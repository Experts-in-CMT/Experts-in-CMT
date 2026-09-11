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
if (!function_exists("expertsincmt_post_format_setup")):
    function expertsincmt_post_format_setup()
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
add_action("after_setup_theme", "expertsincmt_post_format_setup");

// Enqueues editor styles in the editors.
if (!function_exists("expertsincmt_editor_style")):
    function expertsincmt_editor_style()
    {
        add_editor_style([
            "assets/css/editor-style.css",
            "assets/css/main.css",
        ]);
    }
endif;
add_action("after_setup_theme", "expertsincmt_editor_style");

// Enqueues style.css on the front.
if (!function_exists("expertsincmt_enqueue_styles")):
    function expertsincmt_enqueue_styles()
    {
        wp_enqueue_style(
            "expertsincmt-style",
            get_parent_theme_file_uri("style.css"),
            [],
            wp_get_theme()->get("Version")
        );
    }
endif;
add_action("wp_enqueue_scripts", "expertsincmt_enqueue_styles");

// Registers custom block styles.
if (!function_exists("expertsincmt_block_styles")):
    function expertsincmt_block_styles()
    {
        register_block_style("core/list", [
            "name" => "checkmark-list",
            "label" => __("Checkmark", "expertsincmt"),
            "inline_style" => '
				ul.is-style-checkmark-list { list-style-type: "\2713"; }
				ul.is-style-checkmark-list li { padding-inline-start: 1ch; }',
        ]);
    }
endif;
add_action("init", "expertsincmt_block_styles");

// Registers pattern categories.
if (!function_exists("expertsincmt_pattern_categories")):
    function expertsincmt_pattern_categories()
    {
        register_block_pattern_category("expertsincmt_page", [
            "label" => __("Pages", "expertsincmt"),
            "description" => __(
                "A collection of full page layouts.",
                "expertsincmt"
            ),
        ]);

        register_block_pattern_category("expertsincmt_post-format", [
            "label" => __("Post formats", "expertsincmt"),
            "description" => __(
                "A collection of post format patterns.",
                "expertsincmt"
            ),
        ]);
    }
endif;
add_action("init", "expertsincmt_pattern_categories");

// Registers block binding sources.
if (!function_exists("expertsincmt_register_block_bindings")):
    function expertsincmt_register_block_bindings()
    {
        register_block_bindings_source("expertsincmt/format", [
            "label" => _x(
                "Post format name",
                "Label for the block binding placeholder in the editor",
                "expertsincmt"
            ),
            "get_value_callback" => "expertsincmt_format_binding",
        ]);
    }
endif;
add_action("init", "expertsincmt_register_block_bindings");

// Registers block binding callback function for the post format name.
if (!function_exists("expertsincmt_format_binding")):
    function expertsincmt_format_binding()
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
/**
 * Return-state module for [context_nav].
 *
 * Loads on every front-end view because it operates on two page
 * classes at once: listing pages (where it records URL + scroll) and
 * single posts (where it repoints the Return button). The script
 * self-guards by pathname and by the presence of the nav link, so a
 * global enqueue is cheap and side-effect free elsewhere.
 */
add_action("wp_enqueue_scripts", function () {
    if (is_admin()) {
        return;
    }
    $rel = "/assets/js/return-state.js";
    $abs = get_stylesheet_directory() . $rel;
    if (!file_exists($abs)) {
        return;
    }
    wp_enqueue_script(
        "eic-return-state",
        get_stylesheet_directory_uri() . $rel,
        [],
        null,
        true
    );
});

/**
 * Keep the return-state script out of Cloudflare Rocket Loader.
 *
 * Rocket Loader rewrites every script tag's type and runs the scripts
 * after the page has painted. The scroll restore has to run while the
 * listing is still parsing, before first paint, or the page shows its
 * top and then jumps. data-cfasync="false" is Cloudflare's documented
 * per-script opt-out; harmless where Rocket Loader is not in front.
 */
add_filter("wp_script_attributes", function (array $attributes): array {
    if (($attributes["id"] ?? "") === "eic-return-state-js") {
        $attributes["data-cfasync"] = "false";
    }
    return $attributes;
});

