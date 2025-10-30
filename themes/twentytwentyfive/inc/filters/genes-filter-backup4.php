<?php
/**
 * [genes_filter] — Genes DB filter UI only (no results)
 * - Renders the single-selects + search bar
 * - Submits GET params that your [genes_loop] shortcode reads
 * - Action appends #results so the browser jumps to results
 *
 * GET params used by the loop:
 *   cmt_type (int), inheritance (int), neuropathy (int), chromosome (int), qs (string)
 */

if ( shortcode_exists('genes_filter') ) return;

/** Ordered terms helper: sort meta → chromosome fallback → name */
function _eicmt_gf_get_terms_ordered($taxonomy, $args = []) {
  $defaults = [
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
    'meta_key'   => 'sort',
    'orderby'    => 'meta_value_num',
    'order'      => 'ASC',
  ];
  $terms = get_terms(wp_parse_args($args, $defaults));

  if (!is_wp_error($terms) && !empty($terms)) {
    foreach ($terms as $t) {
      if (get_term_meta($t->term_id, 'sort', true) !== '') return $terms;
    }
  }

  if ($taxonomy === 'chromosome') {
    $wanted = ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','X','Y'];
    $map = [];
    $all = get_terms(['taxonomy'=>'chromosome','hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
    if (!is_wp_error($all)) {
      foreach ($all as $t) $map[$t->name] = $t;
      $out = [];
      foreach ($wanted as $w) if (isset($map[$w])) $out[] = $map[$w];
      if (count($out) < count($all)) {
        $missing = array_diff_key($map, array_flip($wanted));
        if ($missing) {
          $rest = array_values($missing);
          usort($rest, fn($a,$b)=>strnatcasecmp($a->name,$b->name));
          $out = array_merge($out, $rest);
        }
      }
      return $out;
    }
  }

  return get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
}

/** Build single-select <option>s (with placeholder on top) */
function _eicmt_gf_options_html_single($taxonomy, $selected = '', $placeholder = '') {
  $terms = _eicmt_gf_get_terms_ordered($taxonomy);
  $sel   = (string) $selected;
  $html  = '';
  if ($placeholder !== '') {
    $html .= '<option value="">' . esc_html($placeholder) . '</option>';
  }
  foreach ($terms as $t) {
    $is = ((string)$t->term_id === $sel) ? ' selected' : '';
    $html .= '<option value="'.(int)$t->term_id.'"'.$is.'>'.esc_html($t->name).'</option>';
  }
  return $html;
}

add_shortcode('genes_filter', function () {
  $sel_cmt_type = isset($_GET['cmt_type']) ? (int) $_GET['cmt_type'] : 0;
  $sel_inherit  = isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0;
  $sel_neuro    = isset($_GET['neuropathy']) ? (int) $_GET['neuropathy'] : 0;
  $sel_chrom    = isset($_GET['chromosome']) ? (int) $_GET['chromosome'] : 0;
  $search_text  = isset($_GET['qs']) ? sanitize_text_field((string) $_GET['qs']) : '';

  $anchor = 'results';

  $base = get_permalink(get_queried_object_id());
  if (!$base) {
    $genes_page = get_page_by_path('genes');
    $base = $genes_page ? get_permalink($genes_page->ID) : home_url('/genes/');
  }

  $action_url = esc_url($base . '#' . $anchor);
  $reset_url  = esc_url($base . '#' . $anchor);

  ob_start(); ?>

  <a id="genes-filter"></a>
  <div class="genesdb-filter-wrap">
    <form class="genes-filter" method="get" action="<?php echo $action_url; ?>">
      <div class="genes-filter__bar">
        <div class="genes-filter__row">

          <label class="genes-filter__field">
            <span class="genes-filter__label">Select Type</span>
            <select name="cmt_type">
              <?php echo _eicmt_gf_options_html_single('cmt_type', $sel_cmt_type, 'Browse All'); ?>
            </select>
          </label>

          <div class="genes-filter__hr">— OR —</div>

          <label class="genes-filter__field">
            <span class="genes-filter__label">Select Inheritance</span>
            <select name="inheritance">
              <?php echo _eicmt_gf_options_html_single('inheritance', $sel_inherit, 'All Inheritance'); ?>
            </select>
          </label>

          <label class="genes-filter__field">
            <span class="genes-filter__label">Select Neuropathy</span>
            <select name="neuropathy">
              <?php echo _eicmt_gf_options_html_single('neuropathy', $sel_neuro, 'All Neuropathy'); ?>
            </select>
          </label>

          <label class="genes-filter__field">
            <span class="genes-filter__label">Select Chromosome</span>
            <select name="chromosome">
              <?php echo _eicmt_gf_options_html_single('chromosome', $sel_chrom, 'All Chromosomes'); ?>
            </select>
          </label>

          <div class="genes-filter__hr">— OR —</div>

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

          <div class="genes-filter__actions" id="genes-filter-hint">
            <button type="submit" class="genes-filter__btn">APPLY FILTERS</button>
            <a class="genes-filter__link" href="<?php echo $reset_url; ?>">RESET</a>
          </div>

          <?php
          foreach ($_GET as $k => $v) {
            if (in_array($k, ['cmt_type','inheritance','neuropathy','chromosome','qs'], true)) continue;
            if (is_scalar($v)) {
              printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr($v));
            }
          }
          ?>

          <noscript><button type="submit">Apply</button></noscript>

          <script>
          document.addEventListener('DOMContentLoaded', function () {
            var input = document.querySelector('form.genes-filter input[name="qs"]');
            if (!input) return;
            input.addEventListener('search', function () {
              if (input.value === '') window.location.href = <?php echo json_encode($reset_url); ?>;
            });
          });
          </script>

        </div>
      </div>
    </form>
  </div>

  <?php
  return ob_get_clean();
});
