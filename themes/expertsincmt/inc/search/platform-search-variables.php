<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Platform Search Variables Layer
 *
 * Since version: 1.8.0
 * Feature: platform-search
 *
 * Architecture:
 * -------------
 * Intent resolution runs through an ordered registry of resolver
 * functions (eic_ps_intent_resolvers). Each resolver receives the
 * normalized query plus a shared context and either:
 *   - returns a payload array (possibly empty) → resolution STOPS, or
 *   - returns null → the next resolver runs.
 *
 * Curated semantic knowledge (retracted genes, archaic names,
 * nomenclature aliases) lives in ONE data table
 * (eic_ps_semantic_table); adding the next alias is a data edit,
 * not a new function.
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * ============================================================
 *  CMT Type → Classification Anchor Map
 * ============================================================
 *
 * Single source of truth for mapping resolved CMT types
 * to their canonical classification anchors.
 */
function eic_ps_type_classification_anchors(): array
{
    return [
        "cmt1" => "cmt1",
        "cmt2" => "cmt2",
        "cmt4" => "cmt4",
        "cmtx" => "cmtx",
        "cmtdi" => "cmtdi",
        "cmtri" => "cmtri",
        "dhmn" => "dhmn",
        "dsma" => "dsma",
        "gan" => "gan",
        "hmsn" => "hmsn",
        "hsan" => "hsan",
        "hsn" => "hsn",
        "sma-lep" => "smalep",
        "unclassified" => "unclassified",
    ];
}

/**
 * ============================================================
 *  Type Classification → Pill Family (color)
 * ============================================================
 *
 * Mirrors eic_gb_family() in gene-browser-table.php so search
 * pills speak the same color language as the browsers, but keyed
 * by type_classification slugs. Keep the two in sync.
 */
function eic_ps_type_family(string $type): string
{
    $map = [
        "cmt1" => "blue",
        "cmt2" => "green",
        "cmt4" => "indigo",
        "cmtx" => "purple",
        "cmtdi" => "amber",
        "cmtri" => "amber",
        "dhmn" => "orange",
        "dsma" => "orange",
        "sma-lep" => "orange",
        "gan" => "grey",
        "hmsn" => "slate",
        "hsan" => "rose",
        "hsn" => "rose",
        "unclassified" => "grey",
    ];

    return $map[strtolower(trim($type))] ?? "grey";
}

/**
 * ============================================================
 *  Canonical Type Classification Order
 * ============================================================
 *
 * Single source of truth for canonical display/sort order.
 * Consumers needing case-insensitive ranking should build
 * their rank map via strtoupper().
 */
function eic_ps_canonical_type_order(): array
{
    return [
        "CMT1",
        "CMT2",
        "CMT4",
        "CMTX",
        "CMTDI",
        "CMTRI",
        "dHMN",
        "dSMA",
        "GAN",
        "HMSN",
        "HSAN",
        "HSN",
        "SMA-LEP",
        "Unclassified",
    ];
}

/**
 * ============================================================
 *  Subtype ID Index (per-request, cache-primed)
 * ============================================================
 *
 * Fetches all published subtype IDs once per request and primes
 * the post cache (posts only — NOT meta) so downstream
 * get_the_title()/get_permalink() calls are cache hits.
 *
 * Meta is deliberately not primed: subtype posts carry ~130 meta
 * rows each (CRDT documents, Yoast, mechanism fields) and loading
 * all of it exhausts memory. Search-relevant fields come from
 * eic_ps_subtype_field() instead.
 */
function eic_ps_all_subtype_ids(): array
{
    static $ids = null;

    if (is_array($ids)) {
        return $ids;
    }

    $ids = get_posts([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "fields" => "ids",
    ]);

    if (!empty($ids)) {
        _prime_post_caches($ids, false, false);
    }

    return $ids;
}

/**
 * ============================================================
 *  Lean Subtype Meta Index
 * ============================================================
 *
 * One query for the three fields the search stack reads in
 * loops. Raw SQL (not get_field) is a deliberate performance
 * exception, mirroring the MU-plugin fallback pattern.
 */
function eic_ps_subtype_meta_index(): array
{
    static $index = null;

    if (is_array($index)) {
        return $index;
    }

    global $wpdb;

    $index = [];
    $ids = eic_ps_all_subtype_ids();

    if (empty($ids)) {
        return $index;
    }

    $id_list = implode(",", array_map("intval", $ids));

    $rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id IN ({$id_list})
           AND meta_key IN ('subtype', 'type_classification', 'gene_symbol')"
    );

    foreach ($rows as $row) {
        $index[(int) $row->post_id][$row->meta_key] = (string) $row->meta_value;
    }

    return $index;
}

/**
 * Read one indexed subtype field ('' when absent).
 */
function eic_ps_subtype_field($post_id, string $key): string
{
    $index = eic_ps_subtype_meta_index();

    return $index[(int) $post_id][$key] ?? "";
}

/**
 * All subtype IDs whose gene_symbol matches (case-insensitive).
 * Index-backed — no query.
 */
function eic_ps_subtype_ids_for_gene(string $symbol): array
{
    $symbol = strtoupper(trim($symbol));

    if ($symbol === "") {
        return [];
    }

    $ids = [];
    foreach (eic_ps_subtype_meta_index() as $post_id => $fields) {
        if (strtoupper($fields["gene_symbol"] ?? "") === $symbol) {
            $ids[] = $post_id;
        }
    }

    return $ids;
}

/**
 * ============================================================
 *  Content Search Helper (shared, capped)
 * ============================================================
 *
 * One place for the native-search content lookups used across
 * all resolvers. Capped (renderer has no pagination); cap is
 * filterable via eic_ps_content_search_limit.
 */
function eic_ps_content_search(string $phrase): array
{
    $phrase = trim($phrase);

    if ($phrase === "") {
        return [];
    }

    $limit = (int) apply_filters("eic_ps_content_search_limit", 20);

    $ids = get_posts([
        "post_type" => ["post", "page", "what-is-cmt", "breathing", "glossary"],
        "post_status" => "publish",
        "posts_per_page" => $limit,
        "fields" => "ids",
        "s" => $phrase,
    ]);

    // Prime post cache (posts only) for excerpt/permalink building
    if (!empty($ids)) {
        _prime_post_caches($ids, false, false);
    }

    return $ids;
}

/**
 * ============================================================
 *  Gene Browser Page URL Resolver
 * ============================================================
 *
 * Mirrors eic_subtype_browser_page_url(): finds the published
 * page hosting [gene_browser], with a slug fallback.
 */
