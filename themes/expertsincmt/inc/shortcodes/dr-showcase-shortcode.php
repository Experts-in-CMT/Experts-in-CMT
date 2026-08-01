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
 * Shortcodes: [dr_showcase] and [featured_subtype]
 * ------------------------------------------------------------
 * Both draw from the global "DR Showcase" ACF options page (see
 * inc/acf/dr-showcase-fields.php), and both are placed independently
 * so each can live wherever you want on a page.
 *
 * [dr_showcase]
 *   Renders the ordered Relationship field of Dorsal Root posts in the
 *   Dorsal Root list style, replacing a hand-built stack of media+text
 *   blocks. Each chosen post renders through eic_dr_render_list_item(),
 *   the same row renderer the /dorsal-root loop uses, so the showcase
 *   can never drift from the live list. Unpublished picks are skipped.
 *   Optional: heading="From The Dorsal Root" renders an <h2>.
 *
 * [featured_subtype]
 *   Renders the optional Featured Subtype as a self-contained, labeled
 *   spotlight card (name, gene, inheritance, year). It reproduces the
 *   genes card's gene/inheritance display rather than calling into the
 *   genes loop, so it never touches that code. Renders nothing when no
 *   subtype is selected.
 *
 * Curate both in one admin screen (DR Showcase); the blocks assemble
 * themselves wherever they are placed, with no blocks to hand-build.
 *
 * Auto-loaded via the inc/shortcodes/*.php glob. The showcase rows reuse
 * the site-wide .dr-list system (assets/css/dr-loop.css); the spotlight
 * is styled by assets/css/dr-showcase.css.
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("eic_dr_render_subtype_spotlight")) {
    /**
     * A labeled "Featured Subtype" spotlight. Self-contained: it reproduces the
     * genes card's gene/inheritance display logic rather than calling into the
     * genes loop, so it never touches that code. The gene symbol is italicized
     * (roman for "Unknown").
     *
     * @param int $post_id A subtype post ID.
     * @return string Spotlight HTML, or "" if the subtype is missing/unpublished.
     */
    function eic_dr_render_subtype_spotlight($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || get_post_status($post_id) !== "publish") {
            return "";
        }

        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);

        // Gene: mirror the genes card (unknown flag or empty -> "Unknown",
        // legacy "gene" field as a fallback source).
        $unknown_gene = (bool) get_post_meta($post_id, "unknown_gene", true);
        $gene_symbol = trim(
            (string) get_post_meta($post_id, "gene_symbol", true)
        );
        if ($gene_symbol === "" && function_exists("get_field")) {
            $legacy = trim((string) get_field("gene", $post_id));
            if ($legacy !== "") {
                $gene_symbol = $legacy;
            }
        }
        $display_gene =
            $unknown_gene || $gene_symbol === "" ? "Unknown" : $gene_symbol;
        $gene_known = $display_gene !== "Unknown";

        // Year discovered.
        $year = function_exists("get_field")
            ? get_field("year_of_discovery", $post_id) ?: ""
            : "";

        // Inheritance: the ACF field, else the taxonomy term names (as the
        // genes card does).
        $inherit = function_exists("get_field")
            ? get_field("inheritance_pattern", $post_id) ?: ""
            : "";
        if ($inherit === "") {
            $terms = wp_get_post_terms($post_id, "inheritance", [
                "fields" => "names",
            ]);
            $inherit = !is_wp_error($terms) ? implode(", ", $terms) : "";
        }

        // Chromosome: the ACF field, else the taxonomy term names.
        $chromosome = function_exists("get_field")
            ? trim((string) get_field("chromosome", $post_id))
            : "";
        if ($chromosome === "") {
            $ct = wp_get_post_terms($post_id, "chromosome", [
                "fields" => "names",
            ]);
            $chromosome = !is_wp_error($ct) ? implode(", ", $ct) : "";
        }

        // Neuropathy: the taxonomy's first term (as the genes card does),
        // else the ACF field.
        $neuropathy = "";
        $nt = wp_get_post_terms($post_id, "neuropathy", ["fields" => "names"]);
        if (!is_wp_error($nt) && !empty($nt)) {
            $neuropathy = (string) $nt[0];
        }
        if ($neuropathy === "" && function_exists("get_field")) {
            $neuropathy = trim((string) get_field("neuropathy", $post_id));
        }

        // aka / alias, surfaced under the title when present.
        $alias = function_exists("get_field")
            ? trim((string) get_field("subtype_alias", $post_id))
            : "";

        // Drives the type-dot color hook, same attribute the genes card uses.
        $type_class = trim(
            (string) get_post_meta($post_id, "type_classification", true)
        );

        // Short summary for the right column: the subtype's first authored
        // body paragraph, skipping the "What Is ...?" opener, which reads far
        // better than the SEO-style meta excerpt. Falls back to any remaining
        // body text, then the manual excerpt. Capped at a word boundary.
        $summary = "";
        $summary_heading = "";
        $content = strip_shortcodes(
            (string) get_post_field("post_content", $post_id)
        );
        // Drop Gutenberg block comments so the heading and paragraph parse
        // cleanly (imported bodies wrap blocks in <!-- wp:* --> comments, which
        // otherwise sit before the opening heading).
        $content = preg_replace('/<!--.*?-->/s', "", $content);
        // Capture the body's opening "What Is ...?" heading to show above the
        // summary, then remove it from the body.
        if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $hm)) {
            $summary_heading = trim(wp_strip_all_tags($hm[1]));
            $content = preg_replace(
                '/<h[1-6][^>]*>.*?<\/h[1-6]>/is',
                "",
                $content,
                1
            );
        }
        // Prefer the first real paragraph. If the "What Is ...?" opener lives in
        // a <p> instead of a heading, use it as the heading and skip past it.
        // Capture the paragraph's raw HTML so authored emphasis survives.
        $para_html = "";
        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $content, $paras)) {
            foreach ($paras[1] as $p) {
                $t = trim(wp_strip_all_tags($p));
                if ($t === "") {
                    continue;
                }
                if (preg_match('/^what is\b.{0,80}?\?$/i', $t)) {
                    if ($summary_heading === "") {
                        $summary_heading = $t;
                    }
                    continue;
                }
                $para_html = trim($p);
                break;
            }
        }

        // Build the summary as safe HTML. Keep the body's own italic emphasis so
        // gene symbols the author italicized stay italic (and stay roman inside
        // a hyphenated subtype name, as the body already handles), but strip
        // everything else. Very long paragraphs fall back to trimmed plain text.
        if ($para_html !== "") {
            $plain = trim(wp_strip_all_tags($para_html));
            if (str_word_count($plain) > 55) {
                $summary = esc_html(wp_trim_words($plain, 55, "\u{2026}"));
            } else {
                $summary = trim(wp_kses($para_html, ["em" => [], "i" => []]));
            }
        } else {
            $rest = trim(wp_strip_all_tags($content));
            if ($rest === "" && has_excerpt($post_id)) {
                $rest = trim(wp_strip_all_tags(get_the_excerpt($post_id)));
            }
            $summary =
                $rest !== ""
                    ? esc_html(wp_trim_words($rest, 55, "\u{2026}"))
                    : "";
        }

        // Guarantee the heading: if the body carried none, build the canonical
        // "What Is {subtype}?" so the summary always leads with it.
        if ($summary !== "" && $summary_heading === "") {
            $summary_heading = "What Is " . $title . "?";
        }

        // Core facts always show (with an Unknown fallback); the extras show
        // only when the subtype carries them.
        $facts = [
            ["Gene", $display_gene, $gene_known],
            ["Inheritance", $inherit !== "" ? $inherit : "Unknown", false],
            ["Discovered", $year !== "" ? $year : "Unknown", false],
        ];
        if ($chromosome !== "") {
            $facts[] = ["Chromosome", $chromosome, false];
        }
        if ($neuropathy !== "") {
            $facts[] = ["Neuropathy", $neuropathy, false];
        }

        ob_start();
        ?>
        <article class="eic-dr-spotlight" data-cmt-type="<?php echo esc_attr(
            $type_class
        ); ?>">
          <div class="eic-dr-spotlight__main">
            <p class="eic-dr-spotlight__eyebrow">Featured Subtype</p>
            <div class="eic-dr-spotlight__head">
              <span class="eic-dr-spotlight__dot" aria-hidden="true"></span>
              <h3 class="eic-dr-spotlight__title">
                <a class="eic-dr-spotlight__link" href="<?php echo esc_url(
                    $permalink
                ); ?>"><?php echo esc_html($title); ?></a>
              </h3>
            </div>
            <?php if ($alias !== ""): ?>
              <p class="eic-dr-spotlight__alias">aka: <?php echo esc_html(
                  $alias
              ); ?></p>
            <?php endif; ?>
            <div class="eic-dr-spotlight__attrs">
              <?php foreach ($facts as $f): ?>
                <div class="eic-dr-spotlight__attr">
                  <span class="eic-dr-spotlight__label"><?php echo esc_html(
                      $f[0]
                  ); ?></span>
                  <span class="eic-dr-spotlight__value<?php echo $f[2]
                      ? " eic-dr-spotlight__gene is-gene"
                      : ""; ?>"><?php echo esc_html($f[1]); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="eic-dr-spotlight__summary">
            <?php if ($summary !== ""): ?>
              <div class="eic-dr-spotlight__summary-body">
                <?php if ($summary_heading !== ""): ?>
                  <h4 class="eic-dr-spotlight__summary-title"><?php echo esc_html(
                      $summary_heading
                  ); ?></h4>
                <?php endif; ?>
                <p class="eic-dr-spotlight__summary-text"><?php echo $summary; ?></p>
              </div>
            <?php endif; ?>
            <span class="eic-dr-spotlight__cta" aria-hidden="true">View subtype <span class="eic-dr-spotlight__cta-arrow">&rarr;</span></span>
          </div>
        </article>
        <?php return trim(ob_get_clean());
    }
}

