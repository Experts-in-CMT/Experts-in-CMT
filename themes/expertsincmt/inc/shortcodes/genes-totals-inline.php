<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  SHORTCODE: GENES TOTALS INLINE
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Provides a deploy-anywhere, context-free totals display
 *      for the Genes system
 *    - Counts published `subtype` posts to determine total
 *      subtypes
 *    - Counts unique `gene_symbol` values across those same
 *      subtype posts to determine total genes
 *    - Outputs a fixed, CSS-safe inline markup block
 *
 *  Constraints:
 *    - No AJAX dependency
 *    - No loop or query context
 *    - No filters or request inspection
 *    - Queries ONLY:
 *        1) Post type: `subtype`
 *        2) Field: `gene_symbol`
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Shortcode handler for [genes_totals_inline]
 *
 * @return string
 */
function eic_genes_totals_inline_shortcode() {

    // Query all published subtype posts
    $q = new WP_Query([
        "post_type"      => "subtype",
        "post_status"    => "publish",
        "posts_per_page" => -1,
        "fields"         => "ids",
        "no_found_rows"  => true,
    ]);

    if (empty($q->posts)) {
        return "";
    }

    // Subtype total
    $subtype_total = count($q->posts);

    // Collect unique gene symbols
    $gene_symbols = [];

    foreach ($q->posts as $post_id) {
        $symbol = get_field("gene_symbol", $post_id);

        if (!empty($symbol)) {
            $gene_symbols[strtoupper(trim($symbol))] = true;
        }
    }

    $gene_total = count($gene_symbols);

    if (!$subtype_total && !$gene_total) {
        return "";
    }

    // Most recent subtype modification = database "Updated" date.
    global $wpdb;
    $last_modified = $wpdb->get_var(
        "SELECT MAX(post_modified) FROM {$wpdb->posts}
         WHERE post_type = 'subtype' AND post_status = 'publish'"
    );
    $updated_label = $last_modified
        ? mysql2date("F j, Y", $last_modified)
        : "";

    ob_start();
    ?>
    <div class="genes-totals-inline" aria-live="polite">
        <span class="genes-totals-label">CMT. Curated.</span><br>
        <?php
        echo esc_html(
            eic_gl_plural($subtype_total, "Subtype")
            . " • "
            . eic_gl_plural($gene_total, "Gene")
        );
        ?>
        <br><span class="genes-totals-status">Currently Indexed</span>
        <?php if ($updated_label): ?>
            <br><span class="genes-totals-updated">Updated: <?php echo esc_html(
                $updated_label
            ); ?></span>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode("genes_totals_inline", "eic_genes_totals_inline_shortcode");
