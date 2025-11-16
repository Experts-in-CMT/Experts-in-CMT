<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Hidden Taxonomy — Glossary Letter
 * ------------------------------------------------------------
 * Registers the private `glossary_letter` taxonomy and keeps it
 * auto-synced to the first alphanumeric character (A–Z, 0–9) of
 * a Glossary term’s canonical term or title.
 *
 * Used for high-performance alphabetical filtering in the
 * Glossary loop and AJAX stack.
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Register the hidden taxonomy.
 */
add_action(
    "init",
    function () {
        $labels = [
            "name" => "Glossary Letters",
            "singular_name" => "Glossary Letter",
        ];

        register_taxonomy(
            "glossary_letter",
            ["glossary"],
            [
                "labels" => $labels,
                "public" => false,
                "show_ui" => false,
                "show_in_menu" => false,
                "show_in_nav_menus" => false,
                "show_tagcloud" => false,
                "hierarchical" => false,
                "rewrite" => false,
                "query_var" => true,
            ]
        );
    },
    5
);

/**
 * Normalize and determine the first alphanumeric character.
 */
function eic_glossary_get_first_letter($string)
{
    if (!is_string($string) || $string === "") {
        return "";
    }
    if (function_exists("remove_accents")) {
        $string = remove_accents($string);
    }
    $string = strtoupper(trim($string));
    $string = preg_replace("/[^A-Z0-9]/", "", $string);
    if ($string === "") {
        return "";
    }
    $first = $string[0];
    return ctype_digit($first) ? "0-9" : $first;
}

/**
 * Ensure base taxonomy terms exist.
 */
function eic_glossary_seed_letter_terms()
{
    $letters = range("A", "Z");
    $letters[] = "0-9";
    foreach ($letters as $letter) {
        if (!term_exists($letter, "glossary_letter")) {
            wp_insert_term($letter, "glossary_letter");
        }
    }
}
add_action("init", "eic_glossary_seed_letter_terms", 15);

/**
 * Auto-sync the taxonomy term on save.
 */
function eic_glossary_autosync_letter($post_id, $post)
{
    if ($post->post_type !== "glossary") {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // Use canonical_term if set, else Title
    $canonical = get_post_meta($post_id, "canonical_term", true);
    if ($canonical === "") {
        $canonical = $post->post_title;
    }

    $letter = eic_glossary_get_first_letter($canonical);
    if ($letter === "") {
        wp_set_object_terms($post_id, null, "glossary_letter");
        return;
    }

    // Ensure term exists
    if (!term_exists($letter, "glossary_letter")) {
        wp_insert_term($letter, "glossary_letter");
    }

    wp_set_object_terms($post_id, $letter, "glossary_letter", false);
}
add_action("save_post", "eic_glossary_autosync_letter", 20, 2);
