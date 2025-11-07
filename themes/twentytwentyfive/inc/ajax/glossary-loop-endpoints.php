<?php
/**
 * Glossary AJAX endpoint
 *
 * File: /inc/ajax/glossary-loop-endpoints.php
 * Purpose: Return the FULL <div id="results">…</div> wrapper for Glossary,
 *          using the exact same rendering path as the non-AJAX page
 *          (via the [glossary_loop] shortcode). This mirrors the DR pipeline
 *          so JS can swap the entire block via outerHTML.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks — Glossary-specific action pair (auth + nopriv)
 */
add_action('wp_ajax_glossary_get_loop', 'eic_glossary_loop_endpoint');
add_action('wp_ajax_nopriv_glossary_get_loop', 'eic_glossary_loop_endpoint');

/**
 * AJAX responder for Glossary loop.
 * Expects:
 *  - nonce    (string) required; must match 'glossary_ajax_nonce'
 *  - alpha    (string) optional; e.g., 'A-E', 'F-J', 'K-O', 'P-T', 'U-Z', '0-9'
 *  - g_sort   (string) optional; e.g., 'title_az', 'title_za', 'oldest', 'newest'
 *  - g_paged  (int)    optional; >=1
 *  - per_page (int)    optional; defaults to shortcode
 *  - qs       (string) optional; search query (title-only search in loop)
 *
 * Returns: JSON { success: true, data: { html: "<div id=\"results\">…</div>" } }
 */
function eic_glossary_loop_endpoint() {
    // 1) Nonce verification
    if (!isset($_POST['nonce']) || !check_ajax_referer('glossary_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Invalid request.'], 400);
    }

    // 2) Collect inputs (POST first, fallback GET)
    $alpha    = isset($_POST['alpha'])    ? strtoupper(trim((string) wp_unslash($_POST['alpha'])))    : (isset($_GET['alpha'])    ? strtoupper(trim((string) wp_unslash($_GET['alpha'])))    : '');
    $g_sort   = isset($_POST['g_sort'])   ? trim((string) wp_unslash($_POST['g_sort']))               : (isset($_GET['g_sort'])   ? trim((string) wp_unslash($_GET['g_sort']))               : '');
    $g_paged  = isset($_POST['g_paged'])  ? max(1, (int) $_POST['g_paged'])                           : (isset($_GET['g_paged'])  ? max(1, (int) $_GET['g_paged'])                           : 1);
    $per_page = isset($_POST['per_page']) ? max(1, (int) $_POST['per_page'])                          : (isset($_GET['per_page']) ? max(1, (int) $_GET['per_page'])                          : 0);
    $qs       = isset($_POST['qs'])       ? trim((string) wp_unslash($_POST['qs']))                   : (isset($_GET['qs'])       ? trim((string) wp_unslash($_GET['qs']))                   : '');

    // 3) Reuse existing Glossary rendering path via [glossary_loop]
    //    Build the GET state expected by glossary-loop.php, assign, render, restore.
    $orig_get = $_GET;

    $new_get = $orig_get;
    $new_get['alpha']    = $alpha;
    $new_get['g_sort']   = $g_sort;
    $new_get['g_paged']  = $g_paged;
    if ($per_page > 0) {
        $new_get['per_page'] = $per_page;
    }
    if ($qs !== '') {
        $new_get['qs'] = $qs;
    } else {
        unset($new_get['qs']); // keep URL/state clean when clearing search
    }

    $_GET = $new_get;

    // Render exactly as the page would
    $full_html = do_shortcode('[glossary_loop]');

    // Restore original GET
    $_GET = $orig_get;

    // 4) Extract the FULL #results wrapper (mirror DR behavior)
    $wrapper_html = eic_glossary_extract_results_wrapper($full_html);
    if ($wrapper_html === null) {
        // Fallback: return full output rather than failing
        $wrapper_html = $full_html;
    }

    wp_send_json_success(['html' => $wrapper_html]);
}

/**
 * Extract the FULL <div id="results">…</div> wrapper from an HTML string.
 * Returns the HTML string (including the outer wrapper), or null if not found.
 */
function eic_glossary_extract_results_wrapper($html) {
    if (!is_string($html) || $html === '') {
        return null;
    }

    $internalErrors = libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    // Ensure UTF-8 handling
    $html_utf8 = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    $doc->loadHTML($html_utf8);

    $xpath   = new DOMXPath($doc);
    $results = $xpath->query('//*[@id="results"]');

    $out = null;
    if ($results && $results->length > 0) {
        $node = $results->item(0);
        // Return the entire wrapper, not innerHTML
        $out  = $doc->saveHTML($node);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    return $out;
}
