<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  Shortcode: [genes_hero]
 * ------------------------------------------------------------
 *  Purpose:
 *  - Renders the Subtype Browser app hero: background image, title,
 *    intro copy, and a static two-item stats line.
 *  - Image is output as a CSS custom property; the left-side
 *    fade is a live CSS mask-image driven by two ACF Range
 *    fields (genes_hero_fade_start / genes_hero_fade_end), with
 *    a separate mobile pair for <= 600px, so no Photoshop
 *    masking is required on the source image.
 *
 *  Fields (page 1819, group_eic_genes_hero):
 *    genes_hero_image                (image, array)
 *    genes_hero_title                (text)
 *    genes_hero_intro                (textarea)
 *    genes_hero_fade_start           (range, 0-100)
 *    genes_hero_fade_end             (range, 0-100)
 *    genes_hero_fade_start_mobile    (range, 0-100)
 *    genes_hero_fade_end_mobile      (range, 0-100)
 *    genes_hero_subtypes_count       (number)
 *    genes_hero_genes_count          (number)
 *
 *  Note:
 *  - Title renders as a plain H2 with no custom styling (inherits
 *    the site's global h2 rule). The page's own H1 lives elsewhere
 *    in the template; this hero never duplicates it.
 *  - The stats line renders as "{value}+ subtypes" / "{value}+ genes",
 *    each skipped entirely if its field is left empty, plus a third
 *    static stat ("1 in 2,500 people") matching the epidemiology
 *    copy in educational-jsonld.php / pages-jsonld.php verbatim.
 *  - This is separate from the [genes_loop] totals module,
 *    which stays live and filter-aware.
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("genes_hero", function () {
    if (is_admin()) {
        return "";
    }

    $post_id = get_queried_object_id();

    $img = get_field("genes_hero_image", $post_id) ?: null;
    $title = trim((string) get_field("genes_hero_title", $post_id));
    $intro = trim((string) get_field("genes_hero_intro", $post_id));

    $fade_start = get_field("genes_hero_fade_start", $post_id);
    $fade_end = get_field("genes_hero_fade_end", $post_id);
    $fade_start = is_numeric($fade_start) ? (float) $fade_start : 0;
    $fade_end = is_numeric($fade_end) ? (float) $fade_end : 45;

    $fade_start_m = get_field("genes_hero_fade_start_mobile", $post_id);
    $fade_end_m = get_field("genes_hero_fade_end_mobile", $post_id);
    $fade_start_m = is_numeric($fade_start_m) ? (float) $fade_start_m : 55;
    $fade_end_m = is_numeric($fade_end_m) ? (float) $fade_end_m : 100;

    $subtypes_count = get_field("genes_hero_subtypes_count", $post_id);
    $genes_count = get_field("genes_hero_genes_count", $post_id);
    $subtypes_count = is_numeric($subtypes_count) ? (int) $subtypes_count : null;
    $genes_count = is_numeric($genes_count) ? (int) $genes_count : null;

    if (!$img && $title === "" && $intro === "") {
        return "";
    }

    $style = "";
    if ($img && is_array($img) && !empty($img["url"])) {
        $style .= "--genes-hero-img:url('" . esc_url($img["url"]) . "');";
    }
    $style .= "--genes-hero-fade-start:" . $fade_start . "%;";
    $style .= "--genes-hero-fade-end:" . $fade_end . "%;";
    $style .= "--genes-hero-fade-start-mobile:" . $fade_start_m . "%;";
    $style .= "--genes-hero-fade-end-mobile:" . $fade_end_m . "%;";

    ob_start();
    ?>

<section class="genes-hero" style="<?php echo esc_attr($style); ?>">
  <div class="genes-hero__inner">

    <?php if ($title !== ""): ?>
      <h2 class="genes-hero__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro !== ""): ?>
      <p class="genes-hero__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>

    <div class="genes-hero__stats">
      <?php $stats_rendered = 0; ?>

      <?php if ($subtypes_count !== null): ?>
      <?php if ($stats_rendered > 0): ?><span class="genes-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="genes-hero__stat">
        <span class="genes-hero__stat-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v13c-3-1-6-1-8 1-2-2-5-2-8-1V5z" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="genes-hero__stat-text"><strong><?php echo esc_html($subtypes_count); ?>+</strong> subtypes</span>
      </div>
      <?php $stats_rendered++; ?>
      <?php endif; ?>
      <?php if ($genes_count !== null): ?>
      <?php if ($stats_rendered > 0): ?><span class="genes-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="genes-hero__stat">
        <span class="genes-hero__stat-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 3c0 6 12 6 12 12M18 21c0-6-12-6-12-12M7 6h10M7 18h10" stroke-linecap="round"/>
          </svg>
        </span>
        <span class="genes-hero__stat-text"><strong><?php echo esc_html($genes_count); ?>+</strong> genes</span>
      </div>
      <?php $stats_rendered++; ?>
      <?php endif; ?>

      <?php if ($stats_rendered > 0): ?><span class="genes-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="genes-hero__stat">
        <span class="genes-hero__stat-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="genes-hero__stat-text">Affects <strong>1 in 2,500</strong> people</span>
      </div>
    </div>

  </div>
</section>

<?php return ob_get_clean();
});
