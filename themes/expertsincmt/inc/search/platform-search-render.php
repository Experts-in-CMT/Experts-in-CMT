<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Platform Search Renderer
 *
 * Since version: 1.8.0
 * Feature: platform-search
 *
 * Purpose:
 * --------
 * Responsible ONLY for rendering structured, clickable search results
 * to the screen. This file:
 *
 * - Accepts resolved search data
 * - Outputs semantic, hierarchy-safe markup
 * - Does NOT resolve search logic
 * - Does NOT perform queries
 * - Does NOT assume AJAX or transport layer
 *
 * This is a rendering layer only.
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Render platform search results.
 *
 * @param array $results Structured results from resolver
 * @return string HTML output
 */
function eic_render_platform_search_results(array $results = [])
{
    // Normalize: allow either wrapped payload OR flattened results
    if (isset($results["results"]) && is_array($results["results"])) {
        $results = $results["results"];
    }

    ob_start();
    ?>
<section class="platform-search-results">

<!-- =========================================
     GROUP: Type
     ========================================= -->
<?php if (!empty($results["types"])): ?>
    <div class="ps-group ps-group--type">
        <h3 class="ps-group__title">
            <?php echo (count($results["types"]) === 1
                ? "Type/Classification"
                : "Types/Classifications") . " Related to Your Search"; ?>
        </h3>
        <ul class="ps-list">
            <?php foreach ($results["types"] as $item): ?>
                <?php
                if (empty($item["label"]) || empty($item["url"])) {
                    continue;
                }

                // Family color derives from the label ("CMT2" → green);
                // "Unclassified Subtypes" falls through to grey
                $family = function_exists("eic_ps_type_family")
                    ? eic_ps_type_family(strtolower($item["label"]))
                    : "grey";
                ?>
                <li class="ps-item">
                    <a href="<?php echo esc_url(
                        $item["url"]
                    ); ?>" class="ps-item__title ps-pill ps-f-<?php echo esc_attr(
    $family
); ?>"><?php echo esc_html($item["label"]); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- =========================================
     GROUP: Subtype
     ========================================= -->
<?php if (!empty($results["subtypes"])): ?>
    <div class="ps-group ps-group--subtype">
        <h3 class="ps-group__title">
            <?php echo (count($results["subtypes"]) === 1
                ? "Subtype"
                : "Subtypes") . " Related to Your Search"; ?>

       </h3>
        <?php
        /**
         * Handoff-less overflow (arbitrary sets with no browser
         * filter): render every pill but mark those past the
         * preview; JS collapses them behind "Show all N". A no-JS
         * visit simply sees the full list. Mutually exclusive with
         * the "Showing 5 of N" handoff (subtypes_total).
         */
        $ps_preview = (int) apply_filters("eic_ps_subtype_preview_limit", 5);
        $ps_subtype_count = count($results["subtypes"]);
        $ps_overflow =
            empty($results["subtypes_total"]) && $ps_subtype_count > $ps_preview;
        ?>
        <ul class="ps-list"<?php echo $ps_overflow
            ? ' data-collapsible="1"'
            : ""; ?>>
            <?php foreach ($results["subtypes"] as $ps_i => $item): ?>
                <?php
                // Subtype pills wear their parent type's family color
                $family =
                    function_exists("eic_ps_type_family") &&
                    function_exists("eic_ps_subtype_field")
                        ? eic_ps_type_family(
                            eic_ps_subtype_field(
                                (int) ($item["id"] ?? 0),
                                "type_classification"
                            )
                        )
                        : "grey";

                $ps_item_class =
                    $ps_overflow && $ps_i >= $ps_preview
                        ? "ps-item ps-item--more"
                        : "ps-item";
                ?>
                <li class="<?php echo esc_attr(
                    $ps_item_class
                ); ?>"><a href="<?php echo esc_url(
    $item["url"] ?? ""
); ?>" class="ps-item__title ps-pill ps-f-<?php echo esc_attr(
    $family
); ?>"><?php echo esc_html($item["label"] ?? ""); ?></a></li>
            <?php endforeach; ?>
        </ul>

        <?php if ($ps_overflow) {
            // Hidden until JS activates the collapse
            echo '<p class="ps-view-all"><button type="button" class="ps-show-all" hidden>Show all ' .
                (int) $ps_subtype_count .
                " subtypes</button></p>";
        } ?>

        <?php if (
            !empty($results["subtypes_total"]) &&
            !empty($results["more_url"])
        ) {
            // Single-line output: newlines inside this paragraph render
            // literally in this theme context instead of collapsing.
            echo '<p class="ps-view-all">Showing ' .
                count($results["subtypes"]) .
                " of " .
                (int) $results["subtypes_total"] .
                ' matching subtypes. <a href="' .
                esc_url($results["more_url"]) .
                '">See them all in the CMT Subtype Browser</a></p>';
        } ?>
    </div>
