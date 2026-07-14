<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC Subtype Importer (run-once)
 * ------------------------------------------------------------
 * Tools > Subtype Importer
 *
 * Ingests authored subtype records as JSON and creates them. The JSON
 * carries the authored surface (per subtype-import.schema.json) plus the
 * pre-assembled body (post_content, post_excerpt). The importer performs
 * the DETERMINISTIC derivations in one place: identity expansion,
 * taxonomies (dual-written from the controlled fields), the Yoast layer,
 * and schema defaults.
 *
 * Design (see subtype-import-model.md):
 *   - Upsert with field-level diff. Creates a record if its slug is absent;
 *     if it exists, updates only the fields that differ and skips identical
 *     ones. Body (post_content) is included: if it differs, the JSON wins.
 *   - Dry-run first, then commit. Take an FSB before committing.
 *   - Never touches clinvar_url, clingen_url, or banner_image, nor any field
 *     it does not manage, so builder-owned and manual edits are preserved
 *     wherever they already match (or aren't managed by the importer).
 *
 * Accepts: a single record object, a bare array of records, or
 *          { "subtypes": [ ... ] }.
 *
 * Location: wp-content/mu-plugins/eic-subtype-importer.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Subtype_Importer
{
    const CAP   = "manage_options";
    const NONCE = "eic_subtype_importer";
    const HGNC_OPTION = "eic_hgnc_cache"; // shared with the URL builders

    const RESEARCH_LABEL = "CMT Natural History Study";
    const RESEARCH_URL   = "https://cmtausa.org/patients-as-partners/inc-research-study-6601/";
    const INTERMEDIATE_CTA_URL = "https://cmtausa.org/cmt-types/intermediate-cmt/";
    const REVIEWED_BY_NAME = "Experts in CMT";
    const REVIEWED_BY_TYPE = "Organization";
    const METADESC =
        '%%title%% is caused by %%cf_inheritance%% mutations in the %%cf_gene_symbol%% gene. Explore symptoms and learn about symptom onset and disease progression.';
    const SEO_TITLE =
        'What Is %%title%% ? | Charcot-Marie-Tooth Disease | %%sitename%%';

    /* type_classification => [ term/acronym, sort_order ] */
    private static function type_map(): array
    {
        return [
            "cmt1" => ["CMT1", 1],
            "cmt2" => ["CMT2", 2],
            "cmt4" => ["CMT4", 3],
            "cmtx" => ["CMTX", 4],
            "cmtdi" => ["CMTDI", 5],
            "cmtri" => ["CMTRI", 6],
            "dhmn" => ["dHMN/HMN", 7],
            "dsma" => ["dSMA", 8],
            "gan" => ["GAN", 9],
            "hmsn" => ["HMSN", 10],
            "hsan" => ["HSAN", 11],
            "hsn" => ["HSN", 12],
            "smalep" => ["SMA-LEP", 13],
            "unclassified" => ["Unclassified Subtypes", 14],
        ];
    }

    private static function neuropathy_term(string $v): string
    {
        return ["demyelinating" => "Demyelinating", "axonal" => "Axonal", "intermediate" => "Intermediate"][$v] ?? ucfirst($v);
    }

    private static function inheritance_terms(string $v): array
    {
        if ($v === "autosomal dominant or autosomal recessive") {
            return ["autosomal dominant", "autosomal recessive"];
        }
        return [$v];
    }

    private static function chromosome_term(string $locus): string
    {
        if (preg_match('/^(MT|X|Y|[0-9]{1,2})/', trim($locus), $m)) {
            return $m[1];
        }
        return trim($locus);
    }

    /* ---- Bootstrap ---- */

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Subtype Importer",
            "Subtype Importer",
            self::CAP,
            "eic-subtype-importer",
            [__CLASS__, "render"]
        );
    }

    /* ---- Parse + validate ---- */

    /** Normalize input to a list of record arrays. */
    private static function extract_records($json): array
    {
        if (isset($json["subtypes"]) && is_array($json["subtypes"])) {
            return $json["subtypes"];
        }
        if (isset($json["subtype"])) {
            return [$json]; // single record object
        }
        if (is_array($json) && isset($json[0])) {
            return $json; // bare array
        }
        return [];
    }

    private static $ENUM = [
        "type_classification" => ["cmt1","cmt2","cmt4","cmtx","cmtdi","cmtri","dhmn","dsma","gan","hmsn","hsan","hsn","smalep","unclassified"],
        "neuropathy" => ["demyelinating","axonal","intermediate"],
        "inheritance" => ["autosomal dominant","autosomal recessive","autosomal dominant or autosomal recessive","X-linked dominant","X-linked recessive","mitochondrial inheritance"],
    ];

    private static $REQUIRED = [
        "type_classification","subtype","chromosome","neuropathy","zygosity",
        "inheritance","year_of_discovery","publication_title","publication_date",
        "authors","doi_url",
    ];

    /** Return list of error strings for one record ([] = valid). */
    private static function validate(array $r): array
    {
        $e = [];
        foreach (self::$REQUIRED as $f) {
            if (!isset($r[$f]) || $r[$f] === "" || $r[$f] === null) {
                $e[] = "missing required field: {$f}";
            }
        }
        foreach (self::$ENUM as $f => $allowed) {
            if (isset($r[$f]) && $r[$f] !== "" && !in_array($r[$f], $allowed, true)) {
                $e[] = "invalid {$f}: " . $r[$f];
            }
        }
        if (isset($r["publication_date"]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r["publication_date"])) {
            $e[] = "publication_date must be Y-m-d";
        }
        foreach (["omim_subtype","omim_gene"] as $f) {
            if (!empty($r[$f]) && !preg_match('/^\d{6}$/', (string) $r[$f])) {
                $e[] = "{$f} must be a 6-digit MIM number";
            }
        }
        if (empty($r["post_content"])) {
            $e[] = "missing assembled body (post_content)";
        }
        return $e;
    }

    /** Compute the full derived plan for a record (no writes). */
    /* Strict slug -> subtype post lookup.
     * get_page_by_path() can fall through to matching attachments (post_type
     * 'attachment', status 'inherit') that share the slug, which caused a
     * subtype's featured image to be mistaken for the subtype itself. This
     * queries ONLY post_type=subtype and excludes attachments/inherit. */
    private static function find_subtype_by_slug(string $slug)
    {
        $q = new WP_Query([
            "post_type"      => "subtype",
            "name"           => $slug,
            "post_status"    => ["publish", "draft", "pending", "private", "future"],
            "posts_per_page" => 1,
            "no_found_rows"  => true,
            "ignore_sticky_posts" => true,
        ]);
        return $q->have_posts() ? $q->posts[0] : null;
    }

    private static function plan(array $r): array
    {
        $type = $r["type_classification"];
        [$acronym, $sort] = self::type_map()[$type] ?? [strtoupper($type), 99];
        $subtype = $r["subtype"];
        $unknown = !empty($r["unknown_gene"]);
        $gene = $unknown ? "" : trim((string) ($r["gene_symbol"] ?? ""));
        $year = (int) ($r["year_of_discovery"] ?? 0);

        $banner_intro = $gene !== ""
            ? "<p><em>" . esc_html($gene) . "</em> | " . ($year ?: "") . "</p>\n"
            : ($year ? "<p>" . $year . "</p>\n" : "");

        // Yoast synonyms from subtype_alias
        $synonyms = [];
        if (!empty($r["subtype_alias"])) {
            foreach (explode(",", (string) $r["subtype_alias"]) as $a) {
                $a = trim($a);
                if ($a !== "" && strcasecmp($a, $subtype) !== 0) {
                    $synonyms[] = "What Is {$a}?";
                }
            }
        }

        // post_excerpt is the resolved meta-description text (not authored):
        // "{subtype} is caused by {inheritance} mutations in the {gene} gene. <tail>"
        $excerpt_tail = "Explore symptoms and learn about symptom onset and disease progression.";
        if ($unknown || $gene === "") {
            $excerpt = "{$subtype} is a subtype of CMT. " . $excerpt_tail;
        } else {
            $excerpt = "{$subtype} is caused by " . $r["inheritance"] .
                " mutations in the {$gene} gene. " . $excerpt_tail;
        }

        return [
            "slug" => sanitize_title($subtype),
            "acronym" => $acronym,
            "type_sort_order" => $sort,
            "banner_title" => $subtype,
            "banner_intro" => $banner_intro,
            "excerpt" => $excerpt,
            "cmt_type_term" => $acronym,
            "inheritance_terms" => self::inheritance_terms((string) $r["inheritance"]),
            "neuropathy_term" => self::neuropathy_term((string) $r["neuropathy"]),
            "chromosome_term" => self::chromosome_term((string) $r["chromosome"]),
            "focuskw" => "What Is {$subtype}?",
            "synonyms" => implode(", ", $synonyms),
            "intermediate_cta" => in_array($type, ["cmtdi", "cmtri"], true)
                ? self::INTERMEDIATE_CTA_URL
                : "",
            "unknown_gene" => $unknown,
            "gene" => $gene,
        ];
    }

    /* ---- Term resolve/create ---- */

    private static function term_id(string $name, string $taxonomy): int
    {
        $t = get_term_by("name", $name, $taxonomy);
        if ($t && !is_wp_error($t)) {
            return (int) $t->term_id;
        }
        // term_exists returns the id (or [term_id,...]) when the term is present
        $exists = term_exists($name, $taxonomy);
        if ($exists) {
            return (int) (is_array($exists) ? $exists["term_id"] : $exists);
        }
        // In preview (dry-run) never create a term; report as a would-add (0 is
        // safely ignored by set_terms). All valid terms are seeded, so this only
        // guards against side effects during preview.
        if (self::$preview) {
            return 0;
        }
        $ins = wp_insert_term($name, $taxonomy);
        if (is_wp_error($ins)) {
            // On term_exists the existing id is carried in the error data
            $data = $ins->get_error_data();
            if (is_numeric($data)) {
                return (int) $data;
            }
            if (is_array($data) && isset($data["term_id"])) {
                return (int) $data["term_id"];
            }
            return 0;
        }
        return (int) $ins["term_id"];
    }

    /* ---- Render ---- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        echo '<div class="wrap"><h1>Subtype Importer</h1>';
        echo "<p>Paste authored subtype JSON (single record, array, or " .
            "<code>{\"subtypes\":[...]}</code>). Run-once tool: dry-run, then " .
            "commit. <strong>Take a database backup before committing.</strong> " .
            "Does not write clinvar_url, clingen_url, or banner_image (owned by " .
            "their tools).</p>";

        $action = $_POST["eic_action"] ?? "";
        $raw = isset($_POST["json"]) ? (string) wp_unslash($_POST["json"]) : "";

        if ($action && check_admin_referer(self::NONCE)) {
            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<div class="notice notice-error"><p>JSON parse error: ' .
                    esc_html(json_last_error_msg()) . "</p></div>";
            } else {
                $records = self::extract_records($json);
                if (!$records) {
                    echo '<div class="notice notice-error"><p>No records found in JSON.</p></div>';
                } elseif ($action === "dryrun") {
                    self::do_dryrun($records);
                } elseif ($action === "commit") {
                    self::do_commit($records);
                }
            }
        }

        echo '<hr><form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><textarea name="json" rows="16" style="width:100%;font-family:monospace" placeholder="Paste subtype JSON here">' .
            esc_textarea($raw) . "</textarea></p>";
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Run dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have taken a backup and reviewed the dry run.</label></p>';
        echo '<button class="button button-primary" name="eic_action" value="commit">Commit (create records)</button>';
        echo "</form></div>";
    }

    private static function do_dryrun(array $records): void
    {
        echo "<h2>Dry run — " . count($records) . " record(s)</h2>";
        foreach ($records as $i => $r) {
            $label = esc_html($r["subtype"] ?? "record " . ($i + 1));
            $errors = self::validate($r);
            echo '<div style="margin:14px 0;padding:12px 16px;border:1px solid #dcdcde;background:#fff;border-radius:6px">';
            echo "<h3 style='margin-top:0'>" . $label . "</h3>";

            if ($errors) {
                echo '<p style="color:#b32d2e"><strong>Invalid — will be skipped:</strong></p><ul>';
                foreach ($errors as $er) {
                    echo "<li>" . esc_html($er) . "</li>";
                }
                echo "</ul></div>";
                continue;
            }

            $p = self::plan($r);
            $exists = self::find_subtype_by_slug($p["slug"]);
            if ($exists) {
                // Compute the exact diff without writing, using the same code path
                // as commit (preview mode). This guarantees the preview matches
                // what commit will do.
                self::$preview = true;
                self::$changes = 0;
                self::$changed_fields = [];
                self::$diffs = [];
                self::diff_post_fields((int) $exists->ID, $r, $p);
                self::write_record((int) $exists->ID, $r, $p);
                $diffs = self::$diffs;
                $n = self::$changes;
                self::$preview = false;
                self::$diffs = [];

                if ($n === 0) {
                    echo '<p style="color:#1a7f37"><strong>Exists (ID ' . (int) $exists->ID .
                        '). No changes — identical to the record on file.</strong></p>';
                } else {
                    echo '<p style="color:#996800"><strong>Exists (ID ' . (int) $exists->ID .
                        '). Commit will change ' . (int) $n . ' field(s):</strong></p>';
                    echo '<table class="widefat striped"><thead><tr>' .
                        '<th style="width:200px">Field</th><th>Before</th><th>After</th>' .
                        '</tr></thead><tbody>';
                    foreach ($diffs as $d) {
                        echo "<tr><td><strong>" . esc_html($d["field"]) . "</strong></td>" .
                            "<td>" . self::preview_val($d["from"]) . "</td>" .
                            "<td>" . self::preview_val($d["to"]) . "</td></tr>";
                    }
                    echo "</tbody></table>";
                }
            } else {
                echo '<p style="color:#1a7f37"><strong>New. Commit will create it.</strong></p>';
            }

            $rows = [
                "post_title / slug" => $r["subtype"] . " / " . $p["slug"],
                "acronym / sort" => $p["acronym"] . " / " . $p["type_sort_order"],
                "banner_intro" => $p["banner_intro"],
                "cmt_type term" => $p["cmt_type_term"],
                "inheritance term(s)" => implode(" + ", $p["inheritance_terms"]),
                "neuropathy term" => $p["neuropathy_term"],
                "chromosome term" => $p["chromosome_term"],
                "gene fields" => $p["unknown_gene"] ? "NON-WRITE (unknown gene)" : ($p["gene"] !== "" ? $p["gene"] : "(none)"),
                "intermediate CTA" => $p["intermediate_cta"] !== "" ? $p["intermediate_cta"] : "(n/a)",
                "omim_subtype / gene" => ($r["omim_subtype"] ?? "—") . " / " . ($r["omim_gene"] ?? "—"),
                "focus keyword" => $p["focuskw"],
                "SEO title" => self::SEO_TITLE,
                "social/X title" => "echoes SEO title",
                "yoast synonyms" => $p["synonyms"] !== "" ? $p["synonyms"] : "(none)",
                "meta description" => "canonical template (identical across records)",
                "schema defaults" => "medical_audience/specialty, reviewed_by = Experts in CMT",
                "research default" => self::RESEARCH_LABEL,
                "NOT written" => "clinvar_url, clingen_url, banner_image (tool-owned)",
                "post_content" => strlen((string) $r["post_content"]) . " chars (assembled body)",
                "post_excerpt" => $p["excerpt"] . " (derived)",
            ];
            echo '<table class="widefat striped"><tbody>';
            foreach ($rows as $k => $v) {
                echo "<tr><td style='width:220px'><strong>" . esc_html($k) .
                    "</strong></td><td>" . esc_html($v) . "</td></tr>";
            }
            echo "</tbody></table></div>";
        }
    }

    private static function do_commit(array $records): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }

        $created = 0; $updated = 0; $unchanged = 0; $skipped = 0; $failed = 0;
        echo "<h2>Commit</h2><ul>";

        foreach ($records as $r) {
            $label = esc_html($r["subtype"] ?? "(unknown)");
            if (self::validate($r)) {
                echo "<li><strong>{$label}</strong>: invalid, skipped.</li>";
                $skipped++; continue;
            }
            $p = self::plan($r);
            self::$preview = false; // commit always writes
            self::$changes = 0;
            self::$changed_fields = [];
            self::$diffs = [];

            $existing = self::find_subtype_by_slug($p["slug"]);
            $status = $r["editorial"]["overrides"]["post_status"] ?? "publish";

            if (!$existing) {
                // CREATE
                $post_id = wp_insert_post([
                    "post_type" => "subtype",
                    "post_status" => $status,
                    "post_title" => $r["subtype"],
                    "post_name" => $p["slug"],
                    "post_content" => (string) $r["post_content"],
                    "post_excerpt" => $p["excerpt"],
                ], true);
                if (is_wp_error($post_id)) {
                    echo "<li><strong>{$label}</strong>: insert failed — " .
                        esc_html($post_id->get_error_message()) . "</li>";
                    $failed++; continue;
                }
                self::write_record($post_id, $r, $p);
                echo "<li><strong>{$label}</strong>: created (ID {$post_id}).</li>";
                $created++;
            } else {
                // UPSERT — diff only
                $post_id = (int) $existing->ID;
                self::diff_post_fields($post_id, $r, $p);
                self::write_record($post_id, $r, $p);
                if (self::$changes > 0) {
                    $flds = array_slice(array_values(array_unique(self::$changed_fields)), 0, 12);
                    echo "<li><strong>{$label}</strong>: updated (ID {$post_id}), " .
                        self::$changes . " field(s) changed: <small>" .
                        esc_html(implode(", ", $flds)) . "</small></li>";
                    $updated++;
                } else {
                    echo "<li><strong>{$label}</strong>: unchanged (ID {$post_id}).</li>";
                    $unchanged++;
                }
            }
        }

        echo "</ul>";
        echo '<div class="notice notice-success"><p><strong>Done.</strong> ' .
            "Created {$created}, updated {$updated}, unchanged {$unchanged}, " .
            "skipped {$skipped}, failed {$failed}. " .
            "Next: run ClinVar, ClinGen, banner tools, and add any history/sibling links by hand.</p></div>";
    }

    /** Diff and update core post fields (content, excerpt, title, status). */
    private static function diff_post_fields(int $post_id, array $r, array $p): void
    {
        $post = get_post($post_id);
        $update = ["ID" => $post_id];
        $any = false;
        $want_content = (string) $r["post_content"];
        $want_excerpt = $p["excerpt"];
        $want_title = (string) $r["subtype"];
        if ($post->post_content !== $want_content) {
            $update["post_content"] = $want_content; $any = true;
            self::note_change("post_content", $post->post_content, $want_content);
        }
        if (trim((string) $post->post_excerpt) !== trim($want_excerpt)) {
            $update["post_excerpt"] = $want_excerpt; $any = true;
            self::note_change("post_excerpt", $post->post_excerpt, $want_excerpt);
        }
        if ($post->post_title !== $want_title) {
            $update["post_title"] = $want_title; $any = true;
            self::note_change("post_title", $post->post_title, $want_title);
        }
        if ($any && !self::$preview) {
            wp_update_post($update);
        }
    }

    /** Write all ACF, taxonomy, Yoast, and default fields for a new post. */
    private static function write_record(int $post_id, array $r, array $p): void
    {
        $ov = $r["editorial"]["overrides"] ?? [];

        /* Identity + controlled ACF fields */
        self::set_field("subtype", $r["subtype"], $post_id);
        self::set_field("acronym", $p["acronym"], $post_id);
        self::set_field("type_classification", $r["type_classification"], $post_id);
        self::set_field("type_sort_order", $p["type_sort_order"], $post_id);
        self::set_field("chromosome", $r["chromosome"], $post_id);
        self::set_field("neuropathy", $r["neuropathy"], $post_id);
        self::set_field("inheritance", $r["inheritance"], $post_id);
        self::set_field("zygosity", $r["zygosity"], $post_id);
        self::set_field("year_of_discovery", $r["year_of_discovery"], $post_id);

        /* Flags */
        self::set_field("unknown_gene", !empty($r["unknown_gene"]), $post_id);
        self::set_field("ars_gene", !empty($r["ars_gene"]), $post_id);
        self::set_field("mitochondrial_involvement", !empty($r["mitochondrial_involvement"]), $post_id);

        /* Gene identity — NON-WRITE when unknown_gene */
        if (empty($r["unknown_gene"])) {
            if (!empty($r["gene_symbol"])) {
                self::set_field("gene_symbol", $r["gene_symbol"], $post_id);
                self::seed_hgnc_cache((string) $r["gene_symbol"]);
            }
            if (!empty($r["full_gene_name"])) {
                self::set_field("full_gene_name", $r["full_gene_name"], $post_id);
            }
            if (!empty($r["gene_alias"])) {
                self::set_field("gene_alias", $r["gene_alias"], $post_id);
            }
            if (!empty($r["omim_gene"])) {
                self::set_field("omim_gene", $r["omim_gene"], $post_id);
            }
        }

        /* Optional authored */
        foreach (["subtype_alias","omim_subtype","symptoms_url","genereviews_url","publication_note"] as $f) {
            if (!empty($r[$f])) {
                self::set_field($f, $r[$f], $post_id);
            }
        }

        /* Citation block (required) */
        self::set_field("publication_title", $r["publication_title"], $post_id);
        self::set_field("authors", $r["authors"], $post_id);
        self::set_field("publication_date", $r["publication_date"], $post_id);
        self::set_field("doi_url", $r["doi_url"], $post_id);

        /* Alt publication block (optional) */
        if (!empty($r["alt_publication"]) && is_array($r["alt_publication"])) {
            foreach ($r["alt_publication"] as $k => $v) {
                if ($v !== "" && $v !== null) {
                    self::set_field($k, $v, $post_id);
                }
            }
        }

        /* Derived intermediate CTA for cmtdi/cmtri types */
        if ($p["intermediate_cta"] !== "") {
            self::set_field("what_is_intermediate_url", $p["intermediate_cta"], $post_id);
        }

        /* Type-specific CTA overrides (optional) */
        if (!empty($r["cta_overrides"]) && is_array($r["cta_overrides"])) {
            foreach ($r["cta_overrides"] as $k => $v) {
                if ($v !== "" && $v !== null) {
                    self::set_field($k, $v, $post_id);
                }
            }
        }

        /* Derived banner fields */
        self::set_field("banner_title", $p["banner_title"], $post_id);
        self::set_field("banner_intro", $p["banner_intro"], $post_id);

        /* Defaults (overridable) */
        self::set_field("research_label", $ov["research_label"] ?? self::RESEARCH_LABEL, $post_id);
        self::set_field("research_url", $ov["research_url"] ?? self::RESEARCH_URL, $post_id);

        /* Schema group defaults */
        self::set_field("medical_audience", $ov["medical_audience"] ?? ["Patient","Clinician","MedicalResearcher"], $post_id);
        self::set_field("medical_specialty", $ov["medical_specialty"] ?? ["Genetic","Neurologic"], $post_id);
        self::set_field("reviewed_by_name", $ov["reviewed_by_name"] ?? self::REVIEWED_BY_NAME, $post_id);
        self::set_field("reviewed_by_type", $ov["reviewed_by_type"] ?? self::REVIEWED_BY_TYPE, $post_id);
        self::set_field("last_reviewed_date", $r["last_reviewed_date"] ?? current_time("Y-m-d"), $post_id);

        /* Taxonomies (dual-written from the controlled fields) */
        $primary = [];
        $primary["cmt_type"] = self::term_id($p["cmt_type_term"], "cmt_type");
        self::set_terms($post_id, [$primary["cmt_type"]], "cmt_type");

        $inh_ids = [];
        foreach ($p["inheritance_terms"] as $n) {
            $inh_ids[] = self::term_id($n, "inheritance");
        }
        self::set_terms($post_id, $inh_ids, "inheritance");
        $primary["inheritance"] = $inh_ids[0] ?? 0;

        $primary["neuropathy"] = self::term_id($p["neuropathy_term"], "neuropathy");
        self::set_terms($post_id, [$primary["neuropathy"]], "neuropathy");

        $primary["chromosome"] = self::term_id($p["chromosome_term"], "chromosome");
        self::set_terms($post_id, [$primary["chromosome"]], "chromosome");

        /* Yoast layer (diff-only).
         * If the record carries editorial.yoast_overrides (a map of Yoast meta
         * key => value), those exact values are written verbatim and the
         * templated default for that key is skipped. Keys not overridden fall
         * back to the template model. This preserves hand-authored production
         * SEO on records that supply it, without changing behavior for records
         * that don't. */
        $yo = $r["editorial"]["yoast_overrides"] ?? [];
        $yo = is_array($yo) ? $yo : [];
        $put = function (string $key, $template) use ($post_id, $yo) {
            if (array_key_exists($key, $yo)) {
                self::set_meta($post_id, $key, $yo[$key]);
            } elseif ($template !== null && $template !== "") {
                self::set_meta($post_id, $key, $template);
            }
        };

        $put("_yoast_wpseo_title", self::SEO_TITLE);
        $put("_yoast_wpseo_opengraph-title", self::SEO_TITLE);
        $put("_yoast_wpseo_twitter-title", self::SEO_TITLE);
        $put("_yoast_wpseo_focuskw", $p["focuskw"]);
        $put("_yoast_wpseo_metadesc", self::METADESC);
        $put("_yoast_wpseo_opengraph-description", self::METADESC);
        $put("_yoast_wpseo_twitter-description", self::METADESC);
        $put("_yoast_wpseo_keywordsynonyms", $p["synonyms"] !== "" ? $p["synonyms"] : null);

        // Any additional Yoast keys supplied only via overrides (e.g. OG/Twitter
        // images) that have no template equivalent are written as-is.
        foreach ($yo as $k => $v) {
            if (strpos($k, "_yoast_wpseo_") === 0) {
                self::set_meta($post_id, $k, $v);
            }
        }

        foreach ($primary as $tax => $tid) {
            if ($tid && !array_key_exists("_yoast_wpseo_primary_" . $tax, $yo)) {
                self::set_meta($post_id, "_yoast_wpseo_primary_" . $tax, $tid);
            }
        }
    }

    /** Assign taxonomy terms only if they differ from current. */
    private static function set_terms(int $post_id, array $term_ids, string $tax): void
    {
        $term_ids = array_values(array_filter(array_map("intval", $term_ids)));
        if (empty($term_ids)) {
            return; // never assign an invalid/zero term
        }
        $current = wp_get_object_terms($post_id, $tax, ["fields" => "ids"]);
        $current = is_wp_error($current) ? [] : array_map("intval", $current);
        sort($term_ids); $cur = $current; sort($cur);
        if ($term_ids !== $cur) {
            if (!self::$preview) {
                wp_set_object_terms($post_id, $term_ids, $tax, false);
            }
            self::note_change(
                "tax:" . $tax,
                implode(",", $cur),
                implode(",", $term_ids)
            );
        }
    }

    /** Update post meta only if it differs from current. */
    private static function set_meta(int $post_id, string $key, $value): void
    {
        $current = get_post_meta($post_id, $key, true);
        if (trim((string) $current) !== trim((string) $value)) {
            if (!self::$preview) {
                update_post_meta($post_id, $key, $value);
            }
            self::note_change($key, $current, $value);
        }
    }

    private static $changes = 0;
    private static $changed_fields = [];
    private static $preview = false;   // when true, setters compute diffs but do not write
    private static $diffs = [];        // [ ['field'=>..,'from'=>..,'to'=>..], ... ]

    /** Format a before/after value for the dry-run diff table:
     *  arrays joined, long strings truncated with a length note, empty shown. */
    private static function preview_val($v): string
    {
        if (is_array($v)) {
            $v = implode(", ", array_map("strval", $v));
        }
        $v = (string) $v;
        if ($v === "") {
            return '<em style="color:#888">(empty)</em>';
        }
        $len = strlen($v);
        if ($len > 160) {
            return esc_html(substr($v, 0, 160)) .
                ' <em style="color:#888">… (' . $len . ' chars)</em>';
        }
        return esc_html($v);
    }

    /** Record a field change (before/after) for both preview and commit. */
    private static function note_change(string $field, $from, $to): void
    {
        self::$changes++;
        self::$changed_fields[] = $field;
        self::$diffs[] = [
            "field" => $field,
            "from"  => $from,
            "to"    => $to,
        ];
    }

    /** Write an ACF field only if it differs from the current value.
     *  Resolves the field key first (reliable on new posts and cross-group
     *  fields). Counts and records the change. */
    private static function set_field($selector, $value, int $post_id): void
    {
        $key = $selector;
        $fo = function_exists("acf_get_field") ? acf_get_field($selector) : null;
        if ($fo && !empty($fo["key"])) {
            $key = $fo["key"];
        }
        $current = get_field($selector, $post_id);
        if (!self::values_equal($current, $value)) {
            if (!self::$preview) {
                update_field($key, $value, $post_id);
            }
            $field = is_string($selector) ? $selector : (string) $key;
            self::note_change($field, $current, $value);
        }
    }

    /** Loose equality for ACF values: arrays compared as sets, scalars as
     *  trimmed strings, booleans normalized. */
    private static function values_equal($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            $a = is_array($a) ? $a : [$a];
            $b = is_array($b) ? $b : [$b];
            $a = array_map("strval", $a);
            $b = array_map("strval", $b);
            sort($a); sort($b);
            return $a === $b;
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        return trim((string) $a) === trim((string) $b);
    }

    /** Seed the shared HGNC cache so the URL builders reuse the symbol. */
    private static function seed_hgnc_cache(string $symbol): void
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return;
        }
        $cache = get_option(self::HGNC_OPTION, []);
        if (!isset($cache[$symbol])) {
            $cache[$symbol] = ["approved" => $symbol];
            update_option(self::HGNC_OPTION, $cache, false);
        }
    }
}

EIC_Subtype_Importer::init();
