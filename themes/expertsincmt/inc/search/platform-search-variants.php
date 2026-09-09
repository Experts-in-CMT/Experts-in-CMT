<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Platform Search: Variant Resolution
 *
 * Feature: platform-search
 *
 * Purpose:
 * --------
 * Resolves a variant query (t424m, p.Thr424Met, HSPB3-P121L,
 * itpr3 c.1271C>T, rs104894520, VCV000041229) to the gene, the
 * variant as ClinVar records it, and the subtypes the gene causes,
 * with the gene page's ClinVar Variants card as the destination.
 *
 * The grammar and amino-acid map live in the EIC Variant Index
 * MU plugin (one place for index and search alike); this file is
 * the search-side use of them. Reads the raw query, since the
 * normalizer strips the dots, hyphens, and ">" that variant
 * notation is made of.
 *
 * Also provides eic_ps_gene_url(): gene pills in search results
 * land on the gene post, with the Gene Browser filter as the
 * fallback for a symbol that has no post yet.
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Raw query stash: set once by the resolver entry point, read by
 * the per-query context so resolvers can see the query as typed.
 */
function eic_ps_raw_query(?string $set = null): string
{
    static $raw = "";
    if ($set !== null) {
        $raw = $set;
    }
    return $raw;
}

/**
 * Gene result URL: the gene post when it exists, else the Gene
 * Browser filtered to the symbol.
 */
function eic_ps_gene_url(string $symbol): string
{
    $symbol = trim($symbol);
    if ($symbol === "") {
        return "";
    }
    if (function_exists("eic_gene_post_url")) {
        $url = eic_gene_post_url($symbol);
        if ($url !== "") {
            return $url;
        }
    }
    $browser = function_exists("eic_subtype_browser_page_url")
        ? eic_subtype_browser_page_url()
        : "";
    return $browser
        ? $browser . "?qs=" . urlencode(strtolower($symbol)) . "#results"
        : "";
}

/**
 * Is this token one of EIC's genes? Symbol or post title, any case.
 */
function eic_ps_variant_gene_token(string $tok): string
{
    $u = strtoupper(trim($tok, " \t\n\r\0\x0B,;:()[]"));
    if ($u === "" || strlen($u) > 32) {
        return "";
    }
    if (function_exists("eic_gene_post_for_symbol") && eic_gene_post_for_symbol($u)) {
        return $u;
    }
    return "";
}

/**
 * Split the raw query into candidate tokens. Whitespace first; a
 * token that is neither a gene nor a variant is split again on the
 * joiners people type between the two (HSPB3-P121L, ITPR3:p.T424M,
 * PMP22/T118M). Intronic cDNA keeps its hyphen because it is tried
 * whole first.
 */
function eic_ps_variant_tokens(string $raw): array
{
    $out = [];
    foreach (preg_split('/\s+/', trim($raw)) as $tok) {
        $tok = trim($tok);
        if ($tok === "") {
            continue;
        }
        if (
            eic_ps_variant_gene_token($tok) !== "" ||
            EIC_Variant_Index::parse_token($tok) !== null
        ) {
            $out[] = $tok;
            continue;
        }
        foreach (preg_split('/[-:\/,;]+/', $tok) as $part) {
            if ($part !== "") {
                $out[] = $part;
            }
        }
    }
    return $out;
}

/**
 * Build one result entry from an index row (or the cached-payload
 * fallback, which returns the same shape).
 */
function eic_ps_variant_entry(array $row, array $parsed): array
{
    $gene = strtoupper((string) ($row["gene"] ?? ""));
    $page = function_exists("eic_gene_post_url") ? eic_gene_post_url($gene) : "";
    $vcv = (string) ($row["vcv"] ?? "");
    $card = $page !== "" ? add_query_arg("v", $vcv, $page) . "#gene-variants" : "";

    return [
        "found" => true,
        "gene" => $gene,
        "gene_url" => eic_ps_gene_url($gene),
        "vcv" => $vcv,
        "title" => (string) ($row["title"] ?? ""),
        "protein3" => (string) ($row["protein3"] ?? ""),
        "protein1" => (string) ($row["protein1"] ?? ""),
        "cdna" => (string) ($row["cdna"] ?? ""),
        "rsid" => (string) ($row["rsid"] ?? ""),
        "classification" => (string) ($row["classification"] ?? ""),
        "stars" => (int) ($row["stars"] ?? 0),
        "tier" => (string) ($row["tier"] ?? ""),
        "url" => (string) ($row["url"] ?? ""),
        "card_url" => $card,
        "query" => (string) $parsed["display"],
    ];
}

/**
 * ============================================================
 *  Resolver: Variant (runs first; passes when no variant parses)
 * ============================================================
 *
 * Contract: array to stop resolution, null to pass.
 *
 *   gene + variant  → that gene's record(s) for the key, or a
 *                     not-found entry for that gene
 *   variant alone   → every gene holding the key (one-to-many);
 *                     with no hit, a not-found entry only when
 *                     the token wore explicit variant syntax
 *   gene alone      → pass (the gene resolver owns it)
 */
