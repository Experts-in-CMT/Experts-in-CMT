
<?php
/**
 * Plugin Name: CMT Genes — Search
 * Description: Extends the WP search to include taxonomy term names and selected ACF text fields for CPT `subtype`.
 * Author: Experts in CMT / Kenny
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) { exit; }

// Adjust which ACF meta keys should be searched.
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
 * Join taxonomy & postmeta for search queries when `is_search()` and post_type=subtype.
 */
add_filter('posts_join', function($join, $query) {
	global $wpdb;
	if (!is_admin() && $query->is_main_query() && $query->is_search()) {
		$post_type = (array) $query->get('post_type');
		if (in_array('subtype', $post_type, true)) {
			// join term relationships for taxonomy term name search
			$join .= " LEFT JOIN {$wpdb->term_relationships} tr ON ({$wpdb->posts}.ID = tr.object_id)";
			$join .= " LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
			$join .= " LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)";
			// join postmeta for ACF text fields
			$join .= " LEFT JOIN {$wpdb->postmeta} pm ON ({$wpdb->posts}.ID = pm.post_id)";
		}
	}
	return $join;
}, 10, 2);

/**
 * Expand WHERE to search title/content, taxonomy term names, and whitelisted ACF meta values.
 */
add_filter('posts_where', function($where, $query) {
	global $wpdb;
	if (!is_admin() && $query->is_main_query() && $query->is_search()) {
		$post_type = (array) $query->get('post_type');
		$s = $query->get('s');
		if ($s && in_array('subtype', $post_type, true)) {
			$safe = esc_sql($wpdb->esc_like($s));
			$acf_keys = array_map('esc_sql', cmtgenes_search_get_acf_keys());
			$acf_keys_sql = "('" . implode("','", $acf_keys) . "')";

			$where  = $wpdb->prepare(" AND ( ");
			$where .= $wpdb->prepare(" {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s ", "%{$safe}%", "%{$safe}%");
			$where .= $wpdb->prepare(" OR t.name LIKE %s ", "%{$safe}%");
			$where .= $wpdb->prepare(" OR (pm.meta_key IN {$acf_keys_sql} AND pm.meta_value LIKE %s) ", "%{$safe}%");
			$where .= ")"; // close AND (...)
		}
	}
	return $where;
}, 10, 2);

/**
 * Force distinct to avoid dupes from the joins.
 */
add_filter('posts_distinct', function($distinct, $query) {
	if (!is_admin() && $query->is_main_query() && $query->is_search()) {
		$post_type = (array) $query->get('post_type');
		if (in_array('subtype', $post_type, true)) {
			return 'DISTINCT';
		}
	}
	return $distinct;
}, 10, 2);
```

> Optional: add the SQL index for speed after migrations:
>
> `CREATE INDEX idx_postmeta_key_post ON wp_postmeta (meta_key(191), post_id);`

---

## 2) Theme include: shortcodes for filter UI and loop
Create `wp-content/themes/yourtheme/inc/genes-filters.php` and require it from `functions.php`.

```php
<?php
if (!defined('ABSPATH')) { exit; }

/**
 * [genes_filter] — renders 2×2 dropdown grid + full‑width search.
 * Submits as GET to current page with anchor jump to #cmt-genetics-database
 */
add_shortcode('genes_filter', function($atts){
	$atts = shortcode_atts([
		'action' => '',
	], $atts, 'genes_filter');

	$action = $atts['action'] ?: esc_url_raw( add_query_arg([], get_permalink()) );

	// Current selections
	$sel = [
		'cmt_type'    => isset($_GET['cmt_type']) ? (int) $_GET['cmt_type'] : 0,
		'inheritance' => isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0,
		'neuropathy'  => isset($_GET['neuropathy']) ? (int) $_GET['neuropathy'] : 0,
		'chromosome'  => isset($_GET['chromosome']) ? (int) $_GET['chromosome'] : 0,
		'q'           => isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '',
	];

	// Helper: taxonomy dropdowns
	$taxes = [
		['cmt_type',    'Type'],
		['inheritance', 'Inheritance'],
		['neuropathy',  'Neuropathy'],
		['chromosome',  'Chromosome'],
	];

	ob_start(); ?>
	<form id="genes-filter" class="genes-filter" action="<?php echo esc_url($action); ?>#cmt-genetics-database" method="get" role="search" aria-label="Filter the Genes Database">
		<div class="gf-grid" data-auto-apply>
			<?php foreach ($taxes as [$tax, $label]) : ?>
				<label class="gf-field">
					<span class="gf-label"><?php echo esc_html($label); ?></span>
					<select name="<?php echo esc_attr($tax); ?>" aria-label="<?php echo esc_attr($label); ?>">
						<option value="0">All</option>
						<?php
						$terms = get_terms([
							'taxonomy'   => $tax,
							'hide_empty' => false,
							'orderby'    => 'name',
							'order'      => 'ASC',
						]);
						if (!is_wp_error($terms)) {
							foreach ($terms as $term) {
								printf('<option value="%1$s" %3$s>%2$s</option>',
									esc_attr($term->term_id),
									esc_html($term->name),
									selected($sel[$tax], $term->term_id, false)
								);
							}
						}
						?>
					</select>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="gf-search">
			<label>
				<span class="screen-reader-text">Search genes and subtypes</span>
				<input type="search" name="q" value="<?php echo esc_attr($sel['q']); ?>" placeholder="Search genes, aliases, or subtypes" aria-label="Search genes and subtypes" />
			</label>
			<button type="submit" class="gf-submit">Search</button>
			<button type="button" class="gf-reset" data-reset>Reset</button>
		</div>
	</form>
	<?php
	return ob_get_clean();
});

