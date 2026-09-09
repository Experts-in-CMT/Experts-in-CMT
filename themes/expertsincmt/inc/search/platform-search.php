<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
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

    // Variant notation lives in the characters normalization strips
    // (p.Thr424Met, c.1271C>T, HSPB3-P121L): the variant resolver
    // reads the query as typed, and the cache key carries it so two
    // spellings that normalize alike never share a result
    if (function_exists("eic_ps_raw_query")) {
        eic_ps_raw_query($raw_query);
    }
    $raw_signature = preg_replace("/\s+/", " ", strtolower(trim($raw_query)));

    /**
     * ------------------------------------------------------------
     * Transient cache (same pattern as the Subtype Browser)
     * ------------------------------------------------------------
     * Keyed on the normalized query + the shared subtype cache
     * version, so subtype CRUD invalidates automatically.
     * Dev bypass: ?nocache=1
     */
    $cache_version = function_exists("eic_gl_ids_version")
        ? eic_gl_ids_version()
        : 1;

    // Saving the admin alias table (Settings → EIC Search) bumps
    // this, so alias edits take effect without waiting out the TTL
    $alias_version = function_exists("eic_search_alias_ver")
        ? eic_search_alias_ver()
        : 0;

    $cache_key =
        "eic_ps_" .
        md5(
            $query_normalized .
                "|" .
                $raw_signature .
                "|" .
                $cache_version .
                "|" .
                $alias_version
        );
    $bypass_cache = isset($_GET["nocache"]);

    if (!$bypass_cache) {
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $cached["query_raw"] = $raw_query;
            return $cached;
        }
    }

    $intent_payload = eic_platform_search_resolve_intent($query_normalized);

    $results = eic_platform_search_build_results(
        $intent_payload,
        $query_normalized
    );

    $payload = [
        "query_raw" => $raw_query,
        "query_normalized" => $query_normalized,
        "intent" => $intent_payload["intent"] ?? null,
        "confidence" => $intent_payload["confidence"] ?? null,
        "notes" => $intent_payload["notes"] ?? null,
        "results" => $results,
    ];

    if (!$bypass_cache) {
        // 60s TTL matches site convention; extend for production
        set_transient(
            $cache_key,
            $payload,
            (int) apply_filters("eic_ps_cache_ttl", MINUTE_IN_SECONDS)
        );
    }

    return $payload;
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
        foreach (eic_ps_all_subtype_ids() as $subtype_id) {
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
    $genes_db_url = function_exists("eic_subtype_browser_page_url")
        ? eic_subtype_browser_page_url()
        : "";

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
        $type = eic_ps_subtype_field($subtype_id, "type_classification");
        $key = strtolower(trim($type));

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
        $gene_symbol = eic_ps_subtype_field($subtype_id, "gene_symbol");
        if ($gene_symbol && $genes_db_url) {
            $results["genes"][] = [
                "label" => $gene_symbol,
                "type" => "Gene",
                "url" => eic_ps_gene_url($gene_symbol),
            ];
        }

        // ------------------------------------------------------------
        // Content (subtype-anchored — exact intent path)
        // ------------------------------------------------------------
        $raw_subtype = eic_ps_subtype_field($subtype_id, "subtype");
        if ($raw_subtype === "") {
            $raw_subtype = get_the_title($subtype_id);
        }

        $content_ids = eic_ps_content_search($raw_subtype);

        if (!empty($content_ids)) {
            foreach (array_unique($content_ids) as $content_id) {
                if (!$content_id) {
                    continue;
                }

                $pt = get_post_type_object(get_post_type($content_id));

                $highlight_terms = array_filter([
                    $raw_subtype, // CMT1A
                    get_the_title($subtype_id), // CMT Type 1A
                    eic_ps_subtype_field($subtype_id, "gene_symbol"), // PMP22 (if present)
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

    // Surface the winning resolver's note (search log) and any typo
    // correction (renderer banner) to the payload consumers
    if (!empty($variables["meta"]["note"])) {
        $results["_note"] = (string) $variables["meta"]["note"];
    }
    if (!empty($variables["meta"]["corrected_label"])) {
        $results["_corrected"] = (string) $variables["meta"]["corrected_label"];
    }

    // Variant entries (variant resolver): rendered as their own group
    // above the rest; the gene, subtypes, and content ride along below
    if (!empty($variables["variants"]) && is_array($variables["variants"])) {
        $results["variants"] = array_values($variables["variants"]);
    }

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

            $gene_symbol = eic_ps_subtype_field($subtype_id, "gene_symbol");
            if ($gene_symbol && $genes_db_url) {
                $results["genes"][] = [
                    "label" => $gene_symbol,
                    "type" => "Gene",
                    "url" => eic_ps_gene_url($gene_symbol),
                ];
            }
        }

        /**
         * --------------------------
         * Genes declared by the intent itself (admin alias rows) —
         * delivered verbatim, independent of what the dataset says
         * --------------------------
         */
        if (!empty($variables["meta"]["gene_labels"]) && $genes_db_url) {
            foreach ((array) $variables["meta"]["gene_labels"] as $gl) {
                $gl = trim((string) $gl);

                if ($gl === "") {
                    continue;
                }

                $results["genes"][] = [
                    "label" => $gl,
                    "type" => "Gene",
                    "url" => eic_ps_gene_url($gl),
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

                $type = eic_ps_subtype_field($subtype_id, "type_classification");
                $key = strtolower(trim($type));

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

            $rank = array_change_key_case(
                array_flip(eic_ps_canonical_type_order()),
                CASE_UPPER
            );

            usort($dedup, function ($a, $b) use ($rank) {
                $a_label = strtoupper($a["label"] ?? "");
                $b_label = strtoupper($b["label"] ?? "");
                return ($rank[$a_label] ?? PHP_INT_MAX) <=>
                    ($rank[$b_label] ?? PHP_INT_MAX);
            });

            $results["types"] = $dedup;
        }

        if (!empty($variables["content"]) && is_array($variables["content"])) {
            // Intent-declared highlight terms (clamp label, matched
            // subtype names, gene symbols, semantic aliases)
            $highlight_terms = array_values(
                array_filter((array) ($variables["meta"]["highlight"] ?? []))
            );

            // When anchor_ids is present, the anchor applies only to
            // those curated targets, not to widened prose hits
            $anchor_ids = array_map(
                "intval",
                (array) ($variables["meta"]["anchor_ids"] ?? [])
            );

            foreach ($variables["content"] as $content_id) {
                if (!$content_id) {
                    continue;
                }

                $pt = get_post_type_object(get_post_type($content_id));

                $apply_anchor =
                    !empty($variables["meta"]["anchor"]) &&
                    (empty($anchor_ids) ||
                        in_array((int) $content_id, $anchor_ids, true));

                $anchor = $apply_anchor
                    ? "#" . $variables["meta"]["anchor"]
                    : "";

                $results["content"][] = [
                    "id" => $content_id,
                    "label" => get_the_title($content_id),
                    "url" => get_permalink($content_id) . $anchor,
                    "type" => $pt->labels->singular_name ?? "Content",
                    "excerpt" => eic_build_search_excerpt(
                        $content_id,
                        $highlight_terms
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

            $gene_symbol = eic_ps_subtype_field($subtype_id, "gene_symbol");
            if ($gene_symbol && $genes_db_url) {
                $results["genes"][] = [
                    "label" => $gene_symbol,
                    "type" => "Gene",
                    "url" => eic_ps_gene_url($gene_symbol),
                ];
            }

            $type = eic_ps_subtype_field($subtype_id, "type_classification");
            $key = strtolower(trim($type));

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
         *
         * Ranks are prefetched once — never query inside usort.
         */
        $type_rank = array_change_key_case(
            array_flip(eic_ps_canonical_type_order()),
            CASE_UPPER
        );

        $subtype_ids = array_filter(array_column($results["subtypes"], "id"));

        $rank_by_id = [];
        foreach ($subtype_ids as $id) {
            $type = strtoupper(
                trim(eic_ps_subtype_field($id, "type_classification"))
            );
            $rank_by_id[$id] = $type_rank[$type] ?? PHP_INT_MAX;
        }

        usort($results["subtypes"], function ($a, $b) use ($rank_by_id) {
            $a_rank = $rank_by_id[$a["id"] ?? 0] ?? PHP_INT_MAX;
            $b_rank = $rank_by_id[$b["id"] ?? 0] ?? PHP_INT_MAX;

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

        $rank = array_change_key_case(
            array_flip(eic_ps_canonical_type_order()),
            CASE_UPPER
        );

        usort($dedup, function ($a, $b) use ($rank) {
            $a_label = strtoupper($a["label"] ?? "");
            $b_label = strtoupper($b["label"] ?? "");

            return ($rank[$a_label] ?? PHP_INT_MAX) <=>
                ($rank[$b_label] ?? PHP_INT_MAX);
        });

        $results["types"] = $dedup;
    }

    /**
     * ------------------------------------------------------------
     * PREVIEW + BROWSER HANDOFF (umbrella queries)
     * ------------------------------------------------------------
     * When a clamp matched a taxonomy/type filter, large subtype
     * and gene buckets are trimmed to a short preview and the rest
     * hands off to the CMT Subtype Browser with that filter
     * pre-applied (browser arrives filtered + canonically sorted).
     * Runs AFTER dedup/sort so the preview is the canonical head.
     */
    $browser_filter = $variables["meta"]["browser_filter"] ?? null;
    $gb_inh_modes = $variables["meta"]["gb_inh_modes"] ?? [];

    // Subtype Browser handoff URL (single taxonomy/type filter).
    // Slug values are the canonical clean-URL form; the browser's
    // eic_resolve_tax_field() accepts them (term_id is the fallback).
    $more_url = "";
    if (
        !empty($browser_filter["param"]) &&
        !empty($browser_filter["term_id"]) &&
        $genes_db_url
    ) {
        $more_url =
            add_query_arg(
                $browser_filter["param"],
                !empty($browser_filter["slug"])
                    ? $browser_filter["slug"]
                    : (int) $browser_filter["term_id"],
                $genes_db_url
            ) . "#results";
    }

    /**
     * --------------------------------------------------------
     * Gene Browser handoff URL, when its native facets can
     * express the same filter:
     *   inheritance → gb_inh (AD/AR/XLD/XLR; multi via widener)
     *   chromosome  → gb_chr (raw token)
     *   cmt_type    → gb_cls (canon display label)
     * Neuropathy has no gene-browser facet and keeps the
     * Subtype Browser link instead.
     * --------------------------------------------------------
     */
    $gb_param = null;

    if ($browser_filter) {
        $slug = (string) ($browser_filter["slug"] ?? "");

        $gb_inh_map = [
            "autosomal-dominant" => "AD",
            "autosomal-recessive" => "AR",
            "x-linked-dominant" => "XLD",
            "x-linked-recessive" => "XLR",
        ];

        $gb_cls_map = [
            "cmt1" => "CMT1",
            "cmt2" => "CMT2",
            "cmt4" => "CMT4",
            "cmtx" => "CMTX",
            "cmtdi" => "CMTDI",
            "cmtri" => "CMTRI",
            "dhmn" => "dHMN/HMN",
            "dsma" => "dSMA",
            "gan" => "GAN",
            "hmsn" => "HMSN",
            "hsan" => "HSAN",
            "hsn" => "HSN",
            "smalep" => "SMA-LEP",
            "unclassified" => "Unclassified Subtypes",
        ];

        if (
            $browser_filter["param"] === "inheritance" &&
            isset($gb_inh_map[$slug])
        ) {
            $gb_param = ["gb_inh", $gb_inh_map[$slug]];
        } elseif ($browser_filter["param"] === "chromosome" && $slug !== "") {
            $gb_param = ["gb_chr", $slug];
        } elseif (
            $browser_filter["param"] === "cmt_type" &&
            isset($gb_cls_map[$slug])
        ) {
            $gb_param = ["gb_cls", $gb_cls_map[$slug]];
        }
    } elseif (!empty($gb_inh_modes)) {
        // Widener union ("dominant" → AD,XLD): gb_inh is multi-value
        $gb_param = ["gb_inh", implode(",", $gb_inh_modes)];
    }

    $preview = (int) apply_filters("eic_ps_subtype_preview_limit", 5);

    /**
     * --------------------------------------------------------
     * Fallback: no native facet expresses this gene set
     * (neuropathy, curated semantic panels like ARS) — deep-link
     * the exact symbol list via the gb_genes allowlist param.
     * --------------------------------------------------------
     */
    if (!$gb_param && count($results["genes"]) > $preview) {
        $symbols = array_values(
            array_filter(array_column($results["genes"], "label"))
        );

        if (!empty($symbols)) {
            $gb_param = ["gb_genes", implode(",", $symbols)];
        }
    }

    $genes_more_url = $gb_param
        ? eic_ps_gene_browser_page_url() .
            "?" .
            http_build_query([$gb_param[0] => $gb_param[1]]) .
            "#gbx-filter"
        : "";

    /**
     * --------------------------------------------------------
     * Trim each bucket to a preview only when a handoff link
     * exists to reach the full set.
     * --------------------------------------------------------
     */

    if ($more_url && count($results["subtypes"]) > $preview) {
        $results["subtypes_total"] = count($results["subtypes"]);
        $results["subtypes"] = array_slice($results["subtypes"], 0, $preview);
        $results["more_url"] = $more_url;
    }

    if (($genes_more_url || $more_url) && count($results["genes"]) > $preview) {
        $results["genes_total"] = count($results["genes"]);
        $results["genes"] = array_slice($results["genes"], 0, $preview);

        if ($genes_more_url) {
            $results["genes_more_url"] = $genes_more_url;
        }
        if ($more_url) {
            $results["more_url"] = $more_url;
        }
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
 *  Fuzzy Candidate Machinery (shared)
 * ============================================================
 *
 * Scores the query against subtype names and gene symbols.
 * Consumed by BOTH the typo-fallback resolver (auto-correct on
 * strong hits) and the no-results "Did you mean" suggestions.
 *
 * Score: exact = 0, prefix = 1, edit distance d = d + 1.
 * Returns entries sorted best-first:
 *   [label, kind(subtype|gene), url, score, subtype_ids[]]
 */
function eic_ps_fuzzy_candidates(string $raw_query): array
{
    $normalized = eic_platform_search_normalize_input($raw_query);

    if ($normalized === "") {
        return [];
    }

    $tokens = array_values(array_filter(preg_split("/\s+/", $normalized)));

    // Also try the whole query collapsed ("cmt 1 a" → "cmt1a")
    $collapsed = str_replace(" ", "", $normalized);
    if ($collapsed !== "" && !in_array($collapsed, $tokens, true)) {
        $tokens[] = $collapsed;
    }

    if (empty($tokens)) {
        return [];
    }

    $browser_url = function_exists("eic_subtype_browser_page_url")
        ? eic_subtype_browser_page_url()
        : "";

    // ------------------------------------------------------------
    // Candidate vocabulary: subtype names + gene symbols.
    // A gene symbol carries EVERY subtype it causes.
    // ------------------------------------------------------------
    $candidates = [];

    foreach (eic_ps_all_subtype_ids() as $subtype_id) {
        $subtype = eic_ps_subtype_field($subtype_id, "subtype");

        if ($subtype !== "") {
            $norm = preg_replace("/[^a-z0-9]/", "", strtolower($subtype));

            if ($norm !== "" && !isset($candidates[$norm])) {
                $candidates[$norm] = [
                    "label" => $subtype,
                    "kind" => "subtype",
                    "url" => get_permalink($subtype_id),
                    "subtype_ids" => [$subtype_id],
                ];
            }
        }

        $gene = eic_ps_subtype_field($subtype_id, "gene_symbol");

        if ($gene !== "" && strtoupper($gene) !== "UNKNOWN") {
            $norm = preg_replace("/[^a-z0-9]/", "", strtolower($gene));

            if ($norm === "") {
                continue;
            }

            if (!isset($candidates[$norm])) {
                $candidates[$norm] = [
                    "label" => strtoupper($gene),
                    "kind" => "gene",
                    "url" => eic_ps_gene_url($gene),
                    "subtype_ids" => [],
                ];
            }

            if ($candidates[$norm]["kind"] === "gene") {
                $candidates[$norm]["subtype_ids"][] = $subtype_id;
            }
        }
    }

    // ------------------------------------------------------------
    // Score every candidate against every token
    // ------------------------------------------------------------
    $scored = [];

    foreach ($candidates as $norm => $item) {
        $best = PHP_INT_MAX;

        foreach ($tokens as $tok) {
            if (strlen($tok) < 2) {
                continue;
            }

            if ($tok === $norm) {
                $best = 0;
                break;
            }

            if (
                strlen($tok) >= 3 &&
                (str_starts_with($norm, $tok) || str_starts_with($tok, $norm))
            ) {
                $best = min($best, 1);
                continue;
            }

            if (
                strlen($tok) >= 3 &&
                strlen($norm) >= 3 &&
                abs(strlen($tok) - strlen($norm)) <= 2
            ) {
                $dist = levenshtein($tok, $norm);
                if ($dist <= 2) {
                    $best = min($best, $dist + 1);
                }
            }
        }

        if ($best !== PHP_INT_MAX) {
            $item["score"] = $best;
            $item["subtype_ids"] = array_values(
                array_unique($item["subtype_ids"])
            );
            $scored[] = $item;
        }
    }

    usort($scored, function ($a, $b) {
        if ($a["score"] !== $b["score"]) {
            return $a["score"] <=> $b["score"];
        }
        return strnatcasecmp($a["label"], $b["label"]);
    });

    return $scored;
}

/**
 * ============================================================
 *  No-Results Suggestions ("Did you mean")
 * ============================================================
 *
 * Runs ONLY when every result bucket is empty (weak fuzzy hits
 * the typo tier declined to auto-correct still surface here).
 */
function eic_ps_no_results_suggestions(string $raw_query, int $max = 5): array
{
    $suggestions = [];

    foreach (eic_ps_fuzzy_candidates($raw_query) as $cand) {
        if ($cand["url"] === "") {
            continue;
        }

        $suggestions[] = [
            "label" =>
                $cand["kind"] === "gene"
                    ? $cand["label"] . " (gene)"
                    : $cand["label"],
            "url" => $cand["url"],
        ];

        if (count($suggestions) >= $max) {
            break;
        }
    }

    return $suggestions;
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

    // Trim highlight terms early: they decide the text source below
    $terms_early = [];
    foreach ($highlight_terms as $t) {
        $t = trim((string) $t);
        if ($t !== "") {
            $terms_early[] = $t;
        }
    }

    // Prefer manual excerpt
    $text = trim((string) $post->post_excerpt);

    // Fallback to content
    if ($text === "") {
        $text = strip_shortcodes((string) $post->post_content);
    } elseif (!empty($terms_early)) {
        /**
         * The manual excerpt only wins when it actually mentions a
         * highlight term. Otherwise the match lives in the body
         * prose — window into that instead, so a KIF1B search shows
         * the sentence about KIF1B, not an unrelated summary.
         */
        $mentions = false;
        foreach ($terms_early as $t) {
            if (stripos($text, $t) !== false) {
                $mentions = true;
                break;
            }
        }

        if (!$mentions) {
            $body = strip_shortcodes((string) $post->post_content);
            foreach ($terms_early as $t) {
                if (stripos($body, $t) !== false) {
                    $text = $body;
                    break;
                }
            }
        }
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
