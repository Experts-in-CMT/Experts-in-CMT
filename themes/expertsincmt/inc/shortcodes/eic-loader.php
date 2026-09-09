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
 *  EIC Loader: the platform-wide loading indicator
 * ------------------------------------------------------------
 *  The DNA helix from the entry cards and heroes, drawing itself
 *  in and out, stroke by stroke. Inline SVG in currentColor, so it
 *  takes the color of wherever it sits, needs no image file, and
 *  has no background. Styled by assets/css/eic-loader.css.
 *
 *  Use it wherever a surface waits on data:
 *    PHP:       echo eic_loader();                 // visible
 *               echo eic_loader("", true);         // rendered hidden, for a script to reveal
 *    Shortcode: [eic_loader]
 *    JS:        el.removeAttribute("hidden") / el.setAttribute("hidden", "")
 *               (an SVG has no .hidden property; use the attribute)
 *
 *  Markup: <svg class="eic-loader" ...><path .../></svg>
 *  First used on the gene page's ClinVar Variants card.
 *
 *  Location: /inc/shortcodes/eic-loader.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("eic_loader")) {
    /**
     * @param string $class  Extra class(es) for placement.
     * @param bool   $hidden Render with the hidden attribute for a script to reveal.
     */
    function eic_loader(string $class = "", bool $hidden = false): string
    {
        return '<svg class="eic-loader' . ($class !== "" ? " " . esc_attr($class) : "") . '" ' .
            'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' .
            'aria-hidden="true"' . ($hidden ? " hidden" : "") . ">" .
            '<path d="M6 3c0 6 12 6 12 12M18 21c0-6-12-6-12-12M7 6h10M7 18h10" stroke-linecap="round"/>' .
            "</svg>";
    }
}

add_shortcode("eic_loader", function ($atts = []) {
    $atts = shortcode_atts(["class" => ""], $atts, "eic_loader");
    return eic_loader((string) $atts["class"]);
});
