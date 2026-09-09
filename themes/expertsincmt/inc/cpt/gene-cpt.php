<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  CPT: Gene
 * ------------------------------------------------------------
 *  A gene post is a shell: title is the HGNC symbol, slug is the
 *  symbol lowercased, and it carries no fields of its own. Every
 *  value shown on a gene page is projected at render time from the
 *  published subtype records whose `gene_symbol` matches, the same
 *  way the Gene Browser resolves a row. The post exists so a gene
 *  has a permalink, a post ID, and a place in the search index.
 *
 *  Nothing on the subtype store changes to accommodate it.
 *
 *  URL:      /genetics/gene/{symbol}/
 *  Creation: Tools > Gene Posts (bulk, dry run then commit), plus a
 *            hook on subtype save that creates the missing post for
 *            a newly published gene so the set never goes stale.
 *
 *  Helpers:
 *    eic_gene_slug_for_symbol(string $symbol): string
 *    eic_gene_post_registry(?WP_Post $add = null): array
 *    eic_gene_post_for_symbol(string $symbol): ?WP_Post
 *    eic_gene_post_url(string $symbol): string
 *    eic_gene_symbol_is_plausible(string $symbol): bool
 *    eic_gene_ensure_post(string $symbol, bool $commit): array
 *    eic_gene_fill_banner(int $post_id, string $symbol, bool $commit): string
 *    eic_gene_projection(string $symbol): ?array
 *
 *  Location: /inc/cpt/gene-cpt.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_register_gene_cpt");
function eic_register_gene_cpt()
{
    register_post_type("gene", [
        "labels" => [
            "name" => "Genes",
            "singular_name" => "Gene",
            "menu_name" => "Genes",
            "all_items" => "All Genes",
            "add_new" => "Add Gene",
            "add_new_item" => "Add New Gene",
            "edit_item" => "Edit Gene",
            "new_item" => "New Gene",
            "view_item" => "View Gene",
            "view_items" => "View Genes",
            "search_items" => "Search Genes",
            "not_found" => "No genes found",
            "not_found_in_trash" => "No genes found in Trash",
            "item_published" => "Gene published.",
            "item_updated" => "Gene updated.",
        ],

        "description" => "One post per CMT disease gene, resolved from the subtype store.",

        "public" => true,
        "publicly_queryable" => true,
        "exclude_from_search" => false,
        "show_ui" => true,
        "show_in_menu" => true,
        "menu_position" => 22,
        "menu_icon" => "dashicons-networking",

        "has_archive" => false,

        // Keeps gene pages inside the existing /genetics/ section beside
        // the browsers and genetic testing, without a parent page.
        "rewrite" => [
            "slug" => "genetics/gene",
            "with_front" => false,
            "feeds" => false,
            "pages" => false,
        ],

        "hierarchical" => false,
        "query_var" => true,

        "show_in_rest" => true,
        "supports" => ["title"],
    ]);
}

/**
 * Slug for a gene symbol: lowercased, sanitized. "MPZ" => "mpz",
 * "HLA-DRB1" => "hla-drb1". Symbols are HGNC-current uppercase as
 * stored on the subtype record.
 */
if (!function_exists("eic_gene_slug_for_symbol")) {
    function eic_gene_slug_for_symbol(string $symbol): string
    {
        $symbol = trim($symbol);
        if (!eic_gene_symbol_is_plausible($symbol)) {
            // EIC counts CMTX3's structural cause as a gene. Its record's
            // gene_symbol is ISCN notation, so its gene post is named by
            // the subtype code instead (title and slug "CMTX3" / "cmtx3").
            $code = eic_gene_structural_code($symbol);
            if ($code !== "") {
                return sanitize_title(strtolower($code));
            }
        }
        return sanitize_title(strtolower($symbol));
    }
}

/**
 * The subtype code carried by the record whose gene_symbol is this
 * non-HGNC string (the structural record), or "" when none.
 */
