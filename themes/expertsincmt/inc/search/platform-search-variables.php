<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Platform Search Variables Layer
 *
 * Since version: 1.8.0
 * Feature: platform-search
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
 *  Basic Variable Extension
 * ============================================================
 */
function eic_platform_search_variable_subtypes(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Phrase-level semantic hard clamp
     * Dominant / Recessive Intermediate A
     * MUST run before subtype token parsing
     * ------------------------------------------------------------
     */
    if (
        preg_match(
            "/\b(dominant|recessive)\s+intermediate\s+(?:cmt\s+)?a\b|\b(dominant|recessive)\s+intermediate\s+a\s+cmt\b/i",
            $normalized_query
        )
    ) {
        return eic_ps_semantic_cmtdia($normalized_query);
    }

    /**
     * ------------------------------------------------------------
     * Phrase-level semantic hard clamp
     * KIF1B (+ optional CMT noise) MUST resolve to CMT2A legacy
     * ------------------------------------------------------------
     */
    if (
        preg_match(
            "/\b(?:kif[\s\-_]*1b)\b.*\b(cmt)\b|\b(cmt)\b.*\b(?:kif[\s\-_]*1b)\b/i",
            $normalized_query
        )
    ) {
        return eic_ps_semantic_cmt2a_legacy($normalized_query);
    }

    // ------------------------------------------------------------
    // Tokenization (required for ACF + taxonomy resolution)
    // ------------------------------------------------------------
    $tokens = preg_split("/\s+/", $normalized_query);

    // ------------------------------------------------------------
    //Semantic variable hit flag
    // ------------------------------------------------------------
    $semantic_hit = false;

    // ------------------------------------------------------------
    // EARLY EXIT: Chromosome intent (numeric-only, authoritative)
    // ------------------------------------------------------------
    // "chromosome" / "chr" is NOT a taxonomy term.
    // If detected, we resolve chromosome intent ONLY and stop.

    if (preg_match("/\b(chr|chromosome)\b/", $normalized_query)) {
        $chromosome_matches = [];
        $content_matches = [];

        foreach ($tokens as $token) {
            // Only numeric tokens are valid chromosome identifiers
            if (!ctype_digit($token)) {
                continue;
            }

            $term = get_term_by("slug", $token, "chromosome");

            if (!$term || is_wp_error($term)) {
                continue;
            }

            // --------------------------------------------------------
            // Subtype clamp (authoritative)
            // --------------------------------------------------------
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

            // --------------------------------------------------------
            // Content resolution (search-based, parity with inheritance/type)
            // --------------------------------------------------------
            $content_matches = get_posts([
                "post_type" => [
                    "post",
                    "page",
                    "what-is-cmt",
                    "breathing",
                    "glossary",
                ],
                "post_status" => "publish",
                "posts_per_page" => -1,
                "fields" => "ids",
                "s" => "chromosome " . $token,
            ]);

            break; // numeric chromosome found → stop token scan
        }

        // HARD STOP — chromosome intent is authoritative
        return [
            "subtypes" => array_values(array_unique($chromosome_matches)),
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => "Chromosome",
                "note" => "chromosome classification",
            ],
        ];
    }

    /**
     * ============================================================
     *  Semantic Inheritance Intent (Authoritative Clamp)
     * ============================================================
     *
     * Behavior:
     * ---------
     * - Clamp subtype discovery
     * - Return semantic payload (subtypes + content)
     * - Do NOT rely on downstream fallback
     *
     * IMPORTANT:
     * ----------
     * This must run BEFORE token-based taxonomy resolution.
     */

    /**
/**
 * ------------------------------------------------------------
 *  Jurisdiction Gate:
 *  Decline inheritance authority for
 *  dominant/recessive + intermediate compound constructs
 * ------------------------------------------------------------
 */
    if (
        strpos($normalized_query, "intermediate") !== false &&
        (strpos($normalized_query, "dominant") !== false ||
            strpos($normalized_query, "recessive") !== false)
    ) {
        // Decline inheritance authority.
        // IMPORTANT: do NOT return.
        // Fall through so semantic variables can run.
    }

    $inheritance_map = [
        "autosomal-dominant" => ["autosomal", "dominant"],
        "autosomal-recessive" => ["autosomal", "recessive"],
        "x-linked-dominant" => ["x-linked", "dominant"],
        "x-linked-recessive" => ["x-linked", "recessive"],
    ];

    $inheritance_content_map = [
        "autosomal-dominant" => "autosomal-dominant",
        "autosomal-recessive" => "autosomal-recessive",
        "x-linked-dominant" => "x-linked-dominant",
        "x-linked-recessive" => "x-linked-recessive",
    ];

    foreach ($inheritance_map as $term_slug => $signals) {
        $matched = true;

        foreach ($signals as $signal) {
            if (strpos($normalized_query, $signal) === false) {
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
        $content_matches = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => str_replace("-", " ", $term_slug),
        ]);

        return [
            "subtypes" => array_values(array_unique($inheritance_matches)),
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => ucwords(str_replace("-", " ", $term_slug)),
                "note" => "inheritance pattern",
            ],
        ];
    }

    /**
     * ============================================================
     *  Neuropathy Semantic Intent (Authoritative Clamp)
     * ============================================================
     *
     * Behavior:
     * ---------
     * - Clamp subtype discovery by neuropathy type
     * - Resolve related content
     * - HARD STOP (return early)
     *
     * Notes:
     * ------
     * - Mirrors inheritance semantic behavior
     * - Uses taxonomy: neuropathy
     * - No widening, no fallthrough
     */

    /**
     * ------------------------------------------------------------
     *  Jurisdiction Gate:
     *  Decline neuropathy authority for
     *  dominant/recessive + intermediate compound constructs
     * ------------------------------------------------------------
     */
    if (
        strpos($normalized_query, "intermediate") !== false &&
        (strpos($normalized_query, "dominant") !== false ||
            strpos($normalized_query, "recessive") !== false)
    ) {
        // Decline neuropathy authority.
        // IMPORTANT: do NOT return.
        // Allow semantic variables to handle this query.
    }

    $neuropathy_terms = ["demyelinating", "axonal", "intermediate"];

    foreach ($neuropathy_terms as $term_slug) {
        if (strpos($normalized_query, $term_slug) === false) {
            continue;
        }

        $term = get_term_by("slug", $term_slug, "neuropathy");

        if (!$term || is_wp_error($term)) {
            continue;
        }

        // --------------------------------------------------------
        // Subtype clamp (authoritative)
        // --------------------------------------------------------
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

        // --------------------------------------------------------
        // Content resolution (search-based, parity with inheritance)
        // --------------------------------------------------------
        $content_matches = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => str_replace("-", " ", $term_slug),
        ]);

        // ------------------------------------------------------------
        // HARD STOP — neuropathy intent is authoritative
        // ------------------------------------------------------------
        return [
            "subtypes" => array_values(array_unique($neuropathy_matches)),
            "content" => array_values(array_unique($content_matches)),
            "meta" => [
                "label" => ucwords($term_slug),
                "note" => "neuropathy type",
            ],
        ];
    }

    /**
     * ============================================================
     *  Subtype Semantic Intent (Authoritative Clamp)
     * ============================================================
     *
     * Detects explicit subtype references (e.g. CMT1A, CMT-2A2B,
     * HSN1A, dHMN-IA) regardless of punctuation, spacing,
     * number-letter order, or bare terminal-pair input (e.g. 4C, C4).
     *
     * Prevents gene bleed and type-wide widening.
     */

    // ------------------------------------------------------------
    // Normalize query tokens (strip noise, preserve structure)
    // ------------------------------------------------------------
    $normalized_tokens = [];

    foreach ($tokens as $token) {
        $clean = preg_replace("/[^a-z0-9]/", "", strtolower($token));

        if ($clean === "") {
            continue;
        }

        /**
         * --------------------------------------------------------
         * GATE: Require genetic discriminator
         * --------------------------------------------------------
         * Reject bare namespace tokens like "cmt"
         * Allow only tokens that contain:
         * - a digit (1, 2, 4, etc), OR
         * - a terminal letter-number or number-letter pair (4c, c4)
         */
        // Reject bare "cmt" only
        if ($clean === "cmt") {
            continue;
        }

        $normalized_tokens[] = $clean;
    }

    // ------------------------------------------------------------
    // Helper: generate terminal pair permutations
    // ------------------------------------------------------------
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

    // ------------------------------------------------------------
    // Iterate all subtype posts (authoritative domain)
    // ------------------------------------------------------------
    $subtype_posts = get_posts([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "fields" => "ids",
    ]);

    $matched_subtypes = [];
    $types = [];
    $content_hits = [];

    foreach ($subtype_posts as $subtype_id) {
        $raw_subtype = get_post_meta($subtype_id, "subtype", true);

        if (!is_string($raw_subtype) || $raw_subtype === "") {
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

        // --------------------------------------------
        // Collect subtype
        // --------------------------------------------
        $matched_subtypes[] = $subtype_id;

        // --------------------------------------------
        // Resolve parent type
        // --------------------------------------------
        $type = get_post_meta($subtype_id, "type_classification", true);
        if (is_string($type) && $type !== "") {
            $types[] = strtolower(trim($type));
        }

        // --------------------------------------------
        // Content resolution (subtype-specific)
        // --------------------------------------------
        $hits = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => $raw_subtype,
        ]);

        $content_hits = array_merge($content_hits, $hits);
    }

    // ------------------------------------------------------------
    // HARD STOP — subtype intent is most specific
    // ------------------------------------------------------------
    if (!empty($matched_subtypes)) {
        return [
            "subtypes" => array_values(array_unique($matched_subtypes)),
            "types" => array_values(array_unique($types)),
            "content" => array_values(array_unique($content_hits)),
            "meta" => [
                "note" => "explicit subtype",
            ],
        ];
    }

    // ============================================================
    // CMT DISCRIMINATOR GATE (block bare CMT only)
    // ============================================================
    $has_cmt = false;
    $has_genetic_discriminator = false;

    foreach ($tokens as $token) {
        $clean = preg_replace("/[^a-z0-9]/", "", strtolower($token));

        if ($clean === "") {
            continue;
        }

        /**
         * --------------------------------------------------------
         * EXPLICIT genetic namespace forms
         * --------------------------------------------------------
         * cmt1a, cmt2e, cmt4c, cmtx1, etc.
         * These MUST count as genetically discriminated.
         */
        if (preg_match('/^cmt[0-9]+[a-z]*$/', $clean)) {
            $has_genetic_discriminator = true;
            continue;
        }

        /**
         * --------------------------------------------------------
         * Other genetic discriminators
         * --------------------------------------------------------
         * 1a, a1, hsn1a, dhmn2, etc.
         */
        if (preg_match("/[0-9]/", $clean)) {
            $has_genetic_discriminator = true;
            continue;
        }

        /**
         * --------------------------------------------------------
         * Bare namespace token
         * --------------------------------------------------------
         */
        if ($clean === "cmt") {
            $has_cmt = true;
        }
    }

    // Only block when it is *truly* bare CMT
    if ($has_cmt && !$has_genetic_discriminator && !$semantic_hit) {
        $content_q = trim(preg_replace("/\bcmt\b/i", "", $normalized_query));
        if ($content_q === "") {
            $content_q = $normalized_query;
        }

        $content_matches = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "glossary",
                "breathing",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => $content_q,
        ]);

        if (!empty($content_matches)) {
            return [
                "subtypes" => [],
                "types" => [],
                "genes" => [],
                "content" => array_values(array_unique($content_matches)),
                "meta" => [
                    "label" => "General content search",
                    "note" => "bare CMT blocked from genetic resolution",
                ],
            ];
        }

        return [];
    }

    /**
     * ============================================================
     *  Type Classification Semantic Intent (Authoritative Clamp)
     * ============================================================
     *
     * Detects CMT type classification (CMT1, CMT2, CMT4, CMTX, etc.)
     * anywhere in the query, regardless of word order or noise.
     * Mirrors inheritance + chromosome behavior.
     */

    $type_anchor_map = eic_ps_type_classification_anchors();

    foreach ($tokens as $token) {
        $token_normalized = strtolower(str_replace(["-", "_"], "", $token));

        if ($token_normalized === "cmt") {
            continue;
        }

        if (!isset($type_anchor_map[$token_normalized])) {
            continue;
        }

        /**
         * ------------------------------------------------------------
         * Exception: Archaic Roussy-Lévy semantic override
         * ------------------------------------------------------------
         * If a type classification (e.g. CMT1) appears alongside
         * "roussy" OR "levy", we must defer to semantic resolution
         * instead of clamping by type.
         */
        if (
            strpos($normalized_query, "roussy") !== false ||
            strpos($normalized_query, "levy") !== false ||
            strpos($normalized_query, "levi") !== false
        ) {
            break; // bypass type clamp, allow semantic variables to resolve
        }

        $type_slug = $type_anchor_map[$token_normalized];

        // ------------------------------------------------------------
        // Subtype clamp (authoritative)
        // ------------------------------------------------------------
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

        // ------------------------------------------------------------
        // Content resolution (search-based, parity with inheritance)
        // ------------------------------------------------------------
        $content_matches = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => $type_slug,
        ]);

        // HARD STOP — type classification is authoritative
        return [
            "subtypes" => array_values(array_unique($type_matches)),
            "types" => [$type_slug],
            "content" => array_values(array_unique($content_matches)),
        ];
    }

    // ------------------------------------------------------------
    // Accumulator for extended resolution
    // ------------------------------------------------------------
    $resolved_subtype_ids = [];
    $resolved_content_ids = [];

    /**
     * ------------------------------------------------------------
     * Semantic variable loader (explicit, curated meaning)
     * ------------------------------------------------------------
     */

    $semantic = eic_ps_semantic_cmt_1f_2e($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_sord($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_cmt3($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_roussy_levy($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_ars($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_cmt2a_legacy($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_med25($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    $semantic = eic_ps_semantic_cmtdia($normalized_query);
    if (!empty($semantic)) {
        $semantic_hit = true;
        return $semantic;
    }

    /**
     * ------------------------------------------------------------
     * Type classification → subtype discovery
     * ------------------------------------------------------------
     */
    $type = strtolower(str_replace(" ", "", $normalized_query));

    // allow cmt1, cmt2, cmt4, cmtx, etc.
    if (preg_match('/^cmt[0-9x]+$/', $type)) {
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

        if (!empty($type_matches)) {
            return [
                "subtypes" => array_values(array_unique($type_matches)),
                "types" => [$type],
            ];
        }
    }
    /**
     * ------------------------------------------------------------
     * Gene symbol → subtype discovery
     * ------------------------------------------------------------
     */
    $gene_tokens = [];

    // Extract possible gene symbols from tokens
    foreach ($tokens as $token) {
        if (strlen($token) >= 3 && strlen($token) <= 6 && ctype_alnum($token)) {
            $gene_tokens[] = strtoupper($token);
        }
    }

    $gene_matches = [];
    $types = [];

    /**
     * ------------------------------------------------------------
     * Primary gene symbol lookup (authoritative)
     * ------------------------------------------------------------
     */
    foreach ($gene_tokens as $gene_symbol) {
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

        // --------------------------------------------------------
        // Subtype + type resolution (authoritative)
        // --------------------------------------------------------
        $gene_matches = array_merge($gene_matches, $matches);

        foreach ($matches as $subtype_id) {
            $type = get_post_meta($subtype_id, "type_classification", true);
            if (is_string($type) && $type !== "") {
                $types[] = strtolower(trim($type));
            }
        }

        // --------------------------------------------------------
        // Content resolution (search-based, parity with inheritance/type)
        // --------------------------------------------------------
        $content_hits = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => $gene_symbol,
        ]);

        if (!empty($content_hits)) {
            $resolved_content_ids = array_merge(
                $resolved_content_ids ?? [],
                $content_hits
            );
        }
    }

    /**
     * ------------------------------------------------------------
     * Gene symbol lookup return (authoritative)
     * ------------------------------------------------------------
     */
    if (!empty($gene_matches)) {
        return [
            "subtypes" => array_values(array_unique($gene_matches)),
            "types" => array_values(array_unique($types)),
            "content" => array_values(
                array_unique($resolved_content_ids ?? [])
            ),
        ];
    }

    /**
     * ------------------------------------------------------------
     * Gene alias → subtype discovery
     * ------------------------------------------------------------
     * Aliases behave exactly like gene symbols for resolution,
     * but NEVER render as a gene bucket entry.
     */
    foreach ($gene_tokens as $gene_symbol) {
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

        // --------------------------------------------------------
        // Subtype + type resolution (authoritative)
        // --------------------------------------------------------
        $gene_matches = array_merge($gene_matches, $alias_matches);

        foreach ($alias_matches as $subtype_id) {
            $type = get_post_meta($subtype_id, "type_classification", true);
            if (is_string($type) && $type !== "") {
                $types[] = strtolower(trim($type));
            }
        }

        // --------------------------------------------------------
        // Content resolution (search-based, parity with gene symbol)
        // --------------------------------------------------------
        $content_hits = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => $normalized,
        ]);

        if (!empty($content_hits)) {
            $resolved_content_ids = array_merge(
                $resolved_content_ids ?? [],
                $content_hits
            );
        }
    }

    if (!empty($gene_matches)) {
        return [
            "subtypes" => array_values(array_unique($gene_matches)),
            "types" => array_values(array_unique($types)),
            "content" => array_values(
                array_unique($resolved_content_ids ?? [])
            ),
        ];
    }

    /**
     * ------------------------------------------------------------
     * 2) Basic pattern variables (number–letter discovery)
     * ------------------------------------------------------------
     */

    $matches = [];

    // Only attempt number–letter discovery for strict tokens (e.g. 1a, 2e, x1)
    if (preg_match('/^[0-9]+[a-z]$|^[a-z][0-9]+$/', $normalized_query)) {
        // Fetch all published subtypes
        $subtypes = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
        ]);

        foreach ($subtypes as $subtype_id) {
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
                strpos($slug_normalized, $normalized_query) !== false ||
                strpos($title_normalized, $normalized_query) !== false
            ) {
                $matches[] = $subtype_id;
            }
        }
    }

    // ============================================================
    // ACF METADATA RESOLUTION
    // ============================================================

    // ------------------------------------------------------------
    // Numeric Token Detection (Year-based resolution)
    // ------------------------------------------------------------
    $numeric_tokens = [];

    foreach ($tokens as $token) {
        if (ctype_digit($token) && strlen($token) === 4) {
            $numeric_tokens[] = (int) $token;
        }
    }

    // ------------------------------------------------------------
    // Resolver: Year of Discovery (ACF - Core tab)
    // ------------------------------------------------------------
    if (!empty($numeric_tokens)) {
        foreach ($numeric_tokens as $year) {
            $year_matches = get_posts([
                "post_type" => "subtype",
                "posts_per_page" => -1,
                "fields" => "ids",
                "meta_query" => [
                    [
                        "key" => "year_of_discovery",
                        "value" => (string) $year,
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
    }

    // ------------------------------------------------------------
    // Resolver: Publication Title (Primary)
    // ------------------------------------------------------------
    foreach ($tokens as $token) {
        if (strlen($token) < 3) {
            continue;
        }

        $pub_title_matches = get_posts([
            "post_type" => "subtype",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "publication_title",
                    "value" => $token,
                    "compare" => "LIKE",
                ],
            ],
        ]);

        if (!empty($pub_title_matches)) {
            $resolved_subtype_ids = array_merge(
                $resolved_subtype_ids,
                $pub_title_matches
            );
        }
    }

    // ------------------------------------------------------------
    // Resolver: Publication Authors (APA-style token matching)
    // ------------------------------------------------------------
    foreach ($tokens as $token) {
        if (strlen($token) < 3) {
            continue;
        }

        $author_matches = get_posts([
            "post_type" => "subtype",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "publication_authors",
                    "value" => $token,
                    "compare" => "LIKE",
                ],
            ],
        ]);

        if (!empty($author_matches)) {
            $resolved_subtype_ids = array_merge(
                $resolved_subtype_ids,
                $author_matches
            );
        }
    }

    // ------------------------------------------------------------
    // Resolver: Alt Publication Title
    // ------------------------------------------------------------
    foreach ($tokens as $token) {
        if (strlen($token) < 3) {
            continue;
        }

        $alt_pub_title_matches = get_posts([
            "post_type" => "subtype",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "alt_publication_title",
                    "value" => $token,
                    "compare" => "LIKE",
                ],
            ],
        ]);

        if (!empty($alt_pub_title_matches)) {
            $resolved_subtype_ids = array_merge(
                $resolved_subtype_ids,
                $alt_pub_title_matches
            );
        }
    }

    // ------------------------------------------------------------
    // Resolver: Alt Publication Authors
    // ------------------------------------------------------------
    foreach ($tokens as $token) {
        if (strlen($token) < 3) {
            continue;
        }

        $alt_author_matches = get_posts([
            "post_type" => "subtype",
            "posts_per_page" => -1,
            "fields" => "ids",
            "meta_query" => [
                [
                    "key" => "alt_publication_authors",
                    "value" => $token,
                    "compare" => "LIKE",
                ],
            ],
        ]);

        if (!empty($alt_author_matches)) {
            $resolved_subtype_ids = array_merge(
                $resolved_subtype_ids,
                $alt_author_matches
            );
        }
    }

    /**
     * ------------------------------------------------------------
     * Inheritance single-term widener (umbrella intent)
     * ------------------------------------------------------------
     * Expands single inheritance concepts into concrete
     * inheritance taxonomy terms.
     *
     * Behavior:
     * ----------
     * - Widens subtype results (no clamp)
     * - Widens content results (posts, pages, breathing, glossary, what-is-cmt)
     * - Uses non-hyphenated inheritance phrases for content
     */

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

    foreach ($tokens as $token) {
        if (!isset($inheritance_single_map[$token])) {
            continue;
        }

        /**
         * --------------------------------------------------------
         * Subtype widening (taxonomy-based)
         * --------------------------------------------------------
         */
        foreach ($inheritance_single_map[$token] as $term_slug) {
            $term = get_term_by("slug", $term_slug, "inheritance");

            if (!$term || is_wp_error($term)) {
                continue;
            }

            $matches = get_posts([
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

            if (!empty($matches)) {
                $resolved_subtype_ids = array_merge(
                    $resolved_subtype_ids,
                    $matches
                );
            }
        }

        /**
         * --------------------------------------------------------
         * Content widening (search-based, non-hyphenated)
         * --------------------------------------------------------
         */
        if (isset($inheritance_content_terms[$token])) {
            foreach ($inheritance_content_terms[$token] as $phrase) {
                $content_hits = get_posts([
                    "post_type" => [
                        "post",
                        "page",
                        "what-is-cmt",
                        "breathing",
                        "glossary",
                    ],
                    "post_status" => "publish",
                    "posts_per_page" => -1,
                    "fields" => "ids",
                    "s" => $phrase,
                ]);

                if (!empty($content_hits)) {
                    $resolved_content_ids = array_merge(
                        $resolved_content_ids ?? [],
                        $content_hits
                    );
                }
            }
        }
    }

    // ============================================================
    // TAXONOMY RESOLUTION (Subtype-anchored, allowlist only)
    // ============================================================

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
    // If NO intent, taxonomy, or ACF resolution occurred,
    // attempt a general WP search here. If still nothing, return []
    // so the upstream controller can fall through however it wants.
    if (
        empty($matches) &&
        empty($resolved_subtype_ids) &&
        empty($resolved_content_ids)
    ) {
        $content_matches = get_posts([
            "post_type" => [
                "post",
                "page",
                "what-is-cmt",
                "breathing",
                "glossary",
            ],
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "s" => $normalized_query,
        ]);

        if (!empty($content_matches)) {
            return [
                "subtypes" => [],
                "content" => array_values(array_unique($content_matches)),
                "meta" => [
                    "label" => "General content search",
                    "note" => "no semantic intent detected",
                ],
            ];
        }

        return [];
    }

    // ------------------------------------------------------------
    // Final return (merge basic + ACF resolution)
    // ------------------------------------------------------------
    $final_subtypes = array_values(
        array_unique(array_merge($matches, $resolved_subtype_ids))
    );
    $final_content = array_values(array_unique($resolved_content_ids));

    // If we resolved nothing at all, return [] so upstream can fall through
    // to general WP search (e.g., "breathing").
    if (empty($final_subtypes) && empty($final_content)) {
        return [];
    }

    return [
        "subtypes" => $final_subtypes,
        "content" => $final_content,
    ];
}

/**
 * ============================================================
 *  Semantic Variables
 * ============================================================
 */
/**
 * ============================================================
 *  Semantic Variable: CMT1F/CMT2E (NEFL)
 * ============================================================
 */
function eic_ps_semantic_cmt_1f_2e(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Normalize semantic token
     * ------------------------------------------------------------
     * Upstream normalization has already:
     * - lowercased
     * - removed punctuation (/, -, _)
     * - collapsed whitespace
     *
     * So we collapse spaces here to match canonical semantic keys.
     */
    $q = iconv("UTF-8", "ASCII//TRANSLIT", $normalized_query);
    $q = str_replace(" ", "", strtolower($q));

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     */
    $matches = ["1f2e", "2e1f", "cmt1f2e", "cmt2e1f"];

    $hit = false;

    foreach ($matches as $m) {
        if (strpos($q, $m) !== false) {
            $hit = true;
            break;
        }
    }

    if (!$hit) {
        return [];
    }

    return [
        "subtypes" => [
            get_page_by_path("cmt1f", OBJECT, "subtype")->ID ?? null,
            get_page_by_path("cmt2e", OBJECT, "subtype")->ID ?? null,
        ],
        "genes" => [get_page_by_path("nefl", OBJECT, "subtype")->ID ?? null],
        "types" => ["cmt1", "cmt2"],
        "content" => [
            get_page_by_path("1f-2e", OBJECT, "what-is-cmt")->ID ?? null,
        ],
        "meta" => [
            "label" => "CMT1F/CMT2E (NEFL)",
            "note" => "semantic variable",
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: CMT-SORD (SORD)
 * ============================================================
 */
function eic_ps_semantic_sord(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Normalize semantic token
     * ------------------------------------------------------------
     * Same normalization guarantees as all other semantic vars.
     */
    $q = str_replace(
        [" ", "é", "sword", "swords", "soard", "soared", "soareds"],
        ["", "e", "sord", "sord", "sord", "sord", "sord"],
        $normalized_query
    );

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     * Includes gene, subtype, biochemical, and colloquial signals.
     */
    $matches = [
        "sord",
        "cmtsord",
        "sordcmt",
        "cmt-sord",
        "sord-cmt",
        "sords",

        // biochemical / long-form
        "sorbitol",
        "sorbitoldehydrogenase",
        "sorbitoldehydrogenasedeficiency",
    ];

    // Allow substring match for noisy real-world inputs
    $hit =
        in_array($q, $matches, true) ||
        strpos($q, "sord") !== false ||
        strpos($q, "sorbitol") !== false;

    if (!$hit) {
        return [];
    }

    return [
        "subtypes" => [
            get_page_by_path("cmt-sord", OBJECT, "subtype")->ID ?? null,
        ],
        "genes" => [get_page_by_path("sord", OBJECT, "subtype")->ID ?? null],
        "content" => [
            get_page_by_path("decoding-cmt-sord", OBJECT, "post")->ID ?? null,
        ],
        "meta" => [
            "label" => "CMT-SORD (SORD)",
            "note" => "semantic variable",
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: CMT3 / Dejerine-Sottas Syndrome (DSS)
 * ============================================================
 */
function eic_ps_semantic_cmt3(string $normalized_query): array
{
    $q = str_replace(" ", "", $normalized_query);

    $matches = [
        "cmt3",
        "dss",
        "dejerinesottas",
        "dejerinesottassyndrome",
        "dejerine-sottas",
        "dejerine-sottas-syndrome",
        "sottas",
    ];

    $hit =
        in_array($q, $matches, true) ||
        strpos($q, "cmt3") !== false ||
        strpos($q, "dss") !== false ||
        strpos($q, "dejerine") !== false ||
        strpos($q, "sottas") !== false;

    if (!$hit) {
        return [];
    }

    $page = get_page_by_path("cmt-classifications", OBJECT, "page");

    if (!$page) {
        return [];
    }

    return [
        "subtypes" => [],
        "genes" => [],
        "types" => [],

        // CONTENT = IDs ONLY (this is mandatory)
        "content" => [$page->ID],

        "meta" => [
            "label" => "CMT3 / Dejerine-Sottas Syndrome",
            "note" => "archaic classification (content-only)",
            "anchor" => "cmt3",
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: Roussy-Lévy Syndrome (archaic)
 * ============================================================
 */
function eic_ps_semantic_roussy_levy(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Normalize semantic token
     * ------------------------------------------------------------
     * Keep this var resilient to diacritics and punctuation.
     */
    $q = iconv("UTF-8", "ASCII//TRANSLIT", $normalized_query);
    $q = strtolower($q);
    $q = str_replace([" ", "-", "_"], "", $q);

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     * Includes historical, hyphenated, and CMT1-prefixed variants.
     */
    $matches = [
        "roussylevy",
        "roussylevysyndrome",
        "roussy-levy",
        "roussy-levy-syndrome",

        "cmt1roussylevy",
        "cmt1-roussy-levy",
        "cmt1roussylevysyndrome",
        "cmt1-roussy-levy-syndrome",

        "charcotmarietoothroussylevy",
    ];

    $hit =
        in_array($q, $matches, true) ||
        strpos($q, "roussy") !== false ||
        strpos($q, "levy") !== false ||
        strpos($q, "levi") !== false;

    if (!$hit) {
        return [];
    }

    $page = get_page_by_path("cmt-classifications", OBJECT, "page");

    if (!$page) {
        return [];
    }

    return [
        "subtypes" => [],
        "genes" => [],
        "types" => [],

        // CONTENT = IDs ONLY
        "content" => [$page->ID],

        "meta" => [
            "label" => "Roussy-Lévy Syndrome",
            "note" => "archaic classification (content-only)",
            "anchor" => "roussy-levy",
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: Aminoacyl-tRNA Synthetase (ARS)
 * ============================================================
 */
function eic_ps_semantic_ars(string $normalized_query): array
{
    // Normalize (diacritics, case, and common separators)
    $q_norm = iconv("UTF-8", "ASCII//TRANSLIT", $normalized_query);
    $q_norm = strtolower($q_norm);
    $q_norm = str_replace([" ", "-", "_"], "", $q_norm);

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys
     * ------------------------------------------------------------
     */
    $matches = [
        "ars",
        "ars1",
        "ars2",
        "trna",
        "trnas",
        "trnasynthetase",
        "aminacyltrnasynthetase", // common misspelling)
        "aminoacyltrnasynthetase",
    ];

    $hit = false;
    foreach ($matches as $m) {
        if (strpos($q_norm, $m) !== false) {
            $hit = true;
            break;
        }
    }

    if (!$hit) {
        return [];
    }

    /**
     * ------------------------------------------------------------
     * Query ALL subtypes with ars_gene = true
     * ------------------------------------------------------------
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
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: CMT2A (legacy nomenclature resolution)
 * ============================================================
 *
 * Handles historical / superseded labels:
 * - KIF1B
 * - CMT2A1
 * - CMT2A2
 * - CMT2A2A
 *
 * All inputs resolve canonically to CMT2A.
 */
function eic_ps_semantic_cmt2a_legacy(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Normalize semantic token
     * ------------------------------------------------------------
     * Upstream normalization already:
     * - lowercased
     * - stripped punctuation
     * - collapsed whitespace
     *
     * We collapse spaces for canonical matching.
     */
    $q = iconv("UTF-8", "ASCII//TRANSLIT", $normalized_query);
    $q = str_replace(" ", "", strtolower($q));

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     * Includes gene-based and subtype-based legacy terms.
     */
    $matches = ["kif1b", "cmt2a1", "cmt2a2", "cmt2a2a"];

    $hit = false;
    foreach ($matches as $m) {
        if (strpos($q, $m) !== false) {
            $hit = true;
            break;
        }
    }

    if (!$hit) {
        return [];
    }

    /**
     * ------------------------------------------------------------
     * Canonical resolution: CMT2A
     * ------------------------------------------------------------
     */
    return [
        "subtypes" => [
            get_page_by_path("cmt2a", OBJECT, "subtype")->ID ?? null,
        ],

        // MFN2 is the current causative gene for CMT2A
        "genes" => [get_page_by_path("mfn2", OBJECT, "subtype")->ID ?? null],

        /**
         * --------------------------------------------------------
         * Content
         * --------------------------------------------------------
         * Explicitly surface the Dorsal Root confliction article.
         * IDs ONLY — required by renderer contract.
         */
        "content" => [
            get_page_by_path("2a-confliction", OBJECT, "post")->ID ?? null,
        ],

        "meta" => [
            "label" => "CMT2A (legacy nomenclature resolved)",
            "note" => "semantic variable",
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: MED25 (retracted gene → CMT2B2)
 * ============================================================
 *
 * Includes historical full name and HGNC aliases.
 */
function eic_ps_semantic_med25(string $normalized_query): array
{
    /**
     * ------------------------------------------------------------
     * Normalize semantic token
     * ------------------------------------------------------------
     * Upstream normalization guarantees:
     * - lowercase
     * - punctuation stripped
     * - whitespace collapsed
     *
     * We remove spaces here for canonical matching.
     */
    $q = iconv("UTF-8", "ASCII//TRANSLIT", $normalized_query);
    $q = strtolower($q);
    $q = str_replace(" ", "", $q);

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     * MED25 + historical full name + HGNC aliases.
     */
    $matches = [
        // canonical
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
    ];

    $hit = false;
    foreach ($matches as $m) {
        if (strpos($q, $m) !== false) {
            $hit = true;
            break;
        }
    }

    if (!$hit) {
        return [];
    }

    /**
     * ------------------------------------------------------------
     * Canonical resolution: CMT2B2
     * ------------------------------------------------------------
     * MED25 is a retracted gene in CMT literature.
     */
    return [
        "subtypes" => [
            get_page_by_path("cmt2b2", OBJECT, "subtype")->ID ?? null,
        ],

        // No gene surfaced — retracted association
        "genes" => [],

        // No content clamped here (by design)
        "content" => [],

        "meta" => [
            "label" => "MED25 → CMT2B2",
            "note" => "retracted gene (alias-aware semantic resolution)",
        ],
    ];
}

/**
 * ============================================================
 *  Semantic Variable: Dominant Intermediate A → CMT2GG
 * ============================================================
 */
function eic_ps_semantic_cmtdia(string $normalized_query): array
{
    $q = iconv("UTF-8", "ASCII//TRANSLIT", strtolower($normalized_query));
    $q = str_replace([" ", "-", "_"], "", $q);

    $matches = [
        "cmtdia",
        "cmta",
        "dominantintermediate",
        "dominantintermediatea",
        "dominantintermediatecmta",
    ];

    foreach ($matches as $m) {
        if (strpos($q, $m) !== false) {
            return [
                "subtypes" => [
                    get_page_by_path("cmt2gg", OBJECT, "subtype")->ID ?? null,
                ],

                "genes" => [
                    get_page_by_path("gbf1", OBJECT, "subtype")->ID ?? null,
                ],

                "types" => ["cmt2"],

                "content" => [],

                "meta" => [
                    "label" => "Dominant Intermediate CMT A",
                    "note" => "semantic variable",
                ],
            ];
        }
    }

    return [];
}
