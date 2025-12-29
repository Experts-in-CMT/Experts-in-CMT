<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
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
    // ------------------------------------------------------------
    // Tokenization (required for ACF + taxonomy resolution)
    // ------------------------------------------------------------
    $tokens = preg_split("/\s+/", $normalized_query);

    // ------------------------------------------------------------
    // EARLY EXIT: Chromosome intent (numeric-only, authoritative)
    // ------------------------------------------------------------
    // "chromosome" / "chr" is NOT a taxonomy term.
    // If detected, we resolve chromosome intent ONLY and stop.

    if (preg_match("/\b(chr|chromosome)\b/", $normalized_query)) {
        $chromosome_matches = [];

        foreach ($tokens as $token) {
            // Only numeric tokens are valid chromosome identifiers
            if (!ctype_digit($token)) {
                continue;
            }

            $term = get_term_by("slug", $token, "chromosome");

            if (!$term || is_wp_error($term)) {
                continue;
            }

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

            break; // numeric chromosome found → stop token scan
        }

        // Hard stop: chromosome intent overrides ALL other logic
        return array_values(array_unique($chromosome_matches));
    }

    /**
     * ============================================================
     *  Semantic Inheritance Intent (Authoritative Clamp)
     * ============================================================
     *
     * Purpose:
     * --------
     * Detects specific inheritance intent (e.g., autosomal dominant)
     * at the semantic level, ignoring word order and noise.
     * Mirrors chromosome behavior exactly:
     *   - interpret intent
     *   - resolve one taxonomy term
     *   - clamp and return
     *
     * IMPORTANT:
     * ----------
     * This must run BEFORE token-based taxonomy resolution.
     */

    $inheritance_map = [
        "autosomal-dominant" => ["autosomal", "dominant"],
        "autosomal-recessive" => ["autosomal", "recessive"],
        "x-linked-dominant" => ["x-linked", "dominant"],
        "x-linked-recessive" => ["x-linked", "recessive"],
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

        // Resolve inheritance term
        $term = get_term_by("slug", $term_slug, "inheritance");

        if (!$term || is_wp_error($term)) {
            continue;
        }

        // Fetch subtypes attached to this inheritance term
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

        // Clamp and return — Overrides all following logic
        return array_values(array_unique($inheritance_matches));
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

    if (!isset($type_anchor_map[$token_normalized])) {
        continue;
    }

    $type_slug = $type_anchor_map[$token_normalized];

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

    // HARD STOP — type classification is authoritative
    return [
        "subtypes" => array_values(array_unique($type_matches)),
        "types" => [$type_slug],
    ];
}

    // ------------------------------------------------------------
    // Accumulator for extended resolution
    // ------------------------------------------------------------
    $resolved_subtype_ids = [];

    /**
     * ------------------------------------------------------------
     * 1) Semantic variables (explicit, curated meaning)
     * ------------------------------------------------------------
     */
    $semantic = eic_ps_semantic_cmt_1f_2e($normalized_query);

    if (!empty($semantic)) {
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
    $gene_symbol = strtoupper($normalized_query);

    $gene_matches = get_posts([
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

    if (!empty($gene_matches)) {
        $types = [];

        foreach ($gene_matches as $subtype_id) {
            $type = get_post_meta($subtype_id, "type_classification", true);
            if (is_string($type) && $type !== "") {
                $types[] = strtolower(trim($type));
            }
        }

        return [
            "subtypes" => array_values(array_unique($gene_matches)),
            "types" => array_values(array_unique($types)),
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
     * Inheritance single-term CLAMP (authoritative)
     * ------------------------------------------------------------
     * "dominant" and "recessive" must STOP resolution.
     * No widening. No taxonomy fall-through.
     */
    $inheritance_clamp_map = [
        "dominant" => "autosomal-dominant",
        "recessive" => "autosomal-recessive",
    ];

    foreach ($inheritance_clamp_map as $token => $slug) {
        if (in_array($token, $tokens, true)) {
            $term = get_term_by("slug", $slug, "inheritance");

            if (!$term || is_wp_error($term)) {
                return [];
            }

            $ids = get_posts([
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

            //HARD STOP — inheritance clamp
            return array_values(array_unique($ids));
        }
    }

    /**
     * ------------------------------------------------------------
     * Inheritance single-term widener (umbrella intent)
     * ------------------------------------------------------------
     * Expands single inheritance concepts into concrete
     * inheritance taxonomy terms. This widens results but
     * never clamps.
     */

    $inheritance_single_map = [
        "autosomal" => ["autosomal-dominant", "autosomal-recessive"],
        "dominant" => ["autosomal-dominant", "x-linked-dominant"],
        "recessive" => ["autosomal-recessive", "x-linked-recessive"],
        "x-linked" => ["x-linked-dominant", "x-linked-recessive"],
    ];

    foreach ($tokens as $token) {
        if (!isset($inheritance_single_map[$token])) {
            continue;
        }

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
    // Final return (merge basic + ACF resolution)
    // ------------------------------------------------------------
    return array_values(
        array_unique(array_merge($matches, $resolved_subtype_ids))
    );
}

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
    $q = str_replace(" ", "", $normalized_query);

    /**
     * ------------------------------------------------------------
     * Canonical semantic keys (normalized form)
     * ------------------------------------------------------------
     */
    $matches = ["1f2e", "2e1f", "cmt1f2e", "cmt2e1f"];

    if (!in_array($q, $matches, true)) {
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