if (!function_exists("eic_gene_structural_code")) {
    function eic_gene_structural_code(string $symbol): string
    {
        static $cache = [];
        $key = strtoupper(trim($symbol));
        if ($key === "" || $key === "UNKNOWN") {
            return "";
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!function_exists("get_field")) {
            return $cache[$key] = "";
        }
        $ids = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => 1,
            "fields" => "ids",
            "no_found_rows" => true,
            "suppress_filters" => true,
            "meta_query" => [["key" => "gene_symbol", "value" => $key, "compare" => "="]],
        ]);
        $code = "";
        foreach ($ids as $id) {
            if (get_field("unknown_gene", $id)) {
                continue;
            }
            $code = strtoupper(trim((string) get_field("subtype", $id)));
            break;
        }
        return $cache[$key] = $code;
    }
}

/**
 * Plausible HGNC symbol (letters, digits, dot, hyphen, underscore),
 * matching the Gene Browser's test. The structural record's ISCN
 * string fails this; eic_gene_slug_for_symbol() names its post by the
 * subtype code instead.
 */
if (!function_exists("eic_gene_symbol_is_plausible")) {
    function eic_gene_symbol_is_plausible(string $symbol): bool
    {
        $symbol = trim($symbol);
        if ($symbol === "" || strtoupper($symbol) === "UNKNOWN") {
            return false;
        }
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $symbol);
    }
}

/**
 * Per-request registry of gene posts keyed by slug. Loaded in one
 * query on first use (the whole set is a few hundred shell posts),
 * so a Gene Browser render costs one query, not one per row. Any
 * status, so a drafted gene post is still found and the creation
 * tool never duplicates it; callers that need a public URL check
 * post_status themselves (see eic_gene_post_url).
 *
 * Pass a WP_Post to register a post created during this request.
 */
if (!function_exists("eic_gene_post_registry")) {
    function eic_gene_post_registry(?WP_Post $add = null): array
    {
        static $registry = null;
        if ($registry === null) {
            $registry = [];
            $all = get_posts([
                "post_type" => "gene",
                "post_status" => "any",
                "posts_per_page" => -1,
                "no_found_rows" => true,
                "suppress_filters" => true,
            ]);
            foreach ($all as $p) {
                $registry[$p->post_name] = $p;
            }
        }
        if ($add instanceof WP_Post && $add->post_name !== "") {
            $registry[$add->post_name] = $add;
        }
        return $registry;
    }
}

/**
 * The gene post for a symbol, or null. Matches on slug against
 * post_type=gene only (never a page or subtype that happens to share
 * the name).
 */
if (!function_exists("eic_gene_post_for_symbol")) {
    function eic_gene_post_for_symbol(string $symbol): ?WP_Post
    {
        $slug = eic_gene_slug_for_symbol($symbol);
        if ($slug === "") {
            return null;
        }
        $registry = eic_gene_post_registry();
        return $registry[$slug] ?? null;
    }
}

/**
 * Public permalink for a symbol's gene post, or "" when there is no
 * published gene post yet. Callers render plain text on "".
 */
if (!function_exists("eic_gene_post_url")) {
    function eic_gene_post_url(string $symbol): string
    {
        $post = eic_gene_post_for_symbol($symbol);
        if (!$post || $post->post_status !== "publish") {
            return "";
        }
        $url = get_permalink($post);
        return is_string($url) ? $url : "";
    }
}

/**
 * Ensure a gene post exists for a symbol. Returns
 *   ["status" => "exists"|"created"|"would_create"|"skipped", "id" => int|null, "reason" => string]
 * With $commit=false nothing is written (dry run). Never updates an
 * existing post: a gene post holds nothing, so there is nothing to
 * update.
 */
