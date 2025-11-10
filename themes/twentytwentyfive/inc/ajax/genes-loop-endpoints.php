<?php
/**
 * ============================================================
 *  GENES LOOP AJAX ENDPOINT
 *  ------------------------------------------------------------
 *  Purpose:
 *    Responds to AJAX requests triggered by genes-ajax.js.
 *    Returns only the rendered inner fragment HTML for
 *    replacement inside #genes-results-root.
 * ============================================================
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   ===================== [ SECTION: HOOK REGISTRATION ] ========
   ============================================================ */

add_action('wp_ajax_genes_get_loop', 'eic_genes_loop_endpoint');
add_action('wp_ajax_nopriv_genes_get_loop', 'eic_genes_loop_endpoint');

/* ============================================================
   ===================== [ SECTION: ENDPOINT HANDLER ] =========
   ============================================================ */

function eic_genes_loop_endpoint() {
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'genes_ajax_nonce')) {
        wp_send_json_error('nonce_fail', 403);
    }

    // ============================================================
    // Build Query Args (mirrors Genes MVP logic)
    // ============================================================
    $paged    = isset($_POST['gd_paged']) ? intval($_POST['gd_paged']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 12;
    $search   = sanitize_text_field($_POST['qs'] ?? '');
    $sort     = isset($_POST['gd_sort']) ? sanitize_key($_POST['gd_sort']) : '';

    $args = [
        'post_type'      => 'subtype',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'title',
        'order'          => 'ASC',
        's'              => $search,
    ];

    /* ------------------------------------------------------------
       Taxonomy filters
       ------------------------------------------------------------ */
    $tax_query = ['relation' => 'AND'];
    $tax_keys  = ['cmt_type', 'inheritance', 'neuropathy', 'chromosome'];

    foreach ($tax_keys as $tax) {
        if (!empty($_POST[$tax]) && $_POST[$tax] !== '0') {
            $tax_query[] = [
                'taxonomy' => $tax,
                'field'    => 'term_id',
                'terms'    => (int) $_POST[$tax],
            ];
        }
    }

    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    /* ------------------------------------------------------------
       Canonical sort logic
       ------------------------------------------------------------ */
    $use_canonical_sort = !isset($_POST['gd_sort']) || $sort === '' || $sort === 'default';

    if ($use_canonical_sort) {
        $args['eic_genes_custom_sort'] = true;
        add_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10, 2);
    }

    /* ------------------------------------------------------------
       Execute query
       ------------------------------------------------------------ */
    error_log('GENES AJAX DEBUG PAYLOAD: ' . print_r($_POST, true));
    error_log('GENES AJAX FINAL ARGS: ' . print_r($args, true));

    $q = new WP_Query($args);

    /* ------------------------------------------------------------
       Cleanup filter (avoid leaking to other queries)
       ------------------------------------------------------------ */
    if ($use_canonical_sort) {
        remove_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10);
    }

    /* ------------------------------------------------------------
       Pass to fragment and output JSON
       ------------------------------------------------------------ */
    set_query_var('genes_args', $args);

    ob_start();
    get_template_part('inc/content/loops/partials/fragment-loop-genes-loop');
    $html = ob_get_clean();

    wp_reset_postdata();

    wp_send_json_success(['html' => $html]);
}
