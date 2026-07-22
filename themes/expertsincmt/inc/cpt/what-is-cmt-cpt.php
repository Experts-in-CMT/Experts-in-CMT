<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 *  CPT: What Is CMT
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_register_what_is_cmt_cpt");
function eic_register_what_is_cmt_cpt()
{
    register_post_type("what-is-cmt", [
        "labels" => [
            "name" => "What Is CMT Topics",
            "singular_name" => "What Is CMT Topic",
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

        // Critical slug override so CPT and static page share the same base.
        "rewrite" => [
            "slug" => "what-is-cmt",
            "with_front" => false,
        ],

        "hierarchical" => false,
        "query_var" => true,

        "show_in_rest" => true,
        "supports" => ["title", "editor"],
    ]);
}
