<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * Base Search Filter Shortcode (Template Seed)
 * ------------------------------------------------------------
 * Purpose:
 *   This file is not loaded directly. It serves as the master
 *   reference for building new filter.php implementations.
 *
 * Usage:
 *   • Copy/paste the contents into a new filter shortcode file.
 *   • Add or remove facets as needed.
 *   • Keep search + reset behavior consistent with Genes.
 *
 * Notes:
 *   • Mirrors subtype-browser-filter.php defaults.
 *   • Provides exact bar/row/field markup for parity.
 *   • Includes hidden GET preservation and anchored reload.
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!shortcode_exists("search_filter")) {
    add_shortcode("search_filter", function () {
        // Current search text
        $search_text = isset($_GET["qs"])
            ? sanitize_text_field((string) $_GET["qs"])
            : "";

        // Anchor + base URL resolution (mirrors subtype-browser-filter.php)
        $anchor = "results";

        $base = get_permalink(get_queried_object_id());
        if (!$base) {
            $genes_page = get_page_by_path("genes");
            $base = $genes_page
                ? get_permalink($genes_page->ID)
                : home_url("/genes/");
        }

        $action_url = esc_url($base . "#" . $anchor);
        $reset_url = esc_url($base . "#" . $anchor);

        ob_start();
        ?>
    <div class="genesdb-filter-wrap">
      <form class="genes-filter" method="get" action="<?php echo $action_url; ?>">
        <div class="genes-filter__bar">
          <div class="genes-filter__row">

            <!-- SEARCH (exactly as in subtype-browser-filter.php) -->
            <label class="genes-filter__field genes-filter__field--search">
              <span class="genes-filter__label">Search by Gene, by Subtype, or by Year of Discovery</span>
              <input
                type="search"
                name="qs"
                value="<?php echo esc_attr($search_text); ?>"
                placeholder='ex: PMP22, SORD, CMTDIG, dHMN-2C, 1999 (type "All" to show everything)'
                autocomplete="off"
                aria-describedby="genes-filter-hint"
              />
            </label>

            <!-- ACTIONS (two buttons, same classes) -->
            <div class="genes-filter__actions" id="genes-filter-hint">
              <button type="submit" class="genes-filter__btn">APPLY FILTERS</button>
              <a class="genes-filter__link" href="<?php echo $reset_url; ?>">RESET</a>
            </div>

            <?php // Preserve other GET params (don’t duplicate qs or pagination/sort)

        foreach ($_GET as $k => $v) {
                if (in_array($k, ["qs", "gd_paged", "gd_sort"], true)) {
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
    </div>

    <script>
    // Genes-style search behavior (no jump; preserve other params)
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.querySelector('form.genes-filter');
      if (!form) return;

      const anchor = '#results';

      // Submit: set qs, drop gd_paged, reload anchored
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);

        // Pull qs from the input
        const qsInput = form.querySelector('input[name="qs"]');
        const val = qsInput ? qsInput.value.trim() : '';

        if (val) params.set('qs', val); else params.delete('qs');
        params.delete('gd_paged'); // reset pagination

        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + anchor;
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
      });

      // RESET: clear qs & gd_paged, keep others, reload anchored
      const resetLink = form.querySelector('.genes-filter__link');
      if (resetLink) {
        resetLink.addEventListener('click', function (e) {
          e.preventDefault();
          const params = new URLSearchParams(window.location.search);
          ['qs','gd_paged'].forEach(k => params.delete(k));
          const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + anchor;
          window.history.replaceState(null, '', newUrl);
          window.location.reload();
        });
      }

      // Built-in clear on <input type="search">
      const searchInput = form.querySelector('input[name="qs"]');
      if (searchInput) {
        searchInput.addEventListener('search', function () {
          if (searchInput.value === '') {
            const params = new URLSearchParams(window.location.search);
            ['qs','gd_paged'].forEach(k => params.delete(k));
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + anchor;
            window.history.replaceState(null, '', newUrl);
            window.location.reload();
          }
        });
      }
    });
    </script>
    <?php return ob_get_clean();
    });
}
