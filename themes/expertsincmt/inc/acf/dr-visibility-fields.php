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
 * ACF — Dorsal Root Visibility (per-post)
 * ------------------------------------------------------------
 * Two sidebar checkboxes on the post editor that let an author keep
 * an individual post out of the Dorsal Root surfaces without any
 * code change:
 *
 *   • dr_hide_from_page   — drop it from the [dr_posts] list on
 *                           /dorsal-root (page load AND the AJAX
 *                           endpoint, since both run through
 *                           eic_dr_apply_search_filters()).
 *   • dr_hide_from_teaser — drop it from the homepage teaser
 *                           ([dorsal_root_section]).
 *
 * The queries read these via eic_dr_hidden_ids() (see
 * inc/content/loops/dr-posts.php), so ticking a box is all it takes
 * to pull a post from review out of view, and unticking it puts the
 * post back. Replaces the old hardcoded ID array.
 *
 * Auto-loaded via the inc/acf/*.php glob in functions.php.
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * IDs of posts whose given visibility box is ticked.
 *
 * Returns the post IDs where $meta_key === '1' (an ACF true_false "on"),
 * so a query can drop them via post__not_in / array_diff. Result is cached
 * per meta key for the request, and the two Dorsal Root surfaces call it:
 *
 *   eic_dr_hidden_ids('dr_hide_from_page')   → [dr_posts] list + AJAX
 *   eic_dr_hidden_ids('dr_hide_from_teaser') → homepage teaser
 *
 * @param string $meta_key The post meta key to test (an ACF field name).
 * @return int[] Post IDs to exclude (empty when none are hidden).
 */
if (!function_exists("eic_dr_hidden_ids")) {
    function eic_dr_hidden_ids(string $meta_key): array
    {
        static $cache = [];
        if (array_key_exists($meta_key, $cache)) {
            return $cache[$meta_key];
        }

        $ids = get_posts([
            "post_type" => "post",
            "post_status" => "any",
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "suppress_filters" => true,
            "meta_key" => $meta_key,
            "meta_value" => "1",
        ]);

        return $cache[$meta_key] = is_array($ids)
            ? array_map("intval", $ids)
            : [];
    }
}

add_action("acf/init", function () {
    if (!function_exists("acf_add_local_field_group")) {
        return;
    }

    acf_add_local_field_group([
        "key" => "group_eic_dr_visibility",
        "title" => "Dorsal Root Visibility",
        "fields" => [
            [
                "key" => "field_eic_dr_hide_from_page",
                "label" => "Hide From Page",
                "name" => "dr_hide_from_page",
                "type" => "true_false",
                "instructions" =>
                    "Keep this post out of the Dorsal Root list on the /dorsal-root page (and its live search). Use while an article is in review.",
                "required" => 0,
                "ui" => 1,
                "ui_on_text" => "Hidden",
                "ui_off_text" => "Visible",
                "default_value" => 0,
            ],
            [
                "key" => "field_eic_dr_hide_from_teaser",
                "label" => "Hide From Teaser",
                "name" => "dr_hide_from_teaser",
                "type" => "true_false",
                "instructions" =>
                    "Keep this post out of the homepage \"The Dorsal Root\" teaser cards.",
                "required" => 0,
                "ui" => 1,
                "ui_on_text" => "Hidden",
                "ui_off_text" => "Visible",
                "default_value" => 0,
            ],
        ],
        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "post",
                ],
            ],
        ],
        "position" => "side",
        "style" => "default",
        "label_placement" => "left",
        "menu_order" => 0,
        "active" => true,
        "description" => "Per-post Dorsal Root visibility toggles.",
    ]);
});
