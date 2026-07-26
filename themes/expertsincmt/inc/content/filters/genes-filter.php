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
 *  [genes_filter] — Genes Database Filter UI
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Renders the full filter UI for the Genes & Subtypes Database
 *      (selector dropdowns + search field + reset link)
 *    - Emits GET params consumed by:
 *        • [genes_loop] shortcode (page-load rendering)
 *        • genes-ajax.js (live AJAX updates)
 *
 *  Notes:
 *    - GET params produced by this filter:
 *        qs           (string) search text
 *        cmt_type     (int)    taxonomy term_id
 *        inheritance  (int)    taxonomy term_id
 *        neuropathy   (int)    taxonomy term_id
 *        chromosome   (int)    taxonomy term_id
 *        gd_sort      (string) sort selector value
 *        gd_paged     (int)    pagination value
 *
 *    - AJAX stack handles:
 *        dropdown changes
 *        debounced search
 *        clean URL updates
 *        pagination
 *        smooth focus-to-results
 *
 *    - Action URL anchors to #results for native page-load behavior
 *      when JavaScript is disabled.
 * ============================================================
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
            "MT",
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

add_shortcode("genes_filter", function ($atts = []) {
    // Inject shortcode attributes, including per_page
    $a = shortcode_atts(
        [
            "per_page" => 12,
        ],
        $atts
    );

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
        $genes_page = get_page_by_path("cmt-genetics-database");
        $base = $genes_page
            ? get_permalink($genes_page->ID)
            : home_url("/cmt-genetics-database/");
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
      data-per-page="<?php echo esc_attr($a["per_page"]); ?>"
      role="search">

    <div class="genes-filter__bar">
      <div class="genes-filter__row">

<?php
// Facet counts for the current filter state. Rendered server-side so
// the numbers are correct on first paint, then refreshed by AJAX.
$eic_facets = function_exists("eic_genes_facet_counts")
    ? eic_genes_facet_counts($_GET)
    : ["tax" => [], "flags" => []];

/** Append " (n)" to an option label, or mark it empty. */
if (!function_exists("_eicmt_gf_count_suffix")) {
    function _eicmt_gf_count_suffix($counts, $term_id)
    {
        $n = isset($counts[$term_id]) ? (int) $counts[$term_id] : 0;
        return " (" . $n . ")";
    }
}
?>

<!-- TYPE -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Type</span>
  <?php
  $type_terms = _eicmt_gf_get_terms_ordered("cmt_type");
  $type_curr = isset($_GET["cmt_type"]) ? (int) $_GET["cmt_type"] : 0;
  $type_counts = $eic_facets["tax"]["cmt_type"] ?? [];
  ?>
  <select name="cmt_type" id="gf-type" class="genes-filter__select">
    <option value="0"<?php selected($type_curr, 0); ?>>By All Types</option>
    <?php foreach ($type_terms as $term):

        $tid = is_object($term)
            ? (int) $term->term_id
            : (int) ($term["term_id"] ?? 0);
        $tobj = $tid ? get_term($tid, "cmt_type") : null;
        $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
        ?>
      <option
        value="<?php echo $tid; ?>"
        data-facet-tax="cmt_type"
        data-facet-term="<?php echo $tid; ?>"
        data-facet-label="<?php echo esc_attr($tname); ?>"
        <?php selected($type_curr, $tid); ?>
      >
        <?php echo esc_html(
            $tname . _eicmt_gf_count_suffix($type_counts, $tid)
        ); ?>
      </option>
    <?php
    endforeach; ?>
  </select>
</label>

<!-- INHERITANCE -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Inheritance</span>
  <?php
  $inheritance_terms = _eicmt_gf_get_terms_ordered("inheritance");
  $inheritance_curr = isset($_GET["inheritance"])
      ? (int) $_GET["inheritance"]
      : 0;
  $inheritance_counts = $eic_facets["tax"]["inheritance"] ?? [];
  ?>
  <select name="inheritance" id="gf-inheritance" class="genes-filter__select">
    <option value="0"<?php selected(
        $inheritance_curr,
        0
    ); ?>>By All Inheritance</option>
    <?php foreach ($inheritance_terms as $term):

        $tid = is_object($term)
            ? (int) $term->term_id
            : (int) ($term["term_id"] ?? 0);
        $tobj = $tid ? get_term($tid, "inheritance") : null;
        $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
        ?>
      <option
        value="<?php echo $tid; ?>"
        data-facet-tax="inheritance"
        data-facet-term="<?php echo $tid; ?>"
        data-facet-label="<?php echo esc_attr($tname); ?>"
        <?php selected($inheritance_curr, $tid); ?>
      >
        <?php echo esc_html(
            $tname . _eicmt_gf_count_suffix($inheritance_counts, $tid)
        ); ?>
      </option>
    <?php
    endforeach; ?>
  </select>
</label>

<!-- NEUROPATHY -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Neuropathy</span>
  <?php
  $neuropathy_terms = _eicmt_gf_get_terms_ordered("neuropathy");
  $neuropathy_curr = isset($_GET["neuropathy"]) ? (int) $_GET["neuropathy"] : 0;
  $neuropathy_counts = $eic_facets["tax"]["neuropathy"] ?? [];
  ?>
  <select name="neuropathy" id="gf-neuropathy" class="genes-filter__select">
    <option value="0"<?php selected(
        $neuropathy_curr,
        0
    ); ?>>By All Neuropathy</option>
    <?php foreach ($neuropathy_terms as $term):

        $tid = is_object($term)
            ? (int) $term->term_id
            : (int) ($term["term_id"] ?? 0);
        $tobj = $tid ? get_term($tid, "neuropathy") : null;
        $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
        ?>
      <option
        value="<?php echo $tid; ?>"
        data-facet-tax="neuropathy"
        data-facet-term="<?php echo $tid; ?>"
        data-facet-label="<?php echo esc_attr($tname); ?>"
        <?php selected($neuropathy_curr, $tid); ?>
      >
        <?php echo esc_html(
            $tname . _eicmt_gf_count_suffix($neuropathy_counts, $tid)
        ); ?>
      </option>
    <?php
    endforeach; ?>
  </select>
</label>

<!-- CHROMOSOME -->
<label class="genes-filter__field">
  <span class="genes-filter__label">Select Chromosome</span>
  <?php
  $chromosome_terms = _eicmt_gf_get_terms_ordered("chromosome");
  $chromosome_curr = isset($_GET["chromosome"]) ? (int) $_GET["chromosome"] : 0;
  $chromosome_counts = $eic_facets["tax"]["chromosome"] ?? [];
  ?>
  <select name="chromosome" id="gf-chromosome" class="genes-filter__select">
    <option value="0"<?php selected(
        $chromosome_curr,
        0
    ); ?>>By All Chromosomes</option>
    <?php foreach ($chromosome_terms as $term):

        $tid = is_object($term)
            ? (int) $term->term_id
            : (int) ($term["term_id"] ?? 0);
        $tobj = $tid ? get_term($tid, "chromosome") : null;
        $tname = $tobj && !is_wp_error($tobj) ? (string) $tobj->name : "";
        ?>
      <option
        value="<?php echo $tid; ?>"
        data-facet-tax="chromosome"
        data-facet-term="<?php echo $tid; ?>"
        data-facet-label="<?php echo esc_attr($tname); ?>"
        <?php selected($chromosome_curr, $tid); ?>
      >
        <?php echo esc_html(
            $tname . _eicmt_gf_count_suffix($chromosome_counts, $tid)
        ); ?>
      </option>
    <?php
    endforeach; ?>
  </select>
</label>

<!-- GENE GROUP FLAGS -->
<fieldset class="genes-filter__field genes-filter__field--flags">
  <legend class="genes-filter__label">Gene Groups</legend>

  <label class="genes-filter__check">
    <input
      type="checkbox"
      class="genes-filter__checkbox"
      name="mito"
      value="1"
      <?php checked(!empty($_GET["mito"])); ?>
    />
    <span data-facet-flag="mito" data-facet-label="Mitochondrial Involvement">
      Mitochondrial Involvement (<?php echo (int) ($eic_facets["flags"]["mito"] ?? 0); ?>)
    </span>
  </label>

  <label class="genes-filter__check">
    <input
      type="checkbox"
      class="genes-filter__checkbox"
      name="ars"
      value="1"
      <?php checked(!empty($_GET["ars"])); ?>
    />
    <span data-facet-flag="ars" data-facet-label="ARS Genes">
      ARS Genes (<?php echo (int) ($eic_facets["flags"]["ars"] ?? 0); ?>)
    </span>
  </label>

  <label class="genes-filter__check">
    <input
      type="checkbox"
      class="genes-filter__checkbox genes-filter__checkbox--exclusive"
      name="unknown"
      value="1"
      <?php checked(!empty($_GET["unknown"])); ?>
    />
    <span data-facet-flag="unknown" data-facet-label="Unknown Gene">
      Unknown Gene (<?php echo (int) ($eic_facets["flags"]["unknown"] ?? 0); ?>)
    </span>
  </label>
</fieldset>

<!-- VARIANT MECHANISM (single-value field, OR facet) -->
<fieldset class="genes-filter__field genes-filter__field--flags">
  <legend class="genes-filter__label">Variant Mechanism</legend>
  <?php
  $eic_mech_facets = [
      "mech_lof" => "Loss of Function (LoF)",
      "mech_dn" => "Dominant-Negative",
      "mech_gof" => "Toxic Gain of Function (GoF)",
      "mech_complex" => "Complex",
      "mech_unknown" => "Unknown",
  ];
  foreach ($eic_mech_facets as $mkey => $mlabel): ?>
    <label class="genes-filter__check">
      <input
        type="checkbox"
        class="genes-filter__checkbox"
        name="<?php echo esc_attr($mkey); ?>"
        value="1"
        <?php checked(!empty($_GET[$mkey])); ?>
      />
      <span data-facet-flag="<?php echo esc_attr(
          $mkey
      ); ?>" data-facet-label="<?php echo esc_attr($mlabel); ?>">
        <?php echo esc_html(
            $mlabel
        ); ?> (<?php echo (int) ($eic_facets["flags"][$mkey] ?? 0); ?>)
      </span>
    </label>
  <?php endforeach; ?>
</fieldset>

<!-- SEARCH -->
<label class="genes-filter__field genes-filter__field--search">
  <span class="genes-filter__label">Search by Subtype, Gene, Publication Author, Year of Discovery</span>
  <input
    type="search"
    name="qs"
    value="<?php echo esc_attr($search_text); ?>"
    placeholder='ex: CMT2A, CMTDIG, PMP22, SORD, Shy, Zuchner, 1999, 2013...'
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
  <button type="submit" class="genes-filter__btn">BROWSE</button>
  <a class="genes-filter__link"
     href="<?php echo esc_url($reset_url); ?>"
     data-role="genes-reset">RESET</a>
</div>

<?php // Preserve other GET params (skip visible controls and gd_* we manage)
foreach ($_GET as $k => $v) {
    if (
        in_array(
            $k,
            [
                "cmt_type",
                "inheritance",
                "neuropathy",
                "chromosome",
                "qs",
                "gd_sort",
                "mito", // rendered as a checkbox above
                "ars", // rendered as a checkbox above
                "unknown", // rendered as a checkbox above
                "mech_lof", // rendered as a checkbox above
                "mech_dn", // rendered as a checkbox above
                "mech_gof", // rendered as a checkbox above
                "mech_complex", // rendered as a checkbox above
                "mech_unknown", // rendered as a checkbox above
                "gd_paged", // skip to prevent duplicate
            ],
            true
        )
    ) {
        continue;
    }
    if (is_scalar($v)) {
        printf(
            '<input type="hidden" name="%s" value="%s">',
            esc_attr($k),
            esc_attr($v)
        );
    }
} ?>

<noscript><button type="submit">Apply</button></noscript>

<input type="hidden" name="gd_paged" value="<?php echo isset($_GET["gd_paged"])
    ? esc_attr(wp_unslash($_GET["gd_paged"]))
    : "1"; ?>">
<input type="hidden" name="per_page" value="<?php echo esc_attr(
    $a["per_page"]
); ?>">

      </div>
    </div>

  </form>

</div>

<?php return ob_get_clean();
});
?>
