<?php
/* ============================================================
   GENES DATABASE LOOP SHORTCODE (responsive to filter UI)
   - Reads new GET params from [genes_filter]:
       cmt_type, inheritance, neuropathy, chromosome, qs
   - Backward-compatible with old params:
       gd_q, gd_type, gd_inherit
   - Case-insensitive meta search over specific ACF fields
   - Custom order by type_classification (FIELD()) preserved
   - Pagination via ?gd_paged=
   - Adds #cmt-genetics-database anchor jump
   ============================================================ */

/**
 * [genes_loop] — rows of 3 with centered last row; preserves CSS classes
 * Usage: [genes_loop per_page="12"]
 */

// --- Config: ACF/meta fields to search (case-insensitive) ---
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

// --- Custom sorter so we can remove it after the query ---
function eic_genes_custom_sort_clauses($clauses, $wp_query) {
    if (!$wp_query->get('eic_genes_custom_sort')) return $clauses;

    global $wpdb;
    $custom_type_order = [
        'CMT1','CMT2','CMT4','CMTX','CMTDI','CMTRI',
        'dHMN','dSMA','GAN','HMSN','HSAN','HSN','SMA-LEP','Unclassified'
    ];

    // LEFT JOIN to postmeta for type_classification (alias mt1)
    if (strpos($clauses['join'] ?? '', ' mt1 ') === false) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} mt1
                              ON (mt1.post_id = {$wpdb->posts}.ID AND mt1.meta_key = 'type_classification')";
    }

    // Build FIELD() list for explicit ordering
    $quoted = array_map(function ($v) use ($wpdb) {
        return trim($wpdb->prepare('%s', $v), "'");
    }, $custom_type_order);
    $field_list = "'" . implode("','", $quoted) . "'";

    // Missing/empty LAST, then custom order, then title ASC
    $clauses['orderby'] =
        "CASE WHEN mt1.meta_value IS NULL OR mt1.meta_value = '' THEN 1 ELSE 0 END ASC, " .
        "FIELD(mt1.meta_value, {$field_list}) ASC, " .
        "{$wpdb->posts}.post_title ASC";

    return $clauses;
}