function eic_ps_resolve_variant(string $q, array $ctx): ?array
{
    if (!class_exists("EIC_Variant_Index")) {
        return null;
    }
    $raw = (string) ($ctx["raw"] ?? "");
    if ($raw === "") {
        return null;
    }

    $genes = [];
    $parsed = [];
    foreach (eic_ps_variant_tokens($raw) as $tok) {
        $g = eic_ps_variant_gene_token($tok);
        if ($g !== "") {
            $genes[$g] = $g; // a gene always wins over the grammar
            continue;
        }
        $p = EIC_Variant_Index::parse_token($tok);
        if ($p !== null) {
            $parsed[$p["key"]] = $p;
        }
    }
    if (!$parsed) {
        return null;
    }
    // Typed genes scope every lookup and stay fixed for the whole loop;
    // genes discovered by a site-wide hit or a near miss join the labels
    // (ride-alongs, highlights) without narrowing later keys.
    $scope_genes = array_values($genes);
    $label_genes = $scope_genes;

    $entries = [];
    $any_found = false;

    foreach ($parsed as $key => $p) {
        if ($scope_genes) {
            foreach ($scope_genes as $gene) {
                $rows = EIC_Variant_Index::lookup($key, $gene);
                if (!$rows) {
                    $rows = EIC_Variant_Index::lookup_cached($key, $gene);
                }
                if ($rows) {
                    foreach ($rows as $row) {
                        $entries[] = eic_ps_variant_entry($row, $p);
                    }
                    $any_found = true;
                } else {
                    // Did you mean: same residues, position a digit off
                    $near = [];
                    foreach (EIC_Variant_Index::near($key, $gene) as $row) {
                        $near[] = eic_ps_variant_entry($row, $p);
                    }
                    $entries[] = [
                        "found" => false,
                        "gene" => $gene,
                        "gene_url" => eic_ps_gene_url($gene),
                        "card_url" => function_exists("eic_gene_post_url") && eic_gene_post_url($gene) !== ""
                            ? eic_gene_post_url($gene) . "#gene-variants"
                            : "",
                        "query" => (string) $p["display"],
                        "near" => $near,
                    ];
                }
            }
        } else {
            $rows = EIC_Variant_Index::lookup($key);
            if ($rows) {
                foreach ($rows as $row) {
                    $entries[] = eic_ps_variant_entry($row, $p);
                    $label_genes[] = strtoupper((string) $row["gene"]);
                }
                $any_found = true;
            } else {
                // Site-wide did-you-mean; a bare one-letter token earns a
                // variant result only when something near exists
                $near = [];
                foreach (EIC_Variant_Index::near($key) as $row) {
                    $near[] = eic_ps_variant_entry($row, $p);
                }
                if ($near || !empty($p["explicit"])) {
                    $entries[] = [
                        "found" => false,
                        "gene" => "",
                        "gene_url" => "",
                        "card_url" => "",
                        "query" => (string) $p["display"],
                        "near" => $near,
                    ];
                    foreach ($near as $n) {
                        $label_genes[] = $n["gene"];
                    }
                }
            }
        }
    }

    if (!$entries) {
        return null;
    }
    $genes = array_values(array_unique($label_genes));
    $any_near = false;
    foreach ($entries as $e) {
        if (!empty($e["near"])) {
            $any_near = true;
        }
    }

    // The gene's own surface rides along: subtypes it causes (types
    // derive from them downstream), and content that names it
    $subtype_ids = [];
    $content_ids = [];
    $highlight = [];
    foreach ($genes as $gene) {
        if (function_exists("eic_ps_subtype_ids_for_gene")) {
            $subtype_ids = array_merge($subtype_ids, eic_ps_subtype_ids_for_gene($gene));
        }
        if (function_exists("eic_ps_content_search")) {
            $content_ids = array_merge($content_ids, eic_ps_content_search($gene));
        }
        $highlight[] = $gene;
    }
    foreach ($entries as $e) {
        foreach (array_merge([$e], (array) ($e["near"] ?? [])) as $item) {
            foreach (["query", "protein1", "protein3", "cdna", "rsid"] as $k) {
                if (!empty($item[$k])) {
                    $highlight[] = $item[$k];
                }
            }
        }
    }

    return [
        "subtypes" => array_values(array_unique(array_map("intval", $subtype_ids))),
        "types" => [],
        "content" => array_values(array_unique(array_map("intval", $content_ids))),
        "variants" => $entries,
        "meta" => [
            "note" => $any_found
                ? "variant"
                : ($any_near ? "variant (did you mean)" : "variant (not in ClinVar P/LP)"),
            "gene_labels" => $genes,
            "highlight" => array_values(array_unique($highlight)),
        ],
    ];
}
