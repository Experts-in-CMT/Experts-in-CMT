<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*
 * ------------------------------------------------------------
 * Glossary: MU Plugin: Canonical Uniqueness Guard
 * ------------------------------------------------------------
 * Enforces case-insensitive, normalized uniqueness of Glossary
 * terms using `canonical_term` (fallback to Title).
 *
 * Key behaviors:
 * • Prevents duplicates on save (admin hard block)
 * • ACF inline validation (form-level surfacing)
 * • Auto-fills canonical_term from Title when empty
 * • Ignores auto-drafts, autosaves, and revisions
 * • Bumps glossary version on CRUD for future caches
 *
 * This file must remain in /mu-plugins/ and must load first.
 * No output, BOMs, or whitespace before the opening tag.
 */

if (!defined("ABSPATH")) {
    exit();
}

/** Normalize a term for comparison */
function eic_glossary_normalize($str)
{
    $str = (string) $str;
    if ($str === "") {
        return "";
    }
    if (function_exists("remove_accents")) {
        $str = remove_accents($str);
    }
    $str = strtolower($str);
    $str = preg_replace("/[^\p{L}\p{N}]+/u", " ", $str); // punctuation → space
    $str = preg_replace("/\s+/u", " ", $str); // collapse spaces
    return trim($str);
}

/** Get canonical (raw, not normalized); fallback to post_title */
function eic_glossary_get_canonical_raw($post_id)
{
    $canonical = null;
    if (function_exists("get_field")) {
        $canonical = get_field("canonical_term", $post_id);
    }
    if ($canonical === null) {
        $canonical = get_post_meta($post_id, "canonical_term", true);
    }
    if (!is_string($canonical) || $canonical === "") {
        $post = get_post($post_id);
        return is_object($post) ? (string) $post->post_title : "";
    }
    return (string) $canonical;
}

/** Auto-fill canonical_term from Title on save if empty (skip auto-draft) */
function eic_glossary_autofill_canonical($post_id, $post)
{
    if ($post->post_type !== "glossary") {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if ($post->post_status === "auto-draft") {
        return;
    } // ← guard

    $current = get_post_meta($post_id, "canonical_term", true);
    if ($current === "") {
        $title = (string) $post->post_title;
        $title_norm = eic_glossary_normalize($title);
        if (
            $title !== "" &&
            $title_norm !== "" &&
            $title_norm !== "auto draft"
        ) {
            // ← guard
            update_post_meta($post_id, "canonical_term", $title);
        }
    }
}
add_action("save_post", "eic_glossary_autofill_canonical", 5, 2);

/** Check for duplicates and block save if found (skip auto-draft) */
function eic_glossary_block_duplicates_on_save($post_id, $post, $update)
{
    if ($post->post_type !== "glossary") {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if ($post->post_status === "auto-draft") {
        return;
    } // ← guard

    $raw = eic_glossary_get_canonical_raw($post_id);
    $norm = eic_glossary_normalize($raw);
    if ($norm === "" || $norm === "auto draft") {
        return;
    } // ← guard

    $ids = get_posts([
        "post_type" => "glossary",
        "post_status" => ["publish", "draft", "pending", "future", "private"],
        "posts_per_page" => -1,
        "fields" => "ids",
        "post__not_in" => [$post_id],
        "no_found_rows" => true,
    ]);

    $dupe_id = 0;
    foreach ($ids as $gid) {
        $other_norm = eic_glossary_normalize(
            eic_glossary_get_canonical_raw($gid)
        );
        if ($other_norm !== "" && $other_norm === $norm) {
            $dupe_id = (int) $gid;
            break;
        }
    }

    if ($dupe_id) {
        $edit_url = admin_url("post.php?post=" . $dupe_id . "&action=edit");
        $message =
            "<p><strong>Duplicate Glossary Term:</strong> A term with the same canonical value already exists.</p>";
        $message .=
            '<p>Existing term: <a href="' .
            esc_url($edit_url) .
            '">Edit #' .
            intval($dupe_id) .
            "</a></p>";
        $message .=
            "<p>Tip: Adjust the <em>Canonical Term</em> or consolidate synonyms/AKA on a single entry.</p>";
        wp_die($message, "Duplicate Glossary Term", [
            "response" => 409,
            "back_link" => true,
        ]);
    }
}
add_action("save_post", "eic_glossary_block_duplicates_on_save", 20, 3);

/** ACF inline validation for canonical_term (skip auto-draft) */
function eic_glossary_acf_validate_canonical($valid, $value, $field, $input)
{
    if ($valid !== true) {
        return $valid;
    }
    if (!is_admin()) {
        return $valid;
    }

    $screen = function_exists("get_current_screen")
        ? get_current_screen()
        : null;
    if ($screen && $screen->post_type !== "glossary") {
        return $valid;
    }

    $post_id = isset($_POST["post_ID"]) ? (int) $_POST["post_ID"] : 0;
    if (!$post_id) {
        return $valid;
    }

    $post = get_post($post_id);
    if ($post && $post->post_status === "auto-draft") {
        return $valid;
    } // ← guard

    $raw =
        $value !== ""
            ? (string) $value
            : (is_object($post)
                ? (string) $post->post_title
                : "");
    $norm = eic_glossary_normalize($raw);
    if ($norm === "" || $norm === "auto draft") {
        return $valid;
    } // ← guard

    $ids = get_posts([
        "post_type" => "glossary",
        "post_status" => ["publish", "draft", "pending", "future", "private"],
        "posts_per_page" => -1,
        "fields" => "ids",
        "post__not_in" => [$post_id],
        "no_found_rows" => true,
    ]);
    foreach ($ids as $gid) {
        $other_norm = eic_glossary_normalize(
            eic_glossary_get_canonical_raw($gid)
        );
        if ($other_norm !== "" && $other_norm === $norm) {
            return "Duplicate term detected. Another Glossary entry has the same Canonical Term.";
        }
    }
    return $valid;
}
add_filter(
    "acf/validate_value/name=canonical_term",
    "eic_glossary_acf_validate_canonical",
    10,
    4
);

/** Glossary version bump (for future caches/suggestion dictionaries) */
function eic_glossary_bump_version()
{
    set_transient("eic_glossary_ver", time(), 7 * DAY_IN_SECONDS);
}
add_action("save_post_glossary", "eic_glossary_bump_version", 50);
add_action("trashed_post", function ($post_id) {
    if (get_post_type($post_id) === "glossary") {
        eic_glossary_bump_version();
    }
});
add_action("untrashed_post", function ($post_id) {
    if (get_post_type($post_id) === "glossary") {
        eic_glossary_bump_version();
    }
});
add_action("deleted_post", function ($post_id) {
    if (get_post_type($post_id) === "glossary") {
        eic_glossary_bump_version();
    }
});
