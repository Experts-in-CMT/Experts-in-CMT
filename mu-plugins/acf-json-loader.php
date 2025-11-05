<?php
/**
 * Plugin Name: ACF JSON Loader
 * Description: Centralized save/load path for ACF JSON field groups.
 * Author: Your Team
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
