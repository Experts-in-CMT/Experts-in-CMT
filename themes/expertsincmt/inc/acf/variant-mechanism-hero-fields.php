<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  ACF — Variant Mechanism Browser App Hero Fields
 * ------------------------------------------------------------
 *  Registers the field group for the Variant Mechanism Browser
 *  app hero (background image, fade, title, intro, and a
 *  three-item stats line). Sibling of the Subtype Browser App Hero;
 *  same meta-box architecture, Var-Mech-specific fields.
 *
 *  Rendered as an overlay hero by [variant_mechanism_hero]; the
 *  image is output as a CSS custom property and positioned
 *  entirely in CSS, so no source-image masking is needed.
 *
 *  Location resolves itself to whichever page hosts the
 *  [variant_mechanism_table] shortcode (cached), so it needs no
 *  hard-coded page ID and survives a page rename.
 *
 *  Location: /inc/acf/variant-mechanism-hero-fields.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * ID of the page that hosts [variant_mechanism_table], found by its shortcode
 * rather than a hard-coded ID. Cached in a transient so the lookup runs at most
 * once per TTL, not on every request. Returns 0 until such a page exists.
 */
if (!function_exists("eic_vmech_hero_page_id")) {
    function eic_vmech_hero_page_id(): int
    {
        $cached = get_transient("eic_vmech_hero_page_id");
        if ($cached !== false) {
            return (int) $cached;
        }
        $id = 0;
        $found = get_posts([
            "post_type" => "page",
            "post_status" => ["publish", "draft", "pending", "private"],
            "posts_per_page" => 10,
            "no_found_rows" => true,
            "s" => "variant_mechanism_table",
        ]);
        foreach ($found as $p) {
            if (has_shortcode($p->post_content, "variant_mechanism_table")) {
                $id = (int) $p->ID;
                break;
            }
        }
        set_transient("eic_vmech_hero_page_id", $id, 12 * HOUR_IN_SECONDS);
        return $id;
    }
}

// A page save can move the shortcode to a different page; drop the cache so the
// location re-resolves on the next load.
add_action("save_post_page", function () {
    delete_transient("eic_vmech_hero_page_id");
});

add_action("init", "eic_acf_vmech_hero_fields");
function eic_acf_vmech_hero_fields()
{
    if (!function_exists("acf_add_local_field_group")) {
        return;
    }

    acf_add_local_field_group([
        "key" => "group_eic_vmech_hero",
        "title" => "Variant Mechanism Browser Hero",
        "fields" => [
            [
                "key" => "field_eic_vmech_hero_image",
                "label" => "Hero Background Image",
                "name" => "vmech_hero_image",
                "type" => "image",
                "instructions" =>
                    "Full-bleed background image. Source at 2000×1125 (16:9). No masking needed, use the fade sliders below to control the left-side fade.",
                "required" => 0,
                "return_format" => "array",
                "preview_size" => "medium",
                "library" => "all",
                "wrapper" => ["width" => "100"],
            ],
            [
                "key" => "field_eic_vmech_hero_fade_start",
                "label" => "Fade Start % (Desktop)",
                "name" => "vmech_hero_fade_start",
                "type" => "range",
                "instructions" =>
                    "Percent from the left edge where the image begins to appear (fully transparent before this point).",
                "required" => 0,
                "default_value" => 33,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_vmech_hero_fade_end",
                "label" => "Fade End % (Desktop)",
                "name" => "vmech_hero_fade_end",
                "type" => "range",
                "instructions" =>
                    "Percent from the left edge where the image reaches full opacity.",
                "required" => 0,
                "default_value" => 66,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_vmech_hero_fade_start_mobile",
                "label" => "Fade Start % (Mobile)",
                "name" => "vmech_hero_fade_start_mobile",
                "type" => "range",
                "instructions" =>
                    "Mobile-only fade start (<= 600px). The image is cropped tighter and the text spans full width there, so a later start keeps the copy readable.",
                "required" => 0,
                "default_value" => 55,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_vmech_hero_fade_end_mobile",
                "label" => "Fade End % (Mobile)",
                "name" => "vmech_hero_fade_end_mobile",
                "type" => "range",
                "instructions" =>
                    "Mobile-only fade end (<= 600px). Percent from the left edge where the image reaches full opacity.",
                "required" => 0,
                "default_value" => 100,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_vmech_hero_title",
                "label" => "Hero Title",
                "name" => "vmech_hero_title",
                "type" => "text",
                "instructions" =>
                    "Enter the title exactly as it should appear on the page. Renders as the page's H1.",
                "required" => 0,
                "maxlength" => 120,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_vmech_hero_intro",
                "label" => "Hero Intro",
                "name" => "vmech_hero_intro",
                "type" => "textarea",
                "instructions" => "1-2 sentences of intro copy below the title.",
                "required" => 0,
                "rows" => 3,
                "new_lines" => "",
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_vmech_hero_subtypes_count",
                "label" => "Classified Subtypes Count",
                "name" => "vmech_hero_subtypes_count",
                "type" => "number",
                "instructions" =>
                    'Rendered as "{value}+ classified subtypes" in the stats line (the + is added automatically; enter a plain number). Leave empty to hide this stat.',
                "required" => 0,
                "default_value" => 174,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_vmech_hero_categories_count",
                "label" => "Mechanism Categories Count",
                "name" => "vmech_hero_categories_count",
                "type" => "number",
                "instructions" =>
                    'Rendered as "{value} mechanism categories" in the stats line. Leave empty to hide this stat.',
                "required" => 0,
                "default_value" => 5,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_vmech_hero_resolved_count",
                "label" => "Resolved Mechanism Count",
                "name" => "vmech_hero_resolved_count",
                "type" => "number",
                "instructions" =>
                    'Rendered as "{value}+ with a resolved mechanism" in the stats line (the + is added automatically; enter a plain number). Leave empty to hide this stat.',
                "required" => 0,
                "default_value" => 154,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "33"],
            ],
        ],
        "location" => [
            [
                [
                    "param" => "page",
                    "operator" => "==",
                    "value" => eic_vmech_hero_page_id(),
                ],
            ],
        ],
        "style" => "seamless",
        "position" => "acf_after_title",
        "menu_order" => 0,
    ]);
}
