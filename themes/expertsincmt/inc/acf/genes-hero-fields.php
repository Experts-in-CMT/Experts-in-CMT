<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * File: ACF — Genes DB App Hero Fields
 * Purpose: Registers the field group for the Genes & Subtypes Database
 *          app hero (background image, title, intro copy). Rendered as
 *          an overlay hero, not a split banner, image is output as a
 *          CSS custom property and positioned entirely in CSS.
 *
 * Location:
 *   /inc/acf/genes-hero-fields.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_acf_genes_hero_fields");
function eic_acf_genes_hero_fields()
{
    acf_add_local_field_group([
        "key" => "group_eic_genes_hero",
        "title" => "Genes DB App Hero",
        "fields" => [
            [
                "key" => "field_eic_genes_hero_image",
                "label" => "Hero Background Image",
                "name" => "genes_hero_image",
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
                "key" => "field_eic_genes_hero_fade_start",
                "label" => "Fade Start %",
                "name" => "genes_hero_fade_start",
                "type" => "range",
                "instructions" =>
                    "Percent from the left edge where the image begins to appear (fully transparent before this point).",
                "required" => 0,
                "default_value" => 0,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_genes_hero_fade_end",
                "label" => "Fade End %",
                "name" => "genes_hero_fade_end",
                "type" => "range",
                "instructions" =>
                    "Percent from the left edge where the image reaches full opacity.",
                "required" => 0,
                "default_value" => 45,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_genes_hero_title",
                "label" => "Hero Title",
                "name" => "genes_hero_title",
                "type" => "text",
                "instructions" =>
                    "Enter the title exactly as it should appear on the page.",
                "required" => 0,
                "maxlength" => 120,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_genes_hero_intro",
                "label" => "Hero Intro",
                "name" => "genes_hero_intro",
                "type" => "textarea",
                "instructions" => "1-2 sentences of intro copy below the title.",
                "required" => 0,
                "rows" => 3,
                "new_lines" => "",
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_genes_hero_subtypes_count",
                "label" => "Subtypes Count",
                "name" => "genes_hero_subtypes_count",
                "type" => "number",
                "instructions" => 'Rendered as "{value}+ subtypes" in the stats line.',
                "required" => 0,
                "default_value" => 170,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_genes_hero_genes_count",
                "label" => "Genes Count",
                "name" => "genes_hero_genes_count",
                "type" => "number",
                "instructions" => 'Rendered as "{value}+ genes" in the stats line.',
                "required" => 0,
                "default_value" => 200,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
        ],
        "location" => [
            [
                [
                    "param" => "page",
                    "operator" => "==",
                    "value" => 1819,
                ],
            ],
        ],
        "style" => "seamless",
        "position" => "acf_after_title",
        "menu_order" => 0,
    ]);
}
