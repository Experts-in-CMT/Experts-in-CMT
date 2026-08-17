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
 * MU Plugin: EIC CMT Dataset Export (DLC generator)
 * ------------------------------------------------------------
 * Builds the public, versioned download of the gene-resolved CMT
 * dataset as JSON and CSV. Reads the live subtype store, applies
 * the redistribution license filter, stamps a provenance + license
 * manifest, and writes versioned files to uploads/eic-datasets/.
 *
 * License filter (see claude/external-records-spec.md):
 *   - IDs and URLs pass (an ID inside a hyperlink is shareable).
 *   - ClinGen values (validity, dosage, disease, MONDO) pass — CC0.
 *   - UniProt gene_function passes — CC BY 4.0 (attributed, modified).
 *   - PanelApp rating VALUE is omitted; only panelapp_url ships,
 *     pending an explicit Genomics England reuse license.
 *   - No GeneReviews or OMIM text is emitted (only links / MIM numbers).
 *
 * The published artefact is a pinned file, never a live endpoint, so
 * a cited dataset version is fixed and reproducible. Re-run to cut a
 * new version; the manifest records the source snapshots it was built
 * from (a data version is independent of the theme version).
 *
 * Location: wp-content/mu-plugins/eic-dataset-export.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Dataset_Export
{
    const CAP = "manage_options";
    const NONCE = "eic_dataset_export";
    const NAME = "Experts in CMT: CMT Gene Browser";
    const DEFAULT_VERSION = "1.0.0";
    const SUBDIR = "eic-datasets";
    // Uploaded license text lives in a wp_option (not autoloaded, never in the
    // media library) so the authoritative, reviewed terms ship verbatim in the
    // zip without a repo file to drift. Set via the tool's license upload.
    const LICENSE_OPTION = "eic_dataset_license";

    // Zenodo concept DOI: version-independent, always resolves to the latest
    // deposited version. Safe to bake in (unlike a per-version DOI, which is
    // minted only after a release is cut). Used in the README + license citation.
    const CONCEPT_DOI = "10.5281/zenodo.21970233";

    // EIC's own license line for the dataset. May be revised after legal review.
    const EIC_LICENSE = "\u{00A9} 2026 Experts in CMT. EIC curation licensed under CC BY 4.0 (https://creativecommons.org/licenses/by/4.0/); attribute as \"Experts in CMT, expertsincmt.org\". Applies to EIC's original curation only; third-party content remains copyright the respective owners under their own terms (see the sources block).";

    /** Source provenance + licenses. Update the snapshots when re-cutting. */
    private static function sources(): array
    {
        return [
            "EIC" => [
                "role" => "Gene/subtype curation, classification, inheritance, mechanism, discovery, aliases",
                "license" => self::EIC_LICENSE,
                "url" => "https://www.expertsincmt.org",
            ],
            "HGNC" => [
                "role" => "Approved gene symbol, name, HGNC ID, cytogenetic location",
                "license" => "Free use (genenames.org)",
                "url" => "https://www.genenames.org",
            ],
            "Ensembl" => [
                "role" => "Ensembl gene ID; genomic coordinates (GRCh38, GRCh37)",
                "license" => "Open, no restrictions",
                "url" => "https://www.ensembl.org",
            ],
            "MANE" => [
                "role" => "MANE Select transcript (RefSeq + Ensembl)",
                "license" => "Open, no restrictions",
                "url" => "https://www.ncbi.nlm.nih.gov/refseq/MANE/",
            ],
            "UniProt" => [
                "role" => "Gene function summary",
                "license" => "CC BY 4.0",
                "note" => "Text modified: inline PubMed citations removed.",
                "url" => "https://www.uniprot.org",
            ],
            "ClinGen" => [
                "role" => "Gene-disease validity, dosage sensitivity (HI/TS), disease label, MONDO ID",
                "license" => "CC0 1.0 Public Domain",
                "snapshot" => "2026-08-02",
                "url" => "https://clinicalgenome.org",
            ],
            "MONDO" => [
                "role" => "Disease ontology identifier (MONDO ID)",
                "license" => "CC BY 4.0",
                "url" => "https://mondo.monarchinitiative.org",
            ],
            "PanelApp" => [
                "role" => "Gene page link",
                "license" => "Unstated reuse license — link only",
                "note" => "Green/amber/red rating value omitted from this dataset pending an explicit reuse license from Genomics England.",
                "panel" => "846 — Hereditary neuropathy or pain disorder (v8.30)",
                "url" => "https://panelapp.genomicsengland.co.uk/panels/846/",
            ],
            "OMIM" => [
                "role" => "MIM number cross-references",
                "license" => "\u{00A9} Johns Hopkins University",
                "note" => "MIM numbers as identifiers only; no OMIM text is redistributed.",
                "url" => "https://www.omim.org",
            ],
            "ClinVar" => [
                "role" => "Variant page link",
                "license" => "Public domain (NCBI)",
                "url" => "https://www.ncbi.nlm.nih.gov/clinvar/",
            ],
            "gnomAD" => [
                "role" => "Gene page link (built from the Ensembl gene ID)",
                "license" => "Link only — no frequency or constraint values redistributed",
                "url" => "https://gnomad.broadinstitute.org",
            ],
            "GENESIS / The Genesis Project Foundation" => [
                "role" => "Discovery-support flag + link",
                "url" => "https://www.tgp-foundation.org/d-i-s-c-o-v-e-r-i-e-s",
            ],
        ];
    }

    /** Inheritance string to short mode(s). "A or B" yields each; [] if unknown. */
    private static function inh_modes(string $s): array
    {
        $map = [
            "autosomal dominant" => "AD",
            "autosomal recessive" => "AR",
            "x-linked recessive" => "XLR",
            "x-linked dominant" => "XLD",
            "mitochondrial inheritance" => "Mito",
        ];
        $out = [];
        foreach (preg_split('/\s+or\s+/i', strtolower(trim($s))) as $p) {
            $p = trim($p);
            if (isset($map[$p]) && !in_array($map[$p], $out, true)) {
                $out[] = $map[$p];
            }
        }
        return $out;
    }

    /**
     * Build the gene-resolved dataset (genes with nested subtypes), license
     * filter already applied. One entry per gene_symbol; unknown-gene records
     * are excluded; the structural record (CMTX3) is kept with no identifiers.
     */
    private static function build_dataset(): array
    {
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
            $sym = trim((string) get_field("gene_symbol", $id));
            if ($sym === "") {
                continue;
            }
            $key = strtoupper($sym);

            if (!isset($genes[$key])) {
                $plausible = (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $sym);
                $alias = trim((string) get_field("gene_alias", $id));
                // gnomAD is a derived link, not a stored field: the gene browser
                // builds it from the Ensembl gene ID, and so do we here, so the
                // dataset carries the same gnomAD reference the site displays and
                // the license cites (URL only; no frequency/constraint values).
                $ensembl = trim((string) get_field("ensembl_gene_id", $id));
                $gnomad_url = $ensembl !== ""
                    ? "https://gnomad.broadinstitute.org/gene/" . $ensembl
                    : "";
                $genes[$key] = [
                    "gene_symbol" => $sym,
                    "full_gene_name" => trim((string) get_field("full_gene_name", $id)),
                    "gene_aliases" => $alias !== ""
                        ? array_values(array_filter(array_map("trim", explode(",", $alias))))
                        : [],
                    "locus" => trim((string) get_field("chromosome", $id)),
                    "structural" => !$plausible,
                    "candidate" => false,
                    "mitochondrial_involvement" => false,
                    "genesis_discovery" => false,
                    "inheritance_modes" => [],
                    "classifications" => [],
                    "identifiers" => [
                        "hgnc_id" => trim((string) get_field("hgnc_id", $id)),
                        "ensembl_gene_id" => $ensembl,
                        "entrez_id" => trim((string) get_field("entrez_id", $id)),
                        "omim_gene" => trim((string) get_field("omim_gene", $id)),
                        "uniprot_id" => trim((string) get_field("uniprot_id", $id)),
                        "refseq_accession" => trim((string) get_field("refseq_accession", $id)),
                        "mane_select_refseq" => trim((string) get_field("mane_select_refseq", $id)),
                        "mane_select_ensembl" => trim((string) get_field("mane_select_ensembl", $id)),
                        "coords_grch38" => trim((string) get_field("coords_grch38", $id)),
                        "coords_grch37" => trim((string) get_field("coords_grch37", $id)),
                    ],
                    "external_records" => array_filter([
                        "clingen_url" => trim((string) get_field("clingen_url", $id)),
                        "clinvar_url" => trim((string) get_field("clinvar_url", $id)),
                        "gnomad_url" => $gnomad_url,
                        "panelapp_url" => trim((string) get_field("panelapp_url", $id)),
                        "clingen_validity_url" => trim((string) get_field("clingen_validity_url", $id)),
                        "clingen_dosage_url" => trim((string) get_field("clingen_dosage_url", $id)),
                    ]),
                    // ClinGen (CC0) values.
                    "clingen_validity" => array_filter([
                        "classification" => trim((string) get_field("clingen_classification", $id)),
                        "disease" => trim((string) get_field("clingen_disease", $id)),
                        "mondo" => trim((string) get_field("clingen_mondo", $id)),
                    ]),
                    "clingen_dosage" => array_filter([
                        "haploinsufficiency" => trim((string) get_field("clingen_hi", $id)),
                        "triplosensitivity" => trim((string) get_field("clingen_ts", $id)),
                    ]),
                    // UniProt (CC BY 4.0) value.
                    "gene_function" => trim((string) get_field("gene_function", $id)),
                    // PanelApp rating VALUE intentionally omitted (license filter);
                    // panelapp_url above is the shareable link.
                    "subtypes" => [],
                    "_years" => [],
                ];
            }

            $g = &$genes[$key];
            if (get_field("candidate_gene", $id)) {
                $g["candidate"] = true;
            }
            if (get_field("mitochondrial_involvement", $id)) {
                $g["mitochondrial_involvement"] = true;
            }
            if (get_field("genesis_discovery", $id)) {
                $g["genesis_discovery"] = true;
            }

            $cls = trim((string) get_field("acronym", $id));
            if ($cls === "Unclassified Subtype") {
                $cls = "Unclassified Subtypes";
            }
            if ($cls !== "" && !in_array($cls, $g["classifications"], true)) {
                $g["classifications"][] = $cls;
            }
            $inh = trim((string) get_field("inheritance", $id));
            foreach (self::inh_modes($inh) as $m) {
                if (!in_array($m, $g["inheritance_modes"], true)) {
                    $g["inheritance_modes"][] = $m;
                }
            }
            $year = trim((string) get_field("year_of_discovery", $id));
            if (ctype_digit($year)) {
                $g["_years"][] = (int) $year;
            }

            $g["subtypes"][] = [
                "code" => trim((string) (get_field("subtype", $id) ?: get_the_title($id))),
                "classification" => $cls,
                "inheritance" => $inh,
                "year" => $year,
                "omim_subtype" => trim((string) get_field("omim_subtype", $id)),
                "doi_url" => trim((string) get_field("doi_url", $id)),
                "publication_title" => trim(wp_strip_all_tags((string) get_field("publication_title", $id))),
            ];
            unset($g);
        }
        wp_reset_postdata();

        // Finalize per gene.
        foreach ($genes as &$g) {
            usort($g["subtypes"], function ($a, $b) {
                $ya = $a["year"] !== "" ? (int) $a["year"] : 9999;
                $yb = $b["year"] !== "" ? (int) $b["year"] : 9999;
                return $ya === $yb ? strcmp($a["code"], $b["code"]) : $ya - $yb;
            });
            $g["first_described"] = !empty($g["_years"]) ? (string) min($g["_years"]) : "";
            unset($g["_years"]);
        }
        unset($g);

        // Sort A-Z (structural by its subtype code).
        uasort($genes, function ($a, $b) {
            $ka = $a["structural"] && !empty($a["subtypes"]) ? strtoupper($a["subtypes"][0]["code"]) : strtoupper($a["gene_symbol"]);
            $kb = $b["structural"] && !empty($b["subtypes"]) ? strtoupper($b["subtypes"][0]["code"]) : strtoupper($b["gene_symbol"]);
            return strcmp($ka, $kb);
        });

        return array_values($genes);
    }

    private static function manifest(string $version, array $genes): array
    {
        $subs = 0;
        $cand = 0;
        foreach ($genes as $g) {
            $subs += count($g["subtypes"]);
            if ($g["candidate"]) {
                $cand++;
            }
        }
        return [
            "dataset" => self::NAME,
            "version" => $version,
            "build_date" => current_time("Y-m-d"),
            "counts" => [
                "genes" => count($genes),
                "subtypes" => $subs,
                "candidate_genes" => $cand,
            ],
            "license" => [
                "eic_content" => self::EIC_LICENSE,
                "third_party" => "Third-party content is copyright the respective owners; see the sources block. This dataset carries identifiers and hyperlinks to third-party resources plus values only where the source license permits redistribution (ClinGen CC0; UniProt CC BY 4.0).",
            ],
            "sources" => self::sources(),
        ];
    }

    /** Subtype-level CSV (one row per subtype; gene fields repeat). */
    private static function to_csv(array $genes): string
    {
        $cols = [
            "gene_symbol", "full_gene_name", "gene_aliases", "candidate", "structural",
            "locus", "inheritance_modes", "classifications", "first_described",
            "hgnc_id", "ensembl_gene_id", "entrez_id", "omim_gene", "uniprot_id",
            "refseq_accession", "mane_select_refseq", "mane_select_ensembl",
            "coords_grch38", "coords_grch37",
            "clingen_url", "clinvar_url", "gnomad_url", "panelapp_url",
            "clingen_validity_url", "clingen_classification", "clingen_disease", "clingen_mondo",
            "clingen_dosage_url", "clingen_hi", "clingen_ts",
            "genesis_discovery", "gene_function",
            "subtype_code", "subtype_classification", "subtype_inheritance", "subtype_year",
            "subtype_omim", "subtype_doi", "subtype_publication_title",
        ];
        $fh = fopen("php://temp", "r+");
        fputcsv($fh, $cols);
        foreach ($genes as $g) {
            $id = $g["identifiers"];
            $ext = $g["external_records"];
            $cv = $g["clingen_validity"];
            $cd = $g["clingen_dosage"];
            $base = [
                $g["gene_symbol"],
                $g["full_gene_name"],
                implode("; ", $g["gene_aliases"]),
                $g["candidate"] ? "1" : "0",
                $g["structural"] ? "1" : "0",
                $g["locus"],
                implode("|", $g["inheritance_modes"]),
                implode("|", $g["classifications"]),
                $g["first_described"],
                $id["hgnc_id"], $id["ensembl_gene_id"], $id["entrez_id"], $id["omim_gene"],
                $id["uniprot_id"], $id["refseq_accession"], $id["mane_select_refseq"],
                $id["mane_select_ensembl"], $id["coords_grch38"], $id["coords_grch37"],
                $ext["clingen_url"] ?? "", $ext["clinvar_url"] ?? "", $ext["gnomad_url"] ?? "",
                $ext["panelapp_url"] ?? "", $ext["clingen_validity_url"] ?? "",
                $cv["classification"] ?? "", $cv["disease"] ?? "", $cv["mondo"] ?? "",
                $ext["clingen_dosage_url"] ?? "", $cd["haploinsufficiency"] ?? "", $cd["triplosensitivity"] ?? "",
                $g["genesis_discovery"] ? "1" : "0",
                $g["gene_function"],
            ];
            if (empty($g["subtypes"])) {
                fputcsv($fh, array_map([__CLASS__, "csv_cell"], array_merge($base, ["", "", "", "", "", "", ""])));
                continue;
            }
            foreach ($g["subtypes"] as $s) {
                fputcsv($fh, array_map([__CLASS__, "csv_cell"], array_merge($base, [
                    $s["code"], $s["classification"], $s["inheritance"], $s["year"],
                    $s["omim_subtype"], $s["doi_url"], $s["publication_title"],
                ])));
            }
        }
        rewind($fh);
        // Prepend a UTF-8 BOM so Excel on Windows decodes multibyte gene/function
        // text correctly (the JSON sibling uses JSON_UNESCAPED_UNICODE).
        $csv = "\xEF\xBB\xBF" . stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }

    /**
     * Neutralize spreadsheet formula injection: a cell whose first character is
     * =, +, -, @, TAB or CR is evaluated as a formula by Excel/Sheets. This CSV
     * is a public download, so prefix such cells with an apostrophe to force
     * literal text. fputcsv handles comma/quote/newline quoting on its own.
     */
    private static function csv_cell($v): string
    {
        $v = (string) $v;
        if ($v !== "" && preg_match('/^[=+\-@\t\r]/', $v)) {
            $v = "'" . $v;
        }
        return $v;
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "CMT Dataset Export",
            "CMT Dataset Export",
            self::CAP,
            "eic-dataset-export",
            [__CLASS__, "render"]
        );
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        eic_admin_tool_open("CMT Dataset Export");
        echo "<p>Builds the public, versioned download of the gene-resolved CMT dataset " .
            "(JSON + CSV + README + license, zipped) from the live store. Only license-named " .
            "sources ship: IDs and URLs pass; ClinGen (CC0) and UniProt (CC BY 4.0) values " .
            "pass; the PanelApp rating value is withheld (link only); GeneReviews and Orphanet " .
            "links are not included; no OMIM text is emitted. Each build stamps a provenance + " .
            "license manifest and replaces the prior build. Upload the authoritative license " .
            "below before a public release.</p>";

        $action = $_POST["eic_action"] ?? "";
        $version = isset($_POST["version"])
            ? preg_replace('/[^0-9A-Za-z._-]/', "", (string) $_POST["version"])
            : self::DEFAULT_VERSION;
        if ($version === "") {
            $version = self::DEFAULT_VERSION;
        }

        if ($action && check_admin_referer(self::NONCE)) {
            if ($action === "upload_license") {
                self::handle_license_upload();
            } else {
                $genes = self::build_dataset();
                if ($action === "build") {
                    self::build($version, $genes);
                } else {
                    self::preview($genes);
                }
            }
        }

        self::render_current_package();

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p>Version: <input type="text" name="version" value="' . esc_attr($version) .
            '" style="width:120px" pattern="[0-9A-Za-z._-]+"></p>';
        echo '<p><button class="button button-primary" name="eic_action" value="preview">Preview (no file written)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the preview and want to write the files.</label></p>';
        echo '<p><button class="button button-primary" name="eic_action" value="build">Build v' . esc_html($version) . '</button></p>';
        echo "</form>";

        self::render_license_panel();
        eic_admin_tool_close();
    }

    private static function preview(array $genes): void
    {
        echo "<h2>Preview — " . count($genes) . " genes</h2>";
        self::metrics($genes);
    }

    /**
     * Newest built package on disk (matches the front-end download button's
     * resolution: newest eic-cmt-genes-v*.zip by mtime). [] when none built.
     */
    private static function current_package(): array
    {
        $up = wp_upload_dir();
        $dir = trailingslashit($up["basedir"]) . self::SUBDIR;
        $zips = glob(trailingslashit($dir) . "eic-cmt-genes-v*.zip");
        if (empty($zips)) {
            return [];
        }
        usort($zips, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $zips[0];
        $file = basename($latest);
        return [
            "file"  => $file,
            "url"   => trailingslashit($up["baseurl"]) . self::SUBDIR . "/" . rawurlencode($file),
            "size"  => size_format((int) filesize($latest), 1),
            "built" => date_i18n(
                get_option("date_format") . " " . get_option("time_format"),
                (int) filemtime($latest)
            ),
            "ver"   => preg_match('/v([0-9A-Za-z._-]+)\.zip$/', $file, $m) ? $m[1] : "",
        ];
    }

    /**
     * Persistent "download the current package" control. Always available (no
     * rebuild needed), so the published zip can be pulled for a Zenodo deposit /
     * DOI at any time. This is the same file the front-end button serves.
     */
    private static function render_current_package(): void
    {
        $p = self::current_package();
        echo "<hr><h2>Current package</h2>";
        if (empty($p)) {
            echo "<p><em>No package built yet. Build one below to produce the downloadable zip.</em></p>";
            return;
        }
        echo "<p>The published package the front-end download button serves. Download it here to " .
            "deposit at Zenodo (or elsewhere) for DOI minting.</p>";
        echo '<p><a class="button button-primary button-hero" href="' . esc_url($p["url"]) .
            '" download="' . esc_attr($p["file"]) . '">Download package — v' . esc_html($p["ver"]) .
            " (" . esc_html($p["size"]) . ")</a></p>";
        echo '<p style="color:#6b7480"><small><code>' . esc_html($p["file"]) . "</code> · built " .
            esc_html($p["built"]) . "</small></p>";
    }

    private static function build(string $version, array $genes): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. No files written.</p></div>';
            self::preview($genes);
            return;
        }
        $manifest = self::manifest($version, $genes);
        $json = wp_json_encode(
            array_merge($manifest, ["genes" => $genes]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $csv = self::to_csv($genes);

        $up = wp_upload_dir();
        $dir = trailingslashit($up["basedir"]) . self::SUBDIR;
        wp_mkdir_p($dir);
        $base = "eic-cmt-genes-v" . $version;
        $ok_json = file_put_contents(trailingslashit($dir) . $base . ".json", $json) !== false;
        $ok_csv = file_put_contents(trailingslashit($dir) . $base . ".csv", $csv) !== false;

        // Bundle the distributable zip (JSON + CSV + README + LICENSE) so the
        // license and citation travel with the data. This is the file the public
        // download button links to (see the eic_dataset_download shortcode).
        $ok_zip = false;
        if ($ok_json && $ok_csv && class_exists("ZipArchive")) {
            $zip_path = trailingslashit($dir) . $base . ".zip";
            @unlink($zip_path);
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile(trailingslashit($dir) . $base . ".json", $base . ".json");
                $zip->addFile(trailingslashit($dir) . $base . ".csv", $base . ".csv");
                $zip->addFromString("README.txt", self::readme($version, $manifest));
                $zip->addFromString("LICENSE.txt", self::license_text_versioned($version));
                $zip->close();
                $ok_zip = is_file($zip_path);
            }
        }

        // Replace prior builds: keep only the version just written so the
        // download button (newest zip) always resolves to this one and stale
        // versions do not accumulate on disk.
        if ($ok_json && $ok_csv) {
            foreach ((array) glob(trailingslashit($dir) . "eic-cmt-genes-v*") as $old) {
                if (strpos(basename($old), $base) !== 0) {
                    @unlink($old);
                }
            }
        }

        if ($ok_json && $ok_csv) {
            $url = trailingslashit($up["baseurl"]) . self::SUBDIR . "/";
            $links =
                '<a href="' . esc_url($url . $base . ".json") . '" download="' . esc_attr($base . ".json") . '">' . esc_html($base) . ".json</a> &nbsp;·&nbsp; " .
                '<a href="' . esc_url($url . $base . ".csv") . '" download="' . esc_attr($base . ".csv") . '">' . esc_html($base) . ".csv</a>";
            if ($ok_zip) {
                $links .= ' &nbsp;·&nbsp; <a href="' . esc_url($url . $base . ".zip") . '" download="' . esc_attr($base . ".zip") . '"><strong>' . esc_html($base) . ".zip</strong></a>";
            }
            echo '<div class="notice notice-success"><p><strong>Built v' . esc_html($version) . "." .
                ($ok_zip ? "" : " (zip not written — ZipArchive unavailable)") . '</strong></p><p>' . $links . "</p></div>";
        } else {
            echo '<div class="notice notice-error"><p>Failed to write one or more files to <code>' .
                esc_html($dir) . "</code>. Check filesystem permissions.</p></div>";
        }
        self::metrics($genes);
    }

    private static function metrics(array $genes): void
    {
        $total = count($genes);
        $subs = 0;
        $cand = 0;
        foreach ($genes as $g) {
            $subs += count($g["subtypes"]);
            if ($g["candidate"]) {
                $cand++;
            }
        }
        $count = function (callable $f) use ($genes) {
            $n = 0;
            foreach ($genes as $g) {
                if ($f($g)) {
                    $n++;
                }
            }
            return $n;
        };
        $rows = [
            ["Full name", $count(fn($g) => $g["full_gene_name"] !== "")],
            ["HGNC ID", $count(fn($g) => $g["identifiers"]["hgnc_id"] !== "")],
            ["Ensembl gene", $count(fn($g) => $g["identifiers"]["ensembl_gene_id"] !== "")],
            ["Coordinates GRCh38", $count(fn($g) => $g["identifiers"]["coords_grch38"] !== "")],
            ["Coordinates GRCh37", $count(fn($g) => $g["identifiers"]["coords_grch37"] !== "")],
            ["OMIM gene number", $count(fn($g) => $g["identifiers"]["omim_gene"] !== "")],
            ["UniProt function (CC BY)", $count(fn($g) => $g["gene_function"] !== "")],
            ["ClinGen validity (CC0)", $count(fn($g) => !empty($g["clingen_validity"]["classification"]))],
            ["ClinGen dosage (CC0)", $count(fn($g) => !empty($g["clingen_dosage"]))],
            ["ClinVar link", $count(fn($g) => !empty($g["external_records"]["clinvar_url"]))],
            ["gnomAD link", $count(fn($g) => !empty($g["external_records"]["gnomad_url"]))],
            ["PanelApp link (value withheld)", $count(fn($g) => !empty($g["external_records"]["panelapp_url"]))],
            ["GENESIS discovery", $count(fn($g) => $g["genesis_discovery"])],
        ];

        echo '<h3 style="margin-top:1.5em">Coverage metrics</h3>';
        echo '<table class="widefat striped" style="max-width:560px"><tbody>';
        echo "<tr><td><strong>Genes</strong></td><td><strong>" . (int) $total . "</strong> · " .
            (int) $cand . " candidate, " . ($total - $cand) . " confirmed</td></tr>";
        echo "<tr><td><strong>Subtypes (dataset rows)</strong></td><td>" . (int) $subs . "</td></tr>";
        foreach ($rows as $r) {
            echo "<tr><td>" . esc_html($r[0]) . "</td><td>" . (int) $r[1] . " of " . (int) $total . " genes</td></tr>";
        }
        echo "</tbody></table>";
        echo '<p style="color:#6b7480"><em>License filter applied: only license-named sources ship; ' .
            "PanelApp rating value withheld (link only); GeneReviews and Orphanet links dropped; " .
            "no OMIM text emitted.</em></p>";
    }

    /** Plain-text README bundled in the download zip; derived from the manifest. */
    private static function readme(string $version, array $manifest): string
    {
        $c = $manifest["counts"];
        $year = substr((string) $manifest["build_date"], 0, 4);
        $L = [];
        $L[] = $manifest["dataset"];
        $L[] = str_repeat("=", strlen($manifest["dataset"]));
        $L[] = "";
        $L[] = "Version:     " . $version;
        $L[] = "Build date:  " . $manifest["build_date"];
        $L[] = "Contents:    " . $c["genes"] . " genes, " . $c["subtypes"] . " subtype rows (" . $c["candidate_genes"] . " candidate).";
        $L[] = "";
        $L[] = "Files in this package:";
        $L[] = "  eic-cmt-genes-v" . $version . ".json   Gene-resolved dataset (genes with nested subtypes) plus a provenance and license manifest.";
        $L[] = "  eic-cmt-genes-v" . $version . ".csv    Subtype-level table (one row per subtype; gene fields repeat).";
        $L[] = "  LICENSE.txt                              License terms.";
        $L[] = "  README.txt                               This file.";
        $L[] = "";
        $L[] = "Cite as:";
        $L[] = "  Experts in CMT. (" . $year . "). " . $manifest["dataset"] . " [Data set]. Version " . $version . ".";
        $L[] = "  Zenodo. https://doi.org/" . self::CONCEPT_DOI . ". Licensed under CC BY 4.0.";
        $L[] = "  (The DOI is the concept DOI, which always resolves to the latest version.)";
        $L[] = "";
        $L[] = "License:";
        $L[] = "  EIC curation is licensed under CC BY 4.0 (https://creativecommons.org/licenses/by/4.0/).";
        $L[] = "  Third-party content is copyright the respective owners under their own terms; see";
        $L[] = "  LICENSE.txt and the sources block in the JSON manifest for full terms.";
        $L[] = "";
        return implode("\r\n", $L);
    }

    /**
     * Store an uploaded license .txt into the wp_option. The stored copy is the
     * authoritative text that ships in every subsequent build's LICENSE.txt.
     * Line endings are normalized to \n on store; license_text() re-applies CRLF.
     */
    private static function handle_license_upload(): void
    {
        $f = $_FILES["license_file"] ?? null;
        if (!is_array($f) || !isset($f["tmp_name"])) {
            echo '<div class="notice notice-error"><p>No file received.</p></div>';
            return;
        }
        if (!empty($f["error"])) {
            echo '<div class="notice notice-error"><p>Upload failed (error code ' .
                (int) $f["error"] . ").</p></div>";
            return;
        }
        if (!is_uploaded_file($f["tmp_name"])) {
            echo '<div class="notice notice-error"><p>Invalid upload.</p></div>';
            return;
        }
        $txt = file_get_contents($f["tmp_name"]);
        if ($txt === false || trim($txt) === "") {
            echo '<div class="notice notice-error"><p>The file was empty.</p></div>';
            return;
        }
        // Reject anything that is not plain text (e.g. a stray binary/zip).
        if (strpos($txt, "\0") !== false) {
            echo '<div class="notice notice-error"><p>That does not look like a plain-text license.</p></div>';
            return;
        }
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);
        update_option(self::LICENSE_OPTION, $txt, false);
        echo '<div class="notice notice-success"><p><strong>License stored</strong> (' .
            number_format(strlen($txt)) . " bytes). It will ship in the next build.</p></div>";
    }

    /** Upload form + current-stored-license status, printed under the build form. */
    private static function render_license_panel(): void
    {
        $stored = get_option(self::LICENSE_OPTION, "");
        echo "<hr><h2>Dataset license</h2>";
        echo '<p style="max-width:640px">The license text below is what ships as ' .
            "<code>LICENSE.txt</code> inside every build. Upload the authoritative, " .
            "reviewed <code>.txt</code>; it is stored in a site option (not the media " .
            "library) and travels verbatim with the data.</p>";

        if (is_string($stored) && trim($stored) !== "") {
            $first = trim((string) strtok($stored, "\n"));
            echo '<p style="color:#1a7f37"><strong>Stored:</strong> ' .
                number_format(strlen($stored)) . " bytes · first line: <code>" .
                esc_html($first) . "</code></p>";
        } else {
            $file = dirname(__FILE__) . "/eic-dataset-license.txt";
            $fallback = is_readable($file) ? "the beside-plugin file" : "the built-in summary";
            echo '<p style="color:#b26a00"><em>No license uploaded yet — builds will ' .
                "fall back to " . esc_html($fallback) . ".</em></p>";
        }

        echo '<form method="post" enctype="multipart/form-data" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="eic_action" value="upload_license">';
        echo '<p><input type="file" name="license_file" accept=".txt,text/plain" required> ';
        echo '<button class="button">Upload license</button></p>';
        echo "</form>";
    }

    /**
     * License text bundled as LICENSE.txt. Prefers the uploaded copy stored in
     * the wp_option (the authoritative, reviewed terms), then an
     * eic-dataset-license.txt placed next to this plugin, then the EIC_LICENSE
     * summary as a last resort. Ships with CRLF for cross-platform readers.
     */
    private static function license_text(): string
    {
        $stored = get_option(self::LICENSE_OPTION, "");
        if (is_string($stored) && trim($stored) !== "") {
            return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $stored));
        }
        $file = dirname(__FILE__) . "/eic-dataset-license.txt";
        if (is_readable($file)) {
            $txt = file_get_contents($file);
            if ($txt !== false && trim($txt) !== "") {
                return $txt;
            }
        }
        return self::EIC_LICENSE . "\r\n\r\n" .
            "Full CC BY 4.0 terms: https://creativecommons.org/licenses/by/4.0/\r\n\r\n" .
            "NOTE: upload the authoritative license in the CMT Dataset Export tool " .
            "so the full, reviewed terms ship verbatim.";
    }

    /**
     * License text with the dataset version stamped into the ATTRIBUTION data
     * citation ("[Data set]. Version X.Y.Z."), so the bundled LICENSE.txt is
     * self-identifying to this exact cut. Injected at build time from the
     * verbatim master (the stored option never itself carries a version), so it
     * can never drift from the version being built. If the citation marker is
     * absent the text ships unchanged.
     */
    private static function license_text_versioned(string $version): string
    {
        $license = self::license_text();
        if (strpos($license, "[Data set].") !== false) {
            $license = preg_replace(
                '/\[Data set\]\./',
                "[Data set]. Version " . $version . ".",
                $license,
                1
            );
        }
        return $license;
    }
}

EIC_Dataset_Export::init();
