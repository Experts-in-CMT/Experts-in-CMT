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
 * MU Plugin: EIC ClinVar Variants
 * ------------------------------------------------------------
 * Gene-scoped surface of ClinVar pathogenic / likely pathogenic
 * (P/LP) variants for the gene page. No canonical storage: every
 * response is a live ClinVar E-utilities fetch behind a self-healing
 * cache keyed on the gene and ClinVar's own release stamp, so it is
 * never staler than ClinVar's weekly roll and never needs a manual
 * version bump.
 *
 *   GET /wp-json/eic/v1/clinvar/{gene}
 *
 * Scope, deliberately narrow:
 *   - Aggregate germline classification only. Records whose aggregate
 *     is VUS, conflicting, benign, or anything other than P, LP, or
 *     P/LP are excluded. No somatic or oncogenicity classifications.
 *   - Review status of at least one star. A P/LP record ClinVar marks
 *     "no assertion criteria provided" (zero stars) is excluded at
 *     every tier: a call with no stated method is not one a reviewer
 *     would act on. Applied on read (MIN_STARS), so the cache is untouched.
 *   - Notation and routing only, no interpretation. The page presents
 *     what ClinVar states and whether the disease it names is CMT.
 *
 * EIC's disease universe is CMT: the thirteen classifications (CMT1,
 * CMT2, CMT4, CMTX, CMTDI, CMTRI, dHMN/HMN, dSMA, GAN, HMSN, HSAN, HSN,
 * SMA-LEP) plus Unclassified Subtypes. A ClinVar-reported disease is CMT when
 * it names one of those; everything else is another disease.
 *
 * Tiers, split by reported disease rather than by variant:
 *   A  Reported in CMT: the variant's CMT report(s) only
 *   B  Variants w/o a Recorded Disease (listed last): no disease named,
 *      at every gene
 *   C  Reported in Other Diseases: the variant's non-CMT report(s)
 * A variant reported in both CMT and another disease appears in A and
 * in C, each time showing only the diseases that put it there, so a
 * non-CMT disease name never appears under Reported in CMT.
 *
 * Breadth (cmt-only vs pleiotropic) is derived from the gene's own
 * ClinVar set: if any P/LP variant at the gene carries a non-CMT
 * disease, the gene is pleiotropic. Nothing is stored; the gene
 * post stays a shell.
 *
 * The CMT set is a MedGen CUI seed plus name patterns built from the
 * classification vocabulary (first pass; a MONDO-resolved set can
 * replace it in place without touching the tiering).
 *
 * Cache: a meta transient holds ClinVar's LastUpdate from einfo
 * (lazy, 1 day). Each gene's response is a transient keyed on
 * gene + that stamp; a new release naturally misses and refetches.
 * The cache holds what ClinVar said (parsed records with their trait
 * names and MedGen IDs); every rule (which disease is CMT, which tier a
 * report lands in) is applied when the cache is read, so a rule change
 * never refetches and nothing is ever version-bumped. A cached entry
 * that lacks a field the parser now produces is refetched: the data
 * heals itself.
 *
 * Location: wp-content/mu-plugins/eic-clinvar-variants.php
 */

if (!defined("ABSPATH")) {
    exit();
}

final class EIC_ClinVar_Variants
{
    const EUTILS = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/";
    const RELEASE_TRANSIENT = "eic_cv_release";
    const RELEASE_TTL = DAY_IN_SECONDS;
    const GENE_TTL = 8 * DAY_IN_SECONDS; // belt and braces; the release key is the real invalidator
    const PAGE = 40; // esummary ids per request: v2 docsums run ~90 KB each and NCBI caps the JSON transform at 10 MB
    const MAX = 5000; // hard ceiling per gene
    const MIN_STARS = 1; // review floor, applied on read: zero stars ("no assertion criteria provided") is out
    /** Fields parse() writes per record; a cached record missing one is stale and refetches. */
    const RECORD_KEYS = ["vcv", "vcv_version", "url", "title", "cdna", "protein", "type", "classification", "review_status", "stars", "last_evaluated", "rsid", "conditions"];

