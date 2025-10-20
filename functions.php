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
if ( ! function_exists( 'twentytwentyfive_post_format_setup' ) ) :
	function twentytwentyfive_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_post_format_setup' );

// Enqueues editor styles in the editors.
if ( ! function_exists( 'twentytwentyfive_editor_style' ) ) :
	function twentytwentyfive_editor_style() {
		add_editor_style( array(
			'assets/css/editor-style.css',
			'assets/css/main.css',
		) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_editor_style' );

// Enqueues style.css on the front.
if ( ! function_exists( 'twentytwentyfive_enqueue_styles' ) ) :
	function twentytwentyfive_enqueue_styles() {
		wp_enqueue_style(
			'twentytwentyfive-style',
			get_parent_theme_file_uri( 'style.css' ),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'twentytwentyfive_enqueue_styles' );

// Registers custom block styles.
if ( ! function_exists( 'twentytwentyfive_block_styles' ) ) :
	function twentytwentyfive_block_styles() {
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'twentytwentyfive' ),
				'inline_style' => '
				ul.is-style-checkmark-list { list-style-type: "\2713"; }
				ul.is-style-checkmark-list li { padding-inline-start: 1ch; }',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_block_styles' );

// Registers pattern categories.
if ( ! function_exists( 'twentytwentyfive_pattern_categories' ) ) :
	function twentytwentyfive_pattern_categories() {
		register_block_pattern_category(
			'twentytwentyfive_page',
			array(
				'label'       => __( 'Pages', 'twentytwentyfive' ),
				'description' => __( 'A collection of full page layouts.', 'twentytwentyfive' ),
			)
		);

		register_block_pattern_category(
			'twentytwentyfive_post-format',
			array(
				'label'       => __( 'Post formats', 'twentytwentyfive' ),
				'description' => __( 'A collection of post format patterns.', 'twentytwentyfive' ),
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_pattern_categories' );

// Registers block binding sources.
if ( ! function_exists( 'twentytwentyfive_register_block_bindings' ) ) :
	function twentytwentyfive_register_block_bindings() {
		register_block_bindings_source(
			'twentytwentyfive/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'twentytwentyfive' ),
				'get_value_callback' => 'twentytwentyfive_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_register_block_bindings' );

// Registers block binding callback function for the post format name.
if ( ! function_exists( 'twentytwentyfive_format_binding' ) ) :
	function twentytwentyfive_format_binding() {
		$post_format_slug = get_post_format();
		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;

/**
 * Experts in CMT image sizes.
 */
function expertsincmt_image_sizes() {
	add_image_size( 'experts-banner-2400', 2400, 1350, true );
}
add_action( 'after_setup_theme', 'expertsincmt_image_sizes' );

/**
 * Enqueue canonical main.css after TT25 on the front end.
 * (This must remain the ONLY loader for main.css so your banner CSS wins.)
 */
add_action( 'wp_enqueue_scripts', function () {
	$relative = '/assets/css/main.css';
	$path     = get_template_directory() . $relative;

	if ( file_exists( $path ) ) {
		wp_enqueue_style(
			'experts-main',
			get_template_directory_uri() . $relative,
			array( 'twentytwentyfive-style' ),
			filemtime( $path )
		);
	}
}, 999);

/**
 * Register header banner from /blocks/header-banner/block.json
 * (Close this callback right after the register call.)
 */
add_action( 'init', function () {
	register_block_type_from_metadata(
		get_template_directory() . '/blocks/header-banner'
	);
} );

/**
 * [header_banner] — renders the ACF banner on pages.
 * Uses /templates/header-banner.php so the markup stays in one place.
 */
function cmt_layout_header_banner_shortcode() {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$post_id = (int) get_queried_object_id();

	ob_start();
	// Make $post_id visible to the template.
	include get_template_directory() . '/templates/header-banner.php';
	return ob_get_clean();
}
add_shortcode( 'header_banner', 'cmt_layout_header_banner_shortcode' );

// Hide core/post-title on pages when an ACF banner_title is set.
add_filter( 'render_block', function( $content, $block ) {
	if ( is_admin() ) return $content;
	if ( $block['blockName'] !== 'core/post-title' ) return $content;
	if ( ! is_page() ) return $content;
	if ( function_exists( 'get_field' ) && trim( (string) get_field( 'banner_title' ) ) !== '' ) {
		return ''; // suppress the H1 block
	}
	return $content;
}, 10, 2 );

/**
 * Enqueue navigation behavior fix so parent items remain clickable.
 */
add_action( 'wp_enqueue_scripts', function () {
	$theme_version = wp_get_theme()->get( 'Version' );

	// CSS
	wp_enqueue_style(
		'cmtgenes-nav-parent-link',
		get_stylesheet_directory_uri() . '/assets/css/nav-parent-link.css',
		array( 'experts-main' ), // ensure it loads after your main.css
		$theme_version
	);

	// JS
	wp_enqueue_script(
		'cmtgenes-nav-parent-link',
		get_stylesheet_directory_uri() . '/assets/js/nav-parent-link.js',
		array(), // no deps
		$theme_version,
		true
	);
} );

// =========================================================
// [context_nav] shortcode (Prev / Back / Next) — Locked Baseline
// Experts in CMT / Dorsal Root Unified Version
// =========================================================
add_shortcode('context_nav', function() {
    if (!is_singular() || is_admin()) return '';

    global $post;
    if (empty($post) || empty($post->ID)) return '';

    $post_type = get_post_type($post);
    $supported_types = ['post', 'subtype', 'glossary', 'resource'];
    if (!in_array($post_type, $supported_types, true)) return '';

    // Label sets per CPT
    $labels = [
        'post' => [
            'prev'       => '← Previous Post',
            'next'       => 'Next Post →',
            'back_label' => 'Return to The Dorsal Root',
        ],
        'subtype' => [
            'prev'       => '← Previous Subtype',
            'next'       => 'Next Subtype →',
            'back_label' => 'Return to Subtypes',
        ],
        'glossary' => [
            'prev'       => '← Previous Term',
            'next'       => 'Next Term →',
            'back_label' => 'Return to The Glossary',
        ],
        'resource' => [
            'prev'       => '← Previous Resource',
            'next'       => 'Next Resource →',
            'back_label' => 'Return to Resources',
        ],
    ];

    // Back URL logic (archives first, with anchors for direct landings)
    $back_url = home_url('/');
    if ($post_type === 'post') {
        $dr_page = get_page_by_path('dorsal-root');
        if ($dr_page instanceof WP_Post) {
            $back_url = get_permalink($dr_page->ID);
        } else {
            $posts_page_id = (int) get_option('page_for_posts');
            if ($posts_page_id) $back_url = get_permalink($posts_page_id);
        }
        $back_url .= '#blog';
    } elseif ($post_type === 'subtype') {
        $archive = get_post_type_archive_link('subtype');
        $back_url = ($archive ?: home_url('/cmt-genetics-database/')) . '#subtypes';
    } elseif ($post_type === 'glossary') {
        $archive = get_post_type_archive_link('glossary');
        $back_url = ($archive ?: home_url('/glossary')) . '#glossary';
    } elseif ($post_type === 'resource') {
        $archive = get_post_type_archive_link('resource');
        $back_url = ($archive ?: home_url('/resources')) . '#resources';
    }

    // Determine previous/next IDs
    $prev_id = $next_id = null;

    if ($post_type === 'post') {
        $prev = get_adjacent_post(false, '', true);
        $next = get_adjacent_post(false, '', false);
        if ($prev instanceof WP_Post) $prev_id = $prev->ID;
        if ($next instanceof WP_Post) $next_id = $next->ID;
    } else {
        $ids = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_status'    => 'publish',
        ]);
        if ($ids && in_array($post->ID, $ids, true)) {
            $i = array_search($post->ID, $ids, true);
            $prev_id = $ids[$i - 1] ?? null;
            $next_id = $ids[$i + 1] ?? null;
        }
    }

    if (!$prev_id && !$next_id) return '';
    $L = $labels[$post_type] ?? ['prev'=>'← Previous','next'=>'Next →','back_label'=>'← Return'];

    ob_start(); ?>
    <div class="eicmt-ctnav-wrap">
      <nav class="eicmt-ctnav" aria-label="Post navigation">
        <div class="eicmt-ctnav__col eicmt-ctnav__col--prev">
          <?php if ($prev_id): ?>
            <a class="eicmt-ctnav__link" href="<?php echo esc_url(get_permalink($prev_id)); ?>">
              <?php echo esc_html($L['prev']); ?>
            </a>
          <?php endif; ?>
        </div>

        <div class="eicmt-ctnav__col eicmt-ctnav__col--back">
          <a class="eicmt-ctnav__link" href="<?php echo esc_url($back_url); ?>">
            <?php echo esc_html($L['back_label']); ?>
          </a>
        </div>

        <div class="eicmt-ctnav__col eicmt-ctnav__col--next">
          <?php if ($next_id): ?>
            <a class="eicmt-ctnav__link" href="<?php echo esc_url(get_permalink($next_id)); ?>">
              <?php echo esc_html($L['next']); ?>
            </a>
          <?php endif; ?>
        </div>
      </nav>
    </div>
    <?php
    return ob_get_clean();
});


/**
 * [dorsal_root_section] shortcode — renders the 3-featured Dorsal Root section.
 */
add_shortcode('dorsal_root_section', function ($atts = []) {
    ob_start();
    get_template_part('templates/parts/section', 'dorsal-root', ['show_search' => true]);
    return ob_get_clean();
});

/**
 * Dorsal Root: "Featured" checkbox
 * - Meta box in post editor
 * - Quick Edit support
 * - Admin column
 */

// Meta box
add_action('add_meta_boxes', function () {
    add_meta_box(
        'dorsal_root_feature_box',
        'Featured',
        function ($post) {
            $val = get_post_meta($post->ID, '_is_featured', true);
            wp_nonce_field('dr_feature_save', 'dr_feature_nonce');
            ?>
            <label for="dorsal_root_feature_field">
                <input type="checkbox" name="dorsal_root_feature_field" id="dorsal_root_feature_field" value="1" <?php checked($val, '1'); ?> />
                Yes
            </label>
            <?php
        },
        'post',
        'side',
        'high'
    );
});

// Save (works for editor and Quick Edit)
add_action('save_post_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Only handle when our nonce is present (editor or quick edit form)
    if (!isset($_POST['dr_feature_nonce']) || !wp_verify_nonce($_POST['dr_feature_nonce'], 'dr_feature_save')) return;

    $is_checked = isset($_POST['dorsal_root_feature_field']) ? '1' : '';
    update_post_meta($post_id, '_is_featured', $is_checked);
});

// Admin list column
add_filter('manage_posts_columns', function ($cols) {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'post') {
        // Insert after title if you want; otherwise append
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['is_featured'] = 'Featured';
            }
        }
        if (!isset($new['is_featured'])) $new['is_featured'] = 'Featured';
        return $new;
    }
    return $cols;
});

add_action('manage_posts_custom_column', function ($col, $post_id) {
    if ($col === 'is_featured') {
        $val = get_post_meta($post_id, '_is_featured', true);
        // Visible mark + a hidden data hook for Quick Edit JS
        echo $val === '1' ? 'Yes' : '';
        echo '<span class="featured_val" data-featured="' . esc_attr($val === '1' ? '1' : '0') . '" style="display:none"></span>';
    }
}, 10, 2);

// Make the column sortable (optional)
add_filter('manage_edit-post_sortable_columns', function ($cols) {
    $cols['is_featured'] = 'is_featured';
    return $cols;
});
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;
    if ($q->get('orderby') === 'is_featured') {
        $q->set('meta_key', '_is_featured');
        $q->set('orderby', 'meta_value');
        $q->set('order', 'DESC');
    }
});