function eic_ps_gene_browser_page_url(): string
{
    static $url = null;

    if ($url !== null) {
        return $url;
    }

    $found = get_posts([
        "post_type" => "page",
        "post_status" => "publish",
        "posts_per_page" => 20,
        "no_found_rows" => true,
        "s" => "gene_browser",
    ]);

    foreach ($found as $p) {
        if (has_shortcode($p->post_content, "gene_browser")) {
            return $url = get_permalink($p->ID);
        }
    }

    return $url = home_url("/genetics/cmt-gene-browser/");
}

/**
 * ============================================================
 *  Terminal Pair Permutations Helper
 * ============================================================
 *
 * Generates acceptable suffix variants (1A ↔ A1, IA ↔ AI).
 * Top-level so the resolver can run more than once per request.
 */
function eic_ps_generate_suffix_variants($value)
{
    $variants = [$value];

    // Match trailing (roman|number)(letter) or (letter)(roman|number)
    if (preg_match('/^(.*?)([0-9]+|i{1,4})([a-z])$/i', $value, $m)) {
        $variants[] = $m[1] . $m[3] . $m[2];
    } elseif (preg_match('/^(.*?)([a-z])([0-9]+|i{1,4})$/i', $value, $m)) {
        $variants[] = $m[1] . $m[3] . $m[2];
    }

    return array_unique($variants);
}

/**
 * ============================================================
 *  Resolver Registry (ordered; first hit wins)
 * ============================================================
 *
 * Contract: a resolver returns an ARRAY (payload — may be empty)
 * to stop resolution, or NULL to pass to the next resolver.
 * Order encodes intent priority and is behavior-critical.
 */
function eic_ps_intent_resolvers(): array
{
    return [
        "eic_ps_resolve_phrase_clamps",
        "eic_ps_resolve_chromosome",
        "eic_ps_resolve_inheritance_clamp",
        "eic_ps_resolve_neuropathy_clamp",
        "eic_ps_resolve_subtype_tokens",
        "eic_ps_resolve_bare_cmt",
        "eic_ps_resolve_type_clamp",
        "eic_ps_resolve_semantics",
        "eic_ps_resolve_type_wholequery",
        "eic_ps_resolve_gene_symbol",
        "eic_ps_resolve_gene_alias",
        "eic_ps_resolve_extended",
        "eic_ps_resolve_typo_fallback",
    ];
}

/**
 * Shared per-query context handed to every resolver.
 */
function eic_ps_build_context(string $normalized_query): array
{
    return [
        "tokens" => preg_split("/\s+/", $normalized_query),

        /**
         * Jurisdiction gate: dominant/recessive + intermediate
         * compound constructs decline inheritance AND neuropathy
         * authority and fall through to semantic resolution
         * (e.g. Dominant Intermediate A → CMT2GG).
         */
        "compound_intermediate" =>
            strpos($normalized_query, "intermediate") !== false &&
            (strpos($normalized_query, "dominant") !== false ||
                strpos($normalized_query, "recessive") !== false),
    ];
}

/**
 * ============================================================
 *  Entry Point
 * ============================================================
 */
function eic_platform_search_variable_subtypes(string $normalized_query): array
{
    $ctx = eic_ps_build_context($normalized_query);

    foreach (eic_ps_intent_resolvers() as $resolver) {
        $payload = $resolver($normalized_query, $ctx);

        if ($payload !== null) {
            return $payload;
        }
    }

    return [];
}

/**
 * ============================================================
 *  Resolver: Phrase-Level Semantic Hard Clamps
 * ============================================================
 *
 * - Dominant/Recessive Intermediate A → CMTDIA semantics
 * - KIF1B (+ CMT noise) → CMT2A legacy resolution
 * MUST run before all other resolution.
 */
function eic_ps_resolve_phrase_clamps(string $q, array $ctx): ?array
{
    if (
        preg_match(
            "/\b(dominant|recessive)\s+intermediate\s+(?:cmt\s+)?a\b|\b(dominant|recessive)\s+intermediate\s+a\s+cmt\b/i",
            $q
        )
    ) {
        return eic_ps_semantic_run("cmtdia", $q);
    }

    if (
        preg_match(
            "/\b(?:kif[\s\-_]*1b)\b.*\b(cmt)\b|\b(cmt)\b.*\b(?:kif[\s\-_]*1b)\b/i",
            $q
        )
    ) {
        return eic_ps_semantic_run("cmt2a_legacy", $q);
    }

    return null;
}

/**
 * ============================================================
 *  Resolver: Chromosome Intent (numeric-only, authoritative)
 * ============================================================
 *
 * "chromosome" / "chr" is NOT a taxonomy term. If detected,
 * chromosome intent resolves alone and resolution stops.
 */
function eic_ps_resolve_chromosome(string $q, array $ctx): ?array
{
    if (!preg_match("/\b(chr|chromosome)\b/", $q)) {
        return null;
    }

    $chromosome_matches = [];
    $content_matches = [];
    $term = null;
    $highlight = [];

    foreach ($ctx["tokens"] as $token) {
        // Only numeric tokens are valid chromosome identifiers
        if (!ctype_digit($token)) {
            continue;
        }

        $term = get_term_by("slug", $token, "chromosome");

        if (!$term || is_wp_error($term)) {
            continue;
        }

        // Subtype clamp (authoritative)
        $chromosome_matches = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "tax_query" => [
                [
                    "taxonomy" => "chromosome",
                    "field" => "term_id",
                    "terms" => [$term->term_id],
                ],
            ],
        ]);

        // Content resolution (search-based, parity with inheritance/type)
        $content_matches = eic_ps_content_search("chromosome " . $token);
        $highlight = ["chromosome " . $token];

        break; // numeric chromosome found → stop token scan
    }

    // HARD STOP — chromosome intent is authoritative
    return [
        "subtypes" => array_values(array_unique($chromosome_matches)),
        "content" => array_values(array_unique($content_matches)),
        "meta" => [
            "label" => "Chromosome",
            "note" => "chromosome classification",
            "highlight" => $highlight,
            "browser_filter" =>
                $term && !is_wp_error($term) && isset($term->term_id)
                    ? [
                        "param" => "chromosome",
                        "term_id" => $term->term_id,
                        "slug" => $term->slug,
                    ]
                    : null,
        ],
    ];
}

/**
 * ============================================================
 *  Resolver: Inheritance Compound Clamp (authoritative)
 * ============================================================
 *
 * Signals are matched against the NORMALIZED query, where
 * hyphens have already become spaces ("x-linked" → "x linked").
 * Declined entirely for compound intermediate constructs.
 */
