<?php

/**
 * @see http://tgmpluginactivation.com/configuration/ for detailed documentation.
 *
 * @package    TGM-Plugin-Activation
 * @subpackage Example
 * @version    2.6.1 for parent theme minimalistix for publication on WordPress.org
 * @author     Thomas Griffin, Gary Jones, Juliette Reinders Folmer
 * @copyright  Copyright (c) 2011, Thomas Griffin
 * @license    http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link       https://github.com/TGMPA/TGM-Plugin-Activation
 */

require_once get_template_directory() . '/inc/tgm/class-tgm-plugin-activation.php';

add_action('tgmpa_register', 'minimalistix_register_required_plugins', 0);
function minimalistix_register_required_plugins()
{
	$plugins = array(
		array(
			'name'      => 'Superb Addons',
			'slug'      => 'superb-blocks',
			'required'  => false,
		),
	);

	$config = array(
		'id'           => 'minimalistix',
		'default_path' => '',
		'menu'         => 'tgmpa-install-plugins',
		'has_notices'  => true,
		'dismissable'  => true,
		'dismiss_msg'  => '',
		'is_automatic' => true,
		'message'      => '',
	);

	tgmpa($plugins, $config);
}


function new_excerpt_more($more){
	return '...';
}
add_filter('excerpt_more', 'new_excerpt_more');



add_action('init', function() {
	remove_theme_support('core-block-patterns');
});

add_theme_support( 'wp-block-styles' );



function minimalistix_pattern_styles()
{
	wp_enqueue_style('minimalistix-patterns', get_template_directory_uri() . '/assets/css/patterns.css', array(), filemtime(get_template_directory() . '/assets/css/patterns.css'));
	if (is_admin()) {
		global $pagenow;
		if ('site-editor.php' === $pagenow) {
			// Do not enqueue editor style in site editor
			return;
		}
		wp_enqueue_style('minimalistix-editor', get_template_directory_uri() . '/assets/css/editor.css', array(), filemtime(get_template_directory() . '/assets/css/editor.css'));
	}
}
add_action('enqueue_block_assets', 'minimalistix_pattern_styles');









// Initialize information content
require_once trailingslashit(get_template_directory()) . 'inc/vendor/autoload.php';

use SuperbThemesThemeInformationContent\ThemeEntryPoint;
add_action("init", function () {
register_block_pattern_category(
	'minimalistix',
	array( 'label' => __( 'Minimalistix', 'minimalistix' ) )
);

	
ThemeEntryPoint::init([
    'type' => 'block', // block / classic
    'theme_url' => 'https://superbthemes.com/minimalistix/',
    'demo_url' => 'https://superbthemes.com/demo/minimalistix/',
    'features' => array(
    	array(
    		'title' => __("Theme Designer", "minimalistix"),
    		'icon' => "lego-duotone.webp",
    		'description' => __("Choose from over 300 designs for footers, headers, landing pages & all other theme parts.", "minimalistix")
    	),
    	   	array(
    		'title' => __("Editor Enhancements", "minimalistix"),
    		'icon' => "1-1.png",
    		'description' => __("Enhanced editor experience, grid systems, improved block control and much more.", "minimalistix")
    	),
    	array(
    		'title' => __("Custom CSS", "minimalistix"),
    		'icon' => "2-1.png",
    		'description' => __("Add custom CSS with syntax highlight, custom display settings, and minified output.", "minimalistix")
    	),
    	array(
    		'title' => __("Animations", "minimalistix"),
    		'icon' => "wave-triangle-duotone.webp",
    		'description' => __("Animate any element on your website with one click. Choose from over 50+ animations.", "minimalistix")
    	),
    	array(
    		'title' => __("WooCommerce Integration", "minimalistix"),
    		'icon' => "shopping-cart-duotone.webp",
    		'description' => __("Choose from over 100 unique WooCommerce designs for your e-commerce store.", "minimalistix")
    	),
    	array(
    		'title' => __("Responsive Controls", "minimalistix"),
    		'icon' => "arrows-out-line-horizontal-duotone.webp",
    		'description' => __("Make any theme mobile-friendly with SuperbThemes responsive controls.", "minimalistix")
    	)
    )
]);
});