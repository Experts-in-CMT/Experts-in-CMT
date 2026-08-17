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
 * MU Plugin: EIC ClinGen URL Builder
 * ------------------------------------------------------------
 * WP-Admin tool:  Tools > ClinGen URL Builder
 *
 * Purpose:
 *   Set `clingen_url` on each `subtype` record to the exact
 *   ClinGen Gene-Disease Validity curation produced by the
 *   Charcot-Marie-Tooth Disease Gene Curation Expert Panel
 *   (GCEP, affiliate 40063) for that gene.
 *
 *   Unlike ClinVar, ClinGen CMT curation is not a URL template:
 *   it is a fixed set of curated gene-disease assertions. This
 *   tool carries the harvested lookup (72 curations / 70 genes)
 *   and only links genes ClinGen has curated for CMT.
 *
 * Coverage & behavior:
 *   - Gene with exactly one CMT curation  -> propose that URL.
 *   - Gene with no CMT curation           -> no link (optional clear).
 *   - Gene with multiple CMT curations    -> flagged "choose"; not
 *     auto-set (only PMP22 and NEFL). Both options shown so the
 *     right disease is picked per subtype by hand.
 *
 * Provenance:
 *   Harvested 2026-07-09 from search.clinicalgenome.org /api/validity,
 *   filtered to the CMT GCEP. Stable CCID citation link form:
 *     https://search.clinicalgenome.org/CCID:XXXXXX
 *
 * Location: wp-content/mu-plugins/eic-clingen-url-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_ClinGen_URL_Tool
{
    const MENU_SLUG   = "eic-clingen-url-tool";
    const CAP         = "manage_options";
    const NONCE       = "eic_clingen_url_tool";
    const HGNC_OPTION = "eic_hgnc_cache"; // shared with the other tools

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
     * Harvested ClinGen CMT GCEP curations.
     * SYMBOL => [ [url, mondo, classification], ... ]
     */
    public static function curations(): array
    {
        // Harvested from ClinGen CMT Disease GCEP (affiliate 40063). Stable CCID citation links. 72 curations / 70 genes, 2026-07-09.
        // Format: SYMBOL => [ [url, mondo, classification], ... ]
        return [
            "AARS1" => [["https://search.clinicalgenome.org/CCID:004002", "MONDO:0013212", "Definitive"]],
            "ARHGEF10" => [["https://search.clinicalgenome.org/CCID:004165", "MONDO:0011998", "Limited"]],
            "ATL1" => [["https://search.clinicalgenome.org/CCID:004200", "MONDO:0013381", "Definitive"]],
            "ATL3" => [["https://search.clinicalgenome.org/CCID:004201", "MONDO:0014286", "Moderate"]],
            "ATP1A1" => [["https://search.clinicalgenome.org/CCID:004208", "MONDO:0054833", "Definitive"]],
            "ATP7A" => [["https://search.clinicalgenome.org/CCID:004216", "MONDO:0010338", "Moderate"]],
            "BSCL2" => [["https://search.clinicalgenome.org/CCID:004292", "MONDO:0018894", "Definitive"]],
            "CADM3" => [["https://search.clinicalgenome.org/CCID:009251", "MONDO:0030433", "Moderate"]],
            "COQ7" => [["https://search.clinicalgenome.org/CCID:009252", "MONDO:0018894", "Strong"]],
            "DNAJB2" => [["https://search.clinicalgenome.org/CCID:004677", "MONDO:0013947", "Definitive"]],
            "DNM2" => [["https://search.clinicalgenome.org/CCID:004687", "MONDO:0015626", "Definitive"]],
            "DST" => [["https://search.clinicalgenome.org/CCID:004708", "MONDO:0013839", "Definitive"]],
            "DYNC1H1" => [["https://search.clinicalgenome.org/CCID:004713", "MONDO:0018894", "Definitive"]],
            "EGR2" => [["https://search.clinicalgenome.org/CCID:004734", "MONDO:0015626", "Definitive"]],
            "ELP1" => [["https://search.clinicalgenome.org/CCID:009357", "MONDO:0009131", "Moderate"]],
            "FBLN5" => [["https://search.clinicalgenome.org/CCID:004821", "MONDO:0018776", "Moderate"]],
            "FBXO38" => [["https://search.clinicalgenome.org/CCID:004827", "MONDO:0018894", "Moderate"]],
            "FGD4" => [["https://search.clinicalgenome.org/CCID:004834", "MONDO:0015626", "Definitive"]],
            "FIG4" => [["https://search.clinicalgenome.org/CCID:004854", "MONDO:0015626", "Definitive"]],
            "GAN" => [["https://search.clinicalgenome.org/CCID:004918", "MONDO:0009749", "Definitive"]],
            "GARS1" => [["https://search.clinicalgenome.org/CCID:004920", "MONDO:0011091", "Definitive"]],
            "GDAP1" => [["https://search.clinicalgenome.org/CCID:004938", "MONDO:0015626", "Definitive"]],
            "GJB1" => [["https://search.clinicalgenome.org/CCID:004952", "MONDO:0010549", "Definitive"]],
            "GNB4" => [["https://search.clinicalgenome.org/CCID:004977", "MONDO:0015626", "Moderate"]],
            "HINT1" => [["https://search.clinicalgenome.org/CCID:005061", "MONDO:0015626", "Definitive"]],
            "HSPB1" => [["https://search.clinicalgenome.org/CCID:005095", "MONDO:0011687", "Definitive"]],
            "HSPB8" => [["https://search.clinicalgenome.org/CCID:005096", "MONDO:0015362", "Definitive"]],
            "IGHMBP2" => [["https://search.clinicalgenome.org/CCID:005120", "MONDO:0020127", "Definitive"]],
            "INF2" => [["https://search.clinicalgenome.org/CCID:005144", "MONDO:0013758", "Definitive"]],
            "ITPR3" => [["https://search.clinicalgenome.org/CCID:009253", "MONDO:0859311", "Definitive"]],
            "KIF1B" => [["https://search.clinicalgenome.org/CCID:005229", "MONDO:0007308", "Limited"]],
            "KIF5A" => [["https://search.clinicalgenome.org/CCID:005232", "MONDO:0024237", "Definitive"]],
            "LITAF" => [["https://search.clinicalgenome.org/CCID:005288", "MONDO:0015626", "Definitive"]],
            "LRSAM1" => [["https://search.clinicalgenome.org/CCID:005306", "MONDO:0013753", "Definitive"]],
            "MARS1" => [["https://search.clinicalgenome.org/CCID:005337", "MONDO:0015626", "Limited"]],
            "MCM3AP" => [["https://search.clinicalgenome.org/CCID:005352", "MONDO:0029131", "Definitive"]],
            "MED25" => [["https://search.clinicalgenome.org/CCID:005366", "MONDO:0011570", "Disputed"]],
            "MFN2" => [["https://search.clinicalgenome.org/CCID:005380", "MONDO:0012231", "Definitive"]],
            "MME" => [["https://search.clinicalgenome.org/CCID:005399", "MONDO:0044640", "Definitive"]],
            "MORC2" => [["https://search.clinicalgenome.org/CCID:005407", "MONDO:0014736", "Definitive"]],
            "MPZ" => [["https://search.clinicalgenome.org/CCID:005415", "MONDO:0015626", "Definitive"]],
            "MTMR2" => [["https://search.clinicalgenome.org/CCID:005499", "MONDO:0018776", "Definitive"]],
            "NEFH" => [["https://search.clinicalgenome.org/CCID:005613", "MONDO:0014836", "Definitive"]],
            "NEFL" => [["https://search.clinicalgenome.org/CCID:005615", "MONDO:0015626", "Definitive"], ["https://search.clinicalgenome.org/CCID:005614", "MONDO:0018993", "Definitive"]],
            "NGF" => [["https://search.clinicalgenome.org/CCID:005638", "MONDO:0015364", "Strong"]],
            "NTRK1" => [["https://search.clinicalgenome.org/CCID:005687", "MONDO:0009746", "Definitive"]],
            "PDK3" => [["https://search.clinicalgenome.org/CCID:005756", "MONDO:0010479", "Definitive"]],
            "PLEKHG5" => [["https://search.clinicalgenome.org/CCID:005829", "MONDO:0019056", "Definitive"]],
            "PMP22" => [["https://search.clinicalgenome.org/CCID:005837", "MONDO:0007309", "Definitive"], ["https://search.clinicalgenome.org/CCID:008314", "MONDO:0008087", "Definitive"]],
            "PMP2" => [["https://search.clinicalgenome.org/CCID:005836", "MONDO:0015626", "Moderate"]],
            "PRX" => [["https://search.clinicalgenome.org/CCID:005907", "MONDO:0018995", "Definitive"]],
            "RAB7A" => [["https://search.clinicalgenome.org/CCID:005949", "MONDO:0018993", "Definitive"]],
            "RETREG1" => [["https://search.clinicalgenome.org/CCID:008315", "MONDO:0015364", "Definitive"]],
            "SBF1" => [["https://search.clinicalgenome.org/CCID:006058", "MONDO:0014117", "Moderate"]],
            "SBF2" => [["https://search.clinicalgenome.org/CCID:006059", "MONDO:0011475", "Definitive"]],
            "SCN11A" => [["https://search.clinicalgenome.org/CCID:006062", "MONDO:0014244", "Definitive"]],
            "SCO2" => [["https://search.clinicalgenome.org/CCID:009336", "MONDO:0015626", "Moderate"]],
            "SEPTIN9" => [["https://search.clinicalgenome.org/CCID:006106", "MONDO:0017362", "Moderate"]],
            "SETX" => [["https://search.clinicalgenome.org/CCID:006121", "MONDO:0018894", "Definitive"]],
            "SH3TC2" => [["https://search.clinicalgenome.org/CCID:006133", "MONDO:0011113", "Definitive"]],
            "SLC25A46" => [["https://search.clinicalgenome.org/CCID:006171", "MONDO:0014671", "Definitive"]],
            "SLC5A7" => [["https://search.clinicalgenome.org/CCID:006195", "MONDO:0008024", "Moderate"]],
            "SORD" => [["https://search.clinicalgenome.org/CCID:006246", "MONDO:0015626", "Definitive"]],
            "SPTLC1" => [["https://search.clinicalgenome.org/CCID:009254", "MONDO:0008086", "Definitive"]],
            "SPTLC2" => [["https://search.clinicalgenome.org/CCID:006270", "MONDO:0013337", "Definitive"]],
            "SYT2" => [["https://search.clinicalgenome.org/CCID:006308", "MONDO:0014468", "Moderate"]],
            "TRPV4" => [["https://search.clinicalgenome.org/CCID:006453", "MONDO:0019056", "Definitive"]],
            "WARS1" => [["https://search.clinicalgenome.org/CCID:006533", "MONDO:0018894", "Limited"]],
            "WNK1" => [["https://search.clinicalgenome.org/CCID:006554", "MONDO:0024309", "Definitive"]],
            "YARS1" => [["https://search.clinicalgenome.org/CCID:006568", "MONDO:0015626", "Definitive"]],
        ];
    }

    /**
     * Confirmed per-subtype decisions for the multi-curation genes
     * (PMP22, NEFL), keyed by clean subtype name. "" = leave blank.
     * Lets the tool resolve these automatically on any environment.
     */
    private static function overrides(): array
    {
        $A = "https://search.clinicalgenome.org/CCID:005837"; // PMP22 CMT1A
        $B = "https://search.clinicalgenome.org/CCID:008314"; // PMP22 HNPP
        $C = "https://search.clinicalgenome.org/CCID:005615"; // NEFL AD
        $D = "https://search.clinicalgenome.org/CCID:005614"; // NEFL AR
        return [
            "CMT1A"  => $A,
            "HNPP"   => $B,
            "CMT1E"  => "", // PMP22 point-mutation CMT; no CMT1E-specific curation.
            "CMT1F"  => $C,
            "CMT2E"  => $C,
            "CMT2B5" => $D,
        ];
    }

    private static function classify_url(string $url): string
    {
        foreach (self::curations() as $curs) {
            foreach ($curs as $c) {
                if ($c[0] === $url) {
                    return $c[2];
                }
            }
        }
        return "";
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
        // have stored a different shape; those must fall through to a
        // live fetch instead of no-op'ing.
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
        // Preserve any keys other EIC tools set on this shared cache entry.
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
            "ClinGen URL Builder",
            "ClinGen URL Builder",
            self::CAP,
            self::MENU_SLUG,
            [__CLASS__, "render"]
        );
    }

    /* ---- Collect subtype records + resolved curation ---- */

    private static function collect(bool $hgnc): array
    {
        $lookup = self::curations();

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
            $current = trim((string) get_field("clingen_url", $id));

            $used = $symbol;
            $note = "";
            if ($hgnc && !$unknown && $symbol !== "") {
                $approved = self::hgnc_symbol($symbol);
                if ($approved && strcasecmp($approved, $symbol) !== 0) {
                    $used = $approved;
                    $note = "HGNC: " . $symbol . " -> " . $approved;
                }
            }

            $subKey = strtoupper(trim(
                (string) (get_field("subtype", $id) ?: get_the_title($id))
            ));
            $ov = self::overrides();

            $curs = ($unknown || $used === "")
                ? []
                : ($lookup[strtoupper($used)] ?? []);

            $proposed = "";
            $cls = "";

            if (array_key_exists($subKey, $ov)) {
                // Resolved per-subtype decision for a multi-curation gene.
                $proposed = $ov[$subKey];
                if ($proposed === "") {
                    $note = trim($note . "  override: leave blank");
                    $status = $current === ""
                        ? "override blank (ok)"
                        : "override blank (clear)";
                } else {
                    $cls = self::classify_url($proposed);
                    $note = trim($note . "  override");
                    $status = $current === ""
                        ? "empty -> set"
                        : ($current === $proposed ? "ok" : "differs -> fix");
                }
            } elseif (count($curs) === 1) {
                $proposed = $curs[0][0];
                $cls = $curs[0][2];
                $status = $current === ""
                    ? "empty -> set"
                    : ($current === $proposed ? "ok" : "differs -> fix");
            } elseif (count($curs) > 1) {
                $opts = [];
                foreach ($curs as $c) {
                    $opts[] = $c[1] . " (" . $c[2] . "): " . $c[0];
                }
                $note = trim($note . "  " . implode(" | ", $opts));
                $status = "multiple (choose)";
            } else {
                $status = $current === "" ? "no CMT curation" : "clear?";
            }

            $rows[] = [
                "id"       => $id,
                "title"    => get_the_title($id),
                "symbol"   => $symbol,
                "n"        => count($curs),
                "cls"      => $cls,
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

        $c = self::curations();
        eic_admin_tool_open("ClinGen URL Builder");
        echo "<p>Sets <code>clingen_url</code> to the exact ClinGen CMT GCEP " .
            "gene-disease validity curation for each gene. Lookup carries " .
            count($c) . " curated genes.</p>";

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
            "Normalize symbols to HGNC-approved first (slower)</label></p>";
        echo '<button class="button button-primary">Dry run (no writes)</button>';
        echo "</form>";

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="commit">';
        echo '<p><label><input type="checkbox" name="hgnc" value="1"> Use HGNC-approved symbols</label></p>';
        echo "<p>Scope: <select name=\"scope\">" .
            '<option value="differs">Only records that differ or are empty</option>' .
            '<option value="empty">Only empty records</option>' .
            '<option value="all">All single-curation matches</option>' .
            "</select></p>";
        echo '<p><label><input type="checkbox" name="clear_none" value="1"> ' .
            "Clear clingen_url where the gene has no CMT curation</label></p>";
        echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' .
            "I have reviewed the dry run and want to write.</label></p>";
        echo '<button class="button button-primary">Commit</button>';
        echo "</form>";
        echo "<p><em>PMP22 and NEFL subtypes are resolved automatically via a " .
            "built-in override map of your confirmed per-subtype decisions " .
            "(CMT1E stays blank). Any other multi-curation gene would still be " .
            "flagged for manual choice.</em></p>";

        eic_admin_tool_close();
    }

    private static function do_dryrun(bool $hgnc): void
    {
        $rows = self::collect($hgnc);
        $counts = [
            "empty -> set" => 0, "differs -> fix" => 0, "ok" => 0,
            "multiple (choose)" => 0, "no CMT curation" => 0,
        ];
        echo "<h2>Dry run — " . count($rows) . " subtypes</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Subtype</th><th>Gene</th><th>Status</th><th>Class</th>" .
            "<th>Current</th><th>Proposed / options</th></tr></thead><tbody>";
        foreach ($rows as $r) {
            if (isset($counts[$r["status"]])) {
                $counts[$r["status"]]++;
            }
            $hi = in_array(
                $r["status"],
                ["empty -> set", "differs -> fix", "multiple (choose)"],
                true
            ) ? ' style="background:#fff3cd"' : "";
            $prop = $r["proposed"] !== "" ? $r["proposed"] : $r["note"];
            if ($prop === "") {
                $prop = "(none)";
            }
            echo "<tr{$hi}>";
            echo "<td><strong>" . esc_html($r["title"]) . "</strong></td>";
            echo "<td>" . esc_html($r["symbol"] !== "" ? $r["symbol"] : "—") . "</td>";
            echo "<td>" . esc_html($r["status"]) . "</td>";
            echo "<td>" . esc_html($r["cls"]) . "</td>";
            echo "<td><small>" . esc_html($r["current"] !== "" ? $r["current"] : "(empty)") . "</small></td>";
            echo "<td><small>" . esc_html($prop) . "</small></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p>To set: <strong>" . $counts["empty -> set"] . "</strong> empty, " .
            "<strong>" . $counts["differs -> fix"] . "</strong> differing, " .
            "<strong>" . $counts["ok"] . "</strong> already correct, " .
            "<strong>" . $counts["multiple (choose)"] . "</strong> multi-curation (manual), " .
            "<strong>" . $counts["no CMT curation"] . "</strong> no CMT curation.</p>";
    }

    private static function do_commit(bool $hgnc): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $scope = $_POST["scope"] ?? "differs";
        $clear_none = !empty($_POST["clear_none"]);

        $rows = self::collect($hgnc);
        $set = 0;
        $cleared = 0;
        $skipped_multi = 0;
        foreach ($rows as $r) {
            if ($r["status"] === "multiple (choose)") {
                $skipped_multi++;
                continue;
            }
            if ($r["proposed"] === "") {
                $override_blank = strpos($r["status"], "override blank") === 0;
                if ($override_blank) {
                    // Explicit decision to keep this subtype blank: enforce it.
                    if ($r["current"] !== "") {
                        update_field("field_clingen_url", "", $r["id"]);
                        $cleared++;
                    }
                } elseif ($clear_none && $r["current"] !== "") {
                    update_field("field_clingen_url", "", $r["id"]);
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
                $should = $r["current"] === "" || $r["current"] !== $r["proposed"];
            }
            if ($should) {
                update_field("field_clingen_url", $r["proposed"], $r["id"]);
                $set++;
            }
        }

        echo '<div class="notice notice-success"><p><strong>Done.</strong> ' .
            "Set " . $set . " URL(s)" .
            ($cleared ? ", cleared " . $cleared : "") .
            ", skipped " . $skipped_multi . " multi-curation record(s) for manual pick.</p></div>";
    }
}

EIC_ClinGen_URL_Tool::init();
