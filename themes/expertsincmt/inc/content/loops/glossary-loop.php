<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  GLOSSARY LOOP (Shortcode)
 *  ------------------------------------------------------------
 *  Shortcode: [glossary_loop]
 *
 *  Purpose:
 *    - Renders the full CMT Glossary grid with alpha filters,
 *      sort dropdown, title-only search, and pagination
 *    - Outputs the EXACT #results wrapper consumed by:
 *          • glossary-ajax.js
 *          • glossary-loop-endpoints.php
 *    - Provides DR-parity structure (card grid, row logic,
 *      pagination markup, totals block, and URL-state rules)
 *
 *  Notes:
 *    - Glossary DOES NOT use a standalone fragment file
 *      (markup is rendered 100% inside this file)
 *    - AJAX endpoint captures the full #results wrapper from
 *      this shortcode output for swap-in behavior
 *    - Alpha ranges, sort values, and pagination all feed into
 *      unified GET-state logic for live updates and new-tab load
 * ============================================================
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

        <?php if ($qs !== ""): ?>
          <!-- No-JS parity: keep an active search when changing letters -->
          <input type="hidden" name="qs" value="<?php echo esc_attr($qs); ?>">
        <?php endif; ?>
      </form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // If AJAX is active, bail out so this native handler never runs
  if (window.GL_AJAX) return;

  const form = document.querySelector('.genes-sort__form');
  if (!form) return;

  // Native submit → stays as fallback
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form));
    params.delete('g_paged');
    const qs = params.toString();
    const newUrl = window.location.pathname + (qs ? '?' + qs : '') + '#results';
    window.location.assign(newUrl);
  });

  /* keep these commented — AJAX owns them now
  // Auto-submit on SORT change
  const sortSelect = form.querySelector('select[name="g_sort"]');
  if (sortSelect) {
    sortSelect.addEventListener('change', function () {
      ...
    });
  }

  // Auto-submit on ALPHA change
  const alphaSelect = form.querySelector('select[name="alpha"]');
  if (alphaSelect) {
    alphaSelect.addEventListener('change', function () {
      ...
    });
  }
  */

  // CLEAR button — stays as fallback
  const clearBtn = form.querySelector('.genes-sort__clear');
  if (clearBtn) {
    clearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const params = new URLSearchParams(new FormData(form));
      params.delete('alpha');
      params.delete('g_paged');
      const qs = params.toString();
      const newUrl = window.location.pathname + (qs ? '?' + qs : '') + '#results';
      window.location.assign(newUrl);
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
            <article class="dr-card wp-block-post eic-subtype-card">
              <a class="eic-subtype-card__link" href="<?php the_permalink(); ?>">

                <div class="eic-subtype-card__body">

                  <div class="eic-subtype-card__head">
                    <div class="eic-subtype-card__heading">
                      <span class="eic-subtype-card__dot" aria-hidden="true"></span>
                      <h2 class="eic-subtype-card__title"><?php the_title(); ?></h2>
                    </div>
                  </div>

                  <?php $gl_aka = trim(
                      (string) get_field("aka_synonyms", $pid)
                  ); ?>
                  <?php if ($gl_aka !== ""): ?>
                    <p class="eic-subtype-card__alias">aka: <?php echo esc_html(
                        $gl_aka
                    ); ?></p>
                  <?php endif; ?>

                  <?php
                  $gl_def = trim((string) get_field("short_definition", $pid));
                  if ($gl_def === "") {
                      $gl_def = wp_trim_words(
                          wp_strip_all_tags(
                              get_post_field("post_content", $pid)
                          ),
                          28,
                          " …"
                      );
                  }
                  ?>
                  <?php if ($gl_def !== ""): ?>
                    <p class="eic-subtype-card__summary"><?php echo esc_html(
                        $gl_def
                    ); ?></p>
                  <?php endif; ?>

                </div>

                <footer class="eic-subtype-card__footer">
                  <time
                    class="eic-subtype-card__updated"
                    datetime="<?php echo esc_attr(
                        get_the_modified_date("Y-m-d")
                    ); ?>">
                    Updated: <?php echo esc_html(
                        get_the_modified_date("F j, Y")
                    ); ?>
                  </time>
                  <span class="eic-subtype-card__arrow" aria-hidden="true">→</span>
                </footer>

              </a>
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
        <div id="glossary-no-results" class="dr-row dr-row--empty"
             style="margin:0 auto 64px auto;display:flex;justify-content:center;align-items:flex-start;max-width:700px;width:100%;">
          <p style="font-size:1.1rem;color:#333;text-align:left;">
            No Matching Glossary Entries Were Found<br>
          </p>
        </div>
      <?php endif; ?>

    </div><!-- /#results -->
</div>


    <?php return ob_get_clean();
});