function eic_ps_resolve_inheritance_clamp(string $q, array $ctx): ?array
{
    if ($ctx["compound_intermediate"]) {
        return null; // jurisdiction gate: defer to semantics
    }

    $inheritance_map = [
        "autosomal-dominant" => ["autosomal", "dominant"],
        "autosomal-recessive" => ["autosomal", "recessive"],
        "x-linked-dominant" => ["x linked", "dominant"],
        "x-linked-recessive" => ["x linked", "recessive"],
    ];

    foreach ($inheritance_map as $term_slug => $signals) {
        $matched = true;

        foreach ($signals as $signal) {
            if (strpos($q, $signal) === false) {
                $matched = false;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        $term = get_term_by("slug", $term_slug, "inheritance");

        if (!$term || is_wp_error($term)) {
            continue;
        }

        // Clamp subtypes (authoritative)
        $inheritance_matches = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "tax_query" => [
                [
                    "taxonomy" => "inheritance",
                    "field" => "term_id",
                    "terms" => [$term->term_id],
                ],
            ],
        ]);

        // Resolve inheritance content (all valid carriers)
        $content_matches = eic_ps_content_search(
            str_replace("-", " ", $term_slug)
        );

        return [
            "subtypes" => array_values(array_unique($inheritance_matches)),
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => ucwords(str_replace("-", " ", $term_slug)),
                "note" => "inheritance pattern",
                // Prose may spell it either way
                "highlight" => [
                    str_replace("-", " ", $term_slug),
                    $term_slug,
                ],
                "browser_filter" => [
                    "param" => "inheritance",
                    "term_id" => $term->term_id,
                    "slug" => $term_slug,
                ],
            ],
        ];
    }

    return null;
}

/**
 * ============================================================
 *  Resolver: Neuropathy Clamp (authoritative)
 * ============================================================
 *
 * Mirrors inheritance semantic behavior (taxonomy: neuropathy).
 * Declined entirely for compound intermediate constructs.
 */
function eic_ps_resolve_neuropathy_clamp(string $q, array $ctx): ?array
{
    if ($ctx["compound_intermediate"]) {
        return null; // jurisdiction gate: defer to semantics
    }

    $neuropathy_terms = ["demyelinating", "axonal", "intermediate"];

    foreach ($neuropathy_terms as $term_slug) {
        if (strpos($q, $term_slug) === false) {
            continue;
        }

        $term = get_term_by("slug", $term_slug, "neuropathy");

        if (!$term || is_wp_error($term)) {
            continue;
        }

        // Subtype clamp (authoritative)
        $neuropathy_matches = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "tax_query" => [
                [
                    "taxonomy" => "neuropathy",
                    "field" => "term_id",
                    "terms" => [$term->term_id],
                ],
            ],
        ]);

        // Content resolution (search-based, parity with inheritance)
        $content_matches = eic_ps_content_search(
            str_replace("-", " ", $term_slug)
        );

        // HARD STOP — neuropathy intent is authoritative
        return [
            "subtypes" => array_values(array_unique($neuropathy_matches)),
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => ucwords($term_slug),
                "note" => "neuropathy type",
                "highlight" => [$term_slug],
                "browser_filter" => [
                    "param" => "neuropathy",
                    "term_id" => $term->term_id,
                    "slug" => $term_slug,
                ],
            ],
        ];
    }

    return null;
}

/**
 * ============================================================
 *  Resolver: Subtype Semantic Intent (authoritative)
 * ============================================================
 *
 * Detects explicit subtype references (CMT1A, CMT-2A2B, HSN1A,
 * dHMN-IA) regardless of punctuation, spacing, number-letter
 * order, or bare terminal-pair input (4C, C4). Prevents gene
 * bleed and type-wide widening.
 */
function eic_ps_resolve_subtype_tokens(string $q, array $ctx): ?array
{
    // Normalize query tokens (strip noise, preserve structure)
    $normalized_tokens = [];

    foreach ($ctx["tokens"] as $token) {
        $clean = preg_replace("/[^a-z0-9]/", "", strtolower($token));

        if ($clean === "") {
            continue;
        }

        // Reject bare "cmt" only
        if ($clean === "cmt") {
            continue;
        }

        $normalized_tokens[] = $clean;
    }

    $matched_subtypes = [];
    $types = [];
    $content_hits = [];
    $highlight = [];

    foreach (eic_ps_all_subtype_ids() as $subtype_id) {
        $raw_subtype = eic_ps_subtype_field($subtype_id, "subtype");

        if ($raw_subtype === "") {
            continue;
        }

        // Normalize subtype
        $subtype_norm = preg_replace(
            "/[^a-z0-9]/",
            "",
            strtolower($raw_subtype)
        );

        // Generate acceptable forms (handles 1A ↔ A1, IA ↔ AI)
        $candidates = eic_ps_generate_suffix_variants($subtype_norm);

        // --------------------------------------------
        // Match logic:
        // 1) Exact token match
        // 2) Terminal-pair suffix match (authoritative)
        // --------------------------------------------
        $matched = false;

        foreach ($normalized_tokens as $token) {
            if (in_array($token, $candidates, true)) {
                $matched = true;
                break;
            }

            foreach ($candidates as $candidate) {
                if (strlen($token) >= 2 && str_ends_with($candidate, $token)) {
                    $matched = true;
                    break 2;
                }
            }
        }

        if (!$matched) {
            continue;
        }

        $matched_subtypes[] = $subtype_id;

        // Resolve parent type
        $type = eic_ps_subtype_field($subtype_id, "type_classification");
        if ($type !== "") {
            $types[] = strtolower(trim($type));
        }

        // Content resolution (subtype-specific)
        $content_hits = array_merge(
            $content_hits,
            eic_ps_content_search($raw_subtype)
        );
        $highlight[] = $raw_subtype;
    }

    // HARD STOP — subtype intent is most specific
    if (!empty($matched_subtypes)) {
        return [
            "subtypes" => array_values(array_unique($matched_subtypes)),
            "types" => array_values(array_unique($types)),
            "content" => array_values(array_unique($content_hits)),
            "meta" => [
                "note" => "explicit subtype",
                "highlight" => array_values(array_unique($highlight)),
            ],
        ];
    }

    return null;
}

/**
 * ============================================================
 *  Resolver: Bare-CMT Discriminator Gate
 * ============================================================
 *
 * A query whose only genetic namespace token is bare "cmt"
 * (no digits anywhere) is blocked from genetic resolution and
 * answered with general content instead.
 */
