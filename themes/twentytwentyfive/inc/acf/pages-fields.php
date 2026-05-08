<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * File: ACF — Pages Schema Markup Fields
 * Purpose: Registers a Schema Markup field group for WordPress
 *          Pages, providing structured data fields for specialty,
 *          audience, reviewer, and content aspect signals.
 *
 * Location:
 *   /inc/acf/pages-fields.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_acf_pages_fields");
function eic_acf_pages_fields()
{
    acf_add_local_field_group([
        "key" => "group_eic_pages_schema",
        "title" => "Page Schema Markup",
        "fields" => [
            [
                "key" => "field_eic_pages_tab_schema",
                "label" => "Schema Markup",
                "type" => "tab",
                "placement" => "top",
            ],
            [
                "key" => "field_eic_pages_specialty",
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
            ],
            [
                "key" => "field_eic_pages_aspect",
                "label" => "Page Aspect",
                "name" => "aspect",
                "type" => "select",
                "instructions" => "Select the medical aspect this page covers.",
                "required" => 0,
                "choices" => [
                    "symptoms" => "Symptoms",
                    "causes" => "Causes",
                    "treatment" => "Treatment",
                    "prevention" => "Prevention",
                    "prognosis" => "Prognosis",
                    "overview" => "Overview",
                ],
                "ui" => 1,
                "allow_null" => 1,
                "multiple" => 0,
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_pages_audience",
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
                "key" => "field_eic_pages_last_reviewed",
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
                "key" => "field_eic_pages_reviewed_by_name",
                "label" => "Reviewed By",
                "name" => "reviewed_by_name",
                "type" => "text",
                "instructions" =>
                    "Full name of the reviewing person or organization.",
                "required" => 0,
                "default_value" => "Experts in CMT",
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_pages_reviewed_by_type",
                "label" => "Reviewer Type",
                "name" => "reviewed_by_type",
                "type" => "select",
                "instructions" =>
                    "Is the reviewer a person or an organization?",
                "required" => 0,
                "choices" => [
                    "Person" => "Person",
                    "Organization" => "Organization",
                ],
                "ui" => 1,
                "allow_null" => 1,
                "multiple" => 0,
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_pages_enable_speakable",
                "label" => "Enable Speakable",
                "name" => "enable_speakable",
                "type" => "true_false",
                "instructions" =>
                    "Enable speakable schema on this page. Use only when the page contains substantive prose content suitable for voice and AI surface extraction. Leave off on hub pages, filter-heavy pages, and pages without significant prose.",
                "required" => 0,
                "default_value" => 0,
                "ui" => 1,
                "wrapper" => ["width" => "100"],
            ],
        ],
        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "page",
                ],
            ],
        ],
        "style" => "seamless",
        "position" => "acf_after_title",
        "menu_order" => 0,
    ]);
}
