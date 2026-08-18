<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  Term Helpers — Ordered Retrieval for Filter UIs
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Provide ordered taxonomy term retrieval for all filter UI
 *      components across the expertsincmt project.
 *    - Ensure consistent <select> option ordering using the
 *      numeric 'sort' meta assigned during taxonomy registration.
 *
 *  Provides:
 *    eicmt_get_ordered_terms()
 *       → Returns WP_Term[] sorted by 'sort' meta, fallback to
 *         name ASC when no meta is present.
 *
 *    eicmt_terms_options_html()
 *       → Utility for generating ordered <option> HTML for filter
 *         dropdowns in Genes, Glossary, and Dorsal Root UIs.
 *
 *  Notes:
 *    - Centralizes ordering logic so filters stay predictable.
 *    - Eliminates scattered get_terms() calls with inconsistent
 *      sorting behavior.
 * ============================================================
 */

function eicmt_get_ordered_terms($taxonomy, $args = [])
{
    $defaults = [
        "taxonomy" => $taxonomy,
        "hide_empty" => false,
        "meta_key" => "sort",
        "orderby" => "meta_value_num",
        "order" => "ASC",
    ];

    $terms = get_terms(wp_parse_args($args, $defaults));

    // Graceful fallback if meta order isn't present yet
    if (is_wp_error($terms) || empty($terms)) {
        $fallback = $defaults;
        unset($fallback["meta_key"], $fallback["orderby"]);
        $fallback["orderby"] = "name";
        return get_terms(wp_parse_args($args, $fallback));
    }

    return $terms;
}

/**
 * Utility: build <option> tags for a <select> from ordered terms.
 *
 * @param string       $taxonomy
 * @param string|int[] $selected One or many selected term IDs.
 * @param string       $placeholder Optional first option label (value="").
 * @return string HTML
 */
function eicmt_terms_options_html($taxonomy, $selected = "", $placeholder = "")
{
    $terms = eicmt_get_ordered_terms($taxonomy);
    $sel = (array) $selected;

    $html = "";
    if ($placeholder !== "") {
        $html .= '<option value="">' . esc_html($placeholder) . "</option>";
        // keep placeholder at top
    }

    foreach ($terms as $t) {
        $is_selected = in_array(
            (string) $t->term_id,
            array_map("strval", $sel),
            true
        );
        $html .= sprintf(
            '<option value="%1$d"%2$s>%3$s</option>',
            (int) $t->term_id,
            $is_selected ? " selected" : "",
            esc_html($t->name)
        );
    }

    return $html;
}

/**
 * Subtype Browser search — exact-identifier match.
 *
 * Returns the published `subtype` post IDs whose canonical identifier —
 * subtype name, gene symbol, or full gene name — is EXACTLY the query.
 *
 * When any exist, both the loop fragment and the facet counts restrict to
 * this set and skip the fuzzy LIKE net. Without this precedence a short
 * gene symbol such as "MME" (the gene for CMT2T) also substring-matches
 * author surnames like "Ti-mme-rman" / "Zi-mme-rmann" through the fuzzy
 * `authors`/`alt_authors` fields, returning dozens of unrelated subtypes.
 *
 * This is the single source of truth for the exact-match rule; both
 * consumers call it so their behavior cannot drift.
 *
 * @param string $qs Search term (already sanitized upstream).
 * @return int[] Matching subtype IDs; empty when the term is blank or
 *               matches no exact identifier.
 */
function eic_genes_search_exact_ids($qs)
{
    $qs = trim((string) $qs);
    if ($qs === "") {
        return [];
    }

    $exact_fields = ["subtype", "gene_symbol", "full_gene_name"];
    $meta_query = ["relation" => "OR"];
    foreach ($exact_fields as $field) {
        $meta_query[] = ["key" => $field, "value" => $qs, "compare" => "="];
    }

    return get_posts([
        "post_type" => "subtype",
        "post_status" => "publish",
        "fields" => "ids",
        "posts_per_page" => -1,
        "no_found_rows" => true,
        "meta_query" => $meta_query,
    ]);
}
