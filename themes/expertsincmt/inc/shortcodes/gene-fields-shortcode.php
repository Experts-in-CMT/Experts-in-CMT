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
 * Shortcode: [gene_fields]
 * ------------------------------------------------------------
 * Renders the Gene field display template inside a Gutenberg
 * Shortcode block. Used exclusively on single Gene pages.
 * Mirrors [subtype_fields] file for file.
 *
 * Notes:
 *   - Loads templates/gene-fields-template.php
 *   - Silent fail with HTML comment if the template is missing
 *   - Only runs on is_singular('gene')
 */

// Prevent direct access
defined("ABSPATH") || exit();

function eic_gene_fields_shortcode()
{
    // Only render on single Gene posts
    if (!is_singular("gene")) {
        return "";
    }

    // Locate the display template
    $template = get_theme_file_path("/templates/gene-fields-template.php");

    if (!file_exists($template)) {
        return "<!-- gene-fields-template.php not found -->";
    }

    // Capture template output
    ob_start();
    include $template;
    return ob_get_clean();
}
add_shortcode("gene_fields", "eic_gene_fields_shortcode");
