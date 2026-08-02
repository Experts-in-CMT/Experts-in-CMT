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
 *  DORSAL ROOT LOOP (Shortcode)
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Renders the Dorsal Root post loop on the static /dorsal-root
 *      page using the shortcode [dr_posts].
 *    - Provides DR-specific card markup while maintaining
 *      full structural parity with Genes and Glossary loops.
 *    - Pagination, sort, filter, and search behavior are handled
 *      by dr-ajax.js and the DR AJAX endpoint.
 *
 *  Notes:
 *    - Glossary DOES NOT use a standalone fragment file
 *      (markup is rendered 100% inside this file)
 *    - AJAX endpoint captures the full #results wrapper from
 *      this shortcode output for swap-in behavior
 *
 *  Shortcode:
 *      [dr_posts per_page="12" category_name=""]
 *
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Shared DR search/category constraint builder.
 *
 * Applies the canonical Dorsal Root search semantics to a set of
 * WP_Query args. Used by BOTH the [dr_posts] shortcode (page load)
 * and the AJAX endpoint (inc/ajax/loop-endpoints.php) so the two
 * renders can never diverge.
 *
 *  - dr_cat only  → constrain to that category
 *  - qs only      → union(text OR taxonomy term-name matches)
 *  - both         → intersection( category ∩ union )
 *
 * @param array      $args            Base WP_Query args (modified and returned).
 * @param string     $qs              Search text ("" for none).
 * @param array|null $dr_cat_resolved Result of eic_resolve_tax_field(), or null.
 * @return array Modified query args.
 */
if (!function_exists("eic_dr_apply_search_filters")) {
    function eic_dr_apply_search_filters(
        array $args,
        string $qs,
        ?array $dr_cat_resolved
    ): array {
        $dr_cat_active = $dr_cat_resolved !== null;

        if ($qs !== "") {
            // A) Text matches (title, excerpt, content via 's')
            $text_ids = get_posts([
                "post_type" => "post",
                "post_status" => "publish",
                "s" => $qs,
                "fields" => "ids",
                "posts_per_page" => -1,
                "no_found_rows" => true,
            ]);

            // B) Taxonomy matches (category + post_tag whose TERM NAMES contain $qs)
            $cat_ids = get_terms([
                "taxonomy" => "dorsal-root",
                "search" => $qs,
                "fields" => "ids",
                "hide_empty" => false,
            ]);
            $tag_ids = get_terms([
                "taxonomy" => "post_tag",
                "search" => $qs,
                "fields" => "ids",
                "hide_empty" => false,
            ]);

            $tax_post_ids = [];
            if (!is_wp_error($cat_ids) && !empty($cat_ids)) {
                $tax_post_ids = array_merge(
                    $tax_post_ids,
                    get_posts([
                        "post_type" => "post",
                        "post_status" => "publish",
                        "fields" => "ids",
                        "posts_per_page" => -1,
                        "no_found_rows" => true,
                        "tax_query" => [
                            [
                                "taxonomy" => "dorsal-root",
                                "field" => "term_id",
                                "terms" => $cat_ids,
                                "include_children" => true,
                                "operator" => "IN",
                            ],
                        ],
                    ])
                );
            }
            if (!is_wp_error($tag_ids) && !empty($tag_ids)) {
                $tax_post_ids = array_merge(
                    $tax_post_ids,
                    get_posts([
                        "post_type" => "post",
                        "post_status" => "publish",
                        "fields" => "ids",
                        "posts_per_page" => -1,
                        "no_found_rows" => true,
                        "tax_query" => [
                            [
                                "taxonomy" => "post_tag",
                                "field" => "term_id",
                                "terms" => $tag_ids,
                                "operator" => "IN",
                            ],
                        ],
                    ])
                );
            }

            // C) Union the sets (text ∪ taxonomy)
            $union_ids = array_unique(array_merge($text_ids, $tax_post_ids));

            if ($dr_cat_active) {
                // D) Category-led intersection: category ∩ union
                $cat_only_ids = get_posts([
                    "post_type" => "post",
                    "post_status" => "publish",
                    "fields" => "ids",
                    "posts_per_page" => -1,
                    "no_found_rows" => true,
                    "tax_query" => [
                        [
                            "taxonomy" => "dorsal-root",
                            "field" => $dr_cat_resolved["field"],
                            "terms" => [$dr_cat_resolved["value"]],
                            "include_children" => true,
                            "operator" => "IN",
                        ],
                    ],
                ]);
                $final_ids = array_values(
                    array_intersect($union_ids, $cat_only_ids)
                );
                $args["post__in"] = !empty($final_ids) ? $final_ids : [0];
            } else {
                // E) Only qs: use union directly
                $args["post__in"] = !empty($union_ids) ? $union_ids : [0];
            }
        } elseif ($dr_cat_active) {
            // F) Only category selected — native category filter
            $args["tax_query"][] = [
                "taxonomy" => "dorsal-root",
                "field" => $dr_cat_resolved["field"],
                "terms" => [$dr_cat_resolved["value"]],
                "include_children" => true,
                "operator" => "IN",
            ];
        }

        // Hide posts flagged "Hide From Page" (ACF dr_hide_from_page) from the
        // loop and its AJAX endpoint. Per-post toggle; see
        // inc/acf/dr-visibility-fields.php for the field and eic_dr_hidden_ids().
        $hidden = function_exists("eic_dr_hidden_ids")
            ? eic_dr_hidden_ids("dr_hide_from_page")
            : [];
        if (!empty($args["post__in"])) {
            $args["post__in"] = array_values(
                array_diff($args["post__in"], $hidden)
            );
            if (empty($args["post__in"])) {
                $args["post__in"] = [0];
            }
        } elseif (!empty($hidden)) {
            $args["post__not_in"] = array_merge(
                isset($args["post__not_in"]) ? (array) $args["post__not_in"] : [],
                $hidden
            );
        }

        return $args;
    }
}