    /* ------------------------------------------------------------
     * CMT set: MedGen CUIs + name patterns (first pass)
     * ---------------------------------------------------------- */

    /** MedGen CUIs known to name CMT. */
    private static function cmt_cuis(): array
    {
        return array_fill_keys(
            [
                "C0007959", // Charcot-Marie-Tooth disease
            ],
            true
        );
    }

    /**
     * Disease-name patterns from the classification vocabulary:
     * CMT1/2/4/X/DI/RI, dHMN/HMN, dSMA, GAN, HMSN, HSAN, HSN, SMA-LEP,
     * plus the ClinVar spellings of the same entities (Charcot-Marie-Tooth,
     * hereditary motor and sensory neuropathy, hereditary sensory and
     * autonomic neuropathy, distal hereditary motor neuropathy, distal
     * spinal muscular atrophy, giant axonal neuropathy, SMA with lower
     * extremity predominance, HNPP under PMP22, Dejerine-Sottas, Roussy-Levy).
     */
    private static function cmt_pattern(): string
    {
        return '/charcot|marie[- ]tooth|\bCMT[0-9A-Z]*\b|\bHMSN\b|\bHSAN\b|\bHSN\b|\bd?HMN\b|\bdSMA\b|\bGAN\b|\bSMA-?LED\b|\bSMA-?LEP\b|hereditary motor and sensory neuropathy|hereditary sensory and autonomic neuropathy|hereditary sensory neuropathy|neuropathy, hereditary (motor|sensory)|hereditary motor neuropathy|distal hereditary motor|distal spinal muscular atrophy|spinal muscular atrophy, distal|spinal muscular atrophy,? lower extremity|giant axonal|dejerine|roussy[- ]l[eé]vy|pressure palsies|(dominant|recessive) intermediate/iu';
    }

    /**
     * Legacy CMT names EIC does not recognize as subtypes: CMT3 and
     * Dejerine-Sottas (archaic, abandoned; DSS survives only as a
     * descriptor) and Roussy-Levy (an outdated description of a symptom
     * pattern, never a subtype). See /genetics/cmt-classifications/.
     * A report against one of these is a report in CMT, so it stays in
     * Reported in CMT, but it is flagged so the page never presents it
     * as a subtype.
     */
    private static function legacy_pattern(): string
    {
        return '/dejerine|sottas|roussy|\bCMT3\b|charcot[- ]marie[- ]tooth (disease|neuropathy),? type (3|III)\b/iu';
    }

    /**
     * ClinVar trait names that name no disease at all: the placeholders,
     * and the "GENE-related disorder" convention, which names the gene
     * rather than a disease and must not make a gene look pleiotropic.
     */
    private static function unspecified_pattern(): string
    {
        return '/^(not provided|not specified|see cases|none provided|inborn genetic diseases|hereditary disease|genetic disease)$|^[A-Z0-9][A-Z0-9._-]*[- ]related (disorder|condition|disease|phenotype|neuropathy)s?$/i';
    }

    /* ------------------------------------------------------------
     * Wiring
     * ---------------------------------------------------------- */

    public static function init(): void
    {
        add_action("rest_api_init", [__CLASS__, "routes"]);
    }

    public static function routes(): void
    {
        register_rest_route("eic/v1", "/clinvar/(?P<gene>[A-Za-z0-9._-]{1,32})", [
            "methods" => "GET",
            "callback" => [__CLASS__, "handle"],
            "permission_callback" => "__return_true",
            "args" => [
                "gene" => ["sanitize_callback" => "sanitize_text_field"],
            ],
        ]);
    }

