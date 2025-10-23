<?php
/* ============================================================
   GENES DATABASE LOOP SHORTCODE (MVP PORT)
   — Minimal change from Dorsal Root loop
   — Keeps legacy CSS handles for styling
   ============================================================ */

/**
 * [genes_loop] — rows of 3 with centered last row; preserves CSS classes
 * Usage: [genes_loop per_page="12"]
 */

// Named sorter so we can remove it after the query
function eic_genes_custom_sort_clauses($clauses, $wp_query) {
    if (!$wp_query->get('eic_genes_custom_sort')) return $clauses;

    global $wpdb;
    // Your exact order
    $custom_type_order = [
        'CMT1','CMT2','CMT4','CMTX','CMTDI','CMTRI',
        'dHMN','dSMA','GAN','HMSN','HSAN','HSN','SMA-LEP','Unclassified'
    ];

    // Ensure LEFT JOIN to postmeta for type_classification (alias mt1)
    $join = " LEFT JOIN {$wpdb->postmeta} mt1
              ON (mt1.post_id = {$wpdb->posts}.ID AND mt1.meta_key = 'type_classification')";
    if (strpos($clauses['join'], ' mt1 ') === false) {
        $clauses['join'] .= $join;
    }

    // Build FIELD() list for explicit ordering
    $quoted = array_map(function ($v) use ($wpdb) {
        return trim($wpdb->prepare('%s', $v), "'");
    }, $custom_type_order);
    $field_list = "'" . implode("','", $quoted) . "'";

    // Missing/empty LAST, then custom order, then title ASC
    $order_sql =
        "CASE WHEN mt1.meta_value IS NULL OR mt1.meta_value = '' THEN 1 ELSE 0 END ASC, " .
        "FIELD(mt1.meta_value, {$field_list}) ASC, " .
        "{$wpdb->posts}.post_title ASC";

    $clauses['orderby'] = $order_sql;

    return $clauses;
}

add_shortcode('genes_loop', function ($atts = []) {
    $a = shortcode_atts([
        'per_page'    => 12,
        'page_window' => 2,
        'edge_count'  => 1,
    ], $atts);

    // Use a dedicated query param to avoid conflicts on the Genes page
    // Example: /genes/?gd_paged=2#genes
    $paged = isset($_GET['gd_paged']) ? max(1, (int) $_GET['gd_paged']) : 1;

    // --- Query args (note: NO meta_key to avoid filtering out posts) ---
 $args = [
    'post_type'      => 'subtype',          // CPT key confirmed
    'post_status'    => 'publish',
    'posts_per_page' => max(1, (int) $a['per_page']),
    'paged'          => $paged,
    'no_found_rows'  => false,

    // baseline fallback order — the custom sorter will override this
    'orderby'        => 'title',
    'order'          => 'ASC',

    // enable custom FIELD() order for type_classification
    'eic_genes_custom_sort' => 1,
];


    // ----------------------------
    // Text search (title/content/excerpt) via ?gd_q=
    // ----------------------------
    $gd_search_term = '';
    if (!empty($_GET['gd_q'])) {
        $gd_search_term = trim( sanitize_text_field( wp_unslash($_GET['gd_q']) ) );
        if ($gd_search_term !== '') {
            $args['s'] = $gd_search_term;
            $args['eic_gd_search'] = 1; // flag so local filters know to run
        }
    }

    // ----------------------------
    // Taxonomy filters
    // ?gd_type=slug (taxonomy cmt-type)
    // ?gd_inherit=slug (taxonomy inheritance)
    // ----------------------------
    $tax_query = [];

    if (!empty($_GET['gd_type'])) {
        $tax_query[] = [
            'taxonomy' => 'cmt-type',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( wp_unslash($_GET['gd_type']) ),
        ];
    }

    if (!empty($_GET['gd_inherit'])) {
        $tax_query[] = [
            'taxonomy' => 'inheritance',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( wp_unslash($_GET['gd_inherit']) ),
        ];
    }

    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    // ----------------------------
    // Extend gd_q to match ANY taxonomy term name attached to `subtype`
    // ----------------------------
    global $wpdb;

    if ($gd_search_term !== '') {
        $eic_join_cb = function ($join) use ($wpdb) {
            return $join
                . " LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = {$wpdb->posts}.ID"
                . " LEFT JOIN {$wpdb->term_taxonomy}   tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
                . " LEFT JOIN {$wpdb->terms}            t ON t.term_id = tt.term_id";
        };

        $eic_search_cb = function ($search, $wp_query) use ($wpdb, $gd_search_term) {
            if (!$wp_query->get('eic_gd_search')) return $search;
            $like = '%' . $wpdb->esc_like($gd_search_term) . '%';

            if ($search && substr(trim($search), -1) === ')') {
                $search = preg_replace(
                    '/\)\s*$/',
                    $wpdb->prepare(" OR (t.name LIKE %s))", $like),
                    $search,
                    1
                );
            } else {
                $search .= $wpdb->prepare(" AND (t.name LIKE %s)", $like);
            }
            return $search;
        };

        $eic_distinct_cb = function ($distinct) { return 'DISTINCT'; };

        add_filter('posts_join',     $eic_join_cb);
        add_filter('posts_search',   $eic_search_cb, 10, 2);
        add_filter('posts_distinct', $eic_distinct_cb);
    }

    // Attach our custom sorter (LEFT JOIN + ORDER BY)
    add_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10, 2);

    // Query
    $q = new WP_Query($args);

    // Clean up temporary filters
    remove_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10);
    if ($gd_search_term !== '') {
        remove_filter('posts_join',     $eic_join_cb);
        remove_filter('posts_search',   $eic_search_cb, 10);
        remove_filter('posts_distinct', $eic_distinct_cb);
    }

    // ---------------------------------------------------
    // Build cards (preserve classes: dr-card + wp-block-post)
    // Card content: Subtype (title), Gene, Discovered, Inheritance, Learn More, Update
    // ---------------------------------------------------
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
            $inherit_acf    = function_exists('get_field') ? (get_field('inheritance_pattern', get_the_ID()) ?: '') : '';

            // Taxonomy fallback for inheritance label
            $inherit_terms = wp_get_post_terms(get_the_ID(), 'inheritance', ['fields' => 'names']);
            $inherit_label = $inherit_acf ?: (!empty($inherit_terms) ? implode(', ', $inherit_terms) : '');

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

    ob_start(); ?>
    <div class="wp-block-query dr-blog">
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
    $current  = max(1, (int) $paged);

    // Always build links off the page hosting the shortcode
    $base_url = get_permalink(get_queried_object_id());
    if (!$base_url) {
        $genes_page = get_page_by_path('genes');
        $base_url   = $genes_page ? get_permalink($genes_page->ID) : home_url('/genes/');
    }

    $qs = $_GET;
    unset($qs['gd_paged']);

    $page_url = function (int $n) use ($base_url, $qs) {
        $qs2 = $qs;
        $qs2['gd_paged'] = $n;
        return esc_url(add_query_arg($qs2, $base_url) . '#genes');
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
