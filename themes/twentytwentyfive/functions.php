<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * DO NOT EDIT WITHOUT REVIEW — load order is critical.
 * ------------------------------------------------------------
 * • main.css loads ONLY via 'experts-main' at priority 999.
 * • nav CSS/JS must depend on 'experts-main'.
 * • If you touch enqueues, verify header banner styles after.
 *
 * Core Theme Bootstrap (functions.php)
 * ------------------------------------------------------------
 * Loads and wires:
 *   – CPTs, taxonomies, ACF groups
 *   – Shortcodes, filters, AJAX endpoints
 *   – Global assets and helper utilities
 *   – Theme supports and editor integration
 *
 * This file is intentionally minimal. All feature logic lives
 * inside /inc/ for clarity, isolation, and predictable upgrades.
 */

// Adds theme support for post formats.
if (!function_exists("twentytwentyfive_post_format_setup")):
    function twentytwentyfive_post_format_setup()
    {
        add_theme_support("post-formats", [
            "aside",
            "audio",
            "chat",
            "gallery",
            "image",
            "link",
            "quote",
            "status",
            "video",
        ]);
    }
endif;
add_action("after_setup_theme", "twentytwentyfive_post_format_setup");

// Enqueues editor styles in the editors.
if (!function_exists("twentytwentyfive_editor_style")):
    function twentytwentyfive_editor_style()
    {
        add_editor_style([
            "assets/css/editor-style.css",
            "assets/css/main.css",
        ]);
    }
endif;
add_action("after_setup_theme", "twentytwentyfive_editor_style");

// Enqueues style.css on the front.
if (!function_exists("twentytwentyfive_enqueue_styles")):
    function twentytwentyfive_enqueue_styles()
    {
        wp_enqueue_style(
            "twentytwentyfive-style",
            get_parent_theme_file_uri("style.css"),
            [],
            wp_get_theme()->get("Version")
        );
    }
endif;
add_action("wp_enqueue_scripts", "twentytwentyfive_enqueue_styles");

// Registers custom block styles.
if (!function_exists("twentytwentyfive_block_styles")):
    function twentytwentyfive_block_styles()
    {
        register_block_style("core/list", [
            "name" => "checkmark-list",
            "label" => __("Checkmark", "twentytwentyfive"),
            "inline_style" => '
				ul.is-style-checkmark-list { list-style-type: "\2713"; }
				ul.is-style-checkmark-list li { padding-inline-start: 1ch; }',
        ]);
    }
endif;
add_action("init", "twentytwentyfive_block_styles");

// Registers pattern categories.
if (!function_exists("twentytwentyfive_pattern_categories")):
    function twentytwentyfive_pattern_categories()
    {
        register_block_pattern_category("twentytwentyfive_page", [
            "label" => __("Pages", "twentytwentyfive"),
            "description" => __(
                "A collection of full page layouts.",
                "twentytwentyfive"
            ),
        ]);

        register_block_pattern_category("twentytwentyfive_post-format", [
            "label" => __("Post formats", "twentytwentyfive"),
            "description" => __(
                "A collection of post format patterns.",
                "twentytwentyfive"
            ),
        ]);
    }
endif;
add_action("init", "twentytwentyfive_pattern_categories");

// Registers block binding sources.
if (!function_exists("twentytwentyfive_register_block_bindings")):
    function twentytwentyfive_register_block_bindings()
    {
        register_block_bindings_source("twentytwentyfive/format", [
            "label" => _x(
                "Post format name",
                "Label for the block binding placeholder in the editor",
                "twentytwentyfive"
            ),
            "get_value_callback" => "twentytwentyfive_format_binding",
        ]);
    }
endif;
add_action("init", "twentytwentyfive_register_block_bindings");

// Registers block binding callback function for the post format name.
if (!function_exists("twentytwentyfive_format_binding")):
    function twentytwentyfive_format_binding()
    {
        $post_format_slug = get_post_format();
        if ($post_format_slug && "standard" !== $post_format_slug) {
            return get_post_format_string($post_format_slug);
        }
    }
endif;

