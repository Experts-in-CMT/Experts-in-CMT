<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Template Part: Subtype Fields Renderer
 * ------------------------------------------------------------
 * Renders all ACF-driven data for Subtype single pages.
 * Each block is wrapped in a <section> with its own title.
 *
 * Blocks:
 *   1. Subtype Overview
 *   2. Clinical & Genetic Context
 *   3. More Info (CTAs)
 *   4. Key Publication(s)
 *   5. Optional Alt Publication
 */

// Exit if accessed directly
defined("ABSPATH") || exit();

/* ============================================================
   # FETCH CORE FIELDS
   ------------------------------------------------------------ */
$subtype = get_the_title();
$acronym = get_field("acronym");
$neuropathy = get_the_terms(get_the_ID(), "neuropathy");
$inheritance = get_the_terms(get_the_ID(), "inheritance");

/* Genetic Context*/
$unknown_gene = (bool) get_field("unknown_gene");

$gene_symbol = trim((string) get_field("gene_symbol"));
if ($gene_symbol === "") {
    $gene_symbol = trim((string) get_field("gene")); // legacy key, if any
}

$gene_symbol =
    $unknown_gene || $gene_symbol === ""
        ? "Gene is Unknown at This Time"
        : $gene_symbol;

$full_gene_name = get_field("full_gene_name");
$gene_alias = get_field("gene_alias");
$chromosome = get_field("chromosome");
$zygosity = get_field("zygosity");
$clinvar_url = trim((string) get_field("clinvar_url"));
$clingen_url = trim((string) get_field("clingen_url"));
$genereviews_url = trim((string) get_field("genereviews_url"));
$mitochondrial_involvement = get_field("mitochondrial_involvement");
$mechanism = strtolower(trim((string) get_field("mechanism")));
$subtype_alias = get_field("subtype_alias");
$omim_subtype = trim((string) get_field("omim_subtype"));
$omim_gene = trim((string) get_field("omim_gene"));

/* More Info — CTA buttons */
$research_url = trim((string) get_field("research_url"));
$research_label = trim((string) get_field("research_label"));
$research_url_2 = trim((string) get_field("research_url_2"));
$research_label_2 = trim((string) get_field("research_label_2"));
$research_url_3 = trim((string) get_field("research_url_3"));
$research_label_3 = trim((string) get_field("research_label_3"));
$symptoms_url = trim((string) get_field("symptoms_url"));
$what_is_cmtx_url = trim((string) get_field("what_is_cmtx_url"));
$what_is_intermediate_url = trim(
    (string) get_field("what_is_intermediate_url")
);

/* Publications — Primary */
$publication_ttl = get_field("publication_title");
$publication_note = get_field("publication_note");
$authors = get_field("authors");
$pub_date = get_field("publication_date");
$doi_url = get_field("doi_url");

/* Publications — Alt */
$alt_publication_ttl = get_field("alt_publication_title");
$alt_publication_note = get_field("alt_publication_note");
$alt_authors = get_field("alt_authors");
$alt_date = get_field("alt_date"); // ACF key name is 'alt_date'
$alt_doi_raw = get_field("alt_doi_url");

/* Publication heading pluralization */
$has_primary_pub = (bool) array_filter([
    $publication_ttl,
    $authors,
    $pub_date,
    $doi_url,
]);

$has_alt_pub = (bool) array_filter([
    $alt_publication_ttl,
    $alt_authors,
    $alt_date,
    $alt_doi_raw,
]);

$pub_count = ($has_primary_pub ? 1 : 0) + ($has_alt_pub ? 1 : 0);
$pub_heading =
    $pub_count === 1
        ? "Original Discovery Publication"
        : "Original Discovery Publications";
?>