function eic_ps_resolve_bare_cmt(string $q, array $ctx): ?array
{
    $has_cmt = false;
    $has_genetic_discriminator = false;

    foreach ($ctx["tokens"] as $token) {
        $clean = preg_replace("/[^a-z0-9]/", "", strtolower($token));

        if ($clean === "") {
            continue;
        }

        // Explicit genetic namespace forms (cmt1a, cmt2e, cmtx1, ...)
        if (preg_match('/^cmt[0-9]+[a-z]*$/', $clean)) {
            $has_genetic_discriminator = true;
            continue;
        }

        // Other genetic discriminators (1a, a1, hsn1a, dhmn2, ...)
        if (preg_match("/[0-9]/", $clean)) {
            $has_genetic_discriminator = true;
            continue;
        }

        // Bare namespace token
        if ($clean === "cmt") {
            $has_cmt = true;
        }
    }

    // Only block when it is *truly* bare CMT
    if (!$has_cmt || $has_genetic_discriminator) {
        return null;
    }

    $content_q = trim(preg_replace("/\bcmt\b/i", "", $q));
    if ($content_q === "") {
        $content_q = $q;
    }

    $content_matches = eic_ps_content_search($content_q);

    if (!empty($content_matches)) {
        return [
            "subtypes" => [],
            "types" => [],
            "genes" => [],
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => "General content search",
                "note" => "bare CMT blocked from genetic resolution",
                "highlight" => [$content_q],
            ],
        ];
    }

    return [];
}

/**
 * ============================================================
 *  Resolver: Type Classification Clamp (authoritative)
 * ============================================================
 *
 * Detects CMT type classification (CMT1, CMT2, CMT4, CMTX, etc.)
 * anywhere in the query, regardless of word order or noise.
 */
function eic_ps_resolve_type_clamp(string $q, array $ctx): ?array
{
    $type_anchor_map = eic_ps_type_classification_anchors();

    foreach ($ctx["tokens"] as $token) {
        $token_normalized = strtolower(str_replace(["-", "_"], "", $token));

        if ($token_normalized === "cmt") {
            continue;
        }

        if (!isset($type_anchor_map[$token_normalized])) {
            continue;
        }

        /**
         * Exception: Archaic Roussy-Lévy semantic override.
         * A type classification alongside "roussy"/"levy" defers
         * to semantic resolution instead of clamping by type.
         */
        if (
            strpos($q, "roussy") !== false ||
            strpos($q, "levy") !== false ||
            strpos($q, "levi") !== false
        ) {
            return null; // bypass type clamp, allow semantics to resolve
        }

        $type_slug = $type_anchor_map[$token_normalized];

        // Subtype clamp (authoritative)
        $type_matches = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "type_classification",
                    "value" => $type_slug,
                    "compare" => "=",
                ],
            ],
        ]);

        // Content resolution (search-based, parity with inheritance)
        $content_matches = eic_ps_content_search($type_slug);

        // Browser handoff filter (cmt_type taxonomy term, if present)
        $type_term = get_term_by("slug", $type_slug, "cmt_type");

        // HARD STOP — type classification is authoritative
        return [
            "subtypes" => array_values(array_unique($type_matches)),
            "types" => [$type_slug],
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => strtoupper($type_slug),
                "note" => "type classification",
                "highlight" => [$type_slug],
                "browser_filter" =>
                    $type_term && !is_wp_error($type_term)
                        ? [
                            "param" => "cmt_type",
                            "term_id" => $type_term->term_id,
                            "slug" => $type_slug,
                        ]
                        : null,
            ],
        ];
    }

    return null;
}

/**
 * ============================================================
 *  Resolver: Semantic Variables (data-driven)
 * ============================================================
 */
function eic_ps_resolve_semantics(string $q, array $ctx): ?array
{
    foreach (array_keys(eic_ps_semantic_table()) as $key) {
        $payload = eic_ps_semantic_run($key, $q);

        if (!empty($payload)) {
            return $payload;
        }
    }

    return null;
}

/**
 * ============================================================
 *  Resolver: Type Classification (whole-query form)
 * ============================================================
 *
 * Legacy whole-query discovery ("cmt1", "cmt 2"). Mostly covered
 * by the token clamp above; kept for parity.
 */
function eic_ps_resolve_type_wholequery(string $q, array $ctx): ?array
{
    $type = strtolower(str_replace(" ", "", $q));

    // allow cmt1, cmt2, cmt4, cmtx, etc.
    if (!preg_match('/^cmt[0-9x]+$/', $type)) {
        return null;
    }

    $type_matches = get_posts([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "fields" => "ids",
        "meta_query" => [
            [
                "key" => "type_classification",
                "value" => $type,
                "compare" => "=",
            ],
        ],
    ]);

    if (empty($type_matches)) {
        return null;
    }

    return [
        "subtypes" => array_values(array_unique($type_matches)),
        "types" => [$type],
    ];
}

/**
 * ============================================================
 *  Gene Token Extraction (shared by symbol + alias resolvers)
 * ============================================================
 */
function eic_ps_gene_tokens(array $tokens): array
{
    $gene_tokens = [];

    foreach ($tokens as $token) {
        if (strlen($token) >= 3 && strlen($token) <= 6 && ctype_alnum($token)) {
            $gene_tokens[] = strtoupper($token);
        }
    }

    return $gene_tokens;
}

/**
 * ============================================================
 *  Resolver: Gene Symbol → Subtype Discovery (authoritative)
 * ============================================================
 */
function eic_ps_resolve_gene_symbol(string $q, array $ctx): ?array
{
    $gene_matches = [];
    $types = [];
    $content_ids = [];
    $highlight = [];

    foreach (eic_ps_gene_tokens($ctx["tokens"]) as $gene_symbol) {
        $matches = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "gene_symbol",
                    "value" => $gene_symbol,
                    "compare" => "=",
                ],
            ],
        ]);

        if (empty($matches)) {
            continue;
        }

        $gene_matches = array_merge($gene_matches, $matches);

        foreach ($matches as $subtype_id) {
            $type = eic_ps_subtype_field($subtype_id, "type_classification");
            if ($type !== "") {
                $types[] = strtolower(trim($type));
            }
        }

        // Content resolution (search-based, parity with inheritance/type)
        $content_hits = eic_ps_content_search($gene_symbol);
        $highlight[] = $gene_symbol;

        if (!empty($content_hits)) {
            $content_ids = array_merge($content_ids, $content_hits);
        }
    }

    if (empty($gene_matches)) {
        return null;
    }

    return [
        "subtypes" => array_values(array_unique($gene_matches)),
        "types" => array_values(array_unique($types)),
        "content" => array_values(array_unique($content_ids)),
        "meta" => [
            "highlight" => array_values(array_unique($highlight)),
        ],
    ];
}

