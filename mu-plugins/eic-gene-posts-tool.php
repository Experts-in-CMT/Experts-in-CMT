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
 * MU Plugin: EIC Gene Posts
 * ------------------------------------------------------------
 * Creates the shell `gene` post for every distinct gene_symbol on a
 * published subtype record that does not already have one. A gene
 * post holds nothing (title = symbol, slug = symbol lowercased), so
 * this tool only ever inserts; it never updates or deletes.
 *
 * Skips unknown-gene records. The structural record (CMTX3), whose
 * gene_symbol is ISCN notation, gets a post titled and slugged by its
 * subtype code. Candidate genes carry real symbols and get posts like
 * any other gene.
 *
 * The one thing it writes to an existing post is the header banner:
 * `banner_title` (the page H1, as on every other post type) and
 * `banner_intro`, fill-if-empty only, matching the importers.
 *
 * Non-destructive and re-runnable: dry run lists what would be
 * created, commit creates it and flushes rewrite rules once so
 * /genetics/gene/{symbol}/ resolves immediately. The acf/save_post
 * hook in the theme (inc/cpt/gene-cpt.php) covers new genes going
 * forward; this page is the bulk pass and the audit view.
 *
 * Location: wp-content/mu-plugins/eic-gene-posts-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Gene_Posts_Tool
{
    const CAP = "manage_options";
    const NONCE = "eic_gene_posts_tool";

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Gene Posts",
            "Gene Posts",
            self::CAP,
            "eic-gene-posts",
            [__CLASS__, "render"]
        );
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        eic_admin_tool_open("Gene Posts");

        if (!function_exists("eic_gene_ensure_post") || !post_type_exists("gene")) {
            echo '<div class="notice notice-error"><p>The <code>gene</code> post type is not registered. ' .
                "Confirm <code>inc/cpt/gene-cpt.php</code> is loaded by the theme, then reload.</p></div>";
            eic_admin_tool_close();
            return;
        }

        echo "<p>Creates one shell <code>gene</code> post per distinct <code>gene_symbol</code> on the " .
            "published subtype records, at <code>/genetics/gene/{symbol}/</code>. A gene post holds no " .
            "fields; its page is projected from the subtype store at render time. Insert-only and " .
            "re-runnable: existing posts are left alone, except that an empty header banner title or intro " .
            "is filled (title = symbol, intro = full gene name), never overwritten. Unknown-gene records " .
            "are skipped; the structural record (CMTX3) gets a post titled by its subtype code.</p>";

        $action = $_POST["eic_action"] ?? "";
        if ($action && check_admin_referer(self::NONCE)) {
            self::run($action === "commit");
        }

        echo '<hr><form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to create the missing gene posts.</label></p>';
        echo '<p><button class="button button-primary eic-danger" name="eic_action" value="commit">Commit</button></p>';
        echo "</form>";
        eic_admin_tool_close();
    }

    private static function run(bool $commit): void
    {
        if ($commit && empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }

        $q = new WP_Query([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "orderby" => "title",
            "order" => "ASC",
        ]);

        // symbol => [record labels]
        $symbols = [];
        $skipped = []; // label => reason
        foreach ($q->posts as $post) {
            $id = (int) $post->ID;
            $label = trim((string) get_field("subtype", $id));
            $label = $label !== "" ? $label : $post->post_title;
            if (get_field("unknown_gene", $id)) {
                $skipped[$label] = "unknown gene";
                continue;
            }
            $sym = trim((string) get_field("gene_symbol", $id));
            if ($sym === "" || strtoupper($sym) === "UNKNOWN") {
                $skipped[$label] = "empty gene_symbol";
                continue;
            }
            $key = strtoupper($sym);
            $symbols[$key][] = $label;
        }
        ksort($symbols);

        $exists = 0;
        $created = 0;
        $failed = 0;

        echo "<h2>" . ($commit ? "Commit" : "Dry run") .
            ": " . count($q->posts) . " published subtype records, " .
            count($symbols) . " distinct gene symbols</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Gene</th><th>Subtype records</th><th>Gene post</th><th>Status</th></tr></thead><tbody>";

        foreach ($symbols as $sym => $labels) {
            $r = eic_gene_ensure_post($sym, $commit);
            $style = "";
            $link = "";
            switch ($r["status"]) {
                case "exists":
                    $exists++;
                    $banner = function_exists("eic_gene_fill_banner")
                        ? eic_gene_fill_banner((int) $r["id"], $sym, $commit)
                        : "";
                    $status = "exists (" . $r["reason"] . ")" . ($banner !== "" ? ", " . $banner : "");
                    if ($banner !== "") {
                        $style = ' style="background:#fff3cd"';
                    }
                    $link = self::post_link((int) $r["id"]);
                    break;
                case "created":
                    $created++;
                    $status = "created" . ($r["reason"] !== "" ? ", " . $r["reason"] : "");
                    $style = ' style="background:#d4edda"';
                    $link = self::post_link((int) $r["id"]);
                    break;
                case "would_create":
                    $created++;
                    $status = "would create";
                    $style = ' style="background:#fff3cd"';
                    break;
                default:
                    $failed++;
                    $status = "failed: " . $r["reason"];
                    $style = ' style="background:#f8d7da"';
            }
            echo "<tr" . $style . "><td><strong>" . esc_html($sym) . "</strong></td><td>" .
                esc_html(implode(", ", $labels)) . "</td><td>" . $link . "</td><td>" .
                esc_html($status) . "</td></tr>";
        }
        echo "</tbody></table>";

        echo "<p><strong>" . count($symbols) . "</strong> genes: " .
            (int) $exists . " already have a post, " .
            (int) $created . ($commit ? " created" : " would be created") .
            ($failed ? ", " . (int) $failed . " failed" : "") . ".</p>";

        if ($skipped) {
            ksort($skipped);
            echo "<details><summary>" . count($skipped) . " subtype records skipped (context)</summary><ul>";
            foreach ($skipped as $label => $reason) {
                echo "<li><code>" . esc_html($label) . "</code>: " . esc_html($reason) . "</li>";
            }
            echo "</ul></details>";
        }

        if ($commit && $created > 0) {
            flush_rewrite_rules(false);
            echo '<div class="notice notice-success"><p>Rewrite rules flushed so <code>/genetics/gene/{symbol}/</code> resolves.</p></div>';
        }
    }

    private static function post_link(int $id): string
    {
        if ($id <= 0) {
            return "";
        }
        $view = get_permalink($id);
        $edit = get_edit_post_link($id, "raw");
        $out = "";
        if ($view) {
            $out .= '<a href="' . esc_url($view) . '" target="_blank" rel="noopener">view</a>';
        }
        if ($edit) {
            $out .= ($out ? " · " : "") . '<a href="' . esc_url($edit) . '">edit</a>';
        }
        return $out;
    }
}

EIC_Gene_Posts_Tool::init();
