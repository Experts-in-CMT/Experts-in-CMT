<?php
/* ============================================================
   DORSAL ROOT POSTS SHORTCODE
   ============================================================ */

/**
 * [dr_posts] — rows of 3 with centered last row; keeps legacy CSS handles for styling
 * Usage: [dr_posts per_page="12"]
 */
add_shortcode('dr_posts', function ($atts = []) {
    $a = shortcode_atts([
    'per_page'    => 12,
    'page_window' => 2,  // current ± window (keeps today’s behavior)
    'edge_count'  => 1,  // always show first/last N pages
], $atts);


    // Use custom query param to avoid static-page pagination conflicts
    // Example: /dorsal-root/?dr_paged=2#blog
    $paged = isset($_GET['dr_paged']) ? max(1, (int) $_GET['dr_paged']) : 1;

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, (int) $a['per_page']),
        'paged'          => $paged,
        'no_found_rows'  => false,
    ];

// Apply text search (title, content, excerpt) from the filter's dr_q input
if (isset($_GET['dr_q'])) {
    $dr_q = trim( sanitize_text_field( wp_unslash($_GET['dr_q']) ) );
    if ($dr_q !== '') {
        $args['s'] = $dr_q;
    }
}

    // Optional taxonomy filter (?dr_cat=slug)
    $tax = 'dorsal-root';
    if (!empty($_GET['dr_cat'])) {
        $slug = sanitize_text_field(wp_unslash($_GET['dr_cat']));
        $args['tax_query'] = [[
            'taxonomy' => $tax,
            'field'    => 'slug',
            'terms'    => $slug,
        ]];
    }
// --- Extend dr_q to also match ANY taxonomy term name attached to posts ---
$dr_search_term = '';
if (!empty($_GET['dr_q'])) {
    $dr_search_term = sanitize_text_field( wp_unslash($_GET['dr_q']) );
    if ($dr_search_term !== '') {
        $args['s'] = $dr_search_term;   // keep core title/content/excerpt search
        $args['eic_dr_search'] = 1;     // flag so our local filters know to run
    }
}

global $wpdb;
if ($dr_search_term !== '') {
    // JOIN terms so we can match term names (all taxonomies attached to posts)
    $eic_join_cb = function ($join) use ($wpdb) {
        return $join
            . " LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = {$wpdb->posts}.ID"
            . " LEFT JOIN {$wpdb->term_taxonomy}   tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
            . " LEFT JOIN {$wpdb->terms}            t ON t.term_id = tt.term_id";
    };

    // Add OR (t.name LIKE '%term%') inside the existing search parentheses
    $eic_search_cb = function ($search, $wp_query) use ($wpdb, $dr_search_term) {
        if (!$wp_query->get('eic_dr_search')) return $search;
        $like = '%' . $wpdb->esc_like($dr_search_term) . '%';

        // If core built "(...)" parentheses, append OR inside them
        if ($search && substr(trim($search), -1) === ')') {
            $search = preg_replace(
                '/\)\s*$/',
                $wpdb->prepare(" OR (t.name LIKE %s))", $like),
                $search,
                1
            );
        } else {
            // Fallback: ensure we still broaden search
            $search .= $wpdb->prepare(" AND (t.name LIKE %s)", $like);
        }
        return $search;
    };

    // Avoid duplicate rows due to JOINs
    $eic_distinct_cb = function ($distinct) { return 'DISTINCT'; };

    add_filter('posts_join',     $eic_join_cb);
    add_filter('posts_search',   $eic_search_cb, 10, 2);
    add_filter('posts_distinct', $eic_distinct_cb);
}

// >>> keep this line exactly where it was <<<
$q = new WP_Query($args);

// Always clean up so no other queries are affected
if ($dr_search_term !== '') {
    remove_filter('posts_join',     $eic_join_cb);
    remove_filter('posts_search',   $eic_search_cb, 10);
    remove_filter('posts_distinct', $eic_distinct_cb);
}


    $q = new WP_Query($args);

    // Build cards (NOTE: both classes: dr-card + wp-block-post so existing CSS applies)
    $cards = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) { 
            $q->the_post();
            ob_start(); ?>
            <article class="dr-card wp-block-post">
                <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()) {
                        the_post_thumbnail('large', ['loading' => 'lazy', 'decoding' => 'async']);
                    } ?>
                </a>

                <h2 class="wp-block-post-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>

                <div class="wp-block-post-date"><?php echo esc_html(get_the_date()); ?></div>

                <div class="wp-block-post-excerpt">
                    <?php echo esc_html(wp_strip_all_tags(get_the_excerpt(), true)); ?>
                </div>

                <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Read More</a>
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
          <div class="dr-row dr-row--empty"><p>No posts found.</p></div>
        <?php endif; ?>
      </div>
<?php
// ----- Shortcode-safe, query-string pagination using dr_paged -----
$total_pages = max(1, (int) $q->max_num_pages);
if ($total_pages > 1) {
    $current  = max(1, (int) $paged); // from above

    // Always build links off the page hosting the shortcode
    $base_url = get_permalink(get_queried_object_id());
    if (!$base_url) {
        $dr_page  = get_page_by_path('dorsal-root');
        $base_url = $dr_page ? get_permalink($dr_page->ID) : home_url('/');
    }

    // Preserve current filters/search/etc., but we'll set dr_paged per link
    $qs = $_GET;
    unset($qs['dr_paged']);

    // Helper to build a page URL like ?dr_paged=2&dr_cat=...#blog
    $page_url = function (int $n) use ($base_url, $qs) {
        $qs2 = $qs;
        $qs2['dr_paged'] = $n;
        return esc_url(add_query_arg($qs2, $base_url) . '#blog');
    };

    // Build list items (same CSS classes WP uses)
    $items = [];

    // Prev
    if ($current > 1) {
        $items[] = '<li><a class="prev page-numbers" href="' . $page_url($current - 1) . '">« Prev</a></li>';
    } else {
        $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
    }

    // Compact number range
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

    // Next
    if ($current < $total_pages) {
        $items[] = '<li><a class="next page-numbers" href="' . $page_url($current + 1) . '">Next »</a></li>';
    } else {
        $items[] = '<li><span class="next page-numbers">Next »</span></li>';
    }

    echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' . implode('', $items) . '</ul></nav>';
}

// Close the outer container printed above
echo '</div>'; // closes .wp-block-query.dr-blog

return ob_get_clean();
});
