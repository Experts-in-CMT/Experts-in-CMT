<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  [gene_browser] — Gene-Resolved Browser
 *  ------------------------------------------------------------
 *  A live, database-driven table of the CMT disease genes,
 *  resolved from the subtype store: one row per gene, expanding
 *  to its external records, stored identifiers, and subtype list.
 *
 *  Server-rendered from a WP_Query over published subtypes,
 *  grouped by gene_symbol, so every gene ships in the HTML.
 *  Search, facets, sort, A-Z jump and expand are client-side
 *  (small catalog). Styling is scoped to `.gbx` and namespaced
 *  `gbx-` so it is independent of the variant-mechanism table.
 *
 *  Unknown-gene subtypes are excluded (no gene, no row); the
 *  structural record (CMTX3) is included with no HGNC identifiers.
 *  The function blurb is deferred until a stored function field
 *  exists (next backfill).
 *
 *  Usage: [gene_browser]
 *  Location: /inc/shortcodes/gene-browser-table.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

if (shortcode_exists("gene_browser")) {
    return;
}

/** EIC canonical type_classification order (genes-type-order.php), display labels. */
if (!function_exists("eic_gb_canon")) {
    function eic_gb_canon(): array
    {
        return [
            "CMT1", "CMT2", "CMT4", "CMTX", "CMTDI", "CMTRI", "dHMN/HMN",
            "dSMA", "GAN", "HMSN", "HSAN", "HSN", "SMA-LEP", "Unclassified Subtypes",
        ];
    }
}

/** Classification family (pill colour) by display label. */
if (!function_exists("eic_gb_family")) {
    function eic_gb_family(string $c): string
    {
        $map = [
            "CMT1" => "blue", "CMT2" => "green", "CMT4" => "indigo",
            "CMTX" => "purple", "CMTDI" => "amber", "CMTRI" => "amber",
            "HMSN" => "slate", "HSAN" => "rose", "HSN" => "rose",
            "dHMN/HMN" => "orange", "dSMA" => "orange", "SMA-LEP" => "orange",
            "GAN" => "grey", "Unclassified Subtypes" => "grey",
        ];
        return $map[$c] ?? "grey";
    }
}

if (!function_exists("eic_gb_cls_label")) {
    function eic_gb_cls_label(string $c): string
    {
        return $c === "Unclassified Subtypes" ? "Unclassified" : $c;
    }
}

/** Inheritance string to short mode(s). A compound "A or B" yields each mode; [] if unrecognised. */
if (!function_exists("eic_gb_inh_modes")) {
    function eic_gb_inh_modes(string $s): array
    {
        $map = [
            "autosomal dominant" => "AD",
            "autosomal recessive" => "AR",
            "x-linked recessive" => "XLR",
            "x-linked dominant" => "XLD",
            "mitochondrial inheritance" => "Mito",
        ];
        $out = [];
        foreach (preg_split('/\s+or\s+/i', strtolower(trim($s))) as $part) {
            $part = trim($part);
            if (isset($map[$part]) && !in_array($map[$part], $out, true)) {
                $out[] = $map[$part];
            }
        }
        return $out;
    }
}

/** Sort rank of a chromosome token (1-22, then X, Y, MT). */
if (!function_exists("eic_gb_chr_order")) {
    function eic_gb_chr_order(string $c): int
    {
        if (ctype_digit($c)) {
            return (int) $c;
        }
        return ["X" => 98, "Y" => 99, "MT" => 100][$c] ?? 97;
    }
}

/** UniProt function blurb: first sentence shown, remainder behind a "more" toggle. */
if (!function_exists("eic_gb_func_block")) {
    function eic_gb_func_block(array $g): string
    {
        $full = trim((string) ($g["func"] ?? ""));
        if ($full === "") {
            return "";
        }
        if (preg_match('/^(.*?[.!?])(\s+)(.*)$/s', $full, $m)) {
            $lead = $m[1];
            $rest = trim($m[3]);
        } else {
            $lead = $full;
            $rest = "";
        }
        $uni = trim((string) ($g["uniprot"] ?? ""));
        $src = '<span class="gbx-func-src">Source: ' .
            ($uni !== ""
                ? '<a href="https://www.uniprot.org/uniprotkb/' . esc_attr($uni) . '" target="_blank" rel="noopener">UniProt</a>'
                : "UniProt") .
            "</span>";
        $more = $rest !== ""
            ? '<details><summary aria-label="Toggle the rest of the function description"></summary> <span class="gbx-func-full">' . esc_html($rest) . "</span></details> "
            : "";
        return '<div class="gbx-func">' . esc_html($lead) . " " . $more . $src . "</div>";
    }
}

/** ClinGen classification -> badge colour token. */
if (!function_exists("eic_gb_cg_color")) {
    function eic_gb_cg_color(string $c): string
    {
        $c = strtolower(trim($c));
        if ($c === "definitive" || $c === "strong") {
            return "green";
        }
        if ($c === "moderate") {
            return "amber";
        }
        if ($c === "limited") {
            return "purple";
        }
        if (in_array($c, ["disputed", "disputing", "refuted"], true)) {
            return "red";
        }
        return "grey";
    }
}

/** PanelApp rating -> badge colour token. */
if (!function_exists("eic_gb_pa_color")) {
    function eic_gb_pa_color(string $r): string
    {
        $r = strtolower(trim($r));
        return ["green" => "green", "amber" => "amber", "red" => "red"][$r] ?? "grey";
    }
}

/** ClinGen dosage score (1/2/3) -> evidence label. */
if (!function_exists("eic_gb_dosage_label")) {
    function eic_gb_dosage_label(string $s): string
    {
        return [
            "1" => "Little evidence",
            "2" => "Emerging evidence",
            "3" => "Sufficient evidence",
        ][trim($s)] ?? "";
    }
}

