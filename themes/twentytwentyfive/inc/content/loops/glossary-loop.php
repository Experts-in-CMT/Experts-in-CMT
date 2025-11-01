<?php
/**
 * Glossary Loop (Shortcode)
 * Renders a paginated grid of Glossary terms with title-only search,
 * alpha-range filtering (A–E, F–J, K–O, P–T, U–Z, 0–9), and uniform cards.
 *
 * Shortcode: [glossary_loop]
 *
 * @package ExpertsInCMT
 */

if (!defined('ABSPATH')) {
    exit();
}

add_shortcode('glossary_loop', function ($atts = []) {
    // ----------------------------
    // Resolve URL + query params
    // ----------------------------
    $qs    = isset($_GET['qs']) ? trim((string) wp_unslash($_GET['qs'])) : '';
    $alpha = isset($_GET['alpha']) ? strtoupper(trim((string) wp_unslash($_GET['alpha']))) : '';
    $paged = isset($_GET['g_paged']) ? max(1, (int) $_GET['g_paged']) : max(1, get_query_var('paged'));

    // Base page URL (no query), for reset links
    $page_url = get_permalink();
    if (!$page_url) {
        $page_url = home_url(add_query_arg([], $GLOBALS['wp']->request));
    }

    // Append #results anchor helper
    $with_anchor = function ($url) {
        if (strpos($url, '#results') === false) {
            $url .= '#results';
        }
        return $url;
    };

    // ----------------------------
    // Map alpha ranges to terms
    // ----------------------------
    $alpha_map = [
        'AE' => range('A', 'E'),
        'FJ' => range('F', 'J'),
        'KO' => range('K', 'O'),
        'PT' => range('P', 'T'),
        'UZ' => range('U', 'Z'),
        '09' => ['0-9'],
    ];
    $alpha_terms = [];
    if ($alpha && isset($alpha_map[$alpha])) {
        $alpha_terms = $alpha_map[$alpha];
    }

    // ----------------------------
    // Build query
    // ----------------------------
    $args = [
        'post_type'      => 'glossary',
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'posts_per_page' => 12,
        'paged'          => $paged,
    ];

    // Title-only search for THIS query
    $remove_filter = null;
    if ($qs !== '') {
        $args['s'] = $qs;

        $title_only_cb = function ($search, \WP_Query $q) {
            global $wpdb;
            if ($q->get('post_type') === 'glossary' && $q->get('s') !== '') {
                $like   = '%' . $wpdb->esc_like($q->get('s')) . '%';
                $search = $wpdb->prepare(" AND {$wpdb->posts}.post_title LIKE %s ", $like);
            }
            return $search;
        };
        add_filter('posts_search', $title_only_cb, 10, 2);

        // Ensure we remove it after our custom query
        $remove_filter = function () use ($title_only_cb) {
            remove_filter('posts_search', $title_only_cb, 10);
        };

        // NOTE: intentionally no meta_query here (reverted to working behavior)
    }

    // Alpha letter tax filter (hidden taxonomy, pre-indexed)
    if (!empty($alpha_terms)) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'glossary_letter',
                'field'    => 'name',
                'terms'    => $alpha_terms,
                'operator' => 'IN',
            ],
        ];
    }

    // ----------------------------
    // Run query
    // ----------------------------
    $q = new WP_Query($args);
    if ($remove_filter) {
        $remove_filter();
    }

    // Build pagination base that preserves filters
    $page_base = add_query_arg(
        array_filter([
            'qs'    => $qs !== '' ? $qs : null,
            'alpha' => $alpha !== '' ? $alpha : null,
        ]),
        $page_url
    );

    // ----------------------------
    // Helpers
    // ----------------------------
    $get_img = function ($post_id) {
        $img_id = (int) get_field('term_image', $post_id);
        if ($img_id) {
            $alt_override = trim((string) get_field('alt_text_override', $post_id));
            $alt = $alt_override !== ''
                ? $alt_override
                : trim((string) get_post_meta($img_id, '_wp_attachment_image_alt', true));
            $html = wp_get_attachment_image($img_id, 'medium', false, [
                'alt'      => esc_attr($alt ?: get_the_title($post_id)),
                'class'    => 'dr-card__media',
                'loading'  => 'lazy',
                'decoding' => 'async',
            ]);
            if ($html) {
                return $html;
            }
        }
        // Fallback empty box keeps card heights uniform
        return '<div class="dr-card__media dr-card__media--empty" aria-hidden="true"></div>';
    };

    // ----------------------------
    // Render (markup aligned to Genes)
    // ----------------------------
    ob_start();
    ?>

  <div id="results" class="wp-block-query dr-blog" style="scroll-margin-top:100px;">

    <!-- Build genes-style action URL that includes #results -->
    <?php
      $anchor     = 'results';
      $base       = strtok($_SERVER['REQUEST_URI'], '?'); // current path without query
      $action_url = esc_url($base . '#' . $anchor);
    ?>

    <!-- Toolbar: mirror Genes container/classes; keep existing behavior/fields -->
    <div class="genes-sort genes-sort--results">
      <form class="genes-sort__form" method="get" action="<?php echo $action_url; ?>">
        <div class="genes-filter__row">
          <input
            type="text"
            name="qs"
            value="<?php echo esc_attr($qs); ?>"
            class="genes-filter__search"
            placeholder="Common Words Search..."
            inputmode="search"
            aria-label="Search glossary by word"
          />

          <select name="alpha" class="genes-filter__select" aria-label="Filter by starting letter range">
            <option value="">All (A–Z)</option>
            <option value="AE" <?php selected($alpha, 'AE'); ?>>A–E</option>
            <option value="FJ" <?php selected($alpha, 'FJ'); ?>>F–J</option>
            <option value="KO" <?php selected($alpha, 'KO'); ?>>K–O</option>
            <option value="PT" <?php selected($alpha, 'PT'); ?>>P–T</option>
            <option value="UZ" <?php selected($alpha, 'UZ'); ?>>U–Z</option>
            <option value="09" <?php selected($alpha, '09'); ?>>0–9</option>
          </select>

          <button type="submit" class="genes-filter__submit">Search</button>
         <a class="genes-filter__reset genes-sort__clear" href="<?php echo esc_url($page_url); ?>">Reset</a>

        </div>
      </form>

  <script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('.genes-sort__form');
  if (!form) return;

  // Submit → reset pagination, push #results, then reload (genes pattern)
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form));
    params.delete('g_paged'); // reset pagination

    const newUrl = window.location.pathname + '?' + params.toString() + '#results';
    window.history.replaceState(null, '', newUrl);
    window.location.reload();
  });

  // Auto-submit on alpha change
  const alpha = form.querySelector('select[name="alpha"]');
  if (alpha) {
    alpha.addEventListener('change', function () {
      form.requestSubmit ? form.requestSubmit() : form.submit();
    });
  }

  // Reset (no jump): clear qs/alpha/g_paged, then #results + reload
  const clearBtn = document.querySelector('.genes-sort__clear');
  if (clearBtn) {
    clearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const params = new URLSearchParams(new FormData(form));
      params.delete('qs');
      params.delete('alpha');
      params.delete('g_paged');

      const query = params.toString();
      const newUrl = window.location.pathname + (query ? '?' + query : '') + '#results';
      window.history.replaceState(null, '', newUrl);
      window.location.reload();
    });
  }
});
</script>

    </div>

    <?php if ($q->have_posts()): ?>

      <?php
      // ============================================================
      // Build cards first (Genes/Subtype structure applied to Glossary)
      // ============================================================
      $cards = [];
      while ($q->have_posts()) {
          $q->the_post();
          $pid = get_the_ID();

          ob_start();
          ?>
          <article class="dr-card wp-block-post">
            <?php if (has_post_thumbnail()): ?>
              <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('large', [
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                ]); ?>
              </a>
            <?php else: ?>
              <!-- Fallback for cards without a featured image -->
              <a class="wp-block-post-featured-image is-placeholder" href="<?php the_permalink(); ?>">
                <?php echo $get_img($pid); ?>
              </a>
            <?php endif; ?>

            <h2 class="wp-block-post-title">
              <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h2>

            <div class="wp-block-post-excerpt">
              <p>
                <?php
                $short = trim((string) get_field('short_definition', $pid));
                if ($short !== '') {
                    echo esc_html($short);
                } else {
                    echo esc_html(
                        wp_trim_words(
                            wp_strip_all_tags(get_post_field('post_content', $pid)),
                            28,
                            ' …'
                        )
                    );
                }
                ?>
              </p>

              <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Definition</a>

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

      // Chunk into rows of 3 (mirror Genes grid structure)
      $rows = array_chunk($cards, 3);
      $total_rows = count($rows);
      ?>

      <div class="dr-grid">
        <?php foreach ($rows as $i => $items): ?>
          <?php
          $is_last = ($i === $total_rows - 1);
          $count   = count($items);
          ?>
          <div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="' . (int) $count . '"' : ''; ?>>
            <?php echo implode('', $items); ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php
      // Pagination (mirror Genes wrapper/classes; keep g_paged + filters + #results)
      $total_pages = max(1, (int) $q->max_num_pages);
      if ($total_pages > 1) {
          $current = max(1, (int) $paged);

          // Preserve qs/alpha; drop g_paged when building each link
          $qs_params = [];
          if ($qs !== '')    $qs_params['qs'] = $qs;
          if ($alpha !== '') $qs_params['alpha'] = $alpha;

          $page_url_fn = function (int $n) use ($page_base, $qs_params) {
              $qs2 = $qs_params;
              $qs2['g_paged'] = $n;
              return esc_url(add_query_arg($qs2, $page_base) . '#results');
          };

          $items = [];
          if ($current > 1) {
              $items[] = '<li><a class="prev page-numbers" href="' . $page_url_fn($current - 1) . '">« Prev</a></li>';
          } else {
              $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
          }

          $end   = $total_pages;
          $start = max(1, $current - 2);
          $stop  = min($end, $current + 2);

          if ($start > 1) {
              $items[] = '<li><a class="page-numbers" href="' . $page_url_fn(1) . '">1</a></li>';
              if ($start > 2) {
                  $items[] = '<li><span class="page-numbers dots">…</span></li>';
              }
          }
          for ($i = $start; $i <= $stop; $i++) {
              if ($i === $current) {
                  $items[] = '<li><span class="page-numbers current">' . $i . '</span></li>';
              } else {
                  $items[] = '<li><a class="page-numbers" href="' . $page_url_fn($i) . '">' . $i . '</a></li>';
              }
          }
          if ($stop < $end) {
              if ($stop < $end - 1) {
                  $items[] = '<li><span class="page-numbers dots">…</span></li>';
              }
              $items[] = '<li><a class="page-numbers" href="' . $page_url_fn($end) . '">' . $end . '</a></li>';
          }
          if ($current < $total_pages) {
              $items[] = '<li><a class="next page-numbers" href="' . $page_url_fn($current + 1) . '">Next »</a></li>';
          } else {
              $items[] = '<li><span class="next page-numbers">Next »</span></li>';
          }

          echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' . implode('', $items) . '</ul></nav>';
      }
      ?>

    <?php else: ?>
      <div id="genes-no-results" class="dr-row dr-row--empty"
        style="margin:-100px auto 64px auto;display:flex;justify-content:center;align-items:flex-start;max-width:700px;width:100%;">
        <p style="font-size:1.1rem;color:#333;text-align:left;">
          No terms found.<br>Try adjusting your filters or letter range.
        </p>
      </div>
    <?php endif; ?>

  </div><!-- /#results.wp-block-query.dr-blog -->

  <?php return ob_get_clean();
});
