<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC Subtype Migration Importer
 * ------------------------------------------------------------
 * WP-Admin tool:  Tools > Subtype Importer
 *
 * Purpose:
 *   One-time-plus-reruns migration of the Wix "Subtypes Dataset"
 *   and "Subtypes Bibliography" CSV exports into the `subtype` CPT.
 *
 * Design guarantees:
 *   - Local-first, dry-run gated. Nothing is written until you
 *     tick "I have reviewed the dry run" and click Commit.
 *   - Idempotent upsert keyed on the clean subtype name, so the
 *     68 already-loaded records update in place and re-runs never
 *     duplicate.
 *   - Writes BOTH ACF fields (display) AND the four taxonomy terms
 *     (cmt_type, inheritance, neuropathy, chromosome) that the
 *     Genes DB filter queries via tax_query term_id.
 *   - Enriches gene fields (full name, aliases, gene OMIM) from the
 *     HGNC REST API, cached to an option so re-runs do not re-hit.
 *   - Every row is logged: created / updated / skipped / flagged.
 *
 * CSV location:
 *   wp-content/uploads/subtype-migration/
 *     Subtypes+Dataset.csv
 *     Subtypes+Bibliography.csv
 *
 * Location: wp-content/mu-plugins/eic-subtype-importer.php
 */

if (!defined("ABSPATH")) {
    exit();
}

// Admin-only tool: register nothing on front-end requests.
if (!is_admin()) {
    return;
}

final class EIC_Subtype_Importer
{
    const MENU_SLUG   = "eic-subtype-importer";
    const CAP         = "manage_options";
    const NONCE       = "eic_subtype_importer";
    const HGNC_OPTION = "eic_hgnc_cache"; // symbol => {name, alias, omim, approved, prev}
    const CSV_SUBDIR  = "subtype-migration";

    const RESEARCH_LABEL = "CMT NATURAL HISTORY STUDY";
    const RESEARCH_URL   =
        "https://cmtausa.org/patients-as-partners/inc-research-study-6601/";

    /* ---- Lookup tables (from reviewed mapping doc) ---- */

    // Dataset "Title" => ACF type_classification select key
    private static function type_key_map(): array
    {
        return [
            "CMT1" => "cmt1", "CMT2" => "cmt2", "CMT4" => "cmt4",
            "CMTX" => "cmtx", "CMTDI" => "cmtdi", "CMTRI" => "cmtri",
            "dHMN" => "dhmn", "dSMA" => "dsma", "GAN" => "gan",
            "HMSN" => "hmsn", "HSAN" => "hsan", "HSN" => "hsn",
            "SMA-LEP" => "smalep", "Unclassified" => "unclassified",
        ];
    }

    // Dataset "Title" => cmt_type taxonomy term NAME (as seeded)
    private static function type_term_map(): array
    {
        return [
            "CMT1" => "CMT1", "CMT2" => "CMT2", "CMT4" => "CMT4",
            "CMTX" => "CMTX", "CMTDI" => "CMTDI", "CMTRI" => "CMTRI",
            "dHMN" => "dHMN/HMN", "dSMA" => "dSMA", "GAN" => "GAN",
            "HMSN" => "HMSN", "HSAN" => "HSAN", "HSN" => "HSN",
            "SMA-LEP" => "SMA-LEP", "Unclassified" => "Unclassified Subtype",
        ];
    }

    // Wix inheritance => [ ACF select value, [taxonomy term names] ]
    private static function inheritance_map(): array
    {
        return [
            "Autosomal Recessive" => [
                "autosomal recessive", ["autosomal recessive"],
            ],
            "Autosomal Dominant" => [
                "autosomal dominant", ["autosomal dominant"],
            ],
            "X-Linked Recessive" => [
                "X-linked recessive", ["X-linked recessive"],
            ],
            "X-Linked Dominant" => [
                "X-linked dominant", ["X-linked dominant"],
            ],
            "Autosomal Dominant or Autosomal Recessive" => [
                "autosomal dominant or autosomal recessive",
                ["autosomal dominant", "autosomal recessive"],
            ],
            "Mitochondrial DNA" => [
                "mitochondrial inheritance", ["mitochondrial inheritance"],
            ],
        ];
    }

