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
/**
 * [dorsal_root_section] shortcode — renders the 3-featured Dorsal Root section.
 */
add_shortcode( 'dorsal_root_section', function( $atts = [] ) {
	ob_start();
	get_template_part( 'templates/parts/section', 'dorsal-root', [ 'show_search' => true ] );
	return ob_get_clean();
});

});





