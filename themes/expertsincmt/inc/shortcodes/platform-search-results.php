<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Platform Search Results Shortcode
 *
 * Since version: 1.8.0
 * Feature: platform-search
 */

if (!defined("ABSPATH")) {
    exit();
}

function eic_platform_search_results_shortcode()
{
    if (!function_exists("eic_platform_search_resolve")) {
        return "<pre>Resolver missing</pre>";
    }

    if (!function_exists("eic_render_platform_search_results")) {
        return "<pre>Renderer missing</pre>";
    }

    $query = isset($_GET["s"]) ? trim($_GET["s"]) : "";

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

foreach (["subtypes", "genes", "types", "content"] as $bucket) {
    if (!empty($results[$bucket])) {
        $has_results = true;
        break;
    }
}

// NO RESULTS → message only (no heading)
if (!$has_results) {
    return
     '<div class="ps-no-results">
        <p class="ps-no-results-title">
            No records matched your search terms.
        </p>
        <p class="ps-no-results-subtitle">
            Search again using different terms.
        </p>
    </div>';
}

// RESULTS EXIST → build heading
$heading  = '<div class="ps-results-heading-wrap">';
$heading .= '<h2 class="ps-results-heading">Search results for “' . esc_html($query) . '”</h2>';
$heading .= '</div>';


    // Pass FLATTENED results to renderer
    return $heading . eic_render_platform_search_results($payload["results"]);
}

add_shortcode(
    "platform_search_results",
    "eic_platform_search_results_shortcode"
);
