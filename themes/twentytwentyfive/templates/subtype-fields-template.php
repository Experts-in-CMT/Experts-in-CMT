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
$gene_symbol = get_field("gene_symbol");
if ($gene_symbol === "" || $gene_symbol === null) {
    $gene_symbol = get_field("gene"); // legacy key, if any
}
$full_gene_name = get_field("full_gene_name");
$gene_alias = get_field("gene_alias");
$chromosome = get_field("chromosome");
$zygosity = get_field("zygosity");
$clinvar_url = trim((string) get_field("clinvar_url"));
$genereviews_url = trim((string) get_field("genereviews_url"));
$mitochondrial_involvement = get_field("mitochondrial_involvement");
$subtype_alias = get_field("subtype_alias");

/* More Info — CTA buttons */
$research_url = trim((string) get_field("research_url"));
$research_label = trim((string) get_field("research_label"));
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
$pub_heading = $pub_count === 1 ? "Original Discovery Publication" : "Original Discovery Publications";
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
          <dd><?php echo esc_html(
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

<?php if (!empty($clinvar_url)): ?>
  <div class="eic-fact">
    <dt>ClinVar Pathogenic Variants</dt>
    <dd>
      <a class="dr-more"
         href="<?php echo esc_url($clinvar_url); ?>"
         target="_blank"
         rel="noopener noreferrer">
        View ClinVar Variants
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
    !empty($what_is_cmtx_url) ||
    !empty($what_is_intermediate_url);

if ($has_cta): ?>
<section class="eic-block eic-block--cta">
  <h2 class="eic-block-title">More Info</h2>

  <dl class="eic-facts">

    <?php if (!empty($research_url)):
        // Label fallback if custom label is empty


        $btn_text =
            $research_label !== ""
                ? $research_label
                : "View Research Opportunity";
        // Sanitize and strip rogue <br> or HTML
        $btn_text = trim(
            wp_strip_all_tags(preg_replace("/<br\s*\/?>/i", "", $btn_text))
        );
        ?>
      <div class="eic-fact">
        <dt>Research Opportunity</dt>
        <dd>
          <a class="dr-more"
             href="<?php echo esc_url($research_url); ?>"
             target="_blank"
             rel="noopener noreferrer">
            <?php echo esc_html($btn_text); ?>
          </a>
        </dd>
      </div>
    <?php
    endif; ?>

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