    // Wix neuropathy => [ ACF select value, taxonomy term name ]
    private static function neuropathy_map(): array
    {
        return [
            "Axonal"        => ["axonal", "Axonal"],
            "Demyelinating" => ["demyelinating", "Demyelinating"],
            "Intermediate"  => ["intermediate", "Intermediate"],
        ];
    }

    // Wix "Repeater Zygosity" => ACF zygosity choice string
    private static function zygosity_map(): array
    {
        return [
            "Homozygous" => "Homozygous",
            "Heterozygous" => "Heterozygous",
            "Compound Heterozygous" => "Compound Heterozygous",
            "Homozygous or Compound Heterozygous" =>
                "Homozygous or Compound Heterozygous",
            "Homozygous (Chromosomal Female) or Hemizygous (Chromosomal Male)" =>
                "Hemizygous (Male) / Homozygous (Female)",
            "Heterozygous (Chromosomal Female) or Hemizygous (Chromosomal Male)" =>
                "Hemizygous (Male) / Heterozygous (Female)",
            "Heterozygous, Homozygous, or Compound Heterozygous" =>
                "Heterozygous or Homozygous or Compound Heterozygous",
            "Heterozygous or Homozygous" => "Heterozygous or Homozygous",
            // CMT-ATP6 (MT-ATP6): mtDNA variant, reviewed as heteroplasmic.
            "Mitochondrial" => "Heteroplasmic",
        ];
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
            self::MENU_SLUG,
            [__CLASS__, "render"]
        );
    }

    /* ---- Small helpers ---- */

    private static function csv_dir(): string
    {
        $up = wp_upload_dir();
        return trailingslashit($up["basedir"]) . self::CSV_SUBDIR . "/";
    }

    private static function json_first(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === "") {
            return "";
        }
        if ($raw[0] === "[") {
            $arr = json_decode($raw, true);
            if (is_array($arr) && isset($arr[0])) {
                return trim((string) $arr[0]);
            }
        }
        return $raw;
    }

    // Normalized join key: drop newline + trailing aka/formerly/etc.
    private static function norm_key(string $s): string
    {
        $s = str_replace(["\r", "\n"], " ", $s);
        $s = preg_split(
            '/\s*\((?:aka|formerly|autosomal|point|deletion|archaic)/i',
            $s
        )[0];
        $s = str_replace("/", " ", $s);
        $s = preg_replace('/\s+/', " ", $s);
        return strtoupper(trim($s));
    }

    // Clean subtype name + parsed alias string from the Wix Subtype cell.
    private static function split_subtype(string $raw): array
    {
        $raw = str_replace(["\r", "\n"], " ", trim($raw));
        $name = $raw;
        $alias = "";
        if (preg_match('/^(.*?)\s*\((?:aka|formerly)\s*(.+?)\)\s*$/i', $raw, $m)) {
            $name = trim($m[1]);
            $alias = trim($m[2]);
        } elseif (preg_match('/^(.*?)\s*\((.+?)\)\s*$/', $raw, $m)) {
            // Bare parenthetical (e.g. "(Autosomal Dominant)") -> keep as-is name.
            $name = trim($m[1]);
        }
        return [$name, $alias];
    }

    // "12p24.31" -> "12"; "Xp11.3" -> "X"; "3p22-p24" -> "3";
    // "Mitochondria" -> "MT"
    private static function chromosome_term(string $locus): string
    {
        if (preg_match('/mitochondri/i', $locus)) {
            return "MT";
        }
        if (preg_match('/^\s*(\d{1,2}|X|Y)/i', $locus, $m)) {
            return strtoupper($m[1]) === "X"
                ? "X"
                : (strtoupper($m[1]) === "Y"
                    ? "Y"
                    : (string) intval($m[1]));
        }
        return "";
    }

    private static function to_ymd(string $human): string
    {
        $human = trim($human);
        if ($human === "") {
            return "";
        }
        $ts = strtotime($human);
        return $ts ? gmdate("Y-m-d", $ts) : "";
    }

    private static function read_csv(string $path): array
    {
        $rows = [];
        if (!is_readable($path)) {
            return $rows;
        }
        if (($fh = fopen($path, "r")) === false) {
            return $rows;
        }
        $header = fgetcsv($fh);
        if ($header) {
            // Strip UTF-8 BOM from first header cell.
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

    /* ---- HGNC enrichment (cached) ---- */

    private static function hgnc_lookup(string $symbol): ?array
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return null;
        }
        $cache = get_option(self::HGNC_OPTION, []);
        if (isset($cache[$symbol])) {
            return $cache[$symbol];
        }

        $resp = wp_remote_get(
            "https://rest.genenames.org/fetch/symbol/" . rawurlencode($symbol),
            [
                "timeout" => 15,
                "headers" => ["Accept" => "application/json"],
            ]
        );
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $docs = $body["response"]["docs"] ?? [];
        if (empty($docs)) {
            $out = null;
        } else {
            $g = $docs[0];
            $out = [
                "approved" => $g["symbol"] ?? "",
                "name"     => $g["name"] ?? "",
                "alias"    => array_values(array_filter(array_merge(
                    (array) ($g["alias_symbol"] ?? []),
                    (array) ($g["prev_symbol"] ?? [])
                ))),
                "omim"     => isset($g["omim_id"][0])
                    ? (string) $g["omim_id"][0]
                    : "",
            ];
        }
        $cache[$symbol] = $out;
        update_option(self::HGNC_OPTION, $cache, false);
        return $out;
    }

    /* ---- Build resolved records from the two CSVs ---- */

    private static function build(bool $enrich): array
    {
        $dataset = self::read_csv(self::csv_dir() . "Subtypes+Dataset.csv");
        $bib     = self::read_csv(self::csv_dir() . "Subtypes+Bibliography.csv");

        // Group bibliography rows by normalized subtype key.
        $bib_groups = [];
        foreach ($bib as $b) {
            $bib_groups[self::norm_key($b["Subtype"] ?? "")][] = $b;
        }

        $type_key   = self::type_key_map();
        $type_term  = self::type_term_map();
        $inh_map    = self::inheritance_map();
        $neu_map    = self::neuropathy_map();
        $zyg_map    = self::zygosity_map();

        $records = [];
        foreach ($dataset as $d) {
            $flags = [];

            [$subtype_name, $subtype_alias] = self::split_subtype(
                $d["Subtype"] ?? ""
            );
            $title = trim($d["Title"] ?? "");

            // Type classification + taxonomy.
            $tclass = $type_key[$title] ?? "";
            $tterm  = $type_term[$title] ?? "";
            if ($tclass === "") {
                $flags[] = "Unmapped type: '{$title}'";
            }

            // Inheritance.
            $inh_raw = self::json_first($d["Innheritance"] ?? "");
            $inh_val = "";
            $inh_terms = [];
            if (isset($inh_map[$inh_raw])) {
                [$inh_val, $inh_terms] = $inh_map[$inh_raw];
            } else {
                $flags[] = "Unmapped inheritance: '{$inh_raw}'";
            }

            // Neuropathy.
            $neu_raw = self::json_first($d["Neuropathy"] ?? "");
            $neu_val = "";
            $neu_term = "";
            if (isset($neu_map[$neu_raw])) {
                [$neu_val, $neu_term] = $neu_map[$neu_raw];
            } else {
                $flags[] = "Unmapped neuropathy: '{$neu_raw}'";
            }

            // Zygosity (from Repeater Zygosity, cleaner phrasing).
            $zyg_raw = self::json_first($d["Repeater Zygosity"] ?? "");
            $zyg_val = $zyg_map[$zyg_raw] ?? "";
            if ($zyg_val === "") {
                $flags[] = "Unmapped zygosity: '{$zyg_raw}' (required field)";
            }

            // Chromosome (ACF text = locus; taxonomy = parsed number/X/Y).
            $chr_locus = trim($d["Chromosome"] ?? "");
            $chr_term  = self::chromosome_term($chr_locus);
            if ($chr_term === "") {
                $flags[] = "Unparsed chromosome: '{$chr_locus}'";
            }

            // Gene symbol + HGNC enrichment.
            $gene_symbol = self::json_first($d["Gene"] ?? "");
            $full_gene_name = "";
            $gene_alias = "";
            $omim_gene = "";
            $unknown_gene = 0;

            // Detect a non-symbol value (locus note / "Unknown but Mapped to …").
            if (
                $gene_symbol === "" ||
                strpos($gene_symbol, " ") !== false ||
                preg_match(
                    '/unknown|mapped|locus|duplic|delet|chromosome/i',
                    $gene_symbol
                )
            ) {
                $unknown_gene = 1;
                $gene_symbol = ""; // save-hook clears gene fields when unknown.
                $flags[] = "Unknown gene (flagged, HGNC skipped)";
            }

            if ($enrich && !$unknown_gene && $gene_symbol !== "") {
                $h = self::hgnc_lookup($gene_symbol);
                if ($h === null) {
                    $flags[] = "No HGNC hit for gene '{$gene_symbol}'";
                } else {
                    $full_gene_name = $h["name"];
                    $gene_alias = implode(", ", $h["alias"]);
                    $omim_gene = $h["omim"];
                    if (
                        $h["approved"] !== "" &&
                        strcasecmp($h["approved"], $gene_symbol) !== 0
                    ) {
                        $flags[] =
                            "HGNC-approved symbol '{$h['approved']}' " .
                            "differs from Wix '{$gene_symbol}'";
                    }
                }
            }

            // Publications: choose primary = paper matching Year of Discovery.
            $yod = preg_replace('/\D/', "", $d["Year of Discovery"] ?? "");
            $papers = $bib_groups[self::norm_key($d["Subtype"] ?? "")] ?? [];
            $pub = self::pick_publications($papers, $yod);
            if (empty($papers)) {
                $flags[] = "No bibliography match (hand-map needed)";
            }

            $mito = ($inh_val === "mitochondrial inheritance");

            $records[] = [
                "flags" => $flags,
                "acf" => [
                    "type_classification" => $tclass,
                    "subtype"             => $subtype_name,
                    "subtype_alias"       => $subtype_alias,
                    "gene_symbol"         => $gene_symbol,
                    "full_gene_name"      => $full_gene_name,
                    "gene_alias"          => $gene_alias,
                    "unknown_gene"        => $unknown_gene,
                    "chromosome"          => $chr_locus,
                    "neuropathy"          => $neu_val,
                    "zygosity"            => $zyg_val,
                    "inheritance"         => $inh_val,
                    "mitochondrial_involvement" => $mito ? 1 : 0,
                    "year_of_discovery"   => $yod !== "" ? (int) $yod : "",
                    "omim_subtype"        => preg_replace(
                        '/\D/', "", $d["Subtype OMIM Entry"] ?? ""
                    ),
                    "omim_gene"           => $omim_gene,
                    "symptoms_url"        => trim($d["Phenotype Link"] ?? ""),
                    "what_is_cmtx_url"    => trim($d["What is X-Linked CMT?"] ?? ""),
                    "what_is_intermediate_url" => trim(
                        $d["What is Intermediate CMT Link"] ?? ""
                    ),
                    "research_label"      => self::RESEARCH_LABEL,
                    "research_url"        => self::RESEARCH_URL,
                    // Primary publication.
                    "publication_title"  => $pub["primary"]["title"],
                    "publication_date"   => $pub["primary"]["date"],
                    "authors"            => $pub["primary"]["authors"],
                    "doi_url"            => $pub["primary"]["doi"],
                    // Alt publication.
                    "alt_publication_title" => $pub["alt"]["title"],
                    "alt_date"              => $pub["alt"]["date"],
                    "alt_authors"           => $pub["alt"]["authors"],
                    "alt_doi_url"           => $pub["alt"]["doi"],
                ],
                "terms" => [
                    "cmt_type"    => $tterm !== "" ? [$tterm] : [],
                    "inheritance" => $inh_terms,
                    "neuropathy"  => $neu_term !== "" ? [$neu_term] : [],
                    "chromosome"  => $chr_term !== "" ? [$chr_term] : [],
                ],
            ];
        }
        return $records;
    }

    private static function pick_publications(array $papers, string $yod): array
    {
        $blank = ["title" => "", "date" => "", "authors" => "", "doi" => ""];
        $shape = function ($p) {
            return [
                "title"   => trim($p["Paper Title"] ?? ""),
                "date"    => self::to_ymd($p["Date of Publication"] ?? ""),
                "authors" => trim($p["Authors"] ?? ""),
                "doi"     => trim($p["Source Link"] ?? ""),
            ];
        };
        if (empty($papers)) {
            return ["primary" => $blank, "alt" => $blank];
        }
        // Primary = paper whose Year matches Year of Discovery, else earliest.
        usort($papers, function ($a, $b) {
            return strcmp(
                self::to_ymd($a["Date of Publication"] ?? ""),
                self::to_ymd($b["Date of Publication"] ?? "")
            );
        });
        $primaryIdx = 0;
        foreach ($papers as $i => $p) {
            if (($p["Year"] ?? "") !== "" && $yod !== "" && $p["Year"] === $yod) {
                $primaryIdx = $i;
                break;
            }
        }
        $primary = $shape($papers[$primaryIdx]);
        $alt = $blank;
        foreach ($papers as $i => $p) {
            if ($i !== $primaryIdx) {
                $alt = $shape($p);
                break;
            }
        }
        return ["primary" => $primary, "alt" => $alt];
    }

    /* ---- Find an existing subtype post by clean name ---- */

    private static function find_existing(string $subtype_name): int
    {
        // Match on the ACF `subtype` meta first, then post_title.
        $q = new WP_Query([
            "post_type"      => "subtype",
            "post_status"    => "any",
            "posts_per_page" => 1,
            "fields"         => "ids",
            "meta_query"     => [
                [
                    "key"     => "subtype",
                    "value"   => $subtype_name,
                    "compare" => "=",
                ],
            ],
            "no_found_rows"  => true,
        ]);
        if (!empty($q->posts)) {
            return (int) $q->posts[0];
        }
        // Fallback: exact title match (avoids deprecated get_page_by_title).
        $q2 = new WP_Query([
            "post_type"      => "subtype",
            "post_status"    => "any",
            "posts_per_page" => 1,
            "fields"         => "ids",
            "title"          => $subtype_name,
            "no_found_rows"  => true,
        ]);
        return !empty($q2->posts) ? (int) $q2->posts[0] : 0;
    }

    /* ---- Commit one record ---- */

    private static function commit_record(array $rec, string $status, bool $update_existing): array
    {
        $acf = $rec["acf"];
        $name = $acf["subtype"];
        $existing = self::find_existing($name);

        if ($existing && !$update_existing) {
            return ["action" => "skipped", "id" => $existing, "name" => $name];
        }

        $postarr = [
            "post_type"  => "subtype",
            "post_title" => $name,
            "post_status" => $status,
        ];
        if ($existing) {
            $postarr["ID"] = $existing;
            $post_id = wp_update_post($postarr, true);
            $action = "updated";
        } else {
            $post_id = wp_insert_post($postarr, true);
            $action = "created";
        }
        if (is_wp_error($post_id)) {
            return [
                "action" => "error",
                "id" => 0,
                "name" => $name,
                "msg" => $post_id->get_error_message(),
            ];
        }

        // ACF fields (fires acf/save_post -> auto type_sort_order).
        // Every field in this group uses the key convention "field_" . name,
        // so writing by key is safe and more reliable than by name.
        $date_fields = ["publication_date", "alt_date"];
        foreach ($acf as $field => $value) {
            if ($value === "" || $value === null) {
                continue;
            }
            // ACF date_picker stores canonical Ymd (no dashes).
            if (in_array($field, $date_fields, true)) {
                $value = str_replace("-", "", $value);
            }
            update_field("field_" . $field, $value, $post_id);
        }

        // Taxonomy terms (match existing terms by name; do not create).
        foreach ($rec["terms"] as $tax => $names) {
            if (empty($names)) {
                continue;
            }
            $ids = [];
            foreach ($names as $n) {
                $term = get_term_by("name", $n, $tax);
                if ($term) {
                    $ids[] = (int) $term->term_id;
                }
            }
            if ($ids) {
                wp_set_object_terms($post_id, $ids, $tax, false);
            }
        }

        return ["action" => $action, "id" => (int) $post_id, "name" => $name];
    }

    /* ---- Render admin page ---- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        $dir = self::csv_dir();
        $have_ds  = is_readable($dir . "Subtypes+Dataset.csv");
        $have_bib = is_readable($dir . "Subtypes+Bibliography.csv");

        echo '<div class="wrap"><h1>Subtype Migration Importer</h1>';
        echo "<p>CSV directory: <code>" . esc_html($dir) . "</code></p>";
        echo "<p>Dataset CSV: " . ($have_ds ? "found" : "<strong>missing</strong>") .
            " &nbsp;|&nbsp; Bibliography CSV: " .
            ($have_bib ? "found" : "<strong>missing</strong>") . "</p>";

        if (!$have_ds || !$have_bib) {
            echo '<div class="notice notice-error"><p>Place both CSVs in the directory above, then reload.</p></div></div>';
            return;
        }

        $action = $_POST["eic_action"] ?? "";
        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "dryrun") {
                self::do_dryrun();
            } elseif ($action === "commit") {
                self::do_commit();
            } elseif ($action === "flush_hgnc") {
                delete_option(self::HGNC_OPTION);
                echo '<div class="notice notice-success"><p>HGNC cache cleared.</p></div>';
            }
        }

        // Forms.
        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="dryrun">';
        echo '<button class="button button-primary">Run dry run (no writes)</button>';
        echo "</form>";

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="commit">';
        echo '<p><label><input type="checkbox" name="confirm" value="1" required> I have reviewed the dry run and want to write to the database.</label></p>';
        echo '<p><label>Post status: <select name="status"><option value="draft">draft</option><option value="publish">publish</option></select></label> &nbsp; ';
        echo '<label><input type="checkbox" name="update_existing" value="1" checked> Update existing records (unchecked = skip existing)</label></p>';
        echo '<button class="button button-primary">Commit import</button>';
        echo "</form>";

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="flush_hgnc">';
        echo '<button class="button">Clear HGNC cache</button>';
        echo "</form>";

        echo "</div>";
    }

    private static function do_dryrun(): void
    {
        $records = self::build(true);
        $flagged = 0;
        echo "<h2>Dry run — " . count($records) . " records</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Subtype</th><th>Type</th><th>Gene</th><th>Full name (HGNC)</th>" .
            "<th>Neuro</th><th>Inherit</th><th>Zyg</th><th>Chr term</th>" .
            "<th>Primary paper</th><th>Alt?</th><th>Terms</th><th>Flags</th>" .
            "</tr></thead><tbody>";
        foreach ($records as $r) {
            $a = $r["acf"];
            $t = $r["terms"];
            $termstr = [];
            foreach ($t as $k => $v) {
                if ($v) {
                    $termstr[] = $k . ":" . implode("+", $v);
                }
            }
            if ($r["flags"]) {
                $flagged++;
            }
            $rowstyle = $r["flags"] ? ' style="background:#fff3cd"' : "";
            echo "<tr{$rowstyle}>";
            echo "<td><strong>" . esc_html($a["subtype"]) . "</strong>" .
                ($a["subtype_alias"] ? "<br><small>aka " .
                    esc_html($a["subtype_alias"]) . "</small>" : "") . "</td>";
            echo "<td>" . esc_html($a["type_classification"]) . "</td>";
            echo "<td>" . esc_html($a["gene_symbol"]) . "</td>";
            echo "<td>" . esc_html($a["full_gene_name"]) . "</td>";
            echo "<td>" . esc_html($a["neuropathy"]) . "</td>";
            echo "<td>" . esc_html($a["inheritance"]) . "</td>";
            echo "<td>" . esc_html($a["zygosity"]) . "</td>";
            echo "<td>" . esc_html($t["chromosome"] ? $t["chromosome"][0] : "") . "</td>";
            echo "<td><small>" . esc_html(
                mb_strimwidth(wp_strip_all_tags($a["publication_title"]), 0, 60, "…")
            ) . " (" . esc_html($a["publication_date"]) . ")</small></td>";
            echo "<td>" . ($a["alt_publication_title"] ? "yes" : "") . "</td>";
            echo "<td><small>" . esc_html(implode(" ", $termstr)) . "</small></td>";
            echo "<td><small>" . esc_html(implode("; ", $r["flags"])) . "</small></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p><strong>{$flagged}</strong> record(s) flagged for review (highlighted).</p>";
    }

    private static function do_commit(): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $status = ($_POST["status"] ?? "draft") === "publish" ? "publish" : "draft";
        $update_existing = !empty($_POST["update_existing"]);

        $records = self::build(true);
        $counts = ["created" => 0, "updated" => 0, "skipped" => 0, "error" => 0];
        $log = [];
        foreach ($records as $r) {
            // Skip records missing a required field entirely.
            $req = ["type_classification", "subtype", "chromosome", "neuropathy",
                "zygosity", "inheritance", "year_of_discovery",
                "publication_title", "publication_date", "authors", "doi_url"];
            $missing = [];
            foreach ($req as $f) {
                if ($r["acf"][$f] === "" || $r["acf"][$f] === null) {
                    $missing[] = $f;
                }
            }
            if ($missing) {
                $counts["skipped"]++;
                $log[] = ["skipped (missing: " . implode(",", $missing) . ")",
                    $r["acf"]["subtype"]];
                continue;
            }
            $res = self::commit_record($r, $status, $update_existing);
            $counts[$res["action"]] = ($counts[$res["action"]] ?? 0) + 1;
            $log[] = [$res["action"] . (isset($res["msg"]) ? ": " . $res["msg"] : ""),
                $res["name"], $res["id"] ?? 0];
        }

        echo '<div class="notice notice-success"><p><strong>Import complete.</strong> ' .
            "Created {$counts['created']}, updated {$counts['updated']}, " .
            "skipped {$counts['skipped']}, errors {$counts['error']} " .
            "(status: {$status}).</p></div>";

        echo '<table class="widefat striped"><thead><tr><th>Result</th><th>Subtype</th><th>Post ID</th></tr></thead><tbody>';
        foreach ($log as $row) {
            echo "<tr><td>" . esc_html($row[0]) . "</td><td>" .
                esc_html($row[1]) . "</td><td>" .
                esc_html((string) ($row[2] ?? "")) . "</td></tr>";
        }
        echo "</tbody></table>";
    }
}

EIC_Subtype_Importer::init();
