<?php
/**
 * ACF Admin Stability — ensures ACF meta boxes render in Gutenberg
 * and that field groups appear for pages and CPTs.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Always allow ACF admin and metaboxes
add_filter( 'acf/settings/show_admin', '__return_true' );
add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );

// Load ACF JSON from the active theme
add_filter( 'acf/settings/load_json', function( $paths ) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});

// Safety: if ACF is loaded but Gutenberg hijacked admin_head,
// ensure meta boxes are still rendered.
add_action( 'admin_head', function() {
    global $current_screen;
    if ( ! $current_screen ) return;

    $post_types = [ 'page', 'post', 'cmt-genetics' ];
    if ( in_array( $current_screen->post_type, $post_types, true ) ) {
        // ACF re-enables classic meta box container for this screen
        add_filter( 'acf/settings/remove_wp_meta_box', '__return_false', 100 );
    }
}, 1);

// Guarantee ACF scripts are enqueued
add_action( 'admin_enqueue_scripts', function() {
    if ( function_exists( 'acf' ) && ! did_action( 'acf/input/admin_enqueue_scripts' ) ) {
        do_action( 'acf/input/admin_enqueue_scripts' );
    }
});