add_shortcode("dr_posts", function ($atts = []) {
    // ================================
    // SHORTCODE PARAMS
    // ================================
    $a = shortcode_atts(
        [
            "per_page" => 12,
            "category_name" => "",
        ],
        $atts,
        "dr_posts"
    );

    // ----------------------------
    // DR Search: read `qs` (search text)
    // ----------------------------
    $qs = isset($_GET["qs"]) ? trim((string) wp_unslash($_GET["qs"])) : "";

    // ----------------------------
    // DR Filter: selected category
    // ----------------------------
    $dr_cat_resolved = isset($_GET["dr_cat"])
        ? eic_resolve_tax_field($_GET["dr_cat"], "dorsal-root")
        : null;
    $dr_cat_active = $dr_cat_resolved !== null;

    // ================================
    // QUERY PARAMS (GET)
    // ================================
    $paged = isset($_GET["dr_paged"]) ? max(1, (int) $_GET["dr_paged"]) : 1;
    $sort = isset($_GET["dr_sort"]) ? sanitize_key($_GET["dr_sort"]) : "";

    // ================================
    // BASE QUERY ARGS
    // ================================
    $args = [
        "post_type" => "post",
        "post_status" => "publish",
        "posts_per_page" => max(1, (int) $a["per_page"]),
        "paged" => $paged,
        "ignore_sticky_posts" => true,
        "orderby" => "date",
        "order" => "DESC",
    ];

    if (!empty($a["category_name"])) {
        $args["category_name"] = sanitize_title($a["category_name"]);
    }

    // ================================
    // SORTING LOGIC
    // ================================
    switch ($sort) {
        case "title_az":
            $args["orderby"] = ["title" => "ASC"];
            break;
        case "title_za":
            $args["orderby"] = ["title" => "DESC"];
            break;
        case "oldest":
            $args["orderby"] = ["date" => "ASC"];
            break;
        case "newest":
            $args["orderby"] = ["date" => "DESC"];
            break;
    }

    // ================================
    // DR Search + Category Filter (combo)
    // Shared with the AJAX endpoint via eic_dr_apply_search_filters()
    // so page-load and AJAX renders always agree.
    // ================================
    $args = eic_dr_apply_search_filters($args, $qs, $dr_cat_resolved);

    $q = new WP_Query($args);

    // ================================
    // TOOLBAR
    // ================================
    $anchor = "results";
    $base = strtok($_SERVER["REQUEST_URI"], "?");
    $action_url = esc_url($base . "#" . $anchor);
    $keep = $_GET;
    unset($keep["dr_paged"]);
    $clear_params = $keep;
    unset($clear_params["dr_sort"]);
    $sort_clear_url =
        esc_url(
            $base . ($clear_params ? "?" . http_build_query($clear_params) : "")
        ) .
        "#" .
        $anchor;

    ob_start();
    ?>


        <div class="genes-sort genes-sort--results">
            <form class="genes-sort__form" method="get" action="<?php echo $action_url; ?>">
                <label class="genes-sort__label" for="dr_sort">Sort by</label>
                <select id="dr_sort" name="dr_sort" class="genes-sort__select">
                    <option value=""         <?php selected(
                        $sort,
                        ""
                    ); ?>>Default</option>
                    <option value="title_az" <?php selected(
                        $sort,
                        "title_az"
                    ); ?>>Title A–Z</option>
                    <option value="title_za" <?php selected(
                        $sort,
                        "title_za"
                    ); ?>>Title Z–A</option>
                    <option value="oldest"   <?php selected(
                        $sort,
                        "oldest"
                    ); ?>>Oldest to Newest</option>
                    <option value="newest"   <?php selected(
                        $sort,
                        "newest"
                    ); ?>>Newest to Oldest</option>
                </select>

                <a class="genes-sort__clear" href="<?php echo $sort_clear_url; ?>">CLEAR</a>

                <?php foreach ($keep as $k => $v) {
                    if (in_array($k, ["dr_sort", "dr_paged"], true)) {
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
                <noscript><button type="submit" class="genes-sort__btn">Apply</button></noscript>
            </form>
        </div>


    <script>
document.addEventListener('DOMContentLoaded', function () {
    // --- DR AJAX safeguard: prevent double handling if dr-ajax.js is active
    if (window.DR_AJAX) return;

    const form = document.querySelector('.genes-sort__form');
    if (!form) return;

    // Sort change → update URL, reload, scroll to results
    form.addEventListener('change', function (e) {
        if (e.target.name !== 'dr_sort') return;
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        params.delete('dr_paged');
        const val = e.target.value;
        if (val) {
            params.set('dr_sort', val);
        } else {
            params.delete('dr_sort');
        }
        const newUrl =
            window.location.pathname +
            (params.toString() ? '?' + params.toString() : '') +
            '#results';
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
    });

    // CLEAR button → strip params, reload
    const clearBtn = form.querySelector('.genes-sort__clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const params = new URLSearchParams(window.location.search);
            params.delete('dr_sort');
            params.delete('dr_paged');
            const newUrl =
                window.location.pathname +
                (params.toString() ? '?' + params.toString() : '') +
                '#results';
            window.history.replaceState(null, '', newUrl);
            window.location.reload();
        });
    }
});
</script>

<!-- ===============================
     AJAX WRAPPER (for future reloads)
     =============================== -->
<div id="dr-results-root" data-loop-root="dr" aria-live="polite">


    <!-- ===============================
         RESULTS WRAPPER
         =============================== -->
    <div id="results" class="dr-blog" style="scroll-margin-top:100px;">

    <?php
    // ================================
    // DR-POSTS CARD RENDERING BLOCK
    // ================================

    $items = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $items[] = eic_dr_render_list_item(get_the_ID());
        }
        wp_reset_postdata();
    }
    ?>

    <?php if (empty($items)): ?>
        <div id="dr-no-results" class="dr-list__empty">
            <p>No posts found. Try adjusting your search.</p>
        </div>
    <?php else: ?>
        <div class="dr-list">
            <?php echo implode("", $items); ?>
        </div>
    <?php endif; ?>

    <?php
    // ================================
    // PAGINATION — shared renderer (see inc/content/loops/dr-pagination.php).
    // The AJAX endpoint calls the same function, so the pager markup and
    // styling stay identical across page swaps.
    // ================================
    $base_url =
        get_permalink(get_queried_object_id()) ?: home_url("/dorsal-root/");
    $qs_params = $_GET;
    unset($qs_params["dr_paged"]);

    echo eic_dr_render_pagination(
        $paged,
        $q->max_num_pages,
        $base_url,
        $qs_params
    );
    ?>

    </div><!-- /#results -->
</div><!-- /#dr-results-root -->


<?php return ob_get_clean();
});