if (!function_exists("eic_gene_ensure_post")) {
    function eic_gene_ensure_post(string $symbol, bool $commit = true): array
    {
        $symbol = trim($symbol);
        if ($symbol === "" || strtoupper($symbol) === "UNKNOWN") {
            return ["status" => "skipped", "id" => null, "reason" => "no gene symbol"];
        }
        // HGNC symbol: the post is the symbol. Structural record: the post
        // is the subtype code (CMTX3), since ISCN notation is not a name.
        $title = eic_gene_symbol_is_plausible($symbol)
            ? strtoupper($symbol)
            : eic_gene_structural_code($symbol);
        if ($title === "") {
            return ["status" => "skipped", "id" => null, "reason" => "no subtype code for structural symbol"];
        }
        $existing = eic_gene_post_for_symbol($symbol);
        if ($existing) {
            return ["status" => "exists", "id" => (int) $existing->ID, "reason" => $existing->post_status];
        }
        if (!$commit) {
            return ["status" => "would_create", "id" => null, "reason" => ""];
        }
        $id = wp_insert_post(
            [
                "post_type" => "gene",
                "post_status" => "publish",
                "post_title" => $title,
                "post_name" => eic_gene_slug_for_symbol($symbol),
            ],
            true
        );
        if (is_wp_error($id) || !$id) {
            return [
                "status" => "skipped",
                "id" => null,
                "reason" => is_wp_error($id) ? $id->get_error_message() : "insert failed",
            ];
        }
        $created = get_post($id);
        if ($created instanceof WP_Post) {
            eic_gene_post_registry($created);
        }
        $banner = eic_gene_fill_banner((int) $id, $symbol, true);
        return ["status" => "created", "id" => (int) $id, "reason" => $banner];
    }
}

/**
 * Header banner for a gene post, echoing what the importers do for a
 * subtype: the Header Banner group's `banner_title` is the page H1
 * (there is no post-title block in the single template), and
 * `banner_intro` sits under it. Fill-if-empty, never overwrites a
 * hand edit. Title is the symbol; intro is the full gene name in
 * italics, read from the gene's subtype records. Returns a short
 * status string for the Tools page.
 */
if (!function_exists("eic_gene_fill_banner")) {
    function eic_gene_fill_banner(int $post_id, string $symbol, bool $commit = true): string
    {
        if ($post_id <= 0 || !function_exists("update_field")) {
            return "";
        }
        $did = [];
        $title_now = trim((string) get_field("banner_title", $post_id));
        if ($title_now === "") {
            if ($commit) {
                $banner_title = eic_gene_symbol_is_plausible(trim($symbol))
                    ? strtoupper(trim($symbol))
                    : eic_gene_structural_code($symbol);
                update_field("field_6792eb48684ab", $banner_title, $post_id);
            }
            $did[] = "title";
        }
        $intro_now = trim((string) get_field("banner_intro", $post_id));
        if ($intro_now === "") {
            $g = eic_gene_projection($symbol);
            $name = $g ? trim((string) $g["full_name"]) : "";
            if ($name !== "") {
                if ($commit) {
                    update_field("field_6792eb80684ac", "<p><em>" . esc_html($name) . "</em></p>", $post_id);
                }
                $did[] = "intro";
            }
        }
        if (!$did) {
            return "";
        }
        return ($commit ? "banner " : "would set banner ") . implode(" + ", $did);
    }
}

/**
 * The gene as the Gene Browser resolves it: one array per symbol,
 * gene-level values from the first published subtype record met
 * (title order) and one entry per subtype record. Same keys as the
 * browser's $genes[$sym] so the two surfaces never disagree on shape.
 * Returns null when no published record carries the symbol.
 * Cached per request per symbol.
 */