<main class="eic-subtype-fields" id="subtype-details">

  <!-- ========================================================
       BLOCK 1: SUBTYPE OVERVIEW
       ======================================================== -->
  <section class="eic-block eic-block--overview">
    <h2 class="eic-block-title">Clinical Basics</h2>
    <dl class="eic-facts">

      <?php if ($subtype): ?>
        <div class="eic-fact">
          <dt>Subtype</dt>
          <dd><?php echo esc_html($subtype); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($acronym): ?>
        <div class="eic-fact">
          <dt>Classification</dt>
          <dd><?php echo esc_html($acronym); ?></dd>
        </div>
      <?php endif; ?>
     
      <?php if (!empty($subtype_alias)): ?>
        <div class="eic-fact">
          <dt>Subtype Alias</dt>
          <dd><?php echo esc_html($subtype_alias); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($neuropathy && !is_wp_error($neuropathy)): ?>
        <div class="eic-fact">
          <dt>Neuropathy Type</dt>
          <dd><?php echo esc_html(
              implode(", ", wp_list_pluck($neuropathy, "name"))
          ); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($inheritance && !is_wp_error($inheritance)): ?>
        <div class="eic-fact">
          <dt>Inheritance Pattern</dt>
          <dd class="eic-card-inheritance"><?php echo esc_html(
              implode(", ", wp_list_pluck($inheritance, "name"))
          ); ?></dd>
        </div>
      <?php endif; ?>
      
       <?php if (!empty($symptoms_url)): ?>
      <div class="eic-fact">
        <dt>Symptoms</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($symptoms_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
            <?php echo esc_html($subtype); ?> Symptoms
          </a>
        </dd>
      </div>
    <?php endif; ?>

    </dl>
  </section>

  <!-- ========================================================
       BLOCK 2: GENETIC CONTEXT
       ======================================================== -->
  <section class="eic-block eic-block--context">
    <h2 class="eic-block-title">Genetic Context</h2>
    <dl class="eic-facts">

      <?php if ($gene_symbol): ?>
        <div class="eic-fact">
          <dt>HGNC-Approved Gene Symbol</dt>
          <dd><?php echo esc_html($gene_symbol); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($full_gene_name): ?>
        <div class="eic-fact">
          <dt>Gene Full Name</dt>
          <dd><?php echo esc_html($full_gene_name); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($gene_alias): ?>
        <div class="eic-fact">
          <dt>HGNC Gene Alias(es)</dt>
          <dd><?php echo esc_html($gene_alias); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($chromosome): ?>
        <div class="eic-fact">
          <dt>Chromosome</dt>
          <dd><?php echo esc_html($chromosome); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($zygosity): ?>
        <div class="eic-fact">
          <dt>Zygosity of Responsible Variant</dt>
          <dd><?php echo esc_html($zygosity); ?></dd>
        </div>
      <?php endif; ?>
      <?php if (isset($mitochondrial_involvement)): ?>
  <div class="eic-fact">
    <dt>Mitochondrial Involvement</dt>
    <dd><?php echo $mitochondrial_involvement ? "Yes" : "No"; ?></dd>
  </div>
<?php endif; ?>

      <?php
      // Single curated mechanism call + detail (same fields the Variant
      // Mechanisms table reads).
      $eic_call_labels = [
          "lof" => "Loss of Function (LoF)",
          "dominant_negative" => "Dominant-Negative",
          "gof" => "Toxic Gain of Function (GoF)",
          "complex" => "Complex",
          "unknown" => "Unknown",
      ];
      $eic_flavor_labels = [
          "biallelic" => "Biallelic",
          "haploinsufficiency" => "Haploinsufficiency",
          "dosage" => "Dosage",
          "dominant-negative" => "Dominant-negative",
          "neomorphic" => "Neomorphic",
          "overactivity" => "Overactivity",
          "repeat-expansion" => "Repeat expansion",
          "mixed" => "Mixed",
          "unresolved" => "Unresolved",
          "no-gene" => "Gene unknown",
      ];
      ?>
      <?php if ($mechanism !== "" && isset($eic_call_labels[$mechanism])): ?>
  <?php
  $eic_vm_call = esc_html($eic_call_labels[$mechanism]);
  $eic_vm_flavor = trim((string) get_field("mechanism_flavor"));
  $eic_vm_basis = $eic_flavor_labels[$eic_vm_flavor] ?? "";
  $eic_vm_conf = strtolower(trim((string) get_field("mechanism_confidence")));
  if (!in_array($eic_vm_conf, ["high", "medium", "low"], true)) {
      $eic_vm_conf = "";
  }
  $eic_vm_pred = trim((string) get_field("mechanism_prediction"));
  $eic_vm_rat = trim((string) get_field("mechanism_rationale"));
  $eic_vm_has_detail =
      $eic_vm_basis !== "" ||
      $eic_vm_conf !== "" ||
      $eic_vm_pred !== "" ||
      $eic_vm_rat !== "";
  ?>
  <div class="eic-fact">
    <dt>Variant Mechanism</dt>
    <dd>
      <?php if ($eic_vm_has_detail): ?>
        <p class="eic-mech__call"><?php echo $eic_vm_call; ?></p>
        <details class="eic-mech">
          <summary class="eic-mech__toggle dr-more">Details</summary>
          <div class="eic-mech__body">
            <?php if ($eic_vm_basis !== ""): ?>
              <p class="eic-mech__line eic-mech__basis">
                <span class="eic-mech__label">Mechanistic basis:</span>
                <?php echo esc_html($eic_vm_basis); ?>
              </p>
            <?php endif; ?>
            <?php if ($eic_vm_conf !== ""): ?>
              <p class="eic-mech__line">
                <span class="eic-mech__label">Confidence:</span>
                <span class="eic-mech__conf eic-mech__conf--<?php echo esc_attr(
                    $eic_vm_conf
                ); ?>"><?php echo esc_html(ucfirst($eic_vm_conf)); ?></span>
              </p>
            <?php endif; ?>
            <?php if ($eic_vm_pred !== ""): ?>
              <p class="eic-mech__line eic-mech__pred">
                <span class="eic-mech__label">Prediction:</span>
                <?php echo function_exists("eic_vmech_italicize_genes")
                    ? eic_vmech_italicize_genes(
                        $eic_vm_pred,
                        eic_vmech_gene_symbol_list()
                    )
                    : esc_html($eic_vm_pred); ?>
              </p>
            <?php endif; ?>
            <?php if ($eic_vm_rat !== ""): ?>
              <p class="eic-mech__line eic-mech__rat">
                <span class="eic-mech__label">Rationale:</span>
                <?php echo function_exists("eic_vmech_italicize_genes")
                    ? eic_vmech_italicize_genes(
                        $eic_vm_rat,
                        eic_vmech_gene_symbol_list()
                    )
                    : esc_html($eic_vm_rat); ?>
              </p>
            <?php endif; ?>
          </div>
        </details>
      <?php else: ?>
        <?php echo $eic_vm_call; ?>
      <?php endif; ?>
    </dd>
  </div>