/**
 * ============================================================
 *  Resolver: Gene Alias → Subtype Discovery
 * ============================================================
 *
 * Aliases behave exactly like gene symbols for resolution,
 * but NEVER render as a gene bucket entry.
 */
function eic_ps_resolve_gene_alias(string $q, array $ctx): ?array
{
    $gene_matches = [];
    $types = [];
    $content_ids = [];
    $highlight = [];

    foreach (eic_ps_gene_tokens($ctx["tokens"]) as $gene_symbol) {
        $normalized = strtoupper(trim($gene_symbol));

        $alias_matches = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "gene_alias",
                    "value" =>
                        "(^|,)\s*" . preg_quote($normalized, "/") . '\s*(,|$)',
                    "compare" => "REGEXP",
                ],
            ],
        ]);

        if (empty($alias_matches)) {
            continue;
        }

        $gene_matches = array_merge($gene_matches, $alias_matches);

        foreach ($alias_matches as $subtype_id) {
            $type = eic_ps_subtype_field($subtype_id, "type_classification");
            if ($type !== "") {
                $types[] = strtolower(trim($type));
            }
        }

        // Content resolution (search-based, parity with gene symbol)
        $content_hits = eic_ps_content_search($normalized);
        $highlight[] = $normalized;

        if (!empty($content_hits)) {
            $content_ids = array_merge($content_ids, $content_hits);
        }
    }

    if (empty($gene_matches)) {
        return null;
    }

    return [
        "subtypes" => array_values(array_unique($gene_matches)),
        "types" => array_values(array_unique($types)),
        "content" => array_values(array_unique($content_ids)),
        "meta" => [
            "highlight" => array_values(array_unique($highlight)),
        ],
    ];
}

/**
 * ============================================================
 *  Resolver: Extended Discovery (accumulating, last resort)
 * ============================================================
 *
 * Number-letter patterns, ACF metadata (year, publications,
 * authors), single-term inheritance widening, taxonomy allowlist,
 * and the general content fallback. Always terminal.
 */
