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
 * Subtype CPT (the canonical store)
 * ------------------------------------------------------------
 * Registers the `subtype` post type in code. Converted 2026-09-08
 * from the ACF Post Type saved at acf-json/post_type_674650572f387.json,
 * value for value, with two settings changed on conversion:
 *
 *   - rewrite slug `subtype` -> `genetics/subtype`, so subtype pages
 *     sit beside gene pages (/genetics/gene/) in the URL tree
 *   - has_archive true -> false (the platform uses no archives)
 *
 * The post type key, labels, supports, REST, menu, capabilities,
 * hierarchy, and query var are unchanged, so every record, field
 * group, importer, exporter, browser, and search resolver keyed on
 * `subtype` continues untouched. Old /subtype/{slug}/ URLs are carried
 * by one regex 301 in Yoast (^/subtype/(.*)$ -> /genetics/subtype/$1).
 *
 * Location: /inc/cpt/subtype-cpt.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_register_subtype_cpt");
function eic_register_subtype_cpt()
{
    register_post_type("subtype", [
        "labels" => [
            "name" => "Subtypes",
            "singular_name" => "Subtype",
            "menu_name" => "Subtype",
            "all_items" => "All Subtypes",
            "edit_item" => "Edit Subtype",
            "view_item" => "View Subtype",
            "view_items" => "View Subtypes",
            "add_new_item" => "Add New Subtype",
            "add_new" => "Add New Subtype",
            "new_item" => "New Subtype",
            "parent_item_colon" => "Parent Subtype:",
            "search_items" => "Search Subtypes",
            "not_found" => "No subtypes found",
            "not_found_in_trash" => "No subtypes found in Trash",
            "archives" => "Subtype Archives",
            "attributes" => "Subtype Attributes",
            "insert_into_item" => "Insert into subtype",
            "uploaded_to_this_item" => "Uploaded to this subtype",
            "filter_items_list" => "Filter subtypes list",
            "filter_by_date" => "Filter subtypes by date",
            "items_list_navigation" => "Subtypes list navigation",
            "items_list" => "Subtypes list",
            "item_published" => "Subtype published.",
            "item_published_privately" => "Subtype published privately.",
            "item_reverted_to_draft" => "Subtype reverted to draft.",
            "item_scheduled" => "Subtype scheduled.",
            "item_updated" => "Subtype updated.",
            "item_link" => "Subtype Link",
            "item_link_description" => "A link to a subtype.",
        ],

        "description" => "CMT Subtype Listing",

        "public" => true,
        "hierarchical" => true,
        "exclude_from_search" => false,
        "publicly_queryable" => true,
        "show_ui" => true,
        "show_in_menu" => true,
        "show_in_admin_bar" => true,
        "show_in_nav_menus" => true,

        "show_in_rest" => true,
        "rest_namespace" => "wp/v2",
        "rest_controller_class" => "WP_REST_Posts_Controller",

        "menu_position" => 5,
        "menu_icon" => "dashicons-database-add",

        "capability_type" => "post",

        "supports" => [
            "title",
            "editor",
            "excerpt",
            "thumbnail",
            "custom-fields",
            "Date Updated",
        ],

        "has_archive" => false,

        // Subtype pages live in the /genetics/ section beside gene pages.
        "rewrite" => [
            "slug" => "genetics/subtype",
            "with_front" => false,
            "feeds" => false,
            "pages" => true,
        ],

        "query_var" => true,
        "can_export" => true,
        "delete_with_user" => false,
    ]);
}
