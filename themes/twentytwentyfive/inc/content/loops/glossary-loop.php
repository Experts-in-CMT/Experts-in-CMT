<?php
/**
 * Glossary Loop (Shortcode)
 * Genes-loop parity: sort-only toolbar, no search, genes-style DOM/classes.
 *
 * Shortcode: [glossary_loop]
 *
 * @package ExpertsInCMT
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("glossary_loop", function ($atts = []) {
    // --------------------------------
    // Query params
    // --------------------------------
    $alpha = isset($_GET["alpha"])
        ? strtoupper(trim((string) wp_unslash($_GET["alpha"])))
        : "";
    $paged = isset($_GET["g_paged"])
        ? max(1, (int) $_GET["g_paged"])
        : max(1, (int) get_query_var("paged"));

    // Capture search text so it’s defined everywhere below
    $qs = isset($_GET["qs"]) ? trim((string) wp_unslash($_GET["qs"])) : "";

    // Base page URL (canonical; no query)
    $page_url = get_permalink();
    if (!$page_url) {
        $page_url = home_url(add_query_arg([], $GLOBALS["wp"]->request));
    }

    $with_results_anchor = function (string $url): string {
        return strpos($url, "#results") === false ? $url . "#results" : $url;
    };

    // --------------------------------
    // Alpha map (hidden taxonomy: glossary_letter)
    // --------------------------------
    $alpha_map = [
        "AE" => range("A", "E"),
        "FJ" => range("F", "J"),
        "KO" => range("K", "O"),
        "PT" => range("P", "T"),
        "UZ" => range("U", "Z"),
        "09" => ["0-9"],
    ];
    $alpha_terms =
        $alpha && isset($alpha_map[$alpha]) ? $alpha_map[$alpha] : [];

    // --------------------------------
    // Build WP_Query
    // --------------------------------
    $args = [
        "post_type" => "glossary", // CPT lock
        "post_status" => "publish",
        "orderby" => "title",
        "order" => "ASC",
        "posts_per_page" => 12,
        "paged" => $paged,
    ];

    // Title OR (canonical_term OR synonyms) when qs present
    $remove_filters = null;
    if ($qs !== "") {
        global $wpdb;
        $like = "%" . $wpdb->esc_like($qs) . "%";

        // LEFT JOIN postmeta (alias: gmeta) so title-only matches still work
        $join_cb = function ($join) use ($wpdb) {
            if (strpos($join, "JOIN {$wpdb->postmeta} AS gmeta") === false) {
                $join .= " LEFT JOIN {$wpdb->postmeta} AS gmeta ON ({$wpdb->posts}.ID = gmeta.post_id) ";
            }
            return $join;
        };
        add_filter("posts_join", $join_cb, 10, 1);

        // WHERE: title LIKE OR (meta_key in [canonical_term, synonyms] AND meta_value LIKE)
        $where_cb = function ($where) use ($wpdb, $like) {
            $where .= $wpdb->prepare(
                " AND (
                {$wpdb->posts}.post_title LIKE %s
                OR (gmeta.meta_key IN ('canonical_term','synonyms') AND gmeta.meta_value LIKE %s)
            )",
                $like,
                $like
            );
            return $where;
        };
        add_filter("posts_where", $where_cb, 10, 1);

        // Avoid duplicates if both meta rows match
        $distinct_cb = function ($distinct) {
            return "DISTINCT";
        };
        add_filter("posts_distinct", $distinct_cb, 10, 1);

        // One-stop cleanup
        $remove_filters = function () use ($join_cb, $where_cb, $distinct_cb) {
            remove_filter("posts_join", $join_cb, 10);
            remove_filter("posts_where", $where_cb, 10);
            remove_filter("posts_distinct", $distinct_cb, 10);
        };
    }
    if (!empty($alpha_terms)) {
        $args["tax_query"] = [
            [
                "taxonomy" => "glossary_letter",
                "field" => "name",
                "terms" => $alpha_terms,
                "operator" => "IN",
            ],
        ];
    }

    // Run query and clean up our temporary filters
    $q = new WP_Query($args);
    if ($remove_filters) {
        $remove_filters();
    }

    // Pagination base that preserves filters
    $page_base = add_query_arg(
        array_filter([
            "alpha" => $alpha !== "" ? $alpha : null,
            "qs" => $qs !== "" ? $qs : null,
        ]),
        $page_url
    );

    // --------------------------------
    // Helpers
    // --------------------------------
    $get_fallback_img = function (int $post_id): string {
        $img_id = (int) get_field("term_image", $post_id);
        if ($img_id) {
            $alt_override = trim(
                (string) get_field("alt_text_override", $post_id)
            );
            $alt =
                $alt_override !== ""
                    ? $alt_override
                    : trim(
                        (string) get_post_meta(
                            $img_id,
                            "_wp_attachment_image_alt",
                            true
                        )
                    );
            $html = wp_get_attachment_image($img_id, "large", false, [
                "alt" => esc_attr($alt ?: get_the_title($post_id)),
                "class" => "dr-card__media",
                "loading" => "lazy",
                "decoding" => "async",
            ]);
            if ($html) {
                return $html;
            }
        }
        return '<div class="dr-card__media dr-card__media--empty" aria-hidden="true"></div>';
    };

    ob_start();
    ?>

    <!-- Toolbar (genes-style) -->
    <div class="genes-sort genes-sort--results">
      <form class="genes-sort__form" method="get" action="<?php echo esc_url(
          $page_url
      ); ?>">
        <span class="genes-sort__label">Sort by</span>
        <select name="alpha" class="genes-sort__select" aria-label="Filter by starting letter range">
          <option value="">All (A–Z)</option>
          <option value="AE" <?php selected($alpha, "AE"); ?>>A–E</option>
          <option value="FJ" <?php selected($alpha, "FJ"); ?>>F–J</option>
          <option value="KO" <?php selected($alpha, "KO"); ?>>K–O</option>
          <option value="PT" <?php selected($alpha, "PT"); ?>>P–T</option>
          <option value="UZ" <?php selected($alpha, "UZ"); ?>>U–Z</option>
          <option value="09" <?php selected($alpha, "09"); ?>>0–9</option>
        </select>

        <a class="genes-sort__clear"
           href="<?php echo esc_url(
               $with_results_anchor(
                   remove_query_arg(["alpha", "g_paged"], $page_url)
               )
           ); ?>">
          CLEAR
        </a>
      </form>

<script>
document.addEventListener('DOMContentLoaded', function () {

if (window.GL_AJAX) return;
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

    <!-- Results container (genes-style wrapper) -->
<div id="gl-results-root" aria-live="polite">
   <div id="results" class="wp-block-query dr-blog dr--glossary" style="scroll-margin-top:100px;">

      <?php if ($q->have_posts()): ?>

        <?php
        // Build cards (genes/subtype card skeleton)
        $cards = [];
        while ($q->have_posts()) {

            $q->the_post();
            $pid = get_the_ID();

            ob_start();
            ?>
            <article class="dr-card wp-block-post">
              <?php if (has_post_thumbnail()): ?>
                <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                  <?php the_post_thumbnail("large", [
                      "loading" => "lazy",
                      "decoding" => "async",
                  ]); ?>
                </a>
              <?php else: ?>
                <a class="wp-block-post-featured-image is-placeholder" href="<?php the_permalink(); ?>">
                  <?php echo $get_fallback_img($pid); ?>
                </a>
              <?php endif; ?>

              <h2 class="wp-block-post-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h2>

              <div class="wp-block-post-excerpt">
                <p>
                  <?php
                  $short = trim((string) get_field("short_definition", $pid));
                  if ($short !== "") {
                      echo esc_html($short);
                  } else {
                      echo esc_html(
                          wp_trim_words(
                              wp_strip_all_tags(
                                  get_post_field("post_content", $pid)
                              ),
                              28,
                              " …"
                          )
                      );
                  }
                  ?>
                </p>

                <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Definition</a>

                <div class="wp-block-post-date" style="text-align:center;margin-top:12px;">
                  <small>Update: <?php echo esc_html(
                      get_the_modified_date(get_option("date_format"))
                  ); ?></small>
                </div>

                <div class="wp-block-spacer" style="height:20px;" aria-hidden="true"></div>
              </div>
            </article>
            <?php $cards[] = ob_get_clean();
        }
        wp_reset_postdata();

        // Chunk into rows of 3 (genes grid pattern)
        $rows = array_chunk($cards, 3);
        $total_rows = count($rows);
        ?>

        <div class="dr-grid">
          <?php foreach ($rows as $i => $items): ?>
            <?php
            $is_last = $i === $total_rows - 1;
            $count = count($items);
            ?>
            <div class="dr-row<?php echo $is_last
                ? " dr-row--last"
                : ""; ?>" <?php echo $is_last
    ? 'data-count="' . (int) $count . '"'
    : ""; ?>>
              <?php echo implode("", $items); ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php
        // Pagination (genes-style wrapper/classes)
        $total_pages = max(1, (int) $q->max_num_pages);
        if ($total_pages > 1) {
            $current = max(1, (int) $paged);

            // Preserve alpha; set g_paged; always include #results
            $page_url_fn = function (int $n) use (
                $page_base,
                $with_results_anchor
            ): string {
                $qs = ["g_paged" => $n];
                return $with_results_anchor(
                    esc_url(add_query_arg($qs, $page_base))
                );
            };

            $items = [];
            if ($current > 1) {
                $items[] =
                    '<li><a class="prev page-numbers" href="' .
                    $page_url_fn($current - 1) .
                    '">« Prev</a></li>';
            } else {
                $items[] =
                    '<li><span class="prev page-numbers">« Prev</span></li>';
            }

            $end = $total_pages;
            $start = max(1, $current - 2);
            $stop = min($end, $current + 2);

            if ($start > 1) {
                $items[] =
                    '<li><a class="page-numbers" href="' .
                    $page_url_fn(1) .
                    '">1</a></li>';
                if ($start > 2) {
                    $items[] =
                        '<li><span class="page-numbers dots">…</span></li>';
                }
            }
            for ($i = $start; $i <= $stop; $i++) {
                if ($i === $current) {
                    $items[] =
                        '<li><span class="page-numbers current">' .
                        $i .
                        "</span></li>";
                } else {
                    $items[] =
                        '<li><a class="page-numbers" href="' .
                        $page_url_fn($i) .
                        '">' .
                        $i .
                        "</a></li>";
                }
            }
            if ($stop < $end) {
                if ($stop < $end - 1) {
                    $items[] =
                        '<li><span class="page-numbers dots">…</span></li>';
                }
                $items[] =
                    '<li><a class="page-numbers" href="' .
                    $page_url_fn($end) .
                    '">' .
                    $end .
                    "</a></li>";
            }
            if ($current < $total_pages) {
                $items[] =
                    '<li><a class="next page-numbers" href="' .
                    $page_url_fn($current + 1) .
                    '">Next »</a></li>';
            } else {
                $items[] =
                    '<li><span class="next page-numbers">Next »</span></li>';
            }

            echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' .
                implode("", $items) .
                "</ul></nav>";
        }
        ?>

      <?php else: ?>
        <div id="genes-no-results" class="dr-row dr-row--empty"
             style="margin:0 auto 64px auto;display:flex;justify-content:center;align-items:flex-start;max-width:700px;width:100%;">
          <p style="font-size:1.1rem;color:#333;text-align:left;">
            No Matching Glossary Entries Were Found<br>
          </p>
        </div>
      <?php endif; ?>

    </div><!-- /#results -->
</div>

    <script>
    // Genes-style behavior: auto-submit on alpha change and keep #results
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.querySelector('.genes-sort__form');
      if (!form) return;

      // Auto-submit when alpha changes
      const alpha = form.querySelector('select[name="alpha"]');
      if (alpha) {
        alpha.addEventListener('change', function () {
          const params = new URLSearchParams(new FormData(form));
          params.delete('g_paged'); // reset pagination
          const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + '#results';
          window.location.assign(newUrl);
        });
      }

      // CLEAR button — strip alpha & g_paged, jump to #results
      const clearBtn = form.querySelector('.genes-sort__clear');
      if (clearBtn) {
        clearBtn.addEventListener('click', function (e) {
          e.preventDefault();
          const base = window.location.pathname + '#results';
          window.location.assign(base);
        });
      }
    });
    </script>

    <?php return ob_get_clean();
});