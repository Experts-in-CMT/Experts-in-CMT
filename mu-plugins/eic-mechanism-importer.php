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
 * MU Plugin: EIC Mechanism Importer (run-once)
 * ------------------------------------------------------------
 * Tools > Mechanism Importer
 *
 * Loads the corrected variant-mechanism dataset onto EXISTING subtype
 * records. Update-only: it never creates a subtype. Each record is matched
 * to a subtype by its `code`, and only the mechanism fields are written,
 * diff-only, with a dry run first.
 *
 * Writes exactly five ACF fields on a matched subtype:
 *   mechanism            <- new_call   (mapped to the select key)
 *   mechanism_flavor     <- flavor     (verbatim; validated against the enum)
 *   mechanism_confidence <- confidence (verbatim; validated against the enum)
 *   mechanism_prediction <- prediction (textarea)
 *   mechanism_rationale  <- rationale  (textarea)
 *
 * Ignores dataset bookkeeping keys (old_label, skeptic_flip, conflation,
 * changed, grey, grey_note, gene, inheritance) and never touches any
 * non-mechanism field. Unmatched records are reported and skipped.
 *
 * Accepts: a bare array of records, a single record object, or
 *          { "subtypes":[...] } / { "records":[...] }.
 *
 * Location: wp-content/mu-plugins/eic-mechanism-importer.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Mechanism_Importer
{
    const CAP   = "manage_options";
    const NONCE = "eic_mechanism_importer";

    /* new_call (dataset form, case-insensitive) => mechanism select key */
    private static function call_map(): array
    {
        return [
            "lof"                    => "lof",
            "loss of function"       => "lof",
            "dominant-negative"      => "dominant_negative",
            "dominant negative"      => "dominant_negative",
            "gof"                    => "gof",
            "toxic gain of function" => "gof",
            "gain of function"       => "gof",
            "complex"                => "complex",
            "unknown"                => "unknown",
        ];
    }

    private static $FLAVORS = [
        "biallelic", "haploinsufficiency", "dosage", "dominant-negative",
        "neomorphic", "overactivity", "repeat-expansion", "mixed",
        "unresolved", "no-gene",
    ];
    private static $CONF = ["high", "medium", "low"];

    /* ---- Bootstrap ---- */

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Mechanism Importer",
            "Mechanism Importer",
            self::CAP,
            "eic-mechanism-importer",
            [__CLASS__, "render"]
        );
    }

    /* ---- Parse ---- */

    /** Normalize input to a list of record arrays. */
    private static function extract_records($json): array
    {
        if (isset($json["subtypes"]) && is_array($json["subtypes"])) {
            return $json["subtypes"];
        }
        if (isset($json["records"]) && is_array($json["records"])) {
            return $json["records"];
        }
        if (isset($json["code"])) {
            return [$json]; // single record object
        }
        if (is_array($json) && isset($json[0])) {
            return $json; // bare array
        }
        return [];
    }

    /* ---- Match ----
     * Resolve a subtype record by `code`, trying, in order:
     *   1) the ACF `subtype` field value (exact),
     *   2) the exact post_title,
     *   3) the post slug = sanitize_title(code).
     * Returns [WP_Post|null, how]. */
    private static function find_subtype(string $code): array
    {
        $code = trim($code);
        if ($code === "") {
            return [null, ""];
        }
        $status = ["publish", "draft", "pending", "private", "future"];

        $q = new WP_Query([
            "post_type"      => "subtype",
            "posts_per_page" => 1,
            "no_found_rows"  => true,
            "ignore_sticky_posts" => true,
            "post_status"    => $status,
            "meta_query"     => [[
                "key"     => "subtype",
                "value"   => $code,
                "compare" => "=",
            ]],
        ]);
        if ($q->have_posts()) {
            return [$q->posts[0], "subtype field"];
        }

        $q = new WP_Query([
            "post_type"      => "subtype",
            "posts_per_page" => 1,
            "no_found_rows"  => true,
            "ignore_sticky_posts" => true,
            "post_status"    => $status,
            "title"          => $code,
        ]);
        if ($q->have_posts()) {
            return [$q->posts[0], "post title"];
        }

        $slug = sanitize_title($code);
        $q = new WP_Query([
            "post_type"      => "subtype",
            "name"           => $slug,
            "posts_per_page" => 1,
            "no_found_rows"  => true,
            "ignore_sticky_posts" => true,
            "post_status"    => $status,
        ]);
        if ($q->have_posts()) {
            return [$q->posts[0], "slug ({$slug})"];
        }

        return [null, ""];
    }

    /* ---- Validate + normalize ---- */

    /** Resolve the mechanism select key for a record's new_call, or "" if none. */
    private static function resolve_call($v): string
    {
        $k = strtolower(trim((string) $v));
        $map = self::call_map();
        return $map[$k] ?? "";
    }

    /** Return [errors[], plan[]] for one record. plan has the five target values. */
    private static function analyze(array $r): array
    {
        $errors = [];
        $code = trim((string) ($r["code"] ?? ""));
        if ($code === "") {
            $errors[] = "missing code";
        }

        $call = self::resolve_call($r["new_call"] ?? "");
        if (($r["new_call"] ?? "") === "") {
            $errors[] = "missing new_call";
        } elseif ($call === "") {
            $errors[] = "unrecognized new_call: " . (string) $r["new_call"];
        }

        $flavor = trim((string) ($r["flavor"] ?? ""));
        if ($flavor !== "" && !in_array($flavor, self::$FLAVORS, true)) {
            $errors[] = "invalid flavor: " . $flavor;
        }

        $conf = trim((string) ($r["confidence"] ?? ""));
        if ($conf !== "" && !in_array($conf, self::$CONF, true)) {
            $errors[] = "invalid confidence: " . $conf;
        }

        $plan = [
            "code"                 => $code,
            "mechanism"            => $call,
            "mechanism_flavor"     => $flavor,
            "mechanism_confidence" => $conf,
            "mechanism_prediction" => (string) ($r["prediction"] ?? ""),
            "mechanism_rationale"  => (string) ($r["rationale"] ?? ""),
        ];
        return [$errors, $plan];
    }

    /* ---- Render ---- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        $has_wrap = function_exists("eic_admin_tool_open");
        if ($has_wrap) {
            eic_admin_tool_open("Mechanism Importer");
        } else {
            echo '<div class="wrap"><h1>Mechanism Importer</h1>';
        }

        echo "<p>Loads the corrected variant-mechanism dataset onto <strong>existing</strong> " .
            "subtype records (paste JSON, or upload a <code>.json</code> file). " .
            "Update-only: it never creates a subtype. Writes only " .
            "<code>mechanism</code>, <code>mechanism_flavor</code>, " .
            "<code>mechanism_confidence</code>, <code>mechanism_prediction</code>, and " .
            "<code>mechanism_rationale</code>, diff-only. Dry-run first, then commit. " .
            "<strong>Take a database backup before committing.</strong></p>";

        $action = $_POST["eic_action"] ?? "";
        $raw = isset($_POST["json"]) ? (string) wp_unslash($_POST["json"]) : "";

        if ($action && check_admin_referer(self::NONCE)) {
            if (
                !empty($_FILES["json_file"]["tmp_name"]) &&
                is_uploaded_file($_FILES["json_file"]["tmp_name"])
            ) {
                $uploaded = file_get_contents($_FILES["json_file"]["tmp_name"]);
                if ($uploaded !== false && trim($uploaded) !== "") {
                    $raw = $uploaded;
                }
            }

            // Strip a leading UTF-8 BOM (common from Windows editors) so a
            // BOM-prefixed upload does not fail json_decode with a syntax error.
            $raw = preg_replace('/^\xEF\xBB\xBF/', "", (string) $raw);
            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<div class="notice notice-error"><p>JSON parse error: ' .
                    esc_html(json_last_error_msg()) . "</p></div>";
            } else {
                $records = self::extract_records($json);
                if (!$records) {
                    echo '<div class="notice notice-error"><p>No records found in JSON.</p></div>';
                } elseif ($action === "dryrun") {
                    self::do_dryrun($records);
                } elseif ($action === "commit") {
                    self::do_commit($records);
                }
            }
        }

        echo '<hr><form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE);
        echo '<p><textarea name="json" rows="14" style="width:100%;font-family:monospace" ' .
            'placeholder="Paste mechanism JSON here, or upload a .json file below">' .
            esc_textarea($raw) . "</textarea></p>";
        echo '<p><label>Or upload a <code>.json</code> file: ' .
            '<input type="file" name="json_file" accept=".json,application/json">' .
            "</label> <em>(an uploaded file overrides the box above)</em></p>";
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have taken a backup and reviewed the dry run.</label></p>';
        echo '<button class="button button-primary eic-danger" name="eic_action" value="commit">Commit (write mechanism fields)</button>';
        echo "</form>";

        if ($has_wrap) {
            eic_admin_tool_close();
        } else {
            echo "</div>";
        }
    }

    private static function do_dryrun(array $records): void
    {
        $matched = 0; $unmatched = 0; $invalid = 0;
        echo "<h2>Dry run &mdash; " . count($records) . " record(s)</h2>";

        foreach ($records as $i => $r) {
            $code = esc_html(is_array($r) && isset($r["code"]) ? $r["code"] : "record " . ($i + 1));
            [$errors, $plan] = self::analyze(is_array($r) ? $r : []);

            echo '<div style="margin:14px 0;padding:12px 16px;border:1px solid #dcdcde;background:#fff;border-radius:6px">';
            echo "<h3 style='margin-top:0'>" . $code . "</h3>";

            if ($errors) {
                echo '<p style="color:#b32d2e"><strong>Invalid &mdash; will be skipped:</strong></p><ul>';
                foreach ($errors as $er) {
                    echo "<li>" . esc_html($er) . "</li>";
                }
                echo "</ul></div>";
                $invalid++;
                continue;
            }

            [$post, $how] = self::find_subtype($plan["code"]);
            if (!$post) {
                echo '<p style="color:#b32d2e"><strong>No matching subtype &mdash; will be skipped.</strong> ' .
                    'Tried the subtype field, exact title, and slug.</p></div>';
                $unmatched++;
                continue;
            }

            $matched++;
            self::$preview = true;
            self::$changes = 0;
            self::$diffs = [];
            self::write_record((int) $post->ID, $plan);
            $diffs = self::$diffs; $n = self::$changes;
            self::$preview = false; self::$diffs = [];

            echo '<p style="color:#1a7f37"><strong>Matched</strong> "' .
                esc_html($post->post_title) . '" (ID ' . (int) $post->ID .
                ', by ' . esc_html($how) . ').</strong></p>';

            if ($n === 0) {
                echo '<p><strong>No changes &mdash; already identical.</strong></p>';
            } else {
                echo '<p style="color:#996800"><strong>Commit will change ' . (int) $n .
                    ' field(s):</strong></p>';
                echo '<table class="widefat striped"><thead><tr>' .
                    '<th style="width:200px">Field</th><th>Before</th><th>After</th>' .
                    '</tr></thead><tbody>';
                foreach ($diffs as $d) {
                    echo "<tr><td><strong>" . esc_html($d["field"]) . "</strong></td>" .
                        "<td>" . self::preview_val($d["from"]) . "</td>" .
                        "<td>" . self::preview_val($d["to"]) . "</td></tr>";
                }
                echo "</tbody></table>";
            }
            echo "</div>";
        }

        echo '<div class="notice notice-info"><p><strong>Dry run complete.</strong> ' .
            "Matched {$matched}, unmatched {$unmatched}, invalid {$invalid}.</p></div>";
    }

    private static function do_commit(array $records): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }

        $updated = 0; $unchanged = 0; $unmatched = 0; $invalid = 0;
        echo "<h2>Commit</h2><ul>";

        foreach ($records as $i => $r) {
            $code = esc_html(is_array($r) && isset($r["code"]) ? $r["code"] : "record " . ($i + 1));
            [$errors, $plan] = self::analyze(is_array($r) ? $r : []);
            if ($errors) {
                echo "<li><strong>{$code}</strong>: invalid, skipped (" .
                    esc_html(implode("; ", $errors)) . ").</li>";
                $invalid++;
                continue;
            }

            [$post, $how] = self::find_subtype($plan["code"]);
            if (!$post) {
                echo "<li><strong>{$code}</strong>: no matching subtype, skipped.</li>";
                $unmatched++;
                continue;
            }

            $post_id = (int) $post->ID;
            self::$preview = false;
            self::$changes = 0;
            self::$diffs = [];
            self::write_record($post_id, $plan);

            if (self::$changes > 0) {
                echo "<li><strong>{$code}</strong>: updated (ID {$post_id}, by " .
                    esc_html($how) . "), " . self::$changes . " field(s).</li>";
                $updated++;
            } else {
                echo "<li><strong>{$code}</strong>: unchanged (ID {$post_id}).</li>";
                $unchanged++;
            }
        }

        echo "</ul>";
        echo '<div class="notice notice-success"><p><strong>Done.</strong> ' .
            "Updated {$updated}, unchanged {$unchanged}, unmatched {$unmatched}, " .
            "invalid {$invalid}.</p></div>";
    }

    /** Write the five mechanism fields, diff-only. */
    private static function write_record(int $post_id, array $plan): void
    {
        self::set_field("mechanism", $plan["mechanism"], $post_id);
        self::set_field("mechanism_flavor", $plan["mechanism_flavor"], $post_id);
        self::set_field("mechanism_confidence", $plan["mechanism_confidence"], $post_id);
        self::set_field("mechanism_prediction", $plan["mechanism_prediction"], $post_id);
        self::set_field("mechanism_rationale", $plan["mechanism_rationale"], $post_id);
    }

    /* ---- Field writer (diff-only, preview-aware) ---- */

    private static $changes = 0;
    private static $preview = false;
    private static $diffs = [];

    private static function set_field($selector, $value, int $post_id): void
    {
        $key = $selector;
        $fo = function_exists("acf_get_field") ? acf_get_field($selector) : null;
        if ($fo && !empty($fo["key"])) {
            $key = $fo["key"];
        }
        $current = function_exists("get_field") ? get_field($selector, $post_id) : get_post_meta($post_id, $selector, true);
        if (!self::values_equal($current, $value)) {
            if (!self::$preview) {
                if (function_exists("update_field")) {
                    update_field($key, $value, $post_id);
                } else {
                    update_post_meta($post_id, $selector, $value);
                }
            }
            self::note_change(is_string($selector) ? $selector : (string) $key, $current, $value);
        }
    }

    private static function values_equal($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            $a = is_array($a) ? $a : [$a];
            $b = is_array($b) ? $b : [$b];
            $a = array_map("strval", $a);
            $b = array_map("strval", $b);
            sort($a); sort($b);
            return $a === $b;
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        return trim((string) $a) === trim((string) $b);
    }

    private static function note_change(string $field, $from, $to): void
    {
        self::$changes++;
        self::$diffs[] = ["field" => $field, "from" => $from, "to" => $to];
    }

    private static function preview_val($v): string
    {
        if (is_array($v)) {
            $v = implode(", ", array_map("strval", $v));
        }
        $v = (string) $v;
        if ($v === "") {
            return '<em style="color:#888">(empty)</em>';
        }
        $len = strlen($v);
        if ($len > 200) {
            return esc_html(substr($v, 0, 200)) . ' <em style="color:#888">&hellip; (' . $len . ' chars)</em>';
        }
        return esc_html($v);
    }
}

EIC_Mechanism_Importer::init();
