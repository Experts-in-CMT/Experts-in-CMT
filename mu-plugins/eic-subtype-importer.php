<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC Subtype Importer (single canonical CSV)
 * ------------------------------------------------------------
 * Tools > Subtype Import. Paired with the exporter.
 *
 * Contract:
 *   - Row with non-empty post_id: SKIPPED (curated records untouched).
 *   - Blank-post_id row: created if new, UPDATED in place if a subtype
 *     of that name already exists (repair path). New records publish.
 *   - Empty CSV cell skipped, never written.
 *   - Column -> ACF field by EXACT key (see key_overrides).
 *   - Dates Y-m-d -> Ymd. Booleans -> 0/1. Checkboxes split on "|".
 *   - research_label / research_url set to the CMTA constant on every
 *     record the importer writes.
 *   - type_sort_order derived; the four taxonomies assigned by matching
 *     existing seeded terms (verified to exist; combined inheritance ->
 *     both terms; chromosome incl. MT). Never creates terms.
 *   - Final wp_update_post per record so save_post re-fires.
 *   - Dry run writes nothing and flags any taxonomy term that does not
 *     exist, plus missing required fields.
 *
 * CSV: wp-content/uploads/subtype-migration/subtypes-import-ready.csv
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Subtype_Importer
{
    const CAP    = "manage_options";
    const NONCE  = "eic_subtype_import";
    const SUBDIR = "subtype-migration";
    const FILE   = "subtypes-import-ready.csv";

    const RESEARCH_LABEL = "CMT NATURAL HISTORY STUDY";
    const RESEARCH_URL   =
        "https://cmtausa.org/patients-as-partners/inc-research-study-6601/";

    /* Fields whose ACF key is NOT "field_" . name (banner + schema markup). */
    private static function key_overrides(): array
    {
        return [
            "banner_title"       => "field_6792eb48684ab",
            "banner_intro"       => "field_6792eb80684ac",
            "banner_image"       => "field_6792eabc684aa",
            "banner_fade_start"  => "field_eic_banner_fade_start",
            "banner_fade_end"    => "field_eic_banner_fade_end",
            "medical_specialty"  => "field_subtype_specialty",
            "medical_audience"   => "field_subtype_medical_audience",
            "last_reviewed_date" => "field_subtype_last_reviewed",
            "reviewed_by_name"   => "field_subtype_reviewed_by_name",
            "reviewed_by_type"   => "field_subtype_reviewed_by_type",
        ];
    }

    private static function field_key(string $col): string
    {
        $ov = self::key_overrides();
        return $ov[$col] ?? ("field_" . $col);
    }

    private static function date_cols(): array
    {
        return ["publication_date", "alt_date", "updated_date", "last_reviewed_date"];
    }
    private static function bool_cols(): array
    {
        return ["unknown_gene", "mitochondrial_involvement", "ars_gene"];
    }
    private static function checkbox_cols(): array
    {
        return ["medical_specialty", "medical_audience"];
    }
    private static function never_write(): array
    {
        return ["post_id", "type_sort_order", "research_label", "research_url"];
    }

    private static function type_term(): array
    {
        return [
            "cmt1" => "CMT1", "cmt2" => "CMT2", "cmt4" => "CMT4", "cmtx" => "CMTX",
            "cmtdi" => "CMTDI", "cmtri" => "CMTRI", "dhmn" => "dHMN/HMN",
            "dsma" => "dSMA", "gan" => "GAN", "hmsn" => "HMSN", "hsan" => "HSAN",
            "hsn" => "HSN", "smalep" => "SMA-LEP", "unclassified" => "Unclassified Subtypes",
        ];
    }
    private static function type_sort(): array
    {
        return [
            "cmt1" => 1, "cmt2" => 2, "cmt4" => 3, "cmtx" => 4, "cmtdi" => 5,
            "cmtri" => 6, "dhmn" => 7, "dsma" => 8, "gan" => 9, "hmsn" => 10,
            "hsan" => 11, "hsn" => 12, "smalep" => 13, "unclassified" => 14,
        ];
    }
    private static function inh_terms(): array
    {
        return [
            "autosomal dominant" => ["autosomal dominant"],
            "autosomal recessive" => ["autosomal recessive"],
            "X-linked dominant" => ["X-linked dominant"],
            "X-linked recessive" => ["X-linked recessive"],
            "mitochondrial inheritance" => ["mitochondrial inheritance"],
            "autosomal dominant or autosomal recessive" =>
                ["autosomal dominant", "autosomal recessive"],
        ];
    }
    private static function neu_term(): array
    {
        return ["axonal" => "Axonal", "demyelinating" => "Demyelinating", "intermediate" => "Intermediate"];
    }

    private static function chromosome_term(string $locus): string
    {
        if (preg_match('/mitochondri/i', $locus)) {
            return "MT";
        }
        if (preg_match('/^\s*(\d{1,2}|X|Y)/i', $locus, $m)) {
            $v = strtoupper($m[1]);
            return ($v === "X" || $v === "Y") ? $v : (string) intval($m[1]);
        }
        return "";
    }

    private static function required(): array
    {
        return ["type_classification", "subtype", "chromosome", "neuropathy",
            "zygosity", "inheritance", "year_of_discovery",
            "publication_title", "publication_date", "authors", "doi_url"];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page("Subtype Import", "Subtype Import", self::CAP,
            "eic-subtype-import", [__CLASS__, "render"]);
    }

    private static function csv_path(): string
    {
        $up = wp_upload_dir();
        return trailingslashit($up["basedir"]) . self::SUBDIR . "/" . self::FILE;
    }

    private static function read_csv(string $path): array
    {
        $rows = [];
        if (!is_readable($path) || ($fh = fopen($path, "r")) === false) {
            return $rows;
        }
        $header = fgetcsv($fh);
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', "", $header[0]);
            $header = array_map("trim", $header);
        }
        while (($data = fgetcsv($fh)) !== false) {
            if ($header && count($data) === count($header)) {
                $rows[] = array_combine($header, $data);
            }
        }
        fclose($fh);
        return $rows;
    }

    private static function find_existing(string $subtype): int
    {
        $q = new WP_Query([
            "post_type" => "subtype", "post_status" => "any",
            "posts_per_page" => 1, "fields" => "ids", "no_found_rows" => true,
            "meta_query" => [["key" => "subtype", "value" => $subtype, "compare" => "="]],
        ]);
        if (!empty($q->posts)) {
            return (int) $q->posts[0];
        }
        $q2 = new WP_Query([
            "post_type" => "subtype", "post_status" => "any",
            "posts_per_page" => 1, "fields" => "ids", "no_found_rows" => true,
            "title" => $subtype,
        ]);
        return !empty($q2->posts) ? (int) $q2->posts[0] : 0;
    }

    private static function term_exists_name(string $name, string $tax): bool
    {
        $t = get_term_by("name", $name, $tax);
        return $t && !is_wp_error($t);
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        echo '<div class="wrap"><h1>Subtype Import</h1>';
        echo "<p>Choose the canonical import CSV, run a dry run, then commit.</p>";

        $action = $_POST["eic_action"] ?? "";
        if ($action && check_admin_referer(self::NONCE)) {
            $file = "";
            if (
                isset($_FILES["csv_file"]) &&
                $_FILES["csv_file"]["error"] === UPLOAD_ERR_OK &&
                is_uploaded_file($_FILES["csv_file"]["tmp_name"])
            ) {
                $file = $_FILES["csv_file"]["tmp_name"];
            }
            if ($file === "") {
                echo '<div class="notice notice-error"><p>No CSV selected, or the upload failed. Choose a file and try again.</p></div>';
            } elseif ($action === "commit" && empty($_POST["confirm"])) {
                echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            } else {
                self::process($action === "commit", $file);
            }
        }

        echo '<hr><form method="post" enctype="multipart/form-data" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p><label>Import CSV: <input type="file" name="csv_file" accept=".csv" required></label></p>';
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Run dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to create/repair the records.</label></p>';
        echo '<button class="button button-primary" name="eic_action" value="commit">Commit import</button>';
        echo "</form></div>";
    }

    private static function process(bool $commit, string $file): void
    {
        $rows = self::read_csv($file);
        $type_term = self::type_term();
        $type_sort = self::type_sort();
        $inh_terms = self::inh_terms();
        $neu_term = self::neu_term();
        $date_cols = self::date_cols();
        $bool_cols = self::bool_cols();
        $check_cols = self::checkbox_cols();
        $never = self::never_write();
        $required = self::required();

        $counts = ["create" => 0, "update" => 0, "skip_existing" => 0, "flagged" => 0];
        $out = [];

        foreach ($rows as $r) {
            $subtype = trim($r["subtype"] ?? "");
            if (trim($r["post_id"] ?? "") !== "") {
                $counts["skip_existing"]++;
                continue;
            }
            if ($subtype === "") {
                $out[] = ["(blank subtype)", "skip", "", "", ["empty subtype"]];
                $counts["flagged"]++;
                continue;
            }
            $existing = self::find_existing($subtype);

            $flags = [];
            $tc = trim($r["type_classification"] ?? "");
            $cmt = $type_term[$tc] ?? "";
            if ($cmt === "") { $flags[] = "type '{$tc}'"; }
            elseif (!self::term_exists_name($cmt, "cmt_type")) { $flags[] = "cmt_type term missing '{$cmt}'"; }
            $inhv = trim($r["inheritance"] ?? "");
            $inht = $inh_terms[$inhv] ?? [];
            if (!$inht) { $flags[] = "inheritance '{$inhv}'"; }
            else { foreach ($inht as $t) { if (!self::term_exists_name($t, "inheritance")) { $flags[] = "inheritance term missing '{$t}'"; } } }
            $neuv = trim($r["neuropathy"] ?? "");
            $neut = $neu_term[$neuv] ?? "";
            if ($neut === "") { $flags[] = "neuropathy '{$neuv}'"; }
            elseif (!self::term_exists_name($neut, "neuropathy")) { $flags[] = "neuropathy term missing '{$neut}'"; }
            $chrt = self::chromosome_term(trim($r["chromosome"] ?? ""));
            if ($chrt === "") { $flags[] = "chromosome '" . trim($r["chromosome"] ?? "") . "'"; }
            elseif (!self::term_exists_name($chrt, "chromosome")) { $flags[] = "chromosome term missing '{$chrt}'"; }
            foreach ($required as $rf) {
                if (trim($r[$rf] ?? "") === "") { $flags[] = "missing {$rf}"; }
            }
            if ($flags) { $counts["flagged"]++; }

            $termstr = "cmt_type:" . $cmt . " inh:" . implode("+", $inht) . " neuro:" . $neut . " chr:" . $chrt;
            $act = $existing ? ("update #" . $existing) : "create";

            if (!$commit) {
                $out[] = [$subtype, $act, trim($r["gene_symbol"] ?? ""), $termstr, $flags];
                $existing ? $counts["update"]++ : $counts["create"]++;
                continue;
            }

            if ($existing) {
                $post_id = $existing;
            } else {
                $post_id = wp_insert_post([
                    "post_type" => "subtype", "post_title" => $subtype, "post_status" => "publish",
                ], true);
                if (is_wp_error($post_id)) {
                    $out[] = [$subtype, "ERROR", "", "", [$post_id->get_error_message()]];
                    continue;
                }
            }

            foreach ($r as $col => $val) {
                if (in_array($col, $never, true)) { continue; }
                $val = (string) $val;
                if (trim($val) === "") { continue; }
                if (in_array($col, $date_cols, true)) {
                    $val = str_replace("-", "", $val);
                } elseif (in_array($col, $bool_cols, true)) {
                    $val = ($val === "1" || strtolower($val) === "true") ? 1 : 0;
                } elseif (in_array($col, $check_cols, true)) {
                    $val = array_filter(array_map("trim", explode("|", $val)));
                }
                update_field(self::field_key($col), $val, $post_id);
            }

            // Research CTA constant on every record.
            update_field("field_research_label", self::RESEARCH_LABEL, $post_id);
            update_field("field_research_url", self::RESEARCH_URL, $post_id);

            if (isset($type_sort[$tc])) {
                update_field("field_type_sort_order", $type_sort[$tc], $post_id);
            }

            self::set_terms($post_id, "cmt_type", $cmt === "" ? [] : [$cmt]);
            self::set_terms($post_id, "inheritance", $inht);
            self::set_terms($post_id, "neuropathy", $neut === "" ? [] : [$neut]);
            self::set_terms($post_id, "chromosome", $chrt === "" ? [] : [$chrt]);

            wp_update_post(["ID" => $post_id]);

            $out[] = [$subtype, ($existing ? "updated #" : "created #") . $post_id, trim($r["gene_symbol"] ?? ""), $termstr, $flags];
            $existing ? $counts["update"]++ : $counts["create"]++;
        }

        self::render_table($out, $counts, $commit);
    }

    private static function set_terms(int $post_id, string $tax, array $names): void
    {
        $ids = [];
        foreach ($names as $n) {
            $t = get_term_by("name", $n, $tax);
            if ($t && !is_wp_error($t)) { $ids[] = (int) $t->term_id; }
        }
        // Replace terms for this taxonomy (empty array clears; only when we have a resolved set).
        if ($ids) {
            wp_set_object_terms($post_id, $ids, $tax, false);
        }
    }

    private static function render_table(array $out, array $counts, bool $commit): void
    {
        $head = $commit ? "Import complete" : "Dry run";
        echo "<h2>" . esc_html($head) . "</h2>";
        echo '<div class="notice notice-' . ($commit ? "success" : "info") . '"><p>' .
            "Create: <strong>" . $counts["create"] . "</strong> &nbsp; " .
            "Update/repair: <strong>" . $counts["update"] . "</strong> &nbsp; " .
            "Skipped (existing post_id): <strong>" . $counts["skip_existing"] . "</strong> &nbsp; " .
            "Flagged: <strong>" . $counts["flagged"] . "</strong></p></div>";
        echo '<table class="widefat striped"><thead><tr><th>Subtype</th><th>Action</th><th>Gene</th><th>Taxonomy terms</th><th>Flags</th></tr></thead><tbody>';
        foreach ($out as $row) {
            $hi = !empty($row[4]) ? ' style="background:#fff3cd"' : "";
            echo "<tr{$hi}><td><strong>" . esc_html($row[0]) . "</strong></td><td>" .
                esc_html($row[1]) . "</td><td>" . esc_html($row[2]) . "</td><td><small>" .
                esc_html($row[3]) . "</small></td><td><small>" . esc_html(implode("; ", $row[4])) .
                "</small></td></tr>";
        }
        echo "</tbody></table>";
    }
}

EIC_Subtype_Importer::init();
