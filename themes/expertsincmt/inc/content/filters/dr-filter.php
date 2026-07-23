<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  [dr_filter] — Dorsal Root Filter UI
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Renders the DR filter/search bar (category + search input)
 *    - Outputs GET params consumed by the DR loop and dr-ajax.js
 *    - Action preserves URL state and anchors to #results
 *
 *  Notes:
 *    - Works in parity with Genes and Glossary filter components
 *    - GET params used by DR loop:
 *        dr_cat   (int)    taxonomy term_id
 *        qs       (string) search text
 *        dr_sort  (string) external sort selector
 *        dr_paged (int)    pagination
 *
 *    - AJAX layer handles:
 *        auto-submit on category change
 *        debounced live search
 *        clean URL updates
 *        scroll and focus behavior
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Facet counts for the DR category selector.
 *
 * Counts how many posts each `dorsal-root` term would return under
 * the current search, deliberately ignoring the active category.
 * A facet that constrained its own options would report the chosen
 * category's total and zero for everything else, making the
 * selector impossible to change.
 *
 * Mirrors the loop's search behavior in dr-posts.php: when a search
 * is active the base set is the union of text matches and posts
 * whose category or tag names match.
 *
 * @param array $req Raw request array (GET, or POST for AJAX).
 * @return array [ term_id => count ]
 */
function eic_dr_facet_counts(array $req)
{
    $qs = isset($req["qs"]) ? trim(sanitize_text_field($req["qs"])) : "";

    $base_args = [
        "post_type" => "post",
        "post_status" => "publish",
        "fields" => "ids",
        "posts_per_page" => -1,
        "no_found_rows" => true,
    ];

    if ($qs === "") {
        $ids = get_posts($base_args);
    } else {
        $ids = get_posts(array_merge($base_args, ["s" => $qs]));

        // Posts whose category or tag NAMES match the search.
        foreach (["dorsal-root", "post_tag"] as $taxonomy) {
            $term_ids = get_terms([
                "taxonomy" => $taxonomy,
                "search" => $qs,
                "fields" => "ids",
                "hide_empty" => false,
            ]);

            if (is_wp_error($term_ids) || empty($term_ids)) {
                continue;
            }

            $ids = array_merge(
                $ids,
                get_posts(
                    array_merge($base_args, [
                        "tax_query" => [
                            [
                                "taxonomy" => $taxonomy,
                                "field" => "term_id",
                                "terms" => $term_ids,
                                "include_children" => true,
                                "operator" => "IN",
                            ],
                        ],
                    ])
                )
            );
        }

        $ids = array_values(array_unique($ids));
    }

    if (empty($ids)) {
        return [];
    }

    $counts = [];
    $terms = wp_get_object_terms($ids, "dorsal-root", [
        "fields" => "all_with_object_id",
    ]);

    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $tid = (int) $term->term_id;
            $counts[$tid] = ($counts[$tid] ?? 0) + 1;
        }
    }

    return $counts;
}

