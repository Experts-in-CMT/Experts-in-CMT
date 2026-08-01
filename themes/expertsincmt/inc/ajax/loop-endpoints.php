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
 *  DORSAL ROOT LOOP AJAX ENDPOINT
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Handles AJAX requests triggered by dr-ajax.js
 *    - Returns ONLY the rendered inner-loop HTML for swap into
 *      #dr-results-root
 *    - Maintains full parity with Genes and Glossary stacks
 *
 *  Notes:
 *    - DR DOES NOT use a standalone fragment file.
 *      The loop markup is rendered directly inside this endpoint.
 *      (This matches Glossary’s architecture.)
 *
 *    - Accepts unified GET/POST intake:
 *        qs        (string)  search text
 *        dr_cat    (int)     taxonomy filter
 *        dr_sort   (string)  sort value
 *        dr_paged  (int)     pagination
 *        per_page  (int)     shortcode override
 *
 *    - Preserves URL state cleanly (slug + query params)
 *    - Output must wrap inner content in the #results container
 *      so the AJAX script can replace it seamlessly.
 * ============================================================
 */

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

add_action("wp_ajax_dr_get_posts", "eic_ajax_dr_get_posts");
add_action("wp_ajax_nopriv_dr_get_posts", "eic_ajax_dr_get_posts");

function eic_ajax_dr_get_posts()
{
    check_ajax_referer("dr_ajax_nonce", "nonce");

    // Params
    $qs = isset($_POST["qs"])
        ? sanitize_text_field(wp_unslash($_POST["qs"]))
        : "";
    $dr_cat = isset($_POST["dr_cat"])
        ? sanitize_text_field(wp_unslash($_POST["dr_cat"]))
        : "";
    $dr_sort = isset($_POST["dr_sort"])
        ? sanitize_text_field(wp_unslash($_POST["dr_sort"]))
        : "";
    $paged = isset($_POST["dr_paged"]) ? max(1, (int) $_POST["dr_paged"]) : 1;
    $per = isset($_POST["per_page"])
        ? min(48, max(1, (int) $_POST["per_page"]))
        : 12;

    // Query (match dr-posts.php)
    $args = [
        "post_type" => "post",
        "post_status" => "publish",
        "posts_per_page" => $per,
        "paged" => $paged,
        "ignore_sticky_posts" => true,
    ];

    // Search + category — canonical semantics shared with dr-posts.php
    // (union of text and term-name matches, intersected with category),
    // via eic_dr_apply_search_filters() so AJAX and page-load renders
    // can never diverge. This also keeps the list consistent with the
    // union-based facet counts from eic_dr_facet_counts().
    $dr_resolved = eic_resolve_tax_field($dr_cat, "dorsal-root");
    $args = eic_dr_apply_search_filters($args, $qs, $dr_resolved);

    // Sort
    switch ($dr_sort) {
        case "title_az":
            $args["orderby"] = "title";
            $args["order"] = "ASC";
            break;
        case "title_za":
            $args["orderby"] = "title";
            $args["order"] = "DESC";
            break;
        case "oldest":
            $args["orderby"] = "date";
            $args["order"] = "ASC";
            break;
        case "newest":
        default:
            $args["orderby"] = "date";
            $args["order"] = "DESC";
            break;
    }

    $q = new WP_Query($args);

    // ---- Render fragment (IDENTICAL structure to dr-posts.php inner) ----
    ob_start();

    echo '<div id="results" class="dr-blog" style="scroll-margin-top:100px;">';

    $items = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $items[] = eic_dr_render_list_item(get_the_ID());
        }
        wp_reset_postdata();
    }

    if (empty($items)) {
        echo '<div id="dr-no-results" class="dr-list__empty"><p>No posts found. Try adjusting your search.</p></div>';
    } else {
        echo '<div class="dr-list">' . implode("", $items) . "</div>";
    }

    // Pagination — shared renderer (identical markup to dr-posts.php so the
    // pager keeps its styling after an AJAX page swap). Preserve the active
    // search/category/sort filters on each link.
    $dr_qs_params = [];
    if ($qs !== "") {
        $dr_qs_params["qs"] = $qs;
    }
    if ($dr_cat !== "") {
        $dr_qs_params["dr_cat"] = $dr_cat;
    }
    if ($dr_sort !== "") {
        $dr_qs_params["dr_sort"] = $dr_sort;
    }
    echo eic_dr_render_pagination(
        max(1, $paged),
        max(1, $q->max_num_pages),
        home_url("/dorsal-root/"),
        $dr_qs_params
    );

    echo "</div>"; // #results

    $html = ob_get_clean();

    // Facet counts ride along so the category selector can refresh
    // in place; the filter form sits outside the swapped root.
    $facets = function_exists("eic_dr_facet_counts")
        ? eic_dr_facet_counts($_POST)
        : null;

    wp_send_json_success([
        "html" => $html,
        "facets" => $facets,
    ]);
}
