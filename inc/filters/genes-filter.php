<?php
/**
 * [genes_filter] — Genes DB filter UI only (no results)
 * - Renders the Wix-style single-selects + search bar
 * - Submits GET params that your [genes_loop] shortcode reads
 * - Action points to #cmt-genetics-database so the page jumps to the loop
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

  // If any term has 'sort' meta, use it.
  if (!is_wp_error($terms) && !empty($terms)) {
    foreach ($terms as $t) {
      if (get_term_meta($t->term_id, 'sort', true) !== '') return $terms;
    }
  }

  // Fallback: Chromosome logical order
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

  // Final fallback: name ASC
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
  // Current selections
  $sel_cmt_type = isset($_GET['cmt_type']) ? (int) $_GET['cmt_type'] : 0;
  $sel_inherit  = isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0;
  $sel_neuro    = isset($_GET['neuropathy']) ? (int) $_GET['neuropathy'] : 0;
  $sel_chrom    = isset($_GET['chromosome']) ? (int) $_GET['chromosome'] : 0;
  $search_text  = isset($_GET['qs']) ? sanitize_text_field((string) $_GET['qs']) : '';

  // Build base URL with anchor for form action (points to the loop section)
  $base = get_permalink(get_queried_object_id());
  if (!$base) {
    $genes_page = get_page_by_path('genes'); // optional fallback
    $base = $genes_page ? get_permalink($genes_page->ID) : home_url('/genes/');
  }
  $action_url = esc_url($base . '#cmt-genetics-database');

  // Reset URL (strip GET) and jump back to the filter itself
  $reset_url = esc_url( remove_query_arg( array_keys($_GET), $base ) . '#genes-filter' );

  ob_start(); ?>

  <style>
    /* Minimal, scoped styling */
    .genes-filter { margin: 0 0 24px; }
    .genes-filter__bar { max-width: 760px; margin: 0 auto; }
    .genes-filter__row { display: grid; gap: 16px; }
    .genes-filter__label { display:block; font-weight:600; margin: 0 0 6px; }
    .genes-filter select, .genes-filter input[type="search"] {
      width:100%; padding:10px 12px; border:1px solid #cfd6dc; border-radius:6px; background:#fff;
    }
    .genes-filter__hr { text-align:center; color:#89939a; margin:16px 0; }
    .genes-filter__actions { display:flex; gap:10px; align-items:center; }
    .genes-filter__btn { padding:8px 12px; border:1px solid #2d6cdf; background:#2d6cdf; color:#fff; border-radius:8px; cursor:pointer; }
    .genes-filter__link { color:#8b1b1b; text-decoration:none; border:1px solid #f1bbbb; padding:8px 10px; border-radius:8px; background:#fff5f5; }
    #genes-filter { scroll-margin-top: 90px; }
  </style>

  <a id="genes-filter"></a>
  <form class="genes-filter" method="get" action="<?php echo $action_url; ?>">
    <div class="genes-filter__bar">
      <div class="genes-filter__row">

        <!-- Top: Select Type -->
        <label class="genes-filter__field">
          <span class="genes-filter__label">Select Type</span>
          <select name="cmt_type">
            <?php echo _eicmt_gf_options_html_single('cmt_type', $sel_cmt_type, 'Select Type'); ?>
          </select>
        </label>

        <div class="genes-filter__hr">— OR —</div>

        <!-- Middle row: Inheritance / Neuropathy / Chromosome -->
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

        <!-- Bottom: text search -->
        <label class="genes-filter__field">
          <span class="genes-filter__label">Search by Gene, by Subtype, or by Year of Discovery</span>
          <input type="search" name="qs" value="<?php echo esc_attr($search_text); ?>" placeholder='ex: PMP22, SORD, CMTDIG, dHMN-2C, 1999 (type "All" to show everything)'>
        </label>

        <div class="genes-filter__actions">
          <button type="submit" class="genes-filter__btn">APPLY FILTERS</button>
          <a class="genes-filter__link" href="<?php echo $reset_url; ?>">RESET</a>
        </div>
      </div>
    </div>
  </form>

  <?php
  return ob_get_clean();
});