add_shortcode("gene_browser", function ($atts = []) {
    $canon = eic_gb_canon();
    $inh_order = ["AD", "AR", "XLR", "XLD", "Mito"];

    $q = new WP_Query([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "no_found_rows" => true,
        "orderby" => "title",
        "order" => "ASC",
    ]);

    $genes = [];

    foreach ($q->posts as $post) {
        $id = $post->ID;

        if (get_field("unknown_gene", $id)) {
            continue;
        }

        $gene = trim((string) get_field("gene_symbol", $id));
        if ($gene === "") {
            continue;
        }

        $cls = trim((string) get_field("acronym", $id));
        if ($cls === "Unclassified Subtype") {
            $cls = "Unclassified Subtypes";
        }

        $st = [
            "code" => trim((string) (get_field("subtype", $id) ?: get_the_title($id))),
            "slug" => $post->post_name,
            "class" => $cls,
            "inheritance" => trim((string) get_field("inheritance", $id)),
            "year" => trim((string) get_field("year_of_discovery", $id)),
            "omim_subtype" => trim((string) get_field("omim_subtype", $id)),
            "pub_title" => trim(wp_strip_all_tags((string) get_field("publication_title", $id))),
            "doi" => trim((string) get_field("doi_url", $id)),
        ];

        if (!isset($genes[$gene])) {
            // Plausible gene symbol => has HGNC records; structural (ISCN) => none.
            $plausible = (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $gene);
            $locus = trim((string) get_field("chromosome", $id));
            $chr = "";
            if (preg_match('/^([0-9]+|X|Y|MT)/', $locus, $m)) {
                $chr = $m[1];
            }
            $genes[$gene] = [
                "symbol" => $gene,
                "structural" => !$plausible,
                "full_name" => trim((string) get_field("full_gene_name", $id)),
                "alias" => trim((string) get_field("gene_alias", $id)),
                "locus" => $locus,
                "chr" => $chr,
                "hgnc_id" => trim((string) get_field("hgnc_id", $id)),
                "ensembl" => trim((string) get_field("ensembl_gene_id", $id)),
                "grch38" => trim((string) get_field("coords_grch38", $id)),
                "grch37" => trim((string) get_field("coords_grch37", $id)),
                "entrez" => trim((string) get_field("entrez_id", $id)),
                "omim_gene" => trim((string) get_field("omim_gene", $id)),
                "uniprot" => trim((string) get_field("uniprot_id", $id)),
                "refseq" => trim((string) get_field("refseq_accession", $id)),
                "mane_refseq" => trim((string) get_field("mane_select_refseq", $id)),
                "mane_ensembl" => trim((string) get_field("mane_select_ensembl", $id)),
                "func" => trim((string) get_field("gene_function", $id)),
                "clingen" => trim((string) get_field("clingen_url", $id)),
                "clinvar" => trim((string) get_field("clinvar_url", $id)),
                "genereviews" => trim((string) get_field("genereviews_url", $id)),
                "cg_class" => trim((string) get_field("clingen_classification", $id)),
                "cg_disease" => trim((string) get_field("clingen_disease", $id)),
                "cg_mondo" => trim((string) get_field("clingen_mondo", $id)),
                "cg_url" => trim((string) get_field("clingen_validity_url", $id)),
                "pa_rating" => trim((string) get_field("panelapp_rating", $id)),
                "pa_votes" => trim((string) get_field("panelapp_votes", $id)),
                "pa_url" => trim((string) get_field("panelapp_url", $id)),
                "cg_hi" => trim((string) get_field("clingen_hi", $id)),
                "cg_ts" => trim((string) get_field("clingen_ts", $id)),
                "cg_dose_url" => trim((string) get_field("clingen_dosage_url", $id)),
                "orphanet" => trim((string) get_field("orphanet_url", $id)),
                "mito" => false,
                "cand" => false,
                "genesis" => false,
                "classes" => [],
                "subtypes" => [],
            ];
        }

        $genes[$gene]["subtypes"][] = $st;
        if (get_field("mitochondrial_involvement", $id)) {
            $genes[$gene]["mito"] = true;
        }
        // Candidate gene association: counts as a gene, never as a subtype.
        if (get_field("candidate_gene", $id)) {
            $genes[$gene]["cand"] = true;
        }
        // GENESIS provenance: discovered/supported through the GENESIS platform.
        if (get_field("genesis_discovery", $id)) {
            $genes[$gene]["genesis"] = true;
        }
        if ($cls !== "" && !in_array($cls, $genes[$gene]["classes"], true)) {
            $genes[$gene]["classes"][] = $cls;
        }
    }
    wp_reset_postdata();

    // Derive per-gene rollups: sortkey, inheritance modes, subtype ordering,
    // classes in canon order, first year.
    $rank = array_flip($canon);
    foreach ($genes as $sym => &$g) {
        usort($g["subtypes"], function ($a, $b) {
            $ya = $a["year"] !== "" ? (int) $a["year"] : 9999;
            $yb = $b["year"] !== "" ? (int) $b["year"] : 9999;
            return $ya === $yb ? strcmp($a["code"], $b["code"]) : $ya - $yb;
        });
        $modes = [];
        foreach ($g["subtypes"] as $s) {
            foreach (eic_gb_inh_modes($s["inheritance"]) as $m) {
                if (!in_array($m, $modes, true)) {
                    $modes[] = $m;
                }
            }
        }
        usort($modes, fn($a, $b) => array_search($a, $inh_order) - array_search($b, $inh_order));
        // Candidates carry no inheritance in this view: forced empty so the row
        // shows a dash and they drop out of the inheritance facet.
        $g["modes"] = $g["cand"] ? [] : $modes;
        usort($g["classes"], fn($a, $b) => ($rank[$a] ?? 999) - ($rank[$b] ?? 999));
        $g["n"] = count($g["subtypes"]);
        $years = array_filter(array_map(fn($s) => ctype_digit($s["year"]) ? (int) $s["year"] : null, $g["subtypes"]), fn($v) => $v !== null);
        $g["first_year"] = $years ? (string) min($years) : "";
        // Structural record sorts by its subtype code (CMTX3), not the ISCN string.
        $g["sortkey"] = $g["structural"] && !empty($g["subtypes"]) ? strtoupper($g["subtypes"][0]["code"]) : strtoupper($sym);
    }
    unset($g);

    // Sort genes A-Z by sortkey.
    uasort($genes, fn($a, $b) => strcmp($a["sortkey"], $b["sortkey"]));

    // Facet counts (per gene).
    $cls_count = [];
    $inh_count = ["AD" => 0, "AR" => 0, "XLR" => 0, "XLD" => 0, "Mito" => 0];
    $mito_count = 0;
    $cand_count = 0;
    $chrs = [];
    $total_sub = 0;
    foreach ($genes as $g) {
        foreach ($g["classes"] as $c) {
            $cls_count[$c] = ($cls_count[$c] ?? 0) + 1;
        }
        foreach ($g["modes"] as $m) {
            $inh_count[$m]++;
        }
        if ($g["mito"]) {
            $mito_count++;
        }
        if ($g["cand"]) {
            $cand_count++;
        }
        if ($g["chr"] !== "") {
            $chrs[$g["chr"]] = true;
        }
        $total_sub += $g["cand"] ? 0 : $g["n"];
    }
    // Classification facets in canon order.
    $cls_facets = [];
    foreach ($canon as $c) {
        if (isset($cls_count[$c])) {
            $cls_facets[$c] = $cls_count[$c];
        }
    }
    // Chromosome dropdown order.
    $chr_list = array_keys($chrs);
    usort($chr_list, fn($a, $b) => eic_gb_chr_order($a) - eic_gb_chr_order($b));

    $total_genes = count($genes);


    // ---- chip + detail helpers ----
    $chip = function (string $label, string $href = "") {
        if ($href !== "") {
            return '<span class="gbx-chip"><a href="' . esc_url($href) . '" target="_blank" rel="noopener">' . esc_html($label) . "</a></span>";
        }
        return '<span class="gbx-chip gbx-chip--mane">' . esc_html($label) . "</span>";
    };
    $idrow = function (string $k, string $v) {
        $na = $v === "";
        return '<div class="gbx-idrow"><span class="gbx-k">' . esc_html($k) . '</span><span class="gbx-v' . ($na ? " gbx-na" : "") . '">' . ($na ? "not applicable" : esc_html($v)) . "</span></div>";
    };

    ob_start();
    ?>
<style>
.gbx{--gp:var(--primary,#174777);--gpl:var(--primary-light,#5ea0c9);--gtx:var(--text,#1c2530);--gmut:var(--muted,#6b7480);--gbd:var(--border,#e2e6ea);--gbg:var(--bg,#f6f8fa);--gr:14px;--gmono:"Fira Code",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;position:relative;left:50%;width:100vw;max-width:1180px;transform:translateX(-50%);padding-inline:12px;color:var(--gtx);margin:1.5rem 0 2.5rem;font-family:"Manrope",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.gbx *{box-sizing:border-box}
.gbx-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}
.gbx-filter{display:flex;flex-wrap:wrap;align-items:center;gap:1rem 1.5rem;padding:1.25rem 1.5rem;background:var(--gbg);border:1px solid var(--gbd);border-radius:28px 0 28px 28px;box-shadow:0 10px 30px rgb(14 42 84 / 5%)}
.gbx-filter__search{flex:1 1 320px;min-width:260px}
.gbx-search{width:100%;padding:.7rem .9rem .7rem 2.8rem;font:15px "Manrope",sans-serif;color:var(--gtx);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aa2ac' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat .9rem center;background-size:18px;border:1px solid var(--gbd);border-radius:10px}
/* Outrank the theme's global input[type=search] padding so the text clears the icon. */
.gbx .gbx-search{padding-left:2.8rem}
.gbx-search:focus-visible{outline:none;border-color:var(--gpl);box-shadow:0 0 0 3px color-mix(in srgb,var(--gp) 18%,transparent)}
/* Visible focus on every interactive element (rows, facets, A-Z, controls, links). --gp (primary) keeps the ring >=3:1 against white per WCAG 2.4.11. */
.gbx-row:focus-visible{outline:2px solid var(--gp);outline-offset:-2px}
.gbx-check input:focus-visible{outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--gp) 22%,transparent)}
.gbx-azl:focus-visible,.gbx-btn:focus-visible{outline:2px solid var(--gp);outline-offset:2px}
.gbx-detail__body a:focus-visible,.gbx-func summary:focus-visible,.gbx-ids summary:focus-visible{outline:2px solid var(--gp);outline-offset:2px;border-radius:2px}
.gbx-group{display:flex;flex-wrap:wrap;align-items:center;gap:6px 18px;margin:0;padding:0;border:0}
.gbx-legend{width:100%;margin:0 0 2px;padding:0;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gmut)}
.gbx-check{display:inline-flex;align-items:center;gap:8px;font-size:14px;line-height:1.2;cursor:pointer;user-select:none;color:var(--gtx)}
.gbx-check input{width:18px;height:18px;margin:0;border-radius:5px;accent-color:var(--gp);cursor:pointer}
.gbx-check .gbx-ct{color:var(--gmut)}
.gbx-actions{display:flex;flex-wrap:wrap;align-items:center;gap:.9rem;flex-basis:100%;width:100%}
.gbx-actions select{width:auto;flex:0 0 auto;font:13px "Manrope",sans-serif;padding:.5rem calc(var(--ctrl-pad-x,14px) * 2) .5rem .6rem;border:1px solid var(--gbd);border-radius:10px;background-color:#fff;color:var(--gtx)}
.gbx-btn{padding:.5rem 1rem;font:600 13px "Manrope",sans-serif;letter-spacing:.03em;color:var(--gp);background:#fff;border:1px solid var(--gbd);border-radius:20px;cursor:pointer}
.gbx-btn:hover{border-color:var(--gpl)}
.gbx-count{font-size:13px;color:var(--gmut);white-space:nowrap}
.gbx-count b{color:var(--gtx)}
.gbx-az{flex-basis:100%;display:flex;flex-wrap:wrap;gap:2px;margin-top:2px;padding-top:12px;border-top:1px solid var(--gbd)}
.gbx-azl{display:inline-flex;align-items:center;justify-content:center;font:600 12px var(--gmono);min-width:28px;height:28px;padding:0;border:0;background:none;color:var(--gp);border-radius:4px;cursor:pointer}
.gbx-azl:hover:not(:disabled){background:#e6eef4}
.gbx-azl:disabled,.gbx-azl.gbx-off{color:#9aa2ac;cursor:default}
.gbx-azl:disabled:hover,.gbx-azl.gbx-off:hover{background:none}
.gbx-headtable{position:sticky;top:var(--gbx-top,0px);z-index:30;width:calc(100% - 2px);margin:20px 1px 0;table-layout:fixed;border-collapse:separate;border-spacing:0}
.gbx-headtable th{text-align:left;padding:21px 14px;font-size:14px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#fff;background:var(--gp)}
.gbx-headtable th:first-child{border-top-left-radius:var(--gr)}
.gbx-headtable th:last-child{border-top-right-radius:var(--gr)}
.gbx-tablewrap{border:1px solid var(--gbd);border-top:0;border-radius:0 0 var(--gr) var(--gr);overflow:clip}
.gbx-table{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:0;font-size:14px;background:#fff}
.gbx-table thead{position:absolute;width:1px;height:1px;margin:-1px;overflow:hidden;clip-path:inset(50%)}
.gbx-c-gene{width:13%}.gbx-c-name{width:30%}.gbx-c-loc{width:12%}.gbx-c-inh{width:9%}.gbx-c-sub{width:9%}.gbx-c-cls{width:22%}.gbx-c-x{width:5%}
.gbx-row{cursor:pointer;scroll-margin-top:calc(var(--gbx-top,0px) + 70px)}
.gbx-row>td{padding:12px 14px;border-top:1px solid var(--gbd);vertical-align:middle;overflow-wrap:break-word}
.gbx-table tbody tr.gbx-row:first-child>td{border-top:0}
.gbx-row:hover>td{background:color-mix(in srgb,var(--gpl) 8%,#fff)}
.gbx-row[aria-expanded="true"]>td{background:color-mix(in srgb,var(--gpl) 12%,#fff);border-bottom:0}
.gbx-gene em{color:#26719c;font-weight:600;font-style:italic}
.gbx-gene.gbx-notgene em{font-style:normal;font-family:var(--gmono);font-size:12px}
.gbx-name{color:var(--gmut)}
.gbx-loc{font-family:var(--gmono);color:var(--gtx);font-size:13px}
.gbx-inh{white-space:nowrap;color:var(--gmut);font-weight:600}
.gbx-sub{font-variant-numeric:tabular-nums;font-weight:700;color:var(--gtx)}
.gbx-pill{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;white-space:nowrap;margin:2px 4px 2px 0}
.gbx-f-blue{background:#e3f0ff;color:#0b4a8f}.gbx-f-green{background:#e6f6ef;color:#0f6b45}
.gbx-f-purple{background:#efe4ff;color:#5b21b6}.gbx-f-indigo{background:#e5e8fb;color:#3538a3}
.gbx-f-amber{background:#fdf0d8;color:#8a5a00}.gbx-f-orange{background:#ffe9e0;color:#9a3412}
.gbx-f-rose{background:#ffe4ef;color:#9b2f5a}.gbx-f-slate{background:#e9edf3;color:#43536b}
.gbx-f-grey{background:#eceff2;color:#5b6675}
.gbx-toggle{text-align:right}
.gbx-plus::before{content:"+";display:inline-block;font-size:22px;font-weight:400;line-height:1;color:var(--gpl);transition:transform .2s ease}
.gbx-row[aria-expanded="true"] .gbx-plus::before{transform:rotate(45deg)}
@media(prefers-reduced-motion:reduce){.gbx-plus::before{transition:none}}
.gbx-detail>td{padding:0;border-top:0}
.gbx-detail__body{padding:6px 18px 20px;background:color-mix(in srgb,var(--gpl) 12%,#fff);border-bottom:1px solid var(--gbd)}
.gbx-exnote{padding:9px 12px;border:1px solid var(--gbd);background:#fff;border-radius:6px;font-size:12.5px;color:var(--gmut);line-height:1.5}
.gbx-func{font-size:14px;color:var(--gtx);line-height:1.5;max-width:80ch;margin:2px 0 6px}
.gbx-func-src{display:inline-block;margin-left:5px;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--gmut);border:1px solid var(--gbd);border-radius:3px;padding:0 5px;white-space:nowrap}
.gbx-func-src a{color:var(--gmut);text-decoration:none}
.gbx-func details{display:inline}.gbx-func summary{display:inline;cursor:pointer;color:var(--gp);font-size:12px;list-style:none}
.gbx-func summary::-webkit-details-marker{display:none}
.gbx-func summary::before{content:"more"}.gbx-func details[open] summary::before{content:"less"}
.gbx-func-full{color:var(--gmut)}
.gbx-meta{display:flex;flex-wrap:wrap;gap:7px 9px;margin-top:12px;font-size:12px;color:var(--gmut)}
.gbx-mtag{border:1px solid var(--gbd);border-radius:3px;padding:2px 8px;background:#fff;color:var(--gtx)}
.gbx-mtag b{font-weight:700}.gbx-mtag.gbx-null{color:var(--gmut);background:#f1f4f6}
.gbx-links{display:flex;flex-wrap:wrap;align-items:center;gap:7px 9px;margin-top:12px;padding-top:12px;border-top:1px dashed var(--gbd);font-size:12px}
.gbx-links .gbx-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--gmut);font-weight:700}
.gbx-chip{border:1px solid var(--gbd);border-radius:3px;padding:2px 8px;background:#fff;font-family:var(--gmono);font-size:11.5px}
.gbx-chip a{color:var(--gp);text-decoration:none}.gbx-chip a:hover{text-decoration:underline}.gbx-chip--mane{background:#f1f4f6;color:var(--gmut)}
.gbx-ev{display:inline-flex;align-items:baseline;gap:6px;border-radius:4px;padding:3px 9px;font-size:12px;font-weight:700;line-height:1.35}
.gbx-ev a{color:inherit;text-decoration:none}.gbx-ev a:hover{text-decoration:underline}
.gbx-ev-k{font-size:9.5px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}
.gbx-ev-dis{font-weight:500;font-size:11px}
.gbx-ev-green{background:#e6f6ef;color:#0f6b45}.gbx-ev-amber{background:#fdf0d8;color:#8a5a00}
.gbx-ev-purple{background:#efe4ff;color:#5b21b6}.gbx-ev-red{background:#ffe4ef;color:#9b2f5a}
.gbx-ev-grey{background:#eceff2;color:#5b6675}.gbx-ev-dose{background:#e8eefc;color:#2a3f8f}
.gbx-matrix{overflow-x:auto;margin-top:14px;border:1px solid var(--gbd);border-radius:8px;background:#fff}
.gbx-matrix table{border-collapse:collapse;width:100%;font-size:12.5px}
.gbx-matrix th,.gbx-matrix td{padding:8px 13px;text-align:left;vertical-align:top;border-bottom:1px solid var(--gbd)}
.gbx-matrix thead th{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--gmut);font-weight:700;background:#fbfcfd;white-space:nowrap}
.gbx-matrix tbody tr:last-child td{border-bottom:none}
.gbx-matrix td.gbx-st{font-family:var(--gmono);font-weight:600;white-space:nowrap}
.gbx-matrix td.gbx-st a{color:var(--gp);text-decoration:none}
.gbx-matrix td.gbx-yr{font-variant-numeric:tabular-nums;white-space:nowrap}
.gbx-clsm{display:inline-block;font-size:10px;font-weight:700;border:1px solid var(--gbd);border-radius:3px;padding:1px 6px;background:#fff}
.gbx-pub{max-width:430px}.gbx-pub-t{line-height:1.35}.gbx-pub-t a{color:var(--gp);text-decoration:none}
.gbx-pub-m{font-size:11px;color:var(--gmut);margin-top:2px}.gbx-none{color:#5b6675}
.gbx-ids{margin-top:12px;font-size:12px}
.gbx-ids summary{cursor:pointer;padding:6px 0;color:var(--gmut);font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;list-style:none}
.gbx-ids summary::-webkit-details-marker{display:none}
.gbx-ids summary::before{content:"\25b8 ";color:var(--gmut)}.gbx-ids[open] summary::before{content:"\25be "}
.gbx-idgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:4px 20px;padding:2px 0 6px}
.gbx-idrow{display:flex;gap:8px;font-family:var(--gmono);font-size:11.5px}
.gbx-k{color:var(--gmut);min-width:96px}.gbx-v{color:var(--gtx);word-break:break-all}.gbx-v.gbx-na{color:var(--gmut);font-style:italic}
.gbx-empty td{padding:2.5rem;text-align:center;color:var(--gmut)}
/* ---- Phone: stacked cards (mirrors the variant-mechanism table) ----
   Seven fixed columns cannot hold shape on a phone (the Subtypes count column
   stole width while the classification pills were crushed), so each gene becomes
   its own card. The accordion still drives open/close and the A-Z jump still
   lands on the row. */
@media(max-width:640px){
  .gbx-filter{padding:1rem;border-radius:0 0 18px 18px;gap:1rem 1.25rem}
  .gbx-actions{gap:.75rem}
  .gbx-actions select{flex:1 1 auto;min-width:140px}
  .gbx-count{flex-basis:100%;order:-1}
  .gbx-btn{white-space:nowrap}
  .gbx-azl{min-width:40px;height:44px;flex:1 0 auto;font-size:15px}
  .gbx-headtable{display:none}
  .gbx-tablewrap{border:0;border-radius:0;overflow:visible;background:transparent;margin-top:1rem}
  .gbx-table{display:block;font-size:14px}
  .gbx-table tbody{display:block}
  .gbx-table td{display:block;width:auto}
  /* :not([hidden]) is load-bearing: it keeps filtered-out rows and collapsed
     detail rows hidden once tr becomes a block. */
  .gbx-table tr:not([hidden]){display:block}
  .gbx-table tr[hidden]{display:none!important}
  /* Higher specificity than the block rule above so the card wins display:flex
     (and its `order` therefore applies). */
  .gbx-table .gbx-row:not([hidden]){display:flex;flex-direction:column;position:relative;margin:0 0 12px;padding:14px 46px 14px 16px;background:#fff;border:1px solid var(--gbd);border-radius:var(--gr);cursor:pointer}
  .gbx-row>td{padding:0;border:0}
  .gbx-row:hover>td,.gbx-row[aria-expanded="true"]>td{background:transparent}
  /* Card order: gene symbol, full name, classification pills, then labelled meta. */
  .gbx-gene{order:1;font-size:16px;font-weight:700}
  .gbx-name{display:block;order:2;margin-top:2px;color:var(--gmut);font-size:13px}
  .gbx-cls{order:3;margin-top:9px}
  .gbx-sub{order:4}
  .gbx-inh{display:block;order:5}
  .gbx-loc{display:block;order:6}
  .gbx-sub,.gbx-inh,.gbx-loc{margin-top:7px;font-size:13px;white-space:normal}
  .gbx-sub::before,.gbx-inh::before,.gbx-loc::before{margin-right:5px;font-family:"Manrope",-apple-system,sans-serif;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--gmut)}
  .gbx-sub::before{content:"Subtypes:"}
  .gbx-inh::before{content:"Inheritance:"}
  .gbx-loc::before{content:"Locus:"}
  .gbx-toggle{position:absolute;top:10px;right:14px}
  /* Open card merges with its detail panel into one unit. */
  .gbx-table .gbx-row[aria-expanded="true"]{margin-bottom:0;border-color:color-mix(in srgb,var(--gpl) 40%,var(--gbd));border-bottom:0;border-radius:var(--gr) var(--gr) 0 0;background:color-mix(in srgb,var(--gpl) 8%,#fff)}
  .gbx-detail>td{padding:0}
  .gbx-detail__body{margin:0 0 12px;padding:4px 14px 16px;border:1px solid color-mix(in srgb,var(--gpl) 40%,var(--gbd));border-top:0;border-radius:0 0 var(--gr) var(--gr);background:color-mix(in srgb,var(--gpl) 8%,#fff)}
}
</style>

<div class="gbx" data-total="<?php echo (int) $total_genes; ?>">
  <div class="gbx-filter" role="search">
    <label class="gbx-filter__search">
      <span class="gbx-sr">Search gene, name, alias, locus, or subtype</span>
      <input type="search" class="gbx-search" placeholder="ex: MFN2, mitofusin 2, 1p36.22, HGNC:16877…" autocomplete="off" spellcheck="false" />
    </label>

    <fieldset class="gbx-group">
      <legend class="gbx-legend">Subtype Classification</legend>
      <?php foreach ($cls_facets as $c => $ct): ?>
        <label class="gbx-check"><input type="checkbox" class="gbx-facet" data-facet="cls" value="<?php echo esc_attr(
            $c
        ); ?>"><span><?php echo esc_html(eic_gb_cls_label($c)); ?> <span class="gbx-ct">(<?php echo (int) $ct; ?>)</span></span></label>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="gbx-group">
      <legend class="gbx-legend">Inheritance</legend>
      <?php foreach ($inh_order as $m):
          if ($inh_count[$m] < 1) {
              continue;
          } ?>
        <label class="gbx-check"><input type="checkbox" class="gbx-facet" data-facet="inh" value="<?php echo esc_attr(
            $m
        ); ?>"><span><?php echo esc_html($m); ?> <span class="gbx-ct">(<?php echo (int) $inh_count[
    $m
]; ?>)</span></span></label>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="gbx-group">
      <legend class="gbx-legend">Mitochondrial</legend>
      <label class="gbx-check"><input type="checkbox" class="gbx-facet" data-facet="mito" value="1"><span>Mito-implicated <span class="gbx-ct">(<?php echo (int) $mito_count; ?>)</span></span></label>
    </fieldset>

    <fieldset class="gbx-group">
      <legend class="gbx-legend">Candidate</legend>
      <label class="gbx-check"><input type="checkbox" class="gbx-facet" data-facet="cand" value="1"><span>Candidate genes <span class="gbx-ct">(<?php echo (int) $cand_count; ?>)</span></span></label>
    </fieldset>

    <div class="gbx-actions">
      <select class="gbx-chr"><option value="">All chromosomes</option><?php foreach ($chr_list as $c): ?><option value="<?php echo esc_attr(
    $c
); ?>">Chr <?php echo esc_html($c); ?></option><?php endforeach; ?></select>
      <select class="gbx-sort">
        <option value="sym">Sort: A–Z</option>
        <option value="n">Sort: most subtypes</option>
        <option value="chr">Sort: by chromosome</option>
        <option value="yr">Sort: earliest described</option>
      </select>
      <button type="button" class="gbx-btn gbx-toggleall">OPEN ALL</button>
      <button type="button" class="gbx-btn gbx-reset">RESET</button>
      <span class="gbx-count" aria-live="polite">Showing <b><?php echo (int) $total_genes; ?></b> genes</span>
      <div class="gbx-az" role="group" aria-label="Jump to gene by initial letter"><?php foreach (str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZ") as $L): ?><button type="button" class="gbx-azl" data-l="<?php echo esc_attr(
    $L
); ?>" aria-label="Jump to genes starting with <?php echo esc_attr($L); ?>"><?php echo esc_html($L); ?></button><?php endforeach; ?></div>
    </div>
  </div>

  <table class="gbx-headtable" aria-hidden="true">
    <colgroup><col class="gbx-c-gene"><col class="gbx-c-name"><col class="gbx-c-loc"><col class="gbx-c-inh"><col class="gbx-c-sub"><col class="gbx-c-cls"><col class="gbx-c-x"></colgroup>
    <thead><tr><th class="gbx-h-gene">Gene</th><th class="gbx-h-name">Full name</th><th class="gbx-h-loc">Locus</th><th class="gbx-h-inh">Inh.</th><th>Subtypes</th><th>Subtype Classification</th><th></th></tr></thead>
  </table>

  <div class="gbx-tablewrap">
    <table class="gbx-table">
      <colgroup><col class="gbx-c-gene"><col class="gbx-c-name"><col class="gbx-c-loc"><col class="gbx-c-inh"><col class="gbx-c-sub"><col class="gbx-c-cls"><col class="gbx-c-x"></colgroup>
      <thead><tr><th scope="col">Gene</th><th scope="col">Full name</th><th scope="col">Locus</th><th scope="col">Inh.</th><th scope="col">Subtypes</th><th scope="col">Subtype Classification</th><th scope="col"><span class="gbx-sr">Details</span></th></tr></thead>
      <tbody>
        <?php $ridc = 0; foreach ($genes as $g):
            $init = strtoupper(substr($g["sortkey"], 0, 1));
            // Stable, unique id linking each summary row (role=button) to the
            // detail row it expands, via aria-controls. Counter suffix guards
            // against two symbols sanitizing to the same slug.
            $rid = "gbx-d-" . sanitize_title($g["symbol"]) . "-" . ++$ridc;
            $haystack = strtolower(
                $g["symbol"] . " " . $g["full_name"] . " " . $g["alias"] . " " . $g["locus"] . " " . $g["hgnc_id"] . " " . $g["func"] . " " .
                implode(" ", array_map(fn($s) => $s["code"], $g["subtypes"]))
            );
            $pills = "";
            foreach ($g["classes"] as $c) {
                $pills .= '<span class="gbx-pill gbx-f-' . eic_gb_family($c) . '">' . esc_html(eic_gb_cls_label($c)) . "</span>";
            }
            // build detail
            $links = [];
            if (!$g["structural"]) {
                if ($g["hgnc_id"]) {
                    $links[] = $chip($g["hgnc_id"], "https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/" . $g["hgnc_id"]);
                }
                if ($g["omim_gene"]) {
                    $links[] = $chip("OMIM " . $g["omim_gene"], "https://omim.org/entry/" . $g["omim_gene"]);
                }
                if ($g["ensembl"]) {
                    $links[] = $chip("gnomAD", "https://gnomad.broadinstitute.org/gene/" . $g["ensembl"]);
                }
                if ($g["uniprot"]) {
                    $links[] = $chip("UniProt " . $g["uniprot"], "https://www.uniprot.org/uniprotkb/" . $g["uniprot"]);
                }
                if ($g["clingen"]) {
                    $links[] = $chip("ClinGen", $g["clingen"]);
                }
                if ($g["clinvar"]) {
                    $links[] = $chip("ClinVar P/LP", $g["clinvar"]);
                }
                if ($g["genereviews"]) {
                    $links[] = $chip("GeneReviews", $g["genereviews"]);
                }
                if ($g["orphanet"]) {
                    $links[] = $chip("Orphanet", $g["orphanet"]);
                }
                if ($g["mane_refseq"]) {
                    $links[] = $chip("MANE " . $g["mane_refseq"], "https://www.ncbi.nlm.nih.gov/nuccore/" . $g["mane_refseq"]);
                }
                if ($g["genesis"]) {
                    $links[] = $chip("GENESIS discovery", "https://www.tgp-foundation.org/d-i-s-c-o-v-e-r-i-e-s");
                }
            }

            // Graded external evidence (ClinGen CMT-GCEP validity, PanelApp 846),
            // rendered as coloured badges in a separate Evidence line.
            $evidence = [];
            if (!$g["structural"]) {
                if ($g["cg_class"] !== "") {
                    $col = eic_gb_cg_color($g["cg_class"]);
                    $inner = $g["cg_url"] !== ""
                        ? '<a href="' . esc_url($g["cg_url"]) . '" target="_blank" rel="noopener">' . esc_html($g["cg_class"]) . "</a>"
                        : esc_html($g["cg_class"]);
                    $dis = $g["cg_disease"] !== ""
                        ? ' <span class="gbx-ev-dis">' . esc_html($g["cg_disease"]) . "</span>"
                        : "";
                    $evidence[] = '<span class="gbx-ev gbx-ev-' . $col . '"><span class="gbx-ev-k">ClinGen</span> ' . $inner . $dis . "</span>";
                }
                if ($g["pa_rating"] !== "") {
                    $col = eic_gb_pa_color($g["pa_rating"]);
                    $title = $g["pa_votes"] !== ""
                        ? ' title="Reviewer ratings green;amber;red — ' . esc_attr($g["pa_votes"]) . '"'
                        : "";
                    $inner = $g["pa_url"] !== ""
                        ? '<a href="' . esc_url($g["pa_url"]) . '" target="_blank" rel="noopener">' . esc_html($g["pa_rating"]) . "</a>"
                        : esc_html($g["pa_rating"]);
                    $evidence[] = '<span class="gbx-ev gbx-ev-' . $col . '"' . $title . '><span class="gbx-ev-k">PanelApp</span> ' . $inner . "</span>";
                }
                // ClinGen dosage — stated only where evidence is available (HI/TS score set).
                if ($g["cg_hi"] !== "" || $g["cg_ts"] !== "") {
                    $dparts = [];
                    if ($g["cg_hi"] !== "") {
                        $dparts[] = "HI " . esc_html($g["cg_hi"]) . " · " . esc_html(eic_gb_dosage_label($g["cg_hi"]));
                    }
                    if ($g["cg_ts"] !== "") {
                        $dparts[] = "TS " . esc_html($g["cg_ts"]) . " · " . esc_html(eic_gb_dosage_label($g["cg_ts"]));
                    }
                    $txt = implode(' <span class="gbx-ev-dis">·</span> ', $dparts);
                    $inner = $g["cg_dose_url"] !== ""
                        ? '<a href="' . esc_url($g["cg_dose_url"]) . '" target="_blank" rel="noopener">' . $txt . "</a>"
                        : $txt;
                    $evidence[] = '<span class="gbx-ev gbx-ev-dose"><span class="gbx-ev-k">ClinGen dosage</span> ' . $inner . "</span>";
                }
            }
            ?>
        <tr class="gbx-row" tabindex="0" role="button" aria-expanded="false"
            aria-controls="<?php echo esc_attr($rid); ?>"
            data-init="<?php echo esc_attr($init); ?>"
            data-classes="<?php echo esc_attr(implode("|", $g["classes"])); ?>"
            data-inh="<?php echo esc_attr(implode("|", $g["modes"])); ?>"
            data-mito="<?php echo $g["mito"] ? "1" : "0"; ?>"
            data-cand="<?php echo $g["cand"] ? "1" : "0"; ?>"
            data-chr="<?php echo esc_attr($g["chr"]); ?>"
            data-chrorder="<?php echo (int) eic_gb_chr_order($g["chr"]); ?>"
            data-n="<?php echo (int) $g["n"]; ?>"
            data-subs="<?php echo (int) ($g["cand"] ? 0 : $g["n"]); ?>"
            data-year="<?php echo (int) ($g["first_year"] !== "" ? $g["first_year"] : 9999); ?>"
            data-sortkey="<?php echo esc_attr($g["sortkey"]); ?>"
            data-text="<?php echo esc_attr($haystack); ?>">
          <td class="gbx-gene<?php echo $g["structural"] ? " gbx-notgene" : ""; ?>"><em><?php echo esc_html(
    $g["symbol"]
); ?></em></td>
          <td class="gbx-name"><?php echo esc_html($g["full_name"]); ?></td>
          <td class="gbx-loc"><?php echo esc_html($g["locus"] !== "" ? $g["locus"] : "n/a"); ?></td>
          <td class="gbx-inh"><?php echo $g["modes"] ? esc_html(implode(", ", $g["modes"])) : '<span class="gbx-none">—</span>'; ?></td>
          <td class="gbx-sub"><?php echo $g["cand"] ? '<span class="gbx-none">—</span>' : (int) $g["n"]; ?></td>
          <td class="gbx-cls"><?php echo $pills; ?></td>
          <td class="gbx-toggle"><span class="gbx-plus" aria-hidden="true"></span></td>
        </tr>
        <tr class="gbx-detail" id="<?php echo esc_attr($rid); ?>" hidden>
          <td colspan="7">
            <div class="gbx-detail__body">
              <?php echo eic_gb_func_block($g); ?>
              <div class="gbx-meta">
                <?php if ($g["full_name"] !== ""): ?>
                <span class="gbx-mtag">Gene name: <b><?php echo esc_html($g["full_name"]); ?></b></span>
                <?php endif; ?>
                <?php if (!$g["cand"]): ?>
                <span class="gbx-mtag"><b><?php echo (int) $g["n"]; ?></b> subtype<?php echo $g["n"] > 1 ? "s" : ""; ?></span>
                <?php endif; ?>
                <span class="gbx-mtag">Locus <b><?php echo esc_html(
    $g["locus"] !== "" ? $g["locus"] : "n/a"
); ?></b></span>
                <?php if ($g["structural"]): ?>
                  <span class="gbx-mtag">ISCN Notation</span>
                <?php elseif ($g["alias"] !== ""): ?>
                  <span class="gbx-mtag">HGNC Aliases: <?php echo esc_html($g["alias"]); ?></span>
                <?php else: ?>
                  <span class="gbx-mtag gbx-null">No HGNC Aliases</span>
                <?php endif; ?>
                <?php if ($g["first_year"] !== ""): ?><span class="gbx-mtag">First described <b><?php echo esc_html(
    $g["first_year"]
); ?></b></span><?php endif; ?>
              </div>
              <?php if ($g["structural"]): ?>
                <div class="gbx-exnote">Counted here as a gene although its cause is a structural insertion rather than a coding gene. Named by ISCN notation, it has no HGNC record, so gene-level external identifiers do not apply. EIC treats the identified causative element as its gene for this view.</div>
              <?php else: ?>
                <div class="gbx-links"><span class="gbx-lbl">External records</span><?php echo implode(
    "",
    $links
); ?></div>
                <?php if ($evidence): ?>
                <div class="gbx-links"><span class="gbx-lbl">Evidence</span><?php echo implode(
    "",
    $evidence
); ?></div>
                <?php endif; ?>
              <?php endif; ?>
              <div class="gbx-matrix"><table>
                <thead><tr><th scope="col">Subtype</th><th scope="col">Inheritance</th><th scope="col">Class</th><th scope="col">Described</th><th scope="col">OMIM</th><th scope="col">Defining publication</th></tr></thead>
                <tbody>
                  <?php foreach ($g["subtypes"] as $s): ?>
                  <tr>
                    <td class="gbx-st"><?php echo $s["slug"]
                        ? '<a href="' . esc_url(home_url("/subtype/" . $s["slug"] . "/")) . '" target="_blank" rel="noopener">' . esc_html($s["code"]) . "</a>"
                        : esc_html($s["code"]); ?></td>
                    <td><?php echo (!$g["cand"] && $s["inheritance"] !== "")
                        ? esc_html($s["inheritance"])
                        : '<span class="gbx-none">' . ($g["cand"] ? "—" : "not recorded") . "</span>"; ?></td>
                    <td><?php echo $s["class"] !== "" ? '<span class="gbx-clsm">' . esc_html($s["class"]) . "</span>" : ""; ?></td>
                    <td class="gbx-yr"><?php echo $s["year"] !== "" ? esc_html($s["year"]) : '<span class="gbx-none">n/a</span>'; ?></td>
                    <td class="gbx-yr"><?php echo $s["omim_subtype"] !== ""
                        ? '<a href="' . esc_url("https://omim.org/entry/" . $s["omim_subtype"]) . '" target="_blank" rel="noopener" style="color:var(--gp);text-decoration:none">' . esc_html($s["omim_subtype"]) . "</a>"
                        : '<span class="gbx-none">n/a</span>'; ?></td>
                    <td class="gbx-pub"><?php if ($s["pub_title"] !== ""): ?><div class="gbx-pub-t"><?php echo $s["doi"] !== ""
    ? '<a href="' . esc_url($s["doi"]) . '" target="_blank" rel="noopener">' . esc_html($s["pub_title"]) . "</a>"
    : esc_html($s["pub_title"]); ?></div><?php if ($s["doi"] !== ""): ?><div class="gbx-pub-m"><?php echo esc_html(
    preg_replace('#^https?://(dx\.)?doi\.org/#', "", $s["doi"])
); ?></div><?php endif; ?><?php else: ?><span class="gbx-none">not recorded</span><?php endif; ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
              <?php if (!$g["structural"]): ?>
              <details class="gbx-ids"><summary>All stored identifiers</summary><div class="gbx-idgrid">
                <?php echo $idrow("hgnc_id", $g["hgnc_id"]);
                echo $idrow("ensembl_gene_id", $g["ensembl"]);
                echo $idrow("coords_grch38", $g["grch38"]);
                echo $idrow("coords_grch37", $g["grch37"]);
                echo $idrow("entrez_id", $g["entrez"]);
                echo $idrow("omim_gene", $g["omim_gene"]);
                echo $idrow("uniprot_ids", $g["uniprot"]);
                echo $idrow("refseq_accession", $g["refseq"]);
                echo $idrow("mane_refseq", $g["mane_refseq"]);
                echo $idrow("mane_ensembl", $g["mane_ensembl"]); ?>
              </div></details>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="gbx-empty" hidden><td colspan="7">No genes match.</td></tr>
      </tbody>
    </table>
  </div>

</div>

<script>
(function () {
  var root = document.querySelector('.gbx');
  if (!root || root.dataset.init) { return; }
  root.dataset.init = '1';

  var search = root.querySelector('.gbx-search');
  var facets = [].slice.call(root.querySelectorAll('.gbx-facet'));
  var chrSel = root.querySelector('.gbx-chr');
  var sortSel = root.querySelector('.gbx-sort');
  var tbody = root.querySelector('.gbx-table tbody');
  var rows = [].slice.call(root.querySelectorAll('.gbx-row'));
  var empty = root.querySelector('.gbx-empty');
  var countEl = root.querySelector('.gbx-count');
  var az = root.querySelector('.gbx-az');
  var totalGenes = rows.length;

  function stickyTop() {
    var bar = document.getElementById('wpadminbar');
    root.style.setProperty('--gbx-top', (bar ? bar.offsetHeight : 0) + 'px');
  }
  stickyTop();
  window.addEventListener('resize', stickyTop);
  window.addEventListener('load', stickyTop);

  function detailOf(row) {
    var d = row.nextElementSibling;
    return d && d.classList.contains('gbx-detail') ? d : null;
  }
  function setOpen(row, open) {
    row.setAttribute('aria-expanded', open ? 'true' : 'false');
    var d = detailOf(row);
    if (d) { d.hidden = !open || row.hidden; }
  }
  function activeSet(facet) {
    var out = {};
    facets.forEach(function (f) { if (f.dataset.facet === facet && f.checked) { out[f.value] = true; } });
    return Object.keys(out).length ? out : null;
  }

  function apply() {
    var cls = activeSet('cls'), inh = activeSet('inh');
    var mito = root.querySelector('.gbx-facet[data-facet="mito"]').checked;
    var cand = root.querySelector('.gbx-facet[data-facet="cand"]').checked;
    var fc = chrSel.value;
    var q = (search.value || '').trim().toLowerCase();
    var shown = 0, subs = 0;
    var inits = {};
    rows.forEach(function (row) {
      var ok = true;
      if (fc && row.dataset.chr !== fc) { ok = false; }
      if (ok && mito && row.dataset.mito !== '1') { ok = false; }
      if (ok && cand && row.dataset.cand !== '1') { ok = false; }
      if (ok && cls) {
        var rc = row.dataset.classes ? row.dataset.classes.split('|') : [];
        if (!rc.some(function (c) { return cls[c]; })) { ok = false; }
      }
      if (ok && inh) {
        var ri = row.dataset.inh ? row.dataset.inh.split('|') : [];
        if (!ri.some(function (m) { return inh[m]; })) { ok = false; }
      }
      if (ok && q && row.dataset.text.indexOf(q) === -1) { ok = false; }
      row.hidden = !ok;
      var d = detailOf(row);
      if (d) { d.hidden = !ok || row.getAttribute('aria-expanded') !== 'true'; }
      if (ok) { shown++; subs += (+row.dataset.subs || 0); inits[row.dataset.init] = true; }
    });
    if (empty) { empty.hidden = shown !== 0; }
    if (countEl) {
      // Subtype total shows only inside an active filter (facet / chromosome /
      // search), where it reads as subtypes among the filtered genes. At rest the
      // count is gene-only, so the table carries no permanent subtype figure to
      // reconcile against the other surfaces. Sort and A-Z do not count as filters.
      var filtered = !!(cls || inh || mito || cand || fc || q);
      countEl.innerHTML = filtered
        ? 'Showing <b>' + shown + '</b> of ' + totalGenes + ' genes \u00b7 <b>' + subs + '</b> subtypes'
        : 'Showing <b>' + totalGenes + '</b> genes';
    }
    az.querySelectorAll('.gbx-azl').forEach(function (b) { var off = !inits[b.dataset.l]; b.classList.toggle('gbx-off', off); b.disabled = off; });
  }

  function sortRows() {
    var key = sortSel.value;
    var pairs = rows.map(function (r) { return [r, detailOf(r)]; });
    pairs.sort(function (a, b) {
      var x = a[0].dataset, y = b[0].dataset;
      if (key === 'n') { return (+y.n) - (+x.n) || x.sortkey.localeCompare(y.sortkey); }
      if (key === 'chr') { return (+x.chrorder) - (+y.chrorder) || x.sortkey.localeCompare(y.sortkey); }
      if (key === 'yr') { return (+x.year) - (+y.year) || x.sortkey.localeCompare(y.sortkey); }
      return x.sortkey.localeCompare(y.sortkey);
    });
    pairs.forEach(function (p) { tbody.appendChild(p[0]); if (p[1]) { tbody.appendChild(p[1]); } });
    if (empty) { tbody.appendChild(empty); }
  }

  rows.forEach(function (row) {
    row.addEventListener('click', function (e) { if (e.target.closest('a')) { return; } setOpen(row, row.getAttribute('aria-expanded') !== 'true'); });
    row.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(row, row.getAttribute('aria-expanded') !== 'true'); } });
  });
  facets.forEach(function (f) { f.addEventListener('change', apply); });
  if (search) { search.addEventListener('input', apply); }
  chrSel.addEventListener('change', apply);
  sortSel.addEventListener('change', sortRows);

  var allOpen = false;
  var toggleBtn = root.querySelector('.gbx-toggleall');
  toggleBtn.addEventListener('click', function () {
    allOpen = !allOpen;
    rows.forEach(function (r) { if (!r.hidden) { setOpen(r, allOpen); } });
    toggleBtn.textContent = allOpen ? 'CLOSE ALL' : 'OPEN ALL';
  });
  root.querySelector('.gbx-reset').addEventListener('click', function () {
    facets.forEach(function (f) { f.checked = false; });
    if (search) { search.value = ''; }
    chrSel.value = ''; sortSel.value = 'sym';
    rows.forEach(function (r) { setOpen(r, false); });
    allOpen = false; toggleBtn.textContent = 'OPEN ALL';
    sortRows(); apply();
  });
  az.addEventListener('click', function (e) {
    var b = e.target.closest('.gbx-azl');
    if (!b || b.classList.contains('gbx-off')) { return; }
    var el = rows.filter(function (r) { return !r.hidden && r.dataset.init === b.dataset.l; })[0];
    if (el) {
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    }
  });

  apply();
})();
</script>
<?php return ob_get_clean();
});
?>