if (!function_exists("eic_gene_projection")) {
    function eic_gene_projection(string $symbol): ?array
    {
        static $cache = [];
        $key = strtoupper(trim($symbol));
        if ($key === "") {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!function_exists("get_field")) {
            return $cache[$key] = null;
        }

        $q = new WP_Query([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "orderby" => "title",
            "order" => "ASC",
            "meta_query" => [
                "relation" => "OR",
                [
                    "key" => "gene_symbol",
                    "value" => $key,
                    "compare" => "=",
                ],
                // The structural record's gene post is titled by its
                // subtype code (CMTX3), so that code resolves it too
                [
                    "key" => "subtype",
                    "value" => $key,
                    "compare" => "=",
                ],
            ],
        ]);

        $g = null;
        foreach ($q->posts as $post) {
            $id = $post->ID;
            if (get_field("unknown_gene", $id)) {
                continue;
            }
            $gene = trim((string) get_field("gene_symbol", $id));
            $by_symbol = strtoupper($gene) === $key;
            $by_code =
                strtoupper(trim((string) get_field("subtype", $id))) === $key &&
                $gene !== "" &&
                !eic_gene_symbol_is_plausible($gene);
            if (!$by_symbol && !$by_code) {
                continue;
            }
            $cls = trim((string) get_field("acronym", $id));
            if ($cls === "Unclassified Subtype") {
                $cls = "Unclassified Subtypes";
            }
            $st = [
                "id" => $id,
                "code" => trim((string) (get_field("subtype", $id) ?: get_the_title($id))),
                "slug" => $post->post_name,
                "url" => get_permalink($id),
                "class" => $cls,
                "inheritance" => trim((string) get_field("inheritance", $id)),
                "year" => trim((string) get_field("year_of_discovery", $id)),
                "omim_subtype" => trim((string) get_field("omim_subtype", $id)),
                "pub_title" => trim(wp_strip_all_tags((string) get_field("publication_title", $id))),
                "authors" => trim(wp_strip_all_tags((string) get_field("authors", $id))),
                "doi" => trim((string) get_field("doi_url", $id)),
                "alt_pub_title" => trim(wp_strip_all_tags((string) get_field("alt_publication_title", $id))),
                "alt_doi" => trim((string) get_field("alt_doi_url", $id)),
                "alt_year" => preg_match('/(\d{4})/', (string) get_field("alt_date", $id), $ym) ? $ym[1] : "",
                "candidate" => (bool) get_field("candidate_gene", $id),
            ];

            if ($g === null) {
                $plausible = (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $gene);
                $locus = trim((string) get_field("chromosome", $id));
                $chr = "";
                if (preg_match('/^([0-9]+|X|Y|MT)/', $locus, $m)) {
                    $chr = $m[1];
                }
                $g = [
                    "symbol" => $gene,
                    "structural" => !$plausible,
                    "full_name" => trim((string) get_field("full_gene_name", $id)),
                    "alias" => trim((string) get_field("gene_alias", $id)),
                    "locus" => $locus,
                    "chr" => $chr,
                    "hgnc_id" => trim((string) get_field("hgnc_id", $id)),
                    "ensembl" => trim((string) get_field("ensembl_gene_id", $id)),
                    "grch38" => trim((string) get_field("coords_grch38", $id)),
                    "grch37" => trim((string) get_field("coords_grch37", $id)),
                    "entrez" => trim((string) get_field("entrez_id", $id)),
                    "omim_gene" => trim((string) get_field("omim_gene", $id)),
                    "uniprot" => trim((string) get_field("uniprot_id", $id)),
                    "refseq" => trim((string) get_field("refseq_accession", $id)),
                    "mane_refseq" => trim((string) get_field("mane_select_refseq", $id)),
                    "mane_ensembl" => trim((string) get_field("mane_select_ensembl", $id)),
                    "func" => trim((string) get_field("gene_function", $id)),
                    "clingen" => trim((string) get_field("clingen_url", $id)),
                    "clinvar" => trim((string) get_field("clinvar_url", $id)),
                    "genereviews" => trim((string) get_field("genereviews_url", $id)),
                    "cg_class" => trim((string) get_field("clingen_classification", $id)),
                    "cg_disease" => trim((string) get_field("clingen_disease", $id)),
                    "cg_mondo" => trim((string) get_field("clingen_mondo", $id)),
                    "cg_url" => trim((string) get_field("clingen_validity_url", $id)),
                    "pa_rating" => trim((string) get_field("panelapp_rating", $id)),
                    "pa_votes" => trim((string) get_field("panelapp_votes", $id)),
                    "pa_url" => trim((string) get_field("panelapp_url", $id)),
                    "cg_hi" => trim((string) get_field("clingen_hi", $id)),
                    "cg_ts" => trim((string) get_field("clingen_ts", $id)),
                    "cg_dose_url" => trim((string) get_field("clingen_dosage_url", $id)),
                    "orphanet" => trim((string) get_field("orphanet_url", $id)),
                    "mito" => false,
                    "cand" => false,
                    "genesis" => false,
                    "ars" => false,
                    "classes" => [],
                    "subtypes" => [],
                ];
            }

            $g["subtypes"][] = $st;
            if (get_field("mitochondrial_involvement", $id)) {
                $g["mito"] = true;
            }
            if (get_field("candidate_gene", $id)) {
                $g["cand"] = true;
            }
            if (get_field("genesis_discovery", $id)) {
                $g["genesis"] = true;
            }
            if (get_field("ars_gene", $id)) {
                $g["ars"] = true;
            }
            if ($cls !== "" && !in_array($cls, $g["classes"], true)) {
                $g["classes"][] = $cls;
            }
        }
        wp_reset_postdata();

        if ($g === null) {
            return $cache[$key] = null;
        }

        // Rollups, as the browser derives them.
        usort($g["subtypes"], function ($a, $b) {
            $ya = $a["year"] !== "" ? (int) $a["year"] : 9999;
            $yb = $b["year"] !== "" ? (int) $b["year"] : 9999;
            return $ya === $yb ? strcmp($a["code"], $b["code"]) : $ya - $yb;
        });
        // Inheritance modes exactly as the browser derives them (eic_gb_inh_modes
        // when the shortcode file is loaded; the same map otherwise).
        $modes = [];
        foreach ($g["subtypes"] as $s) {
            if (function_exists("eic_gb_inh_modes")) {
                $found = eic_gb_inh_modes($s["inheritance"]);
            } else {
                $map = [
                    "autosomal dominant" => "AD",
                    "autosomal recessive" => "AR",
                    "x-linked recessive" => "XLR",
                    "x-linked dominant" => "XLD",
                    "mitochondrial inheritance" => "Mito",
                ];
                $found = [];
                foreach (preg_split('/\s+or\s+/i', strtolower(trim($s["inheritance"]))) as $part) {
                    $part = trim($part);
                    if (isset($map[$part]) && !in_array($map[$part], $found, true)) {
                        $found[] = $map[$part];
                    }
                }
            }
            foreach ($found as $m) {
                if (!in_array($m, $modes, true)) {
                    $modes[] = $m;
                }
            }
        }
        $inh_order = ["AD", "AR", "XLR", "XLD", "Mito"];
        usort($modes, fn($a, $b) => array_search($a, $inh_order) - array_search($b, $inh_order));
        $g["modes"] = $g["cand"] ? [] : $modes;
        $g["n"] = count($g["subtypes"]);
        $years = array_filter(array_map(fn($s) => ctype_digit($s["year"]) ? (int) $s["year"] : null, $g["subtypes"]), fn($v) => $v !== null);
        $g["first_year"] = $years ? (string) min($years) : "";

        return $cache[$key] = $g;
    }
}

/**
 * Self-healing: when a subtype is saved as published with a plausible
 * gene_symbol that has no gene post, create one. Runs after ACF has
 * written the fields (acf/save_post at priority 20), so the symbol
 * read here is the one just saved. Silent on the admin side; the
 * Tools page reports the full set on demand.
 */
add_action("acf/save_post", "eic_gene_autocreate_on_subtype_save", 20);
function eic_gene_autocreate_on_subtype_save($post_id)
{
    if (!is_numeric($post_id) || get_post_type($post_id) !== "subtype") {
        return;
    }
    if (get_post_status($post_id) !== "publish") {
        return;
    }
    if (function_exists("get_field") && get_field("unknown_gene", $post_id)) {
        return;
    }
    $symbol = function_exists("get_field") ? (string) get_field("gene_symbol", $post_id) : "";
    if (!eic_gene_symbol_is_plausible($symbol)) {
        return;
    }
    eic_gene_ensure_post($symbol, true);
}