<?php endif; ?>

<?php if (!empty($clinvar_url)): ?>
  <div class="eic-fact">
    <dt>ClinVar Pathogenic Variants</dt>
    <dd>
      <a class="dr-more"
         href="<?php echo esc_url($clinvar_url); ?>"
         target="_blank"
         rel="noopener noreferrer">
        View <?php echo esc_html($subtype); ?> ClinVar Variants
      </a>
    </dd>
  </div>
<?php endif; ?>

<?php if (!empty($clingen_url)): ?>
      <div class="eic-fact">
        <dt>ClinGen Curation</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($clingen_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
          View <?php echo esc_html($gene_symbol); ?> ClinGen Curation
          </a>
        </dd>
      </div>
<?php endif; ?>  

 <?php if (!empty($genereviews_url)): ?>
      <div class="eic-fact">
        <dt>GeneReviews®</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($genereviews_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
            <?php echo esc_html($subtype); ?> GeneReviews®
          </a>
        </dd>
      </div>
<?php endif; ?>

<?php if (!empty($genereviews_url)): ?>
      <div class="eic-fact">
        <dt>GeneReviews®</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($genereviews_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
            <?php echo esc_html($subtype); ?> GeneReviews®
          </a>
        </dd>
      </div>
    <?php endif; ?>

  <?php if (!empty($omim_subtype)): ?>
  <?php $omim_subtype_no_entry =
      (bool) preg_match('/^\s*no[\s\-]?entry\s*$/i', $omim_subtype) ||
      (bool) preg_match('/^\s*none\s*$/i', $omim_subtype); ?>
  <div class="eic-fact">
    <dt><?php echo esc_html($subtype); ?> OMIM Entry</dt>
    <dd>
      <?php if ($omim_subtype_no_entry): ?>
        <a class="dr-more">No Entry</a>
      <?php else: ?>
        <a class="dr-more"
           href="https://omim.org/entry/<?php echo esc_attr($omim_subtype); ?>"
           target="_blank"
           rel="noopener noreferrer">
          <?php echo esc_html($subtype); ?> OMIM
        </a>
      <?php endif; ?>
    </dd>
  </div>
<?php endif; ?>

<?php // A gene OMIM entry is meaningless when the gene is unknown, and the
      // label would read from the "Gene is Unknown at This Time" placeholder.
      // Suppress the whole block in that case; the subtype OMIM entry remains. ?>
<?php if (!empty($omim_gene) && !$unknown_gene): ?>
  <?php $omim_gene_no_entry =
      (bool) preg_match('/^\s*no[\s\-]?entry\s*$/i', $omim_gene) ||
      (bool) preg_match('/^\s*none\s*$/i', $omim_gene); ?>
  <div class="eic-fact">
    <dt><?php echo esc_html($gene_symbol); ?> OMIM Entry</dt>
    <dd>
      <?php if ($omim_gene_no_entry): ?>
        <a class="dr-more">No Entry</a>
      <?php else: ?>
        <a class="dr-more"
           href="https://omim.org/entry/<?php echo esc_attr($omim_gene); ?>"
           target="_blank"
           rel="noopener noreferrer">
          <?php echo esc_html($gene_symbol); ?> OMIM
        </a>
      <?php endif; ?>
    </dd>
  </div>
<?php endif; ?>

    </dl>
  </section>
    </dl>
  </section>

<?php
/* ============================================================
   Box 3: "More Info"
   ============================================================ */
// Gate the section if at least one CTA is available
$has_cta =
    !empty($symptoms_url) ||
    !empty($research_url) ||
    !empty($research_url_2) ||
    !empty($research_url_3) ||
    !empty($what_is_cmtx_url) ||
    !empty($what_is_intermediate_url);

if ($has_cta): ?>
<section class="eic-block eic-block--cta">
  <h2 class="eic-block-title">More Info</h2>
  <dl class="eic-facts">

<?php
$research_count =
    (int) !empty($research_url) +
    (int) !empty($research_url_2) +
    (int) !empty($research_url_3);
$research_first = true;
if ($research_count > 0):
    $research_dt =
        esc_html($subtype) .
        " Research Opportunit" .
        ($research_count === 1 ? "y" : "ies"); ?>
      <?php if (!empty($research_url)):

          $btn_text =
              $research_label !== ""
                  ? $research_label
                  : "View Research Opportunity";
          $btn_text = trim(
              wp_strip_all_tags(preg_replace("/<br\s*\/?>/i", "", $btn_text))
          );
          ?>
        <div class="eic-fact">
          <dt><?php echo $research_dt; ?></dt>
          <dd>
            <a class="dr-more"
               href="<?php echo esc_url($research_url); ?>"
               target="_blank"
               rel="noopener noreferrer">
              <?php echo esc_html($btn_text); ?>
            </a>
          </dd>
        </div>
        <?php $research_first = false; ?>
      <?php
      endif; ?>

      <?php if (!empty($research_url_2)):

          $btn_text_2 =
              $research_label_2 !== ""
                  ? $research_label_2
                  : "View Research Opportunity";
          $btn_text_2 = trim(
              wp_strip_all_tags(preg_replace("/<br\s*\/?>/i", "", $btn_text_2))
          );
          ?>
        <div class="<?php echo $research_first
            ? "eic-fact"
            : "eic-fact eic-fact--no-label"; ?>">
          <?php if (
              $research_first
          ): ?><dt><?php echo $research_dt; ?></dt><?php $research_first = false;endif; ?>
          <dd>
            <a class="dr-more"
               href="<?php echo esc_url($research_url_2); ?>"
               target="_blank"
               rel="noopener noreferrer">
              <?php echo esc_html($btn_text_2); ?>
            </a>
          </dd>
        </div>
      <?php
      endif; ?>

      <?php if (!empty($research_url_3)):

          $btn_text_3 =
              $research_label_3 !== ""
                  ? $research_label_3
                  : "View Research Opportunity";
          $btn_text_3 = trim(
              wp_strip_all_tags(preg_replace("/<br\s*\/?>/i", "", $btn_text_3))
          );
          ?>
        <div class="<?php echo $research_first
            ? "eic-fact"
            : "eic-fact eic-fact--no-label"; ?>">
          <?php if (
              $research_first
          ): ?><dt><?php echo $research_dt; ?></dt><?php $research_first = false;endif; ?>
          <dd>
            <a class="dr-more"
               href="<?php echo esc_url($research_url_3); ?>"
               target="_blank"
               rel="noopener noreferrer">
              <?php echo esc_html($btn_text_3); ?>
            </a>
          </dd>
        </div>
      <?php
      endif; ?>

    <?php
endif;
?>
    <?php if (!empty($what_is_cmtx_url)): ?>
      <div class="eic-fact">
        <dt>CMTX</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($what_is_cmtx_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
            What is CMTX?
          </a>
        </dd>
      </div>
    <?php endif; ?>

    <?php if (!empty($what_is_intermediate_url)): ?>
      <div class="eic-fact">
        <dt>Intermediate CMT</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($what_is_intermediate_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
            What is Intermediate CMT?
          </a>
        </dd>
      </div>
    <?php endif; ?>

  </dl>
</section>
<?php endif;
?>


   <!-- ========================================================
       BLOCK 4: KEY PUBLICATION(S)
       ======================================================== -->
  <section class="eic-block eic-block--publication">
    <h2 class="eic-block-title"><?php echo esc_html($pub_heading); ?></h2>
    <dl class="eic-facts">

    <?php if (!empty(trim(strip_tags($publication_note ?? "")))): ?>
  <div class="eic-fact eic-fact--inline-note">
    <dt class="eic-fact__label-inline">Note:</dt>
    <dd class="eic-fact__value-inline"><?php echo wp_kses_post(
        $publication_note
    ); ?></dd>
  </div>
<?php endif; ?>


      <?php if ($publication_ttl): ?>
        <div class="eic-fact">
          <dt>Publication Title</dt>
          <dd><?php echo wp_kses_post($publication_ttl); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($authors): ?>
        <div class="eic-fact eic-fact--authors">
          <dt>Authors</dt>
          <dd><?php echo wp_kses_post($authors); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($pub_date): ?>
        <div class="eic-fact">
          <dt>Publication Date</dt>
          <dd><?php echo esc_html(
              date_i18n("F j, Y", strtotime($pub_date))
          ); ?></dd>
        </div>
      <?php endif; ?>

      <?php
      // Normalize DOI/URL (primary)
      $doi_display = "";
      $doi_href = "";
      if ($doi_url) {
          $id = trim($doi_url);
          if (preg_match('~^https?://(dx\.)?doi\.org/(.+)$~i', $id, $m)) {
              $doi_display = $m[2]; // show just the DOI id
              $doi_href = $m[0]; // full URL
          } else {
              $doi_display = $id;
              $doi_href = "https://doi.org/" . ltrim($id, "/");
          }
      }

      if ($doi_href) {
          echo '<div class="eic-fact eic-fact--doi"><dt>DOI</dt><dd><a href="' .
              esc_url($doi_href) .
              '" target="_blank" rel="noopener noreferrer" aria-label="Open DOI ' .
              esc_attr($doi_display) .
              '">' .
              esc_html($doi_display) .
              "</a></dd></div>";
      }
      ?>

    </dl>
  </section> <!-- /eic-block--publication -->


  <?php if ($has_alt_pub): ?>
  <!-- ========================================================
       BLOCK 5: ALT PUBLICATION(S)
       ======================================================== -->
  <section class="eic-block eic-block--alt-publication">
    <dl class="eic-facts">

    <?php if (!empty(trim(strip_tags($alt_publication_note ?? "")))): ?>
  <div class="eic-fact eic-fact--inline-note eic-alt-publication-note">
    <dt class="eic-fact__label-inline">Note:</dt>
    <dd class="eic-fact__value-inline"><?php echo wp_kses_post(
        $alt_publication_note
    ); ?></dd>
  </div>
<?php endif; ?>


      <?php if ($alt_publication_ttl): ?>
        <div class="eic-fact">
          <dt>Publication Title</dt>
          <dd><?php echo wp_kses_post($alt_publication_ttl); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($alt_authors): ?>
        <div class="eic-fact eic-fact--authors">
          <dt>Authors</dt>
          <dd><?php echo wp_kses_post($alt_authors); ?></dd>
        </div>
      <?php endif; ?>

      <?php if ($alt_date): ?>
        <div class="eic-fact">
          <dt>Publication Date</dt>
          <dd><?php echo esc_html(
              date_i18n("F j, Y", strtotime($alt_date))
          ); ?></dd>
        </div>
      <?php endif; ?>

      <?php
      // Normalize DOI/URL (alt)
      $alt_doi_display = "";
      $alt_doi_href = "";
      if ($alt_doi_raw) {
          $id = trim($alt_doi_raw);
          if (preg_match('~^https?://(dx\.)?doi\.org/(.+)$~i', $id, $m)) {
              $alt_doi_display = $m[2];
              $alt_doi_href = $m[0];
          } else {
              $alt_doi_display = $id;
              $alt_doi_href = "https://doi.org/" . ltrim($id, "/");
          }
      }

      if ($alt_doi_href) {
          echo '<div class="eic-fact eic-fact--doi"><dt>DOI</dt><dd><a href="' .
              esc_url($alt_doi_href) .
              '" target="_blank" rel="noopener noreferrer" aria-label="Open DOI ' .
              esc_attr($alt_doi_display) .
              '">' .
              esc_html($alt_doi_display) .
              "</a></dd></div>";
      }
      ?>

    </dl>
  </section> <!-- /eic-block--alt-publication -->
  <?php endif; ?>

<?php // Footer metadata: Updated date + curator byline

$updated = get_the_modified_date("F j, Y"); ?>
<p class="eic-updated">Updated: <?php echo esc_html(
    $updated
); ?> | By: K. Raymond</p>


</main>
