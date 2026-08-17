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
 *  ACF — Gene Browser App Hero Fields
 * ------------------------------------------------------------
 *  Field group for the Gene Browser app hero (background image,
 *  fade, title, intro, and a stats line). Sibling of the Variant
 *  Mechanism Browser hero; same meta-box architecture, gene-
 *  specific fields and stats.
 *
 *  Rendered by [gene_browser_hero]; the image is output as a CSS
 *  custom property and positioned in CSS, so no source masking.
 *
 *  Location resolves to whichever page hosts [gene_browser]
 *  (cached), so no hard-coded page ID and it survives a rename.
 *
 *  Location: /inc/acf/gene-browser-hero-fields.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * ID of the page that hosts [gene_browser], found by its shortcode rather than
 * a hard-coded ID. Cached in a transient. Returns 0 until such a page exists.
 */
if (!function_exists("eic_gbx_hero_page_id")) {
    function eic_gbx_hero_page_id(): int
    {
        // Use the cache only when it holds a real id; never trust a cached 0,
        // so a miss (page not yet created, or a search plugin suppressing `s`)
        // self-heals on the next load instead of sticking for the full TTL.
        $cached = get_transient("eic_gbx_hero_page_id");
        if ($cached !== false && (int) $cached > 0) {
            return (int) $cached;
        }

        $statuses = ["publish", "draft", "pending", "private"];
        $id = 0;

        // Primary: cheap keyword search.
        $found = get_posts([
            "post_type" => "page",
            "post_status" => $statuses,
            "posts_per_page" => 10,
            "no_found_rows" => true,
            "s" => "gene_browser",
        ]);
        foreach ($found as $p) {
            if (has_shortcode($p->post_content, "gene_browser")) {
                $id = (int) $p->ID;
                break;
            }
        }

        // Fallback: scan pages directly. WP core search can be overridden by a
        // custom/relevance search that won't match raw shortcode text.
        if ($id === 0) {
            $all = get_posts([
                "post_type" => "page",
                "post_status" => $statuses,
                "posts_per_page" => -1,
                "no_found_rows" => true,
            ]);
            foreach ($all as $p) {
                if (has_shortcode($p->post_content, "gene_browser")) {
                    $id = (int) $p->ID;
                    break;
                }
            }
        }

        if ($id > 0) {
            set_transient("eic_gbx_hero_page_id", $id, 12 * HOUR_IN_SECONDS);
        }
        return $id;
    }
}

add_action("save_post_page", function () {
    delete_transient("eic_gbx_hero_page_id");
});

add_action("init", "eic_acf_gbx_hero_fields");
function eic_acf_gbx_hero_fields()
{
    if (!function_exists("acf_add_local_field_group")) {
        return;
    }

    acf_add_local_field_group([
        "key" => "group_eic_gbx_hero",
        "title" => "Gene Browser Hero",
        "fields" => [
            [
                "key" => "field_eic_gbx_hero_image",
                "label" => "Hero Background Image",
                "name" => "gbx_hero_image",
                "type" => "image",
                "instructions" =>
                    "Full-bleed background image. Source at 2000×1125 (16:9). No masking needed; use the fade sliders below to control the left-side fade.",
                "required" => 0,
                "return_format" => "array",
                "preview_size" => "medium",
                "library" => "all",
                "wrapper" => ["width" => "100"],
            ],
            [
                "key" => "field_eic_gbx_hero_fade_start",
                "label" => "Fade Start % (Desktop)",
                "name" => "gbx_hero_fade_start",
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
                "key" => "field_eic_gbx_hero_fade_end",
                "label" => "Fade End % (Desktop)",
                "name" => "gbx_hero_fade_end",
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
                "key" => "field_eic_gbx_hero_fade_start_mobile",
                "label" => "Fade Start % (Mobile)",
                "name" => "gbx_hero_fade_start_mobile",
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
                "key" => "field_eic_gbx_hero_fade_end_mobile",
                "label" => "Fade End % (Mobile)",
                "name" => "gbx_hero_fade_end_mobile",
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
                "key" => "field_eic_gbx_hero_title",
                "label" => "Hero Title",
                "name" => "gbx_hero_title",
                "type" => "text",
                "instructions" =>
                    "Enter the title exactly as it should appear. Renders as the page's H1.",
                "required" => 0,
                "maxlength" => 120,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_gbx_hero_intro",
                "label" => "Hero Intro",
                "name" => "gbx_hero_intro",
                "type" => "textarea",
                "instructions" => "1-2 sentences of intro copy below the title.",
                "required" => 0,
                "rows" => 3,
                "new_lines" => "",
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_gbx_hero_genes_count",
                "label" => "Disease Genes Count",
                "name" => "gbx_hero_genes_count",
                "type" => "text",
                "instructions" =>
                    'Rendered as "{value} CMT disease genes cataloged" in the stats line. Leave empty to hide this stat.',
                "required" => 0,
                "default_value" => 140,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_gbx_hero_subtypes_count",
                "label" => "Classified Subtypes Count",
                "name" => "gbx_hero_subtypes_count",
                "type" => "text",
                "instructions" =>
                    'Rendered as "{value} classified subtypes" in the stats line. Leave empty to hide this stat.',
                "required" => 0,
                "default_value" => 170,
                "min" => 0,
                "step" => 1,
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_gbx_hero_chromosomes_count",
                "label" => "Chromosomes Count",
                "name" => "gbx_hero_chromosomes_count",
                "type" => "text",
                "instructions" =>
                    'Rendered as "{value} chromosomes" in the stats line. Leave empty to hide this stat.',
                "required" => 0,
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
                    "value" => eic_gbx_hero_page_id(),
                ],
            ],
        ],
        "style" => "seamless",
        "position" => "acf_after_title",
        "menu_order" => 0,
    ]);
}
