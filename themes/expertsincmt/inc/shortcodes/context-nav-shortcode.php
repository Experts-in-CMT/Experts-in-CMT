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
 *  Shortcode: [context_nav]
 * ------------------------------------------------------------
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
        "what-is-cmt",
        "breathing",
        "gene",
    ];

    if (!in_array($post_type, $supported_types, true)) {
        return "";
    }

    // Label sets
    $labels = [
        "post" => [
            "prev"       => "← Previous Post",
            "next"       => "Next Post →",
            "back_label" => "Return to The Dorsal Root",
        ],
        "subtype" => [
            "prev"       => "← Previous Subtype",
            "next"       => "Next Subtype →",
            "back_label" => "Return to Subtypes",
        ],
        "glossary" => [
            "prev"       => "← Previous Term",
            "next"       => "Next Term →",
            "back_label" => "Return to The Glossary",
        ],
        "what-is-cmt" => [
            "prev"       => "← Previous Topic",
            "next"       => "Next Topic →",
            "back_label" => "Return to What Is CMT",
        ],
        "breathing" => [
            "prev"       => "← Previous Topic",
            "next"       => "Next Topic →",
            "back_label" => "Return to CMT and Breathing",
        ],
        "gene" => [
            "prev"       => "← Previous Gene",
            "next"       => "Next Gene →",
            "back_label" => "Return to the Gene Browser",
        ],
    ];

    // Back URL logic
    if ($post_type === "post") {
        $back_url = home_url("/dorsal-root/#blog");
    } elseif ($post_type === "subtype") {
        $back_url = function_exists("eic_subtype_browser_page_url")
            ? eic_subtype_browser_page_url() . "#ui"
            : home_url("/genetics/cmt-subtype-browser/#ui");
    } elseif ($post_type === "glossary") {
        $back_url = home_url("/cmt-words/#results");
    } elseif ($post_type === "what-is-cmt") {
        $back_url = home_url("/what-is-cmt/#topics");
    } elseif ($post_type === "breathing") {
        $back_url = home_url("/cmt-and-breathing/#topics");
    } elseif ($post_type === "gene") {
        $back_url = function_exists("eic_ps_gene_browser_page_url")
            ? eic_ps_gene_browser_page_url()
            : home_url("/genetics/cmt-gene-browser/");
    } else {
        $back_url = home_url("/");
    }

    // Determine prev/next IDs
    $prev_id = $next_id = null;

