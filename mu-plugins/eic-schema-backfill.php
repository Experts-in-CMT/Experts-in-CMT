<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC Schema Markup Backfill
 * ------------------------------------------------------------
 * Tools > Schema Backfill.
 *
 * Sets the standard Schema Markup defaults on subtype records:
 *   medical_specialty  = Genetic, Neurologic
 *   medical_audience   = Patient, Clinician, Medical Researcher
 *   reviewed_by_name   = Experts in CMT
 *   reviewed_by_type   = Organization
 *
 * Scope "blank" fills only empty fields (never clobbers curation);
 * scope "all" overwrites. Dry-run gated. Uses the real field_subtype_*
 * ACF keys.
 *
 * Location: wp-content/mu-plugins/eic-schema-backfill.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Schema_Backfill
{
    const CAP   = "manage_options";
    const NONCE = "eic_schema_backfill";

    // name => [field_key, default_value, is_checkbox]
    private static function fields(): array
    {
        return [
            "medical_specialty" => ["field_subtype_specialty", ["Genetic", "Neurologic"], true],
            "medical_audience"  => ["field_subtype_medical_audience", ["Patient", "Clinician", "MedicalResearcher"], true],
            "reviewed_by_name"  => ["field_subtype_reviewed_by_name", "Experts in CMT", false],
            "reviewed_by_type"  => ["field_subtype_reviewed_by_type", "Organization", false],
        ];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page("Schema Backfill", "Schema Backfill", self::CAP,
            "eic-schema-backfill", [__CLASS__, "render"]);
    }

    private static function is_empty($v): bool
    {
        if (is_array($v)) {
            return count(array_filter($v)) === 0;
        }
        return trim((string) $v) === "";
    }

    private static function fmt($v): string
    {
        return is_array($v) ? implode(", ", $v) : (string) $v;
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        echo '<div class="wrap"><h1>Schema Backfill</h1>';
        echo "<p>Sets the standard Schema Markup defaults on subtypes " .
            "(specialty, audience, reviewer, reviewer type).</p>";

        $action = $_POST["eic_action"] ?? "";
        $scope = $_POST["scope"] ?? "blank";
        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "dryrun") {
                self::process(false, $scope);
            } elseif ($action === "commit") {
                if (empty($_POST["confirm"])) {
                    echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
                } else {
                    self::process(true, $scope);
                }
            }
        }

        $scope_sel = '<p>Scope: <select name="scope">' .
            '<option value="blank">Only fields that are blank</option>' .
            '<option value="all">Overwrite all</option></select></p>';

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="dryrun">' . $scope_sel;
        echo '<button class="button button-primary">Run dry run (no writes)</button></form>';

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="commit">' . $scope_sel;
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write.</label></p>';
        echo '<button class="button button-primary">Commit</button></form>';
        echo "</div>";
    }

    private static function process(bool $commit, string $scope): void
    {
        $fields = self::fields();
        $q = new WP_Query([
            "post_type" => "subtype", "post_status" => "any",
            "posts_per_page" => -1, "fields" => "ids", "no_found_rows" => true,
            "orderby" => "title", "order" => "ASC",
        ]);
        $writes = 0;
        $rows = [];
        foreach ($q->posts as $id) {
            $cells = [];
            $changed = false;
            foreach ($fields as $name => $cfg) {
                list($key, $default, $is_cb) = $cfg;
                $cur = get_field($key, $id);
                $will = ($scope === "all") || self::is_empty($cur);
                if ($will) {
                    $changed = true;
                    if ($commit) {
                        update_field($key, $default, $id);
                        $writes++;
                    }
                }
                $cells[$name] = self::fmt($cur) === "" ? "(blank)" : self::fmt($cur);
            }
            if ($changed) {
                $rows[] = [get_the_title($id), $cells];
            }
        }

        $head = $commit ? "Backfill complete" : "Dry run";
        echo "<h2>" . esc_html($head) . "</h2>";
        echo '<div class="notice notice-' . ($commit ? "success" : "info") . '"><p>' .
            ($commit ? "Wrote {$writes} field value(s) across " : "Would change ") .
            count($rows) . " subtype(s) (scope: " . esc_html($scope) . ").</p></div>";
        echo '<table class="widefat striped"><thead><tr><th>Subtype</th><th>Specialty (current)</th><th>Audience (current)</th><th>Reviewer (current)</th><th>Type (current)</th></tr></thead><tbody>';
        foreach (array_slice($rows, 0, 200) as $r) {
            echo "<tr><td><strong>" . esc_html($r[0]) . "</strong></td><td>" .
                esc_html($r[1]["medical_specialty"]) . "</td><td>" .
                esc_html($r[1]["medical_audience"]) . "</td><td>" .
                esc_html($r[1]["reviewed_by_name"]) . "</td><td>" .
                esc_html($r[1]["reviewed_by_type"]) . "</td></tr>";
        }
        echo "</tbody></table>";
    }
}

EIC_Schema_Backfill::init();
