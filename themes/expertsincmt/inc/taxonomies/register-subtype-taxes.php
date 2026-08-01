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
 * Genes Database — Taxonomy Registration
 * ------------------------------------------------------------
 * Registers the four core taxonomies used by the Subtype CPT:
 *   - cmt_type
 *   - inheritance
 *   - neuropathy
 *   - chromosome
 *
 * Features:
 *   • Hierarchical taxonomies (checkbox UI)
 *   • Seeded default terms
 *   • Numeric "sort" term meta for stable ordering
 */

add_action("init", function () {
    $taxes = [
        "cmt_type" => [
            "singular" => "CMT Type",
            "plural" => "CMT Type",
            "slug" => "cmt-type",
            "terms" => [
                "CMT1",
                "CMT2",
                "CMT4",
                "CMTX",
                "CMTDI",
                "CMTRI",
                "dHMN/HMN",
                "dSMA",
                "GAN",
                "HMSN",
                "HSAN",
                "HSN",
                "SMA-LEP",
                "Unclassified Subtypes",
            ],
            "order" => [
                "CMT1",
                "CMT2",
                "CMT4",
                "CMTX",
                "CMTDI",
                "CMTRI",
                "dHMN/HMN",
                "dSMA",
                "GAN",
                "HMSN",
                "HSAN",
                "HSN",
                "SMA-LEP",
                "Unclassified Subtypes",
            ],
        ],
        "inheritance" => [
            "singular" => "Inheritance Pattern",
            "plural" => "Inheritance Pattern",
            "slug" => "inheritance",
            "terms" => [
                "autosomal dominant",
                "autosomal recessive",
                "X-linked dominant",
                "X-linked recessive",
                "mitochondrial inheritance",
            ],
            "order" => [
                "autosomal dominant",
                "autosomal recessive",
                "X-linked dominant",
                "X-linked recessive",
                "mitochondrial inheritance",
            ],
        ],
        "neuropathy" => [
            "singular" => "Neuropathy Type",
            "plural" => "Neuropathy Type",
            "slug" => "neuropathy",
            "terms" => ["Demyelinating", "Axonal", "Intermediate"],
            "order" => ["Demyelinating", "Axonal", "Intermediate"],
        ],
        "chromosome" => [
            "singular" => "Chromosome",
            "plural" => "Chromosome",
            "slug" => "chromosome",
            "terms" => [
                "1",
                "2",
                "3",
                "4",
                "5",
                "6",
                "7",
                "8",
                "9",
                "10",
                "11",
                "12",
                "13",
                "14",
                "15",
                "16",
                "17",
                "18",
                "19",
                "20",
                "21",
                "22",
                "X",
                "Y",
                "MT",
            ],
            "order" => [
                "1",
                "2",
                "3",
                "4",
                "5",
                "6",
                "7",
                "8",
                "9",
                "10",
                "11",
                "12",
                "13",
                "14",
                "15",
                "16",
                "17",
                "18",
                "19",
                "20",
                "21",
                "22",
                "X",
                "Y",
                "MT",
            ],
        ],
    ];

    foreach ($taxes as $tax => $L) {
        register_taxonomy(
            $tax,
            ["subtype"],
            [
                "labels" => [
                    "name" => $L["plural"],
                    "singular_name" => $L["singular"],
                    "search_items" => "Search " . $L["plural"],
                    "all_items" => "All" . $L["plural"],
                    "edit_item" => "Edit" . $L["singular"],
                    "view_item" => "View" . $L["singular"],
                    "update_item" => "Update " . $L["singular"],
                    "add_new_item" => "Add New" . $L["singular"],
                    "new_item_name" => "New" . $L["singular"],
                    "not_found" => "No" . strtolower($L["plural"]) . " found",
                ],
                "public"             => false,
                "publicly_queryable" => false,
                "hierarchical"       => true, // checkbox UI (no typing)
                "show_ui"            => true,
                "show_admin_column"  => true,
                "show_in_rest"       => true,
                "rewrite"            => false,
            ]
        );
    }

// Ensure all terms exist and carry sort meta (runs every load; cheap,
    // term_exists() prevents duplicates). Add a term to the arrays above
    // and it appears after a reload. No version key, no drift.
    foreach ($taxes as $tax => $L) {
        foreach ($L["terms"] as $term_name) {
            if (!term_exists($term_name, $tax)) {
                wp_insert_term($term_name, $tax);
            }
        }
        if (!empty($L["order"])) {
            foreach ($L["order"] as $idx => $ordered_name) {
                $t = get_term_by("name", $ordered_name, $tax);
                if ($t && !is_wp_error($t)) {
                    update_term_meta($t->term_id, "sort", (int) $idx);
                }
            }
        }
    }
});