<?php endif; ?>

<!-- =========================================
     GROUP: Genes
     ========================================= -->
<?php if (!empty($results["genes"])): ?>
    <div class="ps-group ps-group--genes">
        <h3 class="ps-group__title">
            <?php echo (count($results["genes"]) === 1 ? "Gene" : "Genes") .
                " Related to Your Search"; ?>
       </h3>
        <ul class="ps-list">
            <?php foreach ($results["genes"] as $item): ?>
                <?php
                $label = $item["label"] ?? "";
                $url = $item["url"] ?? "";
                if ($label === "") {
                    continue;
                }
                ?>
                <li class="ps-item"><?php
                // Genes are classification-neutral: slate pills
                echo $url
                    ? '<a href="' .
                        esc_url($url) .
                        '" class="ps-item__title ps-pill ps-f-slate">' .
                        esc_html($label) .
                        "</a>"
                    : '<span class="ps-item__title ps-pill ps-f-slate">' .
                        esc_html($label) .
                        "</span>";
                ?></li>
            <?php endforeach; ?>
        </ul>

        <?php
        $genes_more = $results["genes_more_url"] ?? ($results["more_url"] ?? "");
        $genes_dest = !empty($results["genes_more_url"])
            ? "CMT Gene Browser"
            : "CMT Subtype Browser";

        if (!empty($results["genes_total"]) && $genes_more) {
            // Single-line output (same literal-newline quirk as above)
            echo '<p class="ps-view-all">Showing ' .
                count($results["genes"]) .
                " of " .
                (int) $results["genes_total"] .
                ' matching genes. <a href="' .
                esc_url($genes_more) .
                '">See them all in the ' .
                esc_html($genes_dest) .
                "</a></p>";
        } ?>
    </div>
<?php endif; ?>



 <!-- =========================================
     GROUP: Content
     ========================================= -->
<?php if (!empty($results["content"])): ?>
    <div class="ps-group ps-group--content">
        <h3 class="ps-group__title">Content Related to Your Search</h3>

        <ul class="ps-list">
            <?php foreach ($results["content"] as $item): ?>
                <?php
                $label = $item["label"] ?? "";
                $url = $item["url"] ?? "";

                if ($label === "" || $url === "") {
                    continue;
                }

                /**
                 * Source labels are reader-facing bylines, not CPT
                 * labels. Pages get no suffix at all — a page's
                 * source is simply the site.
                 */
                $source_map = [
                    "Post" => "The Dorsal Root",
                    "Page" => "",
                    "What Is CMT Topic" => "What Is CMT",
                    "CMT and Breathing Topic" => "CMT and Breathing",
                    "Term" => "Glossary",
                ];

                $raw_type = $item["type"] ?? "";
                $type = $source_map[$raw_type] ?? $raw_type;
                ?>
                <li class="ps-item">
                    <?php
                    // Single line, no internal newlines (this page
                    // context renders them literally). Source rides
                    // the title as a muted suffix OUTSIDE the anchor.
                    echo '<a href="' .
                        esc_url($url) .
                        '" class="ps-item__title">' .
                        esc_html($label) .
                        "</a>";

                    if ($type) {
                        echo '<span class="ps-item__source">&nbsp;| ' .
                            esc_html($type) .
                            "</span>";
                    }
                    ?>

                    <?php if (!empty($item["excerpt"])): ?>
                        <div class="ps-item__excerpt">
                            <?php echo wp_kses_post($item["excerpt"]); ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


</section>


    <?php return ob_get_clean();
}
