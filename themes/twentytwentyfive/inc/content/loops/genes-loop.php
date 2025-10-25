<?php
/* ============================================================
   GENES DATABASE LOOP SHORTCODE (responsive to filter UI)
   - Reads GET params from [genes_filter]: cmt_type, inheritance, neuropathy, chromosome, qs
   - Backward-compatible with old params: gd_q, gd_type, gd_inherit
   - Case-insensitive meta search over specific ACF fields
   - Custom order by type_classification (FIELD()) preserved
   - Pagination via ?gd_paged=
   - Adds #cmt-genetics-database anchor jump
   ============================================================ */

/**
 * [genes_loop] — rows of 3 with centered last row; preserves CSS classes
 * Usage: [genes_loop per_page="12"]
 */

// Fields we want to search (ACF/meta keys)
function eic_gl_search_meta_fields() {
	return [
		'type_classification',
		'subtype',
		'gene',
		'alternate_gene_1',
		'alternate_gene_2',
		'alternate_gene_3',
		'year_of_discovery',
	];
}

// Custom sorter for 'type_classification'
function eic_genes_custom_sort_clauses($clauses, $wp_query) {
	if (!$wp_query->get('eic_genes_custom_sort')) return $clauses;

	global $wpdb;
	$custom_type_order = [
		'CMT1','CMT2','CMT4','CMTX','CMTDI','CMTRI',
		'dHMN','dSMA','GAN','HMSN','HSAN','HSN','SMA-LEP','Unclassified'
	];

	if (strpos($clauses['join'] ?? '', ' mt1 ') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} mt1
		                      ON (mt1.post_id = {$wpdb->posts}.ID AND mt1.meta_key = 'type_classification')";
	}

	$quoted = array_map(function ($v) use ($wpdb) {
		return trim($wpdb->prepare('%s', $v), "'");
	}, $custom_type_order);
	$field_list = "'" . implode("','", $quoted) . "'";

	$clauses['orderby'] =
		"CASE WHEN mt1.meta_value IS NULL OR mt1.meta_value = '' THEN 1 ELSE 0 END ASC, " .
		"FIELD(mt1.meta_value, {$field_list}) ASC, " .
		"{$wpdb->posts}.post_title ASC";

	return $clauses;
}

function eic_gl_get_paged() {
	$p = isset($_GET['gd_paged']) ? (int) $_GET['gd_paged'] : 0;
	if ($p < 1) $p = (int) get_query_var('paged', 1);
	if ($p < 1) $p = (int) get_query_var('page', 1);
	return $p > 0 ? $p : 1;
}

