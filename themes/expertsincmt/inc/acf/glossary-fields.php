<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Glossary — Field Group (ACF PHP Registration)
 * ------------------------------------------------------------
 * Registers the main field group for Glossary entries:
 *   - Canonical Term (uniqueness + search)
 *   - Short Definition
 *   - Term Image + Alt Text Override
 *   - Synonyms / AKA list
 *   - Common Misspellings
 *   - Source URL + Source Label
 *   - Admin Notes (internal only)
 *
 * Location:
 *   Post Type = glossary
 *
 * Loaded via acf/init and stored in the theme repo to
 * ensure stable JSON-free ACF configuration.
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("acf/init", function () {
    if (!function_exists("acf_add_local_field_group")) {
        return;
    }

    acf_add_local_field_group([
        "key" => "group_glossary_core",
        "title" => "Glossary — Core",
        "fields" => [
            // Canonical Term
            [
                "key" => "field_glossary_canonical_term",
                "label" => "Canonical Term",
                "name" => "canonical_term",
                "type" => "text",
                "instructions" =>
                    "Leave blank to auto-fill from Title on save. Used for uniqueness and search.",
                "wrapper" => ["width" => "100"],
            ],

            // Short Definition
            [
                "key" => "field_glossary_short_definition",
                "label" => "Short Definition",
                "name" => "short_definition",
                "type" => "text",
                "instructions" => "1–2 sentences used on cards and previews.",
                "wrapper" => ["width" => "100"],
                "maxlength" => 260,
            ],

            // Term Image
            [
                "key" => "field_glossary_term_image",
                "label" => "Term Image",
                "name" => "term_image",
                "type" => "image",
                "instructions" =>
                    "Displayed on cards and single pages. Square images recommended.",
                "wrapper" => ["width" => "50"],
                "return_format" => "id",
                "preview_size" => "medium_large",
            ],

            // Alt Text Override
            [
                "key" => "field_glossary_alt_text_override",
                "label" => "Alt Text Override",
                "name" => "alt_text_override",
                "type" => "text",
                "instructions" =>
                    "Optional: override the media alt text for accessibility.",
                "wrapper" => ["width" => "50"],
            ],

            // Also Known As / Synonyms
            [
                "key" => "field_glossary_aka_synonyms",
                "label" => "Also Known As / Synonyms",
                "name" => "aka_synonyms",
                "type" => "text",
                "instructions" =>
                    "Comma- or pipe-separated list used on single pages and for search suggestions.",
                "wrapper" => ["width" => "100"],
                "placeholder" => "e.g., neuropathy|peripheral neuropathy",
            ],

            // Common Misspellings
            [
                "key" => "field_glossary_common_misspellings",
                "label" => "Common Misspellings",
                "name" => "common_misspellings",
                "type" => "text",
                "instructions" =>
                    "Comma- or pipe-separated list used for “Did you mean” suggestions (optional).",
                "wrapper" => ["width" => "100"],
                "placeholder" => "e.g., axonapathy, mylin",
            ],

            // Source URL (new)
            [
                "key" => "field_glossary_source_url",
                "label" => "Source URL",
                "name" => "source_url",
                "type" => "url",
                "instructions" =>
                    "Add a link to an external source or reference for this term. Displays as a Source button on single pages.",
                "wrapper" => ["width" => "70"],
                "placeholder" => "https://example.com",
            ],

            // Source Label (new)
            [
                "key" => "field_glossary_source_label",
                "label" => "Source Button Label",
                "name" => "source_label",
                "type" => "text",
                "instructions" =>
                    "Optional: customize the button text. Defaults to “Source.”",
                "wrapper" => ["width" => "30"],
                "default_value" => "Source",
            ],

            // Admin Notes
            [
                "key" => "field_glossary_notes_admin",
                "label" => "Admin Notes",
                "name" => "notes_admin",
                "type" => "textarea",
                "instructions" => "Internal notes. Not shown on the site.",
                "wrapper" => ["width" => "100"],
            ],
        ],
        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "glossary",
                ],
            ],
        ],
        "position" => "acf_after_title",
        "label_placement" => "top",
        "instruction_placement" => "label",
        "active" => true,
    ]);
});