// --------------------------------------------------
// Modular AJAX loaders
// --------------------------------------------------
add_action("wp_enqueue_scripts", function () {
    $map = [
        "dorsal-root" => [
            "handle" => "dr-ajax",
            "file" => "/assets/js/dr-ajax.js",
            "var" => "DR_AJAX",
            "nonce" => "dr_ajax_nonce",
        ],
        "cmt-words" => [
            "handle" => "glossary-ajax",
            "file" => "/assets/js/glossary-ajax.js",
            "var" => "GL_AJAX",
            "nonce" => "glossary_ajax_nonce",
        ],
        "cmt-genetics-database" => [
            "handle" => "genes-ajax",
            "file" => "/assets/js/genes-ajax.js",
            "var" => "GENES_AJAX",
            "nonce" => "genes_ajax_nonce",
        ],
    ];

    foreach ($map as $slug => $c) {
        if (is_page($slug)) {
            // JS
            wp_enqueue_script(
                $c["handle"],
                get_stylesheet_directory_uri() . $c["file"],
                [],
                "1.0",
                true
            );

            // CSS (shared AJAX state + layout fixes)
            wp_enqueue_style(
                "loop-ajax",
                get_stylesheet_directory_uri() . "/assets/css/loop-ajax.css",
                [],
                "1.0"
            );

            // Localized vars
            wp_localize_script($c["handle"], $c["var"], [
                "url" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce($c["nonce"]),
            ]);
        }
    }
});

// ============================================================
// Disable TT25 default "No results found" block for custom loops
// ============================================================
add_action(
    "loop_no_results",
    function () {
        remove_action("loop_no_results", "twentytwentyfive_no_results");
    },
    1
);

/**
 * Genes DB — loop shortcode loader
 */
add_action(
    "after_setup_theme",
    function () {
        $rel = "/inc/content/loops/genes-loop.php";
        $abs = get_template_directory() . $rel;
        if (file_exists($abs)) {
            require_once $abs;
        }
    },
    20
);

// Genes DB — custom type order (global, but opt-in via query var)
add_action(
    "after_setup_theme",
    function () {
        $rel = "/inc/content/sort/genes-type-order.php";
        $abs = get_template_directory() . $rel;
        if (file_exists($abs)) {
            require_once $abs;
        }
    },
    20
);

add_action(
    "after_setup_theme",
    function () {
        $rel = "/inc/content/templates/ensure-single-subtype.php";
        $abs = get_template_directory() . $rel;
        if (file_exists($abs)) {
            require_once $abs;
        }
    },
    20
);

/**
 * Experts in CMT image sizes.
 */
function expertsincmt_image_sizes()
{
    add_image_size("experts-banner-2400", 2400, 1350, true);
}
add_action("after_setup_theme", "expertsincmt_image_sizes");

/**
 * Enqueue canonical main.css after TT25 on the front end.
 * (This must remain the ONLY loader for main.css so your banner CSS wins.)
 */
add_action(
    "wp_enqueue_scripts",
    function () {
        $relative = "/assets/css/main.css";
        $path = get_template_directory() . $relative;

        if (file_exists($path)) {
            wp_enqueue_style(
                "experts-main",
                get_template_directory_uri() . $relative,
                ["twentytwentyfive-style"],
                filemtime($path)
            );
        }
    },
    999
);

/**
 * Enqueue Genes Filters stylesheet — load only when needed.
 */
add_action(
    "wp_enqueue_scripts",
    function () {
        if (is_admin()) {
            return;
        }

        $rel = "/assets/css/genes-filters.css";
        $path = get_stylesheet_directory() . $rel;
        if (!file_exists($path)) {
            return;
        }

        $should_load = false;

        $post = get_post();
        if ($post) {
            $content = (string) $post->post_content;
            if (
                strpos($content, "[genes_filter") !== false ||
                has_shortcode($content, "genes_filter")
            ) {
                $should_load = true;
            }
        }

        if (is_page(["genes", "cmt-genetics-database"])) {
            $should_load = true;
        }

        if ($should_load) {
            wp_enqueue_style(
                "genes-filters",
                get_stylesheet_directory_uri() . $rel,
                ["experts-main"],
                filemtime($path)
            );
        }
    },
    1001
);

// =========================================================
// Load Glossary CPT + Taxonomies + Fields (must load early for ACF visibility)
// =========================================================

// Register the Glossary post type
require_once get_stylesheet_directory() . "/inc/cpt/glossary.php";

// Register the hidden Glossary Letter taxonomy (A–Z, 0–9 autosync)
require_once get_stylesheet_directory() . "/inc/taxonomies/glossary-letter.php";

// Register Glossary ACF field group (Canonical Term, Short Definition, etc.)
require_once get_stylesheet_directory() . "/inc/acf/glossary-fields.php";

// =========================================================
// WHAT IS CMT CPT + ACF + SHORTCODE
// =========================================================
require_once get_stylesheet_directory() . "/inc/cpt/what-is-cmt-cpt.php";
require_once get_stylesheet_directory() . "/inc/acf/what-is-cmt-fields.php";
require_once get_stylesheet_directory() .
    "/inc/shortcodes/what-is-cmt-fields-shortcode.php";

// =========================================================
// CMT AND BREATHING CPT
// =========================================================
require_once get_stylesheet_directory() . "/inc/cpt/cmt-and-breathing-cpt.php";
require_once get_stylesheet_directory() .
    "/inc/acf/cmt-and-breathing-fields.php";
