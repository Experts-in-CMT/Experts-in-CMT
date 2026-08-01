<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Plugin Name: EIC Glossary Importer
 * Description: Imports authored glossary terms (JSON) into the `glossary` post type. Dry-run then commit, upsert by canonical term.
 * Version: 1.0.0
 * Author: Kenneth Raymond
 *
 * ------------------------------------------------------------
 * Glossary Importer (mirrors the Subtype Importer)
 * ------------------------------------------------------------
 * Tools > Glossary Importer
 *
 * Accepts authored glossary JSON: a single record, an array of
 * records, or an object of the form {"glossary":[ ... ]}.
 *
 * NOTE (round-trip): this importer expects the AUTHORED shape below,
 * NOT the lossless backup produced by eic-glossary-exporter.php. An
 * export file will NOT import (it yields "No records found"); exports
 * restore via the database, not through this tool. The two formats
 * are intentionally different.
 *
 * Per-record schema (all text; images are handled by their own
 * tools and are NOT part of this import):
 *   {
 *     "term":                "AFO",                 // -> post_title (required)
 *     "canonical_term":      "AFO",                 // ACF; defaults to term
 *     "definition":          "<p>full body...</p>", // -> post_content
 *     "excerpt":             "",                    // -> post_excerpt (optional)
 *     "short_definition":    "card teaser...",      // ACF
 *     "source_url":          "https://...",         // ACF
 *     "source_label":        "Source",              // ACF; defaults to "Source"
 *     "aka_synonyms":        "a, b, c",             // ACF
 *     "common_misspellings": "x, y, z",             // ACF
 *     "banner_title":        "AFO",                 // ACF; defaults to term
 *     "banner_intro":        "Ankle Foot Orthosis", // ACF
 *     "notes_admin":         ""                     // ACF (optional)
 *   }
 *
 * Matching / upsert:
 *   - Existing terms are matched by NORMALIZED canonical_term
 *     (fallback to title), using the same normalizer as the
 *     glossary uniqueness guard (eic_glossary_normalize). Found =
 *     update, not found = create. Safe to re-run.
 *
 * Not written here:
 *   - term_image, banner_image (image fields; set via the media /
 *     header-banner image tools).
 *   - glossary_letter taxonomy (auto-synced on save by its own plugin).
 */

if (!defined("ABSPATH")) {
    exit();
}

final class EIC_Glossary_Importer
{
    const POST_TYPE = "glossary";
    const CAP = "manage_options";
    const NONCE = "eic_glossary_import_nonce";
    const SLUG = "eic-glossary-importer";

    /* ACF text fields written from the JSON (selector => json key). */
    private static function acf_map(): array
    {
        return [
            "canonical_term" => "canonical_term",
            "short_definition" => "short_definition",
            "source_url" => "source_url",
            "source_label" => "source_label",
            "aka_synonyms" => "aka_synonyms",
            "common_misspellings" => "common_misspellings",
            "banner_title" => "banner_title",
            "banner_intro" => "banner_intro",
            "notes_admin" => "notes_admin",
        ];
    }

