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
 * MU Plugin: EIC HGNC Identifiers Tool (button + bulk backfill)
 * ------------------------------------------------------------
 * Fills the gene-level identifier fields on a Subtype record from
 * a single live HGNC lookup keyed on `gene_symbol`:
 *   full_gene_name, hgnc_id, ensembl_gene_id, entrez_id,
 *   omim_gene, uniprot_id, refseq_accession,
 *   mane_select_refseq, mane_select_ensembl, chromosome
 *
 * Delivery (mirrors eic-omim-tool.php):
 *   - Editor button "Get identifiers from HGNC" injected under the
 *     gene_symbol field (block + classic editor).
 *   - Bulk backfill under Tools > HGNC Identifiers (dry-run/commit).
 *
 * Guard: only an Approved HGNC gene record is accepted, so a subtype
 * code that resolves to a withdrawn entry is rejected (returns empty).
 * A structural record (CMTX3) has no HGNC gene and is skipped safely.
 *
 * Location: wp-content/mu-plugins/eic-hgnc-identifiers-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_HGNC_Identifiers_Tool
{
    const CAP        = "manage_options";
    const NONCE_TOOL = "eic_hgnc_ids_tool";
    const NONCE_AJAX = "eic_hgnc_ids_ajax";
    const CACHE      = "eic_hgnc_idmap_cache";

    /**
     * Max live (uncached) gene lookups per bulk run. A cold-cache backfill of the
     * whole store fires up to 4 remote calls per gene, which can exceed PHP's
     * max_execution_time and die mid-run. Capping the live lookups per run keeps
     * each run bounded; results persist to the cache, so re-running resumes where
     * the previous run stopped.
     */
    const BATCH = 40;

    /**
     * In-request cache memo. The bulk backfill calls fetch() once per gene; the
     * cache option was previously read and re-written on every miss (O(n^2) I/O).
     * Memoize the option in-process and defer a single write to shutdown.
     */
    private static $cache_mem = null;
    private static $cache_dirty = false;
    private static $last_deferred = 0;

    private static function cache_map(): array
    {
        if (self::$cache_mem === null) {
            $c = get_option(self::CACHE, []);
            self::$cache_mem = is_array($c) ? $c : [];
        }
        return self::$cache_mem;
    }

    private static function cache_put(string $symbol, array $map): void
    {
        self::cache_map();
        self::$cache_mem[$symbol] = $map;
        if (!self::$cache_dirty) {
            self::$cache_dirty = true;
            add_action("shutdown", [__CLASS__, "flush_cache"]);
        }
    }

    public static function flush_cache(): void
    {
        if (self::$cache_dirty) {
            update_option(self::CACHE, self::$cache_mem, false);
            self::$cache_dirty = false;
        }
    }

    /** ACF field name => field key, for update_field on commit. */
    private static function keys(): array
    {
        return [
            "full_gene_name"      => "field_full_gene_name",
            "hgnc_id"             => "field_hgnc_id",
            "ensembl_gene_id"     => "field_ensembl_gene_id",
            "entrez_id"           => "field_entrez_id",
            "omim_gene"           => "field_omim_gene",
            "uniprot_id"          => "field_uniprot_id",
            "refseq_accession"    => "field_refseq_accession",
            "mane_select_refseq"  => "field_mane_select_refseq",
            "mane_select_ensembl" => "field_mane_select_ensembl",
            "chromosome"          => "field_chromosome",
            "gene_function"       => "field_gene_function",
            "coords_grch38"       => "field_coords_grch38",
            "coords_grch37"       => "field_coords_grch37",
        ];
    }

    /**
     * Genomic coordinates for both assemblies from Ensembl REST, keyed on the
     * gene symbol: GRCh38 from rest.ensembl.org, GRCh37 from the grch37 archive.
     * Returns "chr{n}:{start}-{end}" per build, or "" for a build that does not
     * resolve (left blank, never guessed). A handful of renamed genes (e.g.
     * GARS1) carry a legacy-symbol fallback so the GRCh37 archive still resolves.
     */
    private static function ensembl_coords(string $symbol): array
    {
        return [
            "coords_grch38" => self::ensembl_one("https://rest.ensembl.org", $symbol),
            "coords_grch37" => self::ensembl_one("https://grch37.rest.ensembl.org", $symbol),
        ];
    }

    private static function ensembl_one(string $base, string $symbol): string
    {
        foreach (self::ensembl_symbols($symbol) as $s) {
            $resp = wp_remote_get(
                $base . "/lookup/symbol/homo_sapiens/" . rawurlencode($s) . "?content-type=application/json",
                ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
            );
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
                continue;
            }
            $b = json_decode(wp_remote_retrieve_body($resp), true);
            $chr = trim((string) ($b["seq_region_name"] ?? ""));
            $start = isset($b["start"]) ? (int) $b["start"] : 0;
            $end = isset($b["end"]) ? (int) $b["end"] : 0;
            if ($chr !== "" && $start > 0 && $end > 0) {
                return "chr" . $chr . ":" . $start . "-" . $end;
            }
        }
        return "";
    }

    /** Current symbol first, then a legacy alias for GRCh37-renamed genes. */
    private static function ensembl_symbols(string $symbol): array
    {
        $legacy = [
            "AARS1" => "AARS", "GARS1" => "GARS", "HARS1" => "HARS",
            "YARS1" => "YARS", "WARS1" => "WARS", "MARS1" => "MARS",
            "KARS1" => "KARS", "SARS1" => "SARS", "NARS1" => "NARS",
            "DARS1" => "DARS", "SEPTIN9" => "SEPT9", "RETREG1" => "FAM134B",
        ];
        $out = [$symbol];
        if (isset($legacy[$symbol])) {
            $out[] = $legacy[$symbol];
        }
        return $out;
    }

    /** Map an HGNC response doc into our field name => value shape. */
    private static function map_doc(array $d): array
    {
        $mane_refseq  = "";
        $mane_ensembl = "";
        foreach ((array) ($d["mane_select"] ?? []) as $m) {
            if (strpos((string) $m, "ENST") === 0) {
                $mane_ensembl = (string) $m;
            } elseif (preg_match('/^N[MR]_/', (string) $m)) {
                $mane_refseq = (string) $m;
            }
        }
        return [
            "full_gene_name"      => (string) ($d["name"] ?? ""),
            "hgnc_id"             => (string) ($d["hgnc_id"] ?? ""),
            "ensembl_gene_id"     => (string) ($d["ensembl_gene_id"] ?? ""),
            "entrez_id"           => (string) ($d["entrez_id"] ?? ""),
            "omim_gene"           => (string) ($d["omim_id"][0] ?? ""),
            "uniprot_id"          => (string) ($d["uniprot_ids"][0] ?? ""),
            "refseq_accession"    => (string) ($d["refseq_accession"][0] ?? ""),
            "mane_select_refseq"  => $mane_refseq,
            "mane_select_ensembl" => $mane_ensembl,
            // HGNC "location" is the cytogenetic band (e.g. 16q22.1); it is the
            // authoritative source for the manually-entered Chromosome field.
            "chromosome"          => self::hgnc_location($d),
        ];
    }

    /**
     * HGNC cytogenetic location for the Chromosome field, or "" to skip.
     * mt-tRNA genes report "mitochondria" (not a band); returning "" means the
     * button skips it and the backfill's want() never writes it, so a
     * manually-entered MT value is left untouched.
     */
    private static function hgnc_location(array $d): string
    {
        $loc = trim((string) ($d["location"] ?? ""));
        if ($loc === "" || stripos($loc, "mitochond") !== false) {
            return "";
        }
        return $loc;
    }

    /**
     * UniProt FUNCTION comment for a UniProtKB accession, or "" on any miss.
     * A second lookup (rest.uniprot.org) keyed on the accession HGNC returned.
     */
    private static function uniprot_function(string $acc): string
    {
        $acc = trim($acc);
        if ($acc === "") {
            return "";
        }
        $resp = wp_remote_get(
            "https://rest.uniprot.org/uniprotkb/" . rawurlencode($acc) . ".json?fields=cc_function",
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (
            is_wp_error($resp) ||
            wp_remote_retrieve_response_code($resp) !== 200
        ) {
            return "";
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        foreach ((array) ($body["comments"] ?? []) as $c) {
            if (($c["commentType"] ?? "") === "FUNCTION") {
                $val = trim((string) ($c["texts"][0]["value"] ?? ""));
                if ($val !== "") {
                    return $val;
                }
            }
        }
        return "";
    }

    /**
     * Normalize a UniProt function summary for display: drop inline PubMed
     * citation groups (non-actionable plain text here; provenance is the
     * Source -> UniProt link) and collapse the leftover spacing. Evidence
     * qualifiers like "(By similarity)" are preserved. Idempotent, so it is
     * safe on already-cleaned or already-cached values.
     */
    private static function clean_function(string $s): string
    {
        if ($s === "") {
            return "";
        }
        $s = preg_replace('/\s*\(PubMed:\d+(?:,\s*PubMed:\d+)*\)/', "", $s);
        return trim(preg_replace('/\s{2,}/', " ", (string) $s));
    }

    /** Cached HGNC map for a symbol, or null on a cache miss (no remote call). */
    private static function fetch_cached(string $symbol): ?array
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return null;
        }
        $cache = self::cache_map();
        // Use the cached map only if it carries every field currently in keys().
        // When a field is added (e.g. chromosome), older cached entries lack that
        // key, so they are treated as stale and re-fetched. Self-heals the cache
        // on any field-set change, with no manual bump or orphaned option.
        if (
            isset($cache[$symbol]) &&
            is_array($cache[$symbol]) &&
            !array_diff_key(self::keys(), $cache[$symbol])
        ) {
            $hit = $cache[$symbol];
            // Clean at read time so already-cached function text (fetched before
            // the PubMed-strip rule existed) is normalized without a re-fetch.
            $hit["gene_function"] = self::clean_function($hit["gene_function"] ?? "");
            return $hit;
        }
        return null;
    }

    /** Live HGNC fetch keyed on symbol, cached. Empty array on any miss. */
    public static function fetch(string $symbol): array
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return [];
        }
        $cached = self::fetch_cached($symbol);
        if ($cached !== null) {
            return $cached;
        }
        $resp = wp_remote_get(
            "https://rest.genenames.org/fetch/symbol/" . rawurlencode($symbol),
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (
            is_wp_error($resp) ||
            wp_remote_retrieve_response_code($resp) !== 200
        ) {
            return [];
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $docs = $body["response"]["docs"] ?? [];
        if (empty($docs) || !is_array($docs[0])) {
            return [];
        }
        $d = $docs[0];
        // Withdrawn-entry guard: only an Approved gene record is accepted.
        if (($d["status"] ?? "") !== "Approved") {
            return [];
        }
        $map = self::map_doc($d);
        // Function summary comes from a second source (UniProt), keyed on the
        // UniProt accession HGNC just returned. Empty when there is no accession
        // (e.g. structural records) or no FUNCTION comment on the entry.
        $map["gene_function"] = self::clean_function(
            self::uniprot_function($map["uniprot_id"] ?? "")
        );
        // Genomic coordinates for both assemblies from Ensembl REST.
        $map = array_merge($map, self::ensembl_coords($symbol));
        self::cache_put($symbol, $map);
        return $map;
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
        add_action("wp_ajax_eic_hgnc_ids", [__CLASS__, "ajax"]);
        add_action("admin_enqueue_scripts", [__CLASS__, "enqueue"]);
    }

    public static function ajax(): void
    {
        check_ajax_referer(self::NONCE_AJAX, "nonce");
        if (!current_user_can(self::CAP)) {
            wp_send_json_error("permission", 403);
        }
        $gene = isset($_POST["gene"])
            ? sanitize_text_field(wp_unslash($_POST["gene"]))
            : "";
        $map = self::fetch($gene);
        if (empty($map)) {
            wp_send_json_error("not_found");
        }
        wp_send_json_success($map);
    }

    public static function enqueue($hook): void
    {
        if (!in_array($hook, ["post.php", "post-new.php"], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== "subtype") {
            return;
        }
        $data = wp_json_encode([
            "ajaxurl" => admin_url("admin-ajax.php"),
            "nonce"   => wp_create_nonce(self::NONCE_AJAX),
            "fields"  => array_keys(self::keys()),
        ]);
        wp_register_script("eic-hgnc-ids", false, [], null, true);
        wp_enqueue_script("eic-hgnc-ids");
        wp_add_inline_script(
            "eic-hgnc-ids",
            "window.EIC_HGNC = " . $data . ";\n" . self::js()
        );
    }

    private static function js(): string
    {
        return <<<'JS'
(function () {
  function fieldEl(name) {
    return document.querySelector(
      '.acf-field[data-name="' + name + '"] input, ' +
      '.acf-field[data-name="' + name + '"] textarea'
    );
  }
  function val(name) {
    var el = fieldEl(name);
    return el ? (el.value || '').trim() : '';
  }
  function setVal(name, v) {
    var el = fieldEl(name);
    if (!el) { return false; }
    el.value = v;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }
  function inject() {
    var wrap = document.querySelector('.acf-field[data-name="gene_symbol"] .acf-input');
    if (!wrap || wrap.querySelector('.eic-hgnc-btn')) { return; }
    var p = document.createElement('p');
    p.style.margin = '6px 0 0';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'button eic-hgnc-btn';
    b.textContent = 'Get identifiers from HGNC';
    var s = document.createElement('span');
    s.className = 'eic-hgnc-status';
    s.style.marginLeft = '8px';
    s.style.color = '#6b7480';
    p.appendChild(b);
    p.appendChild(s);
    wrap.appendChild(p);
  }
  function onClick(e) {
    var b = e.target && e.target.closest ? e.target.closest('.eic-hgnc-btn') : null;
    if (!b) { return; }
    e.preventDefault();
    var d = window.EIC_HGNC || {};
    var gene = val('gene_symbol');
    if (!gene) { alert('No gene symbol on this record.'); return; }
    var status = b.parentNode.querySelector('.eic-hgnc-status');
    var old = b.textContent;
    b.disabled = true;
    b.textContent = 'Looking up...';
    if (status) { status.textContent = ''; }
    fetch(d.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'eic_hgnc_ids', nonce: d.nonce, gene: gene })
    }).then(function (r) { return r.json(); })
      .then(function (j) {
        b.disabled = false; b.textContent = old;
        if (!j || !j.success || !j.data) {
          if (status) { status.textContent = 'No Approved HGNC record for ' + gene + '.'; }
          return;
        }
        var set = 0;
        (d.fields || []).forEach(function (name) {
          if (Object.prototype.hasOwnProperty.call(j.data, name) && j.data[name] !== '') {
            if (setVal(name, j.data[name])) { set++; }
          }
        });
        if (status) { status.textContent = 'Filled ' + set + ' field(s) from HGNC. Review, then Update to save.'; }
        var tab = Array.prototype.slice.call(document.querySelectorAll('.acf-tab-button'))
          .filter(function (a) { return a.textContent.trim() === 'Identifiers'; })[0];
        if (tab) { tab.click(); }
      }).catch(function () {
        b.disabled = false; b.textContent = old;
        if (status) { status.textContent = 'HGNC lookup failed.'; }
      });
  }
  document.addEventListener('click', onClick);
  document.addEventListener('DOMContentLoaded', inject);
  if (window.acf && typeof acf.addAction === 'function') {
    acf.addAction('ready', inject);
    acf.addAction('append', inject);
  }
  var t = 0;
  var iv = setInterval(function () { inject(); if (++t > 20) { clearInterval(iv); } }, 300);
})();
JS;
    }

    /* ---- Bulk backfill ---- */

    public static function menu(): void
    {
        add_management_page(
            "HGNC Identifiers Backfill",
            "HGNC Identifiers Backfill",
            self::CAP,
            "eic-hgnc-ids-tool",
            [__CLASS__, "render"]
        );
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
        $names = array_keys(self::keys());
        $rows = [];
        $budget = self::BATCH;
        self::$last_deferred = 0;
        foreach ($q->posts as $id) {
            $gene = trim((string) get_field("gene_symbol", $id));
            $unknown = (bool) get_field("unknown_gene", $id);
            $cur = [];
            foreach ($names as $n) {
                $cur[$n] = trim((string) get_field($n, $id));
            }
            // Cache-first: an already-cached gene is free. Spend the per-run
            // live-lookup budget only on cache misses; once it is exhausted, defer
            // the rest to the next run rather than risk a mid-run PHP timeout.
            $prop = [];
            $deferred = false;
            if (!$unknown && $gene !== "") {
                $cached = self::fetch_cached($gene);
                if ($cached !== null) {
                    $prop = $cached;
                } elseif ($budget > 0) {
                    $budget--;
                    $prop = self::fetch($gene);
                } else {
                    $deferred = true;
                    self::$last_deferred++;
                }
            }
            // A miss counts as an error only when the symbol looks like a real gene
            // symbol, so a structural record (CMTX3's ISCN string) is skipped, not flagged.
            $plausible = (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $gene);
            $rows[] = [
                "id"       => $id,
                "title"    => get_the_title($id),
                "gene"     => $gene,
                "unknown"  => $unknown,
                "cur"      => $cur,
                "prop"     => $prop,
                "deferred" => $deferred,
                "error"    => (!$unknown && $gene !== "" && $plausible && empty($prop) && !$deferred),
            ];
        }
        return $rows;
    }

    private static function want(string $scope, string $cur, string $prop): bool
    {
        if ($prop === "") {
            return false;
        }
        if ($scope === "all") {
            return true;
        }
        if ($scope === "empty") {
            return $cur === "";
        }
        return $cur === "" || $cur !== $prop; // differs
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        eic_admin_tool_open("HGNC Identifiers Backfill");
        echo "<p>Fills the gene-level identifier fields from a live HGNC lookup on " .
            "<code>gene_symbol</code>. Only Approved gene records are accepted; " .
            "unknown-gene and structural records are skipped.</p>";

        $action = $_POST["eic_action"] ?? "";
        $scope  = $_POST["scope"] ?? "differs";

        if ($action && check_admin_referer(self::NONCE_TOOL)) {
            if ($action === "dryrun") {
                self::run(false, $scope);
            } elseif ($action === "commit") {
                self::run(true, $scope);
            }
        }

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE_TOOL);
        echo '<input type="hidden" name="eic_action" value="dryrun">';
        echo '<button class="button button-primary">Dry run (no writes)</button></form>';

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE_TOOL);
        echo '<input type="hidden" name="eic_action" value="commit">';
        echo '<p>Scope: <select name="scope">' .
            '<option value="differs">Only fields that differ or are empty</option>' .
            '<option value="empty">Only empty fields</option>' .
            '<option value="all">Overwrite all</option></select></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1" required> I have reviewed the dry run and want to write.</label></p>';
        echo '<button class="button button-primary">Commit</button></form>';

        eic_admin_tool_close();
    }

    private static function run(bool $commit, string $scope): void
    {
        if ($commit && empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $rows  = self::collect();
        $keys  = self::keys();
        $names = array_keys($keys);
        $changed_rows = 0;
        $changed_fields = 0;
        $error_rows = [];

        echo "<h2>" . ($commit ? "Commit" : "Dry run") . ": " . count($rows) . " subtypes</h2>";
        if (self::$last_deferred > 0) {
            echo '<div class="notice notice-warning"><p><strong>Cold cache: batch limit reached.</strong> ' .
                (int) self::$last_deferred . " gene(s) were not looked up this run (live-lookup cap of " .
                (int) self::BATCH . " per run). Cached results persist, so run the tool again to fetch the next batch.</p></div>";
        }
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Subtype</th><th>Gene</th><th>Changes</th></tr></thead><tbody>";

        foreach ($rows as $r) {
            $diffs = [];
            foreach ($names as $n) {
                $cur  = $r["cur"][$n] ?? "";
                $prop = $r["prop"][$n] ?? "";
                if (self::want($scope, $cur, $prop)) {
                    $diffs[$n] = [$cur, $prop];
                }
            }
            if (!empty($r["error"])) {
                $error_rows[] = $r;
            }
            if (empty($diffs)) {
                continue;
            }
            $changed_rows++;
            $cells = [];
            foreach ($diffs as $n => $pair) {
                $changed_fields++;
                if ($commit) {
                    update_field($keys[$n], $pair[1], $r["id"]);
                }
                $cells[] = "<code>" . esc_html($n) . "</code>: " .
                    esc_html($pair[0] !== "" ? $pair[0] : "(empty)") . " &rarr; " .
                    "<strong>" . esc_html($pair[1]) . "</strong>";
            }
            echo '<tr style="background:#fff3cd">';
            echo "<td><strong>" . esc_html($r["title"]) . "</strong></td>";
            echo "<td>" . esc_html($r["gene"] !== "" ? $r["gene"] : "—") . "</td>";
            echo "<td>" . implode("<br>", $cells) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";

        $verb = $commit ? "Wrote" : "Would write";
        $err = count($error_rows);
        echo "<p>{$verb} <strong>{$changed_fields}</strong> field(s) across " .
            "<strong>{$changed_rows}</strong> subtype(s). " .
            "<strong>{$err}</strong> lookup error(s).</p>";

        // Coverage metrics: gene-level effective state (proposed value where the
        // source has one, else the stored value) after this run.
        $eff = [];
        foreach ($rows as $r) {
            $sym = $r["gene"];
            if ($sym === "" || $r["unknown"]) {
                continue;
            }
            if (!isset($eff[$sym])) {
                $eff[$sym] = [];
            }
            foreach ($names as $n) {
                if (!empty($eff[$sym][$n])) {
                    continue;
                }
                $prop = $r["prop"][$n] ?? "";
                $val = $prop !== "" ? $prop : ($r["cur"][$n] ?? "");
                if ($val !== "") {
                    $eff[$sym][$n] = $val;
                }
            }
        }
        $labels = [
            "full_gene_name"      => "Full name",
            "hgnc_id"             => "HGNC ID",
            "ensembl_gene_id"     => "Ensembl gene",
            "coords_grch38"       => "Coordinates GRCh38",
            "coords_grch37"       => "Coordinates GRCh37",
            "entrez_id"           => "Entrez",
            "omim_gene"           => "OMIM gene",
            "uniprot_id"          => "UniProt",
            "refseq_accession"    => "RefSeq",
            "mane_select_refseq"  => "MANE RefSeq",
            "mane_select_ensembl" => "MANE Ensembl",
            "chromosome"          => "Cytoband",
            "gene_function"       => "Gene function",
        ];
        $gene_total = count($eff);
        echo '<h3 style="margin-top:1.5em">Coverage metrics</h3>';
        echo '<table class="widefat striped" style="max-width:520px"><tbody>';
        echo "<tr><td><strong>Genes</strong></td><td><strong>" . (int) $gene_total .
            "</strong></td></tr>";
        foreach ($labels as $n => $lab) {
            $c = 0;
            foreach ($eff as $m) {
                if (!empty($m[$n])) {
                    $c++;
                }
            }
            echo "<tr><td>" . esc_html($lab) . "</td><td>" . (int) $c . " of " .
                (int) $gene_total . " genes</td></tr>";
        }
        echo "</tbody></table>";
        if ($err) {
            echo '<div class="notice notice-error"><p><strong>' . (int) $err .
                ' gene symbol(s) returned no Approved HGNC record</strong> and were skipped:</p>';
            echo '<table class="widefat striped"><thead><tr><th>Subtype</th><th>Gene</th></tr></thead><tbody>';
            foreach ($error_rows as $er) {
                echo "<tr><td>" . esc_html($er["title"]) . "</td><td><code>" .
                    esc_html($er["gene"]) . "</code></td></tr>";
            }
            echo "</tbody></table></div>";
        }
        if ($commit) {
            echo '<div class="notice notice-success"><p><strong>Done.</strong></p></div>';
        }
    }
}

EIC_HGNC_Identifiers_Tool::init();
