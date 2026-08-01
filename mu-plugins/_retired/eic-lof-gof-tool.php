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
 * MU Plugin: EIC Variant Mechanism (LoF / GoF) Backfill
 * ------------------------------------------------------------
 * Tools > LoF / GoF Mechanism.
 *
 * Sets the two ACF true/false flags on subtype records:
 *   lof_variant  (Loss of Function)
 *   gof_variant  (Toxic Gain of Function)
 *
 * Subtypes are matched by their subtype code (the record's `subtype`
 * field / post title, e.g. CMT1A), case-insensitive. Note: the ACF
 * field literally named "acronym" holds the broad class (CMT1, CMT2,
 * ...), NOT the per-record code, so matching uses subtype/title.
 * Paste a simple list, one subtype per line:
 *
 *     CMT1A: LoF
 *     CMT2A: GoF
 *     CMT4B1: LoF, GoF        (both mechanisms)
 *     CMTX1: none            (clears both flags)
 *
 * A JSON object is also accepted, e.g.
 *     {"CMT1A":"LoF","CMT4B1":["LoF","GoF"],"CMTX1":"none"}
 *
 * Each listed line is authoritative: it sets BOTH flags for that
 * subtype (a value the line does not name is turned off), so a
 * re-run is idempotent. Subtypes not in the list are left alone.
 *
 * Dry-run then confirm, capability- and nonce-gated, on the shared
 * EIC admin shell.
 *
 * Location: wp-content/mu-plugins/eic-lof-gof-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_LoF_GoF_Tool
{
    const CAP = "manage_options";
    const NONCE = "eic_lof_gof_tool";

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "LoF / GoF Mechanism",
            "LoF / GoF Mechanism",
            self::CAP,
            "eic-lof-gof",
            [__CLASS__, "render"]
        );
    }

    /**
     * Build a case-insensitive map of subtype code => [subtype IDs].
     * The code is the record's `subtype` field (== post title), e.g.
     * "CMT1A"; both are indexed so either matches. A list of IDs (not
     * a single ID) so an ambiguous code is reported rather than
     * silently writing to the wrong record.
     */
    private static function code_map(): array
    {
        $ids = get_posts([
            "post_type" => "subtype",
            "post_status" => "any",
            "posts_per_page" => -1,
            "fields" => "ids",
            "no_found_rows" => true,
        ]);
        $map = [];
        foreach ($ids as $id) {
            $keys = [
                strtoupper(trim((string) get_field("subtype", $id))),
                strtoupper(trim((string) get_the_title($id))),
            ];
            foreach (array_unique(array_filter($keys)) as $k) {
                $map[$k][] = $id;
            }
        }
        foreach ($map as $k => $list) {
            $map[$k] = array_values(array_unique($list));
        }
        return $map;
    }

    /**
     * Parse the pasted input into [ ACRONYM => ['lof'=>bool,'gof'=>bool] ].
     * Accepts a line list ("CMT1A: LoF, GoF") or a JSON object.
     * Returns [ $entries, $errors ].
     */
    private static function parse(string $raw): array
    {
        $raw = trim($raw);
        $entries = [];
        $errors = [];
        if ($raw === "") {
            return [$entries, ["No input provided."]];
        }

        // JSON object form: { "CMT1A": "LoF", "CMT4B1": ["LoF","GoF"] }
        if ($raw[0] === "{" || $raw[0] === "[") {
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return [$entries, ["Input looks like JSON but did not parse."]];
            }
            foreach ($data as $key => $val) {
                $acr = strtoupper(trim((string) $key));
                $val = is_array($val) ? implode(",", $val) : (string) $val;
                if ($acr === "") {
                    continue;
                }
                $entries[$acr] = self::interpret($val);
            }
            return [$entries, $errors];
        }

        // Line form: "ACRONYM: value(s)" (also accepts ACRONYM = value)
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        foreach ($lines as $n => $line) {
            $line = trim($line);
            if ($line === "" || $line[0] === "#") {
                continue;
            }
            if (!preg_match('/^(.+?)\s*[:=]\s*(.*)$/', $line, $m)) {
                $errors[] = sprintf(
                    "Line %d skipped (expected 'code: value'): %s",
                    $n + 1,
                    esc_html($line)
                );
                continue;
            }
            $acr = strtoupper(trim($m[1]));
            $entries[$acr] = self::interpret($m[2]);
        }
        return [$entries, $errors];
    }

    /**
     * Interpret a mechanism value string into the two flags.
     * "lof"/"loss" => LoF, "gof"/"gain" => GoF, both tokens => both,
     * "none"/"neither"/"clear"/"unknown"/empty => clears both.
     */
    private static function interpret(string $val): array
    {
        $v = strtolower($val);
        $lof = strpos($v, "lof") !== false || strpos($v, "loss") !== false;
        $gof = strpos($v, "gof") !== false || strpos($v, "gain") !== false;
        // Explicit clear words win only when no positive token is present.
        return ["lof" => $lof, "gof" => $gof];
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        eic_admin_tool_open(
            "LoF / GoF Mechanism",
            "Set the Loss of Function and Gain of Function flags on subtypes, matched by subtype code (e.g. CMT1A)."
        );

        echo "<p>Paste one subtype per line as <code>CODE: value</code> (code is the subtype, e.g. CMT1A). " .
            "Values: <code>LoF</code>, <code>GoF</code>, <code>LoF, GoF</code> (both), " .
            "or <code>none</code> (clears both). Each line sets both flags, so re-runs are safe. " .
            "Subtypes not listed are left unchanged.</p>";

        $raw = isset($_POST["payload"])
            ? (string) wp_unslash($_POST["payload"])
            : "";
        $action = $_POST["eic_action"] ?? "";

        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "dryrun") {
                self::process($raw, false);
            } elseif ($action === "commit") {
                if (empty($_POST["confirm"])) {
                    echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
                } else {
                    self::process($raw, true);
                }
            }
        }

        $ta = esc_textarea($raw);
        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p><textarea name="payload" rows="12" style="width:100%;font-family:monospace" ' .
            'placeholder="CMT1A: LoF&#10;CMT2A: GoF&#10;CMT4B1: LoF, GoF&#10;CMTX1: none">' .
            $ta .
            "</textarea></p>";
        echo '<input type="hidden" name="eic_action" value="dryrun">';
        echo '<button class="button button-primary">Dry run (no writes)</button></form>';

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="payload" value="' . esc_attr($raw) . '">';
        echo '<input type="hidden" name="eic_action" value="commit">';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write these flags.</label></p>';
        echo '<button class="button button-primary">Commit</button></form>';

        eic_admin_tool_close();
    }

    private static function process(string $raw, bool $commit): void
    {
        list($entries, $errors) = self::parse($raw);
        foreach ($errors as $e) {
            echo '<div class="notice notice-warning"><p>' . $e . "</p></div>";
        }
        if (empty($entries)) {
            echo '<div class="notice notice-error"><p>Nothing to process.</p></div>';
            return;
        }

        $map = self::code_map();
        $rows = [];
        $writes = 0;
        $unmatched = [];
        $ambiguous = [];

        foreach ($entries as $acr => $flags) {
            if (!isset($map[$acr])) {
                $unmatched[] = $acr;
                continue;
            }
            if (count($map[$acr]) > 1) {
                $ambiguous[] = $acr;
                continue;
            }

            $id = $map[$acr][0];
            $cur_lof = (bool) get_field("lof_variant", $id);
            $cur_gof = (bool) get_field("gof_variant", $id);
            $new_lof = $flags["lof"];
            $new_gof = $flags["gof"];
            $changed = $cur_lof !== $new_lof || $cur_gof !== $new_gof;

            if ($changed && $commit) {
                update_field("lof_variant", $new_lof ? 1 : 0, $id);
                update_field("gof_variant", $new_gof ? 1 : 0, $id);
                $writes++;
            }

            $rows[] = [
                "acr" => $acr,
                "title" => get_the_title($id),
                "from" => self::label($cur_lof, $cur_gof),
                "to" => self::label($new_lof, $new_gof),
                "changed" => $changed,
            ];
        }

        $head = $commit ? "Backfill complete" : "Dry run";
        $changes = count(array_filter($rows, fn($r) => $r["changed"]));
        echo "<h2>" . esc_html($head) . "</h2>";
        echo '<div class="notice notice-' .
            ($commit ? "success" : "info") .
            '"><p>' .
            ($commit
                ? "Wrote {$writes} subtype(s). "
                : "{$changes} subtype(s) would change. ") .
            count($rows) .
            " matched, " .
            count($unmatched) .
            " unmatched, " .
            count($ambiguous) .
            " ambiguous.</p></div>";

        if ($unmatched) {
            echo '<div class="notice notice-warning"><p><strong>No subtype matched these codes:</strong> ' .
                esc_html(implode(", ", $unmatched)) .
                "</p></div>";
        }
        if ($ambiguous) {
            echo '<div class="notice notice-warning"><p><strong>Code matched more than one subtype (skipped):</strong> ' .
                esc_html(implode(", ", $ambiguous)) .
                "</p></div>";
        }

        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Code</th><th>Subtype</th><th>From</th><th>To</th><th>Change</th>" .
            "</tr></thead><tbody>";
        foreach ($rows as $r) {
            echo "<tr><td><strong>" .
                esc_html($r["acr"]) .
                "</strong></td><td>" .
                esc_html($r["title"]) .
                "</td><td>" .
                esc_html($r["from"]) .
                "</td><td>" .
                esc_html($r["to"]) .
                "</td><td>" .
                ($r["changed"] ? "yes" : "no") .
                "</td></tr>";
        }
        echo "</tbody></table>";
    }

    private static function label(bool $lof, bool $gof): string
    {
        $parts = [];
        if ($lof) {
            $parts[] = "LoF";
        }
        if ($gof) {
            $parts[] = "GoF";
        }
        return $parts ? implode(" + ", $parts) : "none";
    }
}

EIC_LoF_GoF_Tool::init();
