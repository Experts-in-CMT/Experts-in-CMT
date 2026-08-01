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
 *  Shortcode: [variant_mechanism_hero]
 * ------------------------------------------------------------
 *  Purpose:
 *  - Renders the Variant Mechanism Browser app hero: background
 *    image, title, intro copy, and a three-item stats line.
 *  - Sibling of [genes_hero]; same overlay pattern (image output
 *    as a CSS custom property, left-side fade driven by two ACF
 *    Range fields), so the two tools open the same way. Kept as
 *    its own component so the Genes DB hero is never at risk and
 *    the two can carry different copy, image, and stats.
 *
 *  Fields (group_eic_vmech_hero, on the Variant Mechanisms page):
 *    vmech_hero_image             (image, array)
 *    vmech_hero_fade_start        (range, 0-100)
 *    vmech_hero_fade_end          (range, 0-100)
 *    vmech_hero_title             (text)
 *    vmech_hero_intro             (textarea)
 *    vmech_hero_subtypes_count    (number)  -> "{n} subtypes classified"
 *    vmech_hero_categories_count  (number)  -> "{n} mechanism categories"
 *    vmech_hero_resolved_count    (number)  -> "{n} with a resolved mechanism"
 *
 *  Note:
 *  - Title renders as an H2, matching the Genes DB hero so it
 *    inherits the same navy heading style. The page's real H1
 *    stays as the theme post-title, made screen-reader-only in
 *    the hero stylesheet so the heading order is intact without
 *    a second visible title.
 *  - Each stat is skipped entirely if its field is left empty, so
 *    the line self-trims. Values render exactly (no "+"): these
 *    are curated totals, not "170+"-style round numbers.
 *  - Separate from [variant_mechanism_table], which stays live
 *    and filter-aware.
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

if (shortcode_exists("variant_mechanism_hero")) {
    return;
}

add_shortcode("variant_mechanism_hero", function () {
    if (is_admin()) {
        return "";
    }

    $post_id = get_queried_object_id();

    $img = get_field("vmech_hero_image", $post_id) ?: null;
    $title = trim((string) get_field("vmech_hero_title", $post_id));
    $intro = trim((string) get_field("vmech_hero_intro", $post_id));

    $fade_start = get_field("vmech_hero_fade_start", $post_id);
    $fade_end = get_field("vmech_hero_fade_end", $post_id);
    $fade_start = is_numeric($fade_start) ? (float) $fade_start : 33;
    $fade_end = is_numeric($fade_end) ? (float) $fade_end : 66;

    $fade_start_m = get_field("vmech_hero_fade_start_mobile", $post_id);
    $fade_end_m = get_field("vmech_hero_fade_end_mobile", $post_id);
    $fade_start_m = is_numeric($fade_start_m) ? (float) $fade_start_m : 55;
    $fade_end_m = is_numeric($fade_end_m) ? (float) $fade_end_m : 100;

    $subtypes_count = get_field("vmech_hero_subtypes_count", $post_id);
    $categories_count = get_field("vmech_hero_categories_count", $post_id);
    $resolved_count = get_field("vmech_hero_resolved_count", $post_id);
    $subtypes_count = is_numeric($subtypes_count) ? (int) $subtypes_count : null;
    $categories_count = is_numeric($categories_count)
        ? (int) $categories_count
        : null;
    $resolved_count = is_numeric($resolved_count) ? (int) $resolved_count : null;

    if (!$img && $title === "" && $intro === "") {
        return "";
    }

    $style = "";
    if ($img && is_array($img) && !empty($img["url"])) {
        $style .= "--vmech-hero-img:url('" . esc_url($img["url"]) . "');";
    }
    $style .= "--vmech-hero-fade-start:" . $fade_start . "%;";
    $style .= "--vmech-hero-fade-end:" . $fade_end . "%;";
    $style .= "--vmech-hero-fade-start-mobile:" . $fade_start_m . "%;";
    $style .= "--vmech-hero-fade-end-mobile:" . $fade_end_m . "%;";

    // Stat icons: book (classified), branching nodes (categories),
    // check-in-circle (resolved). Kept inline so the hero needs no sprite.
    $icon_subtypes =
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v13c-3-1-6-1-8 1-2-2-5-2-8-1V5z" stroke-linejoin="round"/>' .
        "</svg>";
    $icon_categories =
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M5 3v6a3 3 0 0 0 3 3h8a3 3 0 0 1 3 3v3M5 12v9" stroke-linecap="round" stroke-linejoin="round"/>' .
        '<circle cx="5" cy="4" r="1.6"/><circle cx="19" cy="20" r="1.6"/><circle cx="5" cy="20" r="1.6"/>' .
        "</svg>";
    $icon_resolved =
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M9 12l2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z" stroke-linecap="round" stroke-linejoin="round"/>' .
        "</svg>";

    ob_start();
    ?>

<section class="vmech-hero" style="<?php echo esc_attr($style); ?>">
  <div class="vmech-hero__inner">

    <?php if ($title !== ""): ?>
      <h2 class="vmech-hero__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro !== ""): ?>
      <p class="vmech-hero__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>

    <div class="vmech-hero__stats">
      <?php $stats_rendered = 0; ?>

      <?php if ($subtypes_count !== null): ?>
      <div class="vmech-hero__stat">
        <span class="vmech-hero__stat-icon" aria-hidden="true"><?php echo $icon_subtypes; ?></span>
        <span class="vmech-hero__stat-text"><strong><?php echo esc_html(
            $subtypes_count
        ); ?>+</strong> classified subtypes</span>
      </div>
      <?php $stats_rendered++; ?>
      <?php endif; ?>

      <?php if ($categories_count !== null): ?>
      <?php if ($stats_rendered > 0): ?><span class="vmech-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="vmech-hero__stat">
        <span class="vmech-hero__stat-icon" aria-hidden="true"><?php echo $icon_categories; ?></span>
        <span class="vmech-hero__stat-text"><strong><?php echo esc_html(
            $categories_count
        ); ?></strong> mechanism categories</span>
      </div>
      <?php $stats_rendered++; ?>
      <?php endif; ?>

      <?php if ($resolved_count !== null): ?>
      <?php if ($stats_rendered > 0): ?><span class="vmech-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="vmech-hero__stat">
        <span class="vmech-hero__stat-icon" aria-hidden="true"><?php echo $icon_resolved; ?></span>
        <span class="vmech-hero__stat-text"><strong><?php echo esc_html(
            $resolved_count
        ); ?>+</strong> with a resolved mechanism</span>
      </div>
      <?php $stats_rendered++; ?>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php return ob_get_clean();
});

/**
 * Mark the page that places [variant_mechanism_hero] so the hero stylesheet can
 * suppress the theme's plain post-title there (the hero carries the H1) and pull
 * the filter up to overlap the hero. Scoped to the actual host page, so no other
 * page is affected.
 */
add_filter("body_class", function ($classes) {
    if (is_singular()) {
        $post = get_queried_object();
        if (
            $post instanceof WP_Post &&
            has_shortcode((string) $post->post_content, "variant_mechanism_hero")
        ) {
            $classes[] = "has-vmech-hero";
        }
    }
    return $classes;
});
