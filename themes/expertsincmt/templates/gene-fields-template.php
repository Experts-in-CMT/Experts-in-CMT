<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Template Part: Gene Fields Renderer
 * ------------------------------------------------------------
 * A gene page is the Gene Browser row, opened up. A gene post holds
 * no fields; everything here is eic_gene_projection($symbol), the
 * same per-gene resolution the browser performs for a row, rendered
 * with the browser's own components (pills, meta tags, chips,
 * evidence badges, subtype matrix, identifier grid) at page width.
 * Styled by assets/css/gene-page.css under the .gpx root.
 *
 * Audience: scientists and clinicians, like the browser. The
 * subtype page, for patients, is the other register.
 *
 * Sections:
 *   1. Gene Function (UniProt)
 *   2. Relationship to CMT (the row's cells: classification,
 *      inheritance, subtype count, locus, first described, flags,
 *      then the subtype matrix)
 *   3. Stored Identifiers (HGNC aliases, the identifier grid,
 *      then external records and graded evidence)
 *   4. ClinVar Variants (three collapsed tiers: Reported in CMT,
 *      Reported in Other Diseases, Variants w/o a Recorded Disease;
 *      fetched live on first open from eic/v1/clinvar/{gene}; not
 *      not for the structural record)
 */

// Exit if accessed directly
defined("ABSPATH") || exit();

$gene_symbol = trim((string) get_the_title());
$g = function_exists("eic_gene_projection")
    ? eic_gene_projection($gene_symbol)
    : null;

if (!$g) {
    echo '<div class="gpx"><div class="gpx-section"><div class="gbx-exnote">No published subtype record carries the symbol <b>' .
        esc_html($gene_symbol) .
        "</b>.</div></div></div>";
    return;
}

$symbol = $g["symbol"];

// Browser helpers (gene-browser-table.php is loaded by the shortcodes glob).
// Fallbacks keep the page rendering if that file is ever absent.
$family = fn(string $c): string => function_exists("eic_gb_family") ? eic_gb_family($c) : "grey";
$cls_label = fn(string $c): string => function_exists("eic_gb_cls_label") ? eic_gb_cls_label($c) : $c;
$cg_color = fn(string $c): string => function_exists("eic_gb_cg_color") ? eic_gb_cg_color($c) : "grey";
$pa_color = fn(string $r): string => function_exists("eic_gb_pa_color") ? eic_gb_pa_color($r) : "grey";
$dose_label = fn(string $s): string => function_exists("eic_gb_dosage_label")
    ? eic_gb_dosage_label($s)
    : (["1" => "Little evidence", "2" => "Emerging evidence", "3" => "Sufficient evidence"][trim($s)] ?? "");

$chip = function (string $label, string $href = ""): string {
    if ($href !== "") {
        return '<span class="gbx-chip"><a href="' . esc_url($href) . '" target="_blank" rel="noopener">' . esc_html($label) . "</a></span>";
    }
    return '<span class="gbx-chip gbx-chip--mane">' . esc_html($label) . "</span>";
};
$idrow = function (string $k, string $v): string {
    $na = $v === "";
    return '<div class="gbx-idrow"><span class="gbx-k">' . esc_html($k) . '</span><span class="gbx-v' . ($na ? " gbx-na" : "") . '">' . ($na ? "not applicable" : esc_html($v)) . "</span></div>";
};

// Classification pills, as the row renders them.
$pills = "";
foreach ($g["classes"] as $c) {
    $pills .= '<span class="gbx-pill gbx-f-' . esc_attr($family($c)) . '">' . esc_html($cls_label($c)) . "</span>";
}

// External record chips, in the browser's order.
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

