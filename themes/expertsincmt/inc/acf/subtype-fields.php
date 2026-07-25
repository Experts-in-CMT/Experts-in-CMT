<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Subtype — Field Group (ACF PHP Registration)
 * ------------------------------------------------------------
 * Defines the complete ACF PHP field group used for Subtypes,
 * including:
 *   - Core fields (gene, subtype, acronym, unknown-gene flag)
 *   - Clinical & Genetic Context
 *   - CTA section (dynamic More Info buttons)
 *   - Discovery tab (year, discoverer, original paper)
 *   - Alt Publication block
 *   - Advanced / Debug fields (e.g., EIC Test Ping)
 *   - Schema Markup fields (specialty, audience, reviewer)
 *
 * Implementation notes:
 *   - Registered via acf/include_field_groups
 *   - JSON sync disabled intentionally for stability
 *   - Field map matches the Subtype ACF Group Builder thread
 *   - Used by the "Single Item: Subtype" TT25 custom template
 *   - _keep_wrapper flag exempts a field from the global 33%
 *     width normalization loop; ACF ignores unknown keys.
 *
 * Location:
 *   /inc/acf/subtype-fields.php
 */

add_action("acf/init", function () {
    if (!function_exists("acf_add_local_field_group")) {
        return;
    }

    $fields = [
        // Tabs
        [
            "key" => "tab_core",
            "label" => "Core",
            "type" => "tab",
            "placement" => "top",
        ],

        // Core fields
        [
            "key" => "field_type_classification",
            "label" => "Type Classification",
            "name" => "type_classification",
            "type" => "select",
            "required" => 1,
            "choices" => [
                "cmt1" => "CMT1",
                "cmt2" => "CMT2",
                "cmt4" => "CMT4",
                "cmtx" => "CMTX",
                "cmtdi" => "CMTDI",
                "cmtri" => "CMTRI",
                "dhmn" => "dHMN/HMN",
                "dsma" => "dSMA",
                "gan" => "GAN",
                "hmsn" => "HMSN",
                "hsan" => "HSAN",
                "hsn" => "HSN",
                "smalep" => "SMA-LEP",
                "unclassified" => "Unclassified",
            ],
            "ui" => 1,
            "return_format" => "value",
            "allow_null" => 0,
            "multiple" => 0,
        ],
        [
            "key" => "field_subtype",
            "label" => "Subtype",
            "name" => "subtype",
            "type" => "text",
            "required" => 1,
        ],
        [
            "key" => "field_subtype_alias",
            "label" => "Subtype Alias(es) – Separate Aliases with a Comma",
            "name" => "subtype_alias",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_gene_symbol",
            "label" => "Associated Gene - HGNC-Approved Gene Symbol",
            "name" => "gene_symbol",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_full_gene_name",
            "label" => "Full HGNC-Approved Gene Name",
            "name" => "full_gene_name",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_gene_alias",
            "label" => "HGNC Gene Alias(es) - Separate Aliases with a Comma",
            "name" => "gene_alias",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_chromosome",
            "label" => "Chromosome",
            "name" => "chromosome",
            "type" => "text",
            "required" => 1,
        ],
        [
            "key" => "field_neuropathy",
            "label" => "Neuropathy",
            "name" => "neuropathy",
            "type" => "select",
            "required" => 1,
            "choices" => [
                "demyelinating" => "Demyelinating",
                "axonal" => "Axonal",
                "intermediate" => "Intermediate",
            ],
            "ui" => 1,
            "return_format" => "value",
            "allow_null" => 0,
            "multiple" => 0,
        ],
        [
            "key" => "field_zygosity",
            "label" => "Zygosity",
            "name" => "zygosity",
            "type" => "select",
            "required" => 1,
            "choices" => [
                "Heterozygous" => "Heterozygous",
                "Homozygous" => "Homozygous",
                "Compound Heterozygous" => "Compound Heterozygous",
                "Homozygous or Compound Heterozygous" =>
                    "Homozygous or Compound Heterozygous",
                "Heterozygous or Homozygous" => "Heterozygous or Homozygous",
                "Heterozygous or Compound Heterozygous" =>
                    "Heterozygous or Compound Heterozygous",
                "Heterozygous or Homozygous or Compound Heterozygous" =>
                    "Heterozygous or Homozygous or Compound Heterozygous",
                "Hemizygous (Male) / Heterozygous (Female)" =>
                    "Hemizygous (Male) / Heterozygous (Female)",
                "Hemizygous (Male) / Homozygous (Female)" =>
                    "Hemizygous (Male) / Homozygous (Female)",
                "Hemizygous (Male) / Compound Heterozygous (Female)" =>
                    "Hemizygous (Male) / Compound Heterozygous (Female)",
                "Hemizygous (Male) / Compound Heterozygous or Homozygous (Female)" =>
                    "Hemizygous (Male) / Compound Heterozygous or Homozygous (Female)",
                "Heteroplasmic" => "Heteroplasmic",
                "Homoplasmic" => "Homoplasmic",
            ],
            "ui" => 1,
            "return_format" => "value",
            "allow_null" => 0,
            "multiple" => 0,
        ],
        [
            "key" => "field_inheritance",
            "label" => "Inheritance",
            "name" => "inheritance",
            "type" => "select",
            "required" => 1,
            "choices" => [
                "autosomal dominant" => "autosomal dominant",
                "autosomal recessive" => "autosomal recessive",
                "autosomal dominant or autosomal recessive" =>
                    "autosomal dominant or autosomal recessive",
                "X-linked dominant" => "X-linked dominant",
                "X-linked recessive" => "X-linked recessive",
                "mitochondrial inheritance" => "mitochondrial inheritance",
            ],
            "ui" => 1,
            "return_format" => "value",
            "allow_null" => 0,
            "multiple" => 0,
        ],
        [
            "key" => "field_acronym",
            "label" => "Acronym",
            "name" => "acronym",
            "type" => "text",
            "instructions" => "",
            "required" => 0,
            "wrapper" => [
                "width" => "33",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_unknown_gene",
            "label" => "Check if Gene is Unknown",
            "name" => "unknown_gene",
            "type" => "true_false",
            "instructions" => "",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "33",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_mitochondrial_involvement",
            "label" => "Mitochondrial Involvement",
            "name" => "mitochondrial_involvement",
            "type" => "true_false",
            "ui" => 1,
            "ui_on_text" => "Yes",
            "ui_off_text" => "No",
            "required" => 0,
            "wrapper" => [],
        ],
        [
            "key" => "field_ars_gene",
            "label" => "Aminoacyl-tRNA Synthetase (ARS) Gene",
            "name" => "ars_gene",
            "type" => "true_false",
            "instructions" =>
                "Check if this subtype is associated with an aminoacyl-tRNA synthetase (ARS) gene.",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "33",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_year_of_discovery",
            "label" => "Year of Discovery",
            "name" => "year_of_discovery",
            "type" => "number",
            "required" => 1,
            "min" => 1850,
            "step" => 1,
        ],
        [
            "key" => "field_type_sort_order",
            "label" => "Type Sort Order",
            "name" => "type_sort_order",
            "type" => "number",
            "required" => 0,
            "wrapper" => [
                "width" => "33",
            ],
            "default_value" => "",
            "min" => 0,
            "step" => 1,
            "conditional_logic" => [
                [
                    [
                        "field" => "field_type_classification",
                        "operator" => "!=",
                        "value" => "",
                    ],
                ],
            ],
        ],

        [
            "key" => "tab_mechanism",
            "label" => "Mechanism",
            "type" => "tab",
            "placement" => "top",
        ],
        [
            "key" => "field_lof_variant",
            "label" => "Loss of Function (LoF) Variant",
            "name" => "lof_variant",
            "type" => "true_false",
            "instructions" =>
                "Select if this subtype is caused by a loss-of-function variant.",
            "ui" => 1,
            "ui_on_text" => "Yes",
            "ui_off_text" => "No",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "33",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_gof_variant",
            "label" => "Toxic Gain of Function (GoF) Variant",
            "name" => "gof_variant",
            "type" => "true_false",
            "instructions" =>
                "Select if this subtype is caused by a toxic gain-of-function variant.",
            "ui" => 1,
            "ui_on_text" => "Yes",
            "ui_off_text" => "No",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "33",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_mechanism_confidence",
            "label" => "Mechanism Confidence",
            "name" => "mechanism_confidence",
            "type" => "select",
            "instructions" =>
                "How firmly the evidence supports the LoF/GoF call for this subtype.",
            "choices" => [
                "high" => "High",
                "medium" => "Medium",
                "low" => "Low",
            ],
            "default_value" => false,
            "allow_null" => 1,
            "ui" => 1,
            "ajax" => 0,
            "return_format" => "value",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "33",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_mechanism_rationale",
            "label" => "Mechanism Rationale",
            "name" => "mechanism_rationale",
            "type" => "textarea",
            "instructions" =>
                "One or two sentences explaining the LoF/GoF call (shown in the Variant Mechanism explorer).",
            "rows" => 3,
            "new_lines" => "",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "100",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "field_mechanism_source",
            "label" => "Mechanism Source",
            "name" => "mechanism_source",
            "type" => "text",
            "instructions" =>
                "Citation(s) supporting the call: OMIM #, GeneReviews chapter, PMID/PMC.",
            "required" => 0,
            "conditional_logic" => 0,
            "wrapper" => [
                "width" => "100",
                "class" => "",
                "id" => "",
            ],
        ],
        [
            "key" => "tab_ctas",
            "label" => "CTAs",
            "type" => "tab",
            "placement" => "top",
        ],
        [
            "key" => "field_symptoms_url",
            "label" => "Symptoms",
            "name" => "symptoms_url",
            "type" => "url",
            "required" => 0,
        ],
        [
            "key" => "field_what_is_cmtx_url",
            "label" => "What is CMTX?",
            "name" => "what_is_cmtx_url",
            "type" => "url",
            "required" => 0,
        ],
        [
            "key" => "field_what_is_intermediate_url",
            "label" => "What is Intermediate CMT?",
            "name" => "what_is_intermediate_url",
            "type" => "url",
            "required" => 0,
        ],
        [
            "key" => "field_research_label",
            "label" => "Research Button 1 Label",
            "name" => "research_label",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_research_url",
            "label" => "Current Research Opportunity 1",
            "name" => "research_url",
            "type" => "url",
            "required" => 0,
        ],

        [
            "key" => "field_research_label_2",
            "label" => "Research Button 2 Label",
            "name" => "research_label_2",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_research_url_2",
            "label" => "Current Research Opportunity 2",
            "name" => "research_url_2",
            "type" => "url",
            "required" => 0,
        ],

        [
            "key" => "field_research_label_3",
            "label" => "Research Button 3 Label",
            "name" => "research_label_3",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_research_url_3",
            "label" => "Current Research Opportunity 3",
            "name" => "research_url_3",
            "type" => "url",
            "required" => 0,
        ],

        [
            "key" => "tab_discovery",
            "label" => "Discovery",
            "type" => "tab",
            "placement" => "top",
        ],
        [
            "key" => "field_publication_title",
            "label" => "Title of Establishing Publication",
            "name" => "publication_title",
            "type" => "wysiwyg",
            "required" => 1,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "full",
            "delay" => 0,
        ],
        [
            "key" => "field_publication_note",
            "label" => "Publication Note",
            "name" => "publication_note",
            "type" => "wysiwyg",
            "required" => 0,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "full",
            "delay" => 0,
        ],
        [
            "key" => "field_publication_date",
            "label" => "Date of Original Publication",
            "name" => "publication_date",
            "type" => "date_picker",
            "required" => 1,
            "display_format" => "F j, Y",
            "return_format" => "Y-m-d",
            "first_day" => 0,
        ],
        [
            "key" => "field_authors",
            "label" => "Authors",
            "name" => "authors",
            "type" => "wysiwyg",
            "required" => 1,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "full",
        ],
        [
            "key" => "field_doi_url",
            "label" => "Publication DOI",
            "name" => "doi_url",
            "type" => "url",
            "required" => 1,
        ],

        [
            "key" => "tab_alt_pub",
            "label" => "Alt Publication",
            "type" => "tab",
            "placement" => "top",
        ],
        [
            "key" => "field_alt_publication_title",
            "label" => "Publication Title",
            "name" => "alt_publication_title",
            "type" => "wysiwyg",
            "required" => 0,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "full",
        ],
        [
            "key" => "field_alt_publication_note",
            "label" => "Alt Publication Note",
            "name" => "alt_publication_note",
            "type" => "wysiwyg",
            "required" => 0,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "full",
            "delay" => 0,
        ],
        [
            "key" => "field_alt_authors",
            "label" => "Authors",
            "name" => "alt_authors",
            "type" => "wysiwyg",
            "required" => 0,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "full",
        ],
        [
            "key" => "field_alt_date",
            "label" => "Publication Date",
            "name" => "alt_date",
            "type" => "date_picker",
            "required" => 0,
            "display_format" => "F j, Y",
            "return_format" => "Y-m-d",
            "first_day" => 0,
        ],
        [
            "key" => "field_alt_doi_url",
            "label" => "DOI",
            "name" => "alt_doi_url",
            "type" => "url",
            "required" => 0,
        ],

        [
            "key" => "tab_advanced",
            "label" => "Advanced",
            "type" => "tab",
            "placement" => "top",
        ],
        [
            "key" => "field_updated_date",
            "label" => "Updated Date",
            "name" => "updated_date",
            "type" => "date_picker",
            "required" => 0,
            "display_format" => "F j, Y",
            "return_format" => "Y-m-d",
            "first_day" => 0,
        ],
        [
            "key" => "field_clinvar_url",
            "label" => "ClinVar Variants URL",
            "name" => "clinvar_url",
            "type" => "url",
            "required" => 0,
            "wrapper" => [
                "width" => "33",
            ],
        ],
        [
            "key" => "field_clingen_url",
            "label" => "ClinGen URL",
            "name" => "clingen_url",
            "type" => "url",
            "required" => 0,
            "wrapper" => [
                "width" => "33",
            ],
        ],
        [
            "key" => "field_genereviews_url",
            "label" => "GeneReviews URL",
            "name" => "genereviews_url",
            "type" => "url",
            "required" => 0,
            "wrapper" => [
                "width" => "33",
            ],
        ],
        [
            "key" => "field_omim_subtype",
            "label" => "OMIM Subtype Number",
            "name" => "omim_subtype",
            "type" => "text",
            "instructions" =>
                "OMIM entry number for this subtype (digits only).",
            "required" => 0,
            "wrapper" => ["width" => "33"],
        ],
        [
            "key" => "field_omim_gene",
            "label" => "OMIM Gene Number",
            "name" => "omim_gene",
            "type" => "text",
            "instructions" =>
                "OMIM entry number for the associated gene (digits only).",
            "required" => 0,
            "wrapper" => ["width" => "33"],
        ],

        [
            "key" => "tab_schema_markup",
            "label" => "Schema Markup",
            "type" => "tab",
            "placement" => "top",
        ],
        [
            "key" => "field_subtype_specialty",
            "label" => "Medical Specialty",
            "name" => "medical_specialty",
            "type" => "checkbox",
            "instructions" =>
                "Select all schema.org MedicalSpecialty values applicable to this page.",
            "required" => 0,
            "choices" => [
                "Genetic" => "Genetic",
                "Neurologic" => "Neurologic",
                "LaboratoryScience" => "Laboratory Science",
                "Musculoskeletal" => "Musculoskeletal",
                "Pathology" => "Pathology",
                "Pediatric" => "Pediatric",
                "Physiotherapy" => "Physiotherapy",
                "Podiatric" => "Podiatric",
                "PublicHealth" => "Public Health",
                "Pulmonary" => "Pulmonary",
                "RespiratoryTherapy" => "Respiratory Therapy",
                "SpeechPathology" => "Speech Pathology",
                "Surgical" => "Surgical",
            ],
            "layout" => "horizontal",
            "wrapper" => ["width" => "100"],
            "_keep_wrapper" => true,
        ],
        [
            "key" => "field_subtype_medical_audience",
            "label" => "Medical Audience",
            "name" => "medical_audience",
            "type" => "checkbox",
            "instructions" => "Primary intended audience for this page.",
            "required" => 0,
            "choices" => [
                "Patient" => "Patient",
                "Caregiver" => "Caregiver",
                "Clinician" => "Clinician",
                "MedicalResearcher" => "Medical Researcher",
            ],
            "layout" => "horizontal",
            "wrapper" => ["width" => "33"],
        ],
        [
            "key" => "field_subtype_last_reviewed",
            "label" => "Last Reviewed Date",
            "name" => "last_reviewed_date",
            "type" => "date_picker",
            "instructions" =>
                "Date this content was last reviewed for accuracy.",
            "required" => 0,
            "display_format" => "F j, Y",
            "return_format" => "Y-m-d",
            "wrapper" => ["width" => "33"],
        ],
        [
            "key" => "field_subtype_reviewed_by_name",
            "label" => "Reviewed By",
            "name" => "reviewed_by_name",
            "type" => "text",
            "instructions" =>
                "Full name of the reviewing person or organization.",
            "required" => 0,
            "default_value" => "expertsincmt",
            "wrapper" => ["width" => "33"],
        ],
        [
            "key" => "field_subtype_reviewed_by_type",
            "label" => "Reviewer Type",
            "name" => "reviewed_by_type",
            "type" => "select",
            "instructions" => "Is the reviewer a person or an organization?",
            "required" => 0,
            "choices" => [
                "Person" => "Person",
                "Organization" => "Organization",
            ],
            "ui" => 1,
            "allow_null" => 1,
            "multiple" => 0,
            "wrapper" => ["width" => "33"],
        ],
    ];

    // Apply 33% width to every field (except tabs and fields flagged with _keep_wrapper).
    foreach ($fields as &$field) {
        if ($field["type"] !== "tab" && empty($field["_keep_wrapper"])) {
            $field["wrapper"] = ["width" => "33"];
        }
    }

    acf_add_local_field_group([
        "key" => "group_subtype_core_discovery",
        "title" => "Subtype — Core & Discovery",
        "fields" => $fields,
        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "subtype",
                ],
            ],
        ],
        "menu_order" => 0,
        "position" => "acf_after_title",
        "style" => "default",
        "label_placement" => "top",
        "instruction_placement" => "label",
        "active" => true,
        "show_in_rest" => 0,
    ]);
});

add_action(
    "acf/save_post",
    function ($post_id) {
        if (get_post_type($post_id) !== "subtype") {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // --- Unknown Gene normalization ---
        $unknown_gene = (bool) get_field("unknown_gene", $post_id);

        if ($unknown_gene) {
            update_field("field_gene_symbol", "", $post_id);
            update_field("field_full_gene_name", "", $post_id);
            update_field("field_gene_alias", "", $post_id);
        }

        $type = get_field("type_classification", $post_id);
        if (!$type) {
            return;
        }

        $map = [
            "cmt1" => 1,
            "cmt2" => 2,
            "cmt4" => 3,
            "cmtx" => 4,
            "cmtdi" => 5,
            "cmtri" => 6,
            "dhmn" => 7,
            "dsma" => 8,
            "gan" => 9,
            "hmsn" => 10,
            "hsan" => 11,
            "hsn" => 12,
            "smalep" => 13,
            "unclassified" => 14,
        ];

        if (isset($map[$type])) {
            update_field("field_type_sort_order", $map[$type], $post_id);
        }
    },
    20
);
