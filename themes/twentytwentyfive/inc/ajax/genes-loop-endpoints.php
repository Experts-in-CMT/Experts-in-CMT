<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  GENES LOOP AJAX ENDPOINT
 *  ------------------------------------------------------------
 *  Purpose:
 *    Handles AJAX requests initiated by genes-ajax.js and returns
 *    ONLY the rendered inner-loop HTML for injection into
 *    #genes-results-root.
 *
 *  Behavior:
 *    - Unified GET+POST intake for full parity with URL state.
 *    - Applies:
 *         • per_page overrides
 *         • live search (ACF/meta/tax hybrid)
 *         • taxonomy filters (cmt_type, inheritance, neuropathy, chromosome)
 *         • sort logic (canonical FIELD() order unless overridden)
 *         • pagination
 *    - Sets query vars, loads fragment-loop-genes-loop.php, captures
 *      the output, resets postdata, and returns JSON.
 *
 *  Notes:
 *    - Must remain in perfect parity with Dorsal Root and Glossary stacks.
 *    - Canonical sort is inviolable; endpoint must never disrupt
 *      FIELD() ordering unless an explicit gd_sort value is supplied.
 *    - Fragment file lives at:
 *          inc/content/loops/partials/fragment-loop-genes-loop.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

/* ============================================================
   ===================== [ SECTION: HOOK REGISTRATION ] ========
   ============================================================ */

add_action("wp_ajax_genes_get_loop", "eic_genes_loop_endpoint");
add_action("wp_ajax_nopriv_genes_get_loop", "eic_genes_loop_endpoint");

/* ============================================================
   ===================== [ SECTION: ENDPOINT HANDLER ] =========
   ============================================================ */

function eic_genes_loop_endpoint()
{
    if (
        isset($_POST["nonce"]) &&
        !wp_verify_nonce($_POST["nonce"], "genes_ajax_nonce")
    ) {
        wp_send_json_error("nonce_fail", 403);
    }

    // ============================================================
    // Build Query Args — unified GET/POST intake
    // ============================================================

    // Accept BOTH POST (AJAX) and GET (URL state) for full parity
    $req = array_merge($_GET, $_POST);

    $paged = isset($req["gd_paged"]) ? max(1, (int) $req["gd_paged"]) : 1;
    $per_page = isset($req["per_page"]) ? max(1, (int) $req["per_page"]) : 12;
    $search = isset($req["qs"]) ? sanitize_text_field($req["qs"]) : "";
    $sort = isset($req["gd_sort"]) ? sanitize_key($req["gd_sort"]) : "";

    $args = [
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => $per_page,
        "paged" => $paged,
        "orderby" => "title",
        "order" => "ASC",
    ];

    // IMPORTANT: NO $args['s'] HERE. Meta query only.
    if ($search !== "") {
        $args["meta_query"] = [
            "relation" => "OR",
            ["key" => "gene", "value" => $search, "compare" => "LIKE"],
            ["key" => "gene_symbol", "value" => $search, "compare" => "LIKE"],
            ["key" => "subtype", "value" => $search, "compare" => "LIKE"],
            [
                "key" => "year_of_discovery",
                "value" => $search,
                "compare" => "LIKE",
            ],
            [
                "key" => "alternate_gene_1",
                "value" => $search,
                "compare" => "LIKE",
            ],
            [
                "key" => "alternate_gene_2",
                "value" => $search,
                "compare" => "LIKE",
            ],
            [
                "key" => "alternate_gene_3",
                "value" => $search,
                "compare" => "LIKE",
            ],
        ];
    }

    // ============================================================
    // Taxonomy filters (UPDATED TO USE $req, NOT $_POST)
    // ============================================================
    $tax_query = ["relation" => "AND"];
    $tax_keys = ["cmt_type", "inheritance", "neuropathy", "chromosome"];

    foreach ($tax_keys as $tax) {
        if (!empty($req[$tax]) && $req[$tax] !== "0") {
            $tax_query[] = [
                "taxonomy" => $tax,
                "field" => "term_id",
                "terms" => (int) $req[$tax],
            ];
        }
    }

    if (count($tax_query) > 1) {
        $args["tax_query"] = $tax_query;
    }

    /* ------------------------------------------------------------
   Pass to fragment and output JSON
   ------------------------------------------------------------ */

    // Make per_page visible to the fragment, same as shortcode path
    set_query_var("genes_shortcode_atts", ["per_page" => $per_page]);

    // Pass tax_query so fragment logic stays identical to page-load
    set_query_var("tax_query", $args["tax_query"] ?? []);

    set_query_var("genes_args", $args);
    set_query_var("qs", $search);
    set_query_var("gd_sort", $sort);

    ob_start();
    get_template_part("inc/content/loops/partials/fragment-loop-genes-loop");
    $html = ob_get_clean();

    wp_reset_postdata();

    wp_send_json_success(["html" => $html]);
}
