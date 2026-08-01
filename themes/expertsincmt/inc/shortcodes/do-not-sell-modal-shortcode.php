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
 *  Shortcode: [do_not_sell_modal]
 * ------------------------------------------------------------
 *  Purpose:
 *  - Outputs the "Do Not Sell My Information" modal markup.
 *  - Keeps the modal self-contained and reusable across templates.
 *
 *  Behavior:
 *  - Includes /templates/do-not-sell-modal.php for the actual markup.
 *  - Fails silently if the template file is missing.
 *
 *  Usage:
 *  - Add [do_not_sell_modal] to a footer template part or page
 *    so the modal HTML exists on the page and can be toggled
 *    via JavaScript when the user clicks the footer link.
 * ============================================================
 */
if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("do_not_sell_modal", function () {
    // Prevent shortcode from doing anything in admin editors
    if (is_admin()) {
        return "";
    }

    $template = get_template_directory() . "/templates/do-not-sell-modal.php";

    if (!file_exists($template)) {
        // Silent fail to avoid breaking front-end if template not present
        return "<!-- do-not-sell-modal.php not found -->";
    }

    ob_start();
    include $template;
    $html = ob_get_clean();

    return shortcode_unautop($html);
});
