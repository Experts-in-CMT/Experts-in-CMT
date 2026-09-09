<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * MU Plugin: EIC Variant Index
 * ------------------------------------------------------------
 * Purpose:
 *   A site-wide, derived index of the pathogenic and likely
 *   pathogenic ClinVar variants at every EIC gene, so platform
 *   search can answer a variant query (t424m, p.Thr424Met,
 *   HSPB3-P121L, c.1271C>T, rs104894520, VCV000041229) with the
 *   gene, the variant, and the gene page's ClinVar Variants card.
 *
 *   Nothing canonical lives here. Rows are rebuilt from the same
 *   payload the gene page's card renders (eic-clinvar-variants.php)
 *   and can be dropped and rebuilt at any time:
 *     - weekly roll (WP-Cron): queues every gene post, then works
 *       the queue a few genes per tick with NCBI etiquette
 *     - self-heal: whenever the card fetches a gene fresh, that
 *       gene's rows are replaced in the same request
 *     - Tools > Variant Index: status and Rebuild Now
 *
 *   The variant grammar and the amino-acid map live here too, in
 *   one place, so the index and the search resolver never drift:
 *   ClinVar writes the same change two ways, three-letter in the
 *   HGVS title (p.Thr424Met) and one-letter in protein_change
 *   (T424M, often a comma list across transcripts). Every key is
 *   the one-letter form, uppercase.
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

final class EIC_Variant_Index
{
    const TABLE = "eic_variant_index";
    const QUEUE_OPTION = "eic_vi_queue";
    const STATE_OPTION = "eic_vi_state";
    const ROLL_HOOK = "eic_vi_roll";
    const TICK_HOOK = "eic_vi_tick";
    const PER_TICK = 4; // genes per cron tick
    const TICK_BUDGET = 40; // seconds of work per tick before rescheduling
    const CAP = "manage_options";
    const NONCE = "eic_vi_rebuild";

    /* ------------------------------------------------------------
     * Amino acids
     * ---------------------------------------------------------- */

    /** Three-letter => one-letter. Ter is the stop codon; ClinVar's short form writes it "*". */
    public static function amino_map(): array
    {
        return [
            "Ala" => "A", "Arg" => "R", "Asn" => "N", "Asp" => "D", "Cys" => "C",
            "Gln" => "Q", "Glu" => "E", "Gly" => "G", "His" => "H", "Ile" => "I",
            "Leu" => "L", "Lys" => "K", "Met" => "M", "Phe" => "F", "Pro" => "P",
            "Ser" => "S", "Thr" => "T", "Trp" => "W", "Tyr" => "Y", "Val" => "V",
            "Ter" => "*",
        ];
    }

    /** One residue in either spelling, or "*"/"X" for stop, to one-letter. Null when it is not a residue. */
    public static function residue_one(string $r): ?string
    {
        $map = self::amino_map();
        $r = trim($r);
        if ($r === "*" || strcasecmp($r, "X") === 0) {
            return "*";
        }
        if (strlen($r) === 1) {
            $u = strtoupper($r);
            return in_array($u, $map, true) ? $u : null;
        }
        if (strlen($r) === 3) {
            $k = ucfirst(strtolower($r));
            return $map[$k] ?? null;
        }
        return null;
    }

    /** One-letter to three-letter ("*" to Ter). */
    public static function residue_three(string $one): string
    {
        $flip = array_flip(self::amino_map());
        return $flip[strtoupper($one)] ?? $one;
    }

    /**
     * Any protein-change string, either spelling, to the one-letter key:
     * Thr424Met => T424M, T424M => T424M, Thr424SerfsTer12 => T424Sfs*12.
     */
    public static function protein_key(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^p\./i', "", $s);
        $s = trim($s, "()");
        $s = preg_replace_callback(
            '/(Ala|Arg|Asn|Asp|Cys|Gln|Glu|Gly|His|Ile|Leu|Lys|Met|Phe|Pro|Ser|Thr|Trp|Tyr|Val|Ter)/i',
            fn($m) => self::amino_map()[ucfirst(strtolower($m[1]))],
            $s
        );
        return strtoupper($s);
    }

