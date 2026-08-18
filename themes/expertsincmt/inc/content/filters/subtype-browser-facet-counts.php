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
 *  CMT Subtype Browser — Facet Counts
 * ------------------------------------------------------------
 *  Computes, for every filter control, how many subtypes that
 *  control would return given everything else currently applied.
 *
 *  Faceting rule (standard, and the reason this isn't a naive
 *  count): a facet's own selection is excluded when counting its
 *  own options. Counting `cmt_type` with `cmt_type` still applied
 *  would report the selected term's total and zero for every
 *  other option, making the selector impossible to change. Each
 *  dimension is therefore counted against all OTHER dimensions.
 *
 *  Search caveat: when a search term is active, the loop fragment
 *  replaces the taxonomy query with a name-match OR and the
 *  selectors stop constraining results. Counts mirror that, and
 *  are computed against the search result set rather than
 *  pretending the dropdowns still apply.
 *
 *  Cost: one ID-only query for the base set, one relationship
 *  query per taxonomy, and one primed meta cache. All set math
 *  after that is in PHP, which at this catalog size is cheaper
 *  than issuing a query per facet option.
 *
 *  Location:
 *    /inc/content/filters/subtype-browser-facet-counts.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

const EIC_GENES_FACET_TAXONOMIES = [
    "cmt_type",
    "inheritance",
    "neuropathy",
    "chromosome",
];

const EIC_GENES_FACET_FLAGS = [
    "mito" => "mitochondrial_involvement",
    "ars" => "ars_gene",
    "unknown" => "unknown_gene",
];

// Variant Mechanism is a single-value field (meta key `mechanism`), filtered
// as an OR facet: selecting several boxes widens the set. Param key => stored
// mechanism value. Per-option counts are emitted into the flags channel (keyed
// by param) so the existing filter UI and its JS repaint them unchanged.
const EIC_GENES_MECH = [
    "mech_lof" => "lof",
    "mech_dn" => "dominant_negative",
    "mech_gof" => "gof",
    "mech_complex" => "complex",
    "mech_unknown" => "unknown",
];

/**
 * Normalize incoming filter state into a predictable shape.
 *
 * @param array $req Raw request array (GET, or GET+POST for AJAX).
 * @return array
 */
function eic_genes_facet_normalize_state(array $req)
{
    $state = [
        "qs" => isset($req["qs"]) ? trim(sanitize_text_field($req["qs"])) : "",
        "flags" => [],
        "tax" => [],
        "mech" => [],
    ];

    foreach (EIC_GENES_FACET_TAXONOMIES as $tax) {
        $raw = isset($req[$tax]) ? $req[$tax] : "";
        $resolved =
            $raw !== "" && function_exists("eic_resolve_tax_field")
                ? eic_resolve_tax_field($raw, $tax)
                : null;

        $term_id = 0;
        if ($resolved !== null) {
            if ($resolved["field"] === "term_id") {
                $term_id = (int) $resolved["value"];
            } else {
                $term = get_term_by("slug", $resolved["value"], $tax);
                $term_id = $term && !is_wp_error($term) ? (int) $term->term_id : 0;
            }
        }

        $state["tax"][$tax] = $term_id;
    }

    foreach (array_keys(EIC_GENES_FACET_FLAGS) as $flag) {
        $state["flags"][$flag] = !empty($req[$flag]);
    }

    // Unknown is exclusive of the other two (see fragment guard).
    if ($state["flags"]["unknown"]) {
        $state["flags"]["mito"] = false;
        $state["flags"]["ars"] = false;
    }

    // Selected mechanism values (OR facet).
    foreach (EIC_GENES_MECH as $mkey => $mval) {
        if (!empty($req[$mkey])) {
            $state["mech"][] = $mval;
        }
    }

    return $state;
}

/**
 * The set of subtype IDs a search term matches, or all published
 * subtypes when no search is active.
 *
 * Mirrors the loop fragment's search branch exactly: the SAME
 * meta_query and tax_query the fragment assigns, run as one query.
 * WP_Query ANDs meta_query and tax_query together, so reproducing
 * the args verbatim guarantees the base set equals the rendered
 * result set regardless of the search semantics' finer points.
 *
 * The field lists and the three name-matched taxonomies are kept in
 * sync with fragment-loop-subtype-browser.php; if that search changes,
 * change it here too.
 *
 * @param string $qs
 * @return int[]
 */