/**
 * [genes_loop] — reads GET params and outputs the loop.
 * Use your existing card markup inside the while loop.
 */
add_shortcode('genes_loop', function($atts){
	$atts = shortcode_atts([
		'ppp' => 24,
	], $atts, 'genes_loop');

	$tax_query = ['relation' => 'AND'];
	$map = [
		'cmt_type'    => 'cmt_type',
		'inheritance' => 'inheritance',
		'neuropathy'  => 'neuropathy',
		'chromosome'  => 'chromosome',
	];
	foreach ($map as $param => $tax) {
		if (!empty($_GET[$param]) && (int) $_GET[$param] > 0) {
			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => [(int) $_GET[$param]],
				'include_children' => true,
			];
		}
	}

	$s = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

	$paged = max(1, get_query_var('paged') ?: (isset($_GET['pg']) ? (int) $_GET['pg'] : 1));

	$args = [
		'post_type'      => 'subtype',
		'posts_per_page' => (int) $atts['ppp'],
		'paged'          => $paged,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'tax_query'      => count($tax_query) > 1 ? $tax_query : [],
	];
	if ($s !== '') {
		$args['s'] = $s;
	}

	$q = new WP_Query($args);

	ob_start();
	?>
	<div id="cmt-genetics-database" class="genes-loop">
		<div class="genes-loop__meta">
			<strong><?php echo (int) $q->found_posts; ?></strong> result<?php echo $q->found_posts == 1 ? '' : 's'; ?>
		</div>

		<div class="genes-loop__grid">
		<?php if ($q->have_posts()) : while ($q->have_posts()) : $q->the_post(); ?>
			<article <?php post_class('genes-card'); ?>>
				<header class="genes-card__header">
					<h3 class="genes-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				</header>
				<div class="genes-card__body">
					<?php if (has_excerpt()) { the_excerpt(); } else { echo wp_kses_post(wp_trim_words(get_the_content(), 28)); } ?>
				</div>
				<footer class="genes-card__footer">
					<a class="btn btn-primary" href="<?php the_permalink(); ?>">Read more</a>
				</footer>
			</article>
		<?php endwhile; else: ?>
			<p>No results. Try adjusting filters.</p>
		<?php endif; ?>
		</div>

		<?php
		// Pagination (uses `pg` to avoid stomping on pretty permalinks)
		$total = (int) $q->max_num_pages;
		if ($total > 1) {
			$current = $paged;
			echo '<nav class="genes-pagination" aria-label="Results">';
			for ($i = 1; $i <= $total; $i++) {
				$url = add_query_arg(array_merge($_GET, ['pg' => $i]));
				printf('<a class="%s" href="%s#cmt-genetics-database">%d</a>', $i === $current ? 'is-current' : '', esc_url($url), $i);
			}
			echo '</nav>';
		}
		?>
	</div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
});
```

In your theme `functions.php`:

```php
require_once get_template_directory() . '/inc/genes-filters.php';
```

---

## 3) Front‑end JS: auto‑apply and reset
Create `assets/js/genes-filter.js` and enqueue in your theme. Adds change listeners to the four selects, debounced submit on typing, and a Reset that clears params.

```js
(function(){
  const form = document.getElementById('genes-filter');
  if (!form) return;

  const searchInput = form.querySelector('input[name="q"]');
  const selects = form.querySelectorAll('select');
  const resetBtn = form.querySelector('[data-reset]');

  // Auto submit on dropdown change
  selects.forEach(sel => sel.addEventListener('change', () => form.requestSubmit()));

  // Debounced submit for search typing
  let t;
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => form.requestSubmit(), 400);
    });
  }

  // Reset clears fields and navigates to base anchor
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      // Clear inputs
      selects.forEach(sel => sel.value = '0');
      if (searchInput) searchInput.value = '';
      // Build base URL without params
      const url = new URL(window.location.href.split('#')[0]);
      url.search = '';
      window.location.href = url.toString() + '#cmt-genetics-database';
    });
  }
})();
```

Enqueue in `functions.php`:

```php
add_action('wp_enqueue_scripts', function(){
	$rel = '/assets/js/genes-filter.js';
	$path = get_template_directory() . $rel;
	if (file_exists($path)) {
		wp_enqueue_script('genes-filter', get_template_directory_uri() . $rel, [], filemtime($path), true);
	}
}, 100);
```

---

## 4) CSS: 2×2 grid + full‑width search + equal‑height cards
Add to your theme stylesheet or a dedicated `genes.css` and enqueue.

```css
/* Filter wrapper */
.genes-filter { display:block; margin: 1.5rem 0 2rem; }

