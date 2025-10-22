<?php
/* ============================================================
   DORSAL ROOT FILTER SHORTCODE ONLY
   (with text search; no class/markup changes to existing bits)
   ============================================================ */

/**
 * [dr_filter] — shows the category dropdown (?dr_cat=slug)
 * plus a text search field (?dr_q=string)
 */
add_shortcode('dr_filter', function () {
    $tax   = 'dorsal-root';
    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
    if (is_wp_error($terms)) return '';

    $current = isset($_GET['dr_cat']) ? sanitize_text_field(wp_unslash($_GET['dr_cat'])) : '';
    $qvalue  = isset($_GET['dr_q'])   ? sanitize_text_field(wp_unslash($_GET['dr_q']))   : '';
    $action  = esc_url(remove_query_arg(array_keys($_GET))) . '#blog';

    ob_start(); ?>
    <form
      class="dr-filter"
      role="search"
      aria-label="Filter Dorsal Root posts"
      aria-controls="blog"
      action="<?php echo $action; ?>"
      method="get"
    >
      <label for="dr-cat">Filter The Dorsal Root by Category</label>
      <select id="dr-cat" name="dr_cat" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach ($terms as $t): ?>
          <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($current, $t->slug); ?>>
            <?php echo esc_html($t->name); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Text search (title/content + taxonomy terms via loop-side logic) -->
      <label class="screen-reader-text" for="dr-q">Search The Dorsal Root</label>
      <input
        id="dr-q"
        type="search"
        name="dr_q"
        value="<?php echo esc_attr($qvalue); ?>"
        placeholder="Search The Dorsal Root"
        autocomplete="off"
        aria-describedby="dr-filter-hint"
      />

      <!-- Execute search button -->
      <div class="dr-filter-buttons" id="dr-filter-hint">
        <button type="submit" class="wp-block-button__link">Search</button>
        <button type="button" id="dr-reset" class="wp-block-button__link">Reset</button>
      </div>

      <?php
      // Preserve other query args
      foreach ($_GET as $k => $v) {
          if ($k === 'dr_cat' || $k === 'dr_q') continue;
          if (is_scalar($v)) {
              printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr($v));
          }
      }
      ?>

      <noscript><button type="submit">Apply</button></noscript>

      <!-- Handles native search box '×' clear -->
      <script>
      document.addEventListener('DOMContentLoaded', function () {
        const drInput = document.getElementById('dr-q');
        if (!drInput) return;
        drInput.addEventListener('search', function () {
          if (drInput.value === '') {
            window.location.href = '<?php
              $dr_page = get_page_by_path("dorsal-root");
              echo esc_url($dr_page ? get_permalink($dr_page->ID) . "#blog" : home_url("/#blog"));
            ?>';
          }
        });
      });
      </script>

      <!-- Reset button mirrors native "x" behavior -->
      <script>
      document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'dr-reset') {
          var input = document.getElementById('dr-q');
          if (input) input.value = '';
          window.location.href = '<?php
            $dr_page = get_page_by_path("dorsal-root");
            echo esc_url($dr_page ? get_permalink($dr_page->ID) . "#blog" : home_url("/#blog"));
          ?>';
        }
      });
      </script>
    </form>
    <?php
    return ob_get_clean();
});
