<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 *  CPT: CMT and Breathing
 * ------------------------------------------------------------
 *  Registers the `breathing` custom post type, which powers
 *  all modular educational topics for the “CMT and Breathing”
 *  section. This CPT mirrors the What Is CMT architecture.
 *
 *  Notes:
 *  - Uses block templates for rendering (single-breathing.html)
 *  - Shares its slug with the static page /cmt-and-breathing/
 *  - Supports title and editor only; metadata handled via ACF
 * ============================================================
 */
if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_register_breathing_cpt");
function eic_register_breathing_cpt()
{
    register_post_type("breathing", [
        "labels" => [
            "name" => "CMT and Breathing Topics",
            "singular_name" => "CMT and Breathing Topic",
            "add_new" => "Add Topic",
            "add_new_item" => "Add New Topic",
            "edit_item" => "Edit Topic",
            "new_item" => "New Topic",
            "view_item" => "View Topic",
            "search_items" => "Search Topics",
            "not_found" => "No topics found",
            "not_found_in_trash" => "No topics found in trash",
        ],

        "public" => true,
        "publicly_queryable" => true,
        "exclude_from_search" => false,
        "show_ui" => true,
        "show_in_menu" => true,
        "menu_position" => 23,
        "menu_icon" => "dashicons-editor-help",

        "has_archive" => false,

        // Same pattern as What Is CMT: CPT and static page share the same URL base
        "rewrite" => [
            "slug" => "cmt-and-breathing",
            "with_front" => false,
        ],

        "hierarchical" => false,
        "query_var" => true,

        "show_in_rest" => true,
        "supports" => ["title", "editor"],
    ]);
}
