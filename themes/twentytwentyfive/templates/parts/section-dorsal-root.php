<?php
/**
 * The Dorsal Root — 3-wide featured section (no categories)
 * Pulls 'post' entries marked with post meta _is_featured = '1'.
 * Fallback: latest posts if none are featured.
 *
 * Accepts $args:
 * - count (int) default 3
 */

$count = isset($args['count']) ? intval($args['count']) : 3;

/* Try featured first */
$featured_q = new WP_Query([
  'post_type'      => 'post',
  'post_status'    => 'publish',
  'posts_per_page' => $count,
  'meta_query'     => [
    [
      'key'   => '_is_featured',
      'value' => '1',
    ],
  ],
  'no_found_rows'  => true,
]);

/* Fallback to latest */
$q = ($featured_q->have_posts())
  ? $featured_q
  : new WP_Query([
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => $count,
      'no_found_rows'  => true,
    ]);
?>

<section class="dr-wrap" aria-labelledby="dr-heading">
  <div class="dr-head">
    <h2 id="dr-heading" class="dr-title">The Dorsal Root</h2>
    <a class="dr-more" href="<?php echo esc_url( home_url('/dorsal-root#blog') ); ?>">More From The Dorsal Root</a>
  </div>

  <div class="dr-grid three-wide">
    <?php if ( $q->have_posts() ) : while ( $q->have_posts() ) : $q->the_post(); ?>
      <article class="dr-card">
        <a class="dr-media" href="<?php the_permalink(); ?>">
          <?php if ( has_post_thumbnail() ) :
            the_post_thumbnail( 'large', ['loading' => 'lazy', 'decoding' => 'async'] );
          else: ?>
            <div class="dr-media__ph" aria-hidden="true"></div>
          <?php endif; ?>
        </a>

        <div class="dr-body">
          <h3 class="dr-h">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
          </h3>

          <p class="dr-excerpt">
            <?php echo esc_html( wp_strip_all_tags( get_the_excerpt(), true ) ); ?>
          </p>
        </div>
      </article>
    <?php endwhile; wp_reset_postdata(); endif; ?>
  </div>
</section>
