<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * CPT Registration — Glossary
 * ------------------------------------------------------------
 * Renders the Glossary field display template inside a Gutenberg
 * Shortcode block. Used exclusively on single Glossary pages.
 *
 * Notes:
 *   - Supports title, editor, excerpt, revisions
 *   - Public, REST-enabled, non-hierarchical
 *   - Uses slug `/glossary` for pretty permalinks
 */


if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("glossary_fields", function ($atts = []) {
    ob_start();

    $template =
        get_stylesheet_directory() . "/templates/glossary-fields-template.php";

    if (file_exists($template)) {
        include $template;
    }

    // Clean rogue line breaks and extra spaces
    $output = ob_get_clean();
    $output = preg_replace('/^\s+|\s+$/u', "", $output);

    return trim($output);
});