require_once get_stylesheet_directory() .
    "/inc/shortcodes/cmt-and-breathing-fields-shortcode.php";

// =========================================================
// Register header banner from /blocks/header-banner/block.json
// =========================================================
add_action("init", function () {
    register_block_type_from_metadata(
        get_template_directory() . "/blocks/header-banner"
    );
});

/**
 * [header_banner] — renders the ACF banner on pages.
 * Uses /templates/header-banner.php so the markup stays in one place.
 */
function cmt_layout_header_banner_shortcode()
{
    if (!function_exists("get_field")) {
        return "";
    }

    $post_id = (int) get_queried_object_id();

    ob_start();
    include get_template_directory() . "/templates/header-banner.php";
    return ob_get_clean();
}
add_shortcode("header_banner", "cmt_layout_header_banner_shortcode");

// Hide core/post-title on pages when an ACF banner_title is set.
add_filter(
    "render_block",
    function ($content, $block) {
        if (is_admin()) {
            return $content;
        }
        if ($block["blockName"] !== "core/post-title") {
            return $content;
        }
        if (!is_page()) {
            return $content;
        }
        if (
            function_exists("get_field") &&
            trim((string) get_field("banner_title")) !== ""
        ) {
            return ""; // suppress the H1 block
        }
        return $content;
    },
    10,
    2
);

/**
 * Enqueue navigation behavior fix so parent items remain clickable.
 */
add_action(
    "wp_enqueue_scripts",
    function () {
        $theme_version = wp_get_theme()->get("Version");

        wp_enqueue_style(
            "cmtgenes-nav-parent-link",
            get_stylesheet_directory_uri() . "/assets/css/nav-parent-link.css",
            ["experts-main"],
            $theme_version
        );

        wp_enqueue_script(
            "cmtgenes-nav-parent-link",
            get_stylesheet_directory_uri() . "/assets/js/nav-parent-link.js",
            [],
            $theme_version,
            true
        );
    },
    1002
);

// =========================================================
// Genes Database Filter Array Taxonomy Includes
// =========================================================
require_once get_stylesheet_directory() .
    "/inc/taxonomies/register-subtype-taxes.php";
require_once get_stylesheet_directory() .
    "/inc/content/filters/terms-helpers.php";
require_once get_stylesheet_directory() .
    "/inc/taxonomies/order-admin-terms.php";
require_once get_stylesheet_directory() .
    "/inc/content/filters/genes-filter.php";

// =========================================================
// Load modular includes — Experts in CMT
// =========================================================

// --- Filters ---
$filters_dir = get_stylesheet_directory() . "/inc/content/filters/";
if (is_dir($filters_dir)) {
    foreach (glob($filters_dir . "*.php") as $file) {
        require_once $file;
    }
}

// --- Loops ---
$loops_dir = get_stylesheet_directory() . "/inc/content/loops/";
if (is_dir($loops_dir)) {
    foreach (glob($loops_dir . "*.php") as $file) {
        require_once $file;
    }
}

// Glossary — fields template loader
add_action("wp", function () {
    if (is_singular("glossary")) {
        include get_template_directory() .
            "/templates/glossary-fields-template.php";
    }
});

// --- ACF Field Groups ---
$acf_dir = get_stylesheet_directory() . "/inc/acf/";
if (is_dir($acf_dir)) {
    foreach (glob($acf_dir . "*.php") as $file) {
        require_once $file;
    }
}

// --- Shortcodes ---
$shortcodes_dir = get_stylesheet_directory() . "/inc/shortcodes/";
if (is_dir($shortcodes_dir)) {
    foreach (glob($shortcodes_dir . "*.php") as $file) {
        require_once $file;
    }
}

// --------------------------------------------------
// Modular includes: auto-load AJAX endpoints
// --------------------------------------------------
add_action("after_setup_theme", function () {
    $dir = get_stylesheet_directory() . "/inc/ajax";
    if (is_dir($dir)) {
        foreach (glob($dir . "/*.php") as $file) {
            require_once $file;
        }
    }
});

/**
 * [dorsal_root_section] shortcode — renders the 3-featured Dorsal Root section.
 */
add_shortcode("dorsal_root_section", function ($atts = []) {
    ob_start();
    get_template_part("templates/parts/section", "dorsal-root", [
        "show_search" => true,
    ]);
    return ob_get_clean();
});

/**
 * Dorsal Root: "Featured" checkbox
 * - Meta box in post editor
 * - Quick Edit support
 * - Admin column
 */

