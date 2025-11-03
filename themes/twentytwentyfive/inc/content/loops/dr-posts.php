<?php
/**
 * Dorsal Root — Posts Loop (Shortcode)
 * Genes-parity layout & UX (no search)
 * - Sort via dr_sort: "", title_az, title_za, oldest, newest
 * - Pagination via dr_paged, anchors to #results
 * - Card/wrapper markup IDENTICAL to Genes loop
 *
 * Shortcode: [dr_posts]
 * Optional attrs:
 *   per_page (int) default 12
 *   category_name (string) optional scope (e.g., "dorsal-root")
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('dr_posts', function ($atts = []) {
    $a = shortcode_atts([
        'per_page'      => 12,
        'category_name' => '',
    ], $atts, 'dr_posts');

    /* --------------------------------------------------------
       INPUTS (GET params)
       -------------------------------------------------------- */
    $paged = max(1, (int)($_GET['dr_paged'] ?? 1));
    $sort  = isset($_GET['dr_sort']) ? sanitize_key($_GET['dr_sort']) : '';

    /* --------------------------------------------------------
       BASE QUERY ARGS (DR posts)
       -------------------------------------------------------- */
    $args = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => max(1, (int)$a['per_page']),
        'paged'               => $paged,
        'ignore_sticky_posts' => true,
        // default ordering (will be overridden if dr_sort present)
        'orderby'             => 'date',
        'order'               => 'DESC',
    ];

    if (!empty($a['category_name'])) {
        $args['category_name'] = sanitize_title($a['category_name']);
    }

    /* --------------------------------------------------------
       SORT: honor explicit dr_sort
       -------------------------------------------------------- */
    switch ($sort) {
        case 'title_az':
            $args['orderby'] = ['title' => 'ASC'];
            $args['order']   = 'ASC';
            break;

        case 'title_za':
            $args['orderby'] = ['title' => 'DESC'];
            $args['order']   = 'DESC';
            break;

        case 'oldest':
            $args['orderby'] = ['date' => 'ASC'];
            $args['order']   = 'ASC';
            break;

        case 'newest':
            $args['orderby'] = ['date' => 'DESC'];
            $args['order']   = 'DESC';
            break;

        default:
            // keep defaults: date DESC
            break;
    }

    /* --------------------------------------------------------
       RUN QUERY
       -------------------------------------------------------- */
    $q = new WP_Query($args);

    /* ========================================================
       ================= [ OUTPUT MARKUP ] =====================
       ======================================================== */

    ob_start();
    ?>
    <div id="results" class="wp-block-query dr-blog" style="scroll-margin-top:100px;">

    <?php

    /* ======================================================================
       RESULTS-LEVEL SORT TOOLBAR (Genes parity; param names swapped)
       ====================================================================== */
    $anchor       = 'results';
    $base         = strtok($_SERVER['REQUEST_URI'], '?'); // current path without query
    $action_url   = esc_url($base . '#' . $anchor);
    $current_sort = $sort;

    // Keep current params; always reset pagination when sorting
    $keep = $_GET;
    unset($keep['dr_paged']);

    // CLEAR = drop sort & pagination, keep other params
    $clear_params = $keep;
    unset($clear_params['dr_sort']);
    $sort_clear_url = esc_url($base . ($clear_params ? '?' . http_build_query($clear_params) : '')) . '#' . $anchor;
    ?>
    
  
    <div class="genes-sort genes-sort--results">
      <form class="genes-sort__form" method="get" action="<?php echo $action_url; ?>">
        <span class="genes-sort__label">Sort by</span>
        <select id="dr_sort" name="dr_sort" class="genes-sort__select" onchange="this.form.submit()">
          <option value=""           <?php selected($current_sort, ''); ?>>Default</option>
          <option value="title_az"   <?php selected($current_sort, 'title_az'); ?>>Title A–Z</option>
          <option value="title_za"   <?php selected($current_sort, 'title_za'); ?>>Title Z–A</option>
          <option value="oldest"     <?php selected($current_sort, 'oldest'); ?>>Oldest to Newest</option>
          <option value="newest"     <?php selected($current_sort, 'newest'); ?>>Newest to Oldest</option>
        </select>

        <a class="genes-sort__clear" href="<?php echo $sort_clear_url; ?>">CLEAR</a>

        <?php // Preserve other GET params (filters, etc.), drop sort/paged
        foreach ($keep as $k => $v) {
            if (in_array($k, ['dr_sort', 'dr_paged'], true)) continue;
            if (is_scalar($v)) {
                printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr($v));
            }
        } ?>
        <noscript><button type="submit" class="genes-sort__btn">Apply</button></noscript>
      </form>

      <script>
      document.addEventListener('DOMContentLoaded', function() {
        const sortForm = document.querySelector('.genes-sort__form');
        const clearBtn = document.querySelector('.genes-sort__clear');
        if (!sortForm) return;

        // Reset pagination, keep others, jump to #results
        sortForm.addEventListener('change', function(e) {
          if (e.target.name !== 'dr_sort') return;
          e.preventDefault();
          const params = new URLSearchParams(window.location.search);
          params.delete('dr_paged');
          const val = e.target.value;
          if (val) { params.set('dr_sort', val); } else { params.delete('dr_sort'); }
          const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + '#results';
          window.history.replaceState(null, '', newUrl);
          window.location.reload();
        });

        if (clearBtn) {
          clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const params = new URLSearchParams(window.location.search);
            params.delete('dr_sort');
            params.delete('dr_paged');
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + '#results';
            window.history.replaceState(null, '', newUrl);
            window.location.reload();
          });
        }
      });
      </script>
    </div>

    <?php

    // Build cards
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

    $rows       = array_chunk($cards, 3);
    $total_rows = count($rows);
    ?>

    <?php if (empty($rows)): ?>
        <div id="genes-no-results" class="dr-row dr-row--empty"
             style="margin:0 auto 64px;display:flex;justify-content:center;align-items:flex-start;max-width:700px;width:100%;">
            <p style="font-size:1.1rem; color:#333; text-align:left;">
                No posts found.
            </p>
        </div>
    <?php endif; ?>

    <div class="dr-grid">
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $i => $row_items):
                $is_last = $i === $total_rows - 1;
                $count   = count($row_items); ?>
                <div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="'.(int)$count.'"' : ''; ?>>
                    <?php echo implode('', $row_items); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    /* ====================================================
       =============== [ PAGINATION ] ======================
       ==================================================== */
    $total_pages = max(1, (int)$q->max_num_pages);
    if ($total_pages > 1) {
        $current   = max(1, (int)($_GET['dr_paged'] ?? 1));
        $base_url  = get_permalink(get_queried_object_id()) ?: home_url('/dorsal-root/');
        $qs_params = $_GET;
        unset($qs_params['dr_paged']);

        $page_url = function (int $n) use ($base_url, $qs_params) {
            $qs2 = $qs_params;
            $qs2['dr_paged'] = $n;
            return esc_url(add_query_arg($qs2, $base_url) . '#results');
        };

        $items = [];
        if ($current > 1) {
            $items[] = '<li><a class="prev page-numbers" href="'.$page_url($current - 1).'">« Prev</a></li>';
        } else {
            $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
        }

        $end   = $total_pages;
        $start = max(1, $current - 2);
        $stop  = min($end, $current + 2);

        if ($start > 1) {
            $items[] = '<li><a class="page-numbers" href="'.$page_url(1).'">1</a></li>';
            if ($start > 2) $items[] = '<li><span class="page-numbers dots">…</span></li>';
        }
        for ($i = $start; $i <= $stop; $i++) {
            if ($i === $current) {
                $items[] = '<li><span class="page-numbers current">'.$i.'</span></li>';
            } else {
                $items[] = '<li><a class="page-numbers" href="'.$page_url($i).'">'.$i.'</a></li>';
            }
        }
        if ($stop < $end) {
            if ($stop < $end - 1) $items[] = '<li><span class="page-numbers dots">…</span></li>';
            $items[] = '<li><a class="page-numbers" href="'.$page_url($end).'">'.$end.'</a></li>';
        }
        if ($current < $total_pages) {
            $items[] = '<li><a class="next page-numbers" href="'.$page_url($current + 1).'">Next »</a></li>';
        } else {
            $items[] = '<li><span class="next page-numbers">Next »</span></li>';
        }

        echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">'.implode('', $items).'</ul></nav>';
    }
    ?>
    </div>
    <?php return ob_get_clean();
});
