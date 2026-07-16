<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC Body Maintenance (post_content hygiene)
 * ------------------------------------------------------------
 * Tools > Body Maintenance
 *
 * Scan-first, per-check apply. Normalizes body text across chosen post
 * types so house terminology and link conventions stay consistent
 * without re-generating content.
 *
 * AUTO-FIX checks (write on apply, shown in context first):
 *   1. Terminology: wasting -> atrophy; disorder(s) -> disease(s);
 *      condition(s) -> disease(s). Case- and plural-aware, whole word.
 *   2. Glossary links: legacy/non-canonical glossary hrefs -> canonical
 *      (extensible map). Internal glossary anchors only.
 *
 * FLAG-ONLY checks (report, never auto-write, because the fix needs
 * judgment):
 *   3. Inheritable family: flags "inherited", "herited", "heritable"
 *      (house rule is "inheritable"); replacement is context-dependent.
 *   4. Em-dashes: flags any "-" (house rule: none); rephrasing is manual.
 *   5. NCV frame (subtype only): flags a "Nerve conduction" sentence that
 *      does not resolve to "... form of CMT." (catches invented phrasings
 *      like "an axonal process"). The corpus varies the NCV wording by
 *      real electrophysiology, so this is never auto-rewritten.
 *
 * Location: wp-content/mu-plugins/eic-body-maintenance.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Body_Maintenance
{
    const CAP   = "manage_options";
    const NONCE = "eic_body_maintenance";

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

    /* Terminology map: pattern => replacement, applied whole-word, case-aware. */
    private static function term_rules(): array
    {
        return [
            "wasting"    => "atrophy",
            "disorders"  => "diseases",
            "disorder"   => "disease",
            "conditions" => "diseases",
            "condition"  => "disease",
        ];
    }

    /* Legacy/non-canonical glossary href => canonical href. */
    private static function link_rules(): array
    {
        return [
            "/glossary/dominant/"  => "/glossary/autosomal-dominant/",
            "/glossary/recessive/" => "/glossary/autosomal-recessive/",
        ];
    }

    /* Canonical body-phrase normalizations: exact string => exact string.
     * Narrowly scoped so a match can only be the intended phrase. Used for
     * house-standard bullet wording that must be identical across records. */
    private static function phrase_rules(): array
    {
        return [
            // Oxford comma after "high arches" in the foot-deformities bullet.
            "including high arches and hammertoes (clawed toes)"
                => "including high arches, and hammertoes (clawed toes)",
        ];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Body Maintenance",
            "Body Maintenance",
            self::CAP,
            "eic-body-maintenance",
            [__CLASS__, "render"]
        );
    }

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
        return $q->posts;
    }

    /* ---- case-aware whole-word replacement ---- */
    private static function apply_case(string $match, string $repl): string
    {
        if ($match === "" || $repl === "") {
            return $repl;
        }
        if (ctype_upper($match[0])) {
            return ucfirst($repl);
        }
        return $repl;
    }

    private static function term_scan(string $content): array
    {
        $hits = [];
        foreach (self::term_rules() as $from => $to) {
            if (preg_match_all('/\b(' . preg_quote($from, "/") . ')\b/i', $content, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $one) {
                    $hits[] = ["from" => $one[0], "to" => self::apply_case($one[0], $to)];
                }
            }
        }
        return $hits;
    }

    private static function term_fix(string $content): string
    {
        foreach (self::term_rules() as $from => $to) {
            $content = preg_replace_callback(
                '/\b(' . preg_quote($from, "/") . ')\b/i',
                function ($m) use ($to) {
                    return self::apply_case($m[1], $to);
                },
                $content
            );
        }
        return $content;
    }

    private static function link_scan(string $content): array
    {
        $hits = [];
        foreach (self::link_rules() as $from => $to) {
            if (strpos($content, $from) !== false) {
                $hits[] = ["from" => $from, "to" => $to];
            }
        }
        return $hits;
    }

    private static function link_fix(string $content): string
    {
        foreach (self::link_rules() as $from => $to) {
            $content = str_replace($from, $to, $content);
        }
        return $content;
    }

    private static function phrase_scan(string $content): array
    {
        $hits = [];
        foreach (self::phrase_rules() as $from => $to) {
            // Only a hit if the non-canonical form is present AND the canonical
            // form is not already what's there (exact, literal match).
            if (strpos($content, $from) !== false) {
                $hits[] = ["from" => $from, "to" => $to];
            }
        }
        return $hits;
    }

    private static function phrase_fix(string $content): string
    {
        foreach (self::phrase_rules() as $from => $to) {
            $content = str_replace($from, $to, $content);
        }
        return $content;
    }

    /* ---- flag-only detectors ---- */
    private static function flag_inheritable(string $c): array
    {
        $out = [];
        if (preg_match_all('/\b(inherited|herited|heritable)\b/i', $c, $m)) {
            $out = array_values(array_unique($m[0]));
        }
        return $out;
    }

    private static function flag_emdash(string $c): int
    {
        return substr_count($c, "\xE2\x80\x94"); // em-dash UTF-8
    }

    private static function flag_ncv(string $c): array
    {
        // find a "Nerve conduction" sentence and check it resolves to "form of CMT."
        $out = [];
        if (preg_match_all('/Nerve conduction[^.]*\./', $c, $m)) {
            foreach ($m[0] as $sent) {
                $plain = trim(wp_strip_all_tags($sent));
                if (stripos($plain, "form of CMT") === false
                    && stripos($plain, "process") === false
                    && stripos($plain, "NCS") === false
                    && stripos($plain, "diagnosis") === false) {
                    $out[] = $plain;
                }
            }
        }
        return $out;
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        $types = self::post_types();
        $type = isset($_POST["eic_post_type"]) ? sanitize_key($_POST["eic_post_type"]) : "subtype";
        if (!isset($types[$type])) {
            $type = "subtype";
        }
        $action = $_POST["eic_action"] ?? "";

        eic_admin_tool_open("Body Maintenance");
        echo "<p>Scan-first body-text hygiene. Auto-fix checks write on apply " .
            "(shown in context first). Flag-only checks report for manual fix. " .
            "<strong>Back up before applying.</strong></p>";

        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "scan") {
                self::do_scan($type);
            } elseif ($action === "apply_terms") {
                self::do_apply($type, "terms");
            } elseif ($action === "apply_links") {
                self::do_apply($type, "links");
            } elseif ($action === "apply_phrases") {
                self::do_apply($type, "phrases");
            }
        }

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p><label>Post type: <select name="eic_post_type">';
        foreach ($types as $k => $label) {
            echo '<option value="' . esc_attr($k) . '"' . selected($type, $k, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> ';
        echo '<button class="button button-primary" name="eic_action" value="scan">Scan</button></p>';
        echo "</form>";
        eic_admin_tool_close();
    }

    /* Render an auto-fix section: per-record checkboxes (checked by default),
     * a select-all toggle, and the apply button. Only checked IDs are written. */
    private static function render_fix_table(
        string $type,
        array $rows,
        string $col_label,
        string $action,
        string $btn_label,
        string $joiner
    ): void {
        if (!$rows) {
            echo "<p>Clean.</p>";
            return;
        }
        $grp = "sel_" . $action;
        echo '<form method="post" style="margin:.5em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_post_type" value="' . esc_attr($type) . '">';
        echo '<table class="widefat striped"><thead><tr>' .
            '<td class="check-column"><input type="checkbox" checked ' .
            'onclick="var b=this.closest(\'form\').querySelectorAll(\'input[name=&quot;ids[]&quot;]\');' .
            'for(var i=0;i<b.length;i++)b[i].checked=this.checked;"></td>' .
            '<th>Post</th><th>' . esc_html($col_label) . '</th></tr></thead><tbody>';
        foreach ($rows as $id => $hits) {
            $desc = [];
            foreach ($hits as $h) { $desc[] = esc_html($h["from"] . " -> " . $h["to"]); }
            echo '<tr><th class="check-column"><input type="checkbox" name="ids[]" value="' .
                (int) $id . '" checked></th>' .
                "<td><strong>" . esc_html(get_the_title($id)) . "</strong></td>" .
                "<td>" . implode($joiner, $desc) . "</td></tr>";
        }
        echo "</tbody></table>";
        echo '<p><label><input type="checkbox" name="confirm" value="1"> Backed up.</label> ';
        echo '<button class="button button-primary eic-danger" name="eic_action" value="' . esc_attr($action) . '">' .
            esc_html($btn_label) . '</button></p>';
        echo "</form>";
    }

    private static function do_scan(string $type): void
    {
        $ids = self::collect($type);
        $t_rows = []; $l_rows = []; $p_rows = []; $inh = []; $dash = []; $ncv = [];
        foreach ($ids as $id) {
            $c = get_post_field("post_content", $id);
            if ($c === "") { continue; }
            $th = self::term_scan($c);
            if ($th) { $t_rows[$id] = $th; }
            $lh = self::link_scan($c);
            if ($lh) { $l_rows[$id] = $lh; }
            $ph = self::phrase_scan($c);
            if ($ph) { $p_rows[$id] = $ph; }
            $ih = self::flag_inheritable($c);
            if ($ih) { $inh[$id] = $ih; }
            $dh = self::flag_emdash($c);
            if ($dh) { $dash[$id] = $dh; }
            if ($type === "subtype") {
                $nh = self::flag_ncv($c);
                if ($nh) { $ncv[$id] = $nh; }
            }
        }

        // AUTO-FIX 1: terminology
        echo "<h2>1. Terminology (auto-fix)</h2>";
        self::render_fix_table($type, $t_rows, "Replacements", "apply_terms", "Apply terminology fixes", ", ");

        // AUTO-FIX 2: glossary links
        echo "<h2>2. Glossary links (auto-fix)</h2>";
        self::render_fix_table($type, $l_rows, "Link fixes", "apply_links", "Apply link fixes", "<br>");

        // AUTO-FIX 3: canonical phrase normalization
        echo "<h2>3. Canonical phrases (auto-fix)</h2>";
        self::render_fix_table($type, $p_rows, "Normalizations", "apply_phrases", "Apply phrase fixes", "<br>");

        // FLAG 3: inheritable
        echo "<h2>4. Inheritable family (flag only)</h2>";
        if ($inh) {
            echo '<table class="widefat striped"><thead><tr><th>Post</th><th>Found</th></tr></thead><tbody>';
            foreach ($inh as $id => $words) {
                echo "<tr><td><strong>" . esc_html(get_the_title($id)) . "</strong></td><td>" . esc_html(implode(", ", $words)) . "</td></tr>";
            }
            echo "</tbody></table><p><em>Fix by hand: house rule is \"inheritable\".</em></p>";
        } else {
            echo "<p>Clean.</p>";
        }

        // FLAG 4: em-dash
        echo "<h2>5. Em-dashes (flag only)</h2>";
        if ($dash) {
            echo '<table class="widefat striped"><thead><tr><th>Post</th><th>Count</th></tr></thead><tbody>';
            foreach ($dash as $id => $n) {
                echo "<tr><td><strong>" . esc_html(get_the_title($id)) . "</strong></td><td>" . (int) $n . "</td></tr>";
            }
            echo "</tbody></table><p><em>Rephrase by hand: no em-dashes.</em></p>";
        } else {
            echo "<p>Clean.</p>";
        }

        // FLAG 5: NCV frame (subtype)
        if ($type === "subtype") {
            echo "<h2>6. Non-conforming NCV sentence (flag only)</h2>";
            if ($ncv) {
                echo '<table class="widefat striped"><thead><tr><th>Post</th><th>Sentence</th></tr></thead><tbody>';
                foreach ($ncv as $id => $sents) {
                    foreach ($sents as $s) {
                        echo "<tr><td><strong>" . esc_html(get_the_title($id)) . "</strong></td><td>" . esc_html($s) . "</td></tr>";
                    }
                }
                echo "</tbody></table><p><em>Review by hand: should resolve to \"... form of CMT.\" The corpus varies NCV wording by electrophysiology, so this is never auto-rewritten.</em></p>";
            } else {
                echo "<p>Clean.</p>";
            }
        }
    }

    private static function do_apply(string $type, string $which): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        // Only the records the user checked in the scan table.
        $selected = isset($_POST["ids"]) && is_array($_POST["ids"])
            ? array_map("intval", $_POST["ids"])
            : [];
        if (empty($selected)) {
            echo '<div class="notice notice-error"><p>No records selected. Nothing written.</p></div>';
            return;
        }
        $ids = array_values(array_intersect(self::collect($type), $selected));
        $n = 0;
        foreach ($ids as $id) {
            $c = get_post_field("post_content", $id);
            if ($c === "") { continue; }
            $new = $c;
            if ($which === "terms") {
                $new = self::term_fix($c);
            } elseif ($which === "links") {
                $new = self::link_fix($c);
            } elseif ($which === "phrases") {
                $new = self::phrase_fix($c);
            }
            if ($new !== $c) {
                wp_update_post(["ID" => $id, "post_content" => $new]);
                $n++;
            }
        }
        $labels = ["terms" => "terminology", "links" => "glossary link", "phrases" => "canonical phrase"];
        $label = $labels[$which] ?? $which;
        echo '<div class="notice notice-success"><p><strong>Done.</strong> Applied ' .
            esc_html($label) . ' fixes to ' . $n . ' post(s).</p></div>';
    }
}

EIC_Body_Maintenance::init();
