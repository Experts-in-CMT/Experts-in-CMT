<?php
/* ============================================================
   GENES DATABASE LOOP SHORTCODE (responsive to filter UI)
   Simplified, reliable search using qs= and ACF/meta/tax filters.
   Keeps all layout + styling from original.
   ============================================================ */


/* ============================================================
   ============================================================
   ===================== [ SECTION: SORT LOGIC ] ===============
   ============================================================
   ============================================================ */

/**
 * Custom sorter for 'type_classification' — immutable order.
 * Empties FIRST (debug), then FIELD() sequence, then post_title ASC.
 */
function eic_genes_custom_sort_clauses($clauses, $wp_query) {
	if (!$wp_query->get('eic_genes_custom_sort')) return $clauses;

	global $wpdb;
	$custom_type_order = [
		'CMT1','CMT2','CMTX','CMT4','CMTDI','CMTRI',
		'dHMN','dSMA','GAN','HMSN','HSAN','HSN','SMA-LEP','Unclassified'
	];

	// Ensure join alias `mt1` exists for type_classification
	if (strpos($clauses['join'] ?? '', ' mt1 ') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} mt1
		                      ON (mt1.post_id = {$wpdb->posts}.ID AND mt1.meta_key = 'type_classification')";
	}

	// Build FIELD() list
	$quoted = array_map(function ($v) use ($wpdb) {
		return trim($wpdb->prepare('%s', $v), "'");
	}, $custom_type_order);
	$field_list = "'" . implode("','", $quoted) . "'";

	// Empties first → then FIELD() order → then title ASC
	$clauses['orderby'] =
		"CASE WHEN mt1.meta_value IS NULL OR mt1.meta_value = '' THEN 0 ELSE 1 END ASC, " .
		"FIELD(mt1.meta_value, {$field_list}) ASC, " .
		"{$wpdb->posts}.post_title ASC";

	return $clauses;
}


/* ============================================================
   ============================================================
   ===================== [ SECTION: SHORTCODE ] ================
   ============================================================
   ============================================================ */

add_shortcode('genes_loop', function ($atts = []) {
	$a = shortcode_atts([
		'per_page' => 12,
	], $atts);

	/* --------------------------------------------------------
	   INPUTS (GET params)
	   -------------------------------------------------------- */
	$qs_raw = isset($_GET['qs']) ? (string) $_GET['qs'] : '';
	$qs     = sanitize_text_field($qs_raw);
	$qs_all = (strtolower(trim($qs)) === 'all');

	$sel = [
		'cmt_type'    => isset($_GET['cmt_type'])    ? (int) $_GET['cmt_type']    : 0,
		'inheritance' => isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0,
		'neuropathy'  => isset($_GET['neuropathy'])  ? (int) $_GET['neuropathy']  : 0,
		'chromosome'  => isset($_GET['chromosome'])  ? (int) $_GET['chromosome']  : 0,
	];

	/* --------------------------------------------------------
	   TAXONOMY FILTERS (AND)
	   -------------------------------------------------------- */
	$tax_query = ['relation' => 'AND'];
	foreach ($sel as $tax => $id) {
		if ($id) {
			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => [$id],
			];
		}
	}
	if (count($tax_query) === 1) $tax_query = [];

	/* --------------------------------------------------------
	   META SEARCH (always OR)
	   -------------------------------------------------------- */
	$args = [
		'post_type'      => 'subtype',
		'post_status'    => 'publish',
		'posts_per_page' => max(1, (int) $a['per_page']),
		'paged'          => max(1, (int) ($_GET['gd_paged'] ?? 1)),
		'orderby'        => 'title',
		'order'          => 'ASC',
		// >>> Activate canonical sort <<<
		'eic_genes_custom_sort' => 1,
	];

	if (!empty($tax_query)) $args['tax_query'] = $tax_query;

	if (!$qs_all && $qs !== '') {
		$args['meta_query'] = [
			'relation' => 'OR',
			[ 'key' => 'gene',              'value' => $qs, 'compare' => '=' ],
			[ 'key' => 'gene_symbol',       'value' => $qs, 'compare' => '=' ],
			[ 'key' => 'subtype',           'value' => $qs, 'compare' => 'LIKE' ],
			[ 'key' => 'year_of_discovery', 'value' => $qs, 'compare' => 'LIKE' ],
			[ 'key' => 'alternate_gene_1',  'value' => $qs, 'compare' => 'LIKE' ],
			[ 'key' => 'alternate_gene_2',  'value' => $qs, 'compare' => 'LIKE' ],
			[ 'key' => 'alternate_gene_3',  'value' => $qs, 'compare' => 'LIKE' ],
		];
	}

	/* --------------------------------------------------------
	   RUN QUERY (with canonical sorter attached)
	   -------------------------------------------------------- */
	add_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10, 2);
	$q = new WP_Query($args);
	remove_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10);