/* 2×2 grid for dropdowns */
.gf-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 1rem; }
@media (min-width: 960px){ .gf-grid{ grid-template-columns: repeat(2, 1fr); } }

.gf-field { display:flex; flex-direction:column; }
.gf-label { font-size: .9rem; font-weight:600; margin-bottom:.35rem; color:#174777; }

/* Native select styling that matches site tokens */
.genes-filter select { 
	width:100%; padding:.625rem .75rem; border:1px solid #e5e7eb; border-radius:12px; background:#fff; 
	appearance:none; line-height:1.3; 
	background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238E97A3' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
	background-repeat:no-repeat; background-position: right .75rem center; background-size:12px 8px;
}

/* Full‑width search row */
.gf-search { display:flex; gap:.75rem; align-items:center; margin-top:1rem; }
.gf-search input[type="search"] { flex:1; padding:.625rem .75rem; border:1px solid #e5e7eb; border-radius:12px; }
.gf-submit, .gf-reset { padding:.625rem .9rem; border-radius:12px; border:1px solid #d1d5db; background:#f8fafc; cursor:pointer; }
.gf-submit:hover, .gf-reset:hover { background:#f3f6f8; }

/* Loop grid and equal height cards */
.genes-loop__grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
.genes-card { display:flex; flex-direction:column; height:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,.06); }
.genes-card__header { padding:1rem 1rem .5rem; }
.genes-card__body { padding:0 1rem 1rem; flex:1 1 auto; }
.genes-card__footer { padding: .75rem 1rem 1rem; margin-top:auto; }
.genes-card__title { margin:0; font-size:1.1rem; }
.genes-card a.btn { display:inline-block; padding:.55rem .9rem; border-radius:10px; border:1px solid #174777; color:#174777; text-decoration:none; }
.genes-card a.btn:hover { background:#174777; color:#fff; }

/* Pagination */
.genes-pagination { display:flex; gap:.5rem; margin-top:1rem; }
.genes-pagination a { padding:.45rem .7rem; border:1px solid #e5e7eb; border-radius:8px; text-decoration:none; }
.genes-pagination a.is-current { background:#174777; color:#fff; border-color:#174777; }
```

Enqueue if split into a file:

```php
add_action('wp_enqueue_scripts', function(){
	$rel = '/assets/css/genes.css';
	$path = get_template_directory() . $rel;
	if (file_exists($path)) {
		wp_enqueue_style('genes-css', get_template_directory_uri() . $rel, [], filemtime($path));
	}
}, 110);
```

---

## 5) Usage on the page

```html
<!-- Filter UI above the loop -->
[genes_filter]

<!-- Results anchor target and loop -->
[genes_loop ppp="24"]
```

This will keep the URL clean with params like `?cmt_type=12&inheritance=34&neuropathy=56&chromosome=7&q=MFN2#cmt-genetics-database` and preserve state on reload.

---

## 6) Notes and next steps
- Counts per filter option can be added via a preflight REST call later. For now, we render straightforward selects for speed.
- If you see “Briefly unavailable for scheduled maintenance,” remove `.maintenance` from webroot. Then re‑enqueue assets.
- Remember to add the `wp_postmeta` index after DB migrations for snappy search.
- If you want auto‑scroll to the grid on submit, the form action already targets the anchor.
