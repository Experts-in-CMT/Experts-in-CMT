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
 * Shared taxonomy-filter resolver for the AJAX loop stacks
 * ------------------------------------------------------------
 * Lets a filter param arrive as either a term slug (new clean
 * URLs) or a numeric term_id (legacy / back-compat) and resolves
 * it into a WP_Query tax_query field/value pair transparently.
 *
 * Used by the Genes and Dorsal Root loops (page-load + AJAX).
 * Glossary has no taxonomy term filter and does not use this.
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("eic_resolve_tax_field")) {
    /**
     * Normalize a single taxonomy filter value.
     *
     * @param string|int|array $value    Raw request value (slug or term_id).
     * @param string           $taxonomy Taxonomy to disambiguate against.
     * @return array|null ['field' => 'term_id'|'slug', 'value' => int|string]
     *                    or null when the value is empty / unselected.
     */
    function eic_resolve_tax_field($value, $taxonomy = "")
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        $value = trim((string) $value);

        if ($value === "" || $value === "0") {
            return null;
        }

        // Prefer a real slug match first. This is essential for taxonomies
        // whose slugs are numeric (e.g. chromosome "10"), which would
        // otherwise be misread as a term_id. Fall back to term_id only when
        // no slug matches — that path preserves legacy numeric links.
        $slug = sanitize_title($value);
        if ($taxonomy !== "" && get_term_by("slug", $slug, $taxonomy)) {
            return ["field" => "slug", "value" => $slug];
        }

        if (ctype_digit($value)) {
            return ["field" => "term_id", "value" => (int) $value];
        }

        return ["field" => "slug", "value" => $slug];
    }
}

if (!function_exists("eic_build_tax_slug_map")) {
    /**
     * Build a { paramKey: { term_id: slug } } map for localizing to JS,
     * so the front end can translate a select's term_id value into a slug
     * without touching the filter form markup. Keyed by the URL param name
     * (which may differ from the taxonomy, e.g. dr_cat → dorsal-root).
     *
     * @param array $param_tax_map [ paramKey => taxonomy ]
     * @return array
     */
    function eic_build_tax_slug_map(array $param_tax_map)
    {
        $out = [];
        foreach ($param_tax_map as $param_key => $tax) {
            $terms = get_terms([
                "taxonomy" => $tax,
                "hide_empty" => false,
            ]);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            $map = [];
            foreach ($terms as $t) {
                $map[(string) $t->term_id] = $t->slug;
            }
            $out[$param_key] = $map;
        }
        return $out;
    }
}