function eic_genes_facet_base_ids($qs)
{
    $args = [
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "fields" => "ids",
        "no_found_rows" => true,
    ];

    if ($qs === "") {
        return get_posts($args);
    }

    // Exact-identifier precedence — mirrors the loop fragment. When the
    // term is an exact subtype/gene/full-name match, the result set is
    // that ID list only (see eic_genes_search_exact_ids()), so the facet
    // counts are computed against the same set the loop renders.
    if (function_exists("eic_genes_search_exact_ids")) {
        $exact_ids = eic_genes_search_exact_ids($qs);
        if (!empty($exact_ids)) {
            return $exact_ids;
        }
    }

    // --- Kept in lockstep with the fragment's search branch ---
    $exact_fields = ["subtype", "gene_symbol", "full_gene_name"];
    $fuzzy_fields = [
        "acronym",
        "gene_alias",
        "year_of_discovery",
        "chromosome",
        "inheritance",
        "neuropathy",
        "research_team",
        "publication",
        "authors",
        "alt_authors",
        "notes",
        "alt_publication",
    ];
    $name_matched_taxonomies = ["cmt_type", "inheritance", "neuropathy"];

    $meta_query = ["relation" => "OR"];
    foreach ($exact_fields as $field) {
        $meta_query[] = ["key" => $field, "value" => $qs, "compare" => "="];
    }
    foreach ($fuzzy_fields as $field) {
        $meta_query[] = ["key" => $field, "value" => $qs, "compare" => "LIKE"];
    }

    $tax_query = ["relation" => "OR"];
    foreach ($name_matched_taxonomies as $tax) {
        $tax_query[] = [
            "taxonomy" => $tax,
            "field" => "name",
            "terms" => $qs,
            "operator" => "LIKE",
        ];
    }

    // Both clauses on one query → WP_Query ANDs them, matching the
    // fragment. (The fragment's tax_query replaces, not augments, so
    // this is the whole search constraint.)
    return get_posts(
        array_merge($args, [
            "meta_query" => $meta_query,
            "tax_query" => $tax_query,
        ])
    );
}

/**
 * Build an in-memory index of facet values for the given posts.
 *
 * @param int[] $ids
 * @return array {
 *     @type array $tax   [ taxonomy => [ post_id => [term_id,...] ] ]
 *     @type array $flags [ flag => [ post_id => bool ] ]
 * }
 */
function eic_genes_facet_index(array $ids)
{
    $index = ["tax" => [], "flags" => [], "mech" => []];

    if (empty($ids)) {
        foreach (EIC_GENES_FACET_TAXONOMIES as $tax) {
            $index["tax"][$tax] = [];
        }
        foreach (array_keys(EIC_GENES_FACET_FLAGS) as $flag) {
            $index["flags"][$flag] = [];
        }
        return $index;
    }

    // One relationship query per taxonomy for the whole set.
    foreach (EIC_GENES_FACET_TAXONOMIES as $tax) {
        $map = [];
        $terms = wp_get_object_terms($ids, $tax, [
            "fields" => "all_with_object_id",
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $map[(int) $term->object_id][] = (int) $term->term_id;
            }
        }

        $index["tax"][$tax] = $map;
    }

    // Prime meta in a single query, then read the boolean flags.
    update_meta_cache("post", $ids);

    foreach (EIC_GENES_FACET_FLAGS as $flag => $meta_key) {
        $map = [];
        foreach ($ids as $id) {
            $raw = get_post_meta($id, $meta_key, true);
            $map[$id] = $raw !== "" && $raw !== "0" && $raw !== null;
        }
        $index["flags"][$flag] = $map;
    }

    // Single mechanism value per post for the OR facet.
    $mech_map = [];
    foreach ($ids as $id) {
        $mech_map[$id] = (string) get_post_meta($id, "mechanism", true);
    }
    $index["mech"] = $mech_map;

    return $index;
}

