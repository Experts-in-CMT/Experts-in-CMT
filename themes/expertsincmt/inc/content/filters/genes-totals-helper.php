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
 *  HELPER: GENES TOTALS (FILTER-AWARE, UNPAGED)
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Provides a single, canonical source of truth for
 *      Genes-related totals derived from filter results
 *    - Computes totals from the FULL filtered dataset,
 *      never from paginated loop results
 *
 *  Responsibilities:
 *    - Query published `subtype` posts using provided
 *      filter arguments
 *    - Strip pagination from all queries
 *    - Count:
 *        • Total subtypes
 *        • Unique gene symbols
 *        • Subtypes with unknown genes
 *
 *  Consumers:
 *    - Genes loop fragment
 *    - Genes AJAX endpoint
 *
 *  Constraints:
 *    - No rendering or markup
 *    - No pagination awareness
 *    - No request inspection
 *    - No shortcode logic
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compute filter-aware Genes totals.
 *
 * @param array $query_args WP_Query arguments (filters applied).
 *                          Pagination-related args will be removed.
 *
 * @return array {
 *     @type int $subtypes Total number of matching subtypes.
 *     @type int $genes    Total number of unique gene symbols.
 *     @type int $unknown  Total number of subtypes with unknown genes.
 * }
 */
function eic_get_genes_totals_from_filters(array $query_args) {

    // Remove pagination-related arguments
    unset(
        $query_args['posts_per_page'],
        $query_args['paged'],
        $query_args['page'],
        $query_args['offset']
    );

    // Force unpaged, ID-only query
    $query_args['posts_per_page'] = -1;
    $query_args['fields'] = 'ids';
    $query_args['no_found_rows'] = true;

    // Ensure correct post type and status
    $query_args['post_type'] = 'subtype';
    $query_args['post_status'] = 'publish';

    $q = new WP_Query($query_args);

    if (empty($q->posts)) {
        return [
            'subtypes' => 0,
            'genes'    => 0,
            'unknown'  => 0,
        ];
    }

    $gene_symbols = [];
    $unknown = 0;

    foreach ($q->posts as $post_id) {

        $symbol = get_field('gene_symbol', $post_id);
        $is_unknown = (bool) get_field('unknown_gene', $post_id);

        if ($is_unknown) {
            $unknown++;
        }

        if (!empty($symbol)) {
            $gene_symbols[strtoupper(trim($symbol))] = true;
        }
    }

    return [
        'subtypes' => count($q->posts),
        'genes'    => count($gene_symbols),
        'unknown'  => $unknown,
    ];
}
