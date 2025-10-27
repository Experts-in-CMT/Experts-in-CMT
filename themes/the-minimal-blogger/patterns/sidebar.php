<?php
/**
 * Title: Sidebar content
 * Slug: the-minimal-blogger/sidebar
 * Categories: the-minimal-blogger
 * Description: Sidebar content
 * Keywords: Sidebar content
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"20px","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"left":{"color":"var:preset|color|tertiary","width":"1px"}}},"className":"the-minimal-blogger-sidebar","layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group the-minimal-blogger-sidebar" style="border-left-color:var(--wp--preset--color--tertiary);border-left-width:1px;padding-top:20px;padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px"}}} -->
<h3 class="wp-block-heading" style="font-size:24px">Looking for something?</h3>
<!-- /wp:heading -->

<!-- wp:search {"label":"","placeholder":"Search...","buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true,"style":{"border":{"radius":"0px","width":"1px","color":"#666666"}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px"},"spacing":{"margin":{"top":"50px"}}}} -->
<h3 class="wp-block-heading" style="margin-top:50px;font-size:24px">Latest Articles</h3>
<!-- /wp:heading -->

<!-- wp:latest-posts {"displayPostDate":true,"displayFeaturedImage":true,"featuredImageAlign":"left","featuredImageSizeWidth":50,"featuredImageSizeHeight":50,"addLinkToFeaturedImage":true,"align":"left"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px"},"spacing":{"margin":{"top":"50px"}}}} -->
<h3 class="wp-block-heading" style="margin-top:50px;font-size:24px">Follow Us</h3>
<!-- /wp:heading -->

<!-- wp:social-links {"iconColor":"base","iconColorValue":"#ffffff","iconBackgroundColor":"contrast","iconBackgroundColorValue":"#000000","className":"is-style-default"} -->
<ul class="wp-block-social-links has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"linkedin"} /-->

<!-- wp:social-link {"url":"#","service":"tiktok"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px"},"spacing":{"margin":{"top":"50px","bottom":"20px"}}}} -->
<h3 class="wp-block-heading" style="margin-top:50px;margin-bottom:20px;font-size:24px">Advertisement</h3>
<!-- /wp:heading -->

<!-- wp:image {"id":1628,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . "/assets/images/banner-example.jpg");?>" alt="" class="wp-image-1628"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->