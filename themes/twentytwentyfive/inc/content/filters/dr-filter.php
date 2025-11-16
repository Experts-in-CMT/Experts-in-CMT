<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
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

add_shortcode("dr_filter", function ($atts = []) {
    ob_start();

    // ----------------------------
    // Resolve GET params (UI only)
    // ----------------------------
    $search_text = isset($_GET["qs"])
        ? trim((string) wp_unslash($_GET["qs"]))
        : "";
    $dr_cat = isset($_GET["dr_cat"]) ? (int) $_GET["dr_cat"] : 0;

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
            if (!is_wp_error($cats)) {
                foreach ($cats as $c) {
                    printf(
                        '<option value="%1$d"%2$s>%3$s</option>',
                        (int) $c->term_id,
                        selected($dr_cat, (int) $c->term_id, false),
                        esc_html($c->name)
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
            if (in_array($k, ["qs", "dr_paged", "dr_sort"], true)) {
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
