<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 * Component: Section — The Dorsal Root (Homepage Feature)
 * ------------------------------------------------------------
 * Renders the 3-wide featured grid for Dorsal Root articles.
 *
 * Behavior:
 *   - Pulls posts where post meta `_is_featured` = '1'
 *   - Falls back to latest posts if none are featured
 *
 * Usage:
 *   - Included as a template partial within the homepage layout
 *
 * Parameters:
 *   $args['count']  (int)  Number of posts to display (default: 3)
 *
 * Notes:
 *   - Uses DR card structure consistent with Glossary and Genes
 *   - Maintains bagpipe card parity (media → heading → excerpt)
 * ============================================================
 */

$count = isset($args["count"]) ? intval($args["count"]) : 3;

/* Posts to keep out of the homepage teaser (e.g. pw-protected articles in review) */
$dr_hidden = [4891];

/* Try featured first */
$featured_q = new WP_Query([
    "post_type" => "post",
    "post_status" => "publish",
    "posts_per_page" => $count,
    "post__not_in" => $dr_hidden,
    "meta_query" => [
        [
            "key" => "_is_featured",
            "value" => "1",
        ],
    ],
    "no_found_rows" => true,
]);

/* Fallback to latest */
$q = $featured_q->have_posts()
    ? $featured_q
    : new WP_Query([
        "post_type" => "post",
        "post_status" => "publish",
        "posts_per_page" => $count,
        "post__not_in" => $dr_hidden,
        "no_found_rows" => true,
    ]);
?>

<section class="dr-feature" aria-labelledby="dr-heading">
    <div class="dr-feature__head">
        <h2 id="dr-heading" class="dr-feature__title">The Dorsal Root</h2>
        <span class="dr-feature__more-wrap"><a class="dr-feature__more" href="<?php echo esc_url(
            home_url("/dorsal-root#blog")
        ); ?>">More From The Dorsal Root</a></span>
    </div>

    <div class="dr-feature__grid">
        <?php if ($q->have_posts()): ?>
            <?php while ($q->have_posts()):
                $q->the_post(); ?>
                <article class="dr-feature__card">
                    <a class="dr-feature__media" href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()): ?>
                            <?php the_post_thumbnail("large", [
                                "loading" => "lazy",
                                "decoding" => "async",
                            ]); ?>
                        <?php else: ?>
                            <div class="dr-feature__media-ph" aria-hidden="true"></div>
                        <?php endif; ?>
                    </a>

                    <div class="dr-feature__body">
                        <h3 class="dr-feature__post-title">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h3>

                        <p class="dr-feature__excerpt">
                            <?php echo esc_html(
                                wp_strip_all_tags(get_the_excerpt(), true)
                            ); ?>
                        </p>
                    </div>
                </article>
            <?php
            endwhile; ?>
            <?php wp_reset_postdata(); ?>
        <?php endif; ?>
    </div>
</section>
