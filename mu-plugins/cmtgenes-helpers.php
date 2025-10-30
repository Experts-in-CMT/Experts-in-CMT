<?php
/**
 * Experts in CMT — Genes DB helpers (counts/totals + facet counts)
 * Path: /wp-content/mu-plugins/cmtgenes-helpers.php
 *
 * NOTE: This file powers:
 *  - Inline totals (subtypes/genes/unknown)
 *  - Facet counts per taxonomy
 *  - Query ID caching with content-change invalidation
 */

/* ============================================================
   SIMPLE TEXT HELPERS
   ============================================================ */
if (!function_exists('eic_gl_plural')) {
  function eic_gl_plural($n, $singular, $plural = null) {
    $plural = $plural ?? ($singular . 's');
    return ($n == 1) ? "$n $singular" : "$n $plural";
  }
}

/* ============================================================
   CACHE VERSIONING — BUST TRANSIENTS ON CONTENT CHANGES
   - eic_gl_ids_version(): reads numeric version from options
   - eic_gl_bump_ids_version(): bump version when subtype changes
   - Hooks on save/trashed/untrashed/deleted for 'subtype'
   ============================================================ */
if (!function_exists('eic_gl_ids_version')) {
  function eic_gl_ids_version(): int {
    return (int) get_option('eic_gl_ids_version', 1);
  }
}

if (!function_exists('eic_gl_bump_ids_version')) {
  function eic_gl_bump_ids_version($post_id) {
    if (get_post_type($post_id) !== 'subtype') return;
    $v = eic_gl_ids_version() + 1;
    // autoload=false to avoid polluting options autoload
    update_option('eic_gl_ids_version', $v, false);
  }
}
// Bust caches whenever 'subtype' content changes
add_action('save_post',       'eic_gl_bump_ids_version', 10, 1);
add_action('trashed_post',    'eic_gl_bump_ids_version', 10, 1);
add_action('untrashed_post',  'eic_gl_bump_ids_version', 10, 1);
add_action('deleted_post',    'eic_gl_bump_ids_version', 10, 1);

/* ============================================================
   REQUEST SIGNATURE (FOR CACHING)
   - Only the filters we honor + a global cache 'ver' to bust on changes
   - Accepts $overrides to neutralize one filter when computing facet counts
   ============================================================ */
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
      // IMPORTANT: bump this when subtype content changes so transients invalidate
      'ver'         => eic_gl_ids_version(),
    ];
    // Allow temporary exclusions (e.g., when counting a specific taxonomy)
    foreach ($overrides as $k => $v) $sig[$k] = $v;

    return md5(wp_json_encode($sig));
  }
}

/* ============================================================
   BASE QUERY ARGUMENTS (MVP LOOP PARITY)
   - CPT: subtype
   - Tax filters: cmt_type, inheritance, neuropathy, chromosome (term_id)
   - Search: qs across eic_gl_search_meta_fields()
   - Back-compat for gd_q (other legacy params intentionally ignored)
   - Returns a light 'ids' query by default
   ============================================================ */
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

    // Fallback list if theme function not present yet
    if (!function_exists('eic_gl_search_meta_fields')) {
      function eic_gl_search_meta_fields() {
        // NOTE: Using 'gene_symbol' as canonical meta key
        return ['type_classification','subtype','gene_symbol','alternate_gene_1','alternate_gene_2','alternate_gene_3','year_of_discovery'];
      }
    }

    $meta_query = ['relation' => 'OR'];
    if ($qs !== '') {
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
      'fields'                 => 'ids',    // lightweight IDs-only
      'no_found_rows'          => true,     // we compute totals separately
      'update_post_term_cache' => false,
      'update_post_meta_cache' => false,
    ];

    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;
    if ($qs !== '')           $args['meta_query'] = $meta_query;

    // Allow caller overrides
    foreach ($extra as $k => $v) $args[$k] = $v;
    return $args;
  }
}

/* ============================================================
   CURRENT MATCHING IDS (CACHED + DEDUPED)
   - Returns unique post IDs matching current filters (or overrides)
   - Transient key includes request signature + version
   - Dev bypass via ?nocache=1 for instant refresh while iterating
   ============================================================ */
if (!function_exists('eic_gl_current_post_ids')) {
  function eic_gl_current_post_ids(array $sig_overrides = []) {
    $key = 'eic_gl_ids_' . eic_gl_request_signature($sig_overrides);

    // DEV BYPASS: add ?nocache=1 to force refresh (handy while building)
    if (!isset($_GET['nocache'])) {
      $ids = get_transient($key);
      if ($ids !== false) return $ids;
    }

    $q   = new WP_Query(eic_gl_build_base_query_args());
    $ids = $q->posts ?: [];

    // Normalize + de-dupe to avoid JOIN-inflated counts
    $ids = array_values(array_unique(array_map('intval', $ids)));

    // Short TTL during development; extend for production if desired
    set_transient($key, $ids, 60); // 60 seconds while developing
    return $ids;
  }
}

