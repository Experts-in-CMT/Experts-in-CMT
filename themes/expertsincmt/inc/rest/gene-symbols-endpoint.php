<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * EIC REST API — Gene Symbols Endpoint
 * Lightweight endpoint for AWS Lambda to read gene_symbol
 * values from all published Subtype records.
 *
 * Endpoint: GET /wp-json/eic/v1/gene-symbols
 * Returns:  JSON array of { id, title, gene_symbol } objects
 * Location: /inc/rest/gene-symbols-endpoint.php
 */

defined("ABSPATH") || exit();

add_action("rest_api_init", function () {
    register_rest_route("eic/v1", "/gene-symbols", [
        "methods" => "GET",
        "callback" => "eic_get_gene_symbols",
        "permission_callback" => "__return_true",
    ]);
});

function eic_get_gene_symbols(WP_REST_Request $request)
{
    $query = new WP_Query([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "fields" => "ids",
        "no_found_rows" => true,
    ]);

    if (empty($query->posts)) {
        return rest_ensure_response([]);
    }

    $results = [];

    foreach ($query->posts as $post_id) {
        $unknown = (bool) get_field("unknown_gene", $post_id);
        $symbol = $unknown
            ? null
            : trim((string) get_field("gene_symbol", $post_id));
        $results[] = [
            "id" => $post_id,
            "title" => get_the_title($post_id),
            "gene_symbol" => $symbol !== "" ? $symbol : null,
        ];
    }

    return rest_ensure_response($results);
}