function eic_ps_resolve_extended(string $q, array $ctx): ?array
{
    $tokens = $ctx["tokens"];

    $matches = [];
    $resolved_subtype_ids = [];
    $resolved_content_ids = [];
    $highlight = [];

    // ------------------------------------------------------------
    // Number–letter discovery (strict tokens: 1a, 2e, x1)
    // ------------------------------------------------------------
    if (preg_match('/^[0-9]+[a-z]$|^[a-z][0-9]+$/', $q)) {
        foreach (eic_ps_all_subtype_ids() as $subtype_id) {
            $slug = get_post_field("post_name", $subtype_id);
            $title = get_the_title($subtype_id);

            $slug_normalized = str_replace(
                " ",
                "",
                eic_platform_search_normalize_input($slug)
            );

            $title_normalized = str_replace(
                " ",
                "",
                eic_platform_search_normalize_input($title)
            );

            if (
                strpos($slug_normalized, $q) !== false ||
                strpos($title_normalized, $q) !== false
            ) {
                $matches[] = $subtype_id;
            }
        }
    }

    // ------------------------------------------------------------
    // Resolver: Year of Discovery (4-digit tokens)
    // ------------------------------------------------------------
    foreach ($tokens as $token) {
        if (!ctype_digit($token) || strlen($token) !== 4) {
            continue;
        }

        $year_matches = get_posts([
            "post_type" => "subtype",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "year_of_discovery",
                    "value" => (string) (int) $token,
                    "compare" => "=",
                ],
            ],
        ]);

        if (!empty($year_matches)) {
            $resolved_subtype_ids = array_merge(
                $resolved_subtype_ids,
                $year_matches
            );
        }
    }

    // ------------------------------------------------------------
    // Resolvers: Publication metadata (LIKE, per token >= 3 chars)
    // NOTE: ACF author field names are 'authors' / 'alt_authors'.
    // ------------------------------------------------------------
    $publication_keys = [
        "publication_title",
        "authors",
        "alt_publication_title",
        "alt_authors",
    ];

    foreach ($publication_keys as $meta_key) {
        foreach ($tokens as $token) {
            if (strlen($token) < 3) {
                continue;
            }

            $pub_matches = get_posts([
                "post_type" => "subtype",
                "posts_per_page" => -1,
                "fields" => "ids",
                "meta_query" => [
                    [
                        "key" => $meta_key,
                        "value" => $token,
                        "compare" => "LIKE",
                    ],
                ],
            ]);

            if (!empty($pub_matches)) {
                $resolved_subtype_ids = array_merge(
                    $resolved_subtype_ids,
                    $pub_matches
                );
            }
        }
    }

    // ------------------------------------------------------------
    // Inheritance single-term widener (umbrella intent)
    // Widens subtypes + content; no clamp.
    // ------------------------------------------------------------
    $inheritance_single_map = [
        "autosomal" => ["autosomal-dominant", "autosomal-recessive"],
        "dominant" => ["autosomal-dominant", "x-linked-dominant"],
        "recessive" => ["autosomal-recessive", "x-linked-recessive"],
        "x-linked" => ["x-linked-dominant", "x-linked-recessive"],
    ];

    // Content phrases MUST be non-hyphenated
    $inheritance_content_terms = [
        "autosomal" => ["autosomal"],
        "dominant" => ["autosomal dominant", "x linked dominant"],
        "recessive" => ["autosomal recessive", "x linked recessive"],
        "x-linked" => ["x linked"],
    ];

    // Collect matching widener keys. "x-linked" is a two-word phrase
    // after normalization, so it is detected on the query, not tokens.
    $widener_keys = [];

    foreach ($tokens as $token) {
        if (isset($inheritance_single_map[$token])) {
            $widener_keys[$token] = true;
        }
    }

    if (strpos($q, "x linked") !== false) {
        $widener_keys["x-linked"] = true;
    }

    foreach (array_keys($widener_keys) as $token) {
        // Subtype widening (taxonomy-based)
        foreach ($inheritance_single_map[$token] as $term_slug) {
            $term = get_term_by("slug", $term_slug, "inheritance");

            if (!$term || is_wp_error($term)) {
                continue;
            }

            $widener_matches = get_posts([
                "post_type" => "subtype",
                "post_status" => "publish",
                "posts_per_page" => -1,
                "fields" => "ids",
                "tax_query" => [
                    [
                        "taxonomy" => "inheritance",
                        "field" => "term_id",
                        "terms" => [$term->term_id],
                    ],
                ],
            ]);

            if (!empty($widener_matches)) {
                $resolved_subtype_ids = array_merge(
                    $resolved_subtype_ids,
                    $widener_matches
                );
            }
        }

        // Content widening (search-based, non-hyphenated)
        if (isset($inheritance_content_terms[$token])) {
            foreach ($inheritance_content_terms[$token] as $phrase) {
                $content_hits = eic_ps_content_search($phrase);

                // Prose may spell the phrase either way
                $highlight[] = $phrase;
                $highlight[] = str_replace(" ", "-", $phrase);

                if (!empty($content_hits)) {
                    $resolved_content_ids = array_merge(
                        $resolved_content_ids,
                        $content_hits
                    );
                }
            }
        }
    }

    // ------------------------------------------------------------
    // Taxonomy resolution (subtype-anchored, allowlist only)
    // ------------------------------------------------------------
    $allowed_taxonomies = ["inheritance", "neuropathy", "chromosome"];

    foreach ($tokens as $token) {
        if (strlen($token) < 2) {
            continue;
        }

        foreach ($allowed_taxonomies as $taxonomy) {
            $term = get_term_by("name", $token, $taxonomy);

            if (!$term || is_wp_error($term)) {
                $term = get_term_by("slug", sanitize_title($token), $taxonomy);
            }

            if (!$term || is_wp_error($term)) {
                continue;
            }

            $tax_matches = get_posts([
                "post_type" => "subtype",
                "post_status" => "publish",
                "posts_per_page" => -1,
                "fields" => "ids",
                "tax_query" => [
                    [
                        "taxonomy" => $taxonomy,
                        "field" => "term_id",
                        "terms" => [$term->term_id],
                    ],
                ],
            ]);

            if (!empty($tax_matches)) {
                $resolved_subtype_ids = array_merge(
                    $resolved_subtype_ids,
                    $tax_matches
                );
            }
        }
    }

    // ------------------------------------------------------------
    // WordPress content fallback (authoritative last resort)
    // ------------------------------------------------------------
    if (
        empty($matches) &&
        empty($resolved_subtype_ids) &&
        empty($resolved_content_ids)
    ) {
        $content_matches = eic_ps_content_search($q);

        if (!empty($content_matches)) {
            // Highlight the whole phrase, plus distinctive long tokens
            $fallback_highlight = [$q];
            foreach ($tokens as $token) {
                if (count($tokens) > 1 && strlen($token) >= 5) {
                    $fallback_highlight[] = $token;
                }
            }

            return [
                "subtypes" => [],
                "content" => array_values(array_unique($content_matches)),
                "meta" => [
                    "label" => "General content search",
                    "note" => "no semantic intent detected",
                    "highlight" => array_values(
                        array_unique($fallback_highlight)
                    ),
                ],
            ];
        }

        return null; // nothing anywhere — let the typo tier try
    }

    // ------------------------------------------------------------
    // Final return (merge basic + ACF resolution)
    // ------------------------------------------------------------
    $final_subtypes = array_values(
        array_unique(array_merge($matches, $resolved_subtype_ids))
    );
    $final_content = array_values(array_unique($resolved_content_ids));

    if (empty($final_subtypes) && empty($final_content)) {
        return null; // nothing anywhere — let the typo tier try
    }

    /**
     * Widener union → gene browser inheritance modes.
     * Single-concept queries ("dominant") widen across taxonomy
     * terms the Subtype Browser cannot express as one filter, but
     * the Gene Browser's gb_inh facet is multi-value (AD,XLD).
     */
    $widener_mode_map = [
        "autosomal" => ["AD", "AR"],
        "dominant" => ["AD", "XLD"],
        "recessive" => ["AR", "XLR"],
        "x-linked" => ["XLD", "XLR"],
    ];

    $gb_inh_modes = [];
    foreach (array_keys($widener_keys) as $wk) {
        foreach ($widener_mode_map[$wk] ?? [] as $mode) {
            $gb_inh_modes[$mode] = true;
        }
    }

    $meta = [];
    if (!empty($gb_inh_modes)) {
        $meta["gb_inh_modes"] = array_keys($gb_inh_modes);
    }
    if (!empty($highlight)) {
        $meta["highlight"] = array_values(array_unique($highlight));
    }

    return [
        "subtypes" => $final_subtypes,
        "content" => $final_content,
        "meta" => $meta,
    ];
}

/**
 * ============================================================
 *  Resolver: Typo Fallback (last chance before "no results")
 * ============================================================
 *
 * When NOTHING resolved — no intent, no taxonomy, no gene, not
 * even prose content — fuzzy-match the query against subtype
 * names and gene symbols and, on a strong hit (exact prefix or
 * edit distance 1), serve that subtype's results with a
 * "Showing results for X" banner instead of a dead end.
 *
 * Weak hits (distance 2) stay in the no-results "Did you mean"
 * suggestions; this tier only auto-corrects when confident.
 */