    public static function handle(WP_REST_Request $req)
    {
        $gene = strtoupper(trim((string) $req->get_param("gene")));
        if ($gene === "") {
            return new WP_Error("eic_cv_bad_gene", "Gene symbol required.", ["status" => 400]);
        }
        // Only genes EIC knows: a symbol with a gene post (any status).
        // Fail closed: if the theme's lookup is unavailable (theme swapped),
        // this route must not become an open proxy to NCBI.
        if (!function_exists("eic_gene_post_for_symbol") || !eic_gene_post_for_symbol($gene)) {
            return new WP_Error("eic_cv_unknown_gene", "Not an EIC gene.", ["status" => 404]);
        }
        $admin = self::diagnostics_allowed();
        // Admin-only: ?debug=1 returns the first raw esummary docsum so a
        // field-name change upstream can be seen and the parser adapted.
        if ($admin && $req->get_param("debug") === "1") {
            return new WP_REST_Response(self::debug_sample($gene), 200);
        }
        $refresh = $admin && $req->get_param("refresh") === "1";
        $data = self::get_gene($gene, $refresh);
        if (is_wp_error($data)) {
            return $data;
        }
        $res = new WP_REST_Response($data, 200);
        $res->header("Cache-Control", "public, max-age=3600");
        return $res;
    }

    /**
     * Diagnostics (?debug=1, ?refresh=1, upstream detail in errors) are for
     * admins. A REST call typed into the address bar carries no nonce, so it
     * is logged out; WP_DEBUG covers that on development only. The site's own
     * production marker (WP_ENV=production, as used to hide the ACF UI) shuts
     * the fallback off even if WP_DEBUG is ever left on in production.
     */
    private static function diagnostics_allowed(): bool
    {
        if (current_user_can("manage_options")) {
            return true;
        }
        if (wp_get_environment_type() === "production") {
            return false;
        }
        return defined("WP_DEBUG") && WP_DEBUG;
    }

    /* ------------------------------------------------------------
     * Cache
     * ---------------------------------------------------------- */

    /** ClinVar's current release stamp (einfo LastUpdate), cached lazily. */
    public static function release(): string
    {
        $r = get_transient(self::RELEASE_TRANSIENT);
        if (is_string($r) && $r !== "") {
            return $r;
        }
        $json = self::get_json("einfo.fcgi", ["db" => "clinvar", "version" => "2.0"]);
        $stamp = "";
        if (is_array($json)) {
            // einfo 2.0 JSON wraps dbinfo in a single-element array.
            $stamp = (string) ($json["einforesult"]["dbinfo"][0]["lastupdate"] ?? "");
        }
        if ($stamp === "") {
            // Fall back to the week so a transient einfo hiccup never
            // pins a stale key for longer than a week.
            $stamp = gmdate("o-\WW");
        }
        set_transient(self::RELEASE_TRANSIENT, $stamp, self::RELEASE_TTL);
        return $stamp;
    }

    private static function gene_key(string $gene, string $release): string
    {
        return "eic_cv_" . md5($gene . "|" . $release);
    }

    /**
     * Cache-only read for page render (gene JSON-LD). Never touches NCBI:
     * a cold release stamp or a cold gene set returns null and the caller
     * omits what it cannot state. The card's own fetch warms both.
     */
    public static function cached(string $gene): ?array
    {
        $gene = strtoupper(trim($gene));
        $release = get_transient(self::RELEASE_TRANSIENT);
        if (!is_string($release) || $release === "") {
            return null;
        }
        $raw = get_transient(self::gene_key($gene, $release));
        return self::usable($raw) ? self::derive($raw) : null;
    }

    /**
     * A cached fetch is usable when it has the shape the parser produces
     * now. A record missing a field added since it was cached means the
     * cache predates the parser, so the gene refetches. No version marker.
     */
    private static function usable($raw): bool
    {
        if (!is_array($raw) || !isset($raw["variants"]) || !is_array($raw["variants"])) {
            return false;
        }
        foreach (["gene", "release", "fetched", "query", "count"] as $k) {
            if (!array_key_exists($k, $raw)) {
                return false;
            }
        }
        $first = $raw["variants"][0] ?? null;
        if ($first === null) {
            return true;
        }
        foreach (self::RECORD_KEYS as $k) {
            if (!array_key_exists($k, $first)) {
                return false;
            }
        }
        $c = $first["conditions"][0] ?? null;
        return $c === null || (array_key_exists("name", $c) && array_key_exists("cui", $c));
    }

