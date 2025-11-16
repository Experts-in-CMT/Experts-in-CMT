<?php

/*
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Genes Database — Canonical Sort Rules
 * ------------------------------------------------------------
 * Defines the immutable FIELD() ordering for ACF key
 * `type_classification` used across all Genes DB queries.
 *
 * IMPORTANT:
 * • This is the highest-priority rule in the Genes stack.
 * • Do not rename aliases, reorder sequence, or change JOIN logic.
 * • This file must not emit output, BOMs, or whitespace.
 * • Triggered only when query_var `eic_genes_custom_sort` is set.
 *
 * Canonical order enforced:
 *   CMT1, CMT2, CMT4, CMTX, CMTDI, CMTRI,
 *   dHMN, dSMA, GAN, HMSN, HSAN, HSN, SMA-LEP, Unclassified
 */

if (!function_exists("eic_genes_type_ordering_clauses")) {
    function eic_genes_type_ordering_clauses(
        array $clauses,
        WP_Query $wp_query
    ): array {
        if (!$wp_query->get("eic_genes_custom_sort")) {
            return $clauses;
        }

        global $wpdb;

        // Exact sequence (must match stored ACF values)
        $custom_type_order = [
            "CMT1",
            "CMT2",
            "CMT4",
            "CMTX",
            "CMTDI",
            "CMTRI",
            "dHMN",
            "dSMA",
            "GAN",
            "HMSN",
            "HSAN",
            "HSN",
            "SMA-LEP",
            "Unclassified",
        ];

        // LEFT JOIN postmeta for type_classification as a dedicated alias to avoid collisions
        // Use a unique alias unlikely to clash: mt_typeclass
        if (strpos($clauses["join"], " mt_typeclass ") === false) {
            $clauses["join"] .= " LEFT JOIN {$wpdb->postmeta} mt_typeclass
                            ON (mt_typeclass.post_id = {$wpdb->posts}.ID
                                AND mt_typeclass.meta_key = 'type_classification')";
        }

        // Build FIELD() list safely
        $quoted = array_map(
            fn($v) => trim($wpdb->prepare("%s", $v), "'"),
            $custom_type_order
        );
        $field_list = "'" . implode("','", $quoted) . "'";

        // Compose the ORDER BY:
        // 1) push NULL/empty meta to bottom
        // 2) explicit FIELD() order
        // 3) title ASC for stable tie-breaks within a bucket
        $custom_order_sql =
            "CASE WHEN mt_typeclass.meta_value IS NULL OR mt_typeclass.meta_value = '' THEN 1 ELSE 0 END ASC, " .
            "FIELD(mt_typeclass.meta_value, {$field_list}) ASC, " .
            "{$wpdb->posts}.post_title ASC";

        $clauses["orderby"] = $custom_order_sql;
        return $clauses;
    }
}

// Register the global filter once; it only triggers when the query var is present.
add_filter("posts_clauses", "eic_genes_type_ordering_clauses", 10, 2);
