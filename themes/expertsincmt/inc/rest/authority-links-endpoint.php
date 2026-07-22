<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * EIC REST API — Authority Links Endpoint
 * ------------------------------------------------------------
 * Registers a custom REST endpoint that returns all URL fields
 * from Subtype CPT records for external validation.
 *
 * Endpoint:
 *   GET /wp-json/eic/v1/authority-links
 *
 * Returns:
 *   Array of subtype records, each containing the post title
 *   and all URL ACF fields grouped by tab context.
 *
 * URL fields included:
 *   CTAs:      symptoms_url, what_is_cmtx_url,
 *              what_is_intermediate_url, research_url
 *   Discovery: doi_url, alt_doi_url
 *   Advanced:  clinvar_url, clingen_url, genereviews_url
 *
 * Location:
 *   /inc/rest/authority-links-endpoint.php
 */

add_action("rest_api_init", function () {
    register_rest_route("eic/v1", "/authority-links", [
        "methods"             => "GET",
        "callback"            => "eic_get_authority_links",
        "permission_callback" => "__return_true",
    ]);
});

function eic_get_authority_links() {
    // Cache key folds in the newest subtype modification time, so the
    // cache self-invalidates whenever a subtype is edited or published.
    $last_modified = get_lastpostmodified("blog", "subtype");
    $cache_key = "eic_authority_links_" . md5((string) $last_modified);

    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return rest_ensure_response($cached);
    }

    $query = new WP_Query([
        "post_type"      => "subtype",
        "post_status"    => "publish",
        "posts_per_page" => -1,
        "orderby"        => "title",
        "order"          => "ASC",
        "fields"         => "ids",
        "no_found_rows"  => true,
    ]);

    $url_fields = [
        // CTAs tab
        "symptoms_url",
        "what_is_cmtx_url",
        "what_is_intermediate_url",
        "research_url",
        // Discovery tab
        "doi_url",
        "alt_doi_url",
        // Advanced tab
        "clinvar_url",
        "clingen_url",
        "genereviews_url",
    ];

    $records = [];

    foreach ($query->posts as $post_id) {
        $record = [
            "post_id" => $post_id,
            "title"   => get_the_title($post_id),
            "urls"    => [],
        ];

        foreach ($url_fields as $field) {
            $value = get_field($field, $post_id);
            if (!empty($value)) {
                $record["urls"][$field] = $value;
            }
        }

        // Only include records that have at least one URL to check
        if (!empty($record["urls"])) {
            $records[] = $record;
        }
    }

    // Cache for 12 hours; the modified-time key handles earlier invalidation.
    set_transient($cache_key, $records, 12 * HOUR_IN_SECONDS);

    return rest_ensure_response($records);
}