    private static $preview = false;
    private static $changes = 0;
    private static $changed = [];

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Glossary Importer",
            "Glossary Importer",
            self::CAP,
            self::SLUG,
            [__CLASS__, "render"]
        );
    }

    /* ---------------------------------------------------------- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            return;
        }

        eic_admin_tool_open("Glossary Importer");
        echo "<p>Paste authored glossary JSON (single record, array, or " .
            "<code>{\"glossary\":[...]}</code>), or upload a <code>.json</code> " .
            "file. Run-once style: dry-run, then commit. Matches existing terms " .
            "by canonical term and updates them, otherwise creates new ones. " .
            "<strong>Take a database backup before committing.</strong> Does not " .
            "write term_image or banner_image (owned by their tools).</p>";

        $action = $_POST["eic_action"] ?? "";
        $raw = isset($_POST["json"]) ? (string) wp_unslash($_POST["json"]) : "";

        if ($action && check_admin_referer(self::NONCE)) {
            // An uploaded .json file takes precedence over the textarea; the
            // textarea then re-populates from it so commit works without re-upload.
            if (
                !empty($_FILES["json_file"]["tmp_name"]) &&
                is_uploaded_file($_FILES["json_file"]["tmp_name"])
            ) {
                $uploaded = file_get_contents($_FILES["json_file"]["tmp_name"]);
                if ($uploaded !== false && trim($uploaded) !== "") {
                    $raw = $uploaded;
                }
            }

            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<div class="notice notice-error"><p>JSON parse error: ' .
                    esc_html(json_last_error_msg()) . "</p></div>";
            } else {
                $records = self::extract_records($json);
                if (!$records) {
                    echo '<div class="notice notice-error"><p>No records found in JSON.</p></div>';
                } elseif ($action === "dryrun") {
                    self::run($records, true);
                } elseif ($action === "commit") {
                    if (empty($_POST["confirm"])) {
                        echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
                    } else {
                        self::run($records, false);
                    }
                }
            }
        }

        echo '<hr><form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE);
        echo '<p><textarea name="json" rows="16" style="width:100%;font-family:monospace" placeholder="Paste glossary JSON here, or upload a .json file below">' .
            esc_textarea($raw) . "</textarea></p>";
        echo '<p><label>Or upload a <code>.json</code> file: ' .
            '<input type="file" name="json_file" accept=".json,application/json">' .
            "</label> <em>(an uploaded file overrides the box above)</em></p>";
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have taken a backup and reviewed the dry run.</label></p>';
        echo '<button class="button button-primary eic-danger" name="eic_action" value="commit">Commit (create / update terms)</button>';
        echo "</form>";
        eic_admin_tool_close();
    }

    /* ---------------------------------------------------------- */

    /** Normalize a JSON payload into a flat list of record arrays. */
    private static function extract_records($json): array
    {
        if (!is_array($json)) {
            return [];
        }
        if (isset($json["glossary"]) && is_array($json["glossary"])) {
            $json = $json["glossary"];
        }
        // Single record (associative) vs. list of records.
        $is_list = array_keys($json) === range(0, count($json) - 1);
        $rows = $is_list ? $json : [$json];

        $out = [];
        foreach ($rows as $r) {
            if (is_array($r) && trim((string) ($r["term"] ?? "")) !== "") {
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Build a map of normalized canonical term => post_id for every
     * existing glossary post, so we can upsert without duplicates.
     */
    private static function build_index(): array
    {
        $index = [];
        $ids = get_posts([
            "post_type" => self::POST_TYPE,
            "post_status" => ["publish", "draft", "pending", "private", "future"],
            "posts_per_page" => -1,
            "fields" => "ids",
            "no_found_rows" => true,
            "suppress_filters" => true,
        ]);
        foreach ($ids as $id) {
            $title = get_the_title($id);
            $canon = function_exists("eic_glossary_get_canonical_raw")
                ? eic_glossary_get_canonical_raw($id)
                : $title;
            // Index by BOTH normalized canonical and title, so a record still
            // matches when a stored canonical is stale (e.g. "Auto Draft").
            foreach ([$canon, $title] as $val) {
                $key = self::normalize($val);
                if ($key !== "" && !isset($index[$key])) {
                    $index[$key] = (int) $id;
                }
            }
        }
        return $index;
    }

    private static function normalize($str): string
    {
        if (function_exists("eic_glossary_normalize")) {
            return eic_glossary_normalize($str);
        }
        $str = strtolower(trim((string) $str));
        $str = preg_replace("/[^\p{L}\p{N}]+/u", " ", $str);
        return trim(preg_replace("/\s+/u", " ", $str));
    }

    /* ---------------------------------------------------------- */

    /** Dry-run (preview) or commit. */
    private static function run(array $records, bool $preview): void
    {
        self::$preview = $preview;
        $index = self::build_index();

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $failed = 0;

        echo "<h2>" . ($preview ? "Dry run" : "Import complete") . " — " .
            count($records) . " record(s)</h2><ul>";

        foreach ($records as $r) {
            $term = trim((string) $r["term"]);
            $canonical = trim((string) ($r["canonical_term"] ?? "")) ?: $term;
            $key = self::normalize($canonical);
            $existing = $index[$key] ?? 0;
            $label = esc_html($term);

            self::$changes = 0;
            self::$changed = [];

            if ($existing) {
                self::write_record($existing, $r);
                if (self::$changes > 0) {
                    echo "<li><strong>{$label}</strong>: " .
                        ($preview ? "would update" : "updated") .
                        " (ID {$existing}), " . self::$changes .
                        " field(s): <small>" .
                        esc_html(implode(", ", array_unique(self::$changed))) .
                        "</small></li>";
                    $updated++;
                } else {
                    echo "<li><strong>{$label}</strong>: unchanged (ID {$existing}).</li>";
                    $unchanged++;
                }
            } else {
                if ($preview) {
                    echo "<li><strong>{$label}</strong>: would create.</li>";
                    $created++;
                    continue;
                }
                $post_id = wp_insert_post(
                    [
                        "post_type" => self::POST_TYPE,
                        "post_status" => "publish",
                        "post_title" => $term,
                        "post_content" => (string) ($r["definition"] ?? ""),
                        "post_excerpt" => (string) ($r["excerpt"] ?? ""),
                    ],
                    true
                );
                if (is_wp_error($post_id)) {
                    echo "<li><strong>{$label}</strong>: insert failed — " .
                        esc_html($post_id->get_error_message()) . "</li>";
                    $failed++;
                    continue;
                }
                self::write_record($post_id, $r);
                $index[$key] = (int) $post_id;
                echo "<li><strong>{$label}</strong>: created (ID {$post_id}).</li>";
                $created++;
            }
        }

        echo "</ul>";
        echo '<div class="notice notice-' .
            ($preview ? "info" : "success") . '"><p><strong>' .
            ($preview ? "Dry run." : "Done.") . "</strong> " .
            ($preview ? "Would create" : "Created") . " {$created}, " .
            ($preview ? "update" : "updated") . " {$updated}, " .
            "unchanged {$unchanged}, failed {$failed}.</p></div>";
    }

    /**
     * Write core post fields + ACF text fields for a record. In preview
     * mode, diffs are computed and counted but nothing is written.
     */
    private static function write_record(int $post_id, array $r): void
    {
        $term = trim((string) $r["term"]);

        // Core post fields. Only touch body/excerpt when the key is present,
        // so an update payload that omits them never blanks live content.
        self::set_post($post_id, "post_title", $term);
        if (array_key_exists("definition", $r)) {
            self::set_post($post_id, "post_content", (string) $r["definition"]);
        }
        if (array_key_exists("excerpt", $r)) {
            self::set_post($post_id, "post_excerpt", (string) $r["excerpt"]);
        }

        // ACF text fields, with sensible defaults.
        $vals = [
            "canonical_term" => trim((string) ($r["canonical_term"] ?? "")) ?: $term,
            "short_definition" => (string) ($r["short_definition"] ?? ""),
            "source_url" => (string) ($r["source_url"] ?? ""),
            "source_label" => trim((string) ($r["source_label"] ?? "")) ?: "Source",
            "aka_synonyms" => (string) ($r["aka_synonyms"] ?? ""),
            "common_misspellings" => (string) ($r["common_misspellings"] ?? ""),
            "banner_title" => trim((string) ($r["banner_title"] ?? "")) ?: $term,
            "banner_intro" => (string) ($r["banner_intro"] ?? ""),
            "notes_admin" => (string) ($r["notes_admin"] ?? ""),
        ];
        foreach (self::acf_map() as $selector => $json_key) {
            // Only write keys actually present in the JSON, except the
            // defaulted identity/label fields which always apply.
            $always = in_array(
                $selector,
                ["canonical_term", "source_label", "banner_title"],
                true
            );
            if ($always || array_key_exists($json_key, $r)) {
                self::set_field($selector, $vals[$selector], $post_id);
            }
        }
    }

    /** Update a core post column only if it differs. */
    private static function set_post(int $post_id, string $field, string $value): void
    {
        $post = get_post($post_id);
        $current = $post ? (string) $post->{$field} : "";
        if ($current === $value) {
            return;
        }
        if (!self::$preview) {
            wp_update_post(["ID" => $post_id, $field => $value]);
        }
        self::note($field);
    }

    /** Update an ACF field only if it differs (key-resolved for new posts). */
    private static function set_field(string $selector, $value, int $post_id): void
    {
        $key = $selector;
        if (function_exists("acf_get_field")) {
            $fo = acf_get_field($selector);
            if ($fo && !empty($fo["key"])) {
                $key = $fo["key"];
            }
        }
        $current = function_exists("get_field")
            ? get_field($selector, $post_id)
            : get_post_meta($post_id, $selector, true);

        if (trim((string) $current) === trim((string) $value)) {
            return;
        }
        if (!self::$preview) {
            if (function_exists("update_field")) {
                update_field($key, $value, $post_id);
            } else {
                update_post_meta($post_id, $selector, $value);
            }
        }
        self::note($selector);
    }

    private static function note(string $field): void
    {
        self::$changes++;
        self::$changed[] = $field;
    }
}

EIC_Glossary_Importer::init();
