<?php
/**
 * DR Posts Loop (Shortcode)
 * Glossary-parity structure; DR-specific card layout.
 *
 * Shortcode: [dr_posts]
 *
 * @package ExpertsInCMT
 */

if (!defined("ABSPATH")) {
    exit();
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
    $dr_cat = isset($_GET["dr_cat"]) ? (int) $_GET["dr_cat"] : 0;

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
    //  - Category leads:
    //      • dr_cat only  → constrain to that category
    //      • qs only      → union(text OR taxonomy)
    //      • both         → intersection( category ∩ union )
    // ================================
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

        if ($dr_cat > 0) {
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
                        "field" => "term_id",
                        "terms" => [$dr_cat],
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
    } elseif ($dr_cat > 0) {
        // F) Only category selected — native category filter
        $args["tax_query"][] = [
            "taxonomy" => "dorsal-root",
            "field" => "term_id",
            "terms" => [$dr_cat],
            "include_children" => true,
            "operator" => "IN",
        ];
    }

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
        const form = document.querySelector('.genes-sort__form');
        if (!form) return;

        // Sort change → update URL, reload, scroll to results
        form.addEventListener('change', function (e) {
            if (e.target.name !== 'dr_sort') return;
            e.preventDefault();
            const params = new URLSearchParams(window.location.search);
            params.delete('dr_paged');
            const val = e.target.value;
            if (val) { params.set('dr_sort', val); } else { params.delete('dr_sort'); }
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + '#results';
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
                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + '#results';
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

    $cards = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) {

            $q->the_post();
            ob_start();
            ?>
            <article class="dr-card wp-block-post">
                <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()) {
                        the_post_thumbnail("large", [
                            "loading" => "lazy",
                            "decoding" => "async",
                        ]);
                    } ?>
                </a>

                <h2 class="wp-block-post-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>

                <div class="wp-block-post-date"><?php echo esc_html(
                    get_the_date()
                ); ?></div>

                <div class="wp-block-post-excerpt">
                    <?php echo esc_html(
                        wp_strip_all_tags(get_the_excerpt(), true)
                    ); ?>
                </div>

                <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Read More</a>
            </article>
            <?php $cards[] = trim(ob_get_clean());
        }
        wp_reset_postdata();
    }

    $rows = array_chunk($cards, 3);
    $total_rows = count($rows);
    ?>

    <?php if (empty($rows)): ?>
        <div id="genes-no-results" class="dr-row dr-row--empty"
             style="margin:0 auto 64px;display:flex;justify-content:center;align-items:flex-start;max-width:700px;width:100%;">
            <p style="font-size:1.1rem; color:#333; text-align:left;">
                No posts found.
            </p>
        </div>
    <?php endif; ?>

    <div class="dr-grid">
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $i => $row_items):

                $is_last = $i === $total_rows - 1;
                $count = count($row_items);
                ?>
                <div class="dr-row<?php echo $is_last
                    ? " dr-row--last"
                    : ""; ?>" <?php echo $is_last
    ? 'data-count="' . (int) $count . '"'
    : ""; ?>>
                    <?php echo implode("", $row_items); ?>
                </div>
            <?php
            endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    // ================================
    // PAGINATION (Genes-style)
    // ================================
    $total_pages = max(1, (int) $q->max_num_pages);
    if ($total_pages > 1) {
        $current = $paged;
        $base_url =
            get_permalink(get_queried_object_id()) ?: home_url("/dorsal-root/");
        $qs_params = $_GET;
        unset($qs_params["dr_paged"]);

        $page_url = function (int $n) use ($base_url, $qs_params) {
            $qs2 = $qs_params;
            $qs2["dr_paged"] = $n;
            return esc_url(add_query_arg($qs2, $base_url) . "#results");
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

        echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' .
            implode("", $items) .
            "</ul></nav>";
    }
    ?>

    </div><!-- /#results -->
</div><!-- /#dr-results-root -->


<?php return ob_get_clean();
});
