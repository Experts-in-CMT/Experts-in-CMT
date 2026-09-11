<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * Shortcode: [gene_fields]
 * ------------------------------------------------------------
 * Renders the Gene field display template inside a Gutenberg
 * Shortcode block. Used exclusively on single Gene pages.
 * Mirrors [subtype_fields] file for file.
 *
 * Notes:
 *   - Loads templates/gene-fields-template.php
 *   - Silent fail with HTML comment if the template is missing
 *   - Only runs on is_singular('gene')
 */

// Prevent direct access
defined("ABSPATH") || exit();

function eic_gene_fields_shortcode()
{
    // Only render on single Gene posts
    if (!is_singular("gene")) {
        return "";
    }

    // Locate the display template
    $template = get_theme_file_path("/templates/gene-fields-template.php");

    if (!file_exists($template)) {
        return "<!-- gene-fields-template.php not found -->";
    }

    // Capture template output, marked for eic_shortcode_block_noautop() below
    ob_start();
    include $template;
    return EIC_NOAUTOP_MARK . ob_get_clean();
}

/*
 * Keep wpautop off the rendered page.
 *
 * The block-template renderer runs do_shortcode() before do_blocks(), so
 * by the time the Shortcode block renders, its content is already this
 * shortcode's full HTML, and the block's own renderer is wpautop($content).
 * wpautop then chunks that HTML at every block tag and leaves a stray </p>
 * wherever inline text precedes a block child: an empty paragraph with
 * margins at the top of every publication cell, a blank band under the
 * identifier grid, dead space between sections.
 *
 * A Shortcode block whose content carries the marker is returned as-is
 * from pre_render_block, which is the one hook that runs before the block's
 * renderer. Any shortcode can opt in by prefixing its output with the mark.
 */
const EIC_NOAUTOP_MARK = "<!--eic-noautop-->";

add_filter("pre_render_block", "eic_shortcode_block_noautop", 10, 2);
function eic_shortcode_block_noautop($pre, array $block)
{
    if (($block["blockName"] ?? "") !== "core/shortcode") {
        return $pre;
    }
    $html = (string) ($block["innerHTML"] ?? "");
    if (!str_contains($html, EIC_NOAUTOP_MARK)) {
        return $pre;
    }
    return str_replace(EIC_NOAUTOP_MARK, "", $html);
}
add_shortcode("gene_fields", "eic_gene_fields_shortcode");
