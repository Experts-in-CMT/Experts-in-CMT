<?php
/* ============================================================
   GENES DATABASE LOOP SHORTCODE (responsive to filter UI)
   Simplified, reliable search using qs= and ACF/meta/tax filters.
   Keeps all layout + styling from original.
   ============================================================ */

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
        "cmt_type"    => isset($_GET["cmt_type"]) ? (int) $_GET["cmt_type"] : 0,
        "inheritance" => isset($_GET["inheritance"]) ? (int) $_GET["inheritance"] : 0,
        "neuropathy"  => isset($_GET["neuropathy"]) ? (int) $_GET["neuropathy"] : 0,
        "chromosome"  => isset($_GET["chromosome"]) ? (int) $_GET["chromosome"] : 0,
    ];

    /* --------------------------------------------------------
       TAXONOMY FILTERS (AND)
       -------------------------------------------------------- */
    $tax_query = ["relation" => "AND"];
    foreach ($sel as $tax => $id) {
        if ($id) {
            $tax_query[] = [
                "taxonomy" => $tax,
                "field"    => "term_id",
                "terms"    => [$id],
            ];
        }
    }
    if (count($tax_query) === 1) {
        $tax_query = [];
    }

    /* --------------------------------------------------------
       PASS VARIABLES TO FRAGMENT
       -------------------------------------------------------- */
    set_query_var('a', $a);
    set_query_var('tax_query', $tax_query);
    set_query_var('qs', $qs);
    set_query_var('qs_all', $qs_all);
    set_query_var('genes_shortcode_atts', $a); // <— ADD THIS LINE

    /* --------------------------------------------------------
       INNER LOOP (fragment include)
       -------------------------------------------------------- */
    ob_start();
    get_template_part('inc/content/loops/partials/fragment-loop-genes-loop');
    return ob_get_clean();
});
