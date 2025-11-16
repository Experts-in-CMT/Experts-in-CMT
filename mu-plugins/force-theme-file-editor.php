<?php

/*
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: Force Theme File Editor (Local Dev Only)
 * ------------------------------------------------------------
 * Scope:
 * • Surfaces Appearance → Theme File Editor for administrators
 * • Works even if themes attempt to remove the menu item
 * • Warns admins when DISALLOW_FILE_EDIT disables the editor
 *
 * Critical:
 * • Intended ONLY for local development environments
 * • Must not be deployed to staging or production
 * • Runs late (priority 999) to override theme-level removals
 */

// If file editing is globally disabled, let admins know.
add_action("admin_notices", function () {
    if (defined("DISALLOW_FILE_EDIT") && DISALLOW_FILE_EDIT) {
        if (current_user_can("manage_options")) {
            echo '<div class="notice notice-warning"><p><strong>Theme File Editor is disabled by DISALLOW_FILE_EDIT in wp-config.php.</strong> Set it to false or remove the constant for the editor to work.</p></div>';
        }
    }
});

// Re-add the Theme File Editor menu item even if a theme removed it.
// Use a very late priority so this runs after any removals.
add_action(
    "admin_menu",
    function () {
        if (!current_user_can("edit_themes")) {
            return;
        }

        // If WP allows editing, surface the core editor link.
        if (!defined("DISALLOW_FILE_EDIT") || !DISALLOW_FILE_EDIT) {
            add_submenu_page(
                "themes.php", // Parent: Appearance
                "Theme File Editor", // Page title
                "Theme File Editor", // Menu title
                "edit_themes", // Capability
                "theme-editor.php" // Core editor slug
            );
        }
    },
    999
);

// As a backup, also ensure it appears on admin head load for themes that hide it late.
add_action("admin_head-themes.php", function () {
    if (!current_user_can("edit_themes")) {
        return;
    }
    if (!defined("DISALLOW_FILE_EDIT") || !DISALLOW_FILE_EDIT) {
        global $submenu;
        if (!isset($submenu["themes.php"])) {
            return;
        }
        // If the entry is missing, inject it.
        $has_editor = false;
        foreach ($submenu["themes.php"] as $item) {
            if (!empty($item[2]) && $item[2] === "theme-editor.php") {
                $has_editor = true;
                break;
            }
        }
        if (!$has_editor) {
            $submenu["themes.php"][] = [
                "Theme File Editor",
                "edit_themes",
                "theme-editor.php",
            ];
        }
    }
});
