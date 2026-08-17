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

    /**
     * Max live (uncached) HGNC lookups per run. Each uncached symbol costs up to
     * two remote calls, so a whole-store cold run can exceed PHP's
     * max_execution_time. Capping per run keeps it bounded; the shared HGNC cache
     * persists, so re-running resumes with the previously fetched genes free.
     */
    const BATCH = 40;

    /**
     * In-request memo for the shared HGNC cache option, with a single deferred
     * write at shutdown (was read + re-written on every uncached name — O(n^2)).
     */
    private static $cache_mem = null;
    private static $cache_dirty = false;
    private static $last_deferred = 0;

    private static function cache_map(): array
    {
        if (self::$cache_mem === null) {
            $c = get_option(self::HGNC_OPTION, []);
            self::$cache_mem = is_array($c) ? $c : [];
        }
        return self::$cache_mem;
    }

    private static function cache_put(string $symbol, array $entry): void
    {
        self::cache_map();
        self::$cache_mem[$symbol] = $entry;
        if (!self::$cache_dirty) {
            self::$cache_dirty = true;
            add_action("shutdown", [__CLASS__, "flush_cache"]);
        }
    }

    public static function flush_cache(): void
    {
        if (self::$cache_dirty) {
            update_option(self::HGNC_OPTION, self::$cache_mem, false);
            self::$cache_dirty = false;
        }
    }

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

    /** Cached HGNC-approved name for a symbol, or null if not cached (no remote). */
    private static function hgnc_name_cached(string $symbol): ?string
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return null;
        }
        $cache = self::cache_map();
        if (isset($cache[$symbol]) && is_array($cache[$symbol]) && !empty($cache[$symbol]["name"])) {
            return $cache[$symbol]["name"];
        }
        return null;
    }

    private static function hgnc_name(string $symbol): ?string
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return null;
        }
        $hit = self::hgnc_name_cached($symbol);
        if ($hit !== null) {
            return $hit;
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
        $cache = self::cache_map();
        $existing = is_array($cache[$symbol] ?? null) ? $cache[$symbol] : [];
        $existing["approved"] = $approved_symbol;
        $existing["name"] = $name;
        self::cache_put($symbol, $existing);
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
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no subtype writes)</button></p>';
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
        $budget = self::BATCH;
        self::$last_deferred = 0;
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

            // Cache-first, then spend the per-run live-lookup budget on misses;
            // defer the rest to the next run to stay under the PHP time limit.
            $cachedName = self::hgnc_name_cached($symbol);
            if ($cachedName !== null) {
                $raw = $cachedName;
            } elseif ($budget > 0) {
                $budget--;
                $raw = self::hgnc_name($symbol);
            } else {
                self::$last_deferred++;
                echo "<tr><td><strong>" . esc_html(get_the_title($id)) . "</strong></td><td>" .
                    esc_html($symbol) . "</td><td>" . esc_html($current ?: "(empty)") .
                    "</td><td><em>deferred &mdash; run again</em></td><td>deferred</td></tr>";
                continue;
            }
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
                // Write by field key (not name) for reliable ACF resolution,
                // matching the HGNC identifiers tool and the CLAUDE.md convention.
                update_field("field_full_gene_name", $proposed, $id);
                $set++;
            }
        }
        echo "</tbody></table>";
        if (self::$last_deferred > 0) {
            echo '<div class="notice notice-warning"><p><strong>Cold cache — batch limit reached.</strong> ' .
                (int) self::$last_deferred . " gene(s) were deferred this run (live-lookup cap of " .
                (int) self::BATCH . " per run). Cached lookups persist, so run again to continue.</p></div>";
        }
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
