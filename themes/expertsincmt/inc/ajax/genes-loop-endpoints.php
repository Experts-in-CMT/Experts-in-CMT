<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
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
  check_ajax_referer( 'genes_ajax_nonce', 'nonce' );

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

    // NOTE: search meta_query is intentionally NOT built here. The
    // fragment (fragment-loop-genes-loop.php) reassigns $args['meta_query']
    // wholesale whenever a search term is present — the same condition
    // under which this endpoint would build one — so anything set here is
    // overwritten and the fragment is the single source of truth for the
    // search field list. $search is still passed through via the `qs`
    // query var below.

    // ============================================================
    // Taxonomy filters (UPDATED TO USE $req, NOT $_POST)
    // ============================================================
    $tax_query = ["relation" => "AND"];
    $tax_keys = ["cmt_type", "inheritance", "neuropathy", "chromosome"];

    foreach ($tax_keys as $tax) {
        if (!isset($req[$tax])) {
            continue;
        }
        $resolved = eic_resolve_tax_field($req[$tax], $tax);
        if ($resolved === null) {
            continue;
        }
        $tax_query[] = [
            "taxonomy" => $tax,
            "field" => $resolved["field"],
            "terms" => $resolved["value"],
        ];
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

    // Gene group flags (checkbox facets) — same shape as page-load
    set_query_var("genes_flags", [
        "mito" => !empty($req["mito"]),
        "ars" => !empty($req["ars"]),
        "unknown" => !empty($req["unknown"]),
        "lof" => !empty($req["lof"]),
        "gof" => !empty($req["gof"]),
    ]);

    set_query_var("genes_args", $args);
    set_query_var("qs", $search);
    set_query_var("gd_sort", $sort);

    ob_start();
    get_template_part("inc/content/loops/partials/fragment-loop-genes-loop");
    $html = ob_get_clean();

    wp_reset_postdata();

    // Facet counts for the state that produced this result set. The
    // filter form lives outside the swapped root, so the counts ride
    // along in the payload and JS updates the labels in place rather
    // than re-rendering the controls (which would drop focus).
    $facets = function_exists("eic_genes_facet_counts")
        ? eic_genes_facet_counts($req)
        : null;

    wp_send_json_success([
        "html" => $html,
        "facets" => $facets,
    ]);
}
