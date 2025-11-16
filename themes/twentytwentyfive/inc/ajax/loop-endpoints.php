<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
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
    $per = isset($_POST["per_page"]) ? max(1, (int) $_POST["per_page"]) : 12;

    // Query (match dr-posts.php)
    $args = [
        "post_type" => "post",
        "posts_per_page" => $per,
        "paged" => $paged,
        "ignore_sticky_posts" => true,
        "s" => $qs,
    ];

    // Category (taxonomy: dorsal-root)
    if ($dr_cat !== "") {
        $args["tax_query"] = [
            [
                "taxonomy" => "dorsal-root",
                "field" => "term_id",
                "terms" => (int) $dr_cat,
                "include_children" => true,
            ],
        ];
    }

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
          <?php echo esc_html(wp_strip_all_tags(get_the_excerpt(), true)); ?>
        </div>

        <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Read More</a>
      </article>
      <?php $cards[] = trim(ob_get_clean());
        }
        wp_reset_postdata();
    }

    $rows = array_chunk($cards, 3);
    $total_rows = count($rows);

    if (empty($rows)) {
        echo '<div id="dr-no-results" class="dr-row dr-row--empty" style="margin:0 auto 64px;display:flex;justify-content:center;align-items:flex-start;max-width:700px;width:100%;">';
        echo '<p style="font-size:1.1rem; color:#333; text-align:left;">No posts found.</p>';
        echo "</div>";
    }

    echo '<div class="dr-grid">';
    if (!empty($rows)) {
        foreach ($rows as $i => $row_items) {
            $is_last = $i === $total_rows - 1;
            $count = count($row_items);
            echo '<div class="dr-row' .
                ($is_last ? " dr-row--last" : "") .
                '"' .
                ($is_last ? ' data-count="' . (int) $count . '"' : "") .
                ">";
            echo implode("", $row_items);
            echo "</div>";
        }
    }
    echo "</div>"; // .dr-grid

    // Pagination (DR class name)
    echo '<div class="dr-pagination">';
    $big = 999999;
    echo paginate_links([
        "base" => str_replace(
            $big,
            "%#%",
            esc_url(add_query_arg("dr_paged", $big))
        ),
        "format" => "&dr_paged=%#%",
        "current" => max(1, $paged),
        "total" => max(1, $q->max_num_pages),
        "prev_text" => "&laquo;",
        "next_text" => "&raquo;",
    ]);
    echo "</div>";

    echo "</div>"; // #results

    $html = ob_get_clean();
    wp_send_json_success(["html" => $html]);
}