add_action("wp_enqueue_scripts", function () {
    $map = [
        "dorsal-root" => [
            "handle" => "dr-ajax",
            "file" => "/assets/js/dr-ajax.js",
            "var" => "DR_AJAX",
            "nonce" => "dr_ajax_nonce",
            "taxMap" => ["dr_cat" => "dorsal-root"],
        ],
        "cmt-words" => [
            "handle" => "glossary-ajax",
            "file" => "/assets/js/glossary-ajax.js",
            "var" => "GL_AJAX",
            "nonce" => "glossary_ajax_nonce",
            "taxMap" => [],
        ],
        "cmt-subtype-browser" => [
            "handle" => "genes-ajax",
            "file" => "/assets/js/subtype-browser-ajax.js",
            "var" => "GENES_AJAX",
            "nonce" => "genes_ajax_nonce",
            "taxMap" => [
                "cmt_type" => "cmt_type",
                "inheritance" => "inheritance",
                "neuropathy" => "neuropathy",
                "chromosome" => "chromosome",
            ],
        ],
    ];

    foreach ($map as $slug => $c) {
        if (is_page($slug)) {
            // Shared loop URL helper (must load before the stack script)
            wp_enqueue_script(
                "eic-loop-url",
                get_stylesheet_directory_uri() .
                    "/assets/js/loop-url-utils.js",
                [],
                null,
                true
            );

            // Stack JS (depends on the shared helper)
            wp_enqueue_script(
                $c["handle"],
                get_stylesheet_directory_uri() . $c["file"],
                ["eic-loop-url"],
                null,
                true
            );

            // loop-ajax.css is auto-loaded via the assets/css glob loader.

            // Localized vars (+ term_id → slug map for taxonomy loops)
            $localized = [
                "url" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce($c["nonce"]),
            ];
            if (
                !empty($c["taxMap"]) &&
                function_exists("eic_build_tax_slug_map")
            ) {
                $localized["taxSlugs"] = eic_build_tax_slug_map($c["taxMap"]);
            }
            wp_localize_script($c["handle"], $c["var"], $localized);
        }
    }
});

// NOTE: A former `loop_no_results` unhook lived here. TT25 registers no
// `loop_no_results` action or `twentytwentyfive_no_results` callback, so
// the hook did nothing. The custom loops' empty-state hiding is handled
// by CSS (`.wp-block-query-no-results { display:none }` in main.css).

/**
 * Subtype Browser — loop shortcode loader
 */
add_action(
    "after_setup_theme",
    function () {
        $rel = "/inc/content/loops/subtype-browser-loop.php";
        $abs = get_template_directory() . $rel;
        if (file_exists($abs)) {
            require_once $abs;
        }
    },
    20
);

// subtype-browser-hero.css is auto-loaded via the assets/css glob loader above.

// Subtype Browser — custom type order (global, but opt-in via query var)
add_action(
    "after_setup_theme",
    function () {
        $rel = "/inc/content/sort/subtype-browser-type-order.php";
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
 * expertsincmt image sizes.
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
                ["expertsincmt-style"],
                null
            );
        }
    },
    999
);

/**
 * Auto-enqueue component stylesheets — glob /assets/css.
 * ------------------------------------------------------------
 * Every stylesheet in /assets/css loads on the front end
 * automatically (depending on experts-main), so adding a new
 * component sheet needs no functions.php edit. Excludes main.css
 * (loaded separately as experts-main) and editor-style.css
 * (editor only). Sheets are component-scoped, so site-wide
 * loading is safe and cheap at this scale.
 */
add_action(
    "wp_enqueue_scripts",
    function () {
        if (is_admin()) {
            return;
        }

        $dir = get_stylesheet_directory() . "/assets/css";
        $uri = get_stylesheet_directory_uri() . "/assets/css";
        $skip = ["main.css", "editor-style.css"];

        foreach (glob($dir . "/*.css") as $file) {
            $name = basename($file);
            if (in_array($name, $skip, true)) {
                continue;
            }
            wp_enqueue_style(
                "eic-" . sanitize_key(pathinfo($name, PATHINFO_FILENAME)),
                $uri . "/" . $name,
                ["experts-main"],
                null
            );
        }
    },
    1000
);

/**
 * Enqueue Genes Filters stylesheet — load only when needed.
 */
// subtype-browser-filters.css is auto-loaded via the assets/css glob loader above.

/**
 * Enqueue Genes Loop stylesheet — load only when needed.
 */
// subtype-browser-loop.css is auto-loaded via the assets/css glob loader above.

// dr-filter.css is auto-loaded via the assets/css glob loader above.

// glossary-filter.css is auto-loaded via the assets/css glob loader above.

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
// SUBTYPE CPT (the canonical store; registered in code since 2026-09-08)
// =========================================================
require_once get_stylesheet_directory() . "/inc/cpt/subtype-cpt.php";

// =========================================================
// GENE CPT (shell post per gene, projected from the subtype store)
// =========================================================
require_once get_stylesheet_directory() . "/inc/cpt/gene-cpt.php";

// Gene page: ClinVar Variants card script, single gene pages only.
add_action("wp_enqueue_scripts", function () {
    if (is_admin() || !is_singular("gene")) {
        return;
    }
    $rel = "/assets/js/gene-page.js";
    $abs = get_stylesheet_directory() . $rel;
    if (!file_exists($abs)) {
        return;
    }
    wp_enqueue_script(
        "eic-gene-page",
        get_stylesheet_directory_uri() . $rel,
        [],
        null,
        true
    );
});

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

// ============================================================
// Navigation Behavior Fix — Keep Parent Menu Items Clickable
// ============================================================
add_action(
    "wp_enqueue_scripts",
    function () {
        // nav-parent-link.css is auto-loaded via the assets/css glob loader.

        wp_enqueue_script(
            "cmtgenes-nav-parent-link",
            get_stylesheet_directory_uri() . "/assets/js/nav-parent-link.js",
            [],
            null,
            true
        );
    },
    1002
);

