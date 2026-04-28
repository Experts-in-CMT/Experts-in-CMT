<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Educational CPTs — MedicalWebPage JSON-LD Schema Injection
 * ------------------------------------------------------------
 * Outputs a MedicalWebPage JSON-LD block in <head> for
 * What Is CMT and CMT and Breathing topic posts. Pulls from
 * ACF fields to provide structured medical content signals
 * for Google and answer engines.
 *
 * Mentions are derived programmatically from internal links
 * in post_content, typed by URL pattern.
 *
 * Additive: does not replace or modify Yoast WebPage schema.
 *
 * Location:
 *   /inc/acf/educational-jsonld.php
 */

defined("ABSPATH") || exit();

add_action("wp_head", function () {
    if (!is_singular(["what-is-cmt", "breathing"])) {
        return;
    }
    $post_id = get_the_ID();
    if (!$post_id) {
        return;
    }
    $title = get_the_title($post_id);
    $url = get_permalink($post_id);
    $summary = trim((string) get_field("summary", $post_id));
    $aspect = trim((string) get_field("aspect", $post_id));
    $audience = get_field("medical_audience", $post_id);
    $last_reviewed = trim((string) get_field("last_reviewed_date", $post_id));
    $reviewed_by_name = trim((string) get_field("reviewed_by_name", $post_id));
    $reviewed_by_type = trim((string) get_field("reviewed_by_type", $post_id));
    $date_published = get_the_date("Y-m-d", $post_id);
    $date_modified = get_the_modified_date("Y-m-d", $post_id);
    $schema = [
        "@context" => "https://schema.org",
        "@type" => "MedicalWebPage",
        "name" => $title,
        "url" => $url,
        "inLanguage" => "en-US",
        "datePublished" => $date_published,
        "dateModified" => $date_modified,
        "specialty" => [
            "@type" => "MedicalSpecialty",
            "name" => "Neurology",
        ],
        "about" => [
            "@type" => "MedicalCondition",
            "name" => "Charcot-Marie-Tooth disease",
            "alternateName" => "CMT",
            "url" => get_post_type($post_id) === "breathing"
                ? "https://expertsincmt.org/cmt-and-breathing/"
                : "https://expertsincmt.org/what-is-cmt/",
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
    if ($summary !== "") {
        $schema["description"] = $summary;
    }
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
    // Mentions: parse internal links from post content
    $post = get_post($post_id);
    $content = $post ? $post->post_content : "";
    $mentions = [];
    if ($content !== "") {
        $site_url = home_url();
        preg_match_all('/<a\s[^>]*href=["\'](' . preg_quote($site_url, '/') . '[^"\']*)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);
        $seen_urls = [];
        foreach ($matches as $match) {
            $href = trim($match[1]);
            $text = trim(wp_strip_all_tags($match[2]));
            if (in_array($href, $seen_urls, true) || $text === "") {
                continue;
            }
            $seen_urls[] = $href;
            $path = str_replace($site_url, "", $href);
            if (preg_match('#^/subtype/#', $path)) {
                $type = "MedicalCondition";
            } elseif (preg_match('#^/glossary/#', $path)) {
                $type = "MedicalEntity";
            } elseif (preg_match('#^/what-is-cmt/#', $path)) {
                $type = "MedicalWebPage";
            } elseif (preg_match('#^/cmt-and-breathing/#', $path)) {
                $type = "MedicalWebPage";
            } else {
                continue;
            }
            $mentions[] = [
                "@type" => $type,
                "name" => $text,
                "url" => $href,
            ];
        }
    }
    if (!empty($mentions)) {
        $schema["mentions"] = $mentions;
    }
    echo "\n" . '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo "\n" . "</script>" . "\n";
}, 10);