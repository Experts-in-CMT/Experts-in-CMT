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
 * MU Plugin: EIC ClinVar URL Builder
 * ------------------------------------------------------------
 * WP-Admin tool:  Tools > ClinVar URL Builder
 *
 * Purpose:
 *   Build and repair the `clinvar_url` field on every `subtype`
 *   record from a single verified template: a gene-scoped ClinVar
 *   search pre-filtered to Pathogenic + Likely Pathogenic germline
 *   variants.
 *
 * Template (verified against ClinVar, July 2026):
 *   https://www.ncbi.nlm.nih.gov/clinvar/?term=
 *     <SYMBOL>[gene] AND ("clinsig pathogenic"[Properties]
 *       OR "clinsig likely pathogenic"[Properties])
 *   (the term is URL-encoded via rawurlencode)
 *
 * Design guarantees:
 *   - Dry-run gated. Shows current vs proposed for every record;
 *     nothing writes until confirmed.
 *   - Scope control: repair only empty, only differing, or all.
 *   - Unknown-gene records get no link (optionally cleared).
 *   - Optional HGNC normalization: resolve the record's symbol to
 *     the HGNC-approved symbol before building the URL, so an
 *     outdated symbol self-corrects. Off by default.
 *
 * Location: wp-content/mu-plugins/eic-clinvar-url-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_ClinVar_URL_Tool
{
    const MENU_SLUG = "eic-clinvar-url-tool";
    const CAP       = "manage_options";
    const NONCE     = "eic_clinvar_url_tool";
    const HGNC_OPTION = "eic_hgnc_cache"; // shared with the importer

    /**
     * In-request memo for the shared HGNC cache option, flushed once at shutdown.
     * The cache was previously read and re-written on every uncached gene during a
     * bulk backfill (O(n^2) option I/O).
     */
    private static $cache_mem = null;
    private static $cache_dirty = false;

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

    /**
     * Build the canonical P/LP ClinVar URL for a gene symbol.
     * Returns "" for an empty symbol.
     */
    public static function build_url(string $symbol): string
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return "";
        }
        $term =
            $symbol .
            '[gene] AND ("clinsig pathogenic"[Properties]' .
            ' OR "clinsig likely pathogenic"[Properties])';
        return "https://www.ncbi.nlm.nih.gov/clinvar/?term=" .
            rawurlencode($term);
    }

    /* ---- HGNC approved-symbol resolution (optional, cached) ---- */

    private static function hgnc_symbol(string $symbol): ?string
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return null;
        }
        $cache = self::cache_map();
        // Only short-circuit on a cache entry that this tool wrote (has
        // the "approved" key). Other EIC tools share HGNC_OPTION and may
        // have stored a different shape (e.g. ["omim" => ...]); those
        // must fall through to a live fetch instead of no-op'ing.
        if (
            isset($cache[$symbol]) &&
            is_array($cache[$symbol]) &&
            array_key_exists("approved", $cache[$symbol])
        ) {
            return $cache[$symbol]["approved"] ?? null;
        }
        $resp = wp_remote_get(
            "https://rest.genenames.org/fetch/symbol/" . rawurlencode($symbol),
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (
            is_wp_error($resp) ||
            wp_remote_retrieve_response_code($resp) !== 200
        ) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $docs = $body["response"]["docs"] ?? [];
        $approved = !empty($docs) ? ($docs[0]["symbol"] ?? "") : "";
        // Store in the same shape the importer uses (approved key at minimum),
        // preserving any keys other EIC tools set on this shared cache entry.
        $entry = isset($cache[$symbol]) && is_array($cache[$symbol]) ? $cache[$symbol] : [];
        $entry["approved"] = $approved;
        self::cache_put($symbol, $entry);
        return $approved !== "" ? $approved : null;
    }

    /* ---- Bootstrap ---- */

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "ClinVar URL Builder",
            "ClinVar URL Builder",
            self::CAP,
            self::MENU_SLUG,
            [__CLASS__, "render"]
        );
    }

    /* ---- Collect all subtype records + resolved URLs ---- */

    private static function collect(bool $hgnc): array
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

        $rows = [];
        foreach ($q->posts as $id) {
            $unknown = (bool) get_field("unknown_gene", $id);
            $symbol  = trim((string) get_field("gene_symbol", $id));
            $current = trim((string) get_field("clinvar_url", $id));

            $used_symbol = $symbol;
            $note = "";
            if ($hgnc && !$unknown && $symbol !== "") {
                $approved = self::hgnc_symbol($symbol);
                if ($approved && strcasecmp($approved, $symbol) !== 0) {
                    $used_symbol = $approved;
                    $note = "HGNC: {$symbol} -> {$approved}";
                }
            }

            $proposed = $unknown ? "" : self::build_url($used_symbol);

            if ($unknown || $symbol === "") {
                $status = $current === "" ? "skip (no gene)" : "clear?";
            } elseif ($current === "") {
                $status = "empty -> set";
            } elseif ($current === $proposed) {
                $status = "ok";
            } else {
                $status = "differs -> fix";
            }

            $rows[] = [
                "id"       => $id,
                "title"    => get_the_title($id),
                "symbol"   => $symbol,
                "unknown"  => $unknown,
                "current"  => $current,
                "proposed" => $proposed,
                "status"   => $status,
                "note"     => $note,
            ];
        }
        return $rows;
    }

    /* ---- Render ---- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        eic_admin_tool_open("ClinVar URL Builder");
        echo "<p>Builds <code>clinvar_url</code> on every subtype from the " .
            "verified Pathogenic + Likely Pathogenic gene template.</p>";

        $action = $_POST["eic_action"] ?? "";
        $hgnc   = !empty($_POST["hgnc"]);

        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "dryrun") {
                self::do_dryrun($hgnc);
            } elseif ($action === "commit") {
                self::do_commit($hgnc);
            }
        }

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="dryrun">';
        echo '<p><label><input type="checkbox" name="hgnc" value="1"> ' .
            "Normalize each symbol to the HGNC-approved symbol first " .
            "(slower, hits HGNC; self-corrects outdated symbols)</label></p>";
        echo '<button class="button button-primary">Dry run (no writes)</button>';
        echo "</form>";

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="commit">';
        echo '<p><label><input type="checkbox" name="hgnc" value="1"> ' .
            "Use HGNC-approved symbols</label></p>";
        echo "<p>Scope: <select name=\"scope\">" .
            '<option value="differs">Only records that differ or are empty</option>' .
            '<option value="empty">Only empty records</option>' .
            '<option value="all">All records with a gene</option>' .
            "</select></p>";
        echo '<p><label><input type="checkbox" name="clear_unknown" value="1"> ' .
            "Clear clinvar_url on unknown-gene records</label></p>";
        echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' .
            "I have reviewed the dry run and want to write.</label></p>";
        echo '<button class="button button-primary">Commit</button>';
        echo "</form>";

        eic_admin_tool_close();
    }

    private static function do_dryrun(bool $hgnc): void
    {
        $rows = self::collect($hgnc);
        $counts = ["empty -> set" => 0, "differs -> fix" => 0, "ok" => 0];
        echo "<h2>Dry run — " . count($rows) . " subtypes</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Subtype</th><th>Gene</th><th>Status</th>" .
            "<th>Current</th><th>Proposed</th></tr></thead><tbody>";
        foreach ($rows as $r) {
            if (isset($counts[$r["status"]])) {
                $counts[$r["status"]]++;
            }
            $hi = in_array($r["status"], ["empty -> set", "differs -> fix"], true)
                ? ' style="background:#fff3cd"'
                : "";
            echo "<tr{$hi}>";
            echo "<td><strong>" . esc_html($r["title"]) . "</strong>" .
                ($r["note"] ? "<br><small>" . esc_html($r["note"]) . "</small>" : "") .
                "</td>";
            echo "<td>" . esc_html($r["symbol"] !== "" ? $r["symbol"] : "—") . "</td>";
            echo "<td>" . esc_html($r["status"]) . "</td>";
            echo "<td><small>" . esc_html($r["current"] !== "" ? $r["current"] : "(empty)") . "</small></td>";
            echo "<td><small>" . esc_html($r["proposed"] !== "" ? $r["proposed"] : "(none)") . "</small></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p>To set: <strong>{$counts["empty -> set"]}</strong> empty, " .
            "<strong>{$counts["differs -> fix"]}</strong> differing, " .
            "<strong>{$counts["ok"]}</strong> already correct.</p>";
    }

    private static function do_commit(bool $hgnc): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $scope = $_POST["scope"] ?? "differs";
        $clear_unknown = !empty($_POST["clear_unknown"]);

        $rows = self::collect($hgnc);
        $set = 0;
        $cleared = 0;
        foreach ($rows as $r) {
            // Unknown / no-gene records.
            if ($r["proposed"] === "") {
                if ($clear_unknown && $r["current"] !== "") {
                    update_field("field_clinvar_url", "", $r["id"]);
                    $cleared++;
                }
                continue;
            }

            $should = false;
            if ($scope === "all") {
                $should = true;
            } elseif ($scope === "empty") {
                $should = $r["current"] === "";
            } else {
                // "differs": empty or mismatched
                $should = $r["current"] === "" || $r["current"] !== $r["proposed"];
            }

            if ($should) {
                update_field("field_clinvar_url", $r["proposed"], $r["id"]);
                $set++;
            }
        }

        echo '<div class="notice notice-success"><p><strong>Done.</strong> ' .
            "Set {$set} URL(s)" .
            ($clear_unknown ? ", cleared {$cleared} unknown-gene record(s)" : "") .
            ".</p></div>";
    }
}

EIC_ClinVar_URL_Tool::init();