/* ============================================================
   FACET COUNTS FOR A TAXONOMY (STANDARD FACETING)
   - Counts are computed with the target taxonomy neutralized
   - Returns: [term_id => count]
   ============================================================ */
if (!function_exists('eic_gl_counts_for_tax')) {
  function eic_gl_counts_for_tax($taxonomy) {
    static $rev_map = [
      'cmt_type'    => 'cmt_type',
      'inheritance' => 'inheritance',
      'neuropathy'  => 'neuropathy',
      'chromosome'  => 'chromosome',
    ];
    if (!isset($rev_map[$taxonomy])) return [];

    $param = $rev_map[$taxonomy];

    // Build IDs with this taxonomy neutralized; cache key separates via overrides
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

/* ============================================================
   UI LABEL HELPER FOR OPTIONS ("Name (n)")
   ============================================================ */
if (!function_exists('eic_gl_option_label_with_count')) {
  function eic_gl_option_label_with_count($term, $counts) {
    $n = isset($counts[$term->term_id]) ? (int) $counts[$term->term_id] : 0;
    return sprintf('%s (%d)', $term->name, $n);
  }
}

/* ============================================================
   DISTINCT GENE COUNT (EXCLUDING 'UNKNOWN')
   - Canonical meta key: gene_symbol
   - Normalizes with TRIM + UPPER
   ============================================================ */
if (!function_exists('eic_gl_count_unique_genes')) {
  function eic_gl_count_unique_genes(array $ids) {
    if (empty($ids)) return 0;
    global $wpdb;
    $ids_csv = implode(',', array_map('intval', $ids));
    $pm = $wpdb->postmeta;
    $sql = "SELECT COUNT(DISTINCT UPPER(TRIM(pm.meta_value)))
            FROM $pm pm
            WHERE pm.post_id IN ($ids_csv)
              AND pm.meta_key = 'gene_symbol'
              AND UPPER(TRIM(pm.meta_value)) <> 'UNKNOWN'";
    return (int) $wpdb->get_var($sql);
  }
}

/* ============================================================
   COUNT SUBTYPES EXPLICITLY FLAGGED AS UNKNOWN GENE
   - ACF true/false: meta_key 'unknown_gene' = '1'
   ============================================================ */
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

/* ============================================================
   INLINE TOTALS SHORTCODE
   - Usage: [genes_totals_inline]
   - Uses current filters (neutralized for grand totals in UI header)
   - Outputs: "CMT. Curated." + "X Subtypes • Y Genes • Z Subtypes with Unknown Genes"
   ============================================================ */
add_shortcode('genes_totals_inline', function () {
    // Neutralize all filters for the grand total line
    $ids     = eic_gl_current_post_ids(['cmt_type'=>0,'inheritance'=>0,'neuropathy'=>0,'chromosome'=>0,'qs'=>'']);

    // Belt & suspenders: ensure de-dupe even if upstream changed
    $ids     = array_values(array_unique(array_map('intval', $ids)));

    $total   = count($ids);
    $uniq    = eic_gl_count_unique_genes($ids);
    $unknown = eic_gl_count_unknown_genes($ids);

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

/* ============================================================
   ACF VALIDATION — UNIQUE 'subtype' FIELD WITHIN CPT
   - Enforces: the ACF 'subtype' field must be unique for post_type=subtype
   ============================================================ */
add_filter('acf/validate_value/name=subtype', function ($valid, $value, $field, $input) {
    if ($valid !== true) return $valid; // honor other validation
    $value = trim((string)$value);
    if ($value === '') return 'Subtype is required.';

    $post_id = isset($_POST['post_ID']) ? (int)$_POST['post_ID'] : 0;
    if ($post_id && get_post_type($post_id) !== 'subtype') return $valid;

    $dupe = new WP_Query([
        'post_type'      => 'subtype',
        'post_status'    => ['publish','pending','draft','future','private'],
        'posts_per_page' => 1,
        'post__not_in'   => $post_id ? [$post_id] : [],
        'meta_query'     => [[ 'key' => 'subtype', 'value' => $value, 'compare' => '=' ]], // case-insensitive on default collations
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    if ($dupe->have_posts()) {
        return 'A record for this Subtype already exists. Please update the existing record instead of creating a duplicate.';
    }
    return true;
}, 20, 4);