// --- Helpers ---
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
    // Read inputs (new params first, fallback to old)
    // -------------------------------------------------------
    // Text search
    $qs_raw = '';
    if (isset($_GET['qs'])) {
        $qs_raw = (string) $_GET['qs'];
    } elseif (isset($_GET['gd_q'])) {
        $qs_raw = (string) $_GET['gd_q'];
    }
    $qs     = sanitize_text_field($qs_raw);
    $qs_all = (trim($qs) !== '' && strtolower(trim($qs)) === 'all');

    // Taxonomy term IDs (new UI — single-select IDs)
    $sel = [
        'cmt_type'    => isset($_GET['cmt_type'])    ? (int) $_GET['cmt_type']    : 0,
        'inheritance' => isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0,
        'neuropathy'  => isset($_GET['neuropathy'])  ? (int) $_GET['neuropathy']  : 0,
        'chromosome'  => isset($_GET['chromosome'])  ? (int) $_GET['chromosome']  : 0,
    ];

    // Fallback: old params by slug
    if (!$sel['cmt_type'] && !empty($_GET['gd_type'])) {
        $term = get_term_by('slug', sanitize_title(wp_unslash($_GET['gd_type'])), 'cmt_type');
        if ($term && !is_wp_error($term)) $sel['cmt_type'] = (int) $term->term_id;
    }
    if (!$sel['inheritance'] && !empty($_GET['gd_inherit'])) {
        $term = get_term_by('slug', sanitize_title(wp_unslash($_GET['gd_inherit'])), 'inheritance');
        if ($term && !is_wp_error($term)) $sel['inheritance'] = (int) $term->term_id;
    }

    // -------------------------------------------------------
    // Build tax_query
    // -------------------------------------------------------
    $tax_query = ['relation' => 'AND'];
    if ($sel['cmt_type'])    $tax_query[] = ['taxonomy'=>'cmt_type',   'field'=>'term_id', 'terms'=>[$sel['cmt_type']],    'operator'=>'IN'];
    if ($sel['inheritance']) $tax_query[] = ['taxonomy'=>'inheritance','field'=>'term_id', 'terms'=>[$sel['inheritance']], 'operator'=>'IN'];
    if ($sel['neuropathy'])  $tax_query[] = ['taxonomy'=>'neuropathy', 'field'=>'term_id', 'terms'=>[$sel['neuropathy']],  'operator'=>'IN'];
    if ($sel['chromosome'])  $tax_query[] = ['taxonomy'=>'chromosome', 'field'=>'term_id', 'terms'=>[$sel['chromosome']],  'operator'=>'IN'];
    if (count($tax_query) === 1) $tax_query = []; // no active clauses

    // If qs provided (and not "All"), broaden with OR across tax NAMES (unless that tax already selected)
    if (!$qs_all && $qs !== '') {
        $or = ['relation' => 'OR'];
        foreach (['cmt_type','inheritance','neuropathy','chromosome'] as $tax) {
            if ($sel[$tax]) continue; // don't broaden an explicit choice
            $ids = get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'search'=>$qs,'fields'=>'ids']);
            if (!is_wp_error($ids) && !empty($ids)) {
                $or[] = ['taxonomy'=>$tax,'field'=>'term_id','terms'=>array_map('intval',$ids),'operator'=>'IN'];
            }
        }
        if (count($or) > 1) {
            if (empty($tax_query)) $tax_query = ['relation'=>'AND'];
            $tax_query[] = $or;
        }
    }

    // -------------------------------------------------------
    // Meta (case-insensitive LIKE on whitelisted fields)
    // -------------------------------------------------------
    $meta_query = [];
    if (!$qs_all && $qs !== '') {
        $meta_query = ['relation' => 'OR'];
        $lower = mb_strtolower($qs);
        foreach (eic_gl_search_meta_fields() as $key) {
            $meta_query[] = ['key'=>$key,'value'=>$lower,'compare'=>'LIKE']; // LOWER(meta_value) handled via WHERE filter
        }
    }

    // -------------------------------------------------------
    // Query args
    // -------------------------------------------------------
    $args = [
        'post_type'      => 'subtype',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, (int) $a['per_page']),
        'paged'          => eic_gl_get_paged(),
        'no_found_rows'  => false,

        // baseline fallback order — overridden by custom sorter
        'orderby'        => 'title',
        'order'          => 'ASC',

        // enable custom FIELD() order for type_classification
        'eic_genes_custom_sort' => 1,
    ];
    if (!empty($tax_query))  $args['tax_query']  = $tax_query;
    if (!empty($meta_query)) $args['meta_query'] = $meta_query;

    // Optional: also search title/content/excerpt (kept for parity)
    if (!$qs_all && $qs !== '') {
        $args['s'] = $qs;
        $args['eic_gd_search'] = 1; // flag for scoped SQL joins below
    }

    // -------------------------------------------------------
    // Scoped SQL tweaks for this query only
    //  - LOWER(meta_value) LIKE '%lowered%'
    //  - Join terms when s=… so we can OR term-name matches into the WHERE
    // -------------------------------------------------------
    global $wpdb;
    $added_where_filter = false;
    $added_join_filter = false;
    $added_search_filter = false;
    $added_distinct_filter = false;

    if (!empty($meta_query)) {
        $where_filter = function($where) {
            return preg_replace('/(\b)meta_value(\s+)LIKE(\s+)/i', 'LOWER(meta_value) LIKE ', $where);
        };
        add_filter('posts_where', $where_filter, 999);
        $added_where_filter = $where_filter;
    }

    if (!$qs_all && $qs !== '') {
        // Term joins to allow searching t.name via posts_search filter
        $join_cb = function ($join) use ($wpdb) {
            return $join
                . " LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = {$wpdb->posts}.ID"
                . " LEFT JOIN {$wpdb->term_taxonomy}   tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
                . " LEFT JOIN {$wpdb->terms}            t ON t.term_id = tt.term_id";
        };
        $search_cb = function ($search, $wp_query) use ($wpdb, $qs) {
            if (!$wp_query->get('eic_gd_search')) return $search;
            $like = '%' . $wpdb->esc_like($qs) . '%';
            if ($search && substr(trim($search), -1) === ')') {
                $search = preg_replace('/\)\s*$/', $wpdb->prepare(" OR (t.name LIKE %s))", $like), $search, 1);
            } else {
                $search .= $wpdb->prepare(" AND (t.name LIKE %s)", $like);
            }
            return $search;
        };
        $distinct_cb = function ($distinct) { return 'DISTINCT'; };

        add_filter('posts_join',     $join_cb);
        add_filter('posts_search',   $search_cb, 10, 2);
        add_filter('posts_distinct', $distinct_cb);

        $added_join_filter     = $join_cb;
        $added_search_filter   = $search_cb;
        $added_distinct_filter = $distinct_cb;
    }

    // Attach custom sorter
    add_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10, 2);

    // -------------------------------------------------------
    // Run query
    // -------------------------------------------------------
    $q = new WP_Query($args);

    // Cleanup filters
    remove_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10);
    if ($added_where_filter)   remove_filter('posts_where', $added_where_filter, 999);
    if ($added_join_filter)    remove_filter('posts_join', $added_join_filter);
    if ($added_search_filter)  remove_filter('posts_search', $added_search_filter, 10);
    if ($added_distinct_filter)remove_filter('posts_distinct', $added_distinct_filter);

    // -------------------------------------------------------
    // OUTPUT
    // -------------------------------------------------------
    ob_start(); ?>
    <a id="cmt-genetics-database"></a>
    <div class="wp-block-query dr-blog">
      <?php
      // Build cards (preserve classes: dr-card + wp-block-post)
      $cards = [];
      if ($q->have_posts()) {
          while ($q->have_posts()) {
              $q->the_post();

              // ACF/meta reads with graceful fallbacks
              $gene_symbol = function_exists('get_field') ? (get_field('gene', get_the_ID()) ?: '') : '';
              if (!$gene_symbol) {
                  $gene_symbol = get_post_meta(get_the_ID(), 'gene_symbol', true) ?: '';
              }
              $display_gene   = $gene_symbol !== '' ? $gene_symbol : get_the_title();

              $year_discovery = function_exists('get_field') ? (get_field('year_of_discovery', get_the_ID()) ?: '') : '';

              // Inheritance label: ACF first, then taxonomy fallback
              $inherit_acf    = function_exists('get_field') ? (get_field('inheritance_pattern', get_the_ID()) ?: '') : '';
              $inherit_terms  = wp_get_post_terms(get_the_ID(), 'inheritance', ['fields' => 'names']);
              $inherit_label  = $inherit_acf ?: (!empty($inherit_terms) ? implode(', ', $inherit_terms) : '');

              ob_start(); ?>
              <article class="dr-card wp-block-post">
                  <?php if (has_post_thumbnail()) : ?>
                      <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                          <?php the_post_thumbnail('large', ['loading' => 'lazy', 'decoding' => 'async']); ?>
                      </a>
                  <?php endif; ?>

                  <!-- Subtype first (post title) -->
                  <h2 class="wp-block-post-title">
                      <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                  </h2>

                  <div class="wp-block-post-excerpt">
                      <p><strong>Gene:</strong> <?php echo esc_html($display_gene); ?></p>
                      <?php if ($year_discovery !== ''): ?>
                          <p><strong>Discovered:</strong> <?php echo esc_html($year_discovery); ?></p>
                      <?php endif; ?>
                      <?php if ($inherit_label !== ''): ?>
                          <p><strong>Inheritance:</strong> <?php echo esc_html($inherit_label); ?></p>
                      <?php endif; ?>

                      <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Learn More</a>

                      <div class="wp-block-post-date" style="text-align:center; margin-top:12px;">
                          <small>Update: <?php echo esc_html( get_the_modified_date( get_option('date_format') ) ); ?></small>
                      </div>

                      <div style="height:20px;" aria-hidden="true" class="wp-block-spacer"></div>
                  </div>
              </article>
              <?php
              $cards[] = ob_get_clean();
          }
          wp_reset_postdata();
      }

      // Chunk into rows of 3
      $rows       = array_chunk($cards, 3);
      $total_rows = count($rows);
      ?>
      <div class="dr-grid">
        <?php if (!empty($rows)) : ?>
          <?php foreach ($rows as $i => $row_items) :
              $is_last = ($i === $total_rows - 1);
              $count   = count($row_items); ?>
              <div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="'.(int) $count.'"' : ''; ?>>
                <?php echo implode('', $row_items); ?>
              </div>
          <?php endforeach; ?>
        <?php else : ?>
          <div class="dr-row dr-row--empty"><p>No results found.</p></div>
        <?php endif; ?>
      </div>
