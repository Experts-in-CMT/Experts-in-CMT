<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Header Banner (Split Layout)
 * ------------------------------------------------------------
 * Image (optional) on the right, text on the left.
 * Renders only when at least one of these exists:
 *   • banner_image
 *   • banner_title
 *   • banner_intro
 */


$post_id = isset($post_id) ? (int) $post_id : (int) get_queried_object_id();

$img = get_field("banner_image", $post_id) ?: null;
$title = trim((string) get_field("banner_title", $post_id));
$intro = get_field("banner_intro", $post_id) ?: "";

if (!$img && $title === "" && $intro === "") {
    return; // nothing to render
}

$alt = "";
if ($img && is_array($img)) {
    $alt = $img["alt"] ?? "";
    if ($alt === "") {
        $alt = get_the_title($post_id) . " header banner";
    }
}
?>
<section class="header-banner header-banner--split">
  <div class="container header-banner__inner">
    <div class="header-banner__content prose">
      <?php if ($title !== ""): ?>
        <h1 class="header-banner__title"><?php echo esc_html($title); ?></h1>
      <?php endif; ?>
      <?php if ($intro): ?>
        <div class="header-banner__intro">
          <?php echo wp_kses_post($intro); ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($img && is_array($img)): ?>
      <div class="header-banner__media">
        <img
          src="<?php echo esc_url($img["url"]); ?>"
          alt="<?php echo esc_attr($alt); ?>"
          class="header-banner__img"
          loading="eager"
          decoding="async"
        />
      </div>
    <?php endif; ?>
  </div>
</section>