// Graded evidence badges, as the browser renders them.
$evidence = [];
if (!$g["structural"]) {
    if ($g["cg_class"] !== "") {
        $col = $cg_color($g["cg_class"]);
        $inner = $g["cg_url"] !== ""
            ? '<a href="' . esc_url($g["cg_url"]) . '" target="_blank" rel="noopener">' . esc_html($g["cg_class"]) . "</a>"
            : esc_html($g["cg_class"]);
        $dis = $g["cg_disease"] !== ""
            ? ' <span class="gbx-ev-dis">' . esc_html($g["cg_disease"]) . "</span>"
            : "";
        $evidence[] = '<span class="gbx-ev gbx-ev-' . esc_attr($col) . '"><span class="gbx-ev-k">ClinGen</span> ' . $inner . $dis . "</span>";
    }
    if ($g["pa_rating"] !== "") {
        $col = $pa_color($g["pa_rating"]);
        $title = $g["pa_votes"] !== ""
            ? ' title="Reviewer ratings green;amber;red: ' . esc_attr($g["pa_votes"]) . '"'
            : "";
        $inner = $g["pa_url"] !== ""
            ? '<a href="' . esc_url($g["pa_url"]) . '" target="_blank" rel="noopener">' . esc_html($g["pa_rating"]) . "</a>"
            : esc_html($g["pa_rating"]);
        $evidence[] = '<span class="gbx-ev gbx-ev-' . esc_attr($col) . '"' . $title . '><span class="gbx-ev-k">PanelApp</span> ' . $inner . "</span>";
    }
    if ($g["cg_hi"] !== "" || $g["cg_ts"] !== "") {
        $dparts = [];
        if ($g["cg_hi"] !== "") {
            $dparts[] = "HI " . esc_html($g["cg_hi"]) . " · " . esc_html($dose_label($g["cg_hi"]));
        }
        if ($g["cg_ts"] !== "") {
            $dparts[] = "TS " . esc_html($g["cg_ts"]) . " · " . esc_html($dose_label($g["cg_ts"]));
        }
        $txt = implode(' <span class="gbx-ev-dis">·</span> ', $dparts);
        $inner = $g["cg_dose_url"] !== ""
            ? '<a href="' . esc_url($g["cg_dose_url"]) . '" target="_blank" rel="noopener">' . $txt . "</a>"
            : $txt;
        $evidence[] = '<span class="gbx-ev gbx-ev-dose"><span class="gbx-ev-k">ClinGen dosage</span> ' . $inner . "</span>";
    }
}
?>