<?php
// ----- Shortcode-safe, query-string pagination using gd_paged -----
$total_pages = max(1, (int) $q->max_num_pages);
if ($total_pages > 1) {
    $current  = max(1, (int) eic_gl_get_paged());

    // Always build links off the page hosting the shortcode
    $base_url = get_permalink(get_queried_object_id());
    if (!$base_url) {
        $genes_page = get_page_by_path('genes');
        $base_url   = $genes_page ? get_permalink($genes_page->ID) : home_url('/genes/');
    }

    // Preserve current filters/search in pagination links
    $qs_params = $_GET;
    unset($qs_params['gd_paged']);

    $page_url = function (int $n) use ($base_url, $qs_params) {
        $qs2 = $qs_params;
        $qs2['gd_paged'] = $n;
        return esc_url(add_query_arg($qs2, $base_url) . '#cmt-genetics-database');
    };

    $items = [];

    if ($current > 1) {
        $items[] = '<li><a class="prev page-numbers" href="' . $page_url($current - 1) . '">« Prev</a></li>';
    } else {
        $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
    }

    $end   = $total_pages;
    $start = max(1, $current - 2);
    $stop  = min($end, $current + 2);

    if ($start > 1) {
        $items[] = '<li><a class="page-numbers" href="' . $page_url(1) . '">1</a></li>';
        if ($start > 2) $items[] = '<li><span class="page-numbers dots">…</span></li>';
    }

    for ($i = $start; $i <= $stop; $i++) {
        if ($i === $current) {
            $items[] = '<li><span class="page-numbers current">' . $i . '</span></li>';
        } else {
            $items[] = '<li><a class="page-numbers" href="' . $page_url($i) . '">' . $i . '</a></li>';
        }
    }

    if ($stop < $end) {
        if ($stop < $end - 1) $items[] = '<li><span class="page-numbers dots">…</span></li>';
        $items[] = '<li><a class="page-numbers" href="' . $page_url($end) . '">' . $end . '</a></li>';
    }

    if ($current < $total_pages) {
        $items[] = '<li><a class="next page-numbers" href="' . $page_url($current + 1) . '">Next »</a></li>';
    } else {
        $items[] = '<li><span class="next page-numbers">Next »</span></li>';
    }

    echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' . implode('', $items) . '</ul></nav>';
}

echo '</div>'; // closes .wp-block-query.dr-blog

return ob_get_clean();
});
