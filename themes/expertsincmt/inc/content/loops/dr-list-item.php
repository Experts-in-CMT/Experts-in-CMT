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
 * Dorsal Root — editorial list item renderer
 * ------------------------------------------------------------
 * Single source of the DR post row markup (image-left / text-right),
 * shared by the page-load shortcode (dr-posts.php) and the AJAX
 * endpoint (loop-endpoints.php) so the two never drift.
 *
 * Colour: outputs one CSS custom property (--dr-cat) from the post's
 * category; dr-loop.css derives the dot, pill tint, and pill text.
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("eic_dr_render_list_item")) {
    /**
     * @param int $post_id
     * @return string Row HTML.
     */
    function eic_dr_render_list_item($post_id)
    {
        $post_id = (int) $post_id;

        $cat = function_exists("eic_dr_post_category")
            ? eic_dr_post_category($post_id)
            : null;
        $cat_name = $cat ? $cat->name : "";
        $cat_color =
            $cat && function_exists("eic_dr_category_color")
                ? eic_dr_category_color($cat->term_id)
                : "#5ea0c9";

        $permalink = get_permalink($post_id);
        $title = get_the_title($post_id);
        $date = get_the_date("F j, Y", $post_id);
        $excerpt = trim(wp_strip_all_tags(get_the_excerpt($post_id), true));

        $thumb = has_post_thumbnail($post_id)
            ? get_the_post_thumbnail($post_id, "large", [
                "loading" => "lazy",
                "decoding" => "async",
            ])
            : "";

        ob_start();
        ?>
        <article class="dr-list__item" style="--dr-cat:<?php echo esc_attr(
            $cat_color
        ); ?>;">
            <a class="dr-list__link" href="<?php echo esc_url($permalink); ?>">

                <div class="dr-list__media">
                    <?php if ($thumb) {
                        echo $thumb;
                    } else { ?>
                        <span class="dr-list__media-ph" aria-hidden="true"></span>
                    <?php } ?>
                </div>

                <div class="dr-list__body">
                    <div class="dr-list__head">
                        <h2 class="dr-list__title"><?php echo esc_html(
                            $title
                        ); ?></h2>
                        <?php if ($cat_name !== ""): ?>
                            <span class="dr-list__cat">
                                <span class="dr-list__dot" aria-hidden="true"></span>
                                <span class="dr-list__pill"><?php echo esc_html(
                                    $cat_name
                                ); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($date): ?>
                        <div class="dr-list__date"><?php echo esc_html(
                            $date
                        ); ?></div>
                    <?php endif; ?>

                    <?php if ($excerpt !== ""): ?>
                        <p class="dr-list__excerpt"><?php echo esc_html(
                            $excerpt
                        ); ?></p>
                    <?php endif; ?>

                    <span class="dr-list__more">Read <span aria-hidden="true">&rarr;</span></span>
                </div>

            </a>
        </article>
        <?php return trim(ob_get_clean());
    }
}
