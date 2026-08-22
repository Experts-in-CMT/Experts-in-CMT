<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Subtype — MedicalCondition + MedicalWebPage JSON-LD Schema Injection
 * ------------------------------------------------------------
 * Outputs two JSON-LD blocks in <head> for each subtype CPT post:
 *   1. MedicalCondition — describes the disease entity
 *   2. MedicalWebPage — describes the page about that entity
 *
 * Additive: does not replace or modify Yoast WebPage schema.
 *
 * Location:
 *   /inc/acf/subtype-jsonld.php
 */

defined("ABSPATH") || exit();

add_action(
    "wp_head",
    function () {
        if (!is_singular("subtype")) {
            return;
        }
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        $subtype_name = get_the_title($post_id);
        $subtype_url = get_permalink($post_id);
        $subtype_acf_name = trim((string) get_field("subtype", $post_id));
        $acronym = trim((string) get_field("acronym", $post_id));
        $subtype_alias = trim((string) get_field("subtype_alias", $post_id));
        $unknown_gene = (bool) get_field("unknown_gene", $post_id);
        $gene_symbol = trim((string) get_field("gene_symbol", $post_id));
        if ($gene_symbol === "") {
            $gene_symbol = trim((string) get_field("gene", $post_id));
        }
        $full_gene_name = trim((string) get_field("full_gene_name", $post_id));
        $gene_alias = trim((string) get_field("gene_alias", $post_id));
        $chromosome = trim((string) get_field("chromosome", $post_id));
        $inheritance = trim((string) get_field("inheritance", $post_id));
        $neuropathy = trim((string) get_field("neuropathy", $post_id));
        $zygosity = trim((string) get_field("zygosity", $post_id));
        $type_class = trim((string) get_field("type_classification", $post_id));
        $year_of_discovery = get_field("year_of_discovery", $post_id);
        $mitochondrial = (bool) get_field(
            "mitochondrial_involvement",
            $post_id
        );
        $ars_gene = (bool) get_field("ars_gene", $post_id);
        $mechanism = strtolower(
            trim((string) get_field("mechanism", $post_id))
        );
        $mechanism_mode = trim(
            (string) get_field("mechanism_mode", $post_id)
        );
        $mechanism_labels = [
            "lof" => "Loss of Function (LoF)",
            "dominant_negative" => "Dominant-Negative",
            "gof" => "Toxic Gain of Function (GoF)",
            "complex" => "Complex",
            "unknown" => "Unknown",
        ];
        $mechanism_mode_labels = [
            "haploinsufficiency" => "Haploinsufficiency",
            "complete-loss" => "Complete loss",
            "hypomorphic" => "Hypomorphic",
            "overactivity" => "Overactivity",
            "neomorphic" => "Neomorphic",
            "dosage" => "Dosage",
            "repeat-expansion" => "Repeat expansion",
            "mixed" => "Mixed",
            "unresolved" => "Unresolved",
            "no-gene" => "Gene unknown",
        ];
        $audience = get_field("medical_audience", $post_id);
        $specialties = get_field("medical_specialty", $post_id);
        $last_reviewed = trim(
            (string) get_field("last_reviewed_date", $post_id)
        );
        $reviewed_by_name = trim(
            (string) get_field("reviewed_by_name", $post_id)
        );
        $reviewed_by_type = trim(
            (string) get_field("reviewed_by_type", $post_id)
        );
        $clinvar_url = trim((string) get_field("clinvar_url", $post_id));
        $clingen_url = trim((string) get_field("clingen_url", $post_id));
        $genereviews_url = trim(
            (string) get_field("genereviews_url", $post_id)
        );
        $omim_subtype = trim((string) get_field("omim_subtype", $post_id));
        $omim_gene = trim((string) get_field("omim_gene", $post_id));

        $omim_no_entry_pattern = '/^\s*no[\s\-]?entry\s*$/i';
        $omim_none_pattern = '/^\s*none\s*$/i';

        $omim_subtype_valid =
            $omim_subtype !== "" &&
            !preg_match($omim_no_entry_pattern, $omim_subtype) &&
            !preg_match($omim_none_pattern, $omim_subtype);

        $omim_gene_valid =
            $omim_gene !== "" &&
            !preg_match($omim_no_entry_pattern, $omim_gene) &&
            !preg_match($omim_none_pattern, $omim_gene);

        $date_published = get_the_date("Y-m-d", $post_id);
        $date_modified = get_the_modified_date("Y-m-d", $post_id);
        $alternate_names = [];
        if ($subtype_acf_name !== "" && $subtype_acf_name !== $subtype_name) {
            $alternate_names[] = $subtype_acf_name;
        }
        if ($acronym !== "") {
            $alternate_names[] = $acronym;
        }
        if ($subtype_alias !== "") {
            $aliases = array_filter(
                array_map("trim", explode(",", $subtype_alias))
            );
            $alternate_names = array_merge(
                $alternate_names,
                array_values($aliases)
            );
        }
        $desc_parts = [];
        $desc_parts[] = sprintf(
            "%s is a subtype of Charcot-Marie-Tooth disease (CMT)%s.",
            $subtype_name,
            $gene_symbol !== "" && !$unknown_gene
                ? sprintf(" caused by mutations in the %s gene", $gene_symbol)
                : ($unknown_gene
                    ? " whose causative gene is currently unknown"
                    : "")
        );
        if (!$unknown_gene && $full_gene_name !== "") {
            $desc_parts[] = sprintf(
                "The %s gene encodes %s.",
                $gene_symbol,
                $full_gene_name
            );
        }
        if ($chromosome !== "") {
            $desc_parts[] = sprintf(
                "%s is located at chromosomal locus %s.",
                $unknown_gene ? "The causative gene" : $gene_symbol,
                $chromosome
            );
        }
        if ($inheritance !== "") {
            $desc_parts[] = sprintf(
                "%s follows %s inheritance.",
                $subtype_name,
                $inheritance
            );
        }
        if ($neuropathy !== "") {
            $desc_parts[] = sprintf(
                "It presents as a %s neuropathy.",
                $neuropathy
            );
        }
        if ($zygosity !== "") {
            $desc_parts[] = sprintf("Causative mutations are %s.", $zygosity);
        }
        if ($mitochondrial) {
            $desc_parts[] =
                "Mitochondrial involvement has been associated with this subtype.";
        }
        if ($ars_gene) {
            $desc_parts[] = sprintf(
                "%s is associated with an aminoacyl-tRNA synthetase (ARS) gene.",
                $subtype_name
            );
        }
        $mechanism_sentences = [
            "lof" => "%s results from a loss-of-function disease mechanism.",
            "dominant_negative" =>
                "%s results from a dominant-negative disease mechanism.",
            "gof" =>
                "%s results from a toxic gain-of-function disease mechanism.",
            "complex" => "%s results from a complex disease mechanism.",
        ];
        if (isset($mechanism_sentences[$mechanism])) {
            $desc_parts[] = sprintf(
                $mechanism_sentences[$mechanism],
                $subtype_name
            );
        }
        if ($year_of_discovery) {
            $desc_parts[] = sprintf(
                "%s was first described in %s.",
                $subtype_name,
                $year_of_discovery
            );
        }
        // BLOCK 1: MedicalCondition
        $condition_schema = [
            "@context" => "https://schema.org",
            "@type" => "MedicalCondition",
            "name" => $subtype_name,
            "url" => $subtype_url,
            "description" => implode(" ", $desc_parts),
            "mainEntityOfPage" => $subtype_url,
            "code" => array_values(
                array_filter([
                    array_filter([
                        "@type" => "MedicalCode",
                        "codingSystem" => "OMIM",
                        "codeValue" => $omim_subtype_valid
                            ? $omim_subtype
                            : null,
                        "url" => $omim_subtype_valid
                            ? "https://omim.org/entry/" . $omim_subtype
                            : null,
                    ]),
                    $omim_gene_valid
                        ? [
                            "@type" => "MedicalCode",
                            "codingSystem" => "OMIM",
                            "codeValue" => $omim_gene,
                            "url" => "https://omim.org/entry/" . $omim_gene,
                        ]
                        : null,
                ])
            ),
            "associatedAnatomy" => [
                "@type" => "AnatomicalStructure",
                "name" => "Peripheral nervous system",
            ],
        ];
        if (!empty($alternate_names)) {
            $condition_schema["alternateName"] =
                count($alternate_names) === 1
                    ? $alternate_names[0]
                    : $alternate_names;
        }
        $additional = [];
        if (!$unknown_gene && $gene_symbol !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Gene Symbol",
                "value" => $gene_symbol,
            ];
            if ($full_gene_name !== "") {
                $additional[] = [
                    "@type" => "PropertyValue",
                    "name" => "Gene Name",
                    "value" => $full_gene_name,
                ];
            }
            if ($gene_alias !== "") {
                $additional[] = [
                    "@type" => "PropertyValue",
                    "name" => "Gene Alias",
                    "value" => $gene_alias,
                ];
            }
        } elseif ($unknown_gene) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Associated Gene Symbol",
                "value" => "Gene unknown at this time",
            ];
        }
        if ($chromosome !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Chromosomal Locus",
                "value" => $chromosome,
            ];
        }
        if ($inheritance !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Inheritance Pattern",
                "value" => $inheritance,
            ];
        }
        if ($neuropathy !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Neuropathy Type",
                "value" => $neuropathy,
            ];
        }
        if ($zygosity !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Zygosity",
                "value" => $zygosity,
            ];
        }
        if ($type_class !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "CMT Classification",
                "value" => strtoupper($type_class),
            ];
        }
        if ($mitochondrial) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Mitochondrial Involvement",
                "value" => "Yes",
            ];
        }
        if ($ars_gene) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "ARS Gene",
                "value" => "Yes",
            ];
        }
        if (
            isset($mechanism_labels[$mechanism]) &&
            $mechanism !== "unknown"
        ) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Variant Mechanism",
                "value" => $mechanism_labels[$mechanism],
            ];
        }
        if (isset($mechanism_mode_labels[$mechanism_mode])) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Mechanistic Basis",
                "value" => $mechanism_mode_labels[$mechanism_mode],
            ];
        }
        if ($year_of_discovery) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Year of Discovery",
                "value" => (string) $year_of_discovery,
            ];
        }
        if (!empty($additional)) {
            $condition_schema["additionalProperty"] = $additional;
        }
        // BLOCK 2: MedicalWebPage
        $base = trailingslashit(home_url());
        $org_id = $base . "#organization";
        $site_id = $base . "#website";

        $page_schema = [
            "@context" => "https://schema.org",
            "@type" => "MedicalWebPage",
            "name" => $subtype_name,
            "url" => $subtype_url,
            "description" => implode(" ", $desc_parts),
            "inLanguage" => "en-US",
            "datePublished" => $date_published,
            "dateModified" => $date_modified,
            "specialty" => !empty($specialties)
                ? array_map(
                    function ($s) {
                        return [
                            "@type" => "MedicalSpecialty",
                            "name" => $s,
                        ];
                    },
                    is_array($specialties) ? $specialties : [$specialties]
                )
                : [["@type" => "MedicalSpecialty", "name" => "Neurologic"]],
            "about" => [
                "@type" => "MedicalCondition",
                "name" => "Charcot-Marie-Tooth disease",
                "alternateName" => "CMT",
                "url" => "https://expertsincmt.org/what-is-cmt/",
            ],
            // Authorship, publishing, and site membership attributed to the
            // real organization and website, referenced by Yoast's @ids so
            // engines merge, not duplicate.
            "author" => ["@id" => $org_id],
            "publisher" => ["@id" => $org_id],
            "isPartOf" => ["@id" => $site_id],
        ];
        if (!empty($audience)) {
            $audience_types = is_array($audience) ? $audience : [$audience];
            $page_schema["audience"] = array_map(function ($a) {
                return [
                    "@type" => "MedicalAudience",
                    "audienceType" => $a,
                ];
            }, $audience_types);
        }
        if ($last_reviewed !== "") {
            $page_schema["lastReviewed"] = $last_reviewed;
        }
        if ($reviewed_by_name !== "") {
            $type = $reviewed_by_type !== "" ? $reviewed_by_type : "Person";
            $page_schema["reviewedBy"] = [
                "@type" => $type,
                "name" => $reviewed_by_name,
            ];
        }
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
            $page_schema["mentions"] = $mentions;
        }

        // significantLink: authoritative external references from ACF fields.
        // Only populated fields are included.
        $significant_links = array_filter([
            $clinvar_url,
            $clingen_url,
            $genereviews_url,
        ]);
        if (!empty($significant_links)) {
            $page_schema["significantLink"] = array_values($significant_links);
        }

        // Speakable: targets block editor prose rendered by wp:post-content
        $page_schema["speakable"] = [
            "@type" => "SpeakableSpecification",
            "cssSelector" => [".wp-block-post-content"],
        ];

        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode(
            $condition_schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        echo "\n" . "</script>" . "\n";
        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode(
            $page_schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        echo "\n" . "</script>" . "\n";
    },
    10
);