// Meta box
add_action("add_meta_boxes", function () {
    add_meta_box(
        "dorsal_root_feature_box",
        "Featured",
        function ($post) {
            $val = get_post_meta($post->ID, "_is_featured", true);
            wp_nonce_field("dr_feature_save", "dr_feature_nonce");
            ?>
            <label for="dorsal_root_feature_field">
                <input type="checkbox" name="dorsal_root_feature_field" id="dorsal_root_feature_field" value="1" <?php checked(
                    $val,
                    "1"
                ); ?> />
                Yes
            </label>
            <?php
        },
        "post",
        "side",
        "high"
    );
});

// Save (works for editor and Quick Edit)
add_action("save_post_post", function ($post_id) {
    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can("edit_post", $post_id)) {
        return;
    }

    // Only handle when our nonce is present (editor or quick edit form)
    if (
        !isset($_POST["dr_feature_nonce"]) ||
        !wp_verify_nonce($_POST["dr_feature_nonce"], "dr_feature_save")
    ) {
        return;
    }

    $is_checked = isset($_POST["dorsal_root_feature_field"]) ? "1" : "";
    update_post_meta($post_id, "_is_featured", $is_checked);
});

// Admin list column
add_filter("manage_posts_columns", function ($cols) {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === "post") {
        // Insert after title if you want; otherwise append
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === "title") {
                $new["is_featured"] = "Featured";
            }
        }
        if (!isset($new["is_featured"])) {
            $new["is_featured"] = "Featured";
        }
        return $new;
    }
    return $cols;
});

add_action(
    "manage_posts_custom_column",
    function ($col, $post_id) {
        if ($col === "is_featured") {
            $val = get_post_meta($post_id, "_is_featured", true);
            // Visible mark + a hidden data hook for Quick Edit JS
            echo $val === "1" ? "Yes" : "";
            echo '<span class="featured_val" data-featured="' .
                esc_attr($val === "1" ? "1" : "0") .
                '" style="display:none"></span>';
        }
    },
    10,
    2
);

// Make the column sortable (optional)
add_filter("manage_edit-post_sortable_columns", function ($cols) {
    $cols["is_featured"] = "is_featured";
    return $cols;
});
add_action("pre_get_posts", function ($q) {
    if (!is_admin() || !$q->is_main_query()) {
        return;
    }
    if ($q->get("orderby") === "is_featured") {
        $q->set("meta_key", "_is_featured");
        $q->set("orderby", "meta_value");
        $q->set("order", "DESC");
    }
});

// Quick Edit: add the checkbox UI
add_action(
    "quick_edit_custom_box",
    function ($column_name, $post_type) {
        if ($post_type !== "post" || $column_name !== "is_featured") {
            return;
        }
        wp_nonce_field("dr_feature_save", "dr_feature_nonce");?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label class="alignleft">
                <input type="checkbox" name="dorsal_root_feature_field" id="dorsal_root_feature_field" value="1">
                <span class="checkbox-title">Featured</span>
            </label>
        </div>
    </fieldset>
    <?php
    },
    10,
    2
);

// Quick Edit: populate checkbox from row data
add_action("admin_print_footer_scripts-edit.php", function () {
    // Only load on Posts admin screen
    $screen = get_current_screen();
    if (!$screen || $screen->id !== "edit-post") {
        return;
    }?>
    <script>
    (function($){
        function setQuickEditFeatured(id){
            var $row = $('#post-' + id);
            var featured = $row.find('.featured_val').data('featured');
            $('#dorsal_root_feature_field').prop('checked', String(featured) === '1');
        }
        var $wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(id){
            $wp_inline_edit.apply(this, arguments);
            var postId = 0;
            if (typeof(id) === 'object') postId = parseInt(this.getId(id), 10);
            if (postId > 0) setQuickEditFeatured(postId);
        };
    })(jQuery);
    </script>
    <?php
});

// Back to Top button markup + script
add_action("wp_footer", function () {
    ?>
    <button id="backToTop" class="back-to-top" aria-label="Back to top" hidden>↑ Top</button>
    <script>
      (function () {
        var btn = document.getElementById('backToTop');
        if (!btn) return;
        var showAt = 300, ticking = false;

        function onScroll() {
          if (!ticking) {
            requestAnimationFrame(function () {
              if (window.scrollY > showAt) {
                btn.hidden = false; btn.classList.add('is-visible');
              } else {
                btn.classList.remove('is-visible'); btn.hidden = true;
              }
              ticking = false;
            });
            ticking = true;
          }
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        btn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
      })();
    </script>
    <?php
});

// ============================================================
// Shortcode: Dynamic Updated Line for What Is CMT + Breathing
// ============================================================
function eic_topic_updated_line()
{
    if (!is_singular(["what-is-cmt", "breathing"])) {
        return "";
    }

    $updated = get_the_modified_date("F j, Y");

    return '<p class="eic-updated">Updated: ' .
        esc_html($updated) .
        " | By: K. Raymond</p>";
}
add_shortcode("topic_updated", "eic_topic_updated_line");