    /** Gene payload: cached fetch when usable, fetched on miss, rules applied on read. */
    public static function get_gene(string $gene, bool $force = false)
    {
        $release = self::release();
        $key = self::gene_key($gene, $release);
        if (!$force) {
            $raw = get_transient($key);
            if (self::usable($raw)) {
                $out = self::derive($raw);
                $out["cached"] = true;
                return $out;
            }
        }
        // Cache misses are the expensive path (a paced NCBI fetch chain
        // holding a PHP worker for seconds), so they are the guarded path.
        if (!self::miss_allowed()) {
            return new WP_Error(
                "eic_cv_rate_limited",
                "Too many uncached requests. Try again shortly.",
                ["status" => 429]
            );
        }
        // One builder per gene: a concurrent miss for the same gene waits
        // for the cache instead of duplicating the fetch chain.
        $lock = "eic_cv_lock_" . md5($gene);
        if (get_transient($lock)) {
            return new WP_Error(
                "eic_cv_busy",
                "This gene's ClinVar set is being fetched. Try again shortly.",
                ["status" => 503]
            );
        }
        set_transient($lock, 1, 2 * MINUTE_IN_SECONDS);
        $raw = self::build($gene, $release);
        delete_transient($lock);
        if (is_wp_error($raw)) {
            return $raw;
        }
        set_transient($key, $raw, self::GENE_TTL);
        $out = self::derive($raw);
        // A fresh set is the moment derived surfaces (the variant index
        // behind search) replace what they hold for this gene.
        do_action("eic_clinvar_gene_built", $gene, $out);
        $out["cached"] = false;
        return $out;
    }

    /**
     * Per-IP budget for cache misses: 30 builds per 15 minutes, which no
     * human browsing gene pages approaches (one miss per gene per release)
     * but a hammering client hits at once. Admins are exempt. Cached hits
     * never touch this.
     */
    private static function miss_allowed(): bool
    {
        if (current_user_can("manage_options")) {
            return true;
        }
        // The weekly index roll builds many genes from one server address
        if (function_exists("wp_doing_cron") && wp_doing_cron()) {
            return true;
        }
        $ip = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
        if ($ip === "") {
            return true;
        }
        $key = "eic_cv_ip_" . md5($ip);
        $n = (int) get_transient($key);
        if ($n >= 30) {
            return false;
        }
        set_transient($key, $n + 1, 15 * MINUTE_IN_SECONDS);
        return true;
    }

    /* ------------------------------------------------------------
     * Fetch + tier
     * ---------------------------------------------------------- */

    private static function build(string $gene, string $release)
    {
        $term =
            $gene .
            '[gene] AND ("clinsig pathogenic"[Properties] OR "clinsig likely pathogenic"[Properties])';

        $search = self::get_json("esearch.fcgi", [
            "db" => "clinvar",
            "term" => $term,
            "retmax" => self::MAX,
        ]);
        if (!is_array($search) || !isset($search["esearchresult"])) {
            if (is_array($search) && self::$last_error === "") {
                self::$last_error = "esearch: unexpected shape " . substr((string) wp_json_encode($search), 0, 600);
            }
            return self::upstream_error("ClinVar search unavailable.");
        }
        if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log("[eic-clinvar] esearch " . $gene . " count=" . (int) ($search["esearchresult"]["count"] ?? -1) . " ids=" . count((array) ($search["esearchresult"]["idlist"] ?? [])));
        }
        $er = $search["esearchresult"];
        $count = (int) ($er["count"] ?? 0);
        $ids = array_values(array_filter(array_map("strval", (array) ($er["idlist"] ?? []))));

