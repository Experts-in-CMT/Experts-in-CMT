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
 * Shortcode: [subtype_fields]
 * ------------------------------------------------------------
 * Renders the Subtype field display template inside a Gutenberg
 * Shortcode block. Used exclusively on single Subtype pages.
 *
 * Notes:
 *   - Loads templates/subtype-fields-template.php
 *   - Silent fail with HTML comment if the template is missing
 *   - Only runs on is_singular('subtype')
 */

// Prevent direct access
defined("ABSPATH") || exit();

function eic_subtype_fields_shortcode()
{
    // Only render on single Subtype posts
    if (!is_singular("subtype")) {
        return "";
    }

    // Locate the display template
    $template = get_theme_file_path("/templates/subtype-fields-template.php");

    if (!file_exists($template)) {
        return "<!-- subtype-fields-template.php not found -->";
    }

    // Capture template output
    ob_start();
    include $template;
    return ob_get_clean();
}
add_shortcode("subtype_fields", "eic_subtype_fields_shortcode");
