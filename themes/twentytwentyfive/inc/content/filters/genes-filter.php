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

if (shortcode_exists("genes_filter")) {
    return;
}

/** Ordered terms helper: sort meta → chromosome fallback → name */
function _eicmt_gf_get_terms_ordered($taxonomy, $args = [])
{
    $defaults = [
        "taxonomy" => $taxonomy,
        "hide_empty" => false,
        "meta_key" => "sort",
        "orderby" => "meta_value_num",
        "order" => "ASC",
    ];
    $terms = get_terms(wp_parse_args($args, $defaults));

    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $t) {
            if (get_term_meta($t->term_id, "sort", true) !== "") {
                return $terms;
            }
        }
    }

    if ($taxonomy === "chromosome") {
        $wanted = [
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9",
            "10",
            "11",
            "12",
            "13",
            "14",
            "15",
            "16",
            "17",
            "18",
            "19",
            "20",
            "21",
            "22",
            "X",
            "Y",
        ];
        $map = [];
        $all = get_terms([
            "taxonomy" => "chromosome",
            "hide_empty" => false,
            "orderby" => "name",
            "order" => "ASC",
        ]);
        if (!is_wp_error($all)) {
            foreach ($all as $t) {
                $map[$t->name] = $t;
            }
            $out = [];
            foreach ($wanted as $w) {
                if (isset($map[$w])) {
                    $out[] = $map[$w];
                }
            }
            if (count($out) < count($all)) {
                $missing = array_diff_key($map, array_flip($wanted));
                if ($missing) {
                    $rest = array_values($missing);
                    usort(
                        $rest,
                        fn($a, $b) => strnatcasecmp($a->name, $b->name)
                    );
                    $out = array_merge($out, $rest);
                }
            }
            return $out;
        }
    }

    return get_terms([
        "taxonomy" => $taxonomy,
        "hide_empty" => false,
        "orderby" => "name",
        "order" => "ASC",
    ]);
}

/** Build single-select <option>s (with placeholder on top) */
function _eicmt_gf_options_html_single(
    $taxonomy,
    $selected = "",
    $placeholder = ""
) {
    $terms = _eicmt_gf_get_terms_ordered($taxonomy);
    $sel = (string) $selected;
    $html = "";
    if ($placeholder !== "") {
        $html .= '<option value="">' . esc_html($placeholder) . "</option>";
    }
    foreach ($terms as $t) {
        $is = (string) $t->term_id === $sel ? " selected" : "";
        $html .=
            '<option value="' .
            (int) $t->term_id .
            '"' .
            $is .
            ">" .
            esc_html($t->name) .
            "</option>";
    }
    return $html;
}

