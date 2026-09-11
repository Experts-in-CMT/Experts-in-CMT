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
     GROUP: Variant (variant resolver; leads the page)
     ========================================= -->
<?php if (!empty($results["variants"])): ?>
    <div class="ps-group ps-group--variant">
        <h3 class="ps-group__title">
            <?php echo (count($results["variants"]) === 1
                ? "Variant"
                : "Variants") . " Related to Your Search"; ?>
        </h3>
        <ul class="ps-list ps-variants">
            <?php foreach ($results["variants"] as $v): ?>
                <?php
                $gene = (string) ($v["gene"] ?? "");
                $gene_url = (string) ($v["gene_url"] ?? "");
                $card_url = (string) ($v["card_url"] ?? "");
                $query = (string) ($v["query"] ?? "");

                $gene_pill = $gene === ""
                    ? ""
                    : ($gene_url
                        ? '<a href="' . esc_url($gene_url) . '" class="ps-pill ps-f-slate">' . esc_html($gene) . "</a>"
                        : '<span class="ps-pill ps-f-slate">' . esc_html($gene) . "</span>");

                if (empty($v["found"])) {
                    // Parsed cleanly, but ClinVar records no P/LP variant by
                    // that name at this gene (or at any CMT gene EIC catalogs)
                    echo '<li class="ps-item ps-variant ps-variant--none">';
                    echo $gene_pill;
                    $name_html = '<span class="ps-variant__name">' . esc_html($query) . "</span>";
                    echo '<span class="ps-variant__note">' .
                        ($gene !== ""
                            ? "No indexed pathogenic or likely pathogenic ClinVar record with assertion criteria matches " . $name_html . " in " . esc_html($gene) . "."
                            : "No indexed pathogenic or likely pathogenic ClinVar record with assertion criteria matches " . $name_html . " in any CMT gene cataloged by EIC.") .
                        "</span>";
                    if ($card_url) {
                        echo '<a class="ps-variant__card" href="' . esc_url($card_url) . '">View ' . esc_html($gene) . "'s ClinVar Variants</a>";
                    }
                    if (!empty($v["near"]) && is_array($v["near"])) {
                        // Same residues, position a digit off: T424M → p.Thr1424Met
                        echo '<div class="ps-variant__near"><span class="ps-variant__near-label">Did you mean:</span> ';
                        $links = [];
                        foreach ($v["near"] as $n) {
                            $n_name = (string) ($n["protein3"] ?: ($n["title"] ?? $n["vcv"] ?? ""));
                            $n_gene = (string) ($n["gene"] ?? "");
                            $n_url = (string) ($n["card_url"] ?? "");
                            $label = ($n_gene !== "" ? '<span class="ps-variant__near-gene">' . esc_html($n_gene) . "</span> " : "") . esc_html($n_name);
                            $links[] = $n_url
                                ? '<a href="' . esc_url($n_url) . '">' . $label . "</a>"
                                : "<span>" . $label . "</span>";
                        }
                        echo implode('<span class="ps-variant__near-sep">·</span>', $links);
                        echo "</div>";
                    }
                    echo "</li>";
                    continue;
                }

                $protein3 = (string) ($v["protein3"] ?? "");
                $protein1 = (string) ($v["protein1"] ?? "");
                $cdna = (string) ($v["cdna"] ?? "");
                $rsid = (string) ($v["rsid"] ?? "");
                $vcv = (string) ($v["vcv"] ?? "");
                $cls = (string) ($v["classification"] ?? "");
                $stars = max(0, min(4, (int) ($v["stars"] ?? 0)));
                $tier = (string) ($v["tier"] ?? "");
                $name = $protein3 !== "" ? $protein3 : ($cdna !== "" ? $cdna : (string) ($v["title"] ?? $vcv));
                $lp = stripos($cls, "likely") !== false && stripos($cls, "pathogenic/") !== 0;
                $tier_label = ["A" => "Reported in CMT", "B" => "No Recorded Disease", "C" => "Reported in Other Diseases"][$tier] ?? "";
                ?>
                <li class="ps-item ps-variant">
                    <?php echo $gene_pill; ?>
                    <span class="ps-variant__name"><?php echo esc_html($name); ?><?php if ($protein1 !== "" && $protein3 !== "") {
                        echo ' <small class="ps-variant__short">' . esc_html($protein1) . "</small>";
                    } ?></span>
                    <?php if ($cdna !== "" && $protein3 !== ""): ?>
                        <span class="ps-variant__cdna"><?php echo esc_html($cdna); ?></span>
                    <?php endif; ?>
                    <?php if ($cls !== ""): ?>
                        <span class="ps-variant__cls ps-variant__cls--<?php echo $lp ? "lp" : "p"; ?>"><?php echo esc_html($cls); ?></span>
                    <?php endif; ?>
                    <span class="ps-variant__stars" aria-hidden="true"><?php
                        // Hidden glyphs + a text equivalent, the gene card's pattern:
                        // aria-label on a plain span is exposed unreliably
                        for ($i = 1; $i <= 4; $i++) {
                            echo '<span class="ps-star' . ($i <= $stars ? " ps-star--on" : "") . '">' . ($i <= $stars ? "&#9733;" : "&#9734;") . "</span>";
                        }
                    ?></span><span class="screen-reader-text"><?php echo esc_html($stars . " of 4 ClinVar review stars"); ?></span>
                    <?php if ($tier_label !== ""): ?>
                        <span class="ps-variant__tier"><?php echo esc_html($tier_label); ?></span>
                    <?php endif; ?>
                    <span class="ps-variant__links"><?php
                        if ($card_url) {
                            echo '<a class="ps-variant__card" href="' . esc_url($card_url) . '">View on the ' . esc_html($gene) . " gene page</a>";
                        }
                        if (!empty($v["url"])) {
                            echo '<a class="ps-variant__ext" href="' . esc_url((string) $v["url"]) . '" target="_blank" rel="noopener">' .
                                esc_html($vcv !== "" ? $vcv : "ClinVar") .
                                '<span class="screen-reader-text"> (opens in a new tab)</span></a>';
                        }
                        if ($rsid !== "") {
                            echo '<span class="ps-variant__rs">' . esc_html($rsid) . "</span>";
                        }
                    ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

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
