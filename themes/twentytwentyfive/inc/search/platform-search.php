<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Platform Search Resolver
 *
 * Since version: 1.8.0
 * Feature: platform-search
 *
 * Purpose:
 * --------
 * Provides a deterministic, human-aware search resolution layer for the
 * Experts in CMT platform search. Responsible for normalization, intent
 * resolution, and result bucket construction.
 */

require_once get_template_directory() . '/inc/search/platform-search-variables.php';

/**
 * ============================================================
 *  Normalization Layer
 * ============================================================
 */

function eic_platform_search_normalize_input($raw)
{
    $value = strtolower($raw);

    $value = preg_replace("/[^\p{L}\p{N}\s\-]/u", " ", $value);
    $value = str_replace(["-", "_"], " ", $value);
    $value = preg_replace("/\s+/", " ", $value);
    $value = trim($value);

    return $value;
}

/**
 * ============================================================
 *  Resolver Entry Point
 * ============================================================
 */

function eic_platform_search_resolve($raw_query)
{
    $query_normalized = eic_platform_search_normalize_input($raw_query);

    $intent_payload = eic_platform_search_resolve_intent($query_normalized);

    $results = eic_platform_search_build_results($intent_payload, $query_normalized);

    return [
        'query_raw'        => $raw_query,
        'query_normalized' => $query_normalized,
        'intent'           => $intent_payload['intent'] ?? null,
        'confidence'       => $intent_payload['confidence'] ?? null,
        'notes'            => $intent_payload['notes'] ?? null,
        'results'          => $results,
    ];
}

/**
 * ============================================================
 *  Intent Resolution
 * ============================================================
 */