function eic_ps_resolve_typo_fallback(string $q, array $ctx): ?array
{
    $tokens = array_values(array_filter($ctx["tokens"]));

    // Prose questions do not auto-correct
    if (empty($tokens) || count($tokens) > 3) {
        return null;
    }

    if (!function_exists("eic_ps_fuzzy_candidates")) {
        return null;
    }

    $strong = [];
    foreach (eic_ps_fuzzy_candidates($q) as $cand) {
        if ($cand["score"] <= 2 && !empty($cand["subtype_ids"])) {
            $strong[] = $cand;
        }
    }

    if (empty($strong)) {
        return null;
    }

    // Keep only the best-scoring tier, capped at 3 corrections
    $best = min(array_column($strong, "score"));

    $ids = [];
    $labels = [];

    foreach ($strong as $cand) {
        if ($cand["score"] !== $best || count($labels) >= 3) {
            continue;
        }

        foreach ($cand["subtype_ids"] as $sid) {
            $ids[(int) $sid] = true;
        }
        $labels[] = $cand["label"];
    }

    if (empty($ids)) {
        return null;
    }

    $content = [];
    foreach ($labels as $label) {
        $content = array_merge($content, eic_ps_content_search($label));
    }

    return [
        "subtypes" => array_keys($ids),
        "content" => array_values(array_unique($content)),
        "meta" => [
            "note" => "fuzzy correction",
            "corrected_label" => implode(", ", $labels),
            "highlight" => $labels,
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variables — Data Table
 * ============================================================
 *
 * One entry per curated concept, in priority order. Adding the
 * next retracted gene or archaic name is a data edit here.
 *
 * Match spec (any hit wins):
 *   replacements — ordered from→to pairs applied to the
 *                  normalized query BEFORE matching (typo repair)
 *   tokens       — whole-token matches (short, collision-prone keys)
 *   substrings   — matched anywhere in the space-collapsed query
 *                  (DELIBERATELY loose: patients resolving their
 *                  own diagnosis; do not tighten without asking)
 *
 * Payload spec (slugs resolved at match time):
 *   subtypes / genes — [slug, post_type] pairs (missing → null id)
 *   content          — [slug, post_type] pairs
 *   content_required — payload declines entirely when a content
 *                      target is missing (archaic content-only vars)
 *   types / meta     — literal
 *   resolve          — callable overriding payload build (dynamic
 *                      sets, e.g. the ARS gene panel query)
 *
 * Admin-managed rows (Settings → EIC Search, via the
 * eic-search-tools MU-plugin) compile into this same entry shape
 * and are merged AHEAD of the code table: an admin row for a term
 * wins over the built-in vocabulary.
 */
function eic_ps_semantic_table(): array
{
    static $table = null;

    if (is_array($table)) {
        return $table;
    }

    $admin = function_exists("eic_search_alias_entries")
        ? eic_search_alias_entries()
        : [];

    return $table = array_merge($admin, eic_ps_semantic_code_table());
}

/**
 * The code-side (version-controlled) semantic vocabulary.
 */
function eic_ps_semantic_code_table(): array
{
    return [
        "cmt_1f_2e" => [
            "match" => [
                "substrings" => ["1f2e", "2e1f", "cmt1f2e", "cmt2e1f"],
            ],
            "payload" => [
                "subtypes" => [["cmt1f", "subtype"], ["cmt2e", "subtype"]],
                "genes" => [["nefl", "subtype"]],
                "types" => ["cmt1", "cmt2"],
                "content" => [["1f-2e", "what-is-cmt"]],
                "meta" => [
                    "label" => "CMT1F/CMT2E (NEFL)",
                    "note" => "semantic variable",
                ],
            ],
            "highlight" => ["CMT1F", "CMT2E", "NEFL", "1F/2E"],
            "content_search" => ["nefl"],
        ],

        "sord" => [
            "match" => [
                // Common misspellings and homophones repair to "sord"
                "replacements" => [
                    " " => "",
                    "é" => "e",
                    "sword" => "sord",
                    "swords" => "sord",
                    "soard" => "sord",
                    "soared" => "sord",
                    "soareds" => "sord",
                ],
                "substrings" => ["sord", "sorbitol"],
            ],
            "payload" => [
                "subtypes" => [["cmt-sord", "subtype"]],
                "genes" => [["sord", "subtype"]],
                "content" => [["decoding-cmt-sord", "post"]],
                "meta" => [
                    "label" => "CMT-SORD (SORD)",
                    "note" => "semantic variable",
                ],
            ],
            "highlight" => ["CMT-SORD", "SORD", "sorbitol"],
            "content_search" => ["sord"],
        ],

        "cmt3" => [
            "match" => [
                "substrings" => ["cmt3", "dss", "dejerine", "sottas"],
            ],
            "payload" => [
                "content" => [["cmt-classifications", "page"]],
                "content_required" => true,
                "meta" => [
                    "label" => "CMT3 / Dejerine-Sottas Syndrome",
                    "note" => "archaic classification (content-only)",
                    "anchor" => "cmt3",
                ],
            ],
            "highlight" => ["CMT3", "Dejerine", "Sottas"],
            "content_search" => ["dejerine"],
        ],

        "roussy_levy" => [
            "match" => [
                "substrings" => ["roussy", "levy", "levi"],
            ],
            "payload" => [
                "content" => [["cmt-classifications", "page"]],
                "content_required" => true,
                "meta" => [
                    "label" => "Roussy-Lévy Syndrome",
                    "note" => "archaic classification (content-only)",
                    "anchor" => "roussy-levy",
                ],
            ],
            "highlight" => ["Roussy", "Lévy", "Levy"],
            "content_search" => ["roussy"],
        ],

        "ars" => [
            "match" => [
                // Short keys match whole tokens only — substring
                // matching caused false positives ("years", "parse")
                "tokens" => ["ars", "ars1", "ars2", "trna", "trnas"],
                "substrings" => [
                    "trnasynthetase",
                    "aminacyltrnasynthetase", // common misspelling
                    "aminoacyltrnasynthetase",
                ],
            ],
            "resolve" => "eic_ps_semantic_ars_payload",
            "highlight" => ["aminoacyl", "tRNA synthetase", "ARS"],
        ],

        "cmt2a_legacy" => [
            "match" => [
                "substrings" => ["kif1b", "cmt2a1", "cmt2a2", "cmt2a2a"],
            ],
            "payload" => [
                // Historical / superseded labels resolve to CMT2A;
                // MFN2 is the current causative gene
                "subtypes" => [["cmt2a", "subtype"]],
                "genes" => [["mfn2", "subtype"]],
                // Dorsal Root confliction article, explicitly surfaced
                "content" => [["2a-confliction", "post"]],
                "meta" => [
                    "label" => "CMT2A (legacy nomenclature resolved)",
                    "note" => "semantic variable",
                ],
            ],
            "highlight" => ["KIF1B", "CMT2A1", "CMT2A2", "CMT2A", "MFN2"],
            "content_search" => ["kif1b"],
        ],

        "med25" => [
            "match" => [
                "substrings" => [
                    "med25",
                    // full historical name
                    "mediatorcomplexsubunit25",
                    "mediatorofrnapolymeraseiitranscriptionsubunit25homolog",
                    "mediatorofrnapolymeraseiitranscriptionsubunit25",
                    // HGNC aliases
                    "arc92",
                    "acid1",
                    "tcbap0758",
                    "dkfzp434k0512",
                ],
            ],
            "payload" => [
                // Retracted gene → CMT2B2; no gene surfaced by design
                "subtypes" => [["cmt2b2", "subtype"]],
                "meta" => [
                    "label" => "MED25 → CMT2B2",
                    "note" =>
                        "retracted gene (alias-aware semantic resolution)",
                ],
            ],
            "highlight" => ["MED25", "CMT2B2"],
            "content_search" => ["med25"],
        ],

        "cmtdia" => [
            "match" => [
                // NOTE: "cmta" is intentionally NOT a key — too
                // ambiguous to resolve to Dominant Intermediate A
                "substrings" => [
                    "cmtdia",
                    "dominantintermediate",
                    "dominantintermediatea",
                    "dominantintermediatecmta",
                ],
            ],
            "payload" => [
                "subtypes" => [["cmt2gg", "subtype"]],
                "genes" => [["gbf1", "subtype"]],
                "types" => ["cmt2"],
                "meta" => [
                    "label" => "Dominant Intermediate CMT A",
                    "note" => "semantic variable",
                ],
            ],
            "highlight" => ["CMT2GG", "GBF1", "dominant intermediate"],
            "content_search" => ["dominant intermediate"],
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Engine
 * ============================================================
 */

/**
 * Run one semantic table entry against the normalized query.
 * Returns its payload on match, [] otherwise.
 */
function eic_ps_semantic_run(string $key, string $normalized_query): array
{
    $table = eic_ps_semantic_table();

    if (!isset($table[$key])) {
        return [];
    }

    $entry = $table[$key];

    if (!eic_ps_semantic_matches($normalized_query, $entry["match"] ?? [])) {
        return [];
    }

    $payload =
        !empty($entry["resolve"]) && is_callable($entry["resolve"])
            ? (array) call_user_func($entry["resolve"], $normalized_query)
            : eic_ps_semantic_build_payload($entry["payload"] ?? []);

    if (empty($payload)) {
        return [];
    }

    // Entry-level highlight terms surface in content excerpts
    if (!empty($entry["highlight"])) {
        $payload["meta"]["highlight"] = array_values(
            array_unique(
                array_merge(
                    (array) ($payload["meta"]["highlight"] ?? []),
                    $entry["highlight"]
                )
            )
        );
    }

    // Widen content with prose mentions (curated entries stay first)
    if (!empty($entry["content_search"])) {
        $curated = array_values(
            array_filter((array) ($payload["content"] ?? []))
        );

        // A meta anchor belongs to the curated targets only, never
        // to the widened prose hits
        if (!empty($payload["meta"]["anchor"]) && !empty($curated)) {
            $payload["meta"]["anchor_ids"] = $curated;
        }

        $extra = [];
        foreach ($entry["content_search"] as $phrase) {
            $extra = array_merge($extra, eic_ps_content_search($phrase));
        }

        $payload["content"] = array_values(
            array_unique(
                array_merge((array) ($payload["content"] ?? []), $extra)
            )
        );
    }

    return $payload;
}

/**
 * Match a semantic entry's spec against the normalized query.
 */
function eic_ps_semantic_matches(string $normalized_query, array $spec): bool
{
    $q = $normalized_query;

    // Typo/homophone repair (ordered pairs; may also collapse spaces)
    if (!empty($spec["replacements"])) {
        $q = str_replace(
            array_keys($spec["replacements"]),
            array_values($spec["replacements"]),
            $q
        );
    }

    // Whole-token keys (collision-prone short forms)
    if (!empty($spec["tokens"])) {
        foreach (preg_split("/\s+/", $q) as $tok) {
            if (in_array($tok, $spec["tokens"], true)) {
                return true;
            }
        }
    }

    // Substring keys on the space-collapsed query (deliberately loose)
    if (!empty($spec["substrings"])) {
        $collapsed = str_replace(" ", "", $q);

        foreach ($spec["substrings"] as $needle) {
            if (strpos($collapsed, $needle) !== false) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Build a declarative payload: resolve slug references to IDs.
 * Missing references become null IDs (skipped downstream) unless
 * content_required is set, in which case the whole payload declines.
 */
function eic_ps_semantic_build_payload(array $spec): array
{
    $payload = [
        "subtypes" => [],
        "genes" => [],
        "types" => $spec["types"] ?? [],
        "content" => [],
        "meta" => $spec["meta"] ?? [],
    ];

    foreach (["subtypes", "genes"] as $bucket) {
        foreach ($spec[$bucket] ?? [] as $ref) {
            // ["SYMBOL", "gene_symbol"] pulls every subtype caused
            // by that gene (admin alias rows: gene:mfn2)
            if (($ref[1] ?? "") === "gene_symbol") {
                foreach (eic_ps_subtype_ids_for_gene($ref[0]) as $gid) {
                    $payload[$bucket][] = $gid;
                }
                continue;
            }

            $post = get_page_by_path($ref[0], OBJECT, $ref[1]);
            $payload[$bucket][] = $post->ID ?? null;
        }
    }

    foreach ($spec["content"] ?? [] as $ref) {
        $post = eic_ps_resolve_content_ref($ref);

        if (!$post && !empty($spec["content_required"])) {
            return []; // archaic content-only var: no target, no match
        }

        $payload["content"][] = $post->ID ?? null;
    }

    return $payload;
}

/**
 * Resolve a [slug, post_type] content reference. Post type "any"
 * (admin alias rows: content:slug) tries each content type in turn.
 */
function eic_ps_resolve_content_ref(array $ref): ?WP_Post
{
    $types =
        ($ref[1] ?? "") === "any"
            ? ["post", "page", "what-is-cmt", "breathing", "glossary"]
            : [$ref[1]];

    foreach ($types as $pt) {
        $post = get_page_by_path($ref[0], OBJECT, $pt);
        if ($post) {
            return $post;
        }
    }

    return null;
}

/**
 * ============================================================
 *  Semantic Payload: Aminoacyl-tRNA Synthetase (ARS)
 * ============================================================
 *
 * Dynamic set: all subtypes flagged ars_gene = true.
 */
function eic_ps_semantic_ars_payload(string $normalized_query): array
{
    /**
     * ACF true_false can store as '1' (string) but NUMERIC compare
     * is the most reliable across DBs/environments.
     */
    $ars_q = new WP_Query([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "fields" => "ids",
        "no_found_rows" => true,
        "meta_query" => [
            [
                "key" => "ars_gene", // meta key = field name
                "value" => 1,
                "type" => "NUMERIC",
                "compare" => "=",
            ],
        ],
    ]);

    if (empty($ars_q->posts)) {
        return [];
    }

    return [
        "subtypes" => array_values(array_unique($ars_q->posts)),
        "genes" => [],
        "types" => [],
        "content" => [],
        "meta" => [
            "label" => "Aminoacyl-tRNA Synthetase (ARS)",
            "note" => "semantic variable",
            // Subtype Browser has a native "ARS Genes" flag (?ars=1)
            "browser_filter" => [
                "param" => "ars",
                "term_id" => 1,
                "slug" => "1",
            ],
        ],
    ];
}
