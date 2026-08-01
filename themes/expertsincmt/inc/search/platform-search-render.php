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
        <h2 class="ps-group__title">
            <?php echo (count($results["types"]) === 1
                ? "Type/Classification"
                : "Types/Classifications") . " Related to Your Search"; ?>
        </h2>
        <ul class="ps-list">
            <?php foreach ($results["types"] as $item): ?>
                <?php if (empty($item["label"]) || empty($item["url"])) {
                    continue;
                } ?>
                <li class="ps-item">
                    <a href="<?php echo esc_url(
                        $item["url"]
                    ); ?>" class="ps-item__title"><?php echo esc_html(
    $item["label"]
); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- =========================================
     GROUP: Subtype
     ========================================= -->
<?php if (!empty($results["subtypes"])): ?>
    <div class="ps-group ps-group--subtype">
        <h2 class="ps-group__title">
            <?php echo (count($results["subtypes"]) === 1
                ? "Subtype"
                : "Subtypes") . " Related to Your Search"; ?>

       </h2>
        <ul class="ps-list">
            <?php foreach ($results["subtypes"] as $item): ?>
                <li class="ps-item"><a href="<?php echo esc_url(
                    $item["url"] ?? ""
                ); ?>" class="ps-item__title"><?php echo esc_html(
    $item["label"] ?? ""
); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- =========================================
     GROUP: Genes
     ========================================= -->
<?php if (!empty($results["genes"])): ?>
    <div class="ps-group ps-group--genes">
        <h2 class="ps-group__title">
            <?php echo (count($results["genes"]) === 1 ? "Gene" : "Genes") .
                " Related to Your Search"; ?>
       </h2>
        <ul class="ps-list">
            <?php foreach ($results["genes"] as $item): ?>
                <?php
                $label = $item["label"] ?? "";
                $url = $item["url"] ?? "";
                if ($label === "") {
                    continue;
                }
                ?>
                <li class="ps-item"><?php echo $url
                    ? '<a href="' .
                        esc_url($url) .
                        '" class="ps-item__title">' .
                        esc_html($label) .
                        "</a>"
                    : '<span class="ps-item__title">' .
                        esc_html($label) .
                        "</span>"; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>



 <!-- =========================================
     GROUP: Content
     ========================================= -->
<?php if (!empty($results["content"])): ?>
    <div class="ps-group ps-group--content">
        <h2 class="ps-group__title">Content Related to Your Search</h2>

        <ul class="ps-list">
            <?php foreach ($results["content"] as $item): ?>
                <?php
                $label = $item["label"] ?? "";
                $url = $item["url"] ?? "";

                if ($label === "" || $url === "") {
                    continue;
                }

                $type =
                    $item["type"] === "Post"
                        ? "The Dorsal Root"
                        : $item["type"] ?? "";
                ?>
                <li class="ps-item">
                    <?php if ($type): ?>
                        <div class="ps-item__meta">
                            <?php echo esc_html($type); ?>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo esc_url($url); ?>"
                       class="ps-item__title">
                        <?php echo esc_html($label); ?>
                    </a>

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
