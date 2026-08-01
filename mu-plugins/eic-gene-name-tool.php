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
 * MU Plugin: EIC Gene Name Backfill
 * ------------------------------------------------------------
 * Tools > Gene Name Backfill
 *
 * Fills the ACF `full_gene_name` field on subtype records from the
 * HGNC-approved gene name, resolved live from the HGNC REST API by the
 * record's `gene_symbol`. Names are written in Title Case to match house
 * style.
 *
 * Design:
 *   - Dry-run gated: shows gene_symbol -> proposed name per record; nothing
 *     writes until confirmed.
 *   - Scope control: only-empty (default), only-differing, or all.
 *   - Unknown-gene records and records with no symbol are skipped.
 *   - Resolves via HGNC (rest.genenames.org), the same source the ClinVar
 *     and ClinGen tools use; caches results in the shared eic_hgnc_cache
 *     option so repeated runs and the URL builders reuse the lookup.
 *   - A small set of acronyms/particles is preserved in canonical case
 *     during Title-casing (tRNA, mRNA, DNA, RNA, ATP, etc.).
 *
 * Location: wp-content/mu-plugins/eic-gene-name-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!is_admin()) {
    return;
}

final class EIC_Gene_Name_Tool
{
    const CAP          = "manage_options";
    const NONCE        = "eic_gene_name_tool";
    const HGNC_OPTION  = "eic_hgnc_cache";

    /* Words kept lowercase in Title Case (unless first word). */
    private static $minor = ["and","or","of","the","in","to","for","a","an","with","at"];