/**
 * Does a post satisfy every active constraint except the excluded
 * dimension?
 *
 * @param int    $id
 * @param array  $state
 * @param array  $index
 * @param string $except Dimension to ignore ('' for none).
 * @param bool   $ignore_tax Skip taxonomy constraints entirely.
 * @return bool
 */
function eic_genes_facet_matches(
    $id,
    array $state,
    array $index,
    $except = "",
    $ignore_tax = false
) {
    if (!$ignore_tax) {
        foreach (EIC_GENES_FACET_TAXONOMIES as $tax) {
            if ($tax === $except) {
                continue;
            }
            $selected = (int) $state["tax"][$tax];
            if (!$selected) {
                continue;
            }
            $have = $index["tax"][$tax][$id] ?? [];
            if (!in_array($selected, $have, true)) {
                return false;
            }
        }
    }

    foreach (array_keys(EIC_GENES_FACET_FLAGS) as $flag) {
        if ($flag === $except) {
            continue;
        }
        if (empty($state["flags"][$flag])) {
            continue;
        }
        if (empty($index["flags"][$flag][$id])) {
            return false;
        }
    }

    // Mechanism OR facet: post must carry one of the selected values.
    if ($except !== "mech" && !empty($state["mech"])) {
        $val = $index["mech"][$id] ?? "";
        if (!in_array($val, $state["mech"], true)) {
            return false;
        }
    }

    return true;
}

/**
 * Compute counts for every facet control.
 *
 * @param array $req Raw request array.
 * @return array {
 *     @type array $tax   [ taxonomy => [ term_id => count ] ]
 *     @type array $flags [ flag => count ]
 *     @type int   $total Matching subtypes under the full state.
 * }
 */
function eic_genes_facet_counts(array $req)
{
    $state = eic_genes_facet_normalize_state($req);
    $base = eic_genes_facet_base_ids($state["qs"]);
    $index = eic_genes_facet_index($base);

    // During search the selectors stop constraining the loop, so
    // counts are computed without taxonomy constraints to match.
    $searching = $state["qs"] !== "";

    $out = ["tax" => [], "flags" => [], "total" => 0];

    foreach (EIC_GENES_FACET_TAXONOMIES as $tax) {
        $counts = [];
        foreach ($base as $id) {
            if (
                !eic_genes_facet_matches($id, $state, $index, $tax, $searching)
            ) {
                continue;
            }
            foreach ($index["tax"][$tax][$id] ?? [] as $term_id) {
                $counts[$term_id] = ($counts[$term_id] ?? 0) + 1;
            }
        }
        $out["tax"][$tax] = $counts;
    }

    foreach (array_keys(EIC_GENES_FACET_FLAGS) as $flag) {
        // Unknown is exclusive of mito/ars, so when counting one
        // side, drop the other side's constraint entirely.
        $probe = $state;
        if ($flag === "unknown") {
            $probe["flags"]["mito"] = false;
            $probe["flags"]["ars"] = false;
        } else {
            $probe["flags"]["unknown"] = false;
        }

        $count = 0;
        foreach ($base as $id) {
            if (
                !eic_genes_facet_matches($id, $probe, $index, $flag, $searching)
            ) {
                continue;
            }
            if (!empty($index["flags"][$flag][$id])) {
                $count++;
            }
        }
        $out["flags"][$flag] = $count;
    }

    // Mechanism facet (OR): count each option against all OTHER dimensions
    // (mechanism excluded), then emit per option into the flags channel.
    foreach (EIC_GENES_MECH as $mkey => $mval) {
        $count = 0;
        foreach ($base as $id) {
            if (
                !eic_genes_facet_matches($id, $state, $index, "mech", $searching)
            ) {
                continue;
            }
            if (($index["mech"][$id] ?? "") === $mval) {
                $count++;
            }
        }
        $out["flags"][$mkey] = $count;
    }

    // Total under the full current state.
    foreach ($base as $id) {
        if (eic_genes_facet_matches($id, $state, $index, "", $searching)) {
            $out["total"]++;
        }
    }

    return $out;
}
