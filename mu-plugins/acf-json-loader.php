<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*
 * ------------------------------------------------------------
 * MU Plugin: ACF JSON Loader
 * ------------------------------------------------------------
 * Purpose:
 * • Centralizes save/load locations for all ACF field groups
 * • Ensures consistent version control via wp-content/acf-json
 * • Allows optional suppression of ACF UI in production
 *
 * Notes:
 * • Save path overrides the default ACF location
 * • Load path must keep the default index removed
 * • Only hide the ACF UI if WP_ENV=production is explicitly set
 */

// Save ACF field groups to wp-content/acf-json
add_filter("acf/settings/save_json", function () {
    return WP_CONTENT_DIR . "/acf-json";
});

// Load ACF field groups from wp-content/acf-json
add_filter("acf/settings/load_json", function ($paths) {
    unset($paths[0]); // Remove default path
    $paths[] = WP_CONTENT_DIR . "/acf-json";
    return $paths;
});

// Optional: Hide ACF field group UI in production
if (defined("WP_ENV") && WP_ENV === "production") {
    add_filter("acf/settings/show_admin", "__return_false");
}
