<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * CMT AND BREATHING — Fields Shortcode
 * Usage: [cmt_and_breathing_fields]
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("cmt_and_breathing_fields", function () {
    // Safety guard — prevents TT25 block editor crashes
    if (!is_singular("breathing")) {
        return "";
    }

    // Path to your template file
    $template =
        get_template_directory() .
        "/templates/cmt-and-breathing-fields-template.php";

    // Silent fail if file missing
    if (!file_exists($template)) {
        return "<!-- cmt-and-breathing-fields-template.php not found -->";
    }

    ob_start();
    include $template; // Only runs on Breathing CPT singles
    return ob_get_clean();
});