/* ========================================================
   ========================================================
   =============== [ SECTION: OUTPUT MARKUP ] ==============
   ========================================================
   ======================================================== */

ob_start(); ?>
<div class="wp-block-query dr-blog" style="scroll-margin-top:100px;">
	<?php
	$cards = [];
	if ($q->have_posts()) {
		while ($q->have_posts()) {
			$q->the_post();

			$gene_symbol    = get_field('gene') ?: get_post_meta(get_the_ID(), 'gene_symbol', true);
			$display_gene   = $gene_symbol ?: get_the_title();
			$year_discovery = get_field('year_of_discovery') ?: '';
			$inherit_label  = get_field('inheritance_pattern') ?: implode(', ', wp_get_post_terms(get_the_ID(), 'inheritance', ['fields' => 'names']));

			ob_start(); ?>
			<article class="dr-card wp-block-post">
				<?php if (has_post_thumbnail()) : ?>
					<a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail('large', ['loading' => 'lazy', 'decoding' => 'async']); ?>
					</a>
				<?php endif; ?>

				<h2 class="wp-block-post-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>

				<div class="wp-block-post-excerpt">
					<p><strong>Gene:</strong> <?php echo esc_html($display_gene); ?></p>
					<?php if ($year_discovery): ?>
						<p><strong>Discovered:</strong> <?php echo esc_html($year_discovery); ?></p>
					<?php endif; ?>
					<?php if ($inherit_label): ?>
						<p><strong>Inheritance:</strong> <?php echo esc_html($inherit_label); ?></p>
					<?php endif; ?>

					<a class="wp-block-read-more" href="<?php the_permalink(); ?>">Learn More</a>

					<div class="wp-block-post-date" style="text-align:center; margin-top:12px;">
						<small>Update: <?php echo esc_html(get_the_modified_date(get_option('date_format'))); ?></small>
					</div>

					<div style="height:20px;" aria-hidden="true" class="wp-block-spacer"></div>
				</div>
			</article>
			<?php
			$cards[] = ob_get_clean();
		}
		wp_reset_postdata();
	}

	$rows       = array_chunk($cards, 3);
	$total_rows = count($rows);
	?>

	<?php if (empty($rows)): ?>
		<div id="genes-no-results" class="dr-row dr-row--empty"
			style="
				margin: -100px auto 64px auto;
				display: flex;
				justify-content: center;
				align-items: flex-start;
				max-width: 700px;
				width: 100%;
			">
			<p style="font-size:1.1rem; color:#333; text-align:left;">
				No results found.<br>Try adjusting your filters or search term.
			</p>
		</div>
	<?php endif; ?>

	<div class="dr-grid">
		<?php if (!empty($rows)): ?>
			<?php foreach ($rows as $i => $row_items):
				$is_last = ($i === $total_rows - 1);
				$count   = count($row_items); ?>
				<div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="'.(int) $count.'"' : ''; ?>>
					<?php echo implode('', $row_items); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	
