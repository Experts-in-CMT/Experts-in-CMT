<?php
/**
 * Shortcode: [subtype_fields]
 * ------------------------------------------------------------
 * Outputs the contents of /templates/subtype-fields-template.php
 * so you can drop [subtype_fields] into a Gutenberg Shortcode block.
 *
 * @package ExpertsInCMT
 * @since 1.0
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

function eic_subtype_fields_shortcode() {

	// Only render on single Subtype posts
	if ( ! is_singular( 'subtype' ) ) {
		return '';
	}

	// Locate the display template
	$template = get_theme_file_path( '/templates/subtype-fields-template.php' );

	if ( ! file_exists( $template ) ) {
		return '<!-- subtype-fields-template.php not found -->';
	}

	// Capture template output
	ob_start();
	include $template;
	return ob_get_clean();
}
add_shortcode( 'subtype_fields', 'eic_subtype_fields_shortcode' );