// ============================================================
// Do Not Sell My Info — Modal Assets
// ============================================================
add_action("wp_enqueue_scripts", function () {
    // do-not-sell.css is auto-loaded via the assets/css glob loader.

    // JS
    $js_rel = "/assets/js/do-not-sell-modal.js";
    $js_path = get_stylesheet_directory() . $js_rel;

    if (file_exists($js_path)) {
        wp_enqueue_script(
            "dnsmi-modal",
            get_stylesheet_directory_uri() . $js_rel,
            [],
            null,
            true
        );
    }
});

// =========================================================
// CMT Subtype Browser Filter Array Taxonomy Includes
// =========================================================
require_once get_stylesheet_directory() .
    "/inc/taxonomies/register-subtype-taxes.php";
require_once get_stylesheet_directory() .
    "/inc/content/filters/terms-helpers.php";
require_once get_stylesheet_directory() .
    "/inc/taxonomies/order-admin-terms.php";
require_once get_stylesheet_directory() .
    "/inc/content/filters/subtype-browser-filter.php";

// =========================================================
// Load modular includes — expertsincmt
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

/**
 * Enqueue Platform Search Styles (modular & scoped)
 */
add_action("wp_enqueue_scripts", function () {

    if (is_admin()) {
        return;
    }

    $should_load = false;
    global $post;

    if ($post) {
        $content = (string) $post->post_content;

        // Load when the shortcode is present
        if (has_shortcode($content, "platform_search_filter")) {
            $should_load = true;
        }
    }

    // Also load on native search pages
    if (is_search()) {
        $should_load = true;
    }

    if ($should_load) {
        wp_enqueue_style(
            "platform-search",
            get_stylesheet_directory_uri() . "/inc/search/platform-search.css",
            [],
            null
        );

        // Live search (progressive enhancement over the GET form)
        wp_enqueue_script(
            "platform-search-ajax",
            get_stylesheet_directory_uri() . "/assets/js/platform-search-ajax.js",
            [],
            null,
            true
        );

        wp_localize_script("platform-search-ajax", "PS_AJAX", [
            "url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("ps_ajax_nonce"),
        ]);
    }
}, 1000);

// --- Platform Search ---
$search_dir = get_stylesheet_directory() . "/inc/search/";
if (is_dir($search_dir)) {
    foreach (glob($search_dir . "*.php") as $file) {
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
        // Use an OR meta_query (EXISTS / NOT EXISTS) so the sort LEFT JOINs
        // postmeta and keeps posts that have no `_is_featured` value. A
        // plain `meta_key` orderby INNER JOINs and silently drops them.
        $q->set("meta_query", [
            "relation" => "OR",
            "featured_clause" => [
                "key" => "_is_featured",
                "compare" => "EXISTS",
            ],
            [
                "key" => "_is_featured",
                "compare" => "NOT EXISTS",
            ],
        ]);
        $q->set("orderby", ["featured_clause" => "DESC", "date" => "DESC"]);
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

// ============================================================
// Google News RSS Feed Hook
// ============================================================

add_action('wp_head', function () {
    if (is_page('dorsal-root')) {
        echo '<link rel="alternate" type="application/rss+xml" title="The Dorsal Root" href="https://expertsincmt.org/dorsal-root/feed/" />' . "\n";
    }
});

// =========================================================
// EIC REST API — GENE SYMBOLS ENDPOINT
// =========================================================
require_once get_template_directory() . '/inc/rest/gene-symbols-endpoint.php';

// =========================================================
// EIC REST API — AUTHORITY LINKS ENDPOINT
// =========================================================
require_once get_template_directory() . '/inc/rest/authority-links-endpoint.php';

// ======================================================================
// Editor Configuration: CPT Editing - Nuke Block Template Surfacing
// ======================================================================
add_filter('block_editor_settings_all', function ($settings, $context) {

    // CPTs that use block templates for rendering but require content-only editing
    if (
        isset($context->post) &&
        $context->post instanceof WP_Post &&
        $context->post->post_type === 'subtype'

    ) {
        // Prevent template preview in the post editor
        $settings['supportsTemplateMode'] = false;

        // Force a content-only editing canvas
        $settings['template'] = [];
        $settings['templateLock'] = false;
    }

    return $settings;
}, 10, 2);

// Add Gene column to Subtype admin list
add_filter('manage_edit-subtype_columns', function ($columns) {
    $new = [];

    foreach ($columns as $key => $label) {
        $new[$key] = $label;

        if ($key === 'title') {
            $new['gene_symbol'] = 'Gene';
        }
    }

    return $new;
});

// Populate Gene column
add_action('manage_subtype_posts_custom_column', function ($column, $post_id) {
    if ($column === 'gene_symbol') {
        $gene = get_field('gene_symbol', $post_id);

        if (!$gene) {
            $gene = get_field('gene', $post_id); // legacy fallback
        }

        echo esc_html($gene ?: '—');
    }
}, 10, 2);