        $variants = [];
        foreach (array_chunk($ids, self::PAGE) as $i => $chunk) {
            if ($i > 0) {
                usleep(350000); // NCBI etiquette: stay under 3 req/s
            }
            $sum = self::post_json("esummary.fcgi", [
                "db" => "clinvar",
                "id" => implode(",", $chunk),
                "version" => "2.0",
            ]);
            if (!is_array($sum) || !isset($sum["result"])) {
                if (is_array($sum) && self::$last_error === "") {
                    self::$last_error = "esummary: unexpected shape " . substr((string) wp_json_encode($sum), 0, 600);
                }
                return self::upstream_error("ClinVar summary unavailable.");
            }
            foreach ($sum["result"] as $uid => $doc) {
                if ($uid === "uids" || !is_array($doc)) {
                    continue;
                }
                $v = self::parse($doc, $gene);
                if ($v) {
                    $variants[] = $v;
                }
            }
        }

        return [
            "gene" => $gene,
            "release" => $release,
            "fetched" => gmdate("c"),
            "source" => "ClinVar (NCBI E-utilities)",
            "query" => $term,
            "count" => $count,
            "variants" => $variants,
        ];
    }

    /**
     * Apply the rules to a cached fetch: classify each reported disease,
     * derive breadth, split into tiers. Runs on every read, so a change
     * here takes effect at once with no refetch.
     */
    private static function derive(array $raw): array
    {
        $gene = (string) $raw["gene"];
        $variants = [];
        foreach ((array) $raw["variants"] as $v) {
            // Review floor. The cache holds every P/LP record ClinVar
            // returned; the floor is a rule, so it lives here with the
            // others and a change to it never refetches.
            if ((int) ($v["stars"] ?? 0) < self::MIN_STARS) {
                continue;
            }
            $conditions = [];
            foreach ((array) ($v["conditions"] ?? []) as $c) {
                $name = (string) ($c["name"] ?? "");
                $cui = (string) ($c["cui"] ?? "");
                $class = self::classify_condition($name, $cui);
                $conditions[] = [
                    "name" => $name,
                    "cui" => $cui,
                    "class" => $class,
                    "legacy" => $class === "cmt" && (bool) preg_match(self::legacy_pattern(), $name),
                ];
            }
            if (!$conditions) {
                $conditions[] = ["name" => "not provided", "cui" => "", "class" => "unspecified", "legacy" => false];
            }
            $v["conditions"] = $conditions;
            $variants[] = $v;
        }

        // Breadth from the gene's own set: any non-CMT disease on any
        // P/LP variant makes the gene pleiotropic.
        $pleiotropic = false;
        foreach ($variants as $v) {
            foreach ($v["conditions"] as $c) {
                if ($c["class"] === "other") {
                    $pleiotropic = true;
                    break 2;
                }
            }
        }

        // Split by reported disease, never by variant. A variant reported
        // in both CMT and another disease appears under A showing only its
        // CMT report(s), and again under C showing only the non-CMT ones.
        // A non-CMT disease name never appears under Reported in CMT:
        // patients read these pages, and Menkes listed there would be
        // read as CMT. CMT is a disease; it is never called a condition.
        $tiers = ["A" => [], "B" => [], "C" => []];
        foreach ($variants as $v) {
            $cmt = [];
            $other = [];
            $unspec = [];
            foreach ($v["conditions"] as $c) {
                if ($c["class"] === "cmt") {
                    $cmt[] = $c;
                } elseif ($c["class"] === "other") {
                    $other[] = $c;
                } else {
                    $unspec[] = $c;
                }
            }
            if ($cmt) {
                $e = $v;
                $e["conditions"] = $cmt;
                $e["tier"] = "A";
                $e["tier_reason"] = "reported in CMT";
                $tiers["A"][] = $e;
            }
            if ($other) {
                $e = $v;
                $e["conditions"] = $other;
                $e["tier"] = "C";
                $e["tier_reason"] = "reported in another disease";
                $tiers["C"][] = $e;
            }
            // A report naming no disease only matters when it is the variant's
            // only report; beside a named disease it adds nothing.
            if ($unspec && !$cmt && !$other) {
                $e = $v;
                $e["conditions"] = $unspec;
                $e["tier"] = "B";
                $e["tier_reason"] = "no disease named";
                $tiers["B"][] = $e;
            }
        }
        foreach ($tiers as &$list) {
            usort($list, fn($a, $b) => strnatcasecmp($a["title"], $b["title"]));
        }
        unset($list);

        return [
            "gene" => $gene,
            "release" => (string) $raw["release"],
            "fetched" => (string) $raw["fetched"],
            "source" => (string) ($raw["source"] ?? "ClinVar (NCBI E-utilities)"),
            "query" => (string) $raw["query"],
            "total_plp" => count($variants),
            "truncated" => (int) $raw["count"] > self::MAX,
            "breadth" => $pleiotropic ? "pleiotropic" : "cmt-only",
            "counts" => ["A" => count($tiers["A"]), "B" => count($tiers["B"]), "C" => count($tiers["C"])],
            "tiers" => $tiers,
        ];
    }

    /** Admin debug: esearch count plus the first raw docsum, unparsed. */
    private static function debug_sample(string $gene): array
    {
        $term = $gene . '[gene] AND ("clinsig pathogenic"[Properties] OR "clinsig likely pathogenic"[Properties])';
        $search = self::get_json("esearch.fcgi", ["db" => "clinvar", "term" => $term, "retmax" => 1]);
        $ids = $search["esearchresult"]["idlist"] ?? [];
        $sum = $ids ? self::get_json("esummary.fcgi", ["db" => "clinvar", "id" => $ids[0], "version" => "2.0"]) : null;
        return [
            "term" => $term,
            "count" => (int) ($search["esearchresult"]["count"] ?? 0),
            "release" => self::release(),
            "first_docsum" => $sum["result"][$ids[0]] ?? null,
        ];
    }

    /** One esummary docsum -> variant row, or null if not aggregate P/LP germline. */
    private static function parse(array $d, string $gene): ?array
    {
        $gc = $d["germline_classification"] ?? [];
        $desc = trim((string) ($gc["description"] ?? ""));
        if (!self::is_plp($desc)) {
            return null;
        }
        $title = trim((string) ($d["title"] ?? ""));
        $vs = $d["variation_set"][0] ?? [];
        $cdna = trim((string) ($vs["cdna_change"] ?? ""));
        $protein = trim((string) ($d["protein_change"] ?? ""));
        $type = trim((string) ($vs["variant_type"] ?? ($d["obj_type"] ?? "")));
        $rsid = "";
        foreach ((array) ($vs["variation_xrefs"] ?? []) as $x) {
            if (strtolower((string) ($x["db_source"] ?? "")) === "dbsnp") {
                $rsid = "rs" . ltrim((string) ($x["db_id"] ?? ""), "rs");
                break;
            }
        }
        $conditions = [];
        foreach ((array) ($gc["trait_set"] ?? []) as $t) {
            $name = trim((string) ($t["trait_name"] ?? ""));
            $cui = "";
            foreach ((array) ($t["trait_xrefs"] ?? []) as $x) {
                if (strtolower((string) ($x["db_source"] ?? "")) === "medgen") {
                    $cui = (string) ($x["db_id"] ?? "");
                    break;
                }
            }
            $conditions[] = ["name" => $name, "cui" => $cui];
        }
        $acc = trim((string) ($d["accession"] ?? ""));
        $accv = trim((string) ($d["accession_version"] ?? $acc));
        return [
            "vcv" => $acc,
            "vcv_version" => $accv,
            "url" => $acc !== "" ? "https://www.ncbi.nlm.nih.gov/clinvar/variation/" . rawurlencode($acc) . "/" : "",
            "title" => $title,
            "cdna" => $cdna,
            "protein" => $protein,
            "type" => $type,
            "classification" => $desc,
            "review_status" => trim((string) ($gc["review_status"] ?? "")),
            "stars" => self::stars((string) ($gc["review_status"] ?? "")),
            "last_evaluated" => trim((string) ($gc["last_evaluated"] ?? "")),
            "rsid" => $rsid,
            "conditions" => $conditions,
        ];
    }

    /** Aggregate germline P, LP, or P/LP only. Conflicting, VUS, benign, risk-only are out. */
    private static function is_plp(string $desc): bool
    {
        $d = strtolower($desc);
        if ($d === "" || str_contains($d, "conflicting") || str_contains($d, "uncertain") || str_contains($d, "benign")) {
            return false;
        }
        return (bool) preg_match('/^(pathogenic|likely pathogenic|pathogenic\/likely pathogenic)(\b|$)/', $d);
    }

    private static function classify_condition(string $name, string $cui): string
    {
        if ($cui !== "" && isset(self::cmt_cuis()[$cui])) {
            return "cmt";
        }
        if ($name === "" || preg_match(self::unspecified_pattern(), $name)) {
            return "unspecified";
        }
        if (preg_match(self::cmt_pattern(), $name)) {
            return "cmt";
        }
        return "other";
    }

    /** ClinVar review status -> star count, as ClinVar displays it. */
    private static function stars(string $status): int
    {
        $s = strtolower($status);
        if (str_contains($s, "practice guideline")) {
            return 4;
        }
        if (str_contains($s, "expert panel")) {
            return 3;
        }
        if (str_contains($s, "multiple submitters, no conflicts")) {
            return 2;
        }
        if (str_contains($s, "single submitter") || str_contains($s, "criteria provided, conflicting")) {
            return 1;
        }
        return 0;
    }

    /* ------------------------------------------------------------
     * HTTP
     * ---------------------------------------------------------- */

    /** Last upstream failure (code + body head), for admin-visible errors. */
    private static $last_error = "";

    private static function params(array $params): array
    {
        $params["retmode"] = "json";
        $params["tool"] = "expertsincmt";
        $params["email"] = (string) get_option("admin_email");
        if (defined("EIC_NCBI_API_KEY") && EIC_NCBI_API_KEY !== "") {
            $params["api_key"] = EIC_NCBI_API_KEY;
        }
        return $params;
    }

    private static function decode($res, string $endpoint)
    {
        if (is_wp_error($res)) {
            self::$last_error = $endpoint . ": " . $res->get_error_message();
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        if ($code !== 200) {
            self::$last_error = $endpoint . ": HTTP " . $code . " " . substr(trim($body), 0, 300);
            return null;
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            self::$last_error = $endpoint . ": non-JSON body " . substr(trim($body), 0, 300);
            return null;
        }
        if (isset($json["error"]) && !isset($json["result"]) && !isset($json["esearchresult"])) {
            self::$last_error = $endpoint . ": " . (string) $json["error"];
            return null;
        }
        return $json;
    }

    private static function get_json(string $endpoint, array $params)
    {
        $url = self::EUTILS . $endpoint . "?" . http_build_query(self::params($params), "", "&", PHP_QUERY_RFC3986);
        return self::decode(wp_remote_get($url, ["timeout" => 25, "headers" => ["Accept" => "application/json"]]), $endpoint);
    }

    /** POST for id lists, which outgrow a URL past a few hundred ids. */
    private static function post_json(string $endpoint, array $params)
    {
        return self::decode(
            wp_remote_post(self::EUTILS . $endpoint, [
                "timeout" => 40,
                "headers" => ["Accept" => "application/json"],
                "body" => self::params($params),
            ]),
            $endpoint
        );
    }

    private static function upstream_error(string $msg): WP_Error
    {
        // Always log the upstream detail; debug.log is the reliable channel.
        error_log("[eic-clinvar] " . $msg . " " . self::$last_error);
        $data = ["status" => 503];
        if (self::diagnostics_allowed() && self::$last_error !== "") {
            $data["upstream"] = self::$last_error;
        }
        return new WP_Error("eic_cv_upstream", $msg, $data);
    }
}

EIC_ClinVar_Variants::init();
