<?php
/**
 * Dynamic render for layout/header-banner
 * Always resolve the right post ID so ACF works from templates & editor.
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

// Find a reliable post ID.
$post_id = 0;

// 1) Try block context (Site Editor passes this).
if ( isset( $block ) && is_object( $block ) && ! empty( $block->context['postId'] ) ) {
	$post_id = (int) $block->context['postId'];
}

// 2) Fallback to global $post.
if ( ! $post_id ) {
	global $post;
	if ( $post && isset( $post->ID ) ) {
		$post_id = (int) $post->ID;
	}
}

// 3) Last resort: queried object.
if ( ! $post_id ) {
	$post_id = (int) get_queried_object_id();
}

// Make $post_id available to the template.
include get_template_directory() . '/templates/header-banner.php';
