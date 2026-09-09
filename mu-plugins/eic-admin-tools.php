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
 * MU Plugin: EIC Admin Tools: Shared Branding + Header Shell
 * ------------------------------------------------------------
 * Purpose:
 *   - Loads one shared stylesheet (eic-admin-tools.css) across
 *     every custom EIC utility page under Tools. Detection is by
 *     the `page` slug prefix (`eic-`), so new tools are covered
 *     automatically with no list to maintain, and core wp-admin
 *     is never touched.
 *   - Provides a shared header-shell helper so every tool opens
 *     with the same branded bar, title, and card body:
 *
 *         eic_admin_tool_open( 'Tool Title', 'Optional subtitle' );
 *         ...tool markup...
 *         eic_admin_tool_close();
 *
 * Notes:
 *   - mu-plugins load before regular plugins/theme; the helper is
 *     defined here and is available by the time any tool renders.
 *   - Destructive actions opt into red via class="... eic-danger".
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Is the current admin request one of our EIC tool pages?
 * True when the ?page slug begins with "eic-".
 */
function eic_admin_tools_is_tool_page(): bool
{
    if (!is_admin()) {
        return false;
    }
    $page = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "";
    return $page !== "" && strpos($page, "eic-") === 0;
}

/**
 * Enqueue the shared admin stylesheet on EIC tool pages only.
 */
add_action("admin_enqueue_scripts", function () {
    if (!eic_admin_tools_is_tool_page()) {
        return;
    }

    $rel = "/eic-admin-tools.css";

    wp_enqueue_style(
        "eic-admin-tools",
        WPMU_PLUGIN_URL . $rel,
        [],
        null
    );
});

if (!function_exists("eic_admin_tool_open")) {
    /**
     * Open a branded tool page: header bar + title + card body.
     * Replaces the ad-hoc `<div class="wrap"><h1>…</h1>` opener.
     *
     * @param string $title    Tool title (rendered as the H1).
     * @param string $subtitle Optional one-line description.
     */
    function eic_admin_tool_open(string $title, string $subtitle = ""): void
    {
        echo '<div class="wrap eic-tool">';
        echo '<div class="eic-tool__bar">';
        echo '<span class="eic-tool__brand">Experts in CMT</span>';
        echo '<span class="eic-tool__kicker">Site Tools</span>';
        echo "</div>";
        echo '<div class="eic-tool__head">';
        echo '<h1 class="eic-tool__title">' . esc_html($title) . "</h1>";
        if ($subtitle !== "") {
            echo '<p class="eic-tool__sub">' . esc_html($subtitle) . "</p>";
        }
        echo "</div>";
        echo '<div class="eic-tool__body">';
    }
}

if (!function_exists("eic_admin_tool_close")) {
    /**
     * Close the tool page opened by eic_admin_tool_open().
     * Replaces the tool's trailing wrap `</div>`.
     */
    function eic_admin_tool_close(): void
    {
        echo "</div>"; // .eic-tool__body
        echo "</div>"; // .eic-tool.wrap
    }
}