<script>
/* ============================================================
   =========== [ SECTION: SMOOTH SCROLL TO RESULTS ] ===========
   ============================================================ */
(function () {
  // Tweak this number to land lower/higher
  var OFFSET = 320; // smaller = land lower (filters higher). larger = land higher.

  function findResults() {
    return document.querySelector('.wp-block-query.dr-blog');
  }

  function scrollToResults(offset) {
    var el = findResults();
    if (!el) return;
    requestAnimationFrame(function () {
      var rect = el.getBoundingClientRect();
      var y = rect.top + window.scrollY - (typeof offset === 'number' ? offset : OFFSET);
      window.scrollTo({ top: y, behavior: 'smooth' });
    });
  }

  // Observe AJAX injections into results
  var container = findResults();
  if (container) {
    var observer = new MutationObserver(function () {
      setTimeout(function () { scrollToResults(OFFSET); }, 100);
    });
    observer.observe(container, { childList: true, subtree: true });
  }

  // Pagination clicks
  document.addEventListener('click', function (e) {
    if (e.target.closest('a.page-numbers')) {
      setTimeout(function () { scrollToResults(OFFSET); }, 350);
    }
  }, true);

  // Filter form submissions
  var form = document.querySelector('form.genes-filter');
  if (form) {
    form.addEventListener('submit', function () {
      setTimeout(function () { scrollToResults(OFFSET); }, 100);
    });
  }

  // On reload with query params (so pagination/filters land correctly)
  window.addEventListener('DOMContentLoaded', function () {
    var p = new URLSearchParams(window.location.search);
    if (['qs','cmt_type','inheritance','neuropathy','chromosome','gd_paged']
        .some(function (k) { return p.has(k) && p.get(k) !== ''; })) {
      setTimeout(function () { scrollToResults(OFFSET); }, 100);
    }
  });
})();
</script>


	<?php
	/* ====================================================
	   =============== [ SECTION: PAGINATION ] =============
	   ==================================================== */
	$total_pages = max(1, (int) $q->max_num_pages);
	if ($total_pages > 1) {
		$current  = max(1, (int) ($_GET['gd_paged'] ?? 1));
		$base_url = get_permalink(get_queried_object_id()) ?: home_url('/genes/');
		$qs_params = $_GET;
		unset($qs_params['gd_paged']);

		$page_url = function (int $n) use ($base_url, $qs_params) {
			$qs2 = $qs_params;
			$qs2['gd_paged'] = $n;
			return esc_url(add_query_arg($qs2, $base_url));
		};

		$items = [];
		if ($current > 1) $items[] = '<li><a class="prev page-numbers" href="'.$page_url($current - 1).'">« Prev</a></li>';
		else $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';

		$end   = $total_pages;
		$start = max(1, $current - 2);
		$stop  = min($end, $current + 2);

		if ($start > 1) {
			$items[] = '<li><a class="page-numbers" href="'.$page_url(1).'">1</a></li>';
			if ($start > 2) $items[] = '<li><span class="page-numbers dots">…</span></li>';
		}
		for ($i = $start; $i <= $stop; $i++) {
			if ($i === $current) $items[] = '<li><span class="page-numbers current">'.$i.'</span></li>';
			else $items[] = '<li><a class="page-numbers" href="'.$page_url($i).'">'.$i.'</a></li>';
		}
		if ($stop < $end) {
			if ($stop < $end - 1) $items[] = '<li><span class="page-numbers dots">…</span></li>';
			$items[] = '<li><a class="page-numbers" href="'.$page_url($end).'">'.$end.'</a></li>';
		}
		if ($current < $total_pages)
			$items[] = '<li><a class="next page-numbers" href="'.$page_url($current + 1).'">Next »</a></li>';
		else
			$items[] = '<li><span class="next page-numbers">Next »</span></li>';

		echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">'.implode('', $items).'</ul></nav>';
	}
	?>
</div>
<?php
return ob_get_clean();

});
