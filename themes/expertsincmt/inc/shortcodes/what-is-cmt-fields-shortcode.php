<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 *
 * WHAT IS CMT — Fields Shortcode
 * Usage: [what_is_cmt_fields]
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("what_is_cmt_fields", function () {
    // SAFETY GUARD — prevents TT25 block editor crashes
    if (!is_singular("what-is-cmt")) {
        return "";
    }

    // Path to your template file
    $template = get_template_directory() . "/templates/cmt-fields-template.php";

    // Silent fail if file missing
    if (!file_exists($template)) {
        return "<!-- cmt-fields-template.php not found -->";
    }

    ob_start();
    include $template; // SAFE — only runs on real What Is CMT pages
    return ob_get_clean();
});
