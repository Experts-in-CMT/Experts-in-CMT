<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * CPT Registration — Glossary
 * ------------------------------------------------------------
 * Registers the "glossary" custom post type used for defining
 * canonical CMT terminology throughout expertsincmt.
 *
 * Key Notes:
 *   - Supports title, editor, excerpt, revisions
 *   - Public, REST-enabled, block-editor friendly
 *   - Slug: /glossary
 *   - Non-hierarchical; standalone terms
 *   - Integrates with ACF field group:
 *       "Glossary — Core" (canonical term, short definition,
 *       synonyms, misspellings, term image, source fields)
 *
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action(
    "init",
    function () {
        $labels = [
            "name" => __("Glossary", "eic"),
            "singular_name" => __("Term", "eic"),
            "menu_name" => __("Glossary", "eic"),
            "name_admin_bar" => __("Term", "eic"),
            "add_new" => __("Add New", "eic"),
            "add_new_item" => __("Add New Term", "eic"),
            "new_item" => __("New Term", "eic"),
            "edit_item" => __("Edit Term", "eic"),
            "view_item" => __("View Term", "eic"),
            "all_items" => __("All Terms", "eic"),
            "search_items" => __("Search Glossary", "eic"),
            "parent_item_colon" => __("Parent Terms:", "eic"),
            "not_found" => __("No terms found.", "eic"),
            "not_found_in_trash" => __("No terms found in Trash.", "eic"),
        ];

        $args = [
            "labels" => $labels,
            "public" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "menu_icon" => "dashicons-book-alt",
            "show_in_rest" => true,
            "hierarchical" => false,
            "supports" => ["title", "editor", "excerpt", "revisions"],
            "has_archive" => false,
            "rewrite" => ["slug" => "glossary", "with_front" => false],
            "exclude_from_search" => false,
            "publicly_queryable" => true,
            "capability_type" => "post",
            "map_meta_cap" => true,
            "menu_position" => 22,
        ];

        register_post_type("glossary", $args);
    },
    0
);
