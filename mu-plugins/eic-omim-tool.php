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
 * MU Plugin: EIC OMIM Tool (buttons + bulk backfill)
 * ------------------------------------------------------------
 * Populates the two OMIM fields on Subtype records:
 *   - omim_subtype : phenotype MIM, from the curated Wix dataset
 *                    (subtype -> MIM lookup embedded below).
 *   - omim_gene    : gene MIM, from a live HGNC lookup (AJAX).
 *
 * Delivery:
 *   - Editor buttons "Get OMIM Subtype #" and "Get OMIM Gene #"
 *     injected under those fields (block + classic editor).
 *   - Bulk backfill under Tools > OMIM Backfill (dry-run/commit).
 *
 * Subtypes with no OMIM entry are left blank by design.
 *
 * Location: wp-content/mu-plugins/eic-omim-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_OMIM_Tool
{
    const CAP         = "manage_options";
    const NONCE_TOOL  = "eic_omim_tool";
    const NONCE_AJAX  = "eic_omim_ajax";
    const HGNC_OPTION = "eic_hgnc_cache";

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

    /** Subtype (uppercase) => phenotype OMIM number ("" = none). */
    public static function subtype_map(): array
    {
            // Subtype phenotype OMIM numbers from the curated Wix dataset (175 subtypes; "" = no OMIM entry). 2026-07-09.
            return [
                "CMTRID" => "616039",
                "CMT2V" => "616491",
                "CMT-SCO2" => "",
                "HSN-2A" => "201300",
                "HSN-2C" => "614213",
                "CMT2J" => "607736",
                "HSN-1B" => "608088",
                "CMTDIF" => "615185",
                "DSMAX-2" => "301830",
                "CMT-MCM3AP" => "618124",
                "CMT2FF" => "619519",
                "CMT2R" => "615490",
                "CMT-DGAT2" => "",
                "CMTX2" => "302801",
                "CMT2F" => "606595",
                "CMT2X" => "616668",
                "CMT1J" => "620111",
                "HSAN-1A" => "162400",
                "CMT2I" => "607677",
                "DHMN1-UBE3C" => "",
                "CMT4K" => "616684",
                "DHMN-2C" => "613376",
                "HSAN-4" => "256800",
                "CMT-MTRFR" => "",
                "DHMN-9" => "617721",
                "CMT-LRP12" => "",
                "CMT-NOTCH2NLC" => "",
                "HSAN-3" => "223900",
                "HSN-1C" => "613640",
                "CMT1B" => "118200",
                "CMT-CRYAB" => "",
                "CMT-C19ORF12" => "",
                "CMT-SARS1" => "",
                "HSAN-2B" => "613115",
                "CMT-COA7" => "618387",
                "CMT4H" => "609311",
                "CMTDIE" => "614455",
                "DHMN-5A" => "600794",
                "HMSN-OKINAWA TYPE" => "604484",
                "CMT2B1" => "605588",
                "SMA-LEP-2A" => "615290",
                "CMT2O" => "614228",
                "CMT2EE" => "618400",
                "CMT2A" => "609260",
                "CMT4A" => "214400",
                "CMTX1" => "302800",
                "CMT-KIF5A" => "",
                "CMT-SYT2" => "",
                "SMA-LEP-1" => "158600",
                "CMT4B3" => "615284",
                "HNPP" => "162500",
                "CMT2Y" => "616687",
                "CMT1D" => "607678",
                "CMT-CHCHD10" => "",
                "CMTDIG" => "617882",
                "CMT2II" => "620068",
                "HSN W/SPG" => "256840",
                "DSMA" => "",
                "CMT-DHX9" => "",
                "SMA-LEP-2B" => "618291",
                "CMT4J" => "611228",
                "CMT2CC" => "616924",
                "CMT2GG" => "606483",
                "CMT2B2" => "605589",
                "DSMAX-3" => "300489",
                "CMT-ARHGAP19" => "",
                "CMT2C" => "606071",
                "DHMN-MYH14" => "614369",
                "CMT-INSC" => "",
                "HSN-FLVCR1" => "",
                "DHMN-8" => "600175",
                "CMTX3" => "302802",
                "CMT2JJ" => "621095",
                "CMT2T" => "617017",
                "CMT2B" => "600882",
                "CMTDID" => "607791",
                "CMT-SCYL1" => "",
                "HSAN-6" => "614653",
                "CMTX5" => "311070",
                "CMTX4" => "310940",
                "HSAN-1B" => "608088",
                "GAN-2" => "610100",
                "CMT2Q" => "615025",
                "CMT1A" => "118220",
                "CMT-TUBB3" => "",
                "CMTRIC" => "615376",
                "CMT-POLG" => "",
                "DHMN-5B" => "614751",
                "CMT2N" => "613287",
                "CMT4C" => "601596",
                "HSAN-9" => "615031",
                "HSAN-2A" => "201300",
                "CMT2E" => "607684",
                "CMT-SORD" => "618912",
                "CMT2B3" => "",
                "HSAN-1C" => "613640",
                "CMT4B2" => "604563",
                "DHMN-1" => "182960",
                "CMT1I" => "619742",
                "CMT-SEPT9" => "",
                "CMT-ARHGEF10" => "608236",
                "CMT2B5" => "",
                "CMT2P" => "614436",
                "CMTDIC" => "608323",
                "HMSN-6B" => "616505",
                "HSAN-8" => "616488",
                "DHMN-2B" => "608634",
                "CMT2A2B" => "617097",
                "CMT4B1" => "601382",
                "CMT2W" => "616625",
                "CMT-DARS2" => "",
                "CMT-RFC1" => "614575",
                "CMT-COQ7" => "620042",
                "DSMA-4" => "611067",
                "HMSN-6A" => "601152",
                "DHMN-2A" => "158590",
                "DHMN-2D" => "615575",
                "CMT1C" => "601098",
                "GAN-1" => "256850",
                "CMTRIA" => "608340",
                "CMTDIB" => "606482",
                "CMT2S" => "616155",
                "HSAN-2D" => "243000",
                "CMT1G" => "618279",
                "CMTX6" => "300905",
                "DSMA-5" => "614881",
                "CMT2B4" => "",
                "CMT-DRP2" => "",
                "CMT-MYO9B" => "",
                "CMT-SACS" => "",
                "CMT1E" => "118300",
                "HMSN-5" => "600361",
                "DHMN-VWA1" => "619216",
                "CMT-NDUFS6" => "",
                "CMT2M" => "606482",
                "CMT1H" => "619764",
                "CMT-HADHB" => "",
                "CMT-SGPL1" => "",
                "CMT2HH" => "619574",
                "CMT-CTDP1" => "",
                "CMT-HINT1" => "137200",
                "CMTRIE" => "",
                "CMT2L" => "608673",
                "HSN-1E" => "614116",
                "CMT2Z" => "616688",
                "DHMN-5C" => "619112",
                "DHMN-7B" => "607641",
                "CMT-ABHD12" => "612674",
                "CMT-DST" => "",
                "DHMN-6" => "604320",
                "CMT4E" => "605253",
                "CMT4F" => "614895",
                "HMSN-6C" => "618511",
                "CMT-ATP6" => "",
                "DHMN-7A" => "158580",
                "CMT1F" => "607734",
                "HSAN-7" => "615548",
                "HSN-1A" => "162400",
                "CMT-NARS1" => "",
                "CMT4G" => "605285",
                "HSAN-5" => "608654",
                "DHMN2-SIGMAR1" => "605726",
                "CMT2D" => "601472",
                "CMT2K" => "607831",
                "HSN-1F" => "615632",
                "CMT-SETX" => "",
                "CMT-CFAP276" => "",
                "CMT2DD" => "618036",
                "CMT-CNTNAP1" => "618186",
                "DHMN-AARS1" => "",
                "HMSN-4" => "266500",
                "HSN-1D" => "613708",
                "CMT-PSAT1" => "",
                "CMT-NAMPT" => "",
                "CMT4D" => "601455",
            ];    }

    /** Gene OMIM via HGNC, cached (merges into the shared HGNC cache). */
    public static function hgnc_omim(string $symbol): string
    {
        $symbol = trim($symbol);
        if ($symbol === "") {
            return "";
        }
        $cache = self::cache_map();
        if (
            isset($cache[$symbol]) &&
            is_array($cache[$symbol]) &&
            array_key_exists("omim", $cache[$symbol])
        ) {
            return (string) ($cache[$symbol]["omim"] ?? "");
        }
        $resp = wp_remote_get(
            "https://rest.genenames.org/fetch/symbol/" . rawurlencode($symbol),
            ["timeout" => 15, "headers" => ["Accept" => "application/json"]]
        );
        if (
            is_wp_error($resp) ||
            wp_remote_retrieve_response_code($resp) !== 200
        ) {
            return "";
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $docs = $body["response"]["docs"] ?? [];
        $omim = (!empty($docs) && isset($docs[0]["omim_id"][0]))
            ? (string) $docs[0]["omim_id"][0]
            : "";
        $entry = isset($cache[$symbol]) && is_array($cache[$symbol]) ? $cache[$symbol] : [];
        $entry["omim"] = $omim;
        self::cache_put($symbol, $entry);
        return $omim;
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
        add_action("wp_ajax_eic_omim_gene", [__CLASS__, "ajax_gene"]);
        add_action("admin_enqueue_scripts", [__CLASS__, "enqueue"]);
    }

    public static function ajax_gene(): void
    {
        check_ajax_referer(self::NONCE_AJAX, "nonce");
        if (!current_user_can(self::CAP)) {
            wp_send_json_error("permission", 403);
        }
        $gene = isset($_POST["gene"])
            ? sanitize_text_field(wp_unslash($_POST["gene"]))
            : "";
        wp_send_json_success(["omim" => self::hgnc_omim($gene)]);
    }

    public static function menu(): void
    {
        add_management_page(
            "OMIM Backfill",
            "OMIM Backfill",
            self::CAP,
            "eic-omim-tool",
            [__CLASS__, "render"]
        );
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
            "subtypes" => self::subtype_map(),
            "ajaxurl"  => admin_url("admin-ajax.php"),
            "nonce"    => wp_create_nonce(self::NONCE_AJAX),
        ]);
        wp_register_script("eic-omim-buttons", false, [], null, true);
        wp_enqueue_script("eic-omim-buttons");
        wp_add_inline_script(
            "eic-omim-buttons",
            "window.EIC_OMIM = " . $data . ";\n" . self::js()
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
    var defs = [
      ['omim_subtype', 'subtype', 'Get OMIM Subtype #'],
      ['omim_gene', 'gene', 'Get OMIM Gene #']
    ];
    defs.forEach(function (cfg) {
      var wrap = document.querySelector(
        '.acf-field[data-name="' + cfg[0] + '"] .acf-input'
      );
      if (!wrap || wrap.querySelector('.eic-omim-btn')) { return; }
      var p = document.createElement('p');
      p.style.margin = '6px 0 0';
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'button eic-omim-btn';
      b.setAttribute('data-omim', cfg[1]);
      b.textContent = cfg[2];
      p.appendChild(b);
      wrap.appendChild(p);
    });
  }
  function onClick(e) {
    var b = e.target && e.target.closest ? e.target.closest('.eic-omim-btn') : null;
    if (!b) { return; }
    e.preventDefault();
    var d = window.EIC_OMIM || {};
    var kind = b.getAttribute('data-omim');

    if (kind === 'subtype') {
      var sub = val('subtype');
      var map = d.subtypes || {};
      var key = (sub || '').toUpperCase();
      if (!Object.prototype.hasOwnProperty.call(map, key)) {
        alert('Subtype "' + sub + '" is not in the OMIM dataset.');
        return;
      }
      if (map[key] === '') {
        alert('No OMIM subtype number on record for ' + sub + '.');
        return;
      }
      setVal('omim_subtype', map[key]);
      return;
    }

    var gene = val('gene_symbol');
    if (!gene) { alert('No gene symbol on this record.'); return; }
    var old = b.textContent;
    b.disabled = true;
    b.textContent = 'Looking up...';
    fetch(d.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'eic_omim_gene', nonce: d.nonce, gene: gene })
    }).then(function (r) { return r.json(); })
      .then(function (j) {
        b.disabled = false; b.textContent = old;
        if (j && j.success && j.data && j.data.omim) {
          setVal('omim_gene', j.data.omim);
        } else {
          alert('No gene OMIM number found for ' + gene + '.');
        }
      }).catch(function () {
        b.disabled = false; b.textContent = old;
        alert('OMIM lookup failed.');
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

    private static function collect(bool $do_sub, bool $do_gene): array
    {
        $map = self::subtype_map();
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
            $sub = trim((string) (get_field("subtype", $id) ?: get_the_title($id)));
            $gene = trim((string) get_field("gene_symbol", $id));
            $unknown = (bool) get_field("unknown_gene", $id);
            $rows[] = [
                "id"        => $id,
                "sub"       => $sub,
                "gene"      => $gene,
                "cur_sub"   => trim((string) get_field("omim_subtype", $id)),
                "prop_sub"  => $do_sub ? ($map[strtoupper($sub)] ?? "") : "",
                "cur_gene"  => trim((string) get_field("omim_gene", $id)),
                "prop_gene" => ($do_gene && !$unknown && $gene !== "")
                    ? self::hgnc_omim($gene)
                    : "",
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
        eic_admin_tool_open("OMIM Backfill");
        echo "<p>Subtype numbers from the curated dataset; gene numbers from a " .
            "live HGNC lookup. Subtypes with no OMIM entry stay blank.</p>";

        $action = $_POST["eic_action"] ?? "";
        $do_sub = !isset($_POST["submitted"]) || !empty($_POST["do_sub"]);
        $do_gene = !isset($_POST["submitted"]) || !empty($_POST["do_gene"]);
        $scope = $_POST["scope"] ?? "differs";

        if ($action && check_admin_referer(self::NONCE_TOOL)) {
            if ($action === "dryrun") {
                self::do_dryrun($do_sub, $do_gene, $scope);
            } elseif ($action === "commit") {
                self::do_commit($do_sub, $do_gene, $scope);
            }
        }

        $opts =
            '<p><label><input type="checkbox" name="do_sub" value="1" checked> Subtype number (dataset)</label> &nbsp; ' .
            '<label><input type="checkbox" name="do_gene" value="1" checked> Gene number (HGNC)</label></p>' .
            '<input type="hidden" name="submitted" value="1">';

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE_TOOL);
        echo '<input type="hidden" name="eic_action" value="dryrun">' . $opts;
        echo '<button class="button button-primary">Dry run (no writes)</button></form>';

        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE_TOOL);
        echo '<input type="hidden" name="eic_action" value="commit">' . $opts;
        echo '<p>Scope: <select name="scope">' .
            '<option value="differs">Only fields that differ or are empty</option>' .
            '<option value="empty">Only empty fields</option>' .
            '<option value="all">Overwrite all</option></select></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1" required> I have reviewed the dry run and want to write.</label></p>';
        echo '<button class="button button-primary">Commit</button></form>';

        eic_admin_tool_close();
    }

    private static function do_dryrun(bool $do_sub, bool $do_gene, string $scope): void
    {
        $rows = self::collect($do_sub, $do_gene);
        $set_sub = 0;
        $set_gene = 0;
        echo "<h2>Dry run: " . count($rows) . " subtypes</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Subtype</th><th>Gene</th><th>OMIM subtype</th><th>OMIM gene</th>" .
            "</tr></thead><tbody>";
        foreach ($rows as $r) {
            // Mirror do_commit(): gate on the same want()/scope logic
            // the commit uses, or the preview counts will not match.
            $sub_change = $do_sub && self::want($scope, $r["cur_sub"], $r["prop_sub"]);
            $gene_change = $do_gene && self::want($scope, $r["cur_gene"], $r["prop_gene"]);
            if ($sub_change) { $set_sub++; }
            if ($gene_change) { $set_gene++; }
            $hi = ($sub_change || $gene_change) ? ' style="background:#fff3cd"' : "";
            $subcell = $r["prop_sub"] === ""
                ? esc_html($r["cur_sub"] !== "" ? $r["cur_sub"] : "(none)")
                : esc_html($r["cur_sub"] !== "" ? $r["cur_sub"] : "(empty)") . " &rarr; " . esc_html($r["prop_sub"]);
            $genecell = $r["prop_gene"] === ""
                ? esc_html($r["cur_gene"] !== "" ? $r["cur_gene"] : "(none)")
                : esc_html($r["cur_gene"] !== "" ? $r["cur_gene"] : "(empty)") . " &rarr; " . esc_html($r["prop_gene"]);
            echo "<tr{$hi}>";
            echo "<td><strong>" . esc_html($r["sub"]) . "</strong></td>";
            echo "<td>" . esc_html($r["gene"] !== "" ? $r["gene"] : "—") . "</td>";
            echo "<td>" . $subcell . "</td>";
            echo "<td>" . $genecell . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p>To set: <strong>" . $set_sub . "</strong> subtype number(s), " .
            "<strong>" . $set_gene . "</strong> gene number(s).</p>";
    }

    private static function do_commit(bool $do_sub, bool $do_gene, string $scope): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $rows = self::collect($do_sub, $do_gene);
        $set_sub = 0;
        $set_gene = 0;
        foreach ($rows as $r) {
            if ($do_sub && self::want($scope, $r["cur_sub"], $r["prop_sub"])) {
                update_field("field_omim_subtype", $r["prop_sub"], $r["id"]);
                $set_sub++;
            }
            if ($do_gene && self::want($scope, $r["cur_gene"], $r["prop_gene"])) {
                update_field("field_omim_gene", $r["prop_gene"], $r["id"]);
                $set_gene++;
            }
        }
        echo '<div class="notice notice-success"><p><strong>Done.</strong> ' .
            "Set " . $set_sub . " subtype number(s), " . $set_gene .
            " gene number(s).</p></div>";
    }
}

EIC_OMIM_Tool::init();
