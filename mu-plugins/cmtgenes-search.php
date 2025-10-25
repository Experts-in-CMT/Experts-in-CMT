<?php
/**
 * Plugin Name: CMT Genes — Search
 * Description: Extends WordPress search to include taxonomy term names and selected ACF text fields for CPT `subtype`.
 * Author: Experts in CMT / Kenny
 * Version: 0.2.1
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Adjust which ACF meta keys should be searched.
 * Use ACF field *names* (not labels). Text-like fields only.
 */
function cmtgenes_search_get_acf_keys() {
	return [
		'type_classification',
		'subtype',
		'gene',
		'full_gene_name',
		'alternate_gene_1',
		'alternate_gene_2',
		'alternate_gene_3',
		'year_of_discovery',
	];
}

/**
 * Helper: true when this query should be enhanced.
 * - Front-end only
 * - Has an 's' param
 * - Targets post_type=subtype (single or in array)
 */
function cmtgenes_search_should_apply( $query ) {
	if ( is_admin() ) return false;

	$s = $query->get('s');
	if ( empty($s) ) return false;

	$post_type = $query->get('post_type');
	if ( empty($post_type) ) return false;

	$types = (array) $post_type;
	return in_array('subtype', $types, true);
}

/**
 * JOIN term tables and postmeta when enhancing search for `subtype`.
 */
add_filter('posts_join', function($join, $query) {
	global $wpdb;

	if ( cmtgenes_search_should_apply($query) ) {
		// Term joins (for t.name LIKE ...)
		$join .= " LEFT JOIN {$wpdb->term_relationships} tr ON ({$wpdb->posts}.ID = tr.object_id)";
		$join .= " LEFT JOIN {$wpdb->term_taxonomy}   tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
		$join .= " LEFT JOIN {$wpdb->terms}           t  ON (tt.term_id = t.term_id)";
		// Postmeta join (for whitelisted ACF text fields)
		$join .= " LEFT JOIN {$wpdb->postmeta}        pm ON ({$wpdb->posts}.ID = pm.post_id)";
	}

	return $join;
}, 10, 2);

/**
 * WHERE: title/content OR term name OR whitelisted ACF meta_value matches the search.
 * Use one $wpdb->prepare() call with placeholders — no preparing static fragments.
 */
add_filter('posts_where', function($where, $query) {
	global $wpdb;

	if ( cmtgenes_search_should_apply($query) ) {
		$like = '%' . $wpdb->esc_like( $query->get('s') ) . '%';

		// Build a safe IN(...) list for meta keys (identifiers can't be bound as placeholders)
		$acf_keys     = array_map('esc_sql', cmtgenes_search_get_acf_keys());
		$acf_keys_sql = "('" . implode("','", $acf_keys) . "')";

		$where .= $wpdb->prepare(
			" AND (
				{$wpdb->posts}.post_title   LIKE %s
				OR {$wpdb->posts}.post_content LIKE %s
				OR t.name LIKE %s
				OR (pm.meta_key IN {$acf_keys_sql} AND pm.meta_value LIKE %s)
			)",
			$like, $like, $like, $like
		);
	}

	return $where;
}, 10, 2);

/**
 * DISTINCT to avoid dupes introduced by LEFT JOINs.
 */
add_filter('posts_distinct', function($distinct, $query) {
	if ( cmtgenes_search_should_apply($query) ) {
		return 'DISTINCT';
	}
	return $distinct;
}, 10, 2);

/**
 * (Optional) GROUP BY posts.ID to further de-dupe under strict SQL modes.
 */
add_filter('posts_groupby', function($groupby, $query) {
	global $wpdb;
	if ( cmtgenes_search_should_apply($query) ) {
		return "{$wpdb->posts}.ID";
	}
	return $groupby;
}, 10, 2);

/**
 * Performance note:
 * After migrations, consider adding this index to speed meta lookups:
 *   CREATE INDEX idx_postmeta_key_post ON wp_postmeta (meta_key(191), post_id);
 */
