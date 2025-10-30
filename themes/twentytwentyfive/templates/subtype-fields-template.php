<?php
/**
 * Template Part: Subtype Fields Renderer
 * ------------------------------------------------------------
 * Renders all ACF-driven data for Subtype single pages.
 * Each block is wrapped in a <section> with its own title.
 *
 * Blocks:
 *   1. Subtype Overview
 *   2. Clinical & Genetic Context
 *   3. Key Publication(s) (+ optional Alt Publication)
 *
 * @package ExpertsInCMT
 * @since 1.0
 */

// Exit if accessed directly
defined("ABSPATH") || exit();

/* ============================================================
   # FETCH CORE FIELDS
   ------------------------------------------------------------ */
$subtype = get_the_title();
$acronym = get_field("acronym");

$gene_symbol = get_field("gene_symbol");
if ($gene_symbol === "" || $gene_symbol === null) {
    $gene_symbol = get_field("gene"); // legacy key, if any
}

$full_gene_name = get_field("full_gene_name");
$gene_alias = get_field("gene_alias");
$chromosome = get_field("chromosome");
$zygosity = get_field("zygosity");

$neuropathy = get_the_terms(get_the_ID(), "neuropathy");
$inheritance = get_the_terms(get_the_ID(), "inheritance");

/* Publications — Primary */
$publication_ttl = get_field("publication_title");
$authors = get_field("authors");
$pub_date = get_field("publication_date");
$doi_url = get_field("doi_url");

/* Publications — Alt */
$alt_publication_ttl = get_field("alt_publication_title");
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
$pub_heading = $pub_count === 1 ? "Key Publication" : "Key Publications";
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

    </dl>
  </section>

  <!-- ========================================================
       BLOCK 2: CLINICAL & GENETIC CONTEXT
       ======================================================== -->
  <section class="eic-block eic-block--context">
    <h2 class="eic-block-title">Clinical Genetic Context</h2>
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

    </dl>
  </section>

  <!-- ========================================================
       BLOCK 3: KEY PUBLICATION(S)
       ======================================================== -->
  <section class="eic-block eic-block--publication">
    <h2 class="eic-block-title"><?php echo esc_html($pub_heading); ?></h2>
    <dl class="eic-facts">

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
       BLOCK 4: ALT PUBLICATION(S)
       ======================================================== -->
  <section class="eic-block eic-block--alt-publication">
    <h2 class="eic-block-title"></h2>
    <dl class="eic-facts">

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
      // Normalize Alt DOI/URL
      $alt_doi_display = "";
      $alt_doi_href = "";
      if ($alt_doi_raw) {
          $alt_id = trim($alt_doi_raw);
          if (preg_match('~^https?://(dx\.)?doi\.org/(.+)$~i', $alt_id, $m)) {
              $alt_doi_display = $m[2];
              $alt_doi_href = $m[0];
          } else {
              $alt_doi_display = $alt_id;
              $alt_doi_href = "https://doi.org/" . ltrim($alt_id, "/");
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

</main>
