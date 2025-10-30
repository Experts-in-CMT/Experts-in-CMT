<?php
// wp-content/themes/twentytwentyfive/inc/acf/subtype-fields.php
// Subtype — Core & Discovery (ACF Local PHP)

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
            "key" => "field_gene_symbol",
            "label" => "Associated Gene - HGNC-Approved Gene Symbol",
            "name" => "gene_symbol",
            "type" => "text",
            "required" => 1,
        ],
        [
            "key" => "field_full_gene_name",
            "label" => "Full HGNC-Approved Gene Name",
            "name" => "full_gene_name",
            "type" => "text",
            "required" => 1,
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
                "Autosomal Dominant" => "Autosomal Dominant",
                "Autosomal Recessive" => "Autosomal Recessive",
                "Autosomal Dominant or Autosomal Recessive" =>
                    "Autosomal Dominant or Autosomal Recessive",
                "X-Linked Dominant" => "X-Linked Dominant",
                "X-Linked Recessive" => "X-Linked Recessive",
                "Mitochondrial Inheritance" => "Mitochondrial Inheritance",
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
            "key" => "field_year_of_discovery",
            "label" => "Year of Discovery",
            "name" => "year_of_discovery",
            "type" => "number",
            "required" => 1,
            "min" => 1850,
            "step" => 1,
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
            "label" => "Research Button Label",
            "name" => "research_label",
            "type" => "text",
            "required" => 0,
        ],
        [
            "key" => "field_research_url",
            "label" => "Current Research Opportunity",
            "name" => "research_url",
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
            "toolbar" => "basic",
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
            "toolbar" => "basic",
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
            "toolbar" => "basic",
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
            "toolbar" => "basic",
        ],
        [
            "key" => "field_alt_publication_note",
            "label" => "Alt Publication Note",
            "name" => "alt_publication_note",
            "type" => "wysiwyg",
            "required" => 0,
            "media_upload" => 0,
            "tabs" => "all",
            "toolbar" => "basic",
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
            "toolbar" => "basic",
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
    ];

    // Apply 33% width to every field (except tabs)
    foreach ($fields as &$field) {
        if ($field["type"] !== "tab") {
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