add_shortcode('genes_loop', function ($atts = []) {
	$a = shortcode_atts([
		'per_page'    => 12,
		'page_window' => 2,
		'edge_count'  => 1,
	], $atts);

	// -------------------------------------------------------
	// Inputs
	// -------------------------------------------------------
	$qs_raw = $_GET['qs'] ?? $_GET['gd_q'] ?? '';
	$qs = sanitize_text_field($qs_raw);
	$qs_all = (trim($qs) !== '' && strtolower(trim($qs)) === 'all');

	$sel = [
		'cmt_type'    => isset($_GET['cmt_type'])    ? (int) $_GET['cmt_type']    : 0,
		'inheritance' => isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0,
		'neuropathy'  => isset($_GET['neuropathy'])  ? (int) $_GET['neuropathy']  : 0,
		'chromosome'  => isset($_GET['chromosome'])  ? (int) $_GET['chromosome']  : 0,
	];

	if (!$sel['cmt_type'] && !empty($_GET['gd_type'])) {
		$term = get_term_by('slug', sanitize_title(wp_unslash($_GET['gd_type'])), 'cmt_type');
		if ($term && !is_wp_error($term)) $sel['cmt_type'] = (int) $term->term_id;
	}
	if (!$sel['inheritance'] && !empty($_GET['gd_inherit'])) {
		$term = get_term_by('slug', sanitize_title(wp_unslash($_GET['gd_inherit'])), 'inheritance');
		if ($term && !is_wp_error($term)) $sel['inheritance'] = (int) $term->term_id;
	}

	// -------------------------------------------------------
	// Taxonomy filters
	// -------------------------------------------------------
	$tax_query = ['relation' => 'AND'];
	foreach ($sel as $tax => $term_id) {
		if ($term_id) {
			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => [$term_id],
				'operator' => 'IN',
			];
		}
	}
	if (count($tax_query) === 1) $tax_query = [];

	// Optional: broaden search with term matches
	if (!$qs_all && $qs !== '') {
		$or = ['relation' => 'OR'];
		foreach (['cmt_type', 'inheritance', 'neuropathy', 'chromosome'] as $tax) {
			if ($sel[$tax]) continue;
			$ids = get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'search'=>$qs,'fields'=>'ids']);
			if (!is_wp_error($ids) && $ids) {
				$or[] = [
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => array_map('intval', $ids),
					'operator' => 'IN',
				];
			}
		}
		if (count($or) > 1) {
			if (empty($tax_query)) $tax_query = ['relation'=>'AND'];
			$tax_query[] = $or;
		}
	}

	// -------------------------------------------------------
	// Meta query (case-insensitive LIKE)
	// -------------------------------------------------------
	$meta_query = [];
	if (!$qs_all && $qs !== '') {
		$meta_query = ['relation' => 'OR'];
		foreach (eic_gl_search_meta_fields() as $key) {
			$meta_query[] = [
				'key'     => $key,
				'value'   => $qs,
				'compare' => 'LIKE',
			];
		}
	}

	// -------------------------------------------------------
	// Query args
	// -------------------------------------------------------
	$args = [
		'post_type'           => 'subtype',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $a['per_page'],
		'paged'               => eic_gl_get_paged(),
		'orderby'             => 'title',
		'order'               => 'ASC',
		'eic_genes_custom_sort' => 1,
	];

	if (!empty($tax_query)) $args['tax_query'] = $tax_query;
	if (!empty($meta_query)) $args['meta_query'] = $meta_query;
	if (!$qs_all && $qs !== '') {
		$args['s'] = $qs;
		$args['eic_gd_search'] = 1;
	}

	// -------------------------------------------------------
	// Optional SQL filters (meta_value lower, taxonomy names)
	// -------------------------------------------------------
	global $wpdb;
	if (!empty($meta_query)) {
		add_filter('posts_where', function($where) {
			return preg_replace('/(\b)meta_value(\s+)LIKE(\s+)/i', 'LOWER(meta_value) LIKE ', $where);
		}, 999);
	}

	if (!$qs_all && $qs !== '') {
		add_filter('posts_join', function ($join) use ($wpdb) {
			return $join
				. " LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = {$wpdb->posts}.ID"
				. " LEFT JOIN {$wpdb->term_taxonomy}   tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
				. " LEFT JOIN {$wpdb->terms}            t ON t.term_id = tt.term_id";
		});
		add_filter('posts_search', function ($search, $wp_query) use ($wpdb, $qs) {
			if (!$wp_query->get('eic_gd_search')) return $search;
			$like = '%' . $wpdb->esc_like($qs) . '%';
			return preg_replace('/\)\s*$/', $wpdb->prepare(" OR (t.name LIKE %s))", $like), $search, 1);
		}, 10, 2);
		add_filter('posts_distinct', fn() => 'DISTINCT');
	}

	add_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10, 2);

	$q = new WP_Query($args);

	// -------------------------------------------------------
	// Cleanup
	// -------------------------------------------------------
	remove_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10);

	// -------------------------------------------------------
	// Output (cards with pagination)
	// -------------------------------------------------------
	ob_start(); ?>
	<a id="cmt-genetics-database"></a>
	<div class="wp-block-query dr-blog">
	<?php
	$cards = [];
	if ($q->have_posts()) {
		while ($q->have_posts()) {
			$q->the_post();

			$gene = get_field('gene') ?: get_post_meta(get_the_ID(), 'gene_symbol', true) ?: get_the_title();
			$discovery = get_field('year_of_discovery') ?: '';
			$inherit = get_field('inheritance_pattern') ?: implode(', ', wp_get_post_terms(get_the_ID(), 'inheritance', ['fields'=>'names']));

			ob_start(); ?>
			<article class="dr-card wp-block-post">
				<?php if (has_post_thumbnail()): ?>
					<a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail('large', ['loading' => 'lazy']); ?>
					</a>
				<?php endif; ?>
				<h2 class="wp-block-post-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>
				<div class="wp-block-post-excerpt">
					<p><strong>Gene:</strong> <?php echo esc_html($gene); ?></p>
					<?php if ($discovery): ?><p><strong>Discovered:</strong> <?php echo esc_html($discovery); ?></p><?php endif; ?>
					<?php if ($inherit): ?><p><strong>Inheritance:</strong> <?php echo esc_html($inherit); ?></p><?php endif; ?>
					<a class="wp-block-read-more" href="<?php the_permalink(); ?>">Learn More</a>
					<div class="wp-block-post-date" style="text-align:center; margin-top:12px;">
						<small>Update: <?php echo esc_html(get_the_modified_date(get_option('date_format'))); ?></small>
					</div>
					<div style="height:20px;" aria-hidden="true" class="wp-block-spacer"></div>
				</div>
			</article>
			<?php $cards[] = ob_get_clean();
		}
		wp_reset_postdata();
	}

	$rows = array_chunk($cards, 3);
	?>
	<div class="dr-grid">
		<?php if ($rows): foreach ($rows as $i => $items):
			$is_last = ($i === count($rows) - 1);
			$count = count($items); ?>
			<div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="'.$count.'"' : ''; ?>>
				<?php echo implode('', $items); ?>
			</div>
		<?php endforeach; else: ?>
			<div class="dr-row dr-row--empty"><p>No results found.</p></div>
		<?php endif; ?>
	</div>
	<?php
	// Pagination
	$total_pages = max(1, (int) $q->max_num_pages);
	if ($total_pages > 1) {
		$current  = max(1, (int) eic_gl_get_paged());
		$base_url = get_permalink(get_queried_object_id()) ?: home_url('/genes/');
		$qs_params = $_GET;
		unset($qs_params['gd_paged']);

		$page_url = function (int $n) use ($base_url, $qs_params) {
			$qs2 = $qs_params;
			$qs2['gd_paged'] = $n;
			return esc_url(add_query_arg($qs2, $base_url) . '#cmt-genetics-database');
		};

		$items = [];
		if ($current > 1)
			$items[] = '<li><a class="prev page-numbers" href="' . $page_url($current - 1) . '">« Prev</a></li>';
		else
			$items[] = '<li><span class="prev page-numbers">« Prev</span></li>';

		$start = max(1, $current - 2);
		$end   = min($total_pages, $current + 2);

		if ($start > 1) {
			$items[] = '<li><a class="page-numbers" href="' . $page_url(1) . '">1</a></li>';
			if ($start > 2) $items[] = '<li><span class="page-numbers dots">…</span></li>';
		}
		for ($i = $start; $i <= $end; $i++) {
			if ($i === $current)
				$items[] = '<li><span class="page-numbers current">' . $i . '</span></li>';
			else
				$items[] = '<li><a class="page-numbers" href="' . $page_url($i) . '">' . $i . '</a></li>';
		}
		if ($end < $total_pages) {
			if ($end < $total_pages - 1) $items[] = '<li><span class="page-numbers dots">…</span></li>';
			$items[] = '<li><a class="page-numbers" href="' . $page_url($total_pages) . '">' . $total_pages . '</a></li>';
		}
		if ($current < $total_pages)
			$items[] = '<li><a class="next page-numbers" href="' . $page_url($current + 1) . '">Next »</a></li>';
		else
			$items[] = '<li><span class="next page-numbers">Next »</span></li>';

		echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' . implode('', $items) . '</ul></nav>';
	}
	?>
	</div>
	<?php return ob_get_clean();
});
