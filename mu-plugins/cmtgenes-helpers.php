<?php
/**
 * Experts in CMT — Genes DB helpers (counts/totals + facet counts)
 * Path: /wp-content/mu-plugins/cmtgenes-helpers.php
 */

if (!function_exists('eic_gl_plural')) {
  function eic_gl_plural($n, $singular, $plural = null) {
    $plural = $plural ?? ($singular . 's');
    return ($n == 1) ? "$n $singular" : "$n $plural";
  }
}

/** Signature for caching: only the filters we honor (qs + four tax params) */
if (!function_exists('eic_gl_request_signature')) {
  function eic_gl_request_signature(array $overrides = []) {
    // Resolve legacy params into the canonical ones
    $qs = isset($_GET['qs']) ? (string) $_GET['qs'] : (isset($_GET['gd_q']) ? (string) $_GET['gd_q'] : '');
    $sig = [
      'qs'          => trim($qs),
      'cmt_type'    => isset($_GET['cmt_type'])    ? (int) $_GET['cmt_type']    : 0,
      'inheritance' => isset($_GET['inheritance']) ? (int) $_GET['inheritance'] : 0,
      'neuropathy'  => isset($_GET['neuropathy'])  ? (int) $_GET['neuropathy']  : 0,
      'chromosome'  => isset($_GET['chromosome'])  ? (int) $_GET['chromosome']  : 0,
    ];
    // Allow temporary exclusions (e.g., when counting a specific taxonomy)
    foreach ($overrides as $k => $v) $sig[$k] = $v;
    return md5(wp_json_encode($sig));
  }
}

/**
 * Build base args matching the MVP loop:
 * - CPT: subtype
 * - Tax filters: cmt_type, inheritance, neuropathy, chromosome (term_id)
 * - Search: qs across eic_gl_search_meta_fields()
 * - Back-compat for gd_q (we ignore other legacy params here on purpose)
 */
if (!function_exists('eic_gl_build_base_query_args')) {
  function eic_gl_build_base_query_args($extra = []) {
    $tax_map = [
      'cmt_type'    => 'cmt_type',
      'inheritance' => 'inheritance',
      'neuropathy'  => 'neuropathy',
      'chromosome'  => 'chromosome',
    ];

    $tax_query = ['relation' => 'AND'];
    foreach ($tax_map as $param => $taxonomy) {
      if (!empty($_GET[$param])) {
        $term_id = (int) $_GET[$param];
        if ($term_id) {
          $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => [$term_id],
          ];
        }
      }
    }

    // Search term (qs), with legacy gd_q fallback
    $qs_raw = isset($_GET['qs']) ? (string) $_GET['qs'] : (isset($_GET['gd_q']) ? (string) $_GET['gd_q'] : '');
    $qs = sanitize_text_field($qs_raw);
    $meta_query = ['relation' => 'OR'];
    if ($qs !== '') {
      if (!function_exists('eic_gl_search_meta_fields')) {
        // Fallback list if the theme’s function isn’t loaded yet
        function eic_gl_search_meta_fields() {
          return ['type_classification','subtype','gene','alternate_gene_1','alternate_gene_2','alternate_gene_3','year_of_discovery'];
        }
      }
      foreach (eic_gl_search_meta_fields() as $key) {
        $meta_query[] = [
          'key'     => $key,
          'value'   => $qs,
          'compare' => 'LIKE',
        ];
      }
    }

    $args = [
      'post_type'              => 'subtype',
      'post_status'            => 'publish',
      'fields'                 => 'ids',
      'no_found_rows'          => true,
      'update_post_term_cache' => false,
      'update_post_meta_cache' => false,
    ];

    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;
    if ($qs !== '')           $args['meta_query'] = $meta_query;

    // Allow caller overrides (rare)
    foreach ($extra as $k => $v) $args[$k] = $v;
    return $args;
  }
}

/** Current matching IDs (reused for totals and facet math). Cached briefly. */
if (!function_exists('eic_gl_current_post_ids')) {
  function eic_gl_current_post_ids(array $sig_overrides = []) {
    $key = 'eic_gl_ids_' . eic_gl_request_signature($sig_overrides);
    $ids = get_transient($key);
    if ($ids !== false) return $ids;

    $q   = new WP_Query(eic_gl_build_base_query_args());
    $ids = $q->posts ?: [];
    set_transient($key, $ids, 5 * MINUTE_IN_SECONDS);
    return $ids;
  }
}

