<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Pages — MedicalWebPage JSON-LD Schema Injection
 * ------------------------------------------------------------
 * Outputs a MedicalWebPage JSON-LD block in <head> for
 * WordPress Pages that have schema markup fields populated.
 * Pulls from ACF fields registered in pages-fields.php to
 * provide structured medical content signals for Google and
 * answer engines.
 *
 * Only fires when at least one Medical Specialty is selected.
 * Pages with no specialty selection are skipped entirely,
 * which excludes non-medical pages (About, Privacy, etc.)
 * from receiving a MedicalWebPage schema block.
 *
 * The `about` node is gated on specialty presence, treating
 * a populated specialty field as the signal that the page
 * is medical in nature and pertains to CMT.
 *
 * Mentions are derived programmatically from internal links
 * in post_content, typed by URL pattern. Only links matching
 * known medical path prefixes are included.
 *
 * Additive: does not replace or modify Yoast WebPage schema.
 *
 * Location:
 *   /inc/acf/pages-jsonld.php
 */

defined("ABSPATH") || exit();

add_action(
    "wp_head",
    function () {
        if (!is_page()) {
            return;
        }
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // Bail early if no specialties are selected — page is not medical.
        $specialties = get_field("medical_specialty", $post_id);
        if (empty($specialties)) {
            return;
        }

        $title = get_the_title($post_id);
        $url = get_permalink($post_id);
        $aspect = trim((string) get_field("aspect", $post_id));
        $audience = get_field("medical_audience", $post_id);
        $last_reviewed = trim(
            (string) get_field("last_reviewed_date", $post_id)
        );
        $reviewed_by_name = trim(
            (string) get_field("reviewed_by_name", $post_id)
        );
        $reviewed_by_type = trim(
            (string) get_field("reviewed_by_type", $post_id)
        );
        $date_published = get_the_date("Y-m-d", $post_id);
        $date_modified = get_the_modified_date("Y-m-d", $post_id);

        // Build specialty array — always an array, even for a single value.
        $specialty_types = is_array($specialties)
            ? $specialties
            : [$specialties];
        $specialty_nodes = array_map(function ($s) {
            return [
                "@type" => "MedicalSpecialty",
                "name" => $s,
            ];
        }, $specialty_types);

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "MedicalWebPage",
            "name" => $title,
            "url" => $url,
            "inLanguage" => "en-US",
            "datePublished" => $date_published,
            "dateModified" => $date_modified,
            "specialty" => $specialty_nodes,
            "about" => [
                "@type" => "MedicalCondition",
                "name" => "Charcot-Marie-Tooth disease",
                "alternateName" => "CMT",
                "url" => "https://expertsincmt.org/what-is-cmt/",
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => "Experts in CMT",
                "url" => "https://expertsincmt.org/",
            ],
            "isPartOf" => [
                "@type" => "WebSite",
                "name" => "Experts in CMT",
                "url" => "https://expertsincmt.org/",
            ],
        ];

        if ($aspect !== "") {
            $schema["aspect"] = ucfirst($aspect);
        }

        if (!empty($audience)) {
            $audience_types = is_array($audience) ? $audience : [$audience];
            $schema["audience"] = array_map(function ($a) {
                return [
                    "@type" => "MedicalAudience",
                    "audienceType" => $a,
                ];
            }, $audience_types);
        }

        if ($last_reviewed !== "") {
            $schema["lastReviewed"] = $last_reviewed;
        }

        if ($reviewed_by_name !== "") {
            $type = $reviewed_by_type !== "" ? $reviewed_by_type : "Person";
            $schema["reviewedBy"] = [
                "@type" => $type,
                "name" => $reviewed_by_name,
            ];
        }

        // Mentions: parse internal links from post_content.
        // Only links matching known medical path prefixes are typed and included.
        $post = get_post($post_id);
        $content = $post ? $post->post_content : "";
        $mentions = [];
        if ($content !== "") {
            $site_url = home_url();
            preg_match_all(
                '/<a\s[^>]*href=["\'](' .
                    preg_quote($site_url, "/") .
                    '[^"\']*)["\'][^>]*>(.*?)<\/a>/i',
                $content,
                $matches,
                PREG_SET_ORDER
            );
            $seen_urls = [];
            foreach ($matches as $match) {
                $href = trim($match[1]);
                $text = trim(wp_strip_all_tags($match[2]));
                if (in_array($href, $seen_urls, true) || $text === "") {
                    continue;
                }
                $seen_urls[] = $href;
                $path = str_replace($site_url, "", $href);
                if (preg_match("#^/subtype/#", $path)) {
                    $mention_type = "MedicalCondition";
                } elseif (preg_match("#^/glossary/#", $path)) {
                    $mention_type = "MedicalEntity";
                } elseif (preg_match("#^/what-is-cmt/#", $path)) {
                    $mention_type = "MedicalWebPage";
                } elseif (preg_match("#^/cmt-and-breathing/#", $path)) {
                    $mention_type = "MedicalWebPage";
                } else {
                    continue;
                }
                $mentions[] = [
                    "@type" => $mention_type,
                    "name" => $text,
                    "url" => $href,
                ];
            }
        }
        if (!empty($mentions)) {
            $schema["mentions"] = $mentions;
        }

        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        echo "\n" . "</script>" . "\n";
    },
    10
);
