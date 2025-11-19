<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * File: ACF — CMT and Breathing Topic Fields
 * Purpose: Registers the minimal field group for the CMT and Breathing
 *          Topics CPT. Includes a dynamic Title field (for Meta Field Block)
 *          and a 300-character summary field used for hub page previews.
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_acf_breathing_fields");
function eic_acf_breathing_fields()
{
    acf_add_local_field_group([
        "key" => "group_eic_breathing",
        "title" => "CMT and Breathing Topic Fields",
        "fields" => [
            // ============================================================
            // Title (used for Meta Field Block output)
            // ============================================================
            [
                "key" => "field_eic_breathing_title",
                "label" => "Topic Title",
                "name" => "topic_title",
                "type" => "text",
                "instructions" =>
                    "Enter the title exactly as it should appear on the page.",
                "required" => 1,
                "maxlength" => 120,
                "wrapper" => [
                    "width" => "100",
                ],
            ],

            // ============================================================
            // Summary (600-character max)
            // ============================================================
            [
                "key" => "field_eic_breathing_summary",
                "label" => "Short Summary",
                "name" => "summary",
                "type" => "textarea",
                "instructions" => "2-4 sentences. Maximum 600 characters.",
                "maxlength" => 600,
                "rows" => 3,
                "new_lines" => "",
                "wrapper" => [
                    "width" => "100",
                ],
            ],
        ],

        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "breathing",
                ],
            ],
        ],

        "style" => "seamless",
        "position" => "acf_after_title",
        "menu_order" => 0,
    ]);
}