// ============================================================
// POSTS (Dorsal Root) — publish date ASC (oldest → newest)
// ============================================================
if ($post_type === "post") {

    $ids = get_posts([
        "post_type"      => "post",
        "posts_per_page" => -1,
        "orderby"        => "date",
        "order"          => "ASC",
        "fields"         => "ids",
        "no_found_rows"  => true,
        "post_status"    => "publish",
    ]);

    if ($ids && in_array($post->ID, $ids, true)) {
        $i       = array_search($post->ID, $ids, true);
        $prev_id = $ids[$i - 1] ?? null;
        $next_id = $ids[$i + 1] ?? null;
    }


    // ============================================================
    // Deterministic Topic Order Logic (meta: topic_order)
    // ============================================================
    } elseif ($post_type === "what-is-cmt" || $post_type === "breathing") {

        $posts = get_posts([
            "post_type"      => $post_type,
            "posts_per_page" => -1,
            "meta_key"       => "topic_order",
            "orderby"        => "meta_value_num",
            "order"          => "ASC",
            "fields"         => "ids",
            "no_found_rows"  => true,
            "post_status"    => "publish",
        ]);

        if (empty($posts)) {
            $posts = get_posts([
                "post_type"      => $post_type,
                "posts_per_page" => -1,
                "orderby"        => "title",
                "order"          => "ASC",
                "fields"         => "ids",
                "no_found_rows"  => true,
                "post_status"    => "publish",
            ]);
        }

        if ($posts && in_array($post->ID, $posts, true)) {
            $i       = array_search($post->ID, $posts, true);
            $prev_id = $posts[$i - 1] ?? null;
            $next_id = $posts[$i + 1] ?? null;
        }

    // ============================================================
    // Canonical Subtype Ordering (ACF type_classification)
    // ============================================================
    } elseif ($post_type === "subtype") {

        $ids = get_posts([
            "post_type"      => "subtype",
            "posts_per_page" => -1,
            "post_status"    => "publish",
            "fields"         => "ids",
            "no_found_rows"  => true,
        ]);

        if (!empty($ids)) {

            $items = [];

            foreach ($ids as $id) {
                $raw = strtolower(trim((string) get_post_meta($id, "type_classification", true)));

                // normalize slash variants
                $raw = str_replace("/", "", $raw);

                $items[] = [
                    "id"    => $id,
                    "type"  => $raw,
                    "title" => get_the_title($id),
                ];
            }

            $order_map = [
                "cmt1"        => 1,
                "cmt2"        => 2,
                "cmt4"        => 3,
                "cmtx"        => 4,
                "cmtdi"       => 5,
                "cmtri"       => 6,
                "dhmnhmn"     => 7,
                "dhmn"        => 7,
                "dsma"        => 8,
                "gan"         => 9,
                "hmsn"        => 10,
                "hsan"        => 11,
                "hsn"         => 12,
                "smalep"      => 13,
                "smaleph"     => 13,
                "sma-lep"     => 13,
                "unclassified"=> 14,
            ];

            usort($items, function ($a, $b) use ($order_map) {
                $a_rank = $order_map[$a["type"]] ?? 999;
                $b_rank = $order_map[$b["type"]] ?? 999;

                if ($a_rank === $b_rank) {
                    return strcasecmp($a["title"], $b["title"]);
                }

                return $a_rank <=> $b_rank;
            });

            $ordered_ids = array_column($items, "id");

            if (in_array($post->ID, $ordered_ids, true)) {
                $i       = array_search($post->ID, $ordered_ids, true);
                $prev_id = $ordered_ids[$i - 1] ?? null;
                $next_id = $ordered_ids[$i + 1] ?? null;
            }
        }

    // ============================================================
    // GLOSSARY / RESOURCES (title ASC)
    // ============================================================
    } else {

        $ids = get_posts([
            "post_type"      => $post_type,
            "posts_per_page" => -1,
            "orderby"        => "title",
            "order"          => "ASC",
            "fields"         => "ids",
            "no_found_rows"  => true,
            "post_status"    => "publish",
        ]);

        if ($ids && in_array($post->ID, $ids, true)) {
            $i       = array_search($post->ID, $ids, true);
            $prev_id = $ids[$i - 1] ?? null;
            $next_id = $ids[$i + 1] ?? null;
        }
    }

    $L = $labels[$post_type];

    // Gene pages name the neighbor: a gene post's title is its symbol.
    // The visible label is the arrow plus the symbol, which tells a screen
    // reader nothing about direction, so the anchor carries the full
    // sentence as an aria-label.
    $prev_aria = "";
    $next_aria = "";
    if ($post_type === "gene") {
        if ($prev_id) {
            $L["prev"] = "\u{2190} " . get_the_title($prev_id);
            $prev_aria = "Previous gene: " . get_the_title($prev_id);
        }
        if ($next_id) {
            $L["next"] = get_the_title($next_id) . " \u{2192}";
            $next_aria = "Next gene: " . get_the_title($next_id);
        }
    }

    ob_start();
    ?>
    <div class="eicmt-ctnav-wrap">
      <nav class="eicmt-ctnav" aria-label="Post navigation">

  <div class="eicmt-ctnav__col eicmt-ctnav__col--prev">
  <?php if ($prev_id): ?>
    <a class="eicmt-ctnav__link" href="<?php echo esc_url(get_permalink($prev_id)); ?>"<?php echo $prev_aria !== "" ? ' aria-label="' . esc_attr($prev_aria) . '"' : ""; ?>>
      <span class="ctnav-prev-label">
        <?php echo esc_html($L["prev"]); ?>
      </span>
    </a>
  <?php endif; ?>
</div>

<div class="eicmt-ctnav__col eicmt-ctnav__col--back">
  <a class="eicmt-ctnav__link"
     data-return-key="<?php echo esc_attr($post_type); ?>"
     href="<?php echo esc_url($back_url); ?>">
    <span class="ctnav-back-label">
      <?php echo esc_html($L["back_label"]); ?>
    </span>
  </a>
</div>

<div class="eicmt-ctnav__col eicmt-ctnav__col--next">
  <?php if ($next_id): ?>
    <a class="eicmt-ctnav__link" href="<?php echo esc_url(get_permalink($next_id)); ?>"<?php echo $next_aria !== "" ? ' aria-label="' . esc_attr($next_aria) . '"' : ""; ?>>
      <span class="ctnav-next-label">
        <?php echo esc_html($L["next"]); ?>
      </span>
    </a>
  <?php endif; ?>
</div>


      </nav>
    </div>
    <?php
    return ob_get_clean();
});
