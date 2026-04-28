<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Subtype — MedicalCondition JSON-LD Schema Injection
 * ------------------------------------------------------------
 * Outputs a MedicalCondition JSON-LD block in <head> for each
 * subtype CPT post. Pulls from ACF fields to provide Google
 * machine-readable entity differentiation across subtypes with
 * near-identical prose bodies.
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
        // Core identity fields
        $subtype_name = get_the_title($post_id);
        $subtype_url = get_permalink($post_id);
        $subtype_acf_name = trim((string) get_field("subtype", $post_id));
        $acronym = trim((string) get_field("acronym", $post_id));
        $subtype_alias = trim((string) get_field("subtype_alias", $post_id));
        // Gene fields
        $unknown_gene = (bool) get_field("unknown_gene", $post_id);
        $gene_symbol = trim((string) get_field("gene_symbol", $post_id));
        if ($gene_symbol === "") {
            $gene_symbol = trim((string) get_field("gene", $post_id));
        }
        $full_gene_name = trim((string) get_field("full_gene_name", $post_id));
        $gene_alias = trim((string) get_field("gene_alias", $post_id));
        // Genetic context fields
        $chromosome = trim((string) get_field("chromosome", $post_id));
        $inheritance = trim((string) get_field("inheritance", $post_id));
        $neuropathy = trim((string) get_field("neuropathy", $post_id));
        $zygosity = trim((string) get_field("zygosity", $post_id));
        $type_class = trim((string) get_field("type_classification", $post_id));
        $year_of_discovery = get_field("year_of_discovery", $post_id);
        $mitochondrial = (bool) get_field("mitochondrial_involvement", $post_id);
        $ars_gene = (bool) get_field("ars_gene", $post_id);
        // Alternate names: ACF subtype value, acronym, aliases
        $alternate_names = [];
        if ($subtype_acf_name !== "" && $subtype_acf_name !== $subtype_name) {
            $alternate_names[] = $subtype_acf_name;
        }
        if ($acronym !== "") {
            $alternate_names[] = $acronym;
        }
        if ($subtype_alias !== "") {
            $aliases = array_filter(array_map("trim", explode(",", $subtype_alias)));
            $alternate_names = array_merge($alternate_names, array_values($aliases));
        }
        // Build description string
        $desc_parts = [];
        $desc_parts[] = sprintf(
            "%s is a subtype of Charcot-Marie-Tooth disease (CMT)%s.",
            $subtype_name,
            $gene_symbol !== "" && !$unknown_gene
                ? sprintf(" caused by mutations in the %s gene", $gene_symbol)
                : ($unknown_gene ? " whose causative gene is currently unknown" : "")
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
            $desc_parts[] = sprintf(
                "Causative mutations are %s.",
                $zygosity
            );
        }
        if ($mitochondrial) {
            $desc_parts[] = "Mitochondrial involvement has been associated with this subtype.";
        }
        if ($ars_gene) {
            $desc_parts[] = sprintf(
                "%s is associated with an aminoacyl-tRNA synthetase (ARS) gene.",
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
        // Schema payload
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "MedicalCondition",
            "name" => $subtype_name,
            "url" => $subtype_url,
            "description" => implode(" ", $desc_parts),
            "code" => [
                "@type" => "MedicalCode",
                "codingSystem" => "OMIM",
            ],
            "associatedAnatomy" => [
                "@type" => "AnatomicalStructure",
                "name" => "Peripheral nervous system",
            ],
        ];
        if (!empty($alternate_names)) {
            $schema["alternateName"] = count($alternate_names) === 1
                ? $alternate_names[0]
                : $alternate_names;
        }
        // additionalProperty: genetic data not formally typed in MedicalCondition
        $additional = [];
        if (!$unknown_gene && $gene_symbol !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Associated Gene Symbol",
                "value" => $gene_symbol,
            ];
            if ($full_gene_name !== "") {
                $additional[] = [
                    "@type" => "PropertyValue",
                    "name" => "Full Gene Name",
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
        if ($year_of_discovery) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Year of Discovery",
                "value" => (string) $year_of_discovery,
            ];
        }
        if (!empty($additional)) {
            $schema["additionalProperty"] = $additional;
        }
        // Output
        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        echo "\n" . "</script>" . "\n";
    },
    10
);