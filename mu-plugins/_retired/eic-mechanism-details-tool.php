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
 * MU Plugin: EIC Variant Mechanism Details Importer
 * ------------------------------------------------------------
 * Tools > Mechanism Details.
 *
 * Sets the three curated mechanism fields on subtype records:
 *   mechanism_confidence  (high | medium | low)
 *   mechanism_rationale   (one or two sentences)
 *   mechanism_source      (OMIM #, GeneReviews, PMID/PMC)
 *
 * These feed the Variant Mechanism explorer. The LoF/GoF call
 * itself is NOT set here (that is the LoF / GoF Mechanism tool);
 * this tool only carries the supporting confidence, rationale,
 * and source.
 *
 * Input is a JSON object keyed by subtype code (the record's
 * `subtype` field / post title, e.g. CMT1A), case-insensitive:
 *
 *   {
 *     "CMT1A": {"confidence":"high","rationale":"...","source":"OMIM #118220"},
 *     "CMT2A": {"confidence":"high","rationale":"...","source":"..."}
 *   }
 *
 * Only the keys present in a record are written, so partial
 * updates are safe and re-runs are idempotent. Dry-run then
 * confirm, capability- and nonce-gated, on the shared EIC shell.
 *
 * Location: wp-content/mu-plugins/eic-mechanism-details-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Mechanism_Details_Tool
{
    const CAP = "manage_options";
    const NONCE = "eic_mechanism_details";
    const FIELDS = ["confidence", "rationale", "source"];

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Mechanism Details",
            "Mechanism Details",
            self::CAP,
            "eic-mechanism-details",
            [__CLASS__, "render"]
        );
    }

    /**
     * Case-insensitive map of subtype code => [subtype IDs].
     * Indexes both the `subtype` field and the post title.
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
     * Parse the JSON object into [ CODE => ['confidence'=>?,'rationale'=>?,'source'=>?] ].
     * Returns [ $entries, $errors ]. Only recognized keys are kept.
     */
    private static function parse(string $raw): array
    {
        $raw = trim($raw);
        $entries = [];
        $errors = [];
        if ($raw === "") {
            return [$entries, ["No input provided."]];
        }
        if ($raw[0] !== "{") {
            return [
                $entries,
                ["Input must be a JSON object keyed by subtype code."],
            ];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [$entries, ["JSON did not parse."]];
        }

        foreach ($data as $key => $val) {
            $code = strtoupper(trim((string) $key));
            if ($code === "" || !is_array($val)) {
                continue;
            }
            $rec = [];
            if (isset($val["confidence"])) {
                $c = strtolower(trim((string) $val["confidence"]));
                if (!in_array($c, ["high", "medium", "low", ""], true)) {
                    $errors[] = sprintf(
                        "%s: invalid confidence '%s' (expected high/medium/low).",
                        esc_html($code),
                        esc_html($c)
                    );
                    $c = "";
                }
                $rec["confidence"] = $c;
            }
            if (isset($val["rationale"])) {
                $rec["rationale"] = trim((string) $val["rationale"]);
            }
            if (isset($val["source"])) {
                $rec["source"] = trim((string) $val["source"]);
            }
            if ($rec) {
                $entries[$code] = $rec;
            }
        }
        return [$entries, $errors];
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        eic_admin_tool_open(
            "Mechanism Details",
            "Load confidence, rationale, and source onto subtype records for the Variant Mechanism explorer, matched by subtype code."
        );

        echo "<p>Paste a JSON object keyed by subtype code (e.g. <code>CMT1A</code>). " .
            "Each value may include <code>confidence</code> (high/medium/low), " .
            "<code>rationale</code>, and <code>source</code>. Only the keys you include are written, " .
            "so re-runs are safe and subtypes not listed are left unchanged.</p>";

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

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p><textarea name="payload" rows="12" style="width:100%;font-family:monospace" ' .
            'placeholder=\'{ "CMT1A": {"confidence":"high","rationale":"...","source":"OMIM #118220"} }\'>' .
            esc_textarea($raw) .
            "</textarea></p>";
        echo '<input type="hidden" name="eic_action" value="dryrun">';
        echo '<button class="button button-primary">Dry run (no writes)</button></form>';

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="payload" value="' . esc_attr($raw) . '">';
        echo '<input type="hidden" name="eic_action" value="commit">';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write these fields.</label></p>';
        echo '<button class="button button-primary">Commit</button></form>';

        eic_admin_tool_close();
    }

    private static function acf_key(string $field): string
    {
        return "mechanism_" . $field;
    }

    private static function trunc(string $s, int $n = 60): string
    {
        $s = trim($s);
        return strlen($s) > $n ? substr($s, 0, $n - 3) . "..." : $s;
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

        foreach ($entries as $code => $rec) {
            if (!isset($map[$code])) {
                $unmatched[] = $code;
                continue;
            }
            if (count($map[$code]) > 1) {
                $ambiguous[] = $code;
                continue;
            }
            $id = $map[$code][0];
            $changed = false;
            $cells = [];
            foreach (self::FIELDS as $f) {
                $key = self::acf_key($f);
                $cur = trim((string) get_field($key, $id));
                if (array_key_exists($f, $rec)) {
                    $new = (string) $rec[$f];
                    if ($cur !== $new) {
                        $changed = true;
                        if ($commit) {
                            update_field($key, $new, $id);
                            $writes++;
                        }
                    }
                    $cells[$f] = $new;
                } else {
                    $cells[$f] = $cur;
                }
            }
            $rows[] = [
                "code" => $code,
                "title" => get_the_title($id),
                "cells" => $cells,
                "changed" => $changed,
            ];
        }

        $head = $commit ? "Import complete" : "Dry run";
        $changes = count(array_filter($rows, fn($r) => $r["changed"]));
        echo "<h2>" . esc_html($head) . "</h2>";
        echo '<div class="notice notice-' .
            ($commit ? "success" : "info") .
            '"><p>' .
            ($commit
                ? "Wrote {$writes} field value(s). "
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
            "<th>Code</th><th>Subtype</th><th>Confidence</th><th>Rationale</th><th>Source</th><th>Change</th>" .
            "</tr></thead><tbody>";
        foreach ($rows as $r) {
            echo "<tr><td><strong>" .
                esc_html($r["code"]) .
                "</strong></td><td>" .
                esc_html($r["title"]) .
                "</td><td>" .
                esc_html($r["cells"]["confidence"]) .
                "</td><td>" .
                esc_html(self::trunc($r["cells"]["rationale"])) .
                "</td><td>" .
                esc_html(self::trunc($r["cells"]["source"])) .
                "</td><td>" .
                ($r["changed"] ? "yes" : "no") .
                "</td></tr>";
        }
        echo "</tbody></table>";
    }
}

EIC_Mechanism_Details_Tool::init();
