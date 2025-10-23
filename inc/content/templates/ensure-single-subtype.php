<?php
/**
 * Ensure the Site Editor template "single-subtype" exists & is published.
 */
add_action('init', function () {
	if ( ! wp_is_block_theme() ) return;

	$theme = wp_get_theme()->get_stylesheet();
	$slug  = 'single-subtype';
	$id    = $theme . '//' . $slug;

	$template = function_exists('get_block_template') ? get_block_template($id, 'wp_template') : null;

	if ( ! $template || ( isset($template->status) && 'publish' !== $template->status ) ) {
		$args = [
			'post_type'   => 'wp_template',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => 'Single Item: Subtype',
			'post_content'=> '', // empty = use file fallback unless/ until you edit in the Site Editor
			'tax_input'   => ['wp_theme' => [$theme]],
		];

		$existing = get_posts([
			'post_type'   => 'wp_template',
			'name'        => $slug,
			'post_status' => ['any','trash','draft','publish'],
			'numberposts' => 1,
			'tax_query'   => [[
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => [$theme],
			]],
		]);

		if ($existing) { $args['ID'] = $existing[0]->ID; wp_update_post($args); }
		else { wp_insert_post($args); }
	}
}, 20);