<div class="gpx" id="gene-details">

  <?php if ($g["func"] !== ""): ?>
  <!-- 1. Gene Function -->
  <section class="gpx-section gpx-section--function">
    <h2 class="gpx-h">Gene Function</h2>
    <div class="gbx-func"><?php echo esc_html($g["func"]); ?>
      <span class="gbx-func-src<?php echo $g["structural"] ? " gbx-func-src--cite" : ""; ?>">Source: <?php
      if ($g["structural"]) {
          // A structural record has no UniProt entry; its text is the
          // sentinel publication's abstract (CC BY), credited to the paper
          $sp = $g["subtypes"][0] ?? [];
          $sp_title = (string) ($sp["pub_title"] ?? "");
          $sp_doi = (string) ($sp["doi"] ?? "");
          $sp_authors = (string) ($sp["authors"] ?? "");
          $sp_year = (string) ($sp["year"] ?? "");
          echo "abstract of ";
          echo $sp_doi !== ""
              ? '<a href="' . esc_url($sp_doi) . '" target="_blank" rel="noopener">' . esc_html($sp_title !== "" ? $sp_title : $sp_doi) . "</a>"
              : esc_html($sp_title);
          if ($sp_authors !== "") {
              echo ", " . esc_html($sp_authors);
          }
          if ($sp_year !== "") {
              echo " (" . esc_html($sp_year) . ")";
          }
          echo '. <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>';
      } else {
          echo $g["uniprot"] !== ""
              ? '<a href="' . esc_url("https://www.uniprot.org/uniprotkb/" . $g["uniprot"]) . '" target="_blank" rel="noopener">UniProt</a>'
              : "UniProt";
      }
      ?></span>
    </div>
  </section>
  <?php endif; ?>

  <!-- 2. Relationship to CMT: the row's cells, then its subtype matrix -->
  <section class="gpx-section gpx-section--summary">
    <h2 class="gpx-h">Relationship to CMT</h2>
    <div class="gpx-summary">
      <?php if ($pills !== ""): ?><span class="gbx-cls"><?php echo $pills; ?></span><?php endif; ?>
      <span class="gbx-inh"><?php echo $g["modes"] ? esc_html(implode(", ", $g["modes"])) : '<span class="gbx-none">—</span>'; ?></span>
      <?php if (!$g["cand"]): ?>
        <span class="gbx-sub"><?php echo (int) $g["n"]; ?> subtype<?php echo $g["n"] === 1 ? "" : "s"; ?></span>
      <?php endif; ?>
      <span class="gbx-loc"><?php echo esc_html($g["locus"] !== "" ? $g["locus"] : "n/a"); ?></span>
    </div>
    <div class="gbx-meta" style="margin-top:12px">
      <?php if ($g["structural"]): ?>
        <span class="gbx-mtag">ISCN Notation</span>
      <?php endif; ?>
      <?php if ($g["first_year"] !== ""): ?>
        <span class="gbx-mtag">First described <b><?php echo esc_html($g["first_year"]); ?></b></span>
      <?php endif; ?>
      <?php if ($g["cand"]): ?>
        <span class="gbx-mtag"><b>Candidate gene</b></span>
      <?php endif; ?>
      <?php if ($g["mito"]): ?>
        <span class="gbx-mtag">Mitochondrial involvement</span>
      <?php endif; ?>
      <?php if ($g["ars"]): ?>
        <span class="gbx-mtag">ARS gene</span>
      <?php endif; ?>
    </div>
    <!-- Subtype matrix, as the row's expanded detail carries it -->
    <div style="margin-top:14px">
    <div class="gbx-matrix"><table>
      <thead><tr><th scope="col">Subtype</th><th scope="col">Inheritance</th><th scope="col">Class</th><th scope="col">OMIM</th><th scope="col">Sentinel Publication</th></tr></thead>
      <tbody>
        <?php foreach ($g["subtypes"] as $s): ?>
        <tr>
          <td class="gbx-st" data-l="Subtype"><span class="gpx-dl">Subtype</span><?php echo $s["url"]
              ? '<a href="' . esc_url($s["url"]) . '">' . esc_html($s["code"]) . "</a>"
              : esc_html($s["code"]); ?></td>
          <td data-l="Inheritance"><span class="gpx-dl">Inheritance</span><?php echo (!$g["cand"] && $s["inheritance"] !== "")
              ? esc_html($s["inheritance"])
              : '<span class="gbx-none">' . ($g["cand"] ? "—" : "not recorded") . "</span>"; ?></td>
          <td data-l="Class"><span class="gpx-dl">Class</span><?php echo $s["class"] !== "" ? '<span class="gbx-clsm">' . esc_html($s["class"]) . "</span>" : ""; ?></td>
          <td class="gbx-yr" data-l="OMIM"><span class="gpx-dl">OMIM</span><?php echo $s["omim_subtype"] !== ""
              ? '<a href="' . esc_url("https://omim.org/entry/" . $s["omim_subtype"]) . '" target="_blank" rel="noopener" style="color:var(--gp);text-decoration:none">' . esc_html($s["omim_subtype"]) . "</a>"
              : '<span class="gbx-none">n/a</span>'; ?></td>
          <td class="gbx-pub" data-l="Sentinel Publication"><span class="gpx-dl">Sentinel Publication</span><?php
              $pubs = [];
              if ($s["pub_title"] !== "") {
                  $pubs[] = ["t" => $s["pub_title"], "doi" => $s["doi"], "y" => $s["year"]];
              }
              if ($s["alt_pub_title"] !== "") {
                  $pubs[] = ["t" => $s["alt_pub_title"], "doi" => $s["alt_doi"], "y" => $s["alt_year"]];
              }
              if (!$pubs) {
                  echo '<span class="gbx-none">not recorded</span>';
              }
              foreach ($pubs as $i => $pb) {
                  $meta = array_filter([
                      $pb["y"],
                      $pb["doi"] !== "" ? preg_replace('#^https?://(dx\.)?doi\.org/#', "", $pb["doi"]) : "",
                  ]);
                  echo '<div class="gbx-pub-t"' . ($i > 0 ? ' style="margin-top:.5em"' : "") . ">" .
                      ($pb["doi"] !== ""
                          ? '<a href="' . esc_url($pb["doi"]) . '" target="_blank" rel="noopener">' . esc_html($pb["t"]) . "</a>"
                          : esc_html($pb["t"])) .
                      "</div>";
                  if ($meta) {
                      echo '<div class="gbx-pub-m">' . esc_html(implode(" · ", $meta)) . "</div>";
                  }
              }
          ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    </div>
  </section>

  <?php if (!$g["structural"]): ?>
  <!-- 3. Stored Identifiers: aliases, the identifier grid, then external records and evidence -->
  <section class="gpx-section gpx-section--ids">
    <h2 class="gpx-h">Stored Identifiers</h2>
    <div class="gbx-meta" style="margin-bottom:10px">
      <?php if ($g["alias"] !== ""): ?>
        <span class="gbx-mtag">HGNC Aliases: <?php echo esc_html($g["alias"]); ?></span>
      <?php else: ?>
        <span class="gbx-mtag gbx-null">No HGNC Aliases</span>
      <?php endif; ?>
    </div>
    <div class="gbx-idgrid">
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
    </div>
    <!-- External records + Evidence, as the row's expanded detail carries them -->
    <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--gbd)">
      <div class="gbx-links"><span class="gbx-lbl">External records</span><?php echo $links ? implode("", $links) : '<span class="gbx-none">none recorded</span>'; ?></div>
      <?php if ($evidence): ?>
        <div class="gbx-links"><span class="gbx-lbl">Evidence</span><?php echo implode("", $evidence); ?></div>
      <?php endif; ?>
    </div>
  </section>
  <?php else: ?>
  <section class="gpx-section gpx-section--ids">
    <h2 class="gpx-h">Stored Identifiers</h2>
    <div class="gbx-exnote">Counted here as a gene although its cause is a structural insertion rather than a coding gene. Named by ISCN notation, it has no HGNC record, so gene-level external identifiers do not apply.</div>
  </section>
  <?php endif; ?>

  <?php if (!$g["structural"]): ?>
  <!-- 4. ClinVar Variants: three collapsed tiers, fetched live on first open (eic-clinvar-variants.php) -->
  <section class="gpx-section gpx-section--variants" id="gene-variants"
           data-gene="<?php echo esc_attr($symbol); ?>"
           data-endpoint="<?php echo esc_url(rest_url("eic/v1/clinvar/" . rawurlencode($symbol))); ?>">
    <h2 class="gpx-h">ClinVar Variants</h2>
    <p class="gpx-note">Pathogenic and likely pathogenic variants in <em><?php echo esc_html($symbol); ?></em>, as classified in ClinVar, are read live from NCBI. Only aggregate germline records are shown. Uncertain and conflicting classifications are not. Experts in CMT makes no claim to the accuracy of ClinVar data. This index is provided for informational purposes only. <span class="gpx-cv-status" aria-live="polite"></span><?php echo function_exists("eic_loader") ? eic_loader("gpx-cv-icon", true) : ""; ?></p>
    <p class="gpx-note gpx-note--stars">Review stars are ClinVar's measure of how well a classification is supported: four for a practice guideline, three for an expert panel review, two for agreement among multiple submitters, one for a single submitter with criteria provided, and none where no criteria were provided.</p>

    <details class="gpx-tier" data-tier="A">
      <summary><span class="gpx-tier__name">Reported in CMT</span><span class="gpx-tier__count" data-count="A"></span></summary>
      <div class="gpx-tier__body"></div>
    </details>
    <details class="gpx-tier" data-tier="C">
      <summary><span class="gpx-tier__name">Reported in Other Diseases</span><span class="gpx-tier__count" data-count="C"></span></summary>
      <p class="gpx-tier__why">Reported in a disease other than CMT. Listed apart rather than counted as CMT variants.</p>
      <div class="gpx-tier__body"></div>
    </details>
    <details class="gpx-tier" data-tier="B">
      <summary><span class="gpx-tier__name">Variants w/o a Recorded Disease</span><span class="gpx-tier__count" data-count="B"></span></summary>
      <p class="gpx-tier__why">Pathogenic or likely pathogenic in ClinVar, submitted without a disease recorded.</p>
      <div class="gpx-tier__body"></div>
    </details>
    <p class="gpx-cv-meta" aria-live="polite"></p>
  </section>
  <?php else: ?>
  <!-- 4. ClinVar Variants, structural record: no gene to query, so the card states the absence -->
  <section class="gpx-section gpx-section--variants" id="gene-variants">
    <h2 class="gpx-h">ClinVar Variants</h2>
    <p class="gpx-note">No ClinVar records were found for <em><?php echo esc_html($symbol); ?></em>.</p>
  </section>
  <?php endif; ?>

</div>