    /* Tokens forced to a fixed casing regardless of position. */
    private static function fixed_case(): array
    {
        return [
            "trna" => "tRNA", "mrna" => "mRNA", "rrna" => "rRNA", "ncrna" => "ncRNA",
            "dna" => "DNA", "rna" => "RNA", "atp" => "ATP", "adp" => "ATP",
            "nadh" => "NADH", "coa" => "CoA", "gtp" => "GTP", "gdp" => "GDP",
            "er" => "ER", "tcp1" => "TCP1", "ii" => "II", "iii" => "III", "iv" => "IV",
        ];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "Gene Name Backfill",
            "Gene Name Backfill",
            self::CAP,
            "eic-gene-name-tool",
            [__CLASS__, "render"]
        );
    }

    /* ---- Title Case with domain-aware exceptions ---- */
    public static function title_case(string $name): string
    {
        $name = trim(preg_replace('/\s+/', " ", $name));
        if ($name === "") {
            return "";
        }
        $fixed = self::fixed_case();
        $parts = explode(" ", $name);
        $out = [];
        $i = 0;
        foreach ($parts as $w) {
            $lower = strtolower($w);
            // preserve hyphenated compounds e.g. "long-chain"
            if (strpos($w, "-") !== false) {
                $sub = array_map(function ($s) use ($fixed) {
                    $sl = strtolower($s);
                    if (isset($fixed[$sl])) { return $fixed[$sl]; }
                    return $s === "" ? $s : ucfirst($sl);
                }, explode("-", $w));
                $out[] = implode("-", $sub);
                $i++;
                continue;
            }
            $strip = preg_replace('/[^a-z0-9]/', "", $lower);
            if (isset($fixed[$strip])) {
                $out[] = str_replace($strip, $fixed[$strip], $lower);
            } elseif ($i > 0 && in_array($lower, self::$minor, true)) {
                $out[] = $lower;
            } else {
                $out[] = ucfirst($lower);
            }
            $i++;
        }
        return implode(" ", $out);
    }

    /* ---- HGNC previous-symbol fallback (retired symbols) ---- */
    private static function hgnc_prev_symbol(string $symbol): array
    {
        $resp = wp_remote_get(
            "https://rest.genenames.org/search/prev_symbol/" . rawurlencode($symbol),
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return [];
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $found = $body["response"]["docs"] ?? [];
        if (empty($found) || empty($found[0]["symbol"])) {
            return [];
        }
        // search returns symbol + hgnc_id; fetch the full record for the name
        $cur = $found[0]["symbol"];
        $resp2 = wp_remote_get(
            "https://rest.genenames.org/fetch/symbol/" . rawurlencode($cur),
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (is_wp_error($resp2) || wp_remote_retrieve_response_code($resp2) !== 200) {
            return [];
        }
        $b2 = json_decode(wp_remote_retrieve_body($resp2), true);
        return $b2["response"]["docs"] ?? [];
    }

    /* ---- HGNC approved name (cached) ---- */
    private static function hgnc_name(string $symbol): ?string
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return null;
        }
        $cache = get_option(self::HGNC_OPTION, []);
        if (isset($cache[$symbol]) && is_array($cache[$symbol]) && !empty($cache[$symbol]["name"])) {
            return $cache[$symbol]["name"];
        }
        $resp = wp_remote_get(
            "https://rest.genenames.org/fetch/symbol/" . rawurlencode($symbol),
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $docs = $body["response"]["docs"] ?? [];
        if (empty($docs)) {
            // Symbol may be a retired/previous HGNC symbol (e.g. SEPT9 -> SEPTIN9).
            // Retry against previous-symbol index to get the current record.
            $docs = self::hgnc_prev_symbol($symbol);
            if (empty($docs)) {
                return null;
            }
        }
        $approved_symbol = $docs[0]["symbol"] ?? $symbol;
        $name = $docs[0]["name"] ?? "";
        if ($name === "") {
            return null;
        }
        $existing = is_array($cache[$symbol] ?? null) ? $cache[$symbol] : [];
        $existing["approved"] = $approved_symbol;
        $existing["name"] = $name;
        $cache[$symbol] = $existing;
        update_option(self::HGNC_OPTION, $cache, false);
        return $name;
    }

    private static function collect(): array
    {
        $q = new WP_Query([
            "post_type"      => "subtype",
            "post_status"    => "any",
            "posts_per_page" => -1,
            "fields"         => "ids",
            "no_found_rows"  => true,
            "orderby"        => "title",
            "order"          => "ASC",
        ]);
        return $q->posts;
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        $scope = isset($_POST["scope"]) ? sanitize_key($_POST["scope"]) : "empty";
        $action = $_POST["eic_action"] ?? "";

        eic_admin_tool_open("Gene Name Backfill");
        echo "<p>Fills <code>full_gene_name</code> on subtypes from the " .
            "HGNC-approved name (resolved live by gene symbol), written in " .
            "Title Case. Unknown-gene records are skipped. " .
            "<strong>Back up before applying.</strong></p>";

        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "dryrun") {
                self::run($scope, false);
            } elseif ($action === "commit") {
                self::run($scope, true);
            }
        }

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p>Scope: <select name="scope">' .
            '<option value="empty"' . selected($scope, "empty", false) . '>Only records with no full gene name</option>' .
            '<option value="differs"' . selected($scope, "differs", false) . '>Only records that differ or are empty</option>' .
            '<option value="all"' . selected($scope, "all", false) . '>All records with a gene</option>' .
            '</select></p>';
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> Backed up and reviewed the dry run.</label></p>';
        echo '<button class="button button-primary" name="eic_action" value="commit">Commit</button>';
        echo "</form>";
        eic_admin_tool_close();
    }

    private static function run(string $scope, bool $commit): void
    {
        if ($commit && empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $ids = self::collect();
        $set = 0; $skip = 0; $unresolved = [];
        echo "<h2>" . ($commit ? "Commit" : "Dry run") . "</h2>";
        echo '<table class="widefat striped"><thead><tr><th>Subtype</th><th>Gene</th><th>Current</th><th>Proposed (Title Case)</th><th>Action</th></tr></thead><tbody>';

        foreach ($ids as $id) {
            $unknown = (bool) get_field("unknown_gene", $id);
            $symbol  = trim((string) get_field("gene_symbol", $id));
            $current = trim((string) get_field("full_gene_name", $id));

            if ($unknown || $symbol === "") {
                continue;
            }
            if ($scope === "empty" && $current !== "") {
                continue;
            }

            $raw = self::hgnc_name($symbol);
            if ($raw === null) {
                $unresolved[] = $symbol;
                echo "<tr><td><strong>" . esc_html(get_the_title($id)) . "</strong></td><td>" .
                    esc_html($symbol) . "</td><td>" . esc_html($current ?: "(empty)") .
                    "</td><td><em>HGNC lookup failed</em></td><td>skip</td></tr>";
                $skip++;
                continue;
            }
            $proposed = self::title_case($raw);

            if ($scope === "differs" && $current === $proposed) {
                continue;
            }
            $will = $current !== $proposed;
            $act = $will ? ($commit ? "written" : "set") : "ok";
            $hi = $will ? ' style="background:#fff3cd"' : "";
            echo "<tr{$hi}><td><strong>" . esc_html(get_the_title($id)) . "</strong></td><td>" .
                esc_html($symbol) . "</td><td>" . esc_html($current ?: "(empty)") .
                "</td><td>" . esc_html($proposed) . "</td><td>" . esc_html($act) . "</td></tr>";

            if ($commit && $will) {
                update_field("full_gene_name", $proposed, $id);
                $set++;
            }
        }
        echo "</tbody></table>";
        if ($commit) {
            echo '<div class="notice notice-success"><p><strong>Done.</strong> Wrote ' . $set . ' name(s).' .
                ($unresolved ? " Unresolved: " . esc_html(implode(", ", array_unique($unresolved))) . "." : "") .
                "</p></div>";
        } elseif ($unresolved) {
            echo '<p><em>HGNC could not resolve: ' . esc_html(implode(", ", array_unique($unresolved))) . "</em></p>";
        }
    }
}

EIC_Gene_Name_Tool::init();
