<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Platform Search Resolver
 *
 * Since version: 1.8.0
 * Feature: platform-search
 *
 * Purpose:
 * --------
 * Provides a deterministic, human-aware search resolution layer for the
 * expertsincmt platform search. Responsible for normalization, intent
 * resolution, and result bucket construction.
 */

require_once get_template_directory() .
    "/inc/search/platform-search-variables.php";

/**
 * ============================================================
 *  Normalization Layer
 * ============================================================
 */

function eic_platform_search_normalize_input($raw)
{
    $value = strtolower(remove_accents($raw));

    $value = preg_replace("/[^\p{L}\p{N}\s\-]/u", " ", $value);
    $value = str_replace(["-", "_"], " ", $value);
    $value = preg_replace("/\s+/", " ", $value);
    $value = trim($value);

    return $value;
}

/**
 * ============================================================
 *  Resolver Entry Point
 * ============================================================
 */

function eic_platform_search_resolve($raw_query)
{
    $query_normalized = eic_platform_search_normalize_input($raw_query);

    $intent_payload = eic_platform_search_resolve_intent($query_normalized);

    $results = eic_platform_search_build_results(
        $intent_payload,
        $query_normalized
    );

    return [
        "query_raw" => $raw_query,
        "query_normalized" => $query_normalized,
        "intent" => $intent_payload["intent"] ?? null,
        "confidence" => $intent_payload["confidence"] ?? null,
        "notes" => $intent_payload["notes"] ?? null,
        "results" => $results,
    ];
}

/**
 * ============================================================
 *  Intent Resolution
 * ============================================================
 */

function eic_platform_search_resolve_intent($query_normalized)
{
    /**
     * ------------------------------------------------------------
     * Canary aliases (HNPP only — preserved exactly)
     * ------------------------------------------------------------
     */
    $canary_aliases = [
        "hnpp" => "hnpp",
        "palsy" => "hnpp",
    ];

    if (isset($canary_aliases[$query_normalized])) {
        return [
            "intent" => "subtype",
            "confidence" => "high",
            "notes" => "canary alias match",
            "subtype" => get_page_by_path(
                $canary_aliases[$query_normalized],
                OBJECT,
                "subtype"
            ),
        ];
    }

    /**
     * ------------------------------------------------------------
     * Expanded subtype resolution (exact, deterministic)
     * ------------------------------------------------------------
     */
    $candidates = [
        $query_normalized,
        str_replace(" ", "-", $query_normalized),
        str_replace(" ", "", $query_normalized),
    ];

    $candidates = array_unique($candidates);

    $resolved_subtype = null;
    $resolved_source = null;

    // Priority 1: exact slug match
    foreach ($candidates as $candidate) {
        $post = get_page_by_path($candidate, OBJECT, "subtype");
        if ($post) {
            $resolved_subtype = $post;
            $resolved_source = "slug";
            break;
        }
    }

    // Priority 2: exact title match (normalized)
    if (!$resolved_subtype) {
        $subtypes = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
        ]);

        foreach ($subtypes as $subtype_id) {
            $title_normalized = eic_platform_search_normalize_input(
                get_the_title($subtype_id)
            );

            foreach ($candidates as $candidate) {
                if ($candidate === $title_normalized) {
                    $resolved_subtype = get_post($subtype_id);
                    $resolved_source = "title";
                    break 2;
                }
            }
        }
    }

    /**
     * ------------------------------------------------------------
     * Subtype token extraction (prose queries)
     * ------------------------------------------------------------
     * Example: "what is cmt1a" → token "cmt1a"
     */
    if (!$resolved_subtype) {
        $tokens = preg_split("/\s+/", $query_normalized);
        $tokens = array_values(array_filter($tokens));

        foreach ($tokens as $tok) {
            $tok = strtolower(trim($tok));
            if ($tok === "") {
                continue;
            }

            // Only attempt plausible subtype-like tokens
            if (
                !preg_match('/^(cmt|hsn|hsan|hmsn|dhmn|dsma)[0-9a-z]+$/', $tok)
            ) {
                continue;
            }

            $post = get_page_by_path($tok, OBJECT, "subtype");
            if ($post) {
                $resolved_subtype = $post;
                $resolved_source = "token";
                break;
            }
        }
    }

    if ($resolved_subtype) {
        return [
            "intent" => "subtype",
            "confidence" => "high",
            "notes" => "exact subtype match (" . $resolved_source . ")",
            "subtype" => $resolved_subtype,
        ];
    }

    return [];
}

