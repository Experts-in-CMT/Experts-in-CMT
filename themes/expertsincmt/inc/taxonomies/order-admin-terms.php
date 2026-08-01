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
 * Admin Term Ordering (Subtype Editor)
 * ------------------------------------------------------------
 * Enforces stable, muscle-memory-friendly ordering for taxonomy
 * term checklists in the Subtype editor by:
 *
 * 1) Ordering terms by numeric meta `sort` (meta_value_num ASC)
 * 2) Disabling WordPress's “checked on top” reshuffle
 *
 * Applies to the following taxonomies:
 *   - chromosome
 *   - cmt_type
 *   - inheritance
 *   - neuropathy
 */

// Which taxonomies to control
function eicmt_ordered_taxonomies()
{
    return ["chromosome", "cmt_type", "inheritance", "neuropathy"];
}

/**
 * 1) Adjust term query in admin to order by term meta 'sort'
 */
add_action(
    "pre_get_terms",
    function ($query) {
        if (!is_admin()) {
            return;
        }

        $tax = $query->query_vars["taxonomy"] ?? null;
        if (empty($tax)) {
            return;
        }

        $targets = eicmt_ordered_taxonomies();
        $is_target = is_array($tax)
            ? (bool) array_intersect($tax, $targets)
            : in_array($tax, $targets, true);
        if (!$is_target) {
            return;
        }

        $query->query_vars["meta_key"] = "sort";
        $query->query_vars["orderby"] = "meta_value_num";
        $query->query_vars["order"] = "ASC";
    },
    10
);

/**
 * 2) Disable "checked_ontop" so checklist order remains stable
 */
add_filter(
    "wp_terms_checklist_args",
    function ($args, $post_id) {
        if (
            !empty($args["taxonomy"]) &&
            in_array($args["taxonomy"], eicmt_ordered_taxonomies(), true)
        ) {
            $args["checked_ontop"] = false;
        }
        return $args;
    },
    10,
    2
);