add_shortcode("dr_filter", function ($atts = []) {
    ob_start();

    // ----------------------------
    // Resolve GET params (UI only)
    // ----------------------------
    $search_text = isset($_GET["qs"])
        ? trim((string) wp_unslash($_GET["qs"]))
        : "";
    // Resolve dr_cat to a term_id so the select highlights correctly for
    // BOTH slug-form URLs (what dr-ajax.js writes) and legacy numeric
    // links. A bare (int) cast turned any slug into 0, so a shared clean
    // URL rendered "All Categories" until JS rehydrated.
    $dr_cat = 0;
    if (isset($_GET["dr_cat"]) && function_exists("eic_resolve_tax_field")) {
        $dr_cat_resolved = eic_resolve_tax_field($_GET["dr_cat"], "dorsal-root");
        if ($dr_cat_resolved !== null) {
            if ($dr_cat_resolved["field"] === "term_id") {
                $dr_cat = (int) $dr_cat_resolved["value"];
            } else {
                $dr_cat_term = get_term_by(
                    "slug",
                    $dr_cat_resolved["value"],
                    "dorsal-root"
                );
                $dr_cat = $dr_cat_term ? (int) $dr_cat_term->term_id : 0;
            }
        }
    }

    // Current page URL (no query, no hash)
    $action_url = esc_url(get_permalink());

    // RESET url (clear qs & dr_paged; keep others including dr_cat)
    $params = $_GET;
    unset($params["qs"], $params["dr_paged"], $params["dr_cat"]);
    $reset_url = esc_url(add_query_arg($params, $action_url) . "#results");
    ?>

<div class="site-searchwrap">
  <form class="site-search"
      method="get"
      action="<?php echo $action_url; ?>"
      data-loop="dr"
      data-action="dr_get_posts"
      data-per-page="12"
      data-anchor="#results"
      data-paged-param="dr_paged"
      data-sort-param="dr_sort"
      data-search-param="qs"
      data-category-param="dr_cat">

    <div class="site-search__bar">
      <div class="site-search__row">

        <!-- CATEGORY -->
        <label class="site-search__field site-search__field--select">
          <span class="site-search__label">Filter by Category</span>
          <select name="dr_cat" class="site-search__select" aria-label="Filter by category">
            <option value="">All Categories</option>
            <?php
            $cats = get_terms([
                "taxonomy" => "dorsal-root",
                "hide_empty" => false,
                "orderby" => "name",
                "order" => "ASC",
            ]);

            // Facet counts for the current search, rendered server-side
            // so the numbers are right on first paint.
            $dr_counts = function_exists("eic_dr_facet_counts")
                ? eic_dr_facet_counts($_GET)
                : [];

            if (!is_wp_error($cats)) {
                foreach ($cats as $c) {
                    $tid = (int) $c->term_id;
                    $n = isset($dr_counts[$tid]) ? (int) $dr_counts[$tid] : 0;
                    $is_current = $dr_cat === $tid;

                    printf(
                        '<option value="%1$d"%2$s%3$s data-facet-term="%1$d" data-facet-label="%4$s">%5$s</option>',
                        $tid,
                        selected($dr_cat, $tid, false),
                        $n === 0 && !$is_current ? " disabled" : "",
                        esc_attr($c->name),
                        esc_html($c->name . " (" . $n . ")")
                    );
                }
            }
            ?>
          </select>
        </label>

        <!-- SEARCH -->
        <label class="site-search__field site-search__field--input">
          <span class="site-search__label">Search The Dorsal Root</span>
          <input
            type="search"
            name="qs"
            value="<?php echo esc_attr($search_text); ?>"
            placeholder="Begin by Typing..."
            inputmode="search"
            autocomplete="on"
            autocapitalize="none"
            spellcheck="false"
            enterkeyhint="search"
            aria-describedby="site-search-hint"
          />
        </label>

        <!-- ACTIONS -->
        <div class="site-search__actions" id="site-search-hint">
          <button type="submit" class="site-search__btn">SEARCH</button>
          <a class="site-search__reset" href="<?php echo $reset_url; ?>">RESET</a>
        </div>

        <?php // Preserve other GET params (don’t duplicate qs or pagination/sort)

    foreach ($_GET as $k => $v) {
            if (in_array($k, ["qs", "dr_cat", "dr_paged", "dr_sort"], true)) {
                continue;
            }
            if (is_scalar($v)) {
                printf(
                    '<input type="hidden" name="%s" value="%s" />',
                    esc_attr($k),
                    esc_attr($v)
                );
            }
        } ?>

        <noscript><button type="submit">Apply</button></noscript>
      </div>
    </div>
  </form>

 <script>
  // Legacy: auto-submit on category change (disabled when AJAX is present)
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form.site-search');
    if (!form || window.DR_AJAX) return; // ← stop if dr-ajax.js is active

    const cat = form.querySelector('select[name="dr_cat"]');
    if (!cat) return;

    const anchor = '#results';

    cat.addEventListener('change', function () {
      const params = new URLSearchParams(new FormData(form));
      params.delete('dr_paged');
      if (!cat.value) params.delete('dr_cat');
      const qs  = params.toString();
      const url = window.location.pathname + (qs ? '?' + qs : '') + anchor;

      window.history.replaceState(null, '', url);
      window.location.reload();
    });
  });
</script>


  <script>
  // Legacy: submit/reset/search-clear (disabled when AJAX is present)
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form.site-search');
    if (!form || window.DR_AJAX) return; // ← stop if dr-ajax.js is active

    const anchor = '#results';

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const params = new URLSearchParams(new FormData(form));
      params.delete('dr_paged');
      const newUrl = window.location.pathname + '?' + params.toString() + anchor;
      window.history.replaceState(null, '', newUrl);
      window.location.reload();
    });

    const resetLink = form.querySelector('.site-search__reset');
    if (resetLink) {
      resetLink.addEventListener('click', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        ['qs','dr_paged','dr_cat'].forEach(k => params.delete(k));
        const qs = params.toString();
        const newUrl = window.location.pathname + (qs ? '?' + qs : '') + anchor;
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
      });
    }

    const searchInput = form.querySelector('input[name="qs"]');
    const cat = form.querySelector('select[name="dr_cat"]');
    if (searchInput) {
      searchInput.addEventListener('search', function () {
        if (searchInput.value === '') {
          if (cat) cat.value = '';
          const params = new URLSearchParams(new FormData(form));
          ['qs','dr_paged','dr_cat'].forEach(k => params.delete(k));
          const qs = params.toString();
          const newUrl = window.location.pathname + (qs ? '?' + qs : '') + anchor;
          window.history.replaceState(null, '', newUrl);
          window.location.reload();
        }
      });
    }
  });
</script>
</div>

<?php return ob_get_clean();
});