/**
 * ============================================================
 *  Result Builder
 * ============================================================
 */
function eic_platform_search_build_results($payload, $query_normalized)
{
    $genes_page = get_page_by_path("cmt-genetics-database", OBJECT, "page");
    $genes_db_url = $genes_page ? get_permalink($genes_page->ID) : "";

    $results = [
        "subtypes" => [],
        "genes" => [],
        "types" => [], // clickable: [ [label,url], ... ]
        "content" => [],
    ];

    /**
     * ------------------------------------------------------------
     * Bring type anchor map + base URL into scope
     * ------------------------------------------------------------
     */
    $type_anchor_map = function_exists("eic_ps_type_classification_anchors")
        ? eic_ps_type_classification_anchors()
        : [];

    $type_base_url = get_permalink_by_slug("cmt-classifications", "page");

    /**
     * ------------------------------------------------------------
     * Subtype results (exact intent resolution)
     * ------------------------------------------------------------
     */
    if (
        ($payload["intent"] ?? null) === "subtype" &&
        !empty($payload["subtype"]) &&
        $payload["subtype"] instanceof WP_Post
    ) {
        $subtype_id = $payload["subtype"]->ID;

        $results["subtypes"][] = [
            "id" => $subtype_id,
            "label" => get_the_title($subtype_id),
            "url" => get_permalink($subtype_id),
            "type" => "Subtype",
        ];

        // Type (clickable)
        $type = get_post_meta($subtype_id, "type_classification", true);
        $key = is_string($type) ? strtolower(trim($type)) : "";

        if ($key !== "" && isset($type_anchor_map[$key]) && $type_base_url) {
            $label = strtoupper(trim($type));

            if ($label === "UNCLASSIFIED") {
                $label = "Unclassified Subtypes";
            }

            $results["types"][] = [
                "label" => $label,
                "url" => $type_base_url . "#" . $type_anchor_map[$key],
            ];
        }

        // Gene (CLICKABLE)
        $gene_symbol = get_field("gene_symbol", $subtype_id);
        if ($gene_symbol && $genes_db_url) {
            $results["genes"][] = [
                "label" => $gene_symbol,
                "type" => "Gene",
                "url" =>
                    $genes_db_url .
                    "?qs=" .
                    urlencode(strtolower($gene_symbol)) .
                    "#results",
            ];
        }

        // ------------------------------------------------------------
        // Content (subtype-anchored — exact intent path)
        // ------------------------------------------------------------
        $raw_subtype = get_post_meta($subtype_id, "subtype", true);
        if (!is_string($raw_subtype) || $raw_subtype === "") {
            $raw_subtype = get_the_title($subtype_id);
        }

        $content_ids = get_posts([
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

        if (!empty($content_ids)) {
            foreach (array_unique($content_ids) as $content_id) {
                if (!$content_id) {
                    continue;
                }

                $pt = get_post_type_object(get_post_type($content_id));

                $highlight_terms = array_filter([
                    $raw_subtype, // CMT1A
                    get_the_title($subtype_id), // CMT Type 1A
                    get_field("gene_symbol", $subtype_id), // PMP22 (if present)
                ]);

                $results["content"][] = [
                    "id" => $content_id,
                    "label" => get_the_title($content_id),
                    "url" => get_permalink($content_id),
                    "type" => $pt->labels->singular_name ?? "Content",
                    "excerpt" => eic_build_search_excerpt(
                        $content_id,
                        $highlight_terms
                    ),
                ];
            }
        }

        return $results;
    }

    /**
     * ------------------------------------------------------------
     * Variable-based discovery
     * ------------------------------------------------------------
     */
    $variables = eic_platform_search_variable_subtypes($query_normalized);

    if (empty($variables)) {
        return $results;
    }

    $results["_platform"] = true;

    /**
     * ------------------------------------------------------------
     * Semantic payload (multi-bucket)
     * ------------------------------------------------------------
     */
    if (is_array($variables) && isset($variables["subtypes"])) {
        foreach ($variables["subtypes"] as $subtype_id) {
            if (!$subtype_id) {
                continue;
            }

            $results["subtypes"][] = [
                "id" => $subtype_id,
                "label" => get_the_title($subtype_id),
                "url" => get_permalink($subtype_id),
                "type" => "Subtype",
            ];

            $gene_symbol = get_field("gene_symbol", $subtype_id);
            if ($gene_symbol && $genes_db_url) {
                $results["genes"][] = [
                    "label" => $gene_symbol,
                    "type" => "Gene",
                    "url" =>
                        $genes_db_url .
                        "?qs=" .
                        urlencode(strtolower($gene_symbol)) .
                        "#results",
                ];
            }
        }

        /**
         * --------------------------
         * Types (explicit resolution)
         * --------------------------
         */
        if (!empty($variables["types"])) {
            foreach ($variables["types"] as $type) {
                $key = is_string($type) ? strtolower(trim($type)) : "";

                if (
                    $key !== "" &&
                    isset($type_anchor_map[$key]) &&
                    $type_base_url
                ) {
                    $label = strtoupper(trim($type));

                    if ($label === "UNCLASSIFIED") {
                        $label = "Unclassified Subtypes";
                    }

                    $results["types"][] = [
                        "label" => $label,
                        "url" => $type_base_url . "#" . $type_anchor_map[$key],
                    ];
                }
            }
        }

        /**
         * --------------------------
         * Types (derived from subtypes if empty)
         * --------------------------
         */
        if (empty($results["types"]) && !empty($results["subtypes"])) {
            foreach ($results["subtypes"] as $subtype) {
                $subtype_id = $subtype["id"] ?? null;
                if (!$subtype_id) {
                    continue;
                }

                $type = get_post_meta($subtype_id, "type_classification", true);
                $key = is_string($type) ? strtolower(trim($type)) : "";

                if (
                    $key !== "" &&
                    isset($type_anchor_map[$key]) &&
                    $type_base_url
                ) {
                    $label = strtoupper(trim($type));

                    if ($label === "UNCLASSIFIED") {
                        $label = "Unclassified Subtypes";
                    }

                    $results["types"][] = [
                        "label" => $label,
                        "url" => $type_base_url . "#" . $type_anchor_map[$key],
                    ];
                }
            }
        }

        /**
         * --------------------------
         * Types — dedup + canonical hierarchy
         * --------------------------
         */
        if (!empty($results["types"])) {
            $seen = [];
            $dedup = [];

            foreach ($results["types"] as $t) {
                $label = $t["label"] ?? "";
                if ($label === "" || isset($seen[$label])) {
                    continue;
                }
                $seen[$label] = true;
                $dedup[] = $t;
            }

            $canonical_type_order = [
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

            $rank = array_flip($canonical_type_order);

            usort($dedup, function ($a, $b) use ($rank) {
                $a_label = $a["label"] ?? "";
                $b_label = $b["label"] ?? "";
                return ($rank[$a_label] ?? 9999) <=> ($rank[$b_label] ?? 9999);
            });

            $results["types"] = $dedup;
        }

        if (!empty($variables["content"]) && is_array($variables["content"])) {
            foreach ($variables["content"] as $content_id) {
                if (!$content_id) {
                    continue;
                }

                $pt = get_post_type_object(get_post_type($content_id));

                $anchor = !empty($variables["meta"]["anchor"])
                    ? "#" . $variables["meta"]["anchor"]
                    : "";

                $results["content"][] = [
                    "id" => $content_id,
                    "label" => get_the_title($content_id),
                    "url" => get_permalink($content_id) . $anchor,
                    "type" => $pt->labels->singular_name ?? "Content",
                    "excerpt" => eic_build_search_excerpt(
                        $content_id,
                        [] // no authoritative term → no highlight
                    ),
                ];
            }
        }

        /** CLOSE the semantic payload block */
    }

    /**
     * ------------------------------------------------------------
     * Basic variable discovery
     * ------------------------------------------------------------
     */
    if (is_array($variables) && !isset($variables["subtypes"])) {
        foreach ($variables as $subtype_id) {
            if (!$subtype_id) {
                continue;
            }

            $results["subtypes"][] = [
                "id" => $subtype_id,
                "label" => get_the_title($subtype_id),
                "url" => get_permalink($subtype_id),
                "type" => "Subtype",
            ];

            $gene_symbol = get_field("gene_symbol", $subtype_id);
            if ($gene_symbol && $genes_db_url) {
                $results["genes"][] = [
                    "label" => $gene_symbol,
                    "type" => "Gene",
                    "url" =>
                        $genes_db_url .
                        "?qs=" .
                        urlencode(strtolower($gene_symbol)) .
                        "#results",
                ];
            }

            $type = get_post_meta($subtype_id, "type_classification", true);
            $key = is_string($type) ? strtolower(trim($type)) : "";

            if (
                $key !== "" &&
                isset($type_anchor_map[$key]) &&
                $type_base_url
            ) {
                $label = strtoupper(trim($type));

                if ($label === "UNCLASSIFIED") {
                    $label = "Unclassified Subtypes";
                }

                $results["types"][] = [
                    "label" => $label,
                    "url" => $type_base_url . "#" . $type_anchor_map[$key],
                ];
            }
        }
    }

    /**
     * ------------------------------------------------------------
     * GLOBAL DE-DUP (ALL PATHS)
     * ------------------------------------------------------------
     */

    // Subtypes — by ID
    if (!empty($results["subtypes"])) {
        $subtype_map = [];
        foreach ($results["subtypes"] as $s) {
            if (!empty($s["id"])) {
                $subtype_map[$s["id"]] = $s;
            }
        }
        $results["subtypes"] = array_values($subtype_map);

        /**
         * --------------------------------------------------------
         * CANONICAL SUBTYPE ORDER
         * --------------------------------------------------------
         * Primary: canonical type_classification order
         * Secondary: subtype label A–Z
         * Unclassified sorts absolute last by law
         */
        $canonical_type_order = [
            "CMT1",
            "CMT2",
            "CMT4",
            "CMTX",
            "CMTDI",
            "CMTRI",
            "DHMN",
            "DSMA",
            "GAN",
            "HMSN",
            "HSAN",
            "HSN",
            "SMA-LEP",
            "UNCLASSIFIED",
        ];

        $type_rank = array_flip($canonical_type_order);

        usort($results["subtypes"], function ($a, $b) use ($type_rank) {
            $a_id = $a["id"] ?? 0;
            $b_id = $b["id"] ?? 0;

            $a_type_raw = get_post_meta($a_id, "type_classification", true);
            $b_type_raw = get_post_meta($b_id, "type_classification", true);

            // normalize to canonical key space
            $a_type = strtoupper(trim((string) $a_type_raw));
            $b_type = strtoupper(trim((string) $b_type_raw));

            $a_rank = $type_rank[$a_type] ?? PHP_INT_MAX;
            $b_rank = $type_rank[$b_type] ?? PHP_INT_MAX;

            if ($a_rank !== $b_rank) {
                return $a_rank <=> $b_rank;
            }

            return strnatcasecmp($a["label"] ?? "", $b["label"] ?? "");
        });
    }

    // Genes — by label, A–Z
    if (!empty($results["genes"])) {
        $gene_map = [];
        foreach ($results["genes"] as $g) {
            if (!empty($g["label"])) {
                $gene_map[$g["label"]] = $g;
            }
        }
        ksort($gene_map, SORT_NATURAL | SORT_FLAG_CASE);
        $results["genes"] = array_values($gene_map);
    }

    // Content — de-dup + hub pinning
    if (!empty($results["content"])) {
        $content_map = [];
        foreach ($results["content"] as $c) {
            if (!empty($c["id"])) {
                $content_map[$c["id"]] = $c;
            }
        }

        $content = array_values($content_map);

        // --------------------------------------------------------
        // HUB PINNING (Opt B)
        // --------------------------------------------------------
        $hub = [];
        $rest = [];

        foreach ($content as $item) {
            $post_id = $item["id"];
            $post = get_post($post_id);

            if (!$post) {
                $rest[] = $item;
                continue;
            }

            $slug = $post->post_name;
            $pt = $post->post_type;

            // canonical hub conditions
            $is_hub =
                $pt === "page" &&
                ($slug === $query_normalized ||
                    strpos($slug, $query_normalized) !== false);

            if ($is_hub) {
                $hub[] = $item;
            } else {
                $rest[] = $item;
            }
        }

        // hub(s) first, preserve WP order otherwise
        $results["content"] = array_merge($hub, $rest);
    }

    // Types — de-dup + canonical order (FINAL)
    if (!empty($results["types"])) {
        $seen = [];
        $dedup = [];

        foreach ($results["types"] as $t) {
            $label = $t["label"] ?? "";
            if ($label === "" || isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;
            $dedup[] = $t;
        }

        $canonical_type_order = [
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

        $rank = array_flip($canonical_type_order);

        usort($dedup, function ($a, $b) use ($rank) {
            $a_label = $a["label"] ?? "";
            $b_label = $b["label"] ?? "";

            return ($rank[$a_label] ?? PHP_INT_MAX) <=>
                ($rank[$b_label] ?? PHP_INT_MAX);
        });

        $results["types"] = $dedup;
    }

    return $results;
}

/**
 * ============================================================
 *  Helper Stubs (Implemented Elsewhere)
 * ============================================================
 */

function get_permalink_by_slug($slug, $post_type)
{
    $post = get_page_by_path($slug, OBJECT, $post_type);

    if (!$post) {
        return "";
    }

    return get_permalink($post->ID);
}

/**
 * ============================================================
 *  Type Display Label Helper
 * ============================================================
 *
 * Presentation-only helper.
 * Does NOT affect sorting, ranking, or canonical values.
 */
function eic_ps_type_display_label(string $type): string
{
    $type = trim($type);

    if ($type === "") {
        return "";
    }

    // Absolute rule: Unclassified renders as "Unclassified Subtypes"
    if (strcasecmp($type, "unclassified") === 0) {
        return "Unclassified Subtypes";
    }

    // Title Case (override forced ALL CAPS)
    return ucwords(strtolower($type));
}

/**
 * ============================================================
 *  Content Excerpt + Highlight Helper (Dynamic Window)
 * ============================================================
 *
 * Goal:
 * - If a highlight term exists in the text, build the excerpt window
 *   around the FIRST match (subtype intent authority).
 * - Then highlight matches.
 * - If no match exists, fall back to the leading excerpt behavior.
 */
function eic_build_search_excerpt(
    $post_id,
    array $highlight_terms = [],
    int $length = 260
): string {
    $post = get_post($post_id);
    if (!$post) {
        return "";
    }

    // Prefer manual excerpt
    $text = trim((string) $post->post_excerpt);

    // Fallback to content
    if ($text === "") {
        $text = strip_shortcodes((string) $post->post_content);
    }

    // Normalize
    $text = wp_strip_all_tags($text);
    $text = preg_replace("/\s+/", " ", $text);
    $text = trim((string) $text);

    if ($text === "") {
        return "";
    }

    // mbstring-safe helpers
    $strlen = function_exists("mb_strlen") ? "mb_strlen" : "strlen";
    $substr = function_exists("mb_substr") ? "mb_substr" : "substr";
    $stripos = function_exists("mb_stripos") ? "mb_stripos" : "stripos";

    $excerpt = $text;

    // ------------------------------------------------------------
    // Dynamic windowing: center excerpt around FIRST match
    // ------------------------------------------------------------
    $terms = [];
    foreach ($highlight_terms as $t) {
        $t = trim((string) $t);
        if ($t !== "") {
            $terms[] = $t;
        }
    }

    if (!empty($terms)) {
        $first_pos = null;

        foreach ($terms as $t) {
            $pos = $stripos($text, $t);
            if ($pos !== false) {
                if ($first_pos === null || $pos < $first_pos) {
                    $first_pos = $pos;
                }
            }
        }

        if ($first_pos !== null && $strlen($text) > $length) {
            $half = (int) floor($length / 2);

            $start = max(0, $first_pos - $half);
            $end = $start + $length;

            // Clamp end
            if ($end > $strlen($text)) {
                $end = $strlen($text);
                $start = max(0, $end - $length);
            }

            $slice = $substr($text, $start, $end - $start);
            $slice = trim($slice);

            // Ellipses if clipped
            if ($start > 0) {
                $slice = "…" . ltrim($slice);
            }
            if ($end < $strlen($text)) {
                $slice = rtrim($slice, " \t\n\r\0\x0B.,;:-") . "…";
            }

            $excerpt = $slice;
        }
    }

    // ------------------------------------------------------------
    // Fallback: leading trim if still too long
    // ------------------------------------------------------------
    if ($strlen($excerpt) > $length) {
        $excerpt = $substr($excerpt, 0, $length);
        $excerpt = rtrim($excerpt, ".,;:-") . "…";
    }

    // Highlight terms (authoritative)
    if (!empty($terms)) {
        $excerpt = eic_ps_highlight_terms($excerpt, $terms);
    }

    return $excerpt;
}

/**
 * Highlight matched terms inside excerpt
 */
function eic_ps_highlight_terms(string $text, array $terms): string
{
    // Longest-first avoids partial-term swallowing (e.g., CMT1 vs CMT1A)
    usort($terms, function ($a, $b) {
        $la = function_exists("mb_strlen") ? mb_strlen($a) : strlen($a);
        $lb = function_exists("mb_strlen") ? mb_strlen($b) : strlen($b);
        return $lb <=> $la;
    });

    foreach ($terms as $term) {
        $term = trim((string) $term);
        if ($term === "") {
            continue;
        }

        $pattern = "/(" . preg_quote($term, "/") . ")/i";
        $text = preg_replace(
            $pattern,
            '<mark class="ps-highlight">$1</mark>',
            $text
        );
    }

    return $text;
}