// Quick Edit: add the checkbox UI
add_action('quick_edit_custom_box', function ($column_name, $post_type) {
    if ($post_type !== 'post' || $column_name !== 'is_featured') return;
    wp_nonce_field('dr_feature_save', 'dr_feature_nonce');
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label class="alignleft">
                <input type="checkbox" name="dorsal_root_feature_field" id="dorsal_root_feature_field" value="1">
                <span class="checkbox-title">Featured</span>
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 2);

// Quick Edit: populate checkbox from row data
add_action('admin_print_footer_scripts-edit.php', function () {
    // Only load on Posts admin screen
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-post') return;
    ?>
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
add_action('wp_footer', function () { ?>
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
<?php });

/* ============================================================
   DORSAL ROOT FILTER + POSTS SHORTCODES
   ------------------------------------------------------------
   Shortcodes:
     [dr_filter]  → Dropdown filter for Dorsal Root taxonomy
     [dr_posts]   → Query loop replacement (grid + pagination)
   ============================================================ */

/**
 * [dr_filter] — shows the category dropdown (?dr_cat=slug)
 */
add_shortcode('dr_filter', function () {
    $tax   = 'dorsal-root';
    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
    if (is_wp_error($terms)) return '';

    $current = isset($_GET['dr_cat']) ? sanitize_text_field(wp_unslash($_GET['dr_cat'])) : '';
    $action  = esc_url(remove_query_arg(array_keys($_GET))) . '#blog';

    ob_start(); ?>
    <form class="dr-filter" action="<?php echo $action; ?>" method="get">
      <label for="dr-cat">Filter The Dorsal Root by Category</label>
      <select id="dr-cat" name="dr_cat" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach ($terms as $t): ?>
          <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($current, $t->slug); ?>>
            <?php echo esc_html($t->name); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php
      // Preserve other query args
      foreach ($_GET as $k => $v) {
          if ($k === 'dr_cat') continue;
          if (is_scalar($v)) {
              printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr($v));
          }
      }
      ?>
      <noscript><button type="submit">Apply</button></noscript>
    </form>
    <?php
    return ob_get_clean();
});


/**
 * [dr_posts] — rows of 3 with centered last row; keeps legacy CSS handles for styling
 * Usage: [dr_posts per_page="9"]
 */
add_shortcode('dr_posts', function ($atts = []) {
    $a = shortcode_atts(['per_page' => 9], $atts);

    $paged = max(
        1,
        get_query_var('paged') ? (int) get_query_var('paged')
            : (isset($_GET['paged']) ? (int) $_GET['paged'] : 1)
    );

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, (int) $a['per_page']),
        'paged'          => $paged,
        'no_found_rows'  => false,
    ];

    // Optional taxonomy filter (?dr_cat=slug)
    $tax = 'dorsal-root';
    if (!empty($_GET['dr_cat'])) {
        $slug = sanitize_text_field(wp_unslash($_GET['dr_cat']));
        $args['tax_query'] = [[
            'taxonomy' => $tax,
            'field'    => 'slug',
            'terms'    => $slug,
        ]];
    }

    $q = new WP_Query($args);

    // Build cards (NOTE: both classes: dr-card + wp-block-post so existing CSS applies)
    $cards = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) { $q->the_post();
            ob_start(); ?>
            <article class="dr-card wp-block-post">
                <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()) {
                        the_post_thumbnail('large', ['loading' => 'lazy', 'decoding' => 'async']);
                    } ?>
                </a>

                <h2 class="wp-block-post-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>

                <div class="wp-block-post-date"><?php echo esc_html(get_the_date()); ?></div>

                <div class="wp-block-post-excerpt">
                    <?php echo esc_html(wp_strip_all_tags(get_the_excerpt(), true)); ?>
                </div>

                <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Read More</a>
            </article>
            <?php
            $cards[] = ob_get_clean();
        }
        wp_reset_postdata();
    }

    // Chunk into rows of 3
    $rows       = array_chunk($cards, 3);
    $total_rows = count($rows);

    ob_start(); ?>
    <div class="wp-block-query dr-blog">
      <div class="dr-grid">
        <?php if (!empty($rows)) : ?>
          <?php foreach ($rows as $i => $row_items) :
              $is_last = ($i === $total_rows - 1);
              $count   = count($row_items); ?>
              <div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="'.(int) $count.'"' : ''; ?>>
                <?php echo implode('', $row_items); ?>
              </div>
          <?php endforeach; ?>
        <?php else : ?>
          <div class="dr-row dr-row--empty"><p>No posts found.</p></div>
        <?php endif; ?>
      </div>

      <?php
      // Pagination (keeps dr_cat in URL)
      $links = paginate_links([
          'base'      => esc_url_raw(add_query_arg('paged', '%#%')),
          'format'    => '',
          'current'   => $paged,
          'total'     => max(1, (int) $q->max_num_pages),
          'type'      => 'list',
          'prev_text' => '« Prev',
          'next_text' => 'Next »',
      ]);
      if ($links): ?>
        <nav class="wp-block-query-pagination"><?php echo $links; ?></nav>
      <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
});

/* ============================================================
   END: DORSAL ROOT FILTER + POSTS SHORTCODES
   ============================================================ */
