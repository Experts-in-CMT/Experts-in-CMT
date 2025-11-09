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

    // Start output buffering
    ob_start();

    /* --------------------------------------------------------
       Sanitize and prepare incoming POST vars
       -------------------------------------------------------- */
    $qs_raw = isset($_POST['qs']) ? sanitize_text_field($_POST['qs']) : '';
    $qs_all = strtolower(trim($qs_raw)) === 'all';
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 12;

    // Build taxonomy query from POSTed term IDs
    $tax_query = ['relation' => 'AND'];
    foreach (['cmt_type', 'inheritance', 'neuropathy', 'chromosome'] as $tax) {
        if (!empty($_POST[$tax])) {
            $tax_query[] = [
                'taxonomy' => $tax,
                'field'    => 'term_id',
                'terms'    => [intval($_POST[$tax])],
            ];
        }
    }
    if (count($tax_query) === 1) {
        $tax_query = [];
    }

    /* --------------------------------------------------------
       Push sanitized vars into query context for fragment
       -------------------------------------------------------- */
    $a = ['per_page' => $per_page];
    set_query_var('a', $a);
    set_query_var('tax_query', $tax_query);
    set_query_var('qs', $qs_raw);
    set_query_var('qs_all', $qs_all);

    /* --------------------------------------------------------
       Load Genes Loop fragment (query + markup)
       -------------------------------------------------------- */
    get_template_part('inc/content/loops/partials/fragment-loop-genes-loop');

    /* --------------------------------------------------------
       Capture + return clean JSON
       -------------------------------------------------------- */
    $html = trim(ob_get_clean());

    wp_send_json_success(['html' => $html]);
    wp_die();
}
