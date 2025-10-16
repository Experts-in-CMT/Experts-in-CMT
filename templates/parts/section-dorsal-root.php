<?php
/**
 * Template part: "Dorsal Root" featured posts (3)
 * Usage: get_template_part( 'templates/parts/section', 'dorsal-root', [ 'show_search' => true ] );
 */

$current_lang = apply_filters( 'wpml_current_language', null );

// Query 3 featured posts (fallback to latest if none marked featured)
$featured_query = [
    'post_type'         => 'post',   // CPT slug
    'posts_per_page'    => 3,
    'post_status'       => 'publish',
    'lang'              => $current_lang,
    'suppress_filters'  => false,
];

if ( function_exists( 'get_field' ) ) {
    $featured_query['meta_query'] = [
        [
            'key'     => 'featured',  // ACF true/false field
            'value'   => '1',
            'compare' => '=',
        ],
    ];
}

$featured = get_posts( $featured_query );

// Fallback to latest if no featured found
if ( empty( $featured ) ) {
    $featured = get_posts( array_merge( $featured_query, [ 'meta_query' => [] ] ) );
}

$show_search = ! is_front_page() ? ( $args['show_search'] ?? false ) : false;
?>

<section class="dorsal-root prose prose-neutral prose-a:text-primary">
	<?php if ( $show_search ) : ?>
		<?php
		get_template_part( 'template-parts/search', 'resources', [
			'placeholder' => __( 'Find Dorsal Root entries', 'twentytwentyfive' ),
		] );
		?>
	<?php endif; ?>

	<div class="container-wide !max-w-[1316px]">
		<div class="md:flex items-center justify-between mb-4">
			<h2><?php _e( 'Dorsal Root', 'twentytwentyfive' ); ?></h2>

			<a href="<?php echo esc_url( home_url( '/dorsal-root' ) ); ?>"
			   class="hidden md:flex items-center justify-center font-barlow uppercase">
				<img class="w-[50px] h-[50px]"
				     src="<?php echo esc_url( get_stylesheet_directory_uri() . '/img/icons/icon_blog_posts.png' ); ?>"
				     aria-hidden="true" alt="" />
				<span><?php _e( 'More from Dorsal Root', 'twentytwentyfive' ); ?></span>
			</a>
		</div>

		<?php if ( ! empty( $featured ) ) : ?>
			<ul class="featured-posts list-none pl-0 grid gap-8 lg:grid-cols-3 mb-12">
				<?php foreach ( $featured as $post ) : ?>
					<?php
					$thumb = get_the_post_thumbnail( $post->ID, 'large', [ 'class' => 'border-b-8 w-full h-auto' ] );
					if ( ! $thumb && function_exists( 'get_field' ) ) {
						$banner = get_field( 'banner_image', $post->ID );
						if ( $banner ) {
							$alt   = esc_attr( $banner['alt'] ?? get_the_title( $post->ID ) );
							$url   = esc_url( $banner['url'] ?? '' );
							$thumb = $url ? '<img class="border-b-8 w-full h-auto" src="' . $url . '" alt="' . $alt . '" />' : '';
						}
					}
					?>
					<li class="flex flex-col justify-between">
						<article>
							<?php if ( $thumb ) : ?>
								<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="block mb-4">
									<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</a>
							<?php endif; ?>

							<h4 class="mt-0">
								<a class="!text-tertiary" href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
									<?php echo esc_html( get_the_title( $post->ID ) ); ?>
								</a>
							</h4>

							<?php if ( ! empty( $post->post_excerpt ) ) : ?>
								<p class="text-gray">
									<?php echo esc_html( $post->post_excerpt ); ?>
								</p>
							<?php endif; ?>
						</article>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="md:hidden text-center">
			<a href="<?php echo esc_url( home_url( '/dorsal-root' ) ); ?>"
			   class="hidden md:flex items-center justify-center font-barlow uppercase">
				<img class="w-[50px] h-[50px]"
				     src="<?php echo esc_url( get_stylesheet_directory_uri() . '/img/icons/icon_blog_posts.png' ); ?>"
				     aria-hidden="true" alt="" />
				<span><?php _e( 'More from Dorsal Root', 'twentytwentyfive' ); ?></span>
			</a>
		</div>

		<hr class="md:hidden"/>
	</div>
</section>
