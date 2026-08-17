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
 * MU Plugin: EIC Candidate Genes Importer
 * ------------------------------------------------------------
 * Creates candidate gene-association records from JSON. Each is a
 * published `subtype` post carrying `candidate_gene = true`, which
 * hides it from the subtype/genes loop and surfaces it in the Gene
 * Browser. Candidates are NOT subtypes, so they get a namespaced
 * slug (candidate-{gene}, or candidate-{former subtype} for a
 * downgrade) and a redirect off their single URL (handled by the
 * loop/redirect component, not here).
 *
 * JSON shape (paste or upload):
 *   { "candidates": [
 *       { "gene_symbol":"ADCY6", "candidate_gene":true, "since":"August 2021" },
 *       { "gene_symbol":"KARS1", "candidate_gene":true, "since":"August 2024",
 *         "former_subtype":"CMTRIB", "note":"Downgraded ... KOL guidance" }
 *   ] }
 *
 * Upsert by slug: re-running updates in place rather than duplicating.
 * After import, run Tools > HGNC Identifiers Backfill to pull each
 * candidate's identifiers (they are real genes).
 *
 * Location: wp-content/mu-plugins/eic-candidate-genes-importer.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Candidate_Genes_Importer
{
    const CAP = "manage_options";
    const NONCE = "eic_candidate_importer";

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Candidate Genes Importer",
            "Candidate Genes Importer",
            self::CAP,
            "eic-candidate-importer",
            [__CLASS__, "render"]
        );
    }

    /** Pull the record array from the accepted JSON shapes. */
    private static function records($json): array
    {
        if (isset($json["candidates"]) && is_array($json["candidates"])) {
            return $json["candidates"];
        }
        if (is_array($json) && isset($json[0])) {
            return $json;
        }
        return [];
    }

    /** Derive title/slug/values for one record. */
    private static function plan(array $r): array
    {
        $gene = trim((string) ($r["gene_symbol"] ?? ""));
        $former = trim((string) ($r["former_subtype"] ?? ""));
        $key = $former !== "" ? $former : $gene;
        return [
            "gene" => $gene,
            "former" => $former,
            "since" => trim((string) ($r["since"] ?? "")),
            "note" => trim((string) ($r["note"] ?? "")),
            "title" => $key,
            "slug" => "candidate-" . sanitize_title($key),
        ];
    }

    private static function validate(array $r): array
    {
        $e = [];
        if (trim((string) ($r["gene_symbol"] ?? "")) === "") {
            $e[] = "missing gene_symbol";
        }
        return $e;
    }

    /** Strict slug -> subtype post lookup (post_type=subtype only). */
    private static function find_by_slug(string $slug)
    {
        $q = new WP_Query([
            "post_type" => "subtype",
            "name" => $slug,
            "post_status" => "any",
            "posts_per_page" => 1,
            "no_found_rows" => true,
        ]);
        return $q->have_posts() ? $q->posts[0] : null;
    }

    /** 4-digit year from a candidate_since string ("August 2021" -> "2021"). */
    private static function candidate_year(string $since): string
    {
        return preg_match('/\b(\d{4})\b/', $since, $m) ? $m[1] : "";
    }

    /** Write an ACF text field only when the stored value differs. */
    private static function set_text_if_changed(string $key, string $name, string $value, int $id): void
    {
        if (trim((string) get_field($name, $id)) !== $value) {
            update_field($key, $value, $id);
        }
    }

    private static function write(int $id, array $p): void
    {
        update_field("field_candidate_gene", 1, $id);
        update_field("field_unknown_gene", 0, $id);
        // Text fields written only when they differ, so a re-run does not
        // needlessly rewrite unchanged ACF values (and bump the modified date).
        self::set_text_if_changed("field_gene_symbol", "gene_symbol", $p["gene"], $id);
        self::set_text_if_changed("field_candidate_since", "candidate_since", $p["since"], $id);
        self::set_text_if_changed("field_candidate_note", "candidate_note", $p["note"], $id);
        // Classification: candidates carry "Candidate" in the acronym field, which
        // the Gene Browser reads as the classification pill.
        self::set_text_if_changed("field_acronym", "acronym", "Candidate", $id);
        // Year of discovery echoes the year in candidate_since (e.g. 2021).
        $yr = self::candidate_year($p["since"]);
        if ($yr !== "") {
            self::set_text_if_changed("field_year_of_discovery", "year_of_discovery", $yr, $id);
        }
        // Downgrade: the former subtype code lives in the subtype field, which is
        // what the Gene Browser reads to render "{code} Downgraded to Candidate".
        self::set_text_if_changed("field_subtype", "subtype", $p["former"], $id);
        // Header banner (subtype page H1 + intro). Filled only when blank, so a
        // hand-added downgrade explainer is never overwritten on a re-run. H1 is
        // the former subtype code for a downgrade, else the gene; intro is italic.
        $banner_title = $p["former"] !== "" ? $p["former"] : $p["gene"];
        $banner_intro =
            "<em>Candidate Gene" . ($yr !== "" ? " | " . $yr : "") . "</em>";
        if (
            $banner_title !== "" &&
            trim((string) get_field("banner_title", $id)) === ""
        ) {
            update_field("field_6792eb48684ab", $banner_title, $id);
        }
        if (trim((string) get_field("banner_intro", $id)) === "") {
            update_field("field_6792eb80684ac", $banner_intro, $id);
        }
    }

    /** Sweep candidate records: set acronym="Candidate" + year_of_discovery from candidate_since. */
    private static function backfill(bool $commit): void
    {
        if ($commit && empty($_POST["confirm_bf"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $q = new WP_Query([
            "post_type" => "subtype",
            "post_status" => "any",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "meta_query" => [
                [
                    "key" => "candidate_gene",
                    "value" => "1",
                    "compare" => "=",
                ],
            ],
        ]);
        $rows = 0;
        $fields = 0;
        $fkeys = [
            "acronym" => "field_acronym",
            "year_of_discovery" => "field_year_of_discovery",
            "banner_title" => "field_6792eb48684ab",
            "banner_intro" => "field_6792eb80684ac",
        ];
        $ymeta = [
            "yoast_focuskw" => "_yoast_wpseo_focuskw",
            "yoast_title" => "_yoast_wpseo_title",
            "yoast_metadesc" => "_yoast_wpseo_metadesc",
        ];
        echo "<h2>" . ($commit ? "Backfill commit" : "Backfill dry run") .
            " — " . count($q->posts) . " candidate record(s)</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Gene</th><th>Candidate Since</th><th>Changes</th></tr></thead><tbody>";
        foreach ($q->posts as $post) {
            $id = (int) $post->ID;
            $gene = trim((string) get_field("gene_symbol", $id));
            $since = trim((string) get_field("candidate_since", $id));
            $cur_ac = trim((string) get_field("acronym", $id));
            $cur_yr = trim((string) get_field("year_of_discovery", $id));
            $yr = self::candidate_year($since);
            $diffs = [];
            if ($cur_ac !== "Candidate") {
                $diffs["acronym"] = [$cur_ac, "Candidate"];
            }
            if ($yr !== "" && $cur_yr !== $yr) {
                $diffs["year_of_discovery"] = [$cur_yr, $yr];
            }
            // Header banner, filled only when blank (protects hand edits).
            $former = trim((string) get_field("subtype", $id));
            $banner_title = $former !== "" ? $former : $gene;
            $banner_intro =
                "<em>Candidate Gene" . ($yr !== "" ? " | " . $yr : "") . "</em>";
            if (
                trim((string) get_field("banner_title", $id)) === "" &&
                $banner_title !== ""
            ) {
                $diffs["banner_title"] = ["", $banner_title];
            }
            if (trim((string) get_field("banner_intro", $id)) === "") {
                $diffs["banner_intro"] = ["", $banner_intro];
            }
            // Yoast SEO (post meta, filled when blank). Label = banner H1; the
            // meta description sentence-cases the full gene name.
            $label = $banner_title;
            $fullname = trim((string) get_field("full_gene_name", $id));
            $y_focuskw = $label . " - CMT Candidate Gene";
            $y_title = $label . " - CMT Candidate Gene | %%sitename%%";
            $y_metadesc =
                $fullname !== ""
                    ? ucfirst($fullname) . " is a candidate CMT disease gene."
                    : "";
            if (
                $label !== "" &&
                trim((string) get_post_meta($id, "_yoast_wpseo_focuskw", true)) === ""
            ) {
                $diffs["yoast_focuskw"] = ["", $y_focuskw];
            }
            if (
                $label !== "" &&
                trim((string) get_post_meta($id, "_yoast_wpseo_title", true)) === ""
            ) {
                $diffs["yoast_title"] = ["", $y_title];
            }
            if (
                $y_metadesc !== "" &&
                trim((string) get_post_meta($id, "_yoast_wpseo_metadesc", true)) === ""
            ) {
                $diffs["yoast_metadesc"] = ["", $y_metadesc];
            }
            if (empty($diffs)) {
                continue;
            }
            $rows++;
            $cells = [];
            $yoast_changed = false;
            foreach ($diffs as $k => $pair) {
                $fields++;
                if ($commit) {
                    if (isset($ymeta[$k])) {
                        update_post_meta($id, $ymeta[$k], $pair[1]);
                        $yoast_changed = true;
                    } else {
                        update_field($fkeys[$k], $pair[1], $id);
                    }
                }
                $cells[] = "<code>" . esc_html($k) . "</code>: " .
                    esc_html($pair[0] !== "" ? $pair[0] : "(empty)") .
                    " &rarr; <strong>" . esc_html($pair[1]) . "</strong>";
            }
            if ($commit && $yoast_changed) {
                self::reindex_yoast($id);
            }
            echo "<tr style=\"background:#fff3cd\"><td><strong>" .
                esc_html($gene !== "" ? $gene : $post->post_title) .
                "</strong></td><td>" . esc_html($since) . "</td><td>" .
                implode("<br>", $cells) . "</td></tr>";
        }
        echo "</tbody></table>";
        if ($commit) {
            echo '<div class="notice notice-success"><p><strong>Done.</strong> Wrote ' .
                (int) $fields . " field(s) across " . (int) $rows . " candidate(s).</p></div>";
        } else {
            echo "<p>Dry run only. " . (int) $fields . " field(s) across " .
                (int) $rows . " candidate(s) would be written.</p>";
        }
    }

    /**
     * Force Yoast to rebuild this post's indexable from the freshly written meta
     * (its front-end title/description read the indexable, not raw post meta),
     * while preserving the existing modified date so the backfill leaves the
     * visible "Updated" date untouched.
     */
    private static function reindex_yoast(int $id): void
    {
        $post = get_post($id);
        if (!$post) {
            return;
        }
        $keep = [$post->post_modified, $post->post_modified_gmt];
        $preserve = static function ($data) use ($keep) {
            $data["post_modified"] = $keep[0];
            $data["post_modified_gmt"] = $keep[1];
            return $data;
        };
        add_filter("wp_insert_post_data", $preserve, 99);
        wp_update_post(["ID" => $id]);
        remove_filter("wp_insert_post_data", $preserve, 99);
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        eic_admin_tool_open("Candidate Genes Importer");
        echo "<p>Paste the candidate JSON (<code>{\"candidates\":[...]}</code>), " .
            "dry-run, then commit. Each record is created as a published subtype " .
            "carrying <code>candidate_gene = true</code>: hidden from the subtype " .
            "loop, surfaced in the Gene Browser. Slug is <code>candidate-{gene}</code>, " .
            "or <code>candidate-{former subtype}</code> for a downgrade. Re-running " .
            "updates in place. Afterward, run <strong>HGNC Identifiers Backfill</strong> " .
            "to pull their identifiers.</p>";

        $raw = isset($_POST["json"]) ? (string) wp_unslash($_POST["json"]) : "";
        $action = $_POST["eic_action"] ?? "";

        if (
            in_array($action, ["backfill_dry", "backfill_commit"], true) &&
            check_admin_referer(self::NONCE)
        ) {
            self::backfill($action === "backfill_commit");
        } elseif ($action && check_admin_referer(self::NONCE)) {
            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<div class="notice notice-error"><p>Invalid JSON: ' .
                    esc_html(json_last_error_msg()) . "</p></div>";
            } else {
                $records = self::records($json);
                if (empty($records)) {
                    echo '<div class="notice notice-error"><p>No records found. Expected {"candidates":[...]}.</p></div>';
                } elseif ($action === "dryrun") {
                    self::run($records, false);
                } elseif ($action === "commit") {
                    self::run($records, true);
                }
            }
        }

        echo '<hr><form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><textarea name="json" rows="14" style="width:100%;font-family:monospace" ' .
            'placeholder="Paste candidate JSON here">' . esc_textarea($raw) . "</textarea></p>";
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write.</label></p>';
        echo '<p><button class="button button-primary eic-danger" name="eic_action" value="commit">Commit</button></p>';
        echo "</form>";

        echo '<hr><h2>Backfill existing candidates</h2>';
        echo '<p>Sweeps every <code>candidate_gene = true</code> record and sets ' .
            '<code>acronym = "Candidate"</code> plus <code>year_of_discovery</code> parsed ' .
            'from each record\'s stored <code>candidate_since</code>. No JSON needed.</p>';
        echo '<form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><button class="button button-primary" name="eic_action" value="backfill_dry">Backfill dry run</button></p>';
        echo '<p><label><input type="checkbox" name="confirm_bf" value="1"> I have reviewed the dry run and want to write.</label></p>';
        echo '<p><button class="button button-primary eic-danger" name="eic_action" value="backfill_commit">Backfill commit</button></p>';
        echo "</form>";

        eic_admin_tool_close();
    }

    private static function run(array $records, bool $commit): void
    {
        if ($commit && empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $created = 0;
        $updated = 0;
        $skipped = 0;

        echo "<h2>" . ($commit ? "Commit" : "Dry run") . " — " . count($records) . " record(s)</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Gene</th><th>Type</th><th>Slug</th><th>Since</th><th>Status</th>" .
            "</tr></thead><tbody>";

        foreach ($records as $r) {
            if (!is_array($r)) {
                continue;
            }
            $p = self::plan($r);
            $err = self::validate($r);
            if ($err) {
                echo "<tr><td>" . esc_html($p["gene"] !== "" ? $p["gene"] : "?") .
                    '</td><td></td><td></td><td></td><td style="color:#b32d2e">invalid: ' .
                    esc_html(implode("; ", $err)) . "</td></tr>";
                $skipped++;
                continue;
            }
            $existing = self::find_by_slug($p["slug"]);
            $type = $p["former"] !== "" ? "downgraded (" . $p["former"] . ")" : "candidate";
            $status = "";

            if ($commit) {
                if ($existing) {
                    $id = (int) $existing->ID;
                    // Preserve the record's existing status on update — do not
                    // force-republish something an editor may have drafted or
                    // trashed — and only rewrite the title when it differs.
                    if (get_post_field("post_title", $id) !== $p["title"]) {
                        wp_update_post(["ID" => $id, "post_title" => $p["title"]]);
                    }
                    $status = "updated (ID {$id})";
                    $updated++;
                } else {
                    $id = wp_insert_post([
                        "post_type" => "subtype",
                        "post_status" => "publish",
                        "post_title" => $p["title"],
                        "post_name" => $p["slug"],
                    ]);
                    if (is_wp_error($id) || !$id) {
                        echo "<tr><td>" . esc_html($p["gene"]) . '</td><td></td><td></td><td></td>' .
                            '<td style="color:#b32d2e">insert failed</td></tr>';
                        $skipped++;
                        continue;
                    }
                    $status = "created (ID {$id})";
                    $created++;
                }
                self::write((int) $id, $p);
            } else {
                $status = $existing ? "exists, will update" : "new, will create";
            }

            echo "<tr><td><strong>" . esc_html($p["gene"]) . "</strong></td>" .
                "<td>" . esc_html($type) . "</td>" .
                "<td><code>" . esc_html($p["slug"]) . "</code></td>" .
                "<td>" . esc_html($p["since"]) . "</td>" .
                "<td>" . esc_html($status) . "</td></tr>";
        }
        echo "</tbody></table>";

        if ($commit) {
            echo '<div class="notice notice-success"><p><strong>Done.</strong> Created ' .
                (int) $created . ", updated " . (int) $updated . ", skipped " . (int) $skipped .
                ". Now run <strong>Tools &rsaquo; HGNC Identifiers Backfill</strong> to pull identifiers.</p></div>";
        } else {
            echo "<p>Dry run only. Nothing was written.</p>";
        }
    }
}

EIC_Candidate_Genes_Importer::init();
