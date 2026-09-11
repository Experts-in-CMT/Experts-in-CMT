<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * Gene — JSON-LD (Gene + MedicalWebPage)
 * ------------------------------------------------------------
 * Schema for single gene pages, the counterpart of subtype-jsonld.php.
 * A gene post holds no fields, so every value comes from
 * eic_gene_projection($symbol), the same per-gene resolution the
 * Gene Browser and the gene page template use.
 *
 * Block 1: schema.org Gene, with identifiers as PropertyValue and
 *          the subtypes it causes as associatedDisease (MedicalCondition
 *          entries pointing at the subtype pages).
 * Block 2: MedicalWebPage, authored and published by the Experts in CMT
 *          organization via Yoast's @ids, as on every medical page.
 *
 * Location:
 *   /inc/acf/gene-jsonld.php
 */

defined("ABSPATH") || exit();

add_action(
    "wp_head",
    function () {
        if (!is_singular("gene")) {
            return;
        }
        $post_id = get_the_ID();
        if (!$post_id || !function_exists("eic_gene_projection")) {
            return;
        }
        $symbol = trim((string) get_the_title($post_id));
        $g = eic_gene_projection($symbol);
        if (!$g) {
            return;
        }
        $gene_url = get_permalink($post_id);
        $date_published = get_the_date("Y-m-d", $post_id);
        $date_modified = get_the_modified_date("Y-m-d", $post_id);

        $subtype_codes = array_map(fn($s) => $s["code"], $g["subtypes"]);

        // Description, in the same register as the subtype schema.
        $desc_parts = [];
        if ($g["cand"]) {
            $desc_parts[] = sprintf(
                "%s%s is a candidate Charcot-Marie-Tooth disease (CMT) gene.",
                $symbol,
                $g["full_name"] !== "" ? " (" . $g["full_name"] . ")" : ""
            );
        } else {
            $desc_parts[] = sprintf(
                "%s%s is a Charcot-Marie-Tooth disease (CMT) gene associated with %s.",
                $symbol,
                $g["full_name"] !== "" ? " (" . $g["full_name"] . ")" : "",
                count($subtype_codes) === 1
                    ? "the subtype " . $subtype_codes[0]
                    : count($subtype_codes) . " subtypes: " . implode(", ", $subtype_codes)
            );
        }
        if ($g["locus"] !== "") {
            $desc_parts[] = sprintf("It is located at %s.", $g["locus"]);
        }
        if ($g["first_year"] !== "" && !$g["cand"]) {
            $desc_parts[] = sprintf(
                "Its association with CMT was first described in %s.",
                $g["first_year"]
            );
        }
        $description = implode(" ", $desc_parts);

        $alternate_names = [];
        if ($g["alias"] !== "") {
            $alternate_names = array_values(
                array_filter(array_map("trim", explode(",", $g["alias"])))
            );
        }

        $identifiers = [];
        $id_map = [
            "HGNC ID" => $g["hgnc_id"],
            "Ensembl Gene ID" => $g["ensembl"],
            "Entrez Gene ID" => $g["entrez"],
            "OMIM Gene Number" => $g["omim_gene"],
            "UniProt Accession" => $g["uniprot"],
            "RefSeq Accession" => $g["refseq"],
            "MANE Select (RefSeq)" => $g["mane_refseq"],
            "MANE Select (Ensembl)" => $g["mane_ensembl"],
            "Genomic Coordinates (GRCh38)" => $g["grch38"],
            "Genomic Coordinates (GRCh37)" => $g["grch37"],
        ];
        foreach ($id_map as $name => $value) {
            if ($value !== "") {
                $identifiers[] = [
                    "@type" => "PropertyValue",
                    "name" => $name,
                    "value" => $value,
                ];
            }
        }

        $same_as = array_values(
            array_filter([
                $g["hgnc_id"] !== ""
                    ? "https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/" . $g["hgnc_id"]
                    : "",
                $g["omim_gene"] !== "" ? "https://omim.org/entry/" . $g["omim_gene"] : "",
                $g["ensembl"] !== "" ? "https://gnomad.broadinstitute.org/gene/" . $g["ensembl"] : "",
                $g["uniprot"] !== "" ? "https://www.uniprot.org/uniprotkb/" . $g["uniprot"] : "",
                $g["orphanet"],
            ])
        );

        $associated = [];
        foreach ($g["subtypes"] as $s) {
            if ($s["candidate"]) {
                continue;
            }
            $entry = [
                "@type" => "MedicalCondition",
                "name" => $s["code"],
                "url" => $s["url"],
            ];
            if ($s["omim_subtype"] !== "") {
                $entry["code"] = [
                    "@type" => "MedicalCode",
                    "codingSystem" => "OMIM",
                    "codeValue" => $s["omim_subtype"],
                    "url" => "https://omim.org/entry/" . $s["omim_subtype"],
                ];
            }
            $associated[] = $entry;
        }

        // BLOCK 1: Gene
        $gene_id = $gene_url . "#gene";
        $variants_id = $gene_url . "#gene-variants";
        $gene_schema = [
            "@context" => "https://schema.org",
            "@type" => "Gene",
            "@id" => $gene_id,
            "name" => $symbol,
            "url" => $gene_url,
            "description" => $description,
            "mainEntityOfPage" => $gene_url,
        ];
        if ($g["full_name"] !== "") {
            $gene_schema["alternateName"] = array_merge(
                [$g["full_name"]],
                $alternate_names
            );
        } elseif ($alternate_names) {
            $gene_schema["alternateName"] = $alternate_names;
        }
        if (isset($gene_schema["alternateName"]) && count($gene_schema["alternateName"]) === 1) {
            $gene_schema["alternateName"] = $gene_schema["alternateName"][0];
        }
        if ($identifiers) {
            $gene_schema["identifier"] = $identifiers;
        }
        if ($same_as) {
            $gene_schema["sameAs"] = $same_as;
        }
        if ($g["func"] !== "") {
            $gene_schema["hasBioChemEntityPart"] = [
                "@type" => "Protein",
                "name" => $g["full_name"] !== "" ? $g["full_name"] : $symbol,
                "description" => $g["func"],
            ];
        }
        if ($associated) {
            $gene_schema["associatedDisease"] = $associated;
        }
        $additional = [];
        if ($g["locus"] !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Locus",
                "value" => $g["locus"],
            ];
        }
        if ($g["modes"]) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "Inheritance Modes",
                "value" => implode(", ", $g["modes"]),
            ];
        }
        if ($g["cg_class"] !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "ClinGen Gene-Disease Validity",
                "value" => $g["cg_class"] .
                    ($g["cg_disease"] !== "" ? " (" . $g["cg_disease"] . ")" : ""),
                "url" => $g["cg_url"] !== "" ? $g["cg_url"] : null,
            ];
        }
        if ($g["pa_rating"] !== "") {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "PanelApp Rating",
                "value" => $g["pa_rating"],
                "url" => $g["pa_url"] !== "" ? $g["pa_url"] : null,
            ];
        }
        $additional[] = [
            "@type" => "PropertyValue",
            "name" => "Candidate Gene Association",
            "value" => $g["cand"] ? "Yes" : "No",
        ];
        $additional[] = [
            "@type" => "PropertyValue",
            "name" => "Mitochondrial Involvement",
            "value" => $g["mito"] ? "Yes" : "No",
        ];
        if ($g["genesis"]) {
            $additional[] = [
                "@type" => "PropertyValue",
                "name" => "GENESIS Discovery",
                "value" => "Yes",
                "url" => "https://www.tgp-foundation.org/d-i-s-c-o-v-e-r-i-e-s",
            ];
        }
        $gene_schema["additionalProperty"] = array_map(
            fn($p) => array_filter($p, fn($v) => $v !== null),
            $additional
        );

        // BLOCK 2: MedicalWebPage
        $base = trailingslashit(home_url());
        $org_id = $base . "#organization";
        $site_id = $base . "#website";

        $page_schema = [
            "@context" => "https://schema.org",
            "@type" => "MedicalWebPage",
            "name" => $symbol,
            "url" => $gene_url,
            "description" => $description,
            "inLanguage" => "en-US",
            "datePublished" => $date_published,
            "dateModified" => $date_modified,
            "specialty" => [
                ["@type" => "MedicalSpecialty", "name" => "Neurologic"],
                ["@type" => "MedicalSpecialty", "name" => "Genetic"],
            ],
            "about" => [
                "@type" => "MedicalCondition",
                "name" => "Charcot-Marie-Tooth disease",
                "alternateName" => "CMT",
                "url" => "https://expertsincmt.org/what-is-cmt/",
            ],
            "mainEntity" => [
                "@type" => "Gene",
                "@id" => $gene_id,
                "name" => $symbol,
                "url" => $gene_url,
            ],
            "author" => ["@id" => $org_id],
            "publisher" => ["@id" => $org_id],
            "isPartOf" => ["@id" => $site_id],
        ];

        $significant_links = array_values(
            array_filter([$g["clinvar"], $g["clingen"], $g["genereviews"]])
        );
        if ($significant_links) {
            $page_schema["significantLink"] = $significant_links;
        }

        $page_schema["speakable"] = [
            "@type" => "SpeakableSpecification",
            // The gene page renders through [gene_fields], not a
            // post-content block: speak the Gene Function card text.
            "cssSelector" => [".gpx-section--function .gbx-func"],
        ];

        // BLOCK 3: ClinVar Variants (Dataset)
        // Mirrors the ClinVar Variants card: same gate (every gene post but
        // the structural record), same payload, read cache-only so page render
        // never calls NCBI. Cold cache: the Dataset node still describes
        // the surface and the variant nodes are omitted until the card's
        // own fetch warms it. Schema.org has no variant type, so each
        // variant is a BioChemEntity pinned to the Sequence Ontology term
        // for sequence_variant, the Bioschemas pattern. Only Reported in
        // CMT (tier A) entries are emitted, each with only its CMT
        // report(s): a disease other than CMT is never asserted here.
        $variants_schema = null;
        if (
            !$g["structural"] &&
            class_exists("EIC_ClinVar_Variants") &&
            method_exists("EIC_ClinVar_Variants", "cached")
        ) {
            $cv = EIC_ClinVar_Variants::cached($symbol);

            $variants_schema = [
                "@context" => "https://schema.org",
                "@type" => "Dataset",
                "@id" => $variants_id,
                "name" => sprintf(
                    "Pathogenic and Likely Pathogenic Variants in %s (ClinVar)",
                    $symbol
                ),
                "url" => $variants_id,
                "description" => sprintf(
                    "Pathogenic and likely pathogenic variants in %s, as classified in ClinVar, read live from NCBI. Only aggregate germline records are indexed. Uncertain and conflicting classifications are not, and neither are records submitted without assertion criteria. Variants reported in Charcot-Marie-Tooth disease (CMT) are listed apart from those reported in other diseases. Experts in CMT makes no claim to the accuracy of ClinVar data. This index is provided for informational purposes only.",
                    $symbol
                ),
                "inLanguage" => "en-US",
                "isAccessibleForFree" => true,
                "license" => "https://www.ncbi.nlm.nih.gov/home/about/policies/",
                "creator" => [
                    "@type" => "Organization",
                    "name" => "National Center for Biotechnology Information",
                    "alternateName" => "NCBI",
                    "url" => "https://www.ncbi.nlm.nih.gov/",
                ],
                "includedInDataCatalog" => [
                    "@type" => "DataCatalog",
                    "name" => "ClinVar",
                    "url" => "https://www.ncbi.nlm.nih.gov/clinvar/",
                ],
                "about" => ["@id" => $gene_id],
                "mainEntityOfPage" => $gene_url,
                "publisher" => ["@id" => $org_id],
            ];

            if (is_array($cv) && !empty($cv["tiers"]["A"])) {
                $variants_schema["version"] = (string) $cv["release"];
                $variants_schema["dateModified"] = substr(
                    (string) ($cv["fetched"] ?? ""),
                    0,
                    10
                );
                $variants_schema["additionalProperty"] = [
                    [
                        "@type" => "PropertyValue",
                        "name" => "P/LP variants in ClinVar with assertion criteria",
                        "value" => (int) $cv["total_plp"],
                    ],
                    [
                        "@type" => "PropertyValue",
                        "name" => "Reported in CMT",
                        "value" => (int) $cv["counts"]["A"],
                    ],
                    [
                        "@type" => "PropertyValue",
                        "name" => "ClinVar release",
                        "value" => (string) $cv["release"],
                    ],
                ];

                // Head budget: PMP22-class genes carry hundreds of P/LP
                // records; the page states the full count above and
                // enumerates up to the cap.
                $cap = max(0, (int) apply_filters("eic_gene_schema_variant_cap", 150));
                $parts = [];
                foreach (array_slice($cv["tiers"]["A"], 0, $cap) as $v) {
                    $vcv = (string) ($v["vcv"] ?? "");
                    if ($vcv === "") {
                        continue;
                    }
                    $node = [
                        "@type" => "BioChemEntity",
                        "@id" => $gene_url . "#" . strtolower($vcv),
                        "additionalType" => "http://purl.obolibrary.org/obo/SO_0001060",
                        "name" => (string) $v["title"],
                        "isPartOfBioChemEntity" => ["@id" => $gene_id],
                    ];
                    $ids = [
                        [
                            "@type" => "PropertyValue",
                            "propertyID" => "ClinVar",
                            "value" => (string) ($v["vcv_version"] ?? $vcv),
                            "url" => (string) $v["url"],
                        ],
                    ];
                    $same = array_filter([(string) $v["url"]]);
                    if (!empty($v["rsid"])) {
                        $ids[] = [
                            "@type" => "PropertyValue",
                            "propertyID" => "dbSNP",
                            "value" => (string) $v["rsid"],
                            "url" => "https://www.ncbi.nlm.nih.gov/snp/" . rawurlencode($v["rsid"]),
                        ];
                        $same[] = "https://www.ncbi.nlm.nih.gov/snp/" . rawurlencode($v["rsid"]);
                    }
                    $node["identifier"] = $ids;
                    $node["sameAs"] = array_values($same);
                    $rep = array_values(
                        array_filter([(string) ($v["cdna"] ?? ""), (string) ($v["protein"] ?? "")])
                    );
                    if ($rep) {
                        $node["hasRepresentation"] = count($rep) === 1 ? $rep[0] : $rep;
                    }
                    $diseases = [];
                    foreach ((array) ($v["conditions"] ?? []) as $c) {
                        $cname = trim((string) ($c["name"] ?? ""));
                        if ($cname === "") {
                            continue;
                        }
                        $d = ["@type" => "MedicalCondition", "name" => $cname];
                        if (!empty($c["cui"])) {
                            $d["code"] = [
                                "@type" => "MedicalCode",
                                "codingSystem" => "MedGen",
                                "codeValue" => (string) $c["cui"],
                                "url" => "https://www.ncbi.nlm.nih.gov/medgen/" . rawurlencode($c["cui"]),
                            ];
                        }
                        $diseases[] = $d;
                    }
                    if ($diseases) {
                        $node["associatedDisease"] = count($diseases) === 1 ? $diseases[0] : $diseases;
                    }
                    $props = [
                        [
                            "@type" => "PropertyValue",
                            "name" => "Germline classification",
                            "value" => (string) $v["classification"],
                        ],
                        [
                            "@type" => "PropertyValue",
                            "name" => "Review status",
                            "value" => (string) $v["review_status"],
                        ],
                    ];
                    if (!empty($v["type"])) {
                        $props[] = [
                            "@type" => "PropertyValue",
                            "name" => "Variant type",
                            "value" => (string) $v["type"],
                        ];
                    }
                    $node["additionalProperty"] = $props;
                    $parts[] = $node;
                }
                // hasPart is CreativeWork-only in schema.org; about takes
                // any Thing, so the Dataset is about the gene and about
                // each variant it enumerates.
                if ($parts) {
                    $variants_schema["about"] = array_merge(
                        [["@id" => $gene_id]],
                        $parts
                    );
                }
            }

            $gene_schema["subjectOf"] = ["@id" => $variants_id];
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($gene_schema, $flags);
        echo "\n" . "</script>" . "\n";
        echo "\n" . '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($page_schema, $flags);
        echo "\n" . "</script>" . "\n";
        if ($variants_schema) {
            echo "\n" . '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($variants_schema, $flags);
            echo "\n" . "</script>" . "\n";
        }
    },
    10
);
