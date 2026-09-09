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
 * MU Plugin: EIC GENESIS Discovery Backfill
 * ------------------------------------------------------------
 * Flags genes whose CMT disease-gene relationship was discovered
 * or supported through the GENESIS platform (Genesis Project
 * Foundation), sourced from TGP's public discoveries list
 * (tgp-foundation.org/d-i-s-c-o-v-e-r-i-e-s). Sets the gene-level
 * ACF `genesis_discovery` true/false on matching subtype records;
 * the Gene Browser surfaces a "GENESIS discovery" chip in the
 * External Records row.
 *
 * Non-destructive and re-runnable: sets the flag on matched
 * records that don't already carry it, never unsets a non-listed
 * gene. When TGP adds discoveries, update EIC_GENESIS_GENES below
 * and re-run.
 *
 * Location: wp-content/mu-plugins/eic-genesis-discovery-backfill.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Genesis_Discovery_Backfill
{
    const CAP = "manage_options";
    const NONCE = "eic_genesis_backfill";
    const FIELD = "field_genesis_discovery";

    /**
     * Gene symbols from the TGP GENESIS discoveries list, HGNC-normalized
     * (bare synthetases carry the "1" suffix: WARS -> WARS1, etc.). Refresh
     * from tgp-foundation.org/d-i-s-c-o-v-e-r-i-e-s as they publish new ones.
     */
    private static function genes(): array
    {
        $list = [
            "SPTAN1", "FICD", "PRDX3", "FGF14", "ATP1A1", "KPNA3", "MYO9B",
            "RFC1", "HPDL", "CADM3", "TSG101", "SARM1", "PCYT2", "ATG7",
            "PLEKHG5", "FBLN5", "TACO1", "DYST", "GBF1", "SORD", "UGP2",
            "UGDH", "RNF170", "UBAP1", "SIPA1L2", "GDAP2", "SCO2", "CHP1",
            "TBK1", "STUB1", "TIA1", "BAG3", "P4HA1", "POLR3A", "DST",
            "CNTNAP1", "WARS1", "ATP13A2", "TYROBP", "SIGMAR1", "MME",
            "CMTX3", "PMP2", "SLC39A14", "ROR1", "SYNE1", "NEFH", "MORC2",
            "TTC3", "ZFP106", "SCYL1", "LIMS2", "TPM3", "DRP2", "ALDH18A1",
            "SLC25A46", "NAGLU", "KCNA2", "DNAJC3", "IGHMBP2", "SYT2", "VCP",
            "FAM65B", "ACTG2", "SIX6", "REEP2", "WWOX", "RFVT2", "PNPLA6",
            "IGSF3", "FBXO38", "MARS1", "DDHD2", "C19ORF12", "DNASE1L3",
            "B4GALNT1", "BICD2", "SLITRK6", "PDK3", "GBA2", "SZT2", "HARS1",
            "DDHD1", "CYP2U1", "HINT1", "RTN2", "AARS1", "DNAJC5", "ANKRD11",
        ];
        return array_fill_keys(array_map("strtoupper", $list), true);
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "GENESIS Discovery Backfill",
            "GENESIS Discovery Backfill",
            self::CAP,
            "eic-genesis-backfill",
            [__CLASS__, "render"]
        );
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        eic_admin_tool_open("GENESIS Discovery Backfill");
        echo "<p>Flags genes on the " .
            "<a href='https://www.tgp-foundation.org/d-i-s-c-o-v-e-r-i-e-s' target='_blank' rel='noopener'>TGP GENESIS discoveries list</a> " .
            "with <code>genesis_discovery = true</code>, surfaced as a chip in the Gene Browser. " .
            "Sets the flag on matching records that do not already carry it; never unsets a " .
            "non-listed gene. Re-runnable: refresh the embedded list when TGP adds discoveries, then re-run.</p>";

        $action = $_POST["eic_action"] ?? "";
        if ($action && check_admin_referer(self::NONCE)) {
            self::run($action === "commit");
        }

        echo '<hr><form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write.</label></p>';
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
        $genes = self::genes();
        $q = new WP_Query([
            "post_type" => "subtype",
            "post_status" => "any",
            "posts_per_page" => -1,
            "no_found_rows" => true,
        ]);
        $set = 0;
        $already = 0;
        $seen = [];

        echo "<h2>" . ($commit ? "Commit" : "Dry run") .
            ": scanning " . count($q->posts) . " records against " .
            count($genes) . " GENESIS genes</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Gene</th><th>Record</th><th>Status</th></tr></thead><tbody>";

        foreach ($q->posts as $post) {
            $id = (int) $post->ID;
            $sym = strtoupper(trim((string) get_field("gene_symbol", $id)));
            if ($sym === "" || !isset($genes[$sym])) {
                continue;
            }
            $seen[$sym] = true;
            if ((bool) get_field("genesis_discovery", $id)) {
                $already++;
                continue;
            }
            if ($commit) {
                update_field(self::FIELD, 1, $id);
            }
            $set++;
            $label = trim((string) get_field("subtype", $id));
            echo "<tr style=\"background:#fff3cd\"><td><strong>" . esc_html($sym) .
                "</strong></td><td>" . esc_html($label !== "" ? $label : $post->post_title) .
                "</td><td>" . esc_html($commit ? "set true" : "would set true") . "</td></tr>";
        }
        echo "</tbody></table>";

        $missing = array_values(array_diff(array_keys($genes), array_keys($seen)));
        sort($missing);
        echo "<p><strong>" . count($seen) . "</strong> GENESIS-list genes present in the dataset: " .
            (int) $set . ($commit ? " newly set" : " would be set") . ", " .
            (int) $already . " already flagged.</p>";
        echo "<details><summary>" . count($missing) .
            " GENESIS-list genes not in the dataset (context)</summary>" .
            "<p style=\"font-family:monospace;font-size:12px;line-height:1.6\">" .
            esc_html(implode(", ", $missing)) . "</p></details>";
    }
}

EIC_Genesis_Discovery_Backfill::init();
