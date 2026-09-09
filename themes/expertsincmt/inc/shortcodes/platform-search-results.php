<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Platform Search Results Shortcode
 *
 * Since version: 1.8.0
 * Feature: platform-search
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Render the full search-page payload (heading + buckets, or the
 * no-results block) for a given query. Shared by the shortcode
 * (page load) and the AJAX endpoint (live search) so both emit
 * identical markup.
 */
function eic_ps_render_search_page(string $query): string
{
    if (!function_exists("eic_platform_search_resolve")) {
        return "<pre>Resolver missing</pre>";
    }

    if (!function_exists("eic_render_platform_search_results")) {
        return "<pre>Renderer missing</pre>";
    }

    $query = trim($query);

    if ($query === "") {
        return "<p>No search query provided.</p>";
    }

    if (mb_strlen($query) < 2) {
        return "<p>Please enter at least 2 characters.</p>";
    }

// Resolve full payload
$payload = eic_platform_search_resolve($query);

// Extract results
$results = $payload["results"] ?? [];

// Determine if all result buckets are empty
$has_results = false;
$total_results = 0;

foreach (["variants", "subtypes", "genes", "types", "content"] as $bucket) {
    // Preview-trimmed buckets carry their real size in *_total
    $total_results +=
        (int) ($results[$bucket . "_total"] ?? count($results[$bucket] ?? []));

    if (!empty($results[$bucket])) {
        $has_results = true;
    }
}

/**
 * ------------------------------------------------------------
 * Search log (eic-search-tools MU-plugin, when present)
 * ------------------------------------------------------------
 * Query text only — no visitor identity. Logged for page loads
 * AND live-search calls (source distinguishes them), including
 * zero-result searches: those drive the alias table.
 */
if (function_exists("eic_search_log_event")) {
    eic_search_log_event([
        "raw" => $query,
        "normalized" => $payload["query_normalized"] ?? "",
        "resolver" =>
            $payload["notes"] ??
            ($results["_note"] ?? ($has_results ? "unlabeled" : "none")),
        "results" => $total_results,
        "source" => wp_doing_ajax() ? "live" : "page",
    ]);
}

// NO RESULTS → message + "did you mean" suggestions (no heading)
if (!$has_results) {
    $suggestions = function_exists("eic_ps_no_results_suggestions")
        ? eic_ps_no_results_suggestions($query)
        : [];

    $browser_url = function_exists("eic_subtype_browser_page_url")
        ? eic_subtype_browser_page_url()
        : "";

    $html = '<div class="ps-no-results">';
    $html .=
        '<p class="ps-no-results-title">No records matched your search terms.</p>';

    if (!empty($suggestions)) {
        $html .= '<p class="ps-no-results-subtitle">Did you mean:</p>';
        $html .= '<ul class="ps-no-results-suggestions">';
        foreach ($suggestions as $s) {
            $html .=
                '<li><a href="' .
                esc_url($s["url"]) .
                '">' .
                esc_html($s["label"]) .
                "</a></li>";
        }
        $html .= "</ul>";

        if ($browser_url) {
            $html .=
                '<p class="ps-no-results-browse">Or explore every subtype in the <a href="' .
                esc_url($browser_url) .
                '">CMT Subtype Browser</a>.</p>';
        }
    } else {
        $html .=
            '<p class="ps-no-results-subtitle">Search again using different terms.</p>';
    }

    $html .= "</div>";

    /**
     * True dead end (nothing to suggest): a search that returns
     * nothing is a 404 in spirit, so return what the 404 returns —
     * the platform entry-point cards. Always something to click.
     */
    if (empty($suggestions) && shortcode_exists("eic_entry_points")) {
        $html .= do_shortcode(
            '[eic_entry_points set="platform" heading="Explore The Platform"]'
        );
    }

    return $html;
}

// RESULTS EXIST → build heading
$heading  = '<div class="ps-results-heading-wrap">';
$heading .= '<h2 class="ps-results-heading">Search results for “' . esc_html($query) . '”</h2>';

// Typo tier fired: say what the results actually show
if (!empty($results["_corrected"])) {
    $heading .=
        '<p class="ps-corrected">Nothing matched “' .
        esc_html($query) .
        '” exactly. Showing results for <strong>' .
        esc_html($results["_corrected"]) .
        "</strong>.</p>";
}

$heading .= '</div>';


    // Pass FLATTENED results to renderer
    return $heading . eic_render_platform_search_results($payload["results"]);
}

/**
 * Shortcode wrapper: renders the current GET query inside a stable
 * root element that platform-search-ajax.js swaps in place.
 */
function eic_platform_search_results_shortcode()
{
    $query = isset($_GET["s"]) ? trim((string) $_GET["s"]) : "";

    return '<div id="ps-results-root" aria-live="polite">' .
        eic_ps_render_search_page($query) .
        "</div>";
}

add_shortcode(
    "platform_search_results",
    "eic_platform_search_results_shortcode"
);
