<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Header Banner Index Loader
 * ------------------------------------------------------------
 * Purpose:
 *   Resolve the correct post ID for block-based and template-based
 *   rendering so the header banner ACF fields load reliably.
 *
 * Notes:
 *   • Supports Site Editor block context
 *   • Falls back cleanly for templates and queried objects
 *   • Passes $post_id into templates/header-banner.php
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
