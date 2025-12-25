<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Platform Search Variables Layer
 *
 * Since version: 1.8.0
 * Feature: platform-search
 */

if (!defined("ABSPATH")) {
    exit;
}


/**
 * ============================================================
 *  Basic Variable Extension
 * ============================================================
 */
function eic_platform_search_variable_subtypes(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * DEBUG: Function entry
     * ------------------------------------------------------------
     */
    $GLOBALS['eic_ps_debug'][] = [
        'stage' => 'variable_subtypes_enter',
        'query' => $normalized_query,
    ];

    /**
     * ------------------------------------------------------------
     * 1) Semantic variables (explicit, curated meaning)
     * ------------------------------------------------------------
     */
    $semantic = eic_ps_semantic_cmt_1f_2e($normalized_query);


    if (!empty($semantic)) {
        return $semantic;
    }

/**
 * ------------------------------------------------------------
 * Type classification → subtype discovery
 * ------------------------------------------------------------
 */
$type = strtolower(str_replace(' ', '', $normalized_query));

// allow cmt1, cmt2, cmt4, cmtx, etc.
if (preg_match('/^cmt[0-9x]+$/', $type)) {

    $type_matches = get_posts([
        'post_type'      => 'subtype',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'type_classification',
                'value'   => $type,
                'compare' => '=',
            ],
        ],
    ]);

    if (!empty($type_matches)) {
        return [
            'subtypes' => array_values(array_unique($type_matches)),
            'types'    => [$type],
        ];
    }
}


/**
 * ------------------------------------------------------------
 * Gene symbol → subtype discovery
 * ------------------------------------------------------------
 */
$gene_symbol = strtoupper($normalized_query);

$gene_matches = get_posts([
    'post_type'      => 'subtype',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'     => 'gene_symbol',
            'value'   => $gene_symbol,
            'compare' => '=',
        ],
    ],
]);

if (!empty($gene_matches)) {

    $types = [];

    foreach ($gene_matches as $subtype_id) {
        $type = get_post_meta($subtype_id, 'type_classification', true);
        if (is_string($type) && $type !== '') {
            $types[] = strtolower(trim($type));
        }
    }

    return [
        'subtypes' => array_values(array_unique($gene_matches)),
        'types'    => array_values(array_unique($types)),
    ];
}

    /**
     * ------------------------------------------------------------
     * 2) Basic pattern variables (number–letter discovery)
     * ------------------------------------------------------------
     */
    $matches = [];

    /**
     * DEBUG: Regex gate check
     */
    $GLOBALS['eic_ps_debug'][] = [
        'stage' => 'basic_regex_check',
        'regex' => '/^[0-9]+[a-z]$|^[a-z][0-9]+$/',
        'query' => $normalized_query,
        'pass'  => (bool) preg_match('/^[0-9]+[a-z]$|^[a-z][0-9]+$/', $normalized_query),
    ];

    // Only proceed for strict number-letter tokens (e.g. 1a, 2e, x1)
    if (!preg_match('/^[0-9]+[a-z]$|^[a-z][0-9]+$/', $normalized_query)) {
        return [];
    }

    // Fetch all published subtypes
    $subtypes = get_posts([
        'post_type'      => 'subtype',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    
    foreach ($subtypes as $subtype_id) {
        $slug  = get_post_field('post_name', $subtype_id);
        $title = get_the_title($subtype_id);

        $slug_normalized = str_replace(
            ' ',
            '',
            eic_platform_search_normalize_input($slug)
        );

        $title_normalized = str_replace(
            ' ',
            '',
            eic_platform_search_normalize_input($title)
        );

        if (
            strpos($slug_normalized, $normalized_query) !== false ||
            strpos($title_normalized, $normalized_query) !== false
        ) {
            $matches[] = $subtype_id;
        }
    }

    /**
     * DEBUG: Basic variable matches
     */
    $GLOBALS['eic_ps_debug'][] = [
        'stage'   => 'basic_variable_matches',
        'matches' => $matches,
    ];

    return array_values(array_unique($matches));
}

/**
 * ============================================================
 *  Semantic Variable: CMT1F / CMT2E (NEFL)
 * ============================================================
 */
function eic_ps_semantic_cmt_1f_2e(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Normalize semantic token
     * ------------------------------------------------------------
     * Upstream normalization has already:
     * - lowercased
     * - removed punctuation (/, -, _)
     * - collapsed whitespace
     *
     * So we collapse spaces here to match canonical semantic keys.
     */
    $q = str_replace(' ', '', $normalized_query);

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     */
    $matches = [
        '1f2e',
        '2e1f',
        'cmt1f2e',
        'cmt2e1f',
    ];

    if (!in_array($q, $matches, true)) {
        return [];
    }

    return [
        'subtypes' => [
            get_page_by_path('cmt1f', OBJECT, 'subtype')->ID ?? null,
            get_page_by_path('cmt2e', OBJECT, 'subtype')->ID ?? null,
        ],
        'genes' => [
            get_page_by_path('nefl', OBJECT, 'subtype')->ID ?? null,
        ],
        'types' => [
            'cmt1',
            'cmt2',
        ],
        'content' => [
            get_page_by_path('1f-2e', OBJECT, 'what-is-cmt')->ID ?? null,
        ],
        'meta' => [
            'label' => 'CMT1F/CMT2E (NEFL)',
            'note'  => 'semantic variable',
        ],
    ];
}
