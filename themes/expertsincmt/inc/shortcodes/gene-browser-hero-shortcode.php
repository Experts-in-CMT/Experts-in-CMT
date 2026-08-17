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
 *  Shortcode: [gene_browser_hero]
 * ------------------------------------------------------------
 *  Renders the Gene Browser app hero: background image, title,
 *  intro copy, and a stats line. Sibling of [variant_mechanism_hero]
 *  and [genes_hero]; same overlay pattern (image as a CSS custom
 *  property, left-side fade driven by two ACF Range fields), so
 *  the tools open the same way. Kept as its own component with its
 *  own copy, image, and stats.
 *
 *  Fields (group_eic_gbx_hero, on the [gene_browser] page):
 *    gbx_hero_image            (image, array)
 *    gbx_hero_fade_start/end   (range, 0-100, + mobile variants)
 *    gbx_hero_title            (text)
 *    gbx_hero_intro            (textarea)
 *    gbx_hero_genes_count      (text)    -> "{value} CMT disease genes cataloged"
 *    gbx_hero_subtypes_count   (number)  -> "{n} classified subtypes"
 *    gbx_hero_chromosomes_count(number)  -> "{n} chromosomes"
 *
 *  Each stat is skipped if its field is empty, so the line
 *  self-trims. Values render exactly (no "+"): these are exact
 *  totals, not round numbers.
 *
 *  Location: /inc/shortcodes/gene-browser-hero-shortcode.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

if (shortcode_exists("gene_browser_hero")) {
    return;
}