add_shortcode("genes_filter", function () {
    $sel_cmt_type = isset($_GET["cmt_type"]) ? (int) $_GET["cmt_type"] : 0;
    $sel_inherit = isset($_GET["inheritance"]) ? (int) $_GET["inheritance"] : 0;
    $sel_neuro = isset($_GET["neuropathy"]) ? (int) $_GET["neuropathy"] : 0;
    $sel_chrom = isset($_GET["chromosome"]) ? (int) $_GET["chromosome"] : 0;
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

    $current_sort = isset($_GET["gd_sort"])
        ? sanitize_key($_GET["gd_sort"])
        : "";

    $keep = $_GET; // preserve other filters
    unset($keep["gd_sort"], $keep["gd_paged"]);
    $sort_clear_url = esc_url(add_query_arg($keep, $base) . "#" . $anchor);

    ob_start();
    ?>

<a id="genes-filter"></a>
<div class="genesdb-filter-wrap">
  <form class="site-search genes-filter"
      method="get"
      action="<?php echo $action_url; ?>"
      data-loop="genes"
      data-search-param="qs"
      data-paged-param="gd_paged"
      data-sort-param="gd_sort"
      data-anchor="#results"
      data-per-page="12"
      role="search">

    <div class="genes-filter__bar">
      <div class="genes-filter__row">

<!-- TYPE -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Type</span>
  <?php
  $type_terms = _eicmt_gf_get_terms_ordered("cmt_type");
  $type_curr = isset($_GET["cmt_type"]) ? (int) $_GET["cmt_type"] : 0;
  ?>
  <select name="cmt_type" id="gf-type" class="genes-filter__select">
    <option value="0"<?php selected($type_curr, 0); ?>>Browse All</option>
    <?php foreach ($type_terms as $term):
      $tid = is_object($term) ? (int) $term->term_id : (int) ($term["term_id"] ?? 0);
      $tobj = $tid ? get_term($tid, "cmt_type") : null;
      $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
    ?>
      <option value="<?php echo $tid; ?>" <?php selected($type_curr, $tid); ?>>
        <?php echo esc_html($tname); ?>
      </option>
    <?php endforeach; ?>
  </select>
</label>

<!-- INHERITANCE -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Inheritance</span>
  <?php
  $inheritance_terms = _eicmt_gf_get_terms_ordered("inheritance");
  $inheritance_curr = isset($_GET["inheritance"]) ? (int) $_GET["inheritance"] : 0;
  ?>
  <select name="inheritance" id="gf-inheritance" class="genes-filter__select">
    <option value="0"<?php selected($inheritance_curr, 0); ?>>All Inheritance</option>
    <?php foreach ($inheritance_terms as $term):
      $tid = is_object($term) ? (int) $term->term_id : (int) ($term["term_id"] ?? 0);
      $tobj = $tid ? get_term($tid, "inheritance") : null;
      $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
    ?>
      <option value="<?php echo $tid; ?>" <?php selected($inheritance_curr, $tid); ?>>
        <?php echo esc_html($tname); ?>
      </option>
    <?php endforeach; ?>
  </select>
</label>

<!-- NEUROPATHY -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Neuropathy</span>
  <?php
  $neuropathy_terms = _eicmt_gf_get_terms_ordered("neuropathy");
  $neuropathy_curr = isset($_GET["neuropathy"]) ? (int) $_GET["neuropathy"] : 0;
  ?>
  <select name="neuropathy" id="gf-neuropathy" class="genes-filter__select">
    <option value="0"<?php selected($neuropathy_curr, 0); ?>>All Neuropathy</option>
    <?php foreach ($neuropathy_terms as $term):
      $tid = is_object($term) ? (int) $term->term_id : (int) ($term["term_id"] ?? 0);
      $tobj = $tid ? get_term($tid, "neuropathy") : null;
      $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
    ?>
      <option value="<?php echo $tid; ?>" <?php selected($neuropathy_curr, $tid); ?>>
        <?php echo esc_html($tname); ?>
      </option>
    <?php endforeach; ?>
  </select>
</label>

<!-- CHROMOSOME -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Chromosome</span>
  <?php
  $chromosome_terms = _eicmt_gf_get_terms_ordered("chromosome");
  $chromosome_curr = isset($_GET["chromosome"]) ? (int) $_GET["chromosome"] : 0;
  ?>
  <select name="chromosome" id="gf-chromosome" class="genes-filter__select">
    <option value="0"<?php selected($chromosome_curr, 0); ?>>All Chromosomes</option>
    <?php foreach ($chromosome_terms as $term):
      $tid = is_object($term) ? (int) $term->term_id : (int) ($term["term_id"] ?? 0);
      $tobj = $tid ? get_term($tid, "chromosome") : null;
      $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
    ?>
      <option value="<?php echo $tid; ?>" <?php selected($chromosome_curr, $tid); ?>>
        <?php echo esc_html($tname); ?>
      </option>
    <?php endforeach; ?>
  </select>
</label>

<!-- SEARCH -->
<label class="genes-filter__field genes-filter__field--search">
  <span class="genes-filter__label">Search by Gene, by Subtype, or by Year of Discovery</span>
  <input
    type="search"
    name="qs"
    value="<?php echo esc_attr($search_text); ?>"
    placeholder='ex: PMP22, SORD, CMTDIG, dHMN-2C, 1999 (type "All" to show everything)'
    autocomplete="on"
    autocapitalize="none"
    spellcheck="false"
    inputmode="search"
    enterkeyhint="search"
    aria-describedby="genes-filter-hint"
  />
</label>

<!-- ACTIONS -->
<div class="genes-filter__actions" id="genes-filter-hint">
  <button type="submit" class="genes-filter__btn">APPLY FILTERS</button>
  <a class="genes-filter__link"
     href="<?php echo esc_url( $reset_url ); ?>#results"
     data-role="genes-reset">RESET</a>
</div>

<?php
// Preserve other GET params (skip visible controls and gd_* we manage)
foreach ($_GET as $k => $v) {
  if (in_array($k, [
    'cmt_type',
    'inheritance',
    'neuropathy',
    'chromosome',
    'qs',
    'gd_sort',
    'gd_paged', // skip to prevent duplicate
  ], true)) {
    continue;
  }
  if (is_scalar($v)) {
    printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr($v));
  }
}
?>

<noscript><button type="submit">Apply</button></noscript>

<input type="hidden" name="gd_paged" value="<?php echo isset($_GET['gd_paged']) ? esc_attr(wp_unslash($_GET['gd_paged'])) : '1'; ?>">



</div>
</div>

</form>






</div>

<?php return ob_get_clean();
});
?>
