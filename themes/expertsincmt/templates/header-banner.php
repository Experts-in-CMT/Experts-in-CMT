<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Since v0.5.0
 * Feature: Header Banner (Overlay Layout)
 * ------------------------------------------------------------
 * Brought in line with the Subtype Browser app hero's overlay design:
 * image is output as a CSS custom property and sits behind the
 * text as a background layer, with a live CSS mask-image fade
 * driven by two ACF Range fields (banner_fade_start / banner_fade_end),
 * rather than living in its own grid column. No Photoshop masking
 * required on the source image.
 *
 * Renders only when at least one of these exists:
 *   • banner_image
 *   • banner_title
 *   • banner_intro
 */

// ------------------------------------------------------------
// Resolve banner context
// ------------------------------------------------------------
if (is_search()) {
    // Force banner context to Search page
    $post_id = 3846;
} elseif (is_404()) {
    // Force banner context to 404 shell page
    $post_id = 3862;
} else {
    $post_id = isset($post_id)
        ? (int) $post_id
        : (int) get_queried_object_id();
}

$img = get_field("banner_image", $post_id) ?: null;
$title = trim((string) get_field("banner_title", $post_id));
$intro = get_field("banner_intro", $post_id) ?: "";

$fade_start = get_field("banner_fade_start", $post_id);
$fade_end = get_field("banner_fade_end", $post_id);
$fade_start = is_numeric($fade_start) ? (float) $fade_start : 33;
$fade_end = is_numeric($fade_end) ? (float) $fade_end : 66;

$fade_start_mobile = get_field("banner_fade_start_mobile", $post_id);
$fade_end_mobile = get_field("banner_fade_end_mobile", $post_id);
$fade_start_mobile = is_numeric($fade_start_mobile)
    ? (float) $fade_start_mobile
    : 55;
$fade_end_mobile = is_numeric($fade_end_mobile)
    ? (float) $fade_end_mobile
    : 100;

if (!$img && $title === "" && $intro === "") {
    return; // nothing to render
}

$style = "";
if ($img && is_array($img) && !empty($img["url"])) {
    $style .= "--banner-img:url('" . esc_url($img["url"]) . "');";
}
$style .= "--banner-fade-start:" . $fade_start . "%;";
$style .= "--banner-fade-end:" . $fade_end . "%;";
$style .= "--banner-fade-start-mobile:" . $fade_start_mobile . "%;";
$style .= "--banner-fade-end-mobile:" . $fade_end_mobile . "%;";
?>
<section class="header-banner header-banner--overlay" style="<?php echo esc_attr(
    $style
); ?>">
  <div class="container header-banner__inner">
    <div class="header-banner__content prose">
      <?php if ($title !== ""): ?>
        <h1 class="header-banner__title"><?php echo wp_kses(
            $title,
            ["br" => []]
        ); ?></h1>
      <?php endif; ?>
      <?php if ($intro): ?>
        <div class="header-banner__intro">
          <?php echo wp_kses_post($intro); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