add_shortcode("gene_browser_hero", function () {
    if (is_admin()) {
        return "";
    }

    $post_id = get_queried_object_id();

    $img = get_field("gbx_hero_image", $post_id) ?: null;
    $title = trim((string) get_field("gbx_hero_title", $post_id));
    $intro = trim((string) get_field("gbx_hero_intro", $post_id));

    $fade_start = get_field("gbx_hero_fade_start", $post_id);
    $fade_end = get_field("gbx_hero_fade_end", $post_id);
    $fade_start = is_numeric($fade_start) ? (float) $fade_start : 33;
    $fade_end = is_numeric($fade_end) ? (float) $fade_end : 66;

    $fade_start_m = get_field("gbx_hero_fade_start_mobile", $post_id);
    $fade_end_m = get_field("gbx_hero_fade_end_mobile", $post_id);
    $fade_start_m = is_numeric($fade_start_m) ? (float) $fade_start_m : 55;
    $fade_end_m = is_numeric($fade_end_m) ? (float) $fade_end_m : 100;

    // Count fields are text, so each stat can carry its own symbol
    // (e.g. "140+", "170+") or stay an exact number (chromosomes). Render the
    // trimmed value exactly; an empty field hides that stat.
    $genes_count = trim((string) get_field("gbx_hero_genes_count", $post_id));
    $subtypes_count = trim((string) get_field("gbx_hero_subtypes_count", $post_id));
    $chromosomes_count = trim((string) get_field("gbx_hero_chromosomes_count", $post_id));
    $genes_count = $genes_count !== "" ? $genes_count : null;
    $subtypes_count = $subtypes_count !== "" ? $subtypes_count : null;
    $chromosomes_count = $chromosomes_count !== "" ? $chromosomes_count : null;

    if (!$img && $title === "" && $intro === "") {
        return "";
    }

    $style = "";
    if ($img && is_array($img) && !empty($img["url"])) {
        $style .= "--gbx-hero-img:url('" . esc_url($img["url"]) . "');";
    }
    $style .= "--gbx-hero-fade-start:" . $fade_start . "%;";
    $style .= "--gbx-hero-fade-end:" . $fade_end . "%;";
    $style .= "--gbx-hero-fade-start-mobile:" . $fade_start_m . "%;";
    $style .= "--gbx-hero-fade-end-mobile:" . $fade_end_m . "%;";

    // Inline icons: DNA (genes), stacked lines (subtypes), X-chromosome.
    // Icons borrowed from the sibling heroes: DNA helix (genes hero) and the
    // open book (genes / var-mech "classified subtypes" stat), so the shared
    // stats read identically across all three browsers.
    $icon_genes =
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M6 3c0 6 12 6 12 12M18 21c0-6-12-6-12-12M7 6h10M7 18h10" stroke-linecap="round"/>' .
        "</svg>";
    $icon_subtypes =
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v13c-3-1-6-1-8 1-2-2-5-2-8-1V5z" stroke-linejoin="round"/>' .
        "</svg>";
    $icon_chromosomes =
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M6 3l12 18M18 3L6 21" stroke-linecap="round"/><circle cx="12" cy="12" r="1.8"/>' .
        "</svg>";

    ob_start();
    ?>
<style>
.gbx-hero{position:relative;left:50%;width:100vw;max-width:1180px;transform:translateX(-50%);overflow:hidden;padding-inline:12px;background:var(--bg,#fff);min-height:clamp(190px,22vw,260px);border-radius:28px 28px 0 0}
.gbx-hero::before{content:"";position:absolute;inset:0 12px;border-radius:28px 28px 0 0;background-image:var(--gbx-hero-img);background-size:cover;background-position:right center}
.gbx-hero::after{content:"";position:absolute;inset:0 12px;border-radius:28px 28px 0 0;background:linear-gradient(to right,var(--bg,#fff) 0%,var(--bg,#fff) var(--gbx-hero-fade-start),transparent var(--gbx-hero-fade-end))}
.gbx-hero__inner{position:relative;z-index:1;max-width:min(560px,55%);height:100%;padding:1.75rem 2.5rem 4.5rem;display:flex;flex-direction:column;justify-content:center}
.gbx-hero__title{font-size:2rem;margin:0}
.gbx-hero__intro{margin:.5rem 0 0;max-width:46ch;color:var(--text,#1c2530);line-height:1.5}
.gbx-hero__stats{display:flex;align-items:center;flex-wrap:nowrap;align-self:flex-start;width:max-content;margin-top:1.5rem;gap:.6rem}
.gbx-hero__stat{display:flex;align-items:center;gap:.5rem;font-size:.85rem;white-space:nowrap;color:var(--text,#1c2530)}
.gbx-hero__stat-icon{display:inline-flex;color:var(--primary,#174777)}
.gbx-hero__stat-text strong{font-weight:600}
.gbx-hero__stat-divider{width:1px;height:1.2em;background:rgb(85 85 85 / 30%)}
.has-gbx-hero .wp-block-post-title{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}
.has-gbx-hero .gbx{margin-top:-32px;z-index:2}
@media(max-width:600px){
  .gbx-hero{min-height:clamp(220px,46vw,300px);border-radius:22px 22px 0 0}
  .gbx-hero__title{font-size:1.75rem !important}
  .gbx-hero::before{background-position:78% center;border-radius:22px 22px 0 0}
  .gbx-hero::after{border-radius:22px 22px 0 0;background:linear-gradient(to right,var(--bg,#fff) 0%,var(--bg,#fff) var(--gbx-hero-fade-start-mobile,55%),transparent var(--gbx-hero-fade-end-mobile,100%))}
  .gbx-hero__inner{max-width:100%;padding:1.5rem 1.75rem 4rem}
  .gbx-hero__stats{flex-direction:column;align-items:flex-start;width:auto;gap:.5rem}
  .gbx-hero__stat-divider{display:none}
  .has-gbx-hero .gbx{margin-top:-20px}
}
</style>

<section class="gbx-hero" style="<?php echo esc_attr($style); ?>">
  <div class="gbx-hero__inner">

    <?php if ($title !== ""): ?>
      <h2 class="gbx-hero__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro !== ""): ?>
      <p class="gbx-hero__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>

    <div class="gbx-hero__stats">
      <?php $rendered = 0; ?>

      <?php if ($genes_count !== null): ?>
      <div class="gbx-hero__stat">
        <span class="gbx-hero__stat-icon" aria-hidden="true"><?php echo $icon_genes; ?></span>
        <span class="gbx-hero__stat-text"><strong><?php echo esc_html(
            $genes_count
        ); ?></strong> CMT disease genes cataloged</span>
      </div>
      <?php $rendered++; ?>
      <?php endif; ?>

      <?php if ($subtypes_count !== null): ?>
      <?php if ($rendered > 0): ?><span class="gbx-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="gbx-hero__stat">
        <span class="gbx-hero__stat-icon" aria-hidden="true"><?php echo $icon_subtypes; ?></span>
        <span class="gbx-hero__stat-text"><strong><?php echo esc_html(
            $subtypes_count
        ); ?></strong> classified subtypes</span>
      </div>
      <?php $rendered++; ?>
      <?php endif; ?>

      <?php if ($chromosomes_count !== null): ?>
      <?php if ($rendered > 0): ?><span class="gbx-hero__stat-divider" aria-hidden="true"></span><?php endif; ?>
      <div class="gbx-hero__stat">
        <span class="gbx-hero__stat-icon" aria-hidden="true"><?php echo $icon_chromosomes; ?></span>
        <span class="gbx-hero__stat-text"><strong><?php echo esc_html(
            $chromosomes_count
        ); ?></strong> chromosomes</span>
      </div>
      <?php $rendered++; ?>
      <?php endif; ?>
    </div>

  </div>
</section>
<?php return ob_get_clean();
});

/**
 * Mark the page that places [gene_browser_hero] so the hero styles can suppress
 * the theme's plain post-title there (the hero carries the visible title) and
 * pull the table up to overlap the hero's flat bottom edge.
 */
add_filter("body_class", function ($classes) {
    if (is_singular()) {
        $post = get_queried_object();
        if (
            $post instanceof WP_Post &&
            has_shortcode((string) $post->post_content, "gene_browser_hero")
        ) {
            $classes[] = "has-gbx-hero";
        }
    }
    return $classes;
});
