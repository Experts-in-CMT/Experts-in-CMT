<?php

/*
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Genes Database — Loop Shortcode
 * ------------------------------------------------------------
 * Renders the full Genes Database loop for page-load mode.
 * Provides:
 *   • Taxonomy filters (cmt_type, inheritance, neuropathy, chromosome)
 *   • Search via qs=
 *   • Canonical FIELD() sort order (type_classification)
 *   • Sort toolbar (gd_sort)
 *   • Pagination (gd_paged)
 *
 * IMPORTANT:
 * – Do not alter query logic or canonical sort integration here.
 * – AJAX updates hydrate this shortcode through the endpoint.
 * – Fragment rendering logic lives in:
 *       inc/content/loops/partials/fragment-loop-genes-loop.php
 */

/* ============================================================
   ============================================================
   ===================== [ SECTION: SORT LOGIC ] ===============
   ============================================================
   ============================================================ */

/*
 * NOTE: The former debug sorter (eic_genes_custom_sort_clauses, empties-first)
 * was removed. Canonical ordering lives in inc/content/sort/genes-type-order.php
 * (eic_genes_type_ordering_clauses, empties-last), triggered by the
 * `eic_genes_custom_sort` query var.
 */

/* ============================================================
   ============================================================
   ===================== [ SECTION: SHORTCODE ] ================
   ============================================================
   ============================================================ */

add_shortcode("genes_loop", function ($atts = []) {
    $a = shortcode_atts(
        [
            "per_page" => 12,
        ],
        $atts
    );

    /* --------------------------------------------------------
       INPUTS (GET params)
       -------------------------------------------------------- */
    $qs_raw = isset($_GET["qs"]) ? (string) $_GET["qs"] : "";
    $qs = sanitize_text_field($qs_raw);
    $qs_all = strtolower(trim($qs)) === "all";

    /* --------------------------------------------------------
       TAXONOMY FILTERS (AND) — slug-or-id via shared resolver
       -------------------------------------------------------- */
    $tax_keys = ["cmt_type", "inheritance", "neuropathy", "chromosome"];
    $tax_query = ["relation" => "AND"];
    foreach ($tax_keys as $tax) {
        if (!isset($_GET[$tax])) {
            continue;
        }
        $resolved = eic_resolve_tax_field($_GET[$tax], $tax);
        if ($resolved === null) {
            continue;
        }
        $tax_query[] = [
            "taxonomy" => $tax,
            "field" => $resolved["field"],
            "terms" => [$resolved["value"]],
        ];
    }
    if (count($tax_query) === 1) {
        $tax_query = [];
    }

    /* --------------------------------------------------------
       GENE GROUP FLAGS (checkbox facets)
       ACF true/false: mitochondrial_involvement, ars_gene
       -------------------------------------------------------- */
    $genes_flags = [
        "mito" => !empty($_GET["mito"]),
        "ars" => !empty($_GET["ars"]),
        "unknown" => !empty($_GET["unknown"]),
    ];

    // Variant Mechanism (OR facet): selected mechanism values from GET.
    $eic_mech_map = [
        "mech_lof" => "lof",
        "mech_dn" => "dominant_negative",
        "mech_gof" => "gof",
        "mech_complex" => "complex",
        "mech_unknown" => "unknown",
    ];
    $genes_mech = [];
    foreach ($eic_mech_map as $mk => $mv) {
        if (!empty($_GET[$mk])) {
            $genes_mech[] = $mv;
        }
    }

    /* --------------------------------------------------------
   PASS VARIABLES TO FRAGMENT
   -------------------------------------------------------- */
    set_query_var("a", $a);
    set_query_var("genes_flags", $genes_flags);
    set_query_var("genes_mech", $genes_mech);
    set_query_var("tax_query", $tax_query);
    set_query_var("qs", $qs);
    set_query_var("qs_all", $qs_all);
    set_query_var("genes_shortcode_atts", $a);

    // PASS ACTIVE SORT (from URL → fragment)
    $current_sort = isset($_GET["gd_sort"])
        ? sanitize_key($_GET["gd_sort"])
        : "";
    set_query_var("gd_sort", $current_sort);

    /* --------------------------------------------------------
   INNER LOOP (fragment include)
   -------------------------------------------------------- */
    ob_start();
    ?>

<?php
/* ============================================================
   ===================== [ SECTION: SORT TOOLBAR ] =============
   ============================================================ */
$anchor = "results";
$base = strtok($_SERVER["REQUEST_URI"], "?");
$action_url = esc_url($base . "#" . $anchor);
$current_sort = isset($_GET["gd_sort"]) ? sanitize_key($_GET["gd_sort"]) : "";
$keep = $_GET;
unset($keep["gd_paged"]);
$clear_params = $keep;
unset($clear_params["gd_sort"]);
$sort_clear_url =
    esc_url(
        $base . ($clear_params ? "?" . http_build_query($clear_params) : "")
    ) .
    "#" .
    $anchor;
?>


<div class="genes-sort genes-sort--results">
    <form class="genes-sort__form" method="get" action="<?php echo $action_url; ?>">
        <label class="genes-sort__label" for="gd_sort">Sort by</label>
        <select id="gd_sort" name="gd_sort" class="genes-sort__select">
            <option value=""       <?php selected(
                $current_sort,
                ""
            ); ?>>Default</option>
            <option value="gene_az"    <?php selected(
                $current_sort,
                "gene_az"
            ); ?>>Gene A to Z</option>
            <option value="subtype_az" <?php selected(
                $current_sort,
                "subtype_az"
            ); ?>>Subtype A to Z</option>
            <option value="oldest"     <?php selected(
                $current_sort,
                "oldest"
            ); ?>>Oldest to Newest</option>
            <option value="newest"     <?php selected(
                $current_sort,
                "newest"
            ); ?>>Newest to Oldest</option>
        </select>
        <a href="#" class="genes-sort__clear" role="button">CLEAR</a>
        <?php foreach ($keep as $k => $v) {
            if (in_array($k, ["gd_sort", "gd_paged"], true)) {
                continue;
            }
            if (is_scalar($v)) {
                printf(
                    '<input type="hidden" name="%s" value="%s">',
                    esc_attr($k),
                    esc_attr($v)
                );
            }
        } ?>
        <noscript><button type="submit" class="genes-sort__btn">Apply</button></noscript>
    </form>
</div>


<?php
get_template_part("inc/content/loops/partials/fragment-loop-genes-loop");
return ob_get_clean();
});
