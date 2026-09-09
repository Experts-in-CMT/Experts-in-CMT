<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Front Page — MedicalWebPage JSON-LD Schema
 * ------------------------------------------------------------
 * The site front page is a static Page of medical/educational
 * content about Charcot-Marie-Tooth disease, but it does not
 * carry the ACF schema fields that pages-jsonld.php keys on, so
 * no MedicalWebPage is emitted for it — only Yoast's generic
 * WebPage graph. That leaves the home page with no author and no
 * content-level medical type, which weakens how answer engines
 * attribute and cite it.
 *
 * This file emits ONE additive MedicalWebPage node for the front
 * page, attributing authorship and publishing to the Experts in
 * CMT organization by @id (Yoast's node, so the graph stays
 * connected rather than duplicated) and anchoring the page to the
 * CMT MedicalCondition entity.
 *
 * It defers to pages-jsonld.php: if the front page ever gets a
 * medical_specialty selected, that file emits the fuller block
 * and this one bails to avoid a duplicate MedicalWebPage.
 *
 * Additive: does not replace or modify Yoast's WebPage schema.
 *
 * Location:
 *   /inc/acf/frontpage-jsonld.php
 */

defined("ABSPATH") || exit();

add_action(
    "wp_head",
    function () {
        // Static front page only; skip a blog-posts front page.
        if (!is_front_page() || is_home()) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // Defer to pages-jsonld.php when the page carries specialty schema,
        // so the two never emit a duplicate MedicalWebPage.
        if (function_exists("get_field")) {
            $specialties = get_field("medical_specialty", $post_id);
            if (!empty($specialties)) {
                return;
            }
        }

        $base = trailingslashit(home_url());
        $org_id = $base . "#organization";
        $site_id = $base . "#website";

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "MedicalWebPage",
            "@id" => $base . "#medicalwebpage",
            "name" => get_the_title($post_id),
            "url" => $base,
            "inLanguage" => "en-US",
            "datePublished" => get_the_date("Y-m-d", $post_id),
            "dateModified" => get_the_modified_date("Y-m-d", $post_id),
            "about" => [
                "@type" => "MedicalCondition",
                "name" => "Charcot-Marie-Tooth disease",
                "alternateName" => "CMT",
                "url" => "https://expertsincmt.org/what-is-cmt/",
            ],
            // Authorship and publishing attributed to the real organization,
            // referenced by Yoast's @id so engines merge, not duplicate.
            "author" => ["@id" => $org_id],
            "publisher" => ["@id" => $org_id],
            "isPartOf" => ["@id" => $site_id],
        ];

        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode(
            $schema,
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        echo "\n" . "</script>" . "\n";
    },
    11
);
