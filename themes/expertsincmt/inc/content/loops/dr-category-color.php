<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * Dorsal Root — dynamic category color
 * ------------------------------------------------------------
 * Each `dorsal-root` category is assigned a color from a curated
 * pool by its term order, so a newly added category automatically
 * takes the next distinct, on-brand color with no config. A given
 * category keeps its color. The template outputs one CSS custom
 * property (--dr-cat) and dr-loop.css derives the dot, the pill
 * tint, and the pill text from it, so all three stay in sync.
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("eic_dr_category_color")) {
    /**
     * @param int $term_id A dorsal-root term id.
     * @return string Hex color assigned to that category.
     */
    function eic_dr_category_color($term_id)
    {
        static $map = null;

        // Curated, sober, on-brand-adjacent pool. New categories cycle
        // through this if the taxonomy ever grows past its length.
        $pool = [
            "#16416f", // navy
            "#2f8f7a", // teal
            "#c67b3c", // amber
            "#7a5fb0", // violet
            "#c65f52", // coral
            "#4a6fa5", // steel
            "#6f8f3f", // moss
            "#a4577f", // plum
        ];

        if ($map === null) {
            $map = [];
            $terms = get_terms([
                "taxonomy" => "dorsal-root",
                "hide_empty" => false,
                "orderby" => "term_id",
                "order" => "ASC",
                "fields" => "ids",
            ]);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach (array_values($terms) as $i => $tid) {
                    $map[(int) $tid] = $pool[$i % count($pool)];
                }
            }
        }

        return $map[(int) $term_id] ?? $pool[0];
    }
}

if (!function_exists("eic_dr_post_category")) {
    /**
     * Primary dorsal-root category for a post (first assigned term).
     *
     * @param int $post_id
     * @return WP_Term|null
     */
    function eic_dr_post_category($post_id)
    {
        $terms = wp_get_post_terms($post_id, "dorsal-root");
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }
        return $terms[0];
    }
}