function eic_platform_search_resolve_intent($query_normalized)
{
    /**
     * ------------------------------------------------------------
     * Canary aliases (HNPP only — preserved exactly)
     * ------------------------------------------------------------
     */
    $canary_aliases = [
        'hnpp'  => 'hnpp',
        'palsy' => 'hnpp',
    ];

    if (isset($canary_aliases[$query_normalized])) {
        return [
            'intent'     => 'subtype',
            'confidence' => 'high',
            'notes'      => 'canary alias match',
            'subtype'    => get_page_by_path($canary_aliases[$query_normalized], OBJECT, 'subtype'),
        ];
    }

    /**
     * ------------------------------------------------------------
     * Expanded subtype resolution (exact, deterministic)
     * ------------------------------------------------------------
     */

    $candidates = [
        $query_normalized,
        str_replace(' ', '-', $query_normalized),
        str_replace(' ', '', $query_normalized),
    ];

    $candidates = array_unique($candidates);

    $resolved_subtype = null;
    $resolved_source  = null;

    // Priority 1: exact slug match
    foreach ($candidates as $candidate) {
        $post = get_page_by_path($candidate, OBJECT, 'subtype');
        if ($post) {
            $resolved_subtype = $post;
            $resolved_source  = 'slug';
            break;
        }
    }

    // Priority 2: exact title match (normalized)
    if (!$resolved_subtype) {
        $subtypes = get_posts([
            'post_type'      => 'subtype',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($subtypes as $subtype_id) {
            $title_normalized = eic_platform_search_normalize_input(
                get_the_title($subtype_id)
            );

            foreach ($candidates as $candidate) {
                if ($candidate === $title_normalized) {
                    $resolved_subtype = get_post($subtype_id);
                    $resolved_source  = 'title';
                    break 2;
                }
            }
        }
    }

    if ($resolved_subtype) {
        return [
            'intent'     => 'subtype',
            'confidence' => 'high',
            'notes'      => 'exact subtype match (' . $resolved_source . ')',
            'subtype'    => $resolved_subtype,
        ];
    }

    return [];
}

/**
 * ============================================================
 *  Result Builder
 * ============================================================
 */
function eic_platform_search_build_results($payload, $query_normalized)
{
    $results = [
        'subtypes' => [],
        'genes'    => [],
        'types'    => [],
        'content'  => [],
    ];

    /**
     * ------------------------------------------------------------
     * Subtype results (exact intent resolution)
     * ------------------------------------------------------------
     */
    if (
        ($payload['intent'] ?? null) === 'subtype'
        && !empty($payload['subtype'])
        && $payload['subtype'] instanceof WP_Post
    ) {
        $subtype_id = $payload['subtype']->ID;

        $results['subtypes'][] = [
            'id'    => $subtype_id,
            'label' => get_the_title($subtype_id),
            'url'   => get_permalink($subtype_id),
            'type'  => 'Subtype',
        ];

        $gene_symbol = get_field('gene_symbol', $subtype_id);
        if ($gene_symbol) {
            $results['genes'][] = [
                'label' => $gene_symbol,
                'type'  => 'Gene',
            ];
        }

        return $results;
    }

    /**
     * ------------------------------------------------------------
     * Variable-based discovery
     * ------------------------------------------------------------
     */
    $variables = eic_platform_search_variable_subtypes($query_normalized);

    if (empty($variables)) {
        return $results;
    }

    /**
     * ------------------------------------------------------------
     * Semantic payload (multi-bucket)
     * ------------------------------------------------------------
     */
    if (is_array($variables) && isset($variables['subtypes'])) {

        /**
         * --------------------------
         * Subtypes (collection)
         * --------------------------
         */
        foreach ($variables['subtypes'] as $subtype_id) {
            if (!$subtype_id) {
                continue;
            }

            $results['subtypes'][] = [
                'id'    => $subtype_id,
                'label' => get_the_title($subtype_id),
                'url'   => get_permalink($subtype_id),
                'type'  => 'Subtype',
            ];

            $gene_symbol = get_field('gene_symbol', $subtype_id);
            if ($gene_symbol) {
                $results['genes'][] = [
                    'label' => $gene_symbol,
                    'type'  => 'Gene',
                ];
            }
        }

        /**
         * --------------------------
         * Subtypes — canonical order
         * EXACT mirror of Genes DB hierarchy
         * --------------------------
         */
        if (!empty($results['subtypes'])) {

            $canonical_type_order = [
                'CMT1',
                'CMT2',
                'CMT4',
                'CMTX',
                'CMTDI',
                'CMTRI',
                'dHMN',
                'dSMA',
                'GAN',
                'HMSN',
                'HSAN',
                'HSN',
                'SMA-LEP',
                'Unclassified',
            ];

            $type_rank = array_flip($canonical_type_order);

            usort($results['subtypes'], function ($a, $b) use ($type_rank) {

                $a_id = $a['id'] ?? null;
                $b_id = $b['id'] ?? null;

                $a_type = $a_id ? get_post_meta($a_id, 'type_classification', true) : '';
                $b_type = $b_id ? get_post_meta($b_id, 'type_classification', true) : '';

                $a_type = is_string($a_type) ? trim($a_type) : '';
                $b_type = is_string($b_type) ? trim($b_type) : '';

                // 1) NULL / empty last
                $a_empty = ($a_type === '');
                $b_empty = ($b_type === '');
                if ($a_empty !== $b_empty) {
                    return $a_empty ? 1 : -1;
                }

                // 2) Canonical type hierarchy
                $a_rank = $type_rank[$a_type] ?? 9999;
                $b_rank = $type_rank[$b_type] ?? 9999;

                if ($a_rank !== $b_rank) {
                    return $a_rank <=> $b_rank;
                }

                // 3) Stable tie-break: title ASC
                return strcasecmp($a['label'], $b['label']);
            });
        }

        /**
         * --------------------------
         * Types (explicit resolution)
         * --------------------------
         */
        if (!empty($variables['types'])) {

            $type_map = [
                'cmt1'         => 'CMT1',
                'cmt2'         => 'CMT2',
                'cmt4'         => 'CMT4',
                'cmtx'         => 'CMTX',
                'cmtdi'        => 'CMTDI',
                'cmtri'        => 'CMTRI',
                'dhmn'         => 'dHMN',
                'dsma'         => 'dSMA',
                'gan'          => 'GAN',
                'hmsn'         => 'HMSN',
                'hsan'         => 'HSAN',
                'hsn'          => 'HSN',
                'smalep'       => 'SMA-LEP',
                'unclassified' => 'Unclassified',
            ];

            foreach ($variables['types'] as $type) {
                $key = strtolower($type);
                if (isset($type_map[$key])) {
                    $results['types'][] = $type_map[$key];
                }
            }
        }

        /**
         * --------------------------
         * Types (derived from subtypes if empty)
         * --------------------------
         */
        if (empty($results['types']) && !empty($results['subtypes'])) {

            foreach ($results['subtypes'] as $subtype) {
                $subtype_id = $subtype['id'] ?? null;
                if (!$subtype_id) {
                    continue;
                }

                $type = get_post_meta($subtype_id, 'type_classification', true);
                if (is_string($type) && $type !== '') {
                    $results['types'][] = trim($type);
                }
            }
        }

        /**
         * --------------------------
         * Types — canonical hierarchy
         * --------------------------
         */
        if (!empty($results['types'])) {

            $results['types'] = array_values(array_unique($results['types']));

            $canonical_type_order = [
                'CMT1',
                'CMT2',
                'CMT4',
                'CMTX',
                'CMTDI',
                'CMTRI',
                'dHMN',
                'dSMA',
                'GAN',
                'HMSN',
                'HSAN',
                'HSN',
                'SMA-LEP',
                'Unclassified',
            ];

            $results['types'] = array_values(
                array_intersect($canonical_type_order, $results['types'])
            );
        }

        /**
         * --------------------------
         * Content
         * --------------------------
         */
        if (!empty($variables['content']) && is_array($variables['content'])) {
            foreach ($variables['content'] as $content_id) {
                if (!$content_id) {
                    continue;
                }

                $post_type = get_post_type($content_id);
                $pt_obj    = $post_type ? get_post_type_object($post_type) : null;

                $results['content'][] = [
                    'label' => get_the_title($content_id),
                    'url'   => get_permalink($content_id),
                    'type'  => $pt_obj->labels->singular_name ?? 'Content',
                ];
            }
        }

        /**
         * --------------------------
         * Genes — de-dup + A–Z
         * --------------------------
         */
        $genes = array_unique(array_column($results['genes'], 'label'));
        sort($genes, SORT_NATURAL | SORT_FLAG_CASE);

        $results['genes'] = array_map(
            fn($label) => ['label' => $label, 'type' => 'Gene'],
            $genes
        );

        return $results;
    }

    /**
     * ------------------------------------------------------------
     * Basic variable discovery
     * ------------------------------------------------------------
     */
    foreach ($variables as $subtype_id) {
        if (!$subtype_id) {
            continue;
        }

        $results['subtypes'][] = [
            'id'    => $subtype_id,
            'label' => get_the_title($subtype_id),
            'url'   => get_permalink($subtype_id),
            'type'  => 'Subtype',
        ];

        $gene_symbol = get_field('gene_symbol', $subtype_id);
        if ($gene_symbol) {
            $results['genes'][] = [
                'label' => $gene_symbol,
                'type'  => 'Gene',
            ];
        }
    }

    return $results;
}



/**
 * ============================================================
 *  Helper Stubs (Implemented Elsewhere)
 * ============================================================
 */

function get_permalink_by_slug($slug, $post_type)
{
    $post = get_page_by_path($slug, OBJECT, $post_type);

    if (!$post) {
        return "";
    }

    return get_permalink($post->ID);
}
