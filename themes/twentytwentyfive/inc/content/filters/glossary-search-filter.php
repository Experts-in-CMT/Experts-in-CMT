<?php
/**
 * Glossary Search Filter (Shortcode)
 * Shortcode: [glossary_search_filter]
 * Neutral classes (site-search*) and glossary-aware params.
 *
 * @package ExpertsInCMT
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!shortcode_exists("glossary_search_filter")) {
    add_shortcode("glossary_search_filter", function () {
        // Current search text
        $search_text = isset($_GET["qs"])
            ? sanitize_text_field((string) $_GET["qs"])
            : "";

        // Anchor + base URL (mirror glossary loop behavior)
        $anchor = "results";
        $base = get_permalink(get_queried_object_id());
        if (!$base) {
            // Fallback to your glossary page slug if needed
            $glossary_page = get_page_by_path("cmt-words");
            $base = $glossary_page
                ? get_permalink($glossary_page->ID)
                : home_url("/cmt-words/");
        }

        $action_url = esc_url($base . "#" . $anchor);
        $reset_url = esc_url($base . "#" . $anchor);

        ob_start();
        ?>
    <div class="site-searchwrap">
      <form
  class="site-search"
  method="get"
  action="<?php echo $action_url; ?>"
  data-loop="gl"
  data-paged-param="g_paged"
  data-search-param="qs"
  data-alpha-param="alpha"
  data-sort-param=""
>
  <div class="site-search__bar">
    <div class="site-search__row">

      <!-- SEARCH -->
      <label class="site-search__field site-search__field--input">
        <span class="site-search__label">Search the Glossary</span>
        <input
          type="search"
          name="qs"
          value="<?php echo esc_attr($search_text); ?>"
          placeholder="Begin by typing…"
          autocomplete="off"
          aria-describedby="site-search-hint"
        />
      </label>

      <!-- ACTIONS -->
      <div class="site-search__actions" id="site-search-hint">
        <button type="submit" class="site-search__btn">SEARCH</button>
        <a class="site-search__reset" data-reset="true" href="<?php echo $reset_url; ?>">RESET</a>
      </div>

      <?php
      // Preserve other GET params (keep alpha; drop qs + pagination)
      foreach ($_GET as $k => $v) {
        if (in_array($k, ['qs','g_paged'], true)) continue; // glossary uses g_paged
        if (is_scalar($v)) {
          printf('<input type="hidden" name="%s" value="%s" />', esc_attr($k), esc_attr($v));
        }
      }
      ?>

      <noscript><button type="submit">Apply</button></noscript>
    </div>
  </div>
</form>

    </div>

    <script>
    // Glossary search behavior (no jump; preserve alpha; reset g_paged)
    document.addEventListener('DOMContentLoaded', function () {
if (window.GL_AJAX) return;
      const form = document.querySelector('form.site-search');
      if (!form) return;
      const anchor = '#results';

      // Submit: set qs, drop g_paged, reload anchored
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        const val = (form.querySelector('input[name="qs"]')?.value || '').trim();
        if (val) params.set('qs', val); else params.delete('qs');
        params.delete('g_paged'); // glossary pagination key
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + anchor;
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
      });

      // Reset: clear qs & g_paged, keep alpha if present
      const resetLink = form.querySelector('.site-search__reset');
      if (resetLink) {
        resetLink.addEventListener('click', function (e) {
          e.preventDefault();
          const params = new URLSearchParams(window.location.search);
          ['qs','g_paged'].forEach(k => params.delete(k));
          const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + anchor;
          window.history.replaceState(null, '', newUrl);
          window.location.reload();
        });
      }

      // Built-in clear (search input ×)
      const searchInput = form.querySelector('input[name="qs"]');
      if (searchInput) {
        searchInput.addEventListener('search', function () {
          if (searchInput.value === '') {
            const params = new URLSearchParams(window.location.search);
            ['qs','g_paged'].forEach(k => params.delete(k));
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