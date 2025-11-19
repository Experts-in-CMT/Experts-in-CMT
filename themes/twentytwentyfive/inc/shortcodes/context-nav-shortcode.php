<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 *  Shortcode: [context_nav]
 * ------------------------------------------------------------
 *  Renders the unified Previous / Back / Next navigation used
 *  across all Experts in CMT content engines:
 *
 *  • Dorsal Root (post)
 *  • Subtypes (subtype)
 *  • Glossary (glossary)
 *  • Resources (resource)
 *  • What Is CMT (what-is-cmt)
 *  • CMT and Breathing (breathing)
 *
 *  Logic:
 *  - Only renders on singular views
 *  - Auto-detects CPT and applies correct labels + back URL
 *  - Prev/Next are alphabetical by title for CPTs
 *  - Posts use WP's built-in adjacent navigation
 *
 *  This file is loaded automatically via the shortcode loader
 *  in functions.php (modular /inc/shortcodes/ autoload).
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("context_nav", function () {
    if (!is_singular() || is_admin()) {
        return "";
    }

    global $post;
    if (empty($post) || empty($post->ID)) {
        return "";
    }

    $post_type = get_post_type($post);

    // Supported CPTs
    $supported_types = [
        "post",
        "subtype",
        "glossary",
        "resource",
        "what-is-cmt",
        "breathing",
    ];

    if (!in_array($post_type, $supported_types, true)) {
        return "";
    }

    // Label sets
    $labels = [
        "post" => [
            "prev" => "← Previous Post",
            "next" => "Next Post →",
            "back_label" => "Return to The Dorsal Root",
        ],
        "subtype" => [
            "prev" => "← Previous Subtype",
            "next" => "Next Subtype →",
            "back_label" => "Return to Subtypes",
        ],
        "glossary" => [
            "prev" => "← Previous Term",
            "next" => "Next Term →",
            "back_label" => "Return to The Glossary",
        ],
        "resource" => [
            "prev" => "← Previous Resource",
            "next" => "Next Resource →",
            "back_label" => "Return to Resources",
        ],
        "what-is-cmt" => [
            "prev" => "← Previous Topic",
            "next" => "Next Topic →",
            "back_label" => "Return to What Is CMT",
        ],
        "breathing" => [
            "prev" => "← Previous Topic",
            "next" => "Next Topic →",
            "back_label" => "Return to CMT and Breathing",
        ],
    ];

    // Back URL logic
    if ($post_type === "post") {
        $back_url = home_url("/dorsal-root/#blog");
    } elseif ($post_type === "subtype") {
        $back_url = home_url("/cmt-genetics-database/#ui");
    } elseif ($post_type === "glossary") {
        $back_url = home_url("/cmt-words/#results");
    } elseif ($post_type === "resource") {
        $archive = get_post_type_archive_link("resource");
        $back_url = ($archive ?: home_url("/resources")) . "#resources";
    } elseif ($post_type === "what-is-cmt") {
        $back_url = home_url("/what-is-cmt/#topics");
    } elseif ($post_type === "breathing") {
        $back_url = home_url("/cmt-and-breathing/#topics");
    } else {
        $back_url = home_url("/");
    }

    // Determine prev/next IDs
    $prev_id = $next_id = null;

    if ($post_type === "post") {
        // Built-in WP adjacent navigation
        $prev = get_adjacent_post(false, "", true);
        $next = get_adjacent_post(false, "", false);

        if ($prev instanceof WP_Post) {
            $prev_id = $prev->ID;
        }
        if ($next instanceof WP_Post) {
            $next_id = $next->ID;
        }
    } else {
        // Alphabetical prev/next for CPTs
        $ids = get_posts([
            "post_type" => $post_type,
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
            "fields" => "ids",
            "no_found_rows" => true,
            "post_status" => "publish",
        ]);

        if ($ids && in_array($post->ID, $ids, true)) {
            $i = array_search($post->ID, $ids, true);
            $prev_id = $ids[$i - 1] ?? null;
            $next_id = $ids[$i + 1] ?? null;
        }
    }

    $L = $labels[$post_type];

    ob_start();
    ?>
    <div class="eicmt-ctnav-wrap">
      <nav class="eicmt-ctnav" aria-label="Post navigation">

        <div class="eicmt-ctnav__col eicmt-ctnav__col--prev">
          <?php if ($prev_id): ?>
            <a class="eicmt-ctnav__link" href="<?php echo esc_url(
                get_permalink($prev_id)
            ); ?>">
              <?php echo esc_html($L["prev"]); ?>
            </a>
          <?php endif; ?>
        </div>

        <div class="eicmt-ctnav__col eicmt-ctnav__col--back">
          <a class="eicmt-ctnav__link" href="<?php echo esc_url($back_url); ?>">
            <?php echo esc_html($L["back_label"]); ?>
          </a>
        </div>

        <div class="eicmt-ctnav__col eicmt-ctnav__col--next">
          <?php if ($next_id): ?>
            <a class="eicmt-ctnav__link" href="<?php echo esc_url(
                get_permalink($next_id)
            ); ?>">
              <?php echo esc_html($L["next"]); ?>
            </a>
          <?php endif; ?>
        </div>

      </nav>
    </div>
    <?php return ob_get_clean();
});
