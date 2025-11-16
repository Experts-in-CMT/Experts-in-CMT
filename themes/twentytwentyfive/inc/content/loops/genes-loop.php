<?php

/*
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
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

/**
 * Custom sorter for 'type_classification' — immutable order.
 * Empties FIRST (debug), then FIELD() sequence, then post_title ASC.
 */
function eic_genes_custom_sort_clauses($clauses, $wp_query)
{
    if (!$wp_query->get("eic_genes_custom_sort")) {
        return $clauses;
    }

    global $wpdb;
    $custom_type_order = [
        "CMT1",
        "CMT2",
        "CMTX",
        "CMT4",
        "CMTDI",
        "CMTRI",
        "dHMN",
        "dSMA",
        "GAN",
        "HMSN",
        "HSAN",
        "HSN",
        "SMA-LEP",
        "Unclassified",
    ];

    // Ensure join alias `mt1` exists for type_classification
    if (strpos($clauses["join"] ?? "", " mt1 ") === false) {
        $clauses["join"] .= " LEFT JOIN {$wpdb->postmeta} mt1
                              ON (mt1.post_id = {$wpdb->posts}.ID AND mt1.meta_key = 'type_classification')";
    }

    // Build FIELD() list
    $quoted = array_map(function ($v) use ($wpdb) {
        return trim($wpdb->prepare("%s", $v), "'");
    }, $custom_type_order);
    $field_list = "'" . implode("','", $quoted) . "'";

    // Empties first → then FIELD() order → then title ASC
    $clauses["orderby"] =
        "CASE WHEN mt1.meta_value IS NULL OR mt1.meta_value = '' THEN 0 ELSE 1 END ASC, " .
        "FIELD(mt1.meta_value, {$field_list}) ASC, " .
        "{$wpdb->posts}.post_title ASC";

    return $clauses;
}

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

    $sel = [
        "cmt_type" => isset($_GET["cmt_type"]) ? (int) $_GET["cmt_type"] : 0,
        "inheritance" => isset($_GET["inheritance"])
            ? (int) $_GET["inheritance"]
            : 0,
        "neuropathy" => isset($_GET["neuropathy"])
            ? (int) $_GET["neuropathy"]
            : 0,
        "chromosome" => isset($_GET["chromosome"])
            ? (int) $_GET["chromosome"]
            : 0,
    ];

    /* --------------------------------------------------------
       TAXONOMY FILTERS (AND)
       -------------------------------------------------------- */
    $tax_query = ["relation" => "AND"];
    foreach ($sel as $tax => $id) {
        if ($id) {
            $tax_query[] = [
                "taxonomy" => $tax,
                "field" => "term_id",
                "terms" => [$id],
            ];
        }
    }
    if (count($tax_query) === 1) {
        $tax_query = [];
    }

    /* --------------------------------------------------------
   PASS VARIABLES TO FRAGMENT
   -------------------------------------------------------- */
    set_query_var("a", $a);
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