    /** One-letter key back to HGVS display: T424M => p.Thr424Met, T424Sfs*12 => p.Thr424SerfsTer12. */
    public static function protein_display(string $key): string
    {
        $key = strtoupper($key);
        // Suffix-only form (T118DEL, R98FS): the suffix word follows the
        // position directly, so there is no second residue to map. Without
        // this, DEL's D would read as aspartate (p.Thr118AspEL).
        if (preg_match('/^([A-Z*])(\d+)((?:DELINS|DEL|DUP|INS|EXT|FS)[A-Z*\d=]*)$/', $key, $m)) {
            $suffix = preg_replace_callback(
                '/(DELINS|DEL|DUP|INS|EXT|FS)/',
                fn($x) => strtolower($x[1]),
                $m[3]
            );
            $suffix = str_replace("*", "Ter", $suffix);
            return "p." . self::residue_three($m[1]) . $m[2] . $suffix;
        }
        if (!preg_match('/^([A-Z*])(\d+)([A-Z*=])?(.*)$/', $key, $m)) {
            return "p." . $key;
        }
        $out = self::residue_three($m[1]) . $m[2];
        if (($m[3] ?? "") !== "") {
            $out .= $m[3] === "=" ? "=" : self::residue_three($m[3]);
        }
        // Suffix: HGVS words lowercase, stop as Ter, digits as they are
        $suffix = (string) ($m[4] ?? "");
        $suffix = preg_replace_callback(
            '/(DELINS|DEL|DUP|INS|EXT|FS)/',
            fn($x) => strtolower($x[1]),
            $suffix
        );
        $suffix = str_replace("*", "Ter", $suffix);
        return "p." . $out . $suffix;
    }

    /* ------------------------------------------------------------
     * Grammar: one typed token to a lookup key
     * ---------------------------------------------------------- */

    /**
     * Parse one token as a variant reference. Returns null when it is not
     * one. The caller checks gene symbols first: S100B parses as nothing
     * here anyway (B is not a residue), but a gene must always win.
     *
     * @return array|null {kind: p|c|r|v, key, display, explicit}
     *   explicit: the token wore variant syntax (p., c., rs, VCV, or a
     *   three-letter residue), so a miss is still a variant search.
     */
    public static function parse_token(string $raw): ?array
    {
        $t = trim($raw, " \t\n\r\0\x0B,;()[]");
        if ($t === "" || strlen($t) > 64) {
            return null;
        }

        // ClinVar accession
        if (preg_match('/^VCV(\d{9})(?:\.\d+)?$/i', $t, $m)) {
            return ["kind" => "v", "key" => "VCV" . $m[1], "display" => "VCV" . $m[1], "explicit" => true];
        }
        // dbSNP
        if (preg_match('/^rs(\d{3,})$/i', $t, $m)) {
            return ["kind" => "r", "key" => "RS" . $m[1], "display" => "rs" . $m[1], "explicit" => true];
        }
        // cDNA: c.1271C>T, c.123del, c.123_124dup, c.88-2A>G, c.123delinsAT
        if (
            preg_match(
                '/^c\.([*-]?\d+(?:[+-]\d+)?(?:_[*-]?\d+(?:[+-]\d+)?)?)([ACGT]>[ACGT]|delins[ACGT]+|del[ACGT]*|dup[ACGT]*|ins[ACGT]+)$/i',
                $t,
                $m
            )
        ) {
            $key = "C." . strtoupper($m[1]) . strtoupper($m[2]);
            $op = strtoupper($m[2]);
            if (preg_match('/^(DELINS|DEL|DUP|INS)(.*)$/', $op, $x)) {
                $op = strtolower($x[1]) . $x[2];
            }
            return ["kind" => "c", "key" => $key, "display" => "c." . $m[1] . $op, "explicit" => true];
        }
        // Protein: [p.][(]Res Pos Res|*|=|fs…|del|dup[)]
        if (
            preg_match(
                '/^(p\.)?\(?([A-Za-z]{1,3})(\d{1,5})(\*|=|[A-Za-z]{1,3})?((?:fs|del|dup|ins|delins|ext)[A-Za-z*\d]*)?\)?$/i',
                $t,
                $m
            )
        ) {
            $r1 = self::residue_one($m[2]);
            if ($r1 === null || $r1 === "*") {
                return null;
            }
            $pos = (int) $m[3];
            if ($pos < 1) {
                return null;
            }
            $r2raw = $m[4] ?? "";
            $suffix = $m[5] ?? "";
            $r2 = "";
            if ($r2raw !== "") {
                if ($r2raw === "=") {
                    $r2 = "=";
                } else {
                    $r2 = self::residue_one($r2raw);
                    if ($r2 === null) {
                        // The residue slot swallowed a suffix word (T118del,
                        // R98fs, G107dup): re-read it as the suffix with no
                        // second residue, the way ClinVar's short form writes it.
                        if (preg_match('/^(?:fs|del|dup|ins|delins|ext)[A-Za-z*\d]*$/i', $r2raw . $suffix)) {
                            $suffix = $r2raw . $suffix;
                            $r2 = "";
                        } else {
                            return null;
                        }
                    }
                }
            }
            if ($r2 === "" && $suffix === "") {
                return null;
            }
            $key = self::protein_key($r1 . $pos . $r2 . $suffix);
            // A suffix word (fs, del, dup...) is variant syntax in its own
            // right, so a suffix form is explicit like p. or a three-letter
            // residue: a miss still reads as a variant search.
            $explicit =
                !empty($m[1]) || strlen($m[2]) === 3 || $suffix !== "" || (isset($m[4]) && strlen($m[4]) === 3);
            return ["kind" => "p", "key" => $key, "display" => self::protein_display($key), "explicit" => $explicit];
        }
        return null;
    }