/**
 * Facet counts for a taxonomy using the current filters
 * EXCLUDING that taxonomy’s own selection (standard faceting).
 * Returns: [term_id => count]
 */
if (!function_exists('eic_gl_counts_for_tax')) {
  function eic_gl_counts_for_tax($taxonomy) {
    static $rev_map = [
      'cmt_type'            => 'cmt_type',
      'inheritance'         => 'inheritance',
      'neuropathy'          => 'neuropathy',
      'chromosome'          => 'chromosome',
    ];
    if (!isset($rev_map[$taxonomy])) return [];

    $param = $rev_map[$taxonomy];

    // Build IDs with this taxonomy neutralized (override signature so cache separates)
    $ids = eic_gl_current_post_ids([$param => 0]);
    if (empty($ids)) return [];

    global $wpdb;
    $ids_csv = implode(',', array_map('intval', $ids));
    $tt = $wpdb->term_taxonomy;
    $tr = $wpdb->term_relationships;

    $sql  = "SELECT tt.term_id, COUNT(*) AS cnt
             FROM $tr tr
             INNER JOIN $tt tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id IN ($ids_csv) AND tt.taxonomy = %s
             GROUP BY tt.term_id";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $taxonomy));
    $out  = [];
    foreach ((array) $rows as $r) $out[(int) $r->term_id] = (int) $r->cnt;
    return $out;
  }
}

/** Compose "Name (n)" for option labels */
if (!function_exists('eic_gl_option_label_with_count')) {
  function eic_gl_option_label_with_count($term, $counts) {
    $n = isset($counts[$term->term_id]) ? (int) $counts[$term->term_id] : 0;
    return sprintf('%s (%d)', $term->name, $n);
  }
}

/** Distinct gene count, excluding 'Unknown' (case-insensitive) */
if (!function_exists('eic_gl_count_unique_genes')) {
  function eic_gl_count_unique_genes(array $ids) {
    if (empty($ids)) return 0;
    global $wpdb;
    $ids_csv = implode(',', array_map('intval', $ids));
    $pm = $wpdb->postmeta;
    // Normalize values for distinct: TRIM + UPPER; exclude literal 'UNKNOWN'
    $sql = "SELECT COUNT(DISTINCT UPPER(TRIM(pm.meta_value)))
            FROM $pm pm
            WHERE pm.post_id IN ($ids_csv)
              AND pm.meta_key = 'gene'
              AND UPPER(TRIM(pm.meta_value)) <> 'UNKNOWN'";
    return (int) $wpdb->get_var($sql);
  }
}

/** Count subtypes explicitly flagged as Unknown Gene via ACF toggle */
if (!function_exists('eic_gl_count_unknown_genes')) {
  function eic_gl_count_unknown_genes(array $ids) {
    if (empty($ids)) return 0;
    global $wpdb;
    $ids_csv = implode(',', array_map('intval', $ids));
    $pm = $wpdb->postmeta;
    $sql = "SELECT COUNT(DISTINCT pm.post_id)
            FROM $pm pm
            WHERE pm.post_id IN ($ids_csv)
              AND pm.meta_key = 'unknown_gene'
              AND pm.meta_value = '1'";
    return (int) $wpdb->get_var($sql);
  }
}

// [genes_totals_inline] — show grand totals anywhere (centered, curated aesthetic)
add_shortcode('genes_totals_inline', function () {
  $ids      = eic_gl_current_post_ids(['cmt_type'=>0,'inheritance'=>0,'neuropathy'=>0,'chromosome'=>0,'qs'=>'']);
  $total    = count($ids);
  $uniq     = eic_gl_count_unique_genes($ids);
  $unknown  = eic_gl_count_unknown_genes($ids);

  ob_start(); ?>
  <div class="genes-totals-inline" aria-live="polite">
    <span class="genes-totals-label">CMT. Curated.</span><br>
    <?php
      echo esc_html( eic_gl_plural($total, 'Subtype') );
      echo ' • ';
      echo esc_html( eic_gl_plural($uniq, 'Gene') );
      if ($unknown > 0) {
        echo ' • ';
        echo esc_html( eic_gl_plural($unknown, 'Subtype with an Unknown Gene', 'Subtypes with Unknown Genes') );
      }
    ?>
  </div>
  <?php
  return ob_get_clean();
});

