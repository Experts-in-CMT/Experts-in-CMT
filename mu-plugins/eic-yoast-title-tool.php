<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC Yoast Title Space Fix
 * ------------------------------------------------------------
 * Tools > Yoast Title Fix.
 *
 * Cleans a stray space before the question mark in the Yoast SEO
 * title template stored per record. The templates read, e.g.:
 *
 *     What Is %%title%% ? | Charcot-Marie-Tooth Disease | %%sitename%%
 *                      ^ unwanted space
 *
 * and should read "%%title%%?". The fix collapses any run of
 * whitespace (including non-breaking spaces) immediately before a
 * "?" in the stored `_yoast_wpseo_title` meta. The %%title%% and
 * other Yoast variables are left untouched, so this is safe to run
 * against the template rather than a rendered string.
 *
 * Only records whose stored title actually changes are written;
 * records using Yoast's default (empty meta) are skipped. Dry-run
 * gated, with a before/after preview, per the other EIC tools.
 *
 * Location: wp-content/mu-plugins/eic-yoast-title-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Yoast_Title_Tool
{
    const CAP      = "manage_options";
    const NONCE    = "eic_yoast_title_tool";
    const META_KEY = "_yoast_wpseo_title";
    private static $hook = "";

    private static function post_types(): array
    {
        return [
            "subtype"     => "Subtype",
            "breathing"   => "CMT and Breathing",
            "what-is-cmt" => "What is CMT",
            "glossary"    => "Glossary",
            "page"        => "Page",
            "post"        => "Post",
        ];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        self::$hook = add_management_page(
            "Yoast Title Fix",
            "Yoast Title Fix",
            self::CAP,
            "eic-yoast-title",
            [__CLASS__, "render"]
        );
    }

    /**
     * Collapse any whitespace (incl. non-breaking) immediately before a
     * "?" down to nothing. Leaves Yoast variables like %%title%% intact.
     */
    private static function fix(string $s): string
    {
        $out = preg_replace('/[\s\x{00A0}]+\?/u', "?", $s);
        // preg_replace returns null on failure; never hand back null.
        return is_string($out) ? $out : $s;
    }

    /**
     * Every post of $type that carries a stored Yoast title, with the
     * current value and its fixed form.
     */
    private static function collect(string $type): array
    {
        $q = new WP_Query([
            "post_type"      => $type,
            "post_status"    => "any",
            "posts_per_page" => -1,
            "fields"         => "ids",
            "no_found_rows"  => true,
            "orderby"        => "title",
            "order"          => "ASC",
        ]);

        $rows = [];
        foreach ($q->posts as $id) {
            $cur = (string) get_post_meta($id, self::META_KEY, true);
            if ($cur === "") {
                continue; // using Yoast's default template; nothing stored to fix
            }
            $fixed = self::fix($cur);
            $rows[] = [
                "id"      => $id,
                "title"   => get_the_title($id),
                "cur"     => $cur,
                "fixed"   => $fixed,
                "changed" => $fixed !== $cur,
            ];
        }
        return $rows;
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        $types  = self::post_types();
        $type   = isset($_POST["eic_post_type"]) ? sanitize_key($_POST["eic_post_type"]) : "subtype";
        if (!isset($types[$type])) {
            $type = "subtype";
        }
        $action = $_POST["eic_action"] ?? "";

        eic_admin_tool_open("Yoast Title Fix", "Remove the stray space before the ? in SEO title templates.");
        echo "<p>Some SEO title templates read <code>What Is %%title%% ? | &hellip;</code> with an unwanted " .
            "space before the question mark. This collapses that space so it reads <code>%%title%%?</code>. " .
            "Yoast variables are left untouched, and only records whose stored title actually changes are written.</p>";

        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "dryrun") {
                self::do_dryrun($type);
            } elseif ($action === "commit") {
                self::do_commit($type);
            }
        }

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p><label>Post type: <select name="eic_post_type">';
        foreach ($types as $k => $label) {
            echo '<option value="' . esc_attr($k) . '"' . selected($type, $k, false) . ">" . esc_html($label) . "</option>";
        }
        echo "</select></label></p>";
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write.</label></p>';
        echo '<button class="button button-primary" name="eic_action" value="commit">Commit</button>';
        echo "</form>";
        eic_admin_tool_close();
    }

    private static function do_dryrun(string $type): void
    {
        $rows   = self::collect($type);
        $label  = self::post_types()[$type];
        $change = 0;

        echo "<h2>Dry run &mdash; " . count($rows) . " " . esc_html($label) . " record(s) with a stored SEO title</h2>";
        echo '<table class="widefat striped"><thead><tr><th>Record</th><th>Current title template</th><th>After fix</th><th>Change?</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            if ($r["changed"]) {
                $change++;
            }
            $hi  = $r["changed"] ? ' style="background:#fff3cd"' : "";
            $aft = $r["changed"] ? "<code>" . esc_html($r["fixed"]) . "</code>" : "<span style=\"color:#666\">(no change)</span>";
            echo "<tr{$hi}><td><strong>" . esc_html($r["title"]) . "</strong></td>" .
                "<td><code>" . esc_html($r["cur"]) . "</code></td>" .
                "<td>" . $aft . "</td>" .
                "<td>" . ($r["changed"] ? "fix" : "ok") . "</td></tr>";
        }
        echo "</tbody></table>";
        echo "<p><strong>" . $change . "</strong> record(s) would change.</p>";
    }

    private static function do_commit(string $type): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $rows = self::collect($type);
        $set  = 0;
        foreach ($rows as $r) {
            if ($r["changed"]) {
                update_post_meta($r["id"], self::META_KEY, $r["fixed"]);
                $set++;
            }
        }
        echo '<div class="notice notice-success"><p><strong>Done.</strong> Fixed the SEO title on ' .
            $set . " " . esc_html(self::post_types()[$type]) . " record(s).</p></div>";
    }
}

EIC_Yoast_Title_Tool::init();
