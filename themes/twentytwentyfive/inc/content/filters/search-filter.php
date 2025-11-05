<?php
/**
 * Search Filter (Shortcode)
 * Shortcode: [search_filter]
 * Neutral classes (site-search*) so it doesn't affect the Genes array.
 *
 * @package ExpertsInCMT
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!shortcode_exists("search_filter")) {
    add_shortcode("search_filter", function () {
        $search_text = isset($_GET["qs"])
            ? sanitize_text_field((string) $_GET["qs"])
            : "";

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
    <div class="site-searchwrap">
      <form class="site-search" method="get" action="<?php echo $action_url; ?>">
        <div class="site-search__bar">
          <div class="site-search__row">

            <!-- SEARCH -->
            <label class="site-search__field site-search__field--input">
              <span class="site-search__label">Search</span>
              <input
                type="search"
                name="qs"
                value="<?php echo esc_attr($search_text); ?>"
                placeholder='Begin by Typing...'
                autocomplete="off"
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
    // Neutral search behavior (no jump; preserve other params)
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.querySelector('form.site-search');
      if (!form) return;

      const anchor = '#results';

      // Submit: set qs, drop gd_paged, reload anchored
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        const qsInput = form.querySelector('input[name="qs"]');
        const val = qsInput ? qsInput.value.trim() : '';
        if (val) params.set('qs', val); else params.delete('qs');
        params.delete('gd_paged');
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + anchor;
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
      });

      // RESET: clear qs & gd_paged, keep others, reload anchored
      const resetLink = form.querySelector('.site-search__reset');
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
