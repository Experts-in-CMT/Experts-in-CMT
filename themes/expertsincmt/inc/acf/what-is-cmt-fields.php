<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * File: ACF — What Is CMT Topic Fields
 * Purpose: Registers fields for the What Is CMT Topics CPT.
 * Includes Core content fields and Schema Markup fields.
 *
 * Location:
 *   /inc/acf/what-is-cmt-fields.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("init", "eic_acf_what_is_cmt_fields");
function eic_acf_what_is_cmt_fields()
{
    acf_add_local_field_group([
        "key" => "group_eic_what_is_cmt",
        "title" => "What Is CMT Topic Fields",
        "fields" => [
            [
                "key" => "field_eic_wic_tab_core",
                "label" => "Core",
                "type" => "tab",
                "placement" => "top",
            ],
            [
                "key" => "field_eic_wic_title",
                "label" => "Topic Title",
                "name" => "topic_title",
                "type" => "text",
                "instructions" =>
                    "Enter the title exactly as it should appear on the page.",
                "required" => 1,
                "maxlength" => 120,
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_wic_summary",
                "label" => "Short Summary",
                "name" => "summary",
                "type" => "textarea",
                "instructions" => "2-4 sentences. Maximum 600 characters.",
                "required" => 1,
                "maxlength" => 600,
                "rows" => 3,
                "new_lines" => "",
                "wrapper" => ["width" => "33"],
            ],
            [
                "key" => "field_eic_wic_topic_order",
                "label" => "Topic Order",
                "name" => "topic_order",
                "type" => "number",
                "instructions" =>
                    "Set the topic sequence number (1, 2, 3...). Lower numbers appear earlier.",
                "required" => 1,
                "wrapper" => ["width" => "33"],
                "min" => "",
                "max" => "",
                "step" => 1,
            ],
            [
                "key" => "field_eic_wic_tab_schema",
                "label" => "Schema Markup",
                "type" => "tab",
                "placement" => "top",
            ],
            [
                "key" => "field_eic_wic_specialty",
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
                "key" => "field_eic_wic_aspect",
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
                "key" => "field_eic_wic_audience",
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
                "key" => "field_eic_wic_last_reviewed",
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
                "key" => "field_eic_wic_reviewed_by_name",
                "label" => "Reviewed By",
                "name" => "reviewed_by_name",
                "type" => "text",
                "instructions" =>
                    "Full name of the reviewing person or organization.",
                "required" => 0,
                "default_value" => "expertsincmt",
                "wrapper" => ["width" => "50"],
            ],
            [
                "key" => "field_eic_wic_reviewed_by_type",
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
        ],
        "location" => [
            [
                [
                    "param" => "post_type",
                    "operator" => "==",
                    "value" => "what-is-cmt",
                ],
            ],
        ],
        "style" => "seamless",
        "position" => "acf_after_title",
        "menu_order" => 0,
    ]);
}
