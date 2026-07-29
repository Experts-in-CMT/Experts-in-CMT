<?php
/**
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * ACF — Dorsal Root Showcase (global options)
 * ------------------------------------------------------------
 * A "DR Showcase" options page holding one ordered Relationship
 * field: hand-pick which Dorsal Root posts the [dr_showcase]
 * shortcode surfaces, and in what order. Stored as post IDs, so a
 * title edit never breaks the link, and the picker is restricted to
 * posts in the `dorsal-root` taxonomy. Global, so the same curated
 * set can render anywhere the shortcode is dropped.
 *
 * Auto-loaded via the inc/acf/*.php glob in functions.php.
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("acf/init", function () {
    if (function_exists("acf_add_options_page")) {
        acf_add_options_page([
            "page_title" => "Dorsal Root Showcase",
            "menu_title" => "DR Showcase",
            "menu_slug" => "eic-dr-showcase",
            "capability" => "edit_posts",
            "icon_url" => "dashicons-images-alt2",
            "position" => "58.6",
            "redirect" => false,
        ]);
    }

    acf_add_local_field_group([
        "key" => "group_eic_dr_showcase",
        "title" => "Dorsal Root Showcase",
        "fields" => [
            [
                "key" => "field_eic_dr_showcase_posts",
                "label" => "Showcase Posts",
                "name" => "dr_showcase_posts",
                "type" => "relationship",
                "instructions" =>
                    "Pick the Dorsal Root posts to feature, then drag them into the order they should appear. The [dr_showcase] shortcode renders them in the Dorsal Root list style. Only Dorsal Root posts are listed.",
                "required" => 0,
                "post_type" => ["post"],
                "taxonomy" => [],
                "filters" => ["search"],
                "elements" => ["featured_image"],
                "return_format" => "id",
                "min" => 0,
                "max" => 6,
            ],
            [
                "key" => "field_eic_dr_showcase_subtype",
                "label" => "Featured Subtype (optional)",
                "name" => "dr_showcase_subtype",
                "type" => "post_object",
                "instructions" =>
                    "Optionally spotlight one subtype. Place the [featured_subtype] shortcode wherever you want it; it renders as a labeled \"Featured Subtype\" card (name, gene, inheritance, year). Leave empty for none.",
                "required" => 0,
                "post_type" => ["subtype"],
                "return_format" => "id",
                "multiple" => 0,
                "allow_null" => 1,
                "ui" => 1,
            ],
        ],
        "location" => [
            [
                [
                    "param" => "options_page",
                    "operator" => "==",
                    "value" => "eic-dr-showcase",
                ],
            ],
        ],
        "style" => "default",
        "menu_order" => 0,
    ]);
});

/**
 * Restrict the relationship picker to posts in the `dorsal-root` taxonomy, so
 * the left pane only ever offers Dorsal Root pieces (not every site post).
 */
add_filter("acf/fields/relationship/query/key=field_eic_dr_showcase_posts", function (
    $args,
    $field,
    $post_id
) {
    $args["tax_query"] = [
        [
            "taxonomy" => "dorsal-root",
            "operator" => "EXISTS",
        ],
    ];
    return $args;
}, 10, 3);