    /* ------------------------------------------------------------
     * Wiring
     * ---------------------------------------------------------- */

    public static function init(): void
    {
        add_action("init", [__CLASS__, "maybe_install"], 6);
        add_action("init", [__CLASS__, "schedule"], 7);
        add_action(self::ROLL_HOOK, [__CLASS__, "roll"]);
        add_action(self::TICK_HOOK, [__CLASS__, "tick"]);
        // Self-heal: a fresh card fetch replaces that gene's rows
        add_action("eic_clinvar_gene_built", [__CLASS__, "upsert_gene"], 10, 2);
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function maybe_install(): void
    {
        // Install by presence, not by a version marker: the table exists or it
        // is created. A column change is a dbDelta on the same statement.
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            return;
        }
        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        $charset = $wpdb->get_charset_collate();
        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                gene varchar(32) NOT NULL,
                vcv varchar(32) NOT NULL,
                vkey varchar(64) NOT NULL,
                kind char(1) NOT NULL,
                title text NOT NULL,
                protein1 varchar(64) NOT NULL DEFAULT '',
                protein3 varchar(96) NOT NULL DEFAULT '',
                cdna varchar(191) NOT NULL DEFAULT '',
                rsid varchar(32) NOT NULL DEFAULT '',
                classification varchar(64) NOT NULL DEFAULT '',
                stars tinyint(1) NOT NULL DEFAULT 0,
                tier char(1) NOT NULL DEFAULT '',
                url varchar(255) NOT NULL DEFAULT '',
                release_stamp varchar(32) NOT NULL DEFAULT '',
                PRIMARY KEY  (id),
                KEY vkey (vkey),
                KEY gene (gene),
                KEY gene_vcv (gene, vcv)
            ) {$charset};"
        );
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::ROLL_HOOK)) {
            // Sunday 03:10 site time, then weekly
            $next = strtotime("next sunday 03:10", current_time("timestamp"));
            wp_schedule_event($next - (get_option("gmt_offset") * HOUR_IN_SECONDS), "weekly", self::ROLL_HOOK);
        }
    }

    /* ------------------------------------------------------------
     * Build
     * ---------------------------------------------------------- */

    /** Symbols with a gene page that carries the ClinVar card. */
    public static function symbols(): array
    {
        $out = [];
        $posts = get_posts([
            "post_type" => "gene",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "orderby" => "title",
            "order" => "ASC",
        ]);
        foreach ($posts as $id) {
            $symbol = trim((string) get_the_title($id));
            if ($symbol === "") {
                continue;
            }
            if (function_exists("eic_gene_projection")) {
                $g = eic_gene_projection($symbol);
                if (!$g || !empty($g["structural"])) {
                    continue;
                }
            }
            $out[] = strtoupper($symbol);
        }
        return array_values(array_unique($out));
    }

    /** Weekly: queue every gene, start ticking. */
    public static function roll(): void
    {
        $symbols = self::symbols();
        update_option(self::QUEUE_OPTION, $symbols, false);
        $state = self::state();
        $state["last_roll"] = current_time("mysql");
        $state["queued"] = count($symbols);
        $state["errors"] = [];
        $state["retried"] = [];
        self::save_state($state);
        if (!wp_next_scheduled(self::TICK_HOOK)) {
            wp_schedule_single_event(time() + 5, self::TICK_HOOK);
        }
    }

    /** Work a few genes off the queue, then reschedule if any remain. */
    public static function tick(int $max = self::PER_TICK): void
    {
        if (!class_exists("EIC_ClinVar_Variants")) {
            return;
        }
        $queue = (array) get_option(self::QUEUE_OPTION, []);
        if (!$queue) {
            return;
        }
        $started = microtime(true);
        $done = 0;
        $state = self::state();
        while ($queue && $done < $max && microtime(true) - $started < self::TICK_BUDGET) {
            $gene = array_shift($queue);
            update_option(self::QUEUE_OPTION, $queue, false);
            // A worst-case fresh build (thousands of records, paced batches)
            // can outrun a host's cron time limit; buy each gene its own
            // budget where the host allows it.
            if (function_exists("set_time_limit")) {
                @set_time_limit(300);
            }
            $payload = EIC_ClinVar_Variants::get_gene($gene);
            if (is_wp_error($payload)) {
                $state["errors"][$gene] = $payload->get_error_message();
                // Leave the gene's existing rows in place. A first failure
                // (lock collision, NCBI hiccup) earns one more pass at the
                // end of this roll; a second failure waits for the next roll
                if (empty($state["retried"][$gene])) {
                    $state["retried"][$gene] = true;
                    $queue[] = $gene;
                    update_option(self::QUEUE_OPTION, $queue, false);
                }
            } else {
                unset($state["errors"][$gene]);
                if (!empty($payload["cached"])) {
                    // A cached payload fired no action; index it here. A
                    // fresh build already replaced its own rows through
                    // eic_clinvar_gene_built inside get_gene().
                    self::upsert_gene($gene, $payload);
                } else {
                    usleep(400000); // fresh fetch: NCBI etiquette between genes
                }
            }
            $done++;
        }
        // Re-read before saving: upsert_gene() wrote per-gene entries into
        // the option during the loop, and saving this function's older copy
        // would erase them. Only this function's own fields carry over.
        $fresh = self::state();
        $fresh["errors"] = $state["errors"];
        $fresh["retried"] = $state["retried"];
        $fresh["last_tick"] = current_time("mysql");
        $fresh["remaining"] = count($queue);
        self::save_state($fresh);
        if ($queue && !wp_next_scheduled(self::TICK_HOOK)) {
            wp_schedule_single_event(time() + 20, self::TICK_HOOK);
        }
    }

    /** Replace one gene's rows from a card payload. Hooked to the fresh-fetch action too. */
    public static function upsert_gene(string $gene, $payload): void
    {
        if (!is_array($payload) || empty($payload["tiers"])) {
            return;
        }
        global $wpdb;
        $gene = strtoupper(trim($gene));
        $table = self::table();
        $release = (string) ($payload["release"] ?? "");

        // One record per VCV; a variant in A and C keeps A
        $byVcv = [];
        foreach (["A", "C", "B"] as $tier) {
            foreach ((array) ($payload["tiers"][$tier] ?? []) as $v) {
                $vcv = strtoupper(trim((string) ($v["vcv"] ?? "")));
                if ($vcv === "" || isset($byVcv[$vcv])) {
                    continue;
                }
                $byVcv[$vcv] = $v;
            }
        }

        $rows = [];
        foreach ($byVcv as $vcv => $v) {
            $keys = [];
            // One-letter forms from protein_change ("T424M, T400M")
            foreach (preg_split('/\s*,\s*/', (string) ($v["protein"] ?? "")) as $pc) {
                $pc = trim($pc);
                if ($pc !== "") {
                    $keys[self::protein_key($pc)] = "p";
                }
            }
            // Three-letter form from the HGVS title "(p.Thr424Met)"
            $protein3 = "";
            if (preg_match('/\(p\.([^)]+)\)/', (string) ($v["title"] ?? ""), $m)) {
                $protein3 = "p." . $m[1];
                $keys[self::protein_key($m[1])] = "p";
            }
            $protein1 = "";
            foreach ($keys as $k => $kind) {
                $protein1 = $k;
                break;
            }
            $cdna = trim((string) ($v["cdna"] ?? ""));
            if ($cdna !== "" && preg_match('/^c\./i', $cdna)) {
                $keys[strtoupper($cdna)] = "c";
            }
            $rsid = trim((string) ($v["rsid"] ?? ""));
            if ($rsid !== "") {
                $keys[strtoupper($rsid)] = "r";
            }
            $keys[$vcv] = "v";

            foreach ($keys as $key => $kind) {
                $rows[] = [
                    "gene" => $gene,
                    "vcv" => $vcv,
                    "vkey" => substr((string) $key, 0, 64),
                    "kind" => $kind,
                    "title" => (string) ($v["title"] ?? ""),
                    "protein1" => substr($protein1, 0, 64),
                    "protein3" => substr($protein3, 0, 96),
                    "cdna" => substr($cdna, 0, 191),
                    "rsid" => substr($rsid, 0, 32),
                    "classification" => substr((string) ($v["classification"] ?? ""), 0, 64),
                    "stars" => (int) ($v["stars"] ?? 0),
                    "tier" => (string) ($v["tier"] ?? ""),
                    "url" => substr((string) ($v["url"] ?? ""), 0, 255),
                    "release_stamp" => substr($release, 0, 32),
                ];
            }
        }

        $wpdb->query("START TRANSACTION");
        $wpdb->delete($table, ["gene" => $gene], ["%s"]);
        foreach ($rows as $r) {
            $wpdb->insert($table, $r, [
                "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%d", "%s", "%s", "%s",
            ]);
        }
        $wpdb->query("COMMIT");

        $state = self::state();
        $state["genes"][$gene] = ["rows" => count($rows), "variants" => count($byVcv), "at" => current_time("mysql")];
        self::save_state($state);
    }

    /* ------------------------------------------------------------
     * Lookup (search)
     * ---------------------------------------------------------- */

    /**
     * Rows for one key, optionally at one gene. One row per (gene, vcv).
     *
     * @return array[] {gene, vcv, title, protein1, protein3, cdna, rsid, classification, stars, tier, url}
     */
    public static function lookup(string $key, string $gene = ""): array
    {
        global $wpdb;
        $table = self::table();
        $key = strtoupper(trim($key));
        if ($key === "") {
            return [];
        }
        if ($gene !== "") {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE vkey = %s AND gene = %s ORDER BY gene, vcv",
                    $key,
                    strtoupper($gene)
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE vkey = %s ORDER BY gene, vcv LIMIT 50", $key),
                ARRAY_A
            );
        }
        $out = [];
        foreach ((array) $rows as $r) {
            $out[$r["gene"] . "|" . $r["vcv"]] = $r;
        }
        return array_values($out);
    }

    /**
     * Near miss for a protein key that matched nothing: same two residues
     * and the same suffix, with the position one digit off (dropped,
     * added, or wrong): T424M finds T1424M. Within one gene when given,
     * site-wide otherwise. Reported in CMT first, then by review stars.
     *
     * @return array[] rows in the lookup() shape, at most $max
     */
    public static function near(string $key, string $gene = "", int $max = 5): array
    {
        global $wpdb;
        $key = strtoupper(trim($key));
        if (!preg_match('/^([A-Z])(\d+)([A-Z*=])(.*)$/', $key, $m)) {
            return [];
        }
        [$all, $r1, $pos, $r2, $suffix] = $m;
        $table = self::table();
        $like = $wpdb->esc_like($r1) . "%" . $wpdb->esc_like($r2 . $suffix);
        if ($gene !== "") {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE kind = 'p' AND gene = %s AND vkey LIKE %s LIMIT 400",
                    strtoupper($gene),
                    $like
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE kind = 'p' AND vkey LIKE %s LIMIT 400", $like),
                ARRAY_A
            );
        }
        $out = [];
        foreach ((array) $rows as $r) {
            if (!preg_match('/^([A-Z])(\d+)([A-Z*=])(.*)$/', (string) $r["vkey"], $x)) {
                continue;
            }
            if ($x[1] !== $r1 || $x[3] !== $r2 || $x[4] !== $suffix || $x[2] === $pos) {
                continue;
            }
            // One digit dropped, added, or wrong
            if (levenshtein($x[2], $pos) > 1) {
                continue;
            }
            $out[$r["gene"] . "|" . $r["vcv"]] = $r;
        }
        $out = array_values($out);
        usort($out, function ($a, $b) {
            $ta = $a["tier"] === "A" ? 0 : 1;
            $tb = $b["tier"] === "A" ? 0 : 1;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            if ((int) $a["stars"] !== (int) $b["stars"]) {
                return (int) $b["stars"] <=> (int) $a["stars"];
            }
            return strnatcasecmp($a["gene"] . $a["vkey"], $b["gene"] . $b["vkey"]);
        });
        return array_slice($out, 0, max(1, $max));
    }

    /**
     * Fallback when the index has no row for a gene: match against the
     * gene's cached card payload, in memory, with the same keys. Never
     * fetches.
     */
    public static function lookup_cached(string $key, string $gene): array
    {
        if (!class_exists("EIC_ClinVar_Variants") || !method_exists("EIC_ClinVar_Variants", "cached")) {
            return [];
        }
        $payload = EIC_ClinVar_Variants::cached($gene);
        if (!is_array($payload)) {
            return [];
        }
        $key = strtoupper(trim($key));
        $out = [];
        foreach (["A", "C", "B"] as $tier) {
            foreach ((array) ($payload["tiers"][$tier] ?? []) as $v) {
                $vcv = strtoupper((string) ($v["vcv"] ?? ""));
                if ($vcv === "" || isset($out[$vcv])) {
                    continue;
                }
                $keys = [$vcv, strtoupper((string) ($v["rsid"] ?? "")), strtoupper((string) ($v["cdna"] ?? ""))];
                foreach (preg_split('/\s*,\s*/', (string) ($v["protein"] ?? "")) as $pc) {
                    if (trim($pc) !== "") {
                        $keys[] = self::protein_key($pc);
                    }
                }
                $protein3 = "";
                if (preg_match('/\(p\.([^)]+)\)/', (string) ($v["title"] ?? ""), $m)) {
                    $protein3 = "p." . $m[1];
                    $keys[] = self::protein_key($m[1]);
                }
                if (!in_array($key, $keys, true)) {
                    continue;
                }
                $out[$vcv] = [
                    "gene" => strtoupper($gene),
                    "vcv" => $vcv,
                    "title" => (string) ($v["title"] ?? ""),
                    "protein1" => $protein3 !== "" ? self::protein_key(substr($protein3, 2)) : "",
                    "protein3" => $protein3,
                    "cdna" => (string) ($v["cdna"] ?? ""),
                    "rsid" => (string) ($v["rsid"] ?? ""),
                    "classification" => (string) ($v["classification"] ?? ""),
                    "stars" => (int) ($v["stars"] ?? 0),
                    "tier" => (string) ($v["tier"] ?? $tier),
                    "url" => (string) ($v["url"] ?? ""),
                ];
            }
        }
        return array_values($out);
    }

    /* ------------------------------------------------------------
     * State + Tools page
     * ---------------------------------------------------------- */

    public static function state(): array
    {
        $s = get_option(self::STATE_OPTION, []);
        return is_array($s)
            ? $s + ["genes" => [], "errors" => [], "retried" => []]
            : ["genes" => [], "errors" => [], "retried" => []];
    }

    private static function save_state(array $state): void
    {
        update_option(self::STATE_OPTION, $state, false);
    }

    public static function menu(): void
    {
        add_management_page("Variant Index", "Variant Index", self::CAP, "eic-variant-index", [__CLASS__, "render"]);
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        global $wpdb;
        eic_admin_tool_open("Variant Index", "ClinVar P/LP variants across every gene page, indexed for platform search.");

        $action = $_POST["eic_action"] ?? "";
        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "rebuild") {
                self::roll();
                echo '<div class="notice notice-success"><p>Queued every gene. The index rebuilds a few genes at a time in the background; reload to watch it progress.</p></div>';
            } elseif ($action === "tick") {
                self::tick();
                echo '<div class="notice notice-success"><p>Ran one tick.</p></div>';
            }
        }

        $table = self::table();
        $rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $variants = (int) $wpdb->get_var("SELECT COUNT(DISTINCT CONCAT(gene, '|', vcv)) FROM {$table}");
        $genes = (int) $wpdb->get_var("SELECT COUNT(DISTINCT gene) FROM {$table}");
        $state = self::state();
        $queue = (array) get_option(self::QUEUE_OPTION, []);
        $next_roll = wp_next_scheduled(self::ROLL_HOOK);
        $next_tick = wp_next_scheduled(self::TICK_HOOK);

        echo "<p>Derived from the same ClinVar payload the gene page's ClinVar Variants card renders. " .
            "Rebuilt weekly by a queued roll, and any gene the card fetches fresh replaces its own rows on the spot. " .
            "Search reads it; nothing else does. Safe to rebuild at any time.</p>";

        echo '<table class="widefat striped" style="max-width:640px"><tbody>';
        $line = function (string $k, string $v) {
            echo "<tr><th style=\"width:220px\">" . esc_html($k) . "</th><td>" . $v . "</td></tr>";
        };
        $line("Genes indexed", esc_html((string) $genes));
        $line("Variants indexed", esc_html((string) $variants));
        $line("Lookup keys", esc_html((string) $rows));
        $line("Queue remaining", esc_html((string) count($queue)));
        $line("Last roll", esc_html((string) ($state["last_roll"] ?? "never")));
        $line("Last tick", esc_html((string) ($state["last_tick"] ?? "never")));
        $line("Next roll", $next_roll ? esc_html(get_date_from_gmt(gmdate("Y-m-d H:i:s", $next_roll), "Y-m-d H:i")) : "not scheduled");
        $line("Next tick", $next_tick ? esc_html(get_date_from_gmt(gmdate("Y-m-d H:i:s", $next_tick), "Y-m-d H:i:s")) : "idle");
        echo "</tbody></table>";

        if (!empty($state["errors"])) {
            echo "<h3>Upstream errors on the last roll</h3><ul>";
            foreach ((array) $state["errors"] as $g => $msg) {
                echo "<li><strong>" . esc_html((string) $g) . "</strong>: " . esc_html((string) $msg) . "</li>";
            }
            echo "</ul>";
        }

        echo '<hr><form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><button class="button button-primary" name="eic_action" value="rebuild">Rebuild Now</button> ';
        echo '<button class="button" name="eic_action" value="tick">Run One Tick</button></p>';
        echo "</form>";
        eic_admin_tool_close();
    }
}

EIC_Variant_Index::init();