/**
 * [dr_showcase] — the curated Dorsal Root post list (posts only).
 */
add_shortcode("dr_showcase", function ($atts) {
    $atts = shortcode_atts(["heading" => ""], $atts, "dr_showcase");

    if (!function_exists("eic_dr_render_list_item")) {
        return "";
    }

    $ids = function_exists("get_field")
        ? get_field("dr_showcase_posts", "option")
        : null;

    if (!is_array($ids)) {
        return "";
    }

    $rows = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && get_post_status($id) === "publish") {
            $rows[] = eic_dr_render_list_item($id);
        }
    }

    if (empty($rows)) {
        return "";
    }

    ob_start();
    ?>
    <section class="eic-dr-showcase"<?php echo $atts["heading"] !== ""
        ? ' aria-labelledby="eic-dr-showcase-heading"'
        : ' aria-label="From The Dorsal Root"'; ?>>
      <?php if ($atts["heading"] !== ""): ?>
        <h2 id="eic-dr-showcase-heading" class="eic-dr-showcase__heading"><?php echo esc_html(
            $atts["heading"]
        ); ?></h2>
      <?php endif; ?>
      <div class="dr-list">
        <?php echo implode("", $rows); ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
});

/**
 * [featured_subtype] — the optional Featured Subtype spotlight, placed
 * independently of the showcase.
 */
add_shortcode("featured_subtype", function ($atts) {
    if (!function_exists("get_field")) {
        return "";
    }

    $sub_id = (int) get_field("dr_showcase_subtype", "option");
    if ($sub_id <= 0) {
        return "";
    }

    return eic_dr_render_subtype_spotlight($sub_id);
});
