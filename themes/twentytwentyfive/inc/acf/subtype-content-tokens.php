<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Subtype — Content Token Substitution
 * ------------------------------------------------------------
 * Replaces %token% placeholders in subtype post_content with
 * ACF field values at render time. Scoped to subtype CPT only.
 *
 * Location:
 *   /inc/acf/subtype-content-tokens.php
 */

defined('ABSPATH') || exit();

add_filter('the_content', function ($content) {
    if (!is_singular('subtype')) {
        return $content;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return $content;
    }

    $unknown_gene = (bool) get_field('unknown_gene', $post_id);
    $gene_symbol = trim((string) get_field('gene_symbol', $post_id));
    if ($gene_symbol === '') {
        $gene_symbol = trim((string) get_field('gene', $post_id));
    }

    $tokens = [
        '%subtype%'          => get_the_title($post_id),
        '%gene%'             => $unknown_gene || $gene_symbol === '' ? 'an unknown gene' : $gene_symbol,
        '%full_gene_name%'   => trim((string) get_field('full_gene_name', $post_id)),
        '%gene_alias%'       => trim((string) get_field('gene_alias', $post_id)),
        '%inheritance%'      => trim((string) get_field('inheritance', $post_id)),
        '%neuropathy%'       => trim((string) get_field('neuropathy', $post_id)),
        '%chromosome%'       => trim((string) get_field('chromosome', $post_id)),
        '%zygosity%'         => trim((string) get_field('zygosity', $post_id)),
        '%type%'             => strtoupper(trim((string) get_field('type_classification', $post_id))),
        '%acronym%'          => trim((string) get_field('acronym', $post_id)),
        '%year%'             => (string) get_field('year_of_discovery', $post_id),
    ];

    return str_replace(array_keys($tokens), array_values($tokens), $content);
});