<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * File: ACF — Header Banner Fields
 * Purpose: Registers the field group for the site-wide split/overlay
 *          header banner (image, title, intro copy), plus the two
 *          fade-mask range fields added when the banner was brought
 *          in line with the Subtype Browser app hero's overlay design.
 *
 * Location:
 *   /inc/acf/header-banner-fields.php
 *
 * Note:
 *   Migrated from an ACF-UI/JSON group (group_6792eabbf10fc). The
 *   group key and all three original field keys are preserved
 *   exactly so existing banner_title / banner_image / banner_intro
 *   values on Pages, Posts, Subtypes, etc. keep resolving correctly.
 *
 *   Once this file is active, delete or deactivate the old "Header
 *   Banner" group in the ACF admin UI (and remove its JSON file from
 *   wp-content/acf-json) so the two versions don't both register.
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_acf_header_banner_fields");
function eic_acf_header_banner_fields()
{
    acf_add_local_field_group([
        "key" => "group_6792eabbf10fc",
        "title" => "Header Banner",
        "fields" => [
            [
                "key" => "field_6792eb48684ab",
                "label" => "Title",
                "name" => "banner_title",
                "aria-label" => "",
                "type" => "text",
                "instructions" => "Overrides page's default H1 heading",
                "required" => 0,
                "conditional_logic" => 0,
                "wrapper" => [
                    "width" => "50",
                    "class" => "",
                    "id" => "",
                ],
                "wpml_cf_preferences" => 2,
                "default_value" => "",
                "maxlength" => "",
                "allow_in_bindings" => 0,
                "placeholder" => "",
                "prepend" => "",
                "append" => "",
            ],
            [
                "key" => "field_6792eabc684aa",
                "label" => "Image",
                "name" => "banner_image",
                "aria-label" => "",
                "type" => "image",
                "instructions" => "",
                "required" => 0,
                "conditional_logic" => 0,
                "wrapper" => [
                    "width" => "50",
                    "class" => "",
                    "id" => "",
                ],
                "wpml_cf_preferences" => 3,
                "return_format" => "array",
                "library" => "all",
                "min_width" => 1400,
                "min_height" => "",
                "min_size" => "",
                "max_width" => "",
                "max_height" => "",
                "max_size" => "",
                "mime_types" => "",
                "allow_in_bindings" => 0,
                "preview_size" => "medium",
            ],
            [
                "key" => "field_eic_banner_fade_start",
                "label" => "Fade Start % (Desktop)",
                "name" => "banner_fade_start",
                "type" => "range",
                "instructions" =>
                    "Desktop / wide layout. Percent from the left edge where the image begins to appear (fully transparent before this point).",
                "required" => 0,
                "default_value" => 33,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_banner_fade_end",
                "label" => "Fade End % (Desktop)",
                "name" => "banner_fade_end",
                "type" => "range",
                "instructions" =>
                    "Desktop / wide layout. Percent from the left edge where the image reaches full opacity.",
                "required" => 0,
                "default_value" => 66,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_banner_fade_start_mobile",
                "label" => "Fade Start % (Mobile)",
                "name" => "banner_fade_start_mobile",
                "type" => "range",
                "instructions" =>
                    "Phones (600px and under). Percent from the left edge where the image begins to appear. Defaults suit the narrower layout.",
                "required" => 0,
                "default_value" => 55,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_banner_fade_end_mobile",
                "label" => "Fade End % (Mobile)",
                "name" => "banner_fade_end_mobile",
                "type" => "range",
                "instructions" =>
                    "Phones (600px and under). Percent from the left edge where the image reaches full opacity.",
                "required" => 0,
                "default_value" => 100,
                "min" => 0,
                "max" => 100,
                "step" => 1,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_6792eb80684ac",
                "label" => "Intro",
                "name" => "banner_intro",
                "aria-label" => "",
                "type" => "wysiwyg",
                "instructions" => "Keep this brief",
                "required" => 0,
                "conditional_logic" => 0,
                "wrapper" => [
                    "width" => "50",
                    "class" => "",
                    "id" => "",
                ],
                "default_value" => "",
                "allow_in_bindings" => 0,
                "tabs" => "all",
                "toolbar" => "full",
                "media_upload" => 1,
                "delay" => 0,
            ],
        ],
        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "page",
                ],
            ],
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "post",
                ],
            ],
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "subtype",
                ],
            ],
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "breathing",
                ],
            ],
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "what-is-cmt",
                ],
            ],
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "glossary",
                ],
            ],
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "gene",
                ],
            ],
        ],
        "menu_order" => 999,
        "position" => "normal",
        "style" => "default",
        "label_placement" => "top",
        "instruction_placement" => "label",
        "hide_on_screen" => "",
        "active" => true,
        "description" => "",
        "show_in_rest" => 0,
    ]);
}
