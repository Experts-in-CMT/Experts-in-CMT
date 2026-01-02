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

$inheritance_map = [
    "autosomal-dominant"   => ["autosomal", "dominant"],
    "autosomal-recessive"  => ["autosomal", "recessive"],
    "x-linked-dominant"    => ["x-linked", "dominant"],
    "x-linked-recessive"   => ["x-linked", "recessive"],
];

$inheritance_content_map = [
    "autosomal-dominant"   => "autosomal-dominant",
    "autosomal-recessive"  => "autosomal-recessive",
    "x-linked-dominant"    => "x-linked-dominant",
    "x-linked-recessive"   => "x-linked-recessive",
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
    "post_type"      => "subtype",
    "post_status"    => "publish",
    "posts_per_page" => -1,
    "fields"         => "ids",
    "tax_query"      => [
        [
            "taxonomy" => "inheritance",
            "field"    => "term_id",
            "terms"    => [$term->term_id],
        ],
    ],
]);

// Resolve inheritance explainer content (all valid carriers)
$content_matches = get_posts([
    "post_type"      => [
        "post",
        "page",
        "what-is-cmt",
        "glossary",
    ],
    "post_status"    => "publish",
    "posts_per_page" => -1,
    "fields"         => "ids",
    "s"              => str_replace("-", " ", $term_slug),
]);

return [
    "subtypes" => array_values(array_unique($inheritance_matches)),
    "content"  => array_values(array_unique($content_matches)),
    "meta"     => [
        "label" => ucwords(str_replace("-", " ", $term_slug)),
        "note"  => "inheritance pattern",
    ],
];


    // Authoritative semantic return
    return [
        "subtypes" => array_values(array_unique($inheritance_matches)),
        "content"  => $content,
        "meta"     => [
            "label" => ucwords(str_replace("-", " ", $term_slug)),
            "note"  => "inheritance pattern",
        ],
    ];
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
    $resolved_content_ids = [];


    /**
     * ------------------------------------------------------------
     * Semantic variables (explicit, curated meaning)
     * ------------------------------------------------------------
     */

    $semantic = eic_ps_semantic_roussy_levy($normalized_query);
    if (!empty($semantic)) {
        return $semantic;
    }

    $semantic = eic_ps_semantic_cmt_1f_2e($normalized_query);
    if (!empty($semantic)) {
        return $semantic;
    }

    $semantic = eic_ps_semantic_sord($normalized_query);
    if (!empty($semantic)) {
        return $semantic;
    }

    $semantic = eic_ps_semantic_cmt3($normalized_query);
    if (!empty($semantic)) {
        return $semantic;
    }

    $semantic = eic_ps_semantic_roussy_levy($normalized_query);
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
        "post_type"      => "subtype",
        "post_status"    => "publish",
        "posts_per_page" => -1,
        "fields"         => "ids",
        "meta_query"     => [
            [
                "key"     => "gene_symbol",
                "value"   => $gene_symbol,
                "compare" => "=",
            ],
        ],
    ]);

    if (empty($matches)) {
        continue;
    }

    $gene_matches = array_merge($gene_matches, $matches);

    foreach ($matches as $subtype_id) {
        $type = get_post_meta($subtype_id, "type_classification", true);
        if (is_string($type) && $type !== "") {
            $types[] = strtolower(trim($type));
        }
    }
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
        "post_type"      => "subtype",
        "post_status"    => "publish",
        "posts_per_page" => -1,
        "fields"         => "ids",
        "meta_query"     => [
            [
                "key"     => "gene_alias",
                "value"   => '(^|,)\s*' . preg_quote($normalized, '/') . '\s*(,|$)',
                "compare" => "REGEXP",
            ],
        ],
    ]);

    if (empty($alias_matches)) {
        continue;
    }

    $gene_matches = array_merge($gene_matches, $alias_matches);

    foreach ($alias_matches as $subtype_id) {
        $type = get_post_meta($subtype_id, "type_classification", true);
        if (is_string($type) && $type !== "") {
            $types[] = strtolower(trim($type));
        }
    }
}

if (!empty($gene_matches)) {
    return [
        "subtypes" => array_values(array_unique($gene_matches)),
        "types"    => array_values(array_unique($types)),
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
 * - Widens content results (posts, pages, glossary, what-is-cmt)
 * - Uses non-hyphenated inheritance phrases for content
 */

$inheritance_single_map = [
    "autosomal" => ["autosomal-dominant", "autosomal-recessive"],
    "dominant"  => ["autosomal-dominant", "x-linked-dominant"],
    "recessive" => ["autosomal-recessive", "x-linked-recessive"],
    "x-linked"  => ["x-linked-dominant", "x-linked-recessive"],
];

// Content phrases MUST be non-hyphenated
$inheritance_content_terms = [
    "autosomal" => ["autosomal"],
    "dominant"  => ["autosomal dominant", "x linked dominant"],
    "recessive" => ["autosomal recessive", "x linked recessive"],
    "x-linked"  => ["x linked"],
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
            "post_type"      => "subtype",
            "post_status"    => "publish",
            "posts_per_page" => -1,
            "fields"         => "ids",
            "tax_query"      => [
                [
                    "taxonomy" => "inheritance",
                    "field"    => "term_id",
                    "terms"    => [$term->term_id],
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
                "post_type"      => ["post", "page", "what-is-cmt", "glossary"],
                "post_status"    => "publish",
                "posts_per_page" => -1,
                "fields"         => "ids",
                "s"              => $phrase,
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
    // Final return (merge basic + ACF resolution)
    // ------------------------------------------------------------
  return [
    "subtypes" => array_values(
        array_unique(array_merge($matches, $resolved_subtype_ids))
    ),
    "content" => array_values(
        array_unique($resolved_content_ids)
    ),
];

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
