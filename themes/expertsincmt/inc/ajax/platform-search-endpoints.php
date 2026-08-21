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
 *  PLATFORM SEARCH AJAX ENDPOINT
 *  ------------------------------------------------------------
 *  Purpose:
 *    Handles live-search requests from platform-search-ajax.js
 *    and returns the SAME markup the [platform_search_results]
 *    shortcode renders on page load, for injection into
 *    #ps-results-root.
 *
 *  Behavior:
 *    - Runs the full resolver pipeline (eic_platform_search_resolve),
 *      including its transient cache, so live search costs the
 *      same as a page load or less.
 *    - Rendering is delegated to eic_ps_render_search_page() so
 *      AJAX and page-load output can never drift apart.
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("wp_ajax_eic_platform_search", "eic_platform_search_endpoint");
add_action(
    "wp_ajax_nopriv_eic_platform_search",
    "eic_platform_search_endpoint"
);

function eic_platform_search_endpoint()
{
    check_ajax_referer("ps_ajax_nonce", "nonce");

    $req = array_merge($_GET, $_POST);
    $query = isset($req["s"]) ? sanitize_text_field((string) $req["s"]) : "";

    $html = function_exists("eic_ps_render_search_page")
        ? eic_ps_render_search_page($query)
        : "<pre>Renderer missing</pre>";

    wp_send_json_success([
        "html" => $html,
        "s" => $query,
    ]);
}
