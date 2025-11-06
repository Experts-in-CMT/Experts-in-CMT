<?php

/**
 * DO NOT EDIT WITHOUT REVIEW — load order is critical.
 * - main.css loads ONLY via 'experts-main' @ priority 999.
 * - nav CSS/JS must depend on 'experts-main'.
 * - If you touch enqueues, verify header banner styles after.
 */

/**
 * Twenty Twenty-Five functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
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
add_action('wp_enqueue_scripts', function () {
    $map = [
        'dorsal-root'  => ['handle' => 'dr-ajax',        'file' => '/assets/js/dr-ajax.js',        'var' => 'DR_AJAX', 'nonce' => 'dr_ajax_nonce'],
       // 'cmt-words' => ['handle' => 'glossary-ajax',  'file' => '/assets/js/glossary-ajax.js',  'var' => 'GL_AJAX', 'nonce' => 'glossary_ajax_nonce'],
        // 'genes' => ['handle' => 'genes-ajax', 'file' => '/assets/js/genes-ajax.js', 'var' => 'GENES_AJAX', 'nonce' => 'genes_ajax_nonce'],
    ];

    foreach ($map as $slug => $c) {
        if (is_page($slug)) {
            wp_enqueue_script($c['handle'], get_stylesheet_directory_uri() . $c['file'], [], '1.0', true);
            wp_localize_script($c['handle'], $c['var'], [
                'url'   => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce($c['nonce']),
            ]);
        }
    }
});

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
// Register header banner from /blocks/header-banner/block.json
// =========================================================
add_action("init", function () {
    register_block_type_from_metadata(
        get_template_directory() . "/blocks/header-banner"
    );
});

/**
 * Enqueue global keyboard navigation (Arrow + WASD) site-wide.
 */
add_action(
    "wp_enqueue_scripts",
    function () {
        $relative = "/assets/js/global-keyboard-nav.js";
        $path = get_template_directory() . $relative;

        if (file_exists($path)) {
            wp_enqueue_script(
                "global-keyboard-nav",
                get_template_directory_uri() . $relative,
                [], // no deps
                filemtime($path),
                true // in footer
            );
        }
    },
    1000
);

/**
 * Global SR live region for keyboard navigation announcements.
 * Inject once right after <body>.
 */
add_action(
    "wp_body_open",
    function () {
        echo '<div id="screenreader-nav-status"
		aria-live="polite"
		aria-atomic="true"
		style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
	</div>';
    },
    5
);

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

// Global keyboard navigation (WASD + Arrow Keys)
wp_enqueue_script(
    "global-keyboard-nav",
    get_stylesheet_directory_uri() . "/assets/js/global-keyboard-nav.js",
    [],
    "0.1.0",
    true
);

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
 * [context_nav] shortcode (Prev / Back / Next) — Locked Baseline
 * Experts in CMT / Dorsal Root Unified Version
 */
add_shortcode("context_nav", function () {
    if (!is_singular() || is_admin()) {
        return "";
    }

    global $post;
    if (empty($post) || empty($post->ID)) {
        return "";
    }

    $post_type = get_post_type($post);
    $supported_types = ["post", "subtype", "glossary", "resource"];
    if (!in_array($post_type, $supported_types, true)) {
        return "";
    }

    // Label sets per CPT
    $labels = [
        "post" => [
            "prev" => "← Previous Post",
            "next" => "Next Post →",
            "back_label" => "Return to The Dorsal Root",
        ],
        "subtype" => [
            "prev" => "← Previous Subtype",
            "next" => "Next Subtype →",
            "back_label" => "Return to Subtypes",
        ],
        "glossary" => [
            "prev" => "← Previous Term",
            "next" => "Next Term →",
            "back_label" => "Return to The Glossary",
        ],
        "resource" => [
            "prev" => "← Previous Resource",
            "next" => "Next Resource →",
            "back_label" => "Return to Resources",
        ],
    ];

    // Back URL logic — explicit anchors per your request
    if ($post_type === "post") {
        $back_url = home_url("/dorsal-root/#blog");
    } elseif ($post_type === "subtype") {
        $back_url = home_url("/cmt-genetics-database/#results");
    } elseif ($post_type === "glossary") {
        // Match subtype behavior: go to the glossary PAGE, not the CPT archive, and use #results
        $back_url = home_url("/cmt-words/#results");
    } elseif ($post_type === "resource") {
        $archive = get_post_type_archive_link("resource");
        $back_url = ($archive ?: home_url("/resources")) . "#resources";
    } else {
        $back_url = home_url("/");
    }

    // Determine previous/next IDs
    $prev_id = $next_id = null;

    if ($post_type === "post") {
        $prev = get_adjacent_post(false, "", true);
        $next = get_adjacent_post(false, "", false);
        if ($prev instanceof WP_Post) {
            $prev_id = $prev->ID;
        }
        if ($next instanceof WP_Post) {
            $next_id = $next->ID;
        }
    } else {
        $ids = get_posts([
            "post_type" => $post_type,
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
            "fields" => "ids",
            "no_found_rows" => true,
            "post_status" => "publish",
        ]);
        if ($ids && in_array($post->ID, $ids, true)) {
            $i = array_search($post->ID, $ids, true);
            $prev_id = $ids[$i - 1] ?? null;
            $next_id = $ids[$i + 1] ?? null;
        }
    }

    if (!$prev_id && !$next_id) {
        return "";
    }
    $L = $labels[$post_type] ?? [
        "prev" => "← Previous",
        "next" => "Next →",
        "back_label" => "← Return",
    ];

    ob_start();
    ?>
    <div class="eicmt-ctnav-wrap">
      <nav class="eicmt-ctnav" aria-label="Post navigation">
        <div class="eicmt-ctnav__col eicmt-ctnav__col--prev">
          <?php if ($prev_id): ?>
            <a class="eicmt-ctnav__link" href="<?php echo esc_url(
                get_permalink($prev_id)
            ); ?>">
              <?php echo esc_html($L["prev"]); ?>
            </a>
          <?php endif; ?>
        </div>

        <div class="eicmt-ctnav__col eicmt-ctnav__col--back">
          <a class="eicmt-ctnav__link" href="<?php echo esc_url($back_url); ?>">
            <?php echo esc_html($L["back_label"]); ?>
          </a>
        </div>

        <div class="eicmt-ctnav__col eicmt-ctnav__col--next">
          <?php if ($next_id): ?>
            <a class="eicmt-ctnav__link" href="<?php echo esc_url(
                get_permalink($next_id)
            ); ?>">
              <?php echo esc_html($L["next"]); ?>
            </a>
          <?php endif; ?>
        </div>
      </nav>
    </div>
    <?php return ob_get_clean();
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
