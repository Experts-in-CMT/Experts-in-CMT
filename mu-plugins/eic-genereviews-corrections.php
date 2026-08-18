<?php
/**
 * Plugin Name: EIC GeneReviews Corrections
 * Description: Sets or CLEARS `genereviews_url` on existing subtype records from a
 *              reviewed JSON list. Update-only; never creates a subtype.
 * Author: Experts in CMT
 * Version: 1.0.0
 *
 * WHY THIS EXISTS
 *
 * `eic-external-records-backfill.php` used to source GeneReviews from a hardcoded
 * gene-to-NBK map and write per GENE. Two consequences:
 *
 *   1. It could not express a subtype-specific link, being keyed on gene symbol.
 *      `genereviews_url` is subtype-specific: a subtype gets a link only where
 *      GeneReviews has a chapter for THAT subtype, and one gene's subtypes
 *      routinely need different answers.
 *   2. It could not clear. Its write loop skips an empty source value
 *      (`if ($new === "") continue;`), so a stale URL already written survived
 *      any change to the source.
 *
 * GeneReviews was therefore removed from that tool entirely on 2026-08-18, and
 * this is now the sole writer of the field. It matches per SUBTYPE, and an empty
 * string is a meaningful instruction: clear the field.
 *
 * INPUT
 *
 *   { "schema": "genereviews-v1",
 *     "records": [ { "code": "CMT4G", "genereviews_url": "" },
 *                  { "code": "CMT2Y", "genereviews_url": "https://www.ncbi.nlm.nih.gov/books/NBK1358/" } ] }
 *
 * Also accepts a bare array, a single record object, or a `subtypes` key.
 * Ignores bookkeeping keys (gene, candidate, was, reason) so the audit file can
 * be loaded unmodified.
 *
 * Location: wp-content/mu-plugins/eic-genereviews-corrections.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_GeneReviews_Corrections
{
    const CAP    = "manage_options";
    const NONCE  = "eic_genereviews_corrections";
    const SCHEMA = "genereviews-v1";
    const FIELD  = "genereviews_url";

    private static $preview = false;
    private static $changes = [];

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "GeneReviews Corrections",
            "GeneReviews Corrections",
            self::CAP,
            "eic-genereviews-corrections",
            [__CLASS__, "render"]
        );
    }

    /* ---- Schema guard ----
     * A record missing the value key would validate as an empty string, and an
     * empty string is a CLEAR here, so an unmarked or foreign dataset could
     * silently wipe the field on every matched subtype. A mismatch is a hard stop. */
    private static function schema_error($json): string
    {
        if (!is_array($json) || !isset($json["schema"])) {
            return "";
        }
        $got = trim((string) $json["schema"]);
        if ($got === self::SCHEMA) {
            return "";
        }
        return sprintf(
            'This dataset is marked "%s" but this tool writes "%s". An empty value here means CLEAR, ' .
            'so loading the wrong dataset could blank GeneReviews on every matched subtype. Refusing.',
            $got,
            self::SCHEMA
        );
    }

    /* ---- Parse ---- */

    private static function extract_records($json): array
    {
        foreach (["records", "subtypes"] as $k) {
            if (isset($json[$k]) && is_array($json[$k])) {
                return $json[$k];
            }
        }
        if (isset($json["code"])) {
            return [$json];
        }
        if (is_array($json) && isset($json[0])) {
            return $json;
        }
        return [];
    }

    /* ---- Match: ACF `subtype` field, then exact post_title, then slug ---- */

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
            "post_status"    => $status,
            "no_found_rows"  => true,
            "meta_query"     => [
                ["key" => "subtype", "value" => $code, "compare" => "="],
            ],
        ]);
        if (!empty($q->posts)) {
            return [$q->posts[0], "subtype field"];
        }

        $q = new WP_Query([
            "post_type"      => "subtype",
            "posts_per_page" => 1,
            "post_status"    => $status,
            "no_found_rows"  => true,
            "title"          => $code,
        ]);
        if (!empty($q->posts)) {
            return [$q->posts[0], "title"];
        }

        $slug = sanitize_title($code);
        $q = new WP_Query([
            "post_type"      => "subtype",
            "posts_per_page" => 1,
            "post_status"    => $status,
            "no_found_rows"  => true,
            "name"           => $slug,
        ]);
        if (!empty($q->posts)) {
            return [$q->posts[0], "slug ({$slug})"];
        }

        // Candidate records carry a namespaced slug.
        $q = new WP_Query([
            "post_type"      => "subtype",
            "posts_per_page" => 1,
            "post_status"    => $status,
            "no_found_rows"  => true,
            "name"           => "candidate-" . $slug,
        ]);
        if (!empty($q->posts)) {
            return [$q->posts[0], "slug (candidate-{$slug})"];
        }

        return [null, ""];
    }

    /* ---- Validate ---- */

    /** @return array{0:string[],1:array{code:string,url:string}} */
    private static function analyze(array $r): array
    {
        $errors = [];
        $code = trim((string) ($r["code"] ?? ""));
        if ($code === "") {
            $errors[] = "missing code";
        }

        if (!array_key_exists(self::FIELD, $r)) {
            $errors[] = "missing genereviews_url key (omit the record to leave it alone; use \"\" to clear)";
            $url = "";
        } else {
            $url = trim((string) $r[self::FIELD]);
        }

        if ($url !== "") {
            if (!preg_match('#^https://www\.ncbi\.nlm\.nih\.gov/books/NBK\d+/$#', $url)) {
                $errors[] = "not a canonical GeneReviews chapter URL: " . $url;
            }
        }

        return [$errors, ["code" => $code, "url" => $url]];
    }

    /* ---- Render ---- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        echo '<div class="wrap"><h1>GeneReviews Corrections</h1>';
        echo "<p>Sets or <strong>clears</strong> <code>genereviews_url</code> on existing subtype records, " .
            "matched by <code>code</code>. Update-only, diff-only. An empty <code>genereviews_url</code> " .
            "means clear the field. " .
            "<strong>Dry-run first. Take a database backup before committing.</strong></p>";

        echo '<div class="notice notice-info"><p><strong>This tool is the only writer of ' .
            '<code>genereviews_url</code>.</strong> GeneReviews was removed from ' .
            '<code>eic-external-records-backfill.php</code> on 2026-08-18: that tool is keyed on gene ' .
            'symbol and writes one value to every subtype of a gene, so it could not hold a ' .
            'subtype-specific link. Nothing you set here will be overwritten by it.</p></div>';

        $raw = isset($_POST["json"]) ? (string) wp_unslash($_POST["json"]) : "";
        $records = [];
        $blocked = "";

        if (!empty($_POST[self::NONCE]) && check_admin_referer(self::NONCE, self::NONCE)) {
            if (
                $raw === "" &&
                !empty($_FILES["json_file"]["tmp_name"]) &&
                is_uploaded_file($_FILES["json_file"]["tmp_name"])
            ) {
                $uploaded = file_get_contents($_FILES["json_file"]["tmp_name"]);
                if ($uploaded !== false && trim($uploaded) !== "") {
                    $raw = $uploaded;
                }
            }
            // Strip a BOM: it does not trip json_decode's syntax error but breaks the first key.
            $raw = preg_replace('/^\xEF\xBB\xBF/', "", trim($raw));

            if ($raw !== "") {
                $json = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo '<div class="notice notice-error"><p>JSON parse error: ' .
                        esc_html(json_last_error_msg()) . "</p></div>";
                } else {
                    $blocked = self::schema_error($json);
                    if ($blocked !== "") {
                        echo '<div class="notice notice-error"><p><strong>Schema mismatch.</strong> ' .
                            esc_html($blocked) . "</p></div>";
                    } else {
                        $records = self::extract_records($json);
                        if (!$records) {
                            echo '<div class="notice notice-error"><p>No records found in that JSON.</p></div>';
                        }
                    }
                }
            }
        }

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE, self::NONCE);
        echo '<p><textarea name="json" rows="12" style="width:100%;font-family:monospace" ' .
            'placeholder="Paste the corrections JSON here, or upload a .json file below">' .
            esc_textarea($raw) . "</textarea></p>";
        echo '<p><label>Or upload a <code>.json</code> file: ' .
            '<input type="file" name="json_file" accept=".json,application/json"></label></p>';
        echo '<p><button class="button button-primary" name="mode" value="dry">Dry run</button> ' .
            '<button class="button" name="mode" value="commit" ' .
            'onclick="return confirm(\'Commit these GeneReviews changes? Empty values will CLEAR the field.\')">' .
            "Commit</button></p>";
        echo "</form>";

        if ($records && $blocked === "") {
            $mode = isset($_POST["mode"]) ? (string) $_POST["mode"] : "dry";
            self::run($records, $mode === "commit");
        }

        echo "</div>";
    }

    /* ---- Run ---- */

    private static function run(array $records, bool $commit): void
    {
        self::$preview = !$commit;

        $matched = $unmatched = $invalid = $changed = $unchanged = 0;
        $set = $cleared = 0;

        echo "<h2>" . ($commit ? "Commit" : "Dry run") . " &mdash; " . count($records) . " record(s)</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Subtype</th><th>Matched by</th><th>Current</th><th>New</th><th>Result</th>" .
            "</tr></thead><tbody>";

        foreach ($records as $r) {
            if (!is_array($r)) {
                $invalid++;
                continue;
            }
            [$errors, $plan] = self::analyze($r);
            $code = $plan["code"] !== "" ? $plan["code"] : "(no code)";

            if ($errors) {
                $invalid++;
                echo '<tr><td><strong>' . esc_html($code) . '</strong></td><td colspan="4" style="color:#b32d2e">' .
                    esc_html(implode("; ", $errors)) . "</td></tr>";
                continue;
            }

            [$post, $how] = self::find_subtype($plan["code"]);
            if (!$post) {
                $unmatched++;
                echo '<tr><td><strong>' . esc_html($code) . '</strong></td><td colspan="4" style="color:#b32d2e">' .
                    "No matching subtype. Tried the subtype field, title, slug, and candidate slug.</td></tr>";
                continue;
            }
            $matched++;

            $id  = (int) $post->ID;
            $old = trim((string) get_field(self::FIELD, $id));
            $new = $plan["url"];

            if ($old === $new) {
                $unchanged++;
                echo "<tr><td><strong>" . esc_html($code) . "</strong></td><td>" . esc_html($how) .
                    '</td><td colspan="2">' . self::show($old) . "</td><td>unchanged</td></tr>";
                continue;
            }

            if ($commit) {
                self::write($id, $new);
            }
            $changed++;
            $new === "" ? $cleared++ : $set++;

            echo '<tr style="background:#fff8e5"><td><strong>' . esc_html($code) . "</strong></td><td>" .
                esc_html($how) . "</td><td>" . self::show($old) . "</td><td>" . self::show($new) .
                "</td><td><strong>" . ($commit ? ($new === "" ? "cleared" : "updated") : ($new === "" ? "will clear" : "will update")) .
                "</strong></td></tr>";
        }

        echo "</tbody></table>";
        echo '<div class="notice notice-' . ($invalid || $unmatched ? "warning" : "success") . '"><p><strong>' .
            ($commit ? "Done." : "Dry run complete.") . "</strong> Matched {$matched}, " .
            ($commit ? "changed" : "will change") . " {$changed} ({$set} set, {$cleared} cleared), " .
            "unchanged {$unchanged}, unmatched {$unmatched}, invalid {$invalid}.</p></div>";
    }

    private static function show(string $v): string
    {
        if ($v === "") {
            return '<em style="color:#777">(empty)</em>';
        }
        if (preg_match('#(NBK\d+)#', $v, $m)) {
            return '<a href="' . esc_url($v) . '" target="_blank" rel="noopener"><code>' .
                esc_html($m[1]) . "</code></a>";
        }
        return "<code>" . esc_html($v) . "</code>";
    }

    /** Writes through ACF so the field-key meta row stays consistent. Empty is a real value here. */
    private static function write(int $post_id, string $value): void
    {
        $key = self::FIELD;
        $fo = function_exists("acf_get_field") ? acf_get_field(self::FIELD) : null;
        if ($fo && !empty($fo["key"])) {
            $key = $fo["key"];
        }
        if (function_exists("update_field")) {
            update_field($key, $value, $post_id);
        } else {
            update_post_meta($post_id, self::FIELD, $value);
        }
    }
}

EIC_GeneReviews_Corrections::init();
