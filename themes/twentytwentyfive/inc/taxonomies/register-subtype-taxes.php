<?php
/**
 * Register Genes Database taxonomies for CPT `subtype`
 * - CMT Type, Inheritance, Neuropathy, Chromosome
 * Hierarchical (checkbox UI), seeded terms, and seeded numeric 'sort' order.
 */

add_action("init", function () {
    $taxes = [
        "cmt_type" => [
            "singular" => "CMT Type",
            "plural" => "CMT Types",
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
            "plural" => "Inheritance Patterns",
            "slug" => "inheritance",
            "terms" => [
                "Autosomal Dominant",
                "Autosomal Recessive",
                "X-Linked Dominant",
                "X-Linked Recessive",
                "Mitochondrial Inheritance",
            ],
            "order" => [
                "Autosomal Dominant",
                "Autosomal Recessive",
                "X-Linked Dominant",
                "X-Linked Recessive",
                "Mitochondrial Inheritance",
            ],
        ],
        "neuropathy" => [
            "singular" => "Neuropathy Type",
            "plural" => "Neuropathy Types",
            "slug" => "neuropathy",
            "terms" => ["Demyelinating", "Axonal", "Intermediate"],
            "order" => ["Demyelinating", "Axonal", "Intermediate"],
        ],
        "chromosome" => [
            "singular" => "Chromosome",
            "plural" => "Chromosomes",
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
                    "all_items" => "All " . $L["plural"],
                    "edit_item" => "Edit " . $L["singular"],
                    "view_item" => "View " . $L["singular"],
                    "update_item" => "Update " . $L["singular"],
                    "add_new_item" => "Add New " . $L["singular"],
                    "new_item_name" => "New " . $L["singular"],
                    "not_found" => "No " . strtolower($L["plural"]) . " found",
                ],
                "public" => true,
                "hierarchical" => true, // checkbox UI (no typing)
                "show_ui" => true,
                "show_admin_column" => true,
                "show_in_rest" => true,
                "rewrite" => ["slug" => $L["slug"]],
            ]
        );
    }

    // Seed terms + numeric 'sort' order (runs once)
    // Bump this key to reseed/repair ordering if needed.
    if (!get_option("subtype_taxonomies_seeded_v4")) {
        foreach ($taxes as $tax => $L) {
            // Insert any missing terms
            foreach ($L["terms"] as $term_name) {
                if (!term_exists($term_name, $tax)) {
                    wp_insert_term($term_name, $tax);
                }
            }

            // Assign 'sort' meta based on the specified 'order' array
            if (!empty($L["order"])) {
                foreach ($L["order"] as $idx => $ordered_name) {
                    $t = get_term_by("name", $ordered_name, $tax);
                    if ($t && !is_wp_error($t)) {
                        update_term_meta($t->term_id, "sort", (int) $idx);
                    }
                }
            }
        }
        update_option("subtype_taxonomies_seeded_v4", true);
    }
});
