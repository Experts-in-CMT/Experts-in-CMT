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
 *  DORSAL ROOT — SHARED PAGINATION RENDERER
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Single source of truth for the Dorsal Root pager markup.
 *    - Used by BOTH the initial shortcode render (dr-posts.php)
 *      and the AJAX endpoint (loop-endpoints.php) so the pager
 *      keeps its styling after an AJAX page swap.
 *
 *  Markup contract (must match the .wp-block-query-pagination
 *  CSS in main.css):
 *      <nav class="wp-block-query-pagination">
 *        <ul class="page-numbers">
 *          <li><a|span class="page-numbers ...">…</a|span></li>
 *          …
 *        </ul>
 *      </nav>
 *
 *  Each link href carries "#blog" so a no-JS click still lands
 *  on the blog section; dr-ajax.js intercepts the click, fetches
 *  the page, and manages the scroll itself.
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("eic_dr_render_pagination")) {
    /**
     * Build the Dorsal Root pager.
     *
     * @param int    $current     Current page number (1-based).
     * @param int    $total_pages Total number of pages.
     * @param string $base_url    Base permalink for the loop page.
     * @param array  $qs_params   Query args to preserve on each link
     *                            (already excluding dr_paged).
     * @return string Pager HTML, or "" when there is only one page.
     */
    function eic_dr_render_pagination(
        $current,
        $total_pages,
        $base_url,
        array $qs_params = []
    ) {
        $total_pages = max(1, (int) $total_pages);
        if ($total_pages <= 1) {
            return "";
        }

        $current = max(1, (int) $current);
        unset($qs_params["dr_paged"]);

        $page_url = function (int $n) use ($base_url, $qs_params) {
            $qs2 = $qs_params;
            $qs2["dr_paged"] = $n;
            return esc_url(add_query_arg($qs2, $base_url) . "#blog");
        };

        $items = [];

        if ($current > 1) {
            $items[] =
                '<li><a class="prev page-numbers" href="' .
                $page_url($current - 1) .
                '">« Prev</a></li>';
        } else {
            $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
        }

        $start = max(1, $current - 2);
        $stop = min($total_pages, $current + 2);

        if ($start > 1) {
            $items[] =
                '<li><a class="page-numbers" href="' .
                $page_url(1) .
                '">1</a></li>';
            if ($start > 2) {
                $items[] = '<li><span class="page-numbers dots">…</span></li>';
            }
        }

        for ($i = $start; $i <= $stop; $i++) {
            if ($i === $current) {
                $items[] =
                    '<li><span class="page-numbers current">' .
                    $i .
                    "</span></li>";
            } else {
                $items[] =
                    '<li><a class="page-numbers" href="' .
                    $page_url($i) .
                    '">' .
                    $i .
                    "</a></li>";
            }
        }

        if ($stop < $total_pages) {
            if ($stop < $total_pages - 1) {
                $items[] = '<li><span class="page-numbers dots">…</span></li>';
            }
            $items[] =
                '<li><a class="page-numbers" href="' .
                $page_url($total_pages) .
                '">' .
                $total_pages .
                "</a></li>";
        }

        if ($current < $total_pages) {
            $items[] =
                '<li><a class="next page-numbers" href="' .
                $page_url($current + 1) .
                '">Next »</a></li>';
        } else {
            $items[] = '<li><span class="next page-numbers">Next »</span></li>';
        }

        return '<nav class="wp-block-query-pagination"><ul class="page-numbers">' .
            implode("", $items) .
            "</ul></nav>";
    }
}
