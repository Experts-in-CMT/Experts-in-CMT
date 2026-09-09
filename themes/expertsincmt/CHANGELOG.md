> **© 2025-2026 Kenneth Raymond — All rights reserved.**  
> Part of the Experts in CMT WordPress theme.  
> Do not copy, modify, or redistribute without permission.

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Gene post type (`inc/cpt/gene-cpt.php`, `mu-plugins/eic-gene-posts-tool.php`, `functions.php`)**
  - `gene` post type at `/genetics/gene/{symbol}/`. A gene post is a shell (title = HGNC symbol, slug = symbol lowercased, no fields); page content is projected at render from the published subtype records matching `gene_symbol` via `eic_gene_projection()`, the Gene Browser's per-gene resolution.
  - Tools > Gene Posts creates missing posts from published subtypes (dry run, commit, insert-only, re-runnable). Saving a published subtype creates its gene post if absent. Symbol lookups load in one query per request.
  - The structural record (CMTX3) gets a gene post titled and slugged by subtype code, resolvable by code or ISCN string.
  - inc/cpt/gene-cpt.php
  - mu-plugins/eic-gene-posts-tool.php
  - functions.php

- **Gene page (`single-gene.html`, `gene-fields-shortcode.php`, `gene-fields-template.php`, `gene-jsonld.php`, `gene-page.css`, `gene-banner.css`)**
  - Block template carrying `[gene_fields]`, rendered in the Gene Browser's register: Gene Function (UniProt summary), Relationship to CMT (row cells plus the subtype matrix headed Sentinel Publication, each code linked), Stored Identifiers (aliases, identifier grid, External Records chips, Evidence badges). Same components and class names as the browser's expanded detail under a `.gpx` root; site typography.
  - Header Banner group attached to `gene`; `banner_title` and `banner_intro` filled when empty by the tool and the publish hook. `gene-banner.css` lets the intro wrap. Tools > Header Banner Image lists Gene.
  - `[context_nav]` gains a `gene` entry (Previous, Next, Return to the Gene Browser, A to Z by symbol).
  - JSON-LD: schema.org `Gene` (identifiers as PropertyValue, external records as sameAs, subtypes as associatedDisease) and `MedicalWebPage` via Yoast @ids.
  - For the structural record: Gene Function carries the sentinel paper's abstract with citation and CC BY 4.0 credit; Stored Identifiers states gene-level identifiers do not apply.
  - templates/single-gene.html
  - templates/gene-fields-template.php
  - inc/shortcodes/gene-fields-shortcode.php
  - inc/acf/gene-jsonld.php
  - inc/acf/header-banner-fields.php
  - inc/shortcodes/context-nav-shortcode.php
  - assets/css/gene-page.css
  - assets/css/gene-banner.css
  - mu-plugins/eic-banner-image-tool.php

- **EIC Loader (`inc/shortcodes/eic-loader.php`, `assets/css/eic-loader.css`)**
  - Platform loading indicator: the DNA helix as inline SVG in the current text color, stroke-dash draw in/out loop, still under reduced motion. `eic_loader($class, $hidden)`, `[eic_loader]`, `.eic-loader`.
  - inc/shortcodes/eic-loader.php
  - assets/css/eic-loader.css

- **ClinVar Variants card on the gene page (`mu-plugins/eic-clinvar-variants.php`, `gene-fields-template.php`, `gene-page.js`, `gene-page.css`)**
  - REST route `GET eic/v1/clinvar/{gene}` fetches the gene's pathogenic and likely pathogenic ClinVar variants (aggregate germline classification only) via E-utilities, summaries in batches of 40. Cache: transient per gene keyed on ClinVar's release stamp, holding parsed records only; tiers and classification applied on read; a cached record missing a field the parser now writes is refetched. One fetch per gene at a time, per-visitor budget on uncached fetches, diagnostics off on production.
  - Three collapsed tiers, split by reported disease: Reported in CMT, Reported in Other Diseases, Variants w/o a Recorded Disease (no disease named, at every gene). A variant reported in both CMT and another disease appears in both tiers, each showing only its own reports. CMT matched by MedGen CUI seed plus classification name patterns; "intermediate" counts only beside dominant or recessive. Legacy names (Dejerine-Sottas, CMT3, Roussy-Levy) render as a dashed "legacy name" chip. "GENE-related disorder" traits read as no disease recorded.
  - Every gene page carries the card, candidates included; the structural record's card states no records were found.
  - Card note, review-stars explainer, counts and totals line loaded on page load; each row: ClinVar link, type, classification, stars, reported diseases, last evaluated. HGVS names break at seams; year-1 placeholder dates blanked. `assets/js/gene-page.js` enqueued on single gene pages only.
  - mu-plugins/eic-clinvar-variants.php
  - templates/gene-fields-template.php
  - assets/js/gene-page.js
  - assets/css/gene-page.css
  - functions.php

- **ClinVar Dataset in the gene page schema (`inc/acf/gene-jsonld.php`, `mu-plugins/eic-clinvar-variants.php`)**
  - schema.org `Dataset` for the card: NCBI creator, ClinVar catalog, release stamp as version, P/LP and Reported in CMT counts, gene as subject. `Gene` carries an `@id` and `subjectOf`; `MedicalWebPage` main entity references it.
  - Reported in CMT variants emitted as `BioChemEntity` (SO:0001060) with VCV and rsID identifiers, HGVS representations, classification, review status, type, and CMT diseases with MedGen codes. Capped at 150 per gene (`eic_gene_schema_variant_cap`).
  - Read cache-only via `EIC_ClinVar_Variants::cached()`; no NCBI call at render. The structural record carries no Dataset.
  - inc/acf/gene-jsonld.php
  - mu-plugins/eic-clinvar-variants.php

- **Variant search (`inc/search/platform-search-variants.php`, `mu-plugins/eic-variant-index.php`)**
  - Resolver for variant queries (`t424m`, `Thr424Met`, `p.Thr424Met`, `HSPB3-P121L`, `itpr3 c.1271C>T`, `rs104894520`, `VCV000041229`): returns the variant (HGVS protein name with one-letter form, cDNA, classification, stars, tier), the gene, its subtypes, and content. Links to the gene page row (`?v=VCV…#gene-variants`, row opened and highlighted) and to ClinVar. Unmatched variant at a known gene returns the gene and subtypes with a miss line. Log intent `variant`.
  - One grammar and amino-acid map: all keys one-letter form; three-letter, `p.`, case, parentheses, frameshift, deletion, duplication, synonymous notation carried through. A gene symbol always wins over the grammar. Resolver reads the raw query; search cache key carries the typed form.
  - Site-wide index table `wp_eic_variant_index`, one row per key per record, derived from the card payload. Weekly WP-Cron roll worked a few genes per tick; self-heals on `eic_clinvar_gene_built`; in-memory fallback from the cached card payload. Tools > Variant Index: counts, queue, schedule, last-roll errors, Rebuild Now.
  - Did you mean: same residues and suffix, position one digit off, dropped, added, or wrong; within the gene when resolved, else site-wide; up to five, Reported in CMT first then by stars. Log intent `variant (did you mean)`.
  - inc/search/platform-search-variants.php
  - inc/search/platform-search.php
  - inc/search/platform-search-variables.php
  - inc/search/platform-search-render.php
  - inc/search/platform-search.css
  - inc/shortcodes/platform-search-results.php
  - assets/js/gene-page.js
  - assets/css/gene-page.css
  - mu-plugins/eic-variant-index.php
  - mu-plugins/eic-clinvar-variants.php

### Changed

- **Subtype post type registered in code; subtype URLs moved (`inc/cpt/subtype-cpt.php`, `functions.php`)**
  - `subtype` registered in PHP, transcribed value for value from the ACF post type, with rewrite slug `genetics/subtype` and `has_archive` false. Key, labels, supports, REST, menu, capabilities, hierarchy, and query var unchanged.
  - Subtype pages now at `/genetics/subtype/{slug}/`. All internal links read the permalink. Old `/subtype/{slug}/` URLs redirect via Yoast.
  - inc/cpt/subtype-cpt.php
  - functions.php

- **Search gene pills link to the gene post (`platform-search.php`, `platform-search-variants.php`)**
  - Gene pills and gene "did you mean" links resolve through `eic_ps_gene_url()` to `/genetics/gene/{symbol}/`, Gene Browser filter as fallback.
  - inc/search/platform-search.php
  - inc/search/platform-search-variants.php

- **Browsers link identifiers and name their calls to action (`gene-browser-table.php`, `variant-mechanism-table.php`, `variant-mechanism.css`)**
  - Gene Browser: symbol cell links to the gene page in every row (label CMTX3 for the structural record; structural Gene Function source as a "Source" pill); expanded detail ends with "Learn more about GENE →". Clicking the symbol navigates rather than toggling the row.
  - Variant Mechanism browser: gene links to the gene page, subtype code to the subtype page.
  - All such links carry an aria-label naming the destination ("MPZ gene page", "CMT1B subtype page").
  - inc/shortcodes/gene-browser-table.php
  - inc/shortcodes/variant-mechanism-table.php
  - assets/css/variant-mechanism.css

- **Subtype page links into the gene stack (`subtype-fields-template.php`)**
  - Gene symbol links to the gene page (aria-label "SYMBOL gene page"). ClinVar Pathogenic Variants button goes to the gene page's `#gene-variants` card; ClinVar search kept for a symbol without a page. `clinvar_url` field untouched.
  - templates/subtype-fields-template.php

- **Gene page navigation (`context-nav-shortcode.php`, `return-state.js`)**
  - Previous and Next labeled with the neighbor symbol (← CLTCL1, CNTNAP1 →), with direction and destination in the accessible name. Return to the Gene Browser restores filters and scroll position (`cmt-gene-browser` added to the return-state map).
  - inc/shortcodes/context-nav-shortcode.php
  - assets/js/return-state.js

- **ClinVar Variants card: copy, stars, sticky tiers (`gene-fields-template.php`, `gene-jsonld.php`, `gene-page.css`, `gene-page.js`, `platform-search.css`)**
  - Card note: "Pathogenic and likely pathogenic variants in GENE, as classified in ClinVar, are read live from NCBI. Only aggregate germline records are shown. Uncertain and conflicting classifications are not. Experts in CMT makes no claim to the accuracy of ClinVar data. This index is provided for informational purposes only." Dataset description matches. Reported in Other Diseases explainer: "Reported in a disease other than CMT. Listed apart rather than counted as CMT variants."
  - Review stars: all four in Star Gold, filled for earned, outlined for the rest, card and search results. Tier header bars match the browsers' height and type size. Open tier summaries are sticky below the admin bar with the table header beneath. Search-target row washed in Light Blue at 10%. Reported in CMT condition chips in Deep Blue on the Light Blue 10% wash.
  - Closing a tier from its pinned header holds the viewport at the header bar. A condition name renders once per row.
  - templates/gene-fields-template.php
  - inc/acf/gene-jsonld.php
  - assets/css/gene-page.css
  - assets/js/gene-page.js
  - inc/search/platform-search.css

- **Every medical page names its author (`pages-jsonld.php`, `subtype-jsonld.php`, `educational-jsonld.php`)**
  - `MedicalWebPage` author, publisher, and website reference the Yoast organization and website entities by @id on medical, subtype, and educational pages, replacing inline copies.
  - inc/acf/pages-jsonld.php
  - inc/acf/subtype-jsonld.php
  - inc/acf/educational-jsonld.php

- **No cache-version query strings (`functions.php`, `eic-admin-tools.php`, `dataset-download.php`)**
  - Stylesheets, scripts, and the dataset download serve without `?ver=` or `?v=`.
  - functions.php
  - mu-plugins/eic-admin-tools.php
  - inc/shortcodes/dataset-download.php

- **Gene page and search variants: mobile and accessibility (`gene-page.css`, `gene-fields-template.php`, `gene-page.js`, `platform-search.css`, `platform-search-render.php`)**
  - Stored Identifiers stack to one labeled column on phones; evidence badges wrap. Stacked-table labels are real text. External links announce "opens in a new tab". Stars carry a spoken equivalent. Totals line announced on load. Focus never lands under a sticky header; arriving from search, focus lands on the target row. Variant names and cDNA wrap at any width.
  - assets/css/gene-page.css
  - templates/gene-fields-template.php
  - assets/js/gene-page.js
  - inc/search/platform-search.css
  - inc/search/platform-search-render.php

- **Structured data hardened against markup in values (`pages-jsonld.php`, `subtype-jsonld.php`, `gene-jsonld.php`, `frontpage-jsonld.php`, `educational-jsonld.php`)**
  - Angle brackets in every schema value emit as unicode escapes.
  - inc/acf/pages-jsonld.php
  - inc/acf/subtype-jsonld.php
  - inc/acf/gene-jsonld.php
  - inc/acf/frontpage-jsonld.php
  - inc/acf/educational-jsonld.php

### Fixed

- **Gene Browser: Enter on a focused symbol link opens the gene page instead of toggling the row (`gene-browser-table.php`)**
  - inc/shortcodes/gene-browser-table.php

- **ClinVar Variants: cache keyed on ClinVar's actual release date, named in the totals line, in place of a week-number fallback (`eic-clinvar-variants.php`)**
  - mu-plugins/eic-clinvar-variants.php

- **ClinVar Variants: no-disease reports land in Variants w/o a Recorded Disease at every gene (`eic-clinvar-variants.php`, `gene-fields-template.php`)**
  - Previously diverted to Reported in Other Diseases at genes whose set includes other diseases. Other Diseases explainer updated.
  - mu-plugins/eic-clinvar-variants.php
  - templates/gene-fields-template.php

- **Variant grammar: suffix shorthand (`R98fs`, `T118del`, `G107dup`) parses and displays as `p.Arg98fs`, `p.Thr118del`, `p.Gly107dup` (`eic-variant-index.php`)**
  - mu-plugins/eic-variant-index.php

- **Variant Index: per-gene state survives a cron tick; a freshly built gene indexes once; multi-variant queries resolve each key site-wide unless a gene was typed; each queued gene gets its own time budget (`eic-variant-index.php`, `platform-search-variants.php`)**
  - mu-plugins/eic-variant-index.php
  - inc/search/platform-search-variants.php

- **Mechanism Details panel spans both fact columns on desktop instead of clipping at the page edge (`subtype-mechanism.css`)**
  - assets/css/subtype-mechanism.css

- **ClinVar Variants: accented condition names classify (`eic-clinvar-variants.php`)**
  - CMT and legacy name patterns compiled with the unicode flag; "Roussy-Lévy syndrome" now lands in Reported in CMT as a legacy name instead of Reported in Other Diseases.
  - mu-plugins/eic-clinvar-variants.php

- **Variant search: miss and did-you-mean copy (`platform-search-render.php`)**
  - Miss line: "No indexed pathogenic or likely pathogenic ClinVar records found for VARIANT in GENE." and, with no gene typed, "No indexed pathogenic or likely pathogenic ClinVar record matches VARIANT in any CMT gene cataloged by EIC." Did-you-mean entries read gene first ("ITPR3 p.Thr1424Met").
  - inc/search/platform-search-render.php

- **Variant Mechanism browser: Enter on a focused gene or subtype link navigates instead of toggling the row (`variant-mechanism-table.php`)**
  - inc/shortcodes/variant-mechanism-table.php

### Removed

- **ACF post type definition for subtypes (`acf-json/post_type_674650572f387.json`)**, superseded by `inc/cpt/subtype-cpt.php`.

## [4.2.0] - 2026-08-20

Platform Search overhaul: the site search now understands what you mean, forgives what you mistype, and always gives you somewhere to go.

### Added

- **Live Search Results (`platform-search-ajax.js`, `platform-search-endpoints.php`, `platform-search-results.php`)**
  - Search results now appear as you type, without reloading the page. Pressing Search still works exactly as before, and the page address updates with each search, so any result page can be copied and shared as a link. Browser Back and Forward step through your searches.
  - On any page where the live layer cannot run, the search form falls back to its native behavior unchanged.
  - assets/js/platform-search-ajax.js
  - inc/ajax/platform-search-endpoints.php

- **Search Never Comes Back Empty (`platform-search.php`, `platform-search-variables.php`, `platform-search-results.php`)**
  - A near-miss is corrected automatically: searching "cmt1q" shows the closest subtypes under a banner explaining that nothing matched exactly. A farther miss offers "Did you mean" links to the nearest subtypes and genes.
  - When there is truly nothing to suggest, the search page presents the Explore The Platform cards, the same guided starting points as the site's 404 page, so a dead end always leaves a path forward.

- **Preview and Handoff for Broad Searches (`platform-search.php`, `gene-browser-table.php`)**
  - A broad search, such as an inheritance pattern, a neuropathy type, a chromosome, or a CMT type, now previews the first five matching subtypes and genes with a "Showing 5 of N" line, then hands off to the CMT Subtype Browser or CMT Gene Browser with the matching filter already applied, instead of listing every record on the search page.
  - The Gene Browser can now be linked with an exact set of genes (`gb_genes`), used for gene sets no single filter can express, such as the aminoacyl-tRNA synthetase panel. A notice chip shows the active gene set and clears with one click.
  - Searches with no browser filter to hand off to preview five subtypes with a "Show all N" reveal in place.
  - inc/shortcodes/gene-browser-table.php

- **EIC Search Admin Page (`mu-plugins/eic-search-tools.php`)**
  - Settings → EIC Search: an alias table that maps the terms patients actually type to what they should find: subtypes, genes, types, pinned articles, search phrases, and highlight terms. Rows run ahead of the built-in vocabulary, are validated on save with plain-language warnings, and take effect immediately.
  - A search log records what visitors search and, most importantly, what returned nothing, so the vocabulary can grow from real usage. Query text only: no accounts, no visitor identity, administrator searches excluded, and entries older than 90 days pruned automatically. Includes a zero-result rollup, a recent-searches view, and a CSV download.
  - mu-plugins/eic-search-tools.php

- **Highlighted, Windowed Excerpts (`platform-search.php`)**
  - Matched terms are now marked in every content excerpt, on every search path. Excerpts center on the sentence where the match actually lives, so a search for KIF1B shows the passage about KIF1B rather than an unrelated summary. Curated articles stay pinned first, with prose mentions following.

- **Classification Pills (`platform-search-render.php`, `platform-search.css`)**
  - Types, subtypes, and genes render as color-coded pills in the same classification family palette the Gene Browser uses, so CMT1 blue and CMT2 green mean the same thing everywhere. Content results carry a source byline on the title line, such as "| The Dorsal Root".

### Changed

- **Results page redesign (`platform-search.css`, `platform-search-render.php`)**: a coherent type scale, tighter spacing calibrated to the new previews, and a restored mobile hierarchy (the sitewide mobile font floor previously flattened the search headings and pills to body size).
- **Search engine internals (`platform-search.php`, `platform-search-variables.php`)**: the resolver was rebuilt as an ordered registry with the curated vocabulary as a data table, verified byte-identical against a 66-query baseline before and after. Results are briefly cached, so repeated searches are instant.
- **Readable filter links (`cmtgenes-helpers.php`)**: browser handoffs now use word-based addresses such as `?inheritance=autosomal-dominant` instead of numeric IDs. Legacy numeric links keep working.
- **Accessibility (`platform-search-render.php`, `platform-search-ajax.js`)**: proper heading structure (page, then your search, then each result group), keyboard focus handed to newly revealed results, updating results announced to assistive technology, comfortable tap targets on touch screens, and every pill color verified for contrast.

### Fixed

- X-linked inheritance searches never matched due to how hyphens were normalized; "x linked recessive" now resolves to its subtypes correctly.
- Publication author search queried field names that did not exist and could never match; searching an author name now finds their subtypes.
- "years" no longer triggers the aminoacyl-tRNA synthetase gene panel, and "cmta" no longer resolves to Dominant Intermediate A.
- Long names such as HMSN-Okinawa Type no longer overlap neighboring entries in results.
- Links into the Subtype Browser, Gene Browser, and Variant Mechanisms now land on the filters and results reliably, instead of drifting into page prose while images above finish loading.
- Repeating a search no longer stacks `#results` onto the page address.

### Security

- The search log's CSV download neutralizes spreadsheet formula prefixes, so a hostile search query cannot execute as a formula when the file is opened in Excel or Google Sheets.

### Removed

- Unused hosting-vendor mu-plugins removed from the development repository (endurance-page-cache, automation-by-installatron, woocommerce-analytics-proxy-speed-module).

## [4.1.0] - 2026-08-18

### Added

- **Gene Browser and Variant Mechanisms: Shareable Filter Links with Back and Forward (`gene-browser-table.php`, `variant-mechanism-table.php`)**
  - Filter, search, and sort choices on both tables now write to the page address, so any configured view can be copied and shared as a link. Opening that link restores the same filters and scrolls the reader to the filter controls. Both tables already render their full catalog on load, so this is handled entirely in the browser with no extra server request.
  - The address updates use prefixed keys (`gb_` for the gene browser, `vm_` for variant mechanisms) so the two tables never collide with each other or with the Genetics Database. Multi-select facets are comma-joined. The variant table's existing per-subtype row link (`#vmech-<code>`) is preserved and still takes precedence over the filter landing when both are present.
  - Browser Back and Forward now step through filter states, matching the Genetics Database. A discrete change (a facet, the chromosome menu, or sort) adds a history entry; typing in search replaces the current one, so a search term does not leave an entry per keystroke. Returning to an earlier or empty state restores the controls and the table cleanly.
  - inc/shortcodes/gene-browser-table.php
  - inc/shortcodes/variant-mechanism-table.php

- **Gene Browser and Variant Mechanisms: Live Facet Counts (`gene-browser-table.php`, `variant-mechanism-table.php`, `variant-mechanism.css`)**
  - The count beside each filter option now updates as filters change, showing how many entries that option would return in the current context rather than a fixed total. An option that would return nothing is dimmed and disabled, so a dead-end combination is visible before it is clicked. A currently-checked option is never disabled, so it can always be switched back off. Counts track the search box as well, not only the checkboxes.
  - Counting neutralizes the option's own group. Within a multi-select group the options are combined with OR, so a selected option does not zero out its siblings; each sibling instead shows how many results adding it would bring. Everything is computed in the browser from data already carried on each row, so there is no server request.
  - Because the gene browser is gene-resolved, a gene is counted under every classification and inheritance mode it carries. Genes such as NEFL and MPZ span CMT1, CMT2, and CMT-intermediate, so the classification counts overlap by design and sum past the gene total, which is the correct reading for a per-gene table. The chromosome menu keeps a plain list with no per-option counts.
  - inc/shortcodes/gene-browser-table.php
  - inc/shortcodes/variant-mechanism-table.php
  - assets/css/variant-mechanism.css

- **GeneReviews Corrections: Subtype-Keyed Tool for `genereviews_url` (`eic-genereviews-corrections.php`)**
  - A new Tools > GeneReviews Corrections admin page sets or clears `genereviews_url` one subtype at a time, from a JSON dataset, with the same dry-run and commit shape as the other importers. It exists because `genereviews_url` is subtype-specific and the only tool that wrote it was keyed on gene symbol, and because that tool structurally cannot clear a value: its write loop opens `if ($new === "") continue;`, so a stale URL already stored survives any change to its source. Matching runs `code` against the ACF `subtype` field, then post title, then slug, with a `candidate-{slug}` fallback for candidate records.
  - URLs are validated against `#^https://www\.ncbi\.nlm\.nih\.gov/books/NBK\d+/$#`, and the tool refuses a dataset not marked `genereviews-v1`. That guard matters more here than on the mechanism importer: an empty value is a real instruction to clear, so a foreign dataset whose records lack the key would blank the field on every subtype it matched rather than merely skipping it.
  - mu-plugins/eic-genereviews-corrections.php

### Changed

- **Genes Database Renamed to CMT Subtype Browser Throughout the Theme Code (`functions.php`, `inc/`, `assets/`)**
  - The feature formerly called the Genes Database is now the CMT Subtype Browser. The 14 theme files named for it were renamed from `genes-*` to `subtype-browser-*` (the loop, filter, facet counts, totals helper, totals-inline shortcode, canonical type-order, AJAX endpoints, hero shortcode, hero ACF fields, the loop fragment partial, and the three `genes-*` stylesheets plus `genes-ajax.js`), with every `require`, `get_template_part`, and enqueue reference updated to match. All comments, docblocks, and CSS headers that named the old feature were rewritten, including the cross-references in sibling files (variant mechanism, glossary, dorsal root, header banner).
  - Internal identifiers were left in place on purpose, so nothing in the stored page content or in shared links breaks: the `[genes_loop]`, `[genes_filter]`, `[genes_hero]`, and `[genes_totals_inline]` shortcode tags, the `genes-filter`/`genes-hero` CSS classes and custom properties, the `genes_get_loop` AJAX action, the `GENES_AJAX` script variable, and the filter query parameters are all unchanged.
  - The page moved to `/genetics/cmt-subtype-browser/`, so the theme's links to it were reconciled to be slug-agnostic. A new `eic_subtype_browser_page_url()` helper resolves the page by the shortcode it hosts (mirroring `eic_vmech_page_url()`), so the filter form action, reset, and pagination links, the platform search result links, the context-nav Return button, and the entry-point cards all follow the page wherever it lives, in page-load and AJAX contexts alike. The script-enqueue gate and the return-state page map were pointed at the new leaf slug `cmt-subtype-browser`. Only editor-authored links inside page bodies, the footer, and the block templates still name the old slug; those are content, not theme code.
  - Verified by a full WordPress bootstrap: the theme loads with no error, all four shortcodes register, the resolver returns the live page URL, and the browser page renders end to end with its form action on the new path.
  - functions.php, inc/content/loops/, inc/content/filters/, inc/content/sort/, inc/ajax/, inc/acf/, inc/shortcodes/, inc/search/, assets/css/, assets/js/

- **Variant Mechanism: `mechanism_flavor` Renamed to `mechanism_mode`, and Its Vocabulary Rebuilt (`subtype-fields.php`, `subtype-jsonld.php`, `variant-mechanism-table.php`, `subtype-fields-template.php`)**
  - The second-level mechanism field is renamed from `mechanism_flavor` to `mechanism_mode` (ACF key `field_mechanism_flavor` to `field_mechanism_mode`). "Flavor" read as a database column rather than science and appeared nowhere in the literature; "mode" pairs with `mechanism` and is native to genetics vocabulary. The public label is unchanged, since all three render paths already displayed it as "Mechanistic Basis," so nothing user-facing was renamed.
  - The vocabulary changed with it, under one rule: the mode carries a mechanistic claim beyond what the class and Zygosity already state, or it is empty. Two values were pure restatement and are retired. `biallelic` sat on 76 rows, every one of which already declared two affected copies in `zygosity`; `dominant-negative` sat on 27 rows, all of which carry `mechanism = dominant_negative`. That was 103 of 174 rows where the field said nothing the record did not already say. `homoplasmic` is retired for the same reason, allele count belonging to `zygosity`, which already carries Heteroplasmic and Homoplasmic.
  - `biallelic` is replaced by a distinction the literature makes constantly: `complete-loss` (42 rows), where the allele class abolishes the protein or its activity and homozygous true nulls are the typical disease genotype, and `hypomorphic` (35 rows), where residual partial activity is characteristic. The deciding test is whether two true null alleles would be compatible with the phenotype; where complete loss is embryonic-lethal or causes a more severe disease, the CMT alleles are necessarily the milder end. `dominant_negative` now takes an empty mode, because the literature recognizes no standard sub-mode of interference and inventing buckets would be manufacturing taxonomy rather than recording it. Both render sites already guarded on a non-empty value, so the 29 dominant-negative subtypes omit the line cleanly with no template change.
  - The editor instructions on the field now state the axis rule and tell the curator to leave the mode empty for Dominant-negative. `subtype-jsonld.php` is included because it emits Mechanistic Basis as a schema.org `PropertyValue`, so a stale label map there would have put retired values into the structured data.
  - inc/acf/subtype-fields.php
  - inc/acf/subtype-jsonld.php
  - inc/shortcodes/variant-mechanism-table.php
  - templates/subtype-fields-template.php

- **Mechanism Importer: Writes `mechanism_mode`, Accepts the Legacy Key, and Names Retired Values (`eic-mechanism-importer.php`)**
  - The importer writes `mechanism_mode` and validates against the ten-value mode enum, accepting an empty mode as valid so a dominant-negative record round-trips. The record's canonical value key is `mode`, with `flavor` retained as an alias so an older hand-pasted record is still read rather than silently treated as absent.
  - A retired value now fails with guidance instead of a bare rejection: `biallelic` reports that allele count belongs on zygosity and to use `complete-loss` or `hypomorphic`, `dominant-negative` reports that it restates the class and the mode should be left empty, and the three allele-count values report where they belong. This turns the most likely operator mistake into a self-explaining error.
  - mu-plugins/eic-mechanism-importer.php

- **Variant Mechanism Prose: All 174 Subtypes Rewritten (data, loaded through the Mechanism Importer)**
  - Data rather than code, recorded here because it is the substance of what the Variant Mechanisms surfaces now render. The Prediction and Rationale fields had collapsed into a handful of sentence templates with the gene name swapped: 46 rows shared "so restored wild-type is predicted to rescue: a biallelic loss of function," 67 Predictions opened with the same eleven words, and the confidence-hedge clause was repeated 107 times. The defect is invisible row by row and unmistakable down a column, and the supplementation test had become a reflex applied to rows where nobody had proposed the alternative.
  - Every row was rewritten to lead with what is true only of that row: the protein, what these alleles do to it, and the specific published evidence, whether a founder allele, a knockout mouse, an enzyme assay, a complementation result, or a parallel disease at the same locus. The most-repeated eight-word sequence in the Rationale field fell from 46 rows to 2. Also removed: seventeen rows of derivation commentary addressed to the grader rather than the reader, and twelve rows of conflation bookkeeping ("this subtype's own alleles"), which exists only to reassure a reviewer that a sibling subtype was not borrowed from.
  - The confidence clause is now reserved for genuinely contested mechanisms. No high-confidence row carries a hedge.

- **Variant Mechanism Calls: Five Changed on Researched Evidence (data)**
  - `CMT-ARHGEF10` from Unknown to gain-of-function, overactivity, medium. The functional literature was unreachable under a nomenclature split: the sentinel publication reports p.Thr109Ile while the functional work numbers the same variant p.Thr332Ile (`NM_014629.4:c.995C>T`). The allele sits at the edge of an autoinhibitory region and behaves like its deletion, loading more GTP onto RhoA and shortening Schwann cell processes, which is what thin myelin with uniformly slowed conduction and no axon loss predicts.
  - `dHMN-AARS1` from Complex to dominant-negative, medium, aligning it with `CMT2N`. The two are one gene and one broad allelic series, and the loss-plus-toxic profile the Complex grade described belongs to the CMT2N alleles rather than the dHMN allele, which tested negative on aminoacylation, conformation and novel binding.
  - `CMT-CFAP276` from Complex to loss-of-function, haploinsufficiency, medium. Heterozygous null mice develop a dominant-intermediate CMT and AAV gene addition corrects nulls, while the second arm's aggregating allele was never shown toxic or neomorphic and its knock-in phenocopies the pure null.
  - `CMT-CRYAB` from Complex to dominant-negative, low. Complex requires one subtype's own alleles to carry two mechanisms; this subtype has one allele, and what resembled two mechanisms was several groups disagreeing about it.
  - `dHMN1-UBE3C` from loss-of-function to Unknown, unresolved, low. Haploinsufficiency is affirmatively contradicted, since biallelic UBE3C loss causes a separate recessive neurodevelopmental disease whose heterozygous carriers have no neuropathy.
  - Two mode corrections followed from the same review: `CMT2P` and `CMT2T` keep `mixed`, which is correct and deliberate, as each subtype is genuinely defined across a dominant and a recessive allele class with its own sentinel publication for each.

### Fixed

- **GeneReviews Links: 92 Subtypes Pointed at the Wrong Chapter, or at One They Should Not Have (data)**
  - A subtype gets a GeneReviews link only where GeneReviews has a chapter for that subtype. No chapter, no link. GeneReviews is a flourish surfaced where available, not a field to be filled, so most subtypes carrying none is the correct state rather than a gap. Two things are disqualified by that rule and both were in use as if they were not: multi-gene overviews, which are about no single subtype, and a different disease at the same gene, however close, so Spastic Paraplegia 11 is not CMT2X's chapter and Leber optic neuropathy is not CMT-ATP6's.
  - 121 records carried a link. 91 were cleared, 67 of them pointing at a multi-gene overview, 12 at a different disease at the same gene, 11 failing the per-subtype test on a gene-specific chapter, and 8 candidate-gene records cleared as policy. One was corrected rather than cleared: `CMT-RFC1` moves from the Hereditary Ataxia Overview to NBK564656, RFC1 CANVAS / Spectrum Disorder, on the KOL consensus that treats CANVAS as the RFC1 entity, that chapter being authored by Cortese, Reilly and Houlden. 29 links were already correct and are untouched, leaving 30 of 193 records carrying a link.
  - All 19 chapters behind the retained links were verified live against NCBI, with three known retirements used as controls to prove the check worked rather than trusting a null result. Worth keeping for any re-check: four live chapters carry the literal string "RETIRED CHAPTER, FOR HISTORICAL REFERENCE ONLY" in their markup because NCBI's sidebar links out to retired chapters, so a naive grep condemns them wrongly. The reliable markers are the title suffix and the "HAS BEEN RETIRED" banner.
  - The visible damage was compounded by the button label. `subtype-fields-template.php` renders "{subtype} GeneReviews®", so CMT2JJ's page carried a button reading "CMT2JJ GeneReviews®" pointing at a dilated cardiomyopathy chapter.

- **External Records Backfill: GeneReviews Removed From a Gene-Keyed Tool (`eic-external-records-backfill.php`)**
  - `genereviews_url` lives on a subtype record and is subtype-specific, and one gene's subtypes routinely need different answers. This tool is keyed on `gene_symbol` and writes one value to every subtype of that gene, taking whatever chapter names the gene without testing that the chapter is about the subtype. It structurally cannot express the right value, which is why it pointed BAG3 at Dilated Cardiomyopathy, TUBB3 at Congenital Fibrosis of the Extraocular Muscles, HK1 at Hyperinsulinism, JAG1 at Alagille Syndrome, and MYH14 at a Genetic Hearing Loss Overview that never names MYH14.
  - GeneReviews is therefore removed from the tool rather than its map corrected entry by entry, since correcting the map would leave a gene-keyed mechanism in place for subtype-specific data, armed to break again the first time an entry was repopulated. `fields()` no longer lists `field_genereviews_url`; the write loop indexes `$row[$i]` off those keys, so dropping entry 0 makes it skip GeneReviews while indices 1 through 11 stay aligned. Slot 0 of all 149 data rows is emptied so the dead values cannot be resurrected by re-adding the field, and slots 1 through 11 are byte-identical to the original, verified row by row. The header docs, admin description and coverage counter no longer claim the tool writes GeneReviews.
  - Every remaining field there is gene-level by nature: ClinGen gene-disease validity, ClinGen dosage sensitivity, PanelApp panel 846, and a gene-keyed Orphanet URL. GeneReviews was the sole exception. PMP22 stops being a special case as a result: HNPP keeps NBK1392 while CMT1A and CMT1E get nothing, because each record is now addressed individually rather than through its gene.
  - mu-plugins/eic-external-records-backfill.php

- **Subtype Page: Duplicate GeneReviews Button (`subtype-fields-template.php`)**
  - Every subtype carrying a `genereviews_url` rendered two identical GeneReviews buttons, from a byte-identical duplicate of the `eic-fact` block. Removed the second copy. Verified that `$genereviews_url` was the only variable guarded by more than one `!empty()` block in the file, and that the repeated Publication Title, Authors, Publication Date and DOI labels are not duplicates but the primary and alternate publication blocks.
  - templates/subtype-fields-template.php

- **Mechanism Importer: Silent Field Blanking on a Schema Mismatch (`eic-mechanism-importer.php`)**
  - Loading a dataset built for a different schema passed validation without complaint and would have blanked the mechanism field on every matched subtype: the importer read a value key the dataset did not carry, the missing key validated as an empty string, and the empty string overwrote. The importer now carries a `SCHEMA` constant and refuses a dataset whose top-level `schema` marker names a different one, with an error stating both markers and the consequence. Unmarked input is still accepted so hand-pasted records keep working. Verified in all four directions across the two schema versions.
  - mu-plugins/eic-mechanism-importer.php

## [4.0.0] - 2026-08-16

The CMT Gene Browser release: a new gene-resolved public surface, its data model, the admin tooling that populates it, and the versioned open dataset behind it. This is a major bump because the subtype record's long-required core fields (subtype, chromosome, inheritance, neuropathy, zygosity, type classification, year of discovery, and the full publication set) are now optional, so the store can hold candidate gene associations and structural records that are not classified subtypes. Also folds in the Dorsal Root visibility, header banner, and genes-search work committed since 3.1.0.

### Added

- **CMT Gene Browser: Gene-Resolved Table (`gene-browser-table.php`)**
  - A new `[gene_browser]` shortcode renders a live, database-driven table of the CMT disease genes, one row per gene, resolved from the subtype store by grouping a `WP_Query` over published subtypes on `gene_symbol`. Every gene ships in the server-rendered HTML; search, facets, sort, A-Z jump, and row expand are client-side over that small catalog. Each row expands to the gene's external records, stored identifiers, and its subtype list.
  - inc/shortcodes/gene-browser-table.php

- **CMT Gene Browser: App Hero (`gene-browser-hero-shortcode.php`, `gene-browser-hero-fields.php`, `eic-gbx-hero-mask-preview.php`)**
  - A `[gene_browser_hero]` shortcode gives the Gene Browser the same app-hero treatment as the Genes DB and Variant Mechanisms tools: a background image output as a CSS custom property, a left-side fade driven by desktop and mobile ACF Range slider pairs, a navy title, intro copy, and a three-item stats line (genes cataloged, classified subtypes, chromosomes). Each stat self-trims if empty, and the counts render exactly with no evergreen "+", since these are exact totals.
  - The ACF field group locates itself to whichever page hosts `[gene_browser]` (cached, self-healing on a miss) so there is no hard-coded page ID. An editor-only mask-preview mu-plugin paints the desktop and mobile fade live onto the hero thumbnail in the meta box, a sibling of the Variant Mechanisms hero preview under its own class namespace.
  - inc/shortcodes/gene-browser-hero-shortcode.php
  - inc/acf/gene-browser-hero-fields.php
  - mu-plugins/eic-gbx-hero-mask-preview.php

- **CMT Gene Browser: Subtype Data Model for Genes (`subtype-fields.php`)**
  - The subtype record gains the fields the gene-resolved surface reads. Candidate-gene fields: `candidate_gene` (true/false), `candidate_since`, and a `candidate_note` for a downgrade's KOL rationale. A `genesis_discovery` true/false flag. Eleven external-record fields: `clingen_classification`, `clingen_disease`, `clingen_mondo`, `clingen_validity_url`, `panelapp_rating`, `panelapp_votes`, `panelapp_url`, `clingen_hi`, `clingen_ts`, `clingen_dosage_url`, and `orphanet_url`. Ten identifier fields: `hgnc_id`, `ensembl_gene_id`, `coords_grch38`, `coords_grch37`, `entrez_id`, `uniprot_id`, `refseq_accession`, `mane_select_refseq`, `mane_select_ensembl`, and `gene_function`.
  - The "Advanced" tab is renamed "Identifiers" to match its new contents.
  - inc/acf/subtype-fields.php

- **CMT Dataset Export: Versioned DLC Generator (`eic-dataset-export.php`)**
  - Builds the public, versioned download of the gene-resolved CMT dataset as JSON and CSV from the live subtype store, applies the redistribution license filter, stamps a provenance and license manifest, and writes versioned files plus a bundled zip (JSON, CSV, README, license) to uploads/eic-datasets/. The license filter passes IDs, URLs, ClinGen values, and the attributed UniProt gene function, omits the PanelApp rating value pending a Genomics England reuse license, and emits no GeneReviews or OMIM text. The artefact is a pinned file, never a live endpoint, so a cited version is fixed and reproducible; the data version is independent of the theme version.
  - mu-plugins/eic-dataset-export.php

- **CMT Dataset Download: Ungated Button (`dataset-download.php`)**
  - An `[eic_dataset_download]` shortcode links the newest zip produced by the export tool as a direct, ungated download, matching the open CC BY 4.0 release: no email capture, no gate. Renders nothing until a build exists; styles print once inline.
  - inc/shortcodes/dataset-download.php

- **HGNC Identifiers Tool (`eic-hgnc-identifiers-tool.php`)**
  - Fills the gene-level identifier fields on a subtype from a single live HGNC lookup keyed on `gene_symbol` (full gene name, HGNC ID, Ensembl gene ID, Entrez ID, OMIM gene, UniProt accession, RefSeq, MANE Select RefSeq and Ensembl, chromosome, and the UniProt function summary). Delivered as an editor button under the gene symbol field and a bulk backfill under Tools > HGNC Identifiers with dry-run and commit. Only an Approved HGNC record is accepted, so a withdrawn entry is rejected and the structural record (CMTX3) is skipped safely. Live lookups are capped per run against the PHP time limit; the shared cache persists so a re-run resumes for free.
  - mu-plugins/eic-hgnc-identifiers-tool.php

- **External Records Backfill (`eic-external-records-backfill.php`)**
  - Writes the external-record fields onto subtypes from a signed cross-reference of EIC genes against five sources: GeneReviews (gene chapter), ClinGen gene-disease validity (CMT GCEP only), Genomics England PanelApp 846 (rating, votes, URL), ClinGen dosage sensitivity (HI/TS where evidence exists), and Orphanet. Matches on `gene_symbol`; non-destructive and re-runnable, writing only when a source has a value that differs from what is stored and never clearing a field.
  - mu-plugins/eic-external-records-backfill.php

- **GENESIS Discovery Backfill (`eic-genesis-discovery-backfill.php`)**
  - Sets the gene-level `genesis_discovery` flag on subtypes whose CMT disease-gene relationship was discovered or supported through the GENESIS platform, sourced from the Genesis Project Foundation's public discoveries list. The Gene Browser surfaces a "GENESIS discovery" chip in the External Records row. Non-destructive and re-runnable: it sets the flag on matched records that lack it and never unsets a non-listed gene. Refresh the embedded list and re-run as TGP publishes new discoveries.
  - mu-plugins/eic-genesis-discovery-backfill.php

- **Candidate Genes Importer (`eic-candidate-genes-importer.php`)**
  - Creates candidate gene-association records from JSON, each a published `subtype` post carrying `candidate_gene = true`, which hides it from the subtype/genes loop and surfaces it in the Gene Browser. Candidates are not subtypes, so they get a namespaced slug (candidate-{gene}, or candidate-{former subtype} for a downgrade) and a redirect off their single URL. Upsert by slug, so a re-run updates in place rather than duplicating; run the HGNC Identifiers backfill afterward to pull each candidate's identifiers.
  - mu-plugins/eic-candidate-genes-importer.php

- **Authoritative Resources Shortcode (`authoritative-resources-shortcode.php`)**
  - An `[eic_authoritative_resources]` shortcode renders a compact strip of outbound links to primary CMT references, giving answer engines on-page evidence that the site's claims are anchored to recognized sources. Seeded from the same references declared as the CMT MedicalCondition `sameAs` set, and filterable so the list can be curated without editing the file.
  - inc/shortcodes/authoritative-resources-shortcode.php

- **Front Page MedicalWebPage JSON-LD (`frontpage-jsonld.php`)**
  - Emits one additive MedicalWebPage node for the static front page, attributing authorship and publishing to the Experts in CMT organization by @id (Yoast's node, so the graph stays connected) and anchoring the page to the CMT MedicalCondition entity. It defers to pages-jsonld.php: if the front page ever gets a medical specialty selected, that file emits the fuller block and this one bails to avoid a duplicate. Additive; does not touch Yoast's WebPage schema.
  - inc/acf/frontpage-jsonld.php

- **Dorsal Root Visibility: Per-Post Hide Toggles for the List and the Teaser (`dr-visibility-fields.php`, `dr-posts.php`, `section-dorsal-root.php`)**
  - Keeping an individual Dorsal Root post out of the front-facing surfaces (for example a co-authored article that is password-protected while its co-author reviews it) had meant hardcoding that post's ID into an array in the query files and redeploying, then editing the code again to bring it back once cleared. Two per-post checkboxes replace that entirely: a new "Dorsal Root Visibility" panel in the post editor sidebar carries "Hide From Page" and "Hide From Teaser," each an ACF true/false toggle, so an author hides or restores a post with a click and no code change.
  - "Hide From Page" drops the post from the `[dr_posts]` list on `/dorsal-root`. Because the exclusion lives inside the shared `eic_dr_apply_search_filters()`, it applies to the page-load render and the AJAX/live-search endpoint together, so a hidden post cannot slip back in through search. "Hide From Teaser" drops the post from the homepage "The Dorsal Root" teaser cards, covering both the featured query and the latest-posts fallback.
  - A small per-request-cached reader, `eic_dr_hidden_ids($meta_key)`, returns the IDs of posts whose given box is ticked, and both query files feed those IDs to the same `post__not_in` / `array_diff` paths that had held the hardcoded ID, so the surrounding query logic is unchanged and the old `[4891]` literal is gone. The Dorsal Root Showcase is deliberately left alone: a hand-picked showcase post is explicit curation and is not filtered by these toggles.
  - inc/acf/dr-visibility-fields.php
  - inc/content/loops/dr-posts.php
  - templates/parts/section-dorsal-root.php

### Changed

- **Subtype Record: Core Fields Relaxed to Optional (`subtype-fields.php`)**
  - Eleven fields that were required are now optional (with the selects allowing null): type classification, subtype, chromosome, neuropathy, zygosity, inheritance, year of discovery, publication title, publication date, authors, and DOI. A candidate gene association and a structural record are real rows that legitimately carry no subtype code, no classified inheritance, and no establishing publication, so the required constraints could not hold once the store admitted them. This is the structural change behind the major bump.
  - inc/acf/subtype-fields.php

- **Candidate Genes Excluded from the Subtype Surfaces (`fragment-loop-genes-loop.php`, `genes-totals-inline.php`, `variant-mechanism-table.php`)**
  - Candidate gene associations (`candidate_gene = true`) are not classified subtypes, so they are removed from the Genes DB loop, the homepage totals, and the Variant Mechanisms table, and surface only in the Gene Browser. The same OR clause is used in all three: exclude `candidate_gene = 1`, keep everything else via `NOT EXISTS` so ordinary subtypes (meta absent or "0") are retained. In the genes loop it is ANDed in last so it survives every meta_query branch and feeds the filter-aware totals.
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - inc/shortcodes/genes-totals-inline.php
  - inc/shortcodes/variant-mechanism-table.php

- **Entry-Point Cards: Gene Browser Added Across the Card Sets (`entry-points-shortcode.php`)**
  - The home, genetics, and platform (404) sets gain a "CMT Gene Browser" card ("The gene, resolved") aimed at researchers and clinicians, so the home set is now five cards. The database card is renamed "CMT Subtype Browser" and the variant card "CMT Variant Mechanisms Browser," matching the production nav labels. The DNA double-helix icon moves from Genetic Testing to the Gene Browser (where it reads as gene-resolved), Genetic Testing takes a new lab-vial glyph, and The Dorsal Root card takes a new dorsal-root-ganglion glyph in place of the impulse waveform. On the 404 set the Glossary card is swapped out for the Gene Browser and kept commented for easy restore.
  - inc/shortcodes/entry-points-shortcode.php

- **Variant Mechanisms Table: Sticky Header, Count, and Accessibility Now Mirror the Gene Browser (`variant-mechanism.css`, `variant-mechanism-table.php`)**
  - The filter card and column header no longer ride together in one sticky wrapper; only the column header is sticky, and the filter scrolls away above it, matching the Gene Browser. The filter action row goes full-width and left-aligned with the count beside Reset, the search field gains an inline magnifier icon, and the count line reads "Showing N subtypes" at rest and "Showing N of total subtypes" when filtered. Accessibility: the row toggle gains `aria-controls` pointing at its detail row; the focus ring and the italic gene accent are darkened to clear WCAG contrast (the gene accent from #5ea0c9 to #26719c, the focus ring to `--primary`); the plus animation respects `prefers-reduced-motion`; and the detail labels adopt the uppercase muted section-label style. The AD/AR inheritance abbreviation renders as "AD, AR".
  - assets/css/variant-mechanism.css
  - inc/shortcodes/variant-mechanism-table.php

- **Shared HGNC Cache: In-Request Memo with a Single Deferred Write (`eic-clingen-url-tool.php`, `eic-clinvar-url-tool.php`, `eic-omim-tool.php`, `eic-gene-name-tool.php`)**
  - The shared `eic_hgnc_cache` option was read and re-written on every uncached gene during a bulk backfill, order n-squared option I/O. The four tools now memoize the option in-request and flush once at shutdown, and each preserves any keys the other tools set on a shared cache entry instead of overwriting the whole entry.
  - mu-plugins/eic-clingen-url-tool.php
  - mu-plugins/eic-clinvar-url-tool.php
  - mu-plugins/eic-omim-tool.php
  - mu-plugins/eic-gene-name-tool.php

- **Gene Name Tool: Field-Key Writes and a Per-Run Live-Lookup Cap (`eic-gene-name-tool.php`)**
  - The tool now writes `full_gene_name` by field key (`field_full_gene_name`) for reliable ACF resolution, matching the HGNC identifiers tool and the CLAUDE.md convention, and splits the HGNC lookup into a cache-only read plus a live fetch so a run spends a bounded budget (40) of live lookups and defers the rest with a "cold cache" notice, keeping a whole-store run under the PHP time limit.
  - mu-plugins/eic-gene-name-tool.php

- **Subtype Maintenance: Scans Run On Demand (`eic-subtype-maintenance.php`)**
  - The maintenance checks are N+1 over every subtype across six checks, so they no longer run on every page load. A "Run scan" button triggers them, and a scan runs automatically right after an apply so the updated state shows without a second click.
  - mu-plugins/eic-subtype-maintenance.php

- **ClinGen Validity Map: MONDO and Classification Corrections (`eic-clingen-url-tool.php`)**
  - The embedded ClinGen CMT GCEP map is reconciled to ClinGen's live surface: classification upgrades for ATP1A1, LITAF, and DNAJB2's disease term, and corrected MONDO identifiers for DNAJB2, DYNC1H1, MFN2, MME, SCN11A, and SH3TC2.
  - mu-plugins/eic-clingen-url-tool.php

- **Header Banner: Taller on Desktop (`header-banner.css`)**
  - Increased the desktop header banner height for more presence at the top of the page. Mobile is unchanged.
  - assets/css/header-banner.css

### Fixed

- **Subtype Page: Publication Block Hidden When There Is No Publication (`subtype-fields-template.php`)**
  - With the publication fields now optional, the Key Publication(s) block is gated on a present primary publication so a candidate or unmapped record no longer renders an empty publication section.
  - templates/subtype-fields-template.php

- **Subtype Page: CMTX3 Insertional Nomenclature (`subtype-fields-template.php`)**
  - The structural CMTX3 record, whose cause is an interchromosomal insertion rather than a coding gene, is displayed in ISCN notation rather than being forced through the gene-symbol display path.
  - templates/subtype-fields-template.php

- **Candidate Records: Empty Subtype Allowed When Flagged Candidate (`cmtgenes-helpers.php`)**
  - The duplicate-subtype ACF validation returned "Subtype is required" on an empty subtype, which blocked saving a candidate gene association (which legitimately has none). An empty subtype is now valid when the `candidate_gene` flag is on.
  - mu-plugins/cmtgenes-helpers.php

- **Importers: Strip a Leading UTF-8 BOM Before Parsing (`eic-subtype-importer.php`, `eic-mechanism-importer.php`, `eic-glossary-importer.php`)**
  - A JSON upload saved by a Windows editor could carry a leading UTF-8 BOM, which failed `json_decode` with a syntax error. The three importers now strip a leading BOM before parsing.
  - mu-plugins/eic-subtype-importer.php
  - mu-plugins/eic-mechanism-importer.php
  - mu-plugins/eic-glossary-importer.php

- **Subtype Importer: Dry Run No Longer Mutates the Shared HGNC Cache (`eic-subtype-importer.php`)**
  - `seed_hgnc_cache()` was writing to the shared HGNC cache option even during a dry run, breaking the preview-does-not-touch-the-DB contract that every other setter honors. It now returns early on a dry run.
  - mu-plugins/eic-subtype-importer.php

- **Mechanism Importer: Guard a Non-Array Record (`eic-mechanism-importer.php`)**
  - A malformed record that is not an array no longer trips the code-extraction path; the code is read defensively before the record is analyzed.
  - mu-plugins/eic-mechanism-importer.php

- **Site References: Corrected .com to .org and Reconciled the Theme Readme (`footer.html`, `readme.txt`, `style.css`)**
  - Stray expertsincmt.com references were corrected to .org in the footer and theme headers, and the theme readme was reconciled.
  - parts/footer.html
  - readme.txt
  - style.css

- **Dorsal Root Filter: SEARCH Button Label Wrapped to Two Lines on Narrow Phones (`dr-filter.css`)**
  - On the `/dorsal-root` search bar the SEARCH label wrapped to a second line at narrow widths, growing the button from 48px to 60px. The mobile button rule now sets `white-space: nowrap` and trims the side padding so each label stays on one line down to ~360px-wide screens. Markup unchanged.
  - assets/css/dr-filter.css

- **Genes DB Search: Short Gene Symbols Matched Author Surnames, Returning Dozens of Unrelated Subtypes (`terms-helpers.php`, `fragment-loop-genes-loop.php`, `genes-facet-counts.php`)**
  - A search for a short gene symbol (for example `MME`) matched author surnames like "Timmerman" through the fuzzy author `LIKE` net, returning far too many subtypes, and the facet counts agreed with the wrong set. A query that exactly matches a canonical identifier now resolves to those posts only via a shared `eic_genes_search_exact_ids($qs)` helper, with the fuzzy author/alias net running only when there is no exact match. Both the loop and the facet counts call the one helper so they cannot drift.
  - inc/content/filters/terms-helpers.php
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - inc/content/filters/genes-facet-counts.php

- **Header Banner: Mobile Fade Sliders Had No Effect on the Rendered Page (`header-banner.php`)**
  - The template emitted only the desktop fade custom properties, so the mobile gradient always fell back to the CSS default stops regardless of the "(Mobile)" ACF sliders. The template now reads and prints the mobile custom properties inline alongside the desktop pair, so the mobile sliders drive the rendered fade.
  - templates/header-banner.php

## [3.1.0] - 2026-07-29

### Added

- **Dorsal Root Showcase: Curated Post List Driven by an ACF Options Page (`dr-showcase-fields.php`, `dr-showcase-shortcode.php`)**
  - The homepage's featured Dorsal Root block was a hand-built stack of media+text blocks, one per article (image, title, excerpt, link placed by hand), so swapping or reordering a featured piece meant rebuilding blocks. A new `[dr_showcase]` shortcode replaces that manual assembly while keeping the curation fully by hand: which posts appear, and in what order, is set in one place and the block rebuilds itself.
  - Curation lives on a global ACF options page ("DR Showcase") holding a single ordered Relationship field. The editor picks Dorsal Root posts from a searchable picker (with thumbnails) and drags them into order; a query filter restricts the picker to posts in the `dorsal-root` taxonomy so only Dorsal Root pieces are offered. The field stores post IDs, not titles, so renaming a post never breaks the showcase. Because it is an options page rather than a per-page field, the same curated set can render anywhere the shortcode is dropped.
  - `[dr_showcase]` reads the chosen IDs in order and renders each through `eic_dr_render_list_item()`, the same row renderer the `/dorsal-root` loop and its AJAX endpoint already share, so the homepage showcase can never visually drift from the live Dorsal Root list, and the rows reuse the site-wide `.dr-list` styles. Unpublished picks are skipped, an empty list renders nothing, and an optional `heading` attribute adds an `<h2>`. Both PHP files auto-load via the existing `inc/acf` and `inc/shortcodes` globs, so no functions.php edit.
  - The options page also carries an optional single Featured Subtype (a Post Object filtered to the `subtype` type), surfaced by its own `[featured_subtype]` shortcode so it can be placed independently of the post list. When set, it renders a labeled "Featured Subtype" spotlight: a two-column card with the subtype's facts on the left (name, aka, gene with the symbol italicized, inheritance, year, and chromosome/neuropathy when present) and, on the right, an auto-summary above a right-aligned "View subtype" link. The summary leads with the subtype's own "What Is ...?" heading (navy, lighter weight, a step up from the body size for hierarchy) and then its first authored body paragraph (capped at a word boundary), which reads better than the SEO-style meta excerpt; it falls back to remaining body text, then the manual excerpt. On phones the two columns stack. The spotlight is self-contained: it reproduces the genes card's gene/inheritance display rather than calling into the genes loop, so the working Genes DB is never touched, at the cost of not auto-following a future change to that card's display rules. Its styling lives in a small dedicated `dr-showcase.css` (the rows still use `dr-loop.css`).
  - inc/acf/dr-showcase-fields.php
  - inc/shortcodes/dr-showcase-shortcode.php
  - assets/css/dr-showcase.css

- **Entry-Point Cards: `[eic_entry_points]` Shortcode with Named Per-Page Sets (`entry-points-shortcode.php`, `entry-points.css`)**
  - The homepage, the Genetics and Learn context pages, and the 404 template each carried a hand-built Stackable column set of clickable panels, one per destination, styled apart from the rest of the site and re-edited in the block editor on every page that used them. A new `[eic_entry_points]` shortcode replaces all of those with hardcoded, single-file card sets that render in the genes/subtype card system: the shared card tokens (radius, shadow, speed, ease), a light-weight navy title, bare navy line-icons in the app heroes' stroke style, and the house scale-on-hover. Every set lays out three-up on desktop for parity with the rest of the site, and the grid centers an incomplete final row (four cards render 3 + 1, five cards 3 + 2), the same way the Dorsal Root loops center a partial last row; below 900px it steps down to two columns, then one.
  - Each card is a clickable panel built on the accessible clickable-card pattern: the title carries the one real link, and a stretched `::after` overlays the whole panel, so the entire card is a click target while a screen reader announces a single link per card. A bottom "Explore" cue is pinned to the card foot (decorative, `aria-hidden`), its arrow sliding on hover, filling the card's lower whitespace and giving a first-time visitor an explicit "this is clickable" signal.
  - Named sets let each surface show its own cards from one file, mirroring that section's nav: `set="home"` (the default) renders the four "Where to start?" on-ramps (What Is CMT?, The Database, Variant Mechanisms, Genetic Testing); `set="genetics"` the four Genetics-nav destinations (CMT Classifications, CMT Genetics Database, CMT Variant Mechanisms, CMT Genetic Testing, matching production's CMT-prefixed nav labels); `set="learn"` the three Learn-nav destinations (What Is CMT?, CMT and Breathing, CMT Glossary); and `set="platform"` a five-card cross-section of the whole site for the 404 "Explore The Platform" block (What Is CMT?, CMT Genetics Database, CMT Classifications, The Dorsal Root, CMT Glossary). A set carries a default lead label (the home set uses "Start here if:"), and any card may override it with its own tagline (the genetics, learn, and platform cards each carry one, e.g. "CMT. Curated.", "Nerves talk. We listen."); a lead that resolves to empty renders no label. Adding another page later is one more named set. An optional `heading` attribute renders an `<h2>` above the grid for a surface with no section heading of its own.
  - Icons follow the heroes and are one-per-destination so no two read alike in a single grid: an info mark for What Is CMT?, a search glass for the database, the Variant Mechanisms categories branch, the Genes DB DNA double-helix for Genetic Testing, an open book for CMT Classifications, an exhale/airflow mark for CMT and Breathing, a nerve-impulse spike for The Dorsal Root (echoing its impulse hero), and a term-and-definition list for the Glossary (kept distinct from the book so the Glossary and Classifications never both read as books on the platform grid). The helix flattens into an "S" at the 28px card size, so it renders a touch larger (34px, via an `--lg` icon modifier) on the Genetic Testing card, where it reads as a double helix.
  - inc/shortcodes/entry-points-shortcode.php
  - assets/css/entry-points.css

- **Variant Mechanism Browser: App Hero (`variant-mechanism-hero-shortcode.php`, `variant-mechanism-hero-fields.php`, `variant-mechanism-hero.css`)**
  - The Variant Mechanisms page opened on a plain post-title while its sibling, the Genes DB, opened on an app hero. A new `[variant_mechanism_hero]` shortcode gives it the same treatment: a background image output as a CSS custom property, a left-side fade driven by two ACF Range sliders, a navy title, intro copy, and a three-item stats line, all editable from a meta box on the page. It is built as its own component rather than a shared one, so the Genes DB hero is never at risk and the two can carry different image, copy, and stats. The stats are data-forward for this tool: subtypes classified, mechanism categories, and count with a resolved mechanism, each a curated number surfaced as its own field and skipped if left empty.
  - The ACF field group mirrors the Genes DB hero's meta-box architecture with Var-Mech-specific fields, and locates itself to whichever page hosts `[variant_mechanism_table]` (cached, invalidated on page save) so there is no hard-coded page ID to maintain. The title renders as an H2 so it inherits the same navy heading style as the Genes DB hero; the page's real H1 stays as the theme post-title, made screen-reader-only so heading order and SEO hold without a second visible title. The hero and the filter/table break out to the same centered 1180px width as the Genes DB, and the filter is pulled up to overlap the hero's flat bottom edge, the same app seam. The breakout adds a transform to the section that holds the sticky filter/header unit; because that section is tall, the sticky keeps its full range and the end-of-scroll release is unaffected.
  - inc/shortcodes/variant-mechanism-hero-shortcode.php
  - inc/acf/variant-mechanism-hero-fields.php
  - assets/css/variant-mechanism-hero.css

- **Both Hero Images: Live Fade-Mask Preview in the Editor (`eic-vmech-hero-mask-preview.php`, `eic-genes-hero-mask-preview.php`)**
  - Both app heroes drive their left-side fade from ACF Range sliders, but an editor could only see where the fade actually landed by saving and reloading the front end. Two editor-only mu-plugins now paint a live preview of the fade directly onto the hero image thumbnail in its meta box: a desktop overlay plus a small mobile tile, both repainting as the sliders move. They are cloned from the existing header-banner mask preview, each under its own class namespace (`eic-vmh-preview` for Variant Mechanisms, `eic-gh-preview` for the Genes DB) so the two never collide, and they load only in the admin.
  - mu-plugins/eic-vmech-hero-mask-preview.php
  - mu-plugins/eic-genes-hero-mask-preview.php

- **Utilities Stylesheet: Opt-In `.eic-collapse-mobile` Helper (`utilities.css`)**
  - A new utilities stylesheet for small, reusable, opt-in helpers that apply only when a block adds the class in its editor "Additional CSS class(es)" field. Its first helper, `.eic-collapse-mobile`, collapses a block to zero height at 600px and below, so a Spacer block placed for desktop breathing room does not leave a tall empty band on phones.
  - assets/css/utilities.css

- **Mechanism Importer: Load the Corrected Variant-Mechanism Dataset onto Subtypes (`eic-mechanism-importer.php`)**
  - The single-value mechanism model added the `mechanism`, `mechanism_flavor`, `mechanism_confidence`, `mechanism_prediction`, and `mechanism_rationale` fields, but the curated dataset that fills them had no loader, so each subtype would otherwise be hand-entered or left at the Unknown default. A run-once admin tool (Tools > Mechanism Importer), built on the same pattern as the Subtype Importer, ingests the corrected dataset as JSON (paste or `.json` upload) and writes those five fields onto existing subtypes, diff-only, after a dry run. It is update-only and never creates a subtype: each record is matched by its `code` (the ACF subtype field, then exact title, then slug), and unmatched or invalid records are reported and skipped. The `new_call` value is mapped to the mechanism select key (LoF, GoF, Dominant-negative, Complex, Unknown), while flavor, confidence, prediction, and rationale are written verbatim and validated against the field enums; the dataset's bookkeeping keys are ignored. The dry run shows the exact before and after for every field so the write is reviewed before commit.
  - mu-plugins/eic-mechanism-importer.php

### Changed

- **Variant Mechanism: Single-Value Model Replaces the Two-Flag System (`subtype-fields.php`)**
  - The mechanism call had been derived from two ACF true/false flags, `lof_variant` and `gof_variant` (both on read as "Both," neither as "Unknown"), with `mechanism_source` holding a citation. That model could not express a dominant-negative or a complex mechanism, and it collapsed genuinely distinct calls into a single "Both." The two flags and `mechanism_source` are retired. In their place the subtype record carries a single `mechanism` select (Loss of Function, Dominant-Negative, Toxic Gain of Function, Complex, or Unknown, defaulting to Unknown so an uncurated record still renders), a `mechanism_flavor` select for the mechanistic basis (biallelic, haploinsufficiency, dosage, dominant-negative, neomorphic, overactivity, repeat-expansion, mixed, unresolved, no-gene), and a `mechanism_prediction` textarea. `mechanism_confidence` and `mechanism_rationale` are unchanged.
  - inc/acf/subtype-fields.php

- **Variant Mechanisms Table: Reads the Single Call, Five Facets, Basis / Prediction / Rationale (`variant-mechanism-table.php`)**
  - The `[variant_mechanism_table]` shortcode now reads the single `mechanism` value instead of deriving a call from the two flags, so the "Both" bucket is gone and Dominant-Negative and Complex are first-class options. The Mechanism facet offers all five calls, and each expanded row shows the mechanistic basis, then the prediction, then the rationale, with the Source line removed. The gene-symbol italic pass runs over both the prediction and the rationale.
  - inc/shortcodes/variant-mechanism-table.php

- **Subtype Page: Variant Mechanism Block Rebuilt (`subtype-fields-template.php`)**
  - The single subtype page's Variant Mechanism fact reads the single call and is gated on a curated `mechanism` value, so it stays hidden until a record is curated. The disclosure reads mechanistic basis, confidence, prediction, then rationale; the Source line is removed.
  - templates/subtype-fields-template.php

- **Subtype JSON-LD: Mechanism Sentence and Mechanistic Basis Property (`subtype-jsonld.php`)**
  - The MedicalCondition schema derives one mechanism sentence and one "Variant Mechanism" property from the single call, both omitted for Unknown so no mechanism is asserted without evidence, and adds a "Mechanistic Basis" property when the basis is set.
  - inc/acf/subtype-jsonld.php

- **Genes Database: Five-Value Mechanism Filter as an OR Facet (`genes-filter.php` and four others)**
  - The Genes DB "Variant Mechanism" filter replaced its two boolean checkboxes with five, one per call, filtering the single `mechanism` meta as an OR facet (selecting several widens the set) rather than the AND-of-booleans the gene-group flags use. Per-option counts are emitted through the existing flags channel so the filter markup and its facet-count repaint work unchanged; the only JavaScript edit adds the five `mech_*` keys to the URL-restore whitelist.
  - inc/content/filters/genes-filter.php
  - inc/content/filters/genes-facet-counts.php
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - inc/ajax/genes-loop-endpoints.php
  - inc/content/loops/genes-loop.php
  - assets/js/genes-ajax.js

- **Variant Mechanisms Table: Header Presence and Subtype De-emphasis (`variant-mechanism.css`)**
  - The header bar was thin (11px labels, 12px padding) and did not anchor the columns, and the Subtype column's bold navy competed with the italic teal Gene link for "what is this row." The header is now taller with 13px letter-spaced labels, and the Subtype cell drops to regular weight while keeping its `#174777` navy, so Gene leads and Subtype supports, matching this table's gene-first design. On the mobile card view, where the subtype is the card's title, it stays semibold.
  - assets/css/variant-mechanism.css

- **Genes DB Hero: Separate Mobile Fade and Editor Mask Preview, at Parity With the New Var Mech Hero (`genes-hero-fields.php`, `genes-hero-shortcode.php`, `genes-hero.css`)**
  - The Genes DB hero fade had been a single pair of Range sliders shared across every width, but the mobile crop reveals less of the image and needs the copy backed further. The hero now carries a second slider pair: the original sliders are relabeled "(Desktop)" and a new mobile start/end pair (defaulting to 55% / 100%) drives a mobile-only fade gradient. The shortcode emits the mobile values as their own CSS custom properties, and `genes-hero.css` adds the mobile `::after` that reads them. This brings the Genes DB hero to the same footing as the new Variant Mechanisms hero, which ships with the mobile fade and the live mask preview built in.
  - inc/acf/genes-hero-fields.php
  - inc/shortcodes/genes-hero-shortcode.php
  - assets/css/genes-hero.css

- **Variant Mechanism Browser Hero: Evergreen Stat Counts (`variant-mechanism-hero-shortcode.php`, `variant-mechanism-hero-fields.php`)**
  - The hero's classified-subtypes and resolved-mechanism stats read as exact counts, which drift out of date as calls are revised. The shortcode now appends a "+" to those two stats on output, matching the Genes DB hero, so the editor enters a plain number and the line renders as an evergreen floor ("170+ classified subtypes", "150+ with a resolved mechanism"). The mechanism-categories stat stays exact. The count fields remain numeric; the "+" is added template-side, which also resolves the earlier problem where typing "+" into a Number field saved an empty value.
  - inc/shortcodes/variant-mechanism-hero-shortcode.php
  - inc/acf/variant-mechanism-hero-fields.php

### Fixed

- **Variant Mechanisms: Filter and Header Pin and Release as One Sticky Unit (`variant-mechanism.css`, `variant-mechanism-table.php`)**
  - The filter card and the column header had been two independent sticky elements. Their sticky ranges differed (the header was bounded by the table, the filter by the whole section), so the resting gap between them collapsed on scroll and, at the bottom of the table, the header un-pinned first and tucked up behind the filter, losing the gap and its corners. Both now ride inside a single sticky wrapper, `.vmech-stick`, so they pin and release together: because the wrapper's containing block is the whole section, it stays pinned through the entire table and releases cleanly at the end, letting the page scroll on into the footer. The visible header is now a standalone table that shares the data table's `colgroup`, so its columns track the body automatically; the data table keeps a visually hidden `thead` for screen-reader column association. The wrapper's white background backs the gap between the two bars and the filter's rounded corner notches, replacing the former sticky-spacer element and its seam-pinning offsets.
  - assets/css/variant-mechanism.css
  - inc/shortcodes/variant-mechanism-table.php

- **Variant Mechanisms: Mobile Filter Checkbox Alignment on Wrapped Labels (`variant-mechanism.css`, `genes-filters.css`)**
  - At the narrowest widths a long facet label ("Toxic Gain of Function (GoF)") wraps to two lines, and the checkbox was vertically centered against the pair, floating in the middle. Both filter components now top-align the checkbox against the label's first line, with a one-pixel optical nudge.
  - assets/css/variant-mechanism.css
  - assets/css/genes-filters.css

- **Variant Mechanisms Table: Filtered Cards Now Hide on Mobile (`variant-mechanism.css`)**
  - In the stacked mobile card view the result count updated when a mechanism or confidence box was checked, but the filtered-out cards stayed on screen: an unguarded `.vmech-row { display: flex }` overrode the `hidden` attribute the filter sets. An explicit rule now keeps `[hidden]` rows hidden.
  - assets/css/variant-mechanism.css

- **Subtype Page: Mechanism Detail Label Spacing (`subtype-mechanism.css`)**
  - WordPress's auto-paragraph pass inserts a `<br>` after each block label in the mechanism disclosure, from the newline in the template markup, adding an empty second line between every label and its value. The block label already breaks to its own line, so that `<br>` is now suppressed and each label sits tight above its value.
  - assets/css/subtype-mechanism.css

- **Page Template: Removed a Stray `/header` Paragraph (`page.html`)**
  - A leftover block-editor paragraph, `<p>/header</p>`, was baked into the page template, most likely from typing `/header` to insert a template part via the slash command and having it land as literal text. It rendered "/header" in the page body on every page using this template, including once it shipped to production. Removed. If a Site Editor customization of the Page template overrides the file, clearing that customization reverts to the corrected file.
  - templates/page.html

- **Both Browser Tools: Mobile Dialing Pass (`variant-mechanism.css`, `variant-mechanism-hero.css`, `genes-hero.css`, `genes-filters.css`)**
  - A round of mobile adjustments shared across the Genes DB and Variant Mechanisms tools. Both hero titles are boosted past the global mobile font floor (`body * { font-size: 18px !important }`), which would otherwise flatten a heading to body size, so each title reads as a title on phones. The top two corners of both filter bars are squared at 600px and below so each filter meets its hero's flat bottom edge cleanly while the bottom corners stay rounded. A gap is opened between each filter and its first result. And the Variant Mechanisms tool now keeps its full-viewport breakout on phones, matching the Genes DB, instead of sitting inset at content width.
  - assets/css/variant-mechanism.css
  - assets/css/variant-mechanism-hero.css
  - assets/css/genes-hero.css
  - assets/css/genes-filters.css

- **Genes DB: Pagination Controls Wrap and Center (`main.css`)**
  - The Genes DB pager's Previous, page-number, and Next controls could run past their row and sit flush left on narrow widths. The pagination list is now a centered flex row that wraps, with non-shrinking items and non-wrapping labels, so the controls stay centered and legible at every width.
  - assets/css/main.css

- **Variant Mechanisms Table: Italicize Named Partner and Alias Genes (`variant-mechanism-table.php`)**
  - The gene-symbol italic pass covered each subtype's own causative gene but not the partner and alias genes named within a prediction or rationale, so a gene such as *MFN1*, cited beside *MFN2* in the CMT2A row, rendered upright. The curated extras list now also carries the secondary genes named in the prose (*MFN1*, *HDAC6*, *CHCHD2*, *CHCHD4* / *MIA40*, *VAC14*, *PIKFYVE*, *HSJ1*, and the *LITAF* alias *SIMPLE*), so every named human gene italicizes consistently in both the browser and the single subtype pages. Subtype codes, amino-acid substitutions, and protein or complex names are deliberately left roman.
  - inc/shortcodes/variant-mechanism-table.php

### Removed

- **Retired the Two-Flag Mechanism Admin Tools (`eic-lof-gof-tool.php`, `eic-mechanism-details-tool.php`)**
  - Both tools managed the retired two-flag model, and `eic-lof-gof-tool.php` re-wrote `lof_variant` / `gof_variant` on use. Superseded by the single-value model, which is edited in the standard subtype editor, both were moved out of the mu-plugins auto-load path so neither can re-introduce the old meta.
  - mu-plugins/eic-lof-gof-tool.php
  - mu-plugins/eic-mechanism-details-tool.php

## [3.0.0] - 2026-07-22

### Changed

- **Theme Migrated to a Safe Slug (`twentytwentyfive` : `expertsincmt`)**
  - The customized theme had been living in the bundled-default `twentytwentyfive` folder. Ionos/WordPress default-theme maintenance kept overwriting that folder and wiping the live customizations: the recurring "ghost" that broke the site, which even day-back restores could not reliably undo, because the folder name is what updaters key on. The theme is now forked to its own unique slug, `expertsincmt`, that no default-theme updater touches.
  - The Site Editor templates, template parts, and global styles were baked into theme files with Create Block Theme so the new slug is fully self-contained, and the six custom PHP template files the clone skipped (do-not-sell-modal, glossary-fields-template, header-banner, single-subtype, subtype-fields-template, and the section-dorsal-root part) were staged in by hand. style.css now carries a clean "Experts in CMT" header at Version 3.0.0; functions.php already resolves the version through `wp_get_theme()->get("Version")`, so nothing hardcodes it. Deploying to production is now a plain file upload with no database surgery, and the eic-shadow spare is a real working copy.
  - style.css
  - themes/twentytwentyfive : themes/expertsincmt (folder rename)

### Fixed

- **Genes Cards — Mobile "Rogue E" on the INHERITANCE Label (`genes-loop.css`)**
  - In the stacked mobile card layout each attribute label is a flex item in its row. The uppercase INHERITANCE label carries letter-spacing, and with nothing forbidding a wrap its final "E" stranded onto its own line in the narrow column. The label now holds its width and stays on one line (`white-space: nowrap`, `flex-shrink: 0`), so the value column absorbs the space instead; DISCOVERED is covered by the same rule. Scoped to `<=600px`, so the desktop three-column strip is untouched.
  - assets/css/genes-loop.css

- **Mobile Header — Rebuilt Without Magic-Number Lifts (`main.css`)**
  - Reported from the wild on a ~360px Android device: the header search box overlapped the "Charcot-Marie-Tooth Disease" tagline, and menu taps sometimes landed on the wrong page. Root cause was a mobile header held together by fixed offsets: the search was pulled up `margin-top: -175px` and the hamburger `translateY(-75px)`, both assuming one exact header height, so on a shorter/narrower device they overlapped visually and as tap targets. These never showed on the builder's own wider phone (iPhone 14 Pro Max, 430px), which is why it shipped as "good enough."
  - Replaced with a layout that cannot overlap at any height: logo top-left, hamburger absolutely pinned to the top-right corner and vertically centered against the logo band, and a full-width search on its own line. Empty Stackable spacer columns are hidden on mobile, the desktop-only 58px search baseline spacer is zeroed, and the top spacer is halved so the logo sits in line with the hamburger. Desktop layout is untouched.
  - assets/css/main.css

- **Mobile — Long URL Wrapping, Content Spacer Reduction (`main.css`)**
  - Raw lab-catalog URLs in body copy (e.g. Invitae/LabCorp test links) had no break points and ran off the content column on narrow screens, clipping and forcing horizontal scroll. Content links now wrap (`overflow-wrap: anywhere`) at all widths.
  - Fixed 50px / 100px content spacers around the related-section buttons and the Dorsal Root feature read as large empty voids on a phone; shrunk to 24px / 36px at `<=600px` only, leaving the small paragraph spacers and desktop spacing alone.
  - assets/css/main.css

- **Dorsal Root Feature — Mobile Reorder, Shorter Button, Tablet Centering (`section-dorsal-root.css`, `section-dorsal-root.php`)**
  - On mobile the section header put "The Dorsal Root" title and the "More" button in one row, crushing the title into three stacked words. The header wrapper is now collapsed (`display: contents`) and the pieces reordered to title, then cards, then button, so it reads as a proper feed. The button label was shortened to "More Dorsal Root" (global; it reads fine on desktop too).
  - In the two-column tablet range (680-1023px), a lone third card was orphaned in the left slot with dead space beside it. When the card count is odd, the last card now spans both columns and re-centers at single-column width, sitting centered under the pair above.
  - The mobile title carries `!important` so it escapes the global `body * { font-size: 18px !important }` mobile floor; without it the "The Dorsal Root" heading was being flattened to body size on phones.
  - assets/css/section-dorsal-root.css
  - templates/parts/section-dorsal-root.php

### Added

- **Admin Tool — Yoast Title Space Fix (`eic-yoast-title-tool.php`)**
  - New Tools page that removes a stray space before the question mark in the Yoast SEO title template stored per record. The subtype templates had been saved as `What Is %%title%% ? | Charcot-Marie-Tooth Disease | %%sitename%%`, rendering "What Is dHMN-2B ?" with a space before the mark; the tool collapses that to `%%title%%?`.
  - Operates on the raw `_yoast_wpseo_title` meta, collapsing any run of whitespace (including doubled spaces and non-breaking spaces) immediately before a `?` while leaving `%%title%%` and the other Yoast variables intact, so it edits the template safely rather than a rendered string. Records using Yoast's default (empty meta) are skipped.
  - Follows the shared EIC tool pattern: `manage_options`, nonce, per-type selector (default Subtype), a before/after dry run that highlights only the records that actually change, and a confirm-gated commit that writes just those records. Initial run cleaned 126 subtype records.
  - mu-plugins/eic-yoast-title-tool.php

- **Header Banner — Independent Mobile Fade Mask (`header-banner-fields.php`, `header-banner.php`, `header-banner.css`)**
  - The banner mask is now adjustable per breakpoint. Two new ACF Range fields, `banner_fade_start_mobile` / `banner_fade_end_mobile` (defaults 55/100), drive a mobile-only gradient, separate from the existing desktop `banner_fade_start` / `banner_fade_end` (33/66). The desktop fields were relabeled "(Desktop)" and the new ones "(Mobile)" for clarity; field names and keys are unchanged, so existing banner data needs no migration.
  - The `<=600px` `::after` mask previously carried hard-coded `55% / 100%` stops that deliberately ignored the ACF fade vars because the desktop numbers were tuned for the wider layout. It now reads `--banner-fade-start-mobile` / `--banner-fade-end-mobile`, output inline by the template alongside the desktop vars. The CSS fallbacks match the old hard-coded values, so no page's banner shifts until a mobile slider is actually moved.
  - inc/acf/header-banner-fields.php
  - templates/header-banner.php
  - assets/css/header-banner.css

- **Header Banner — Editor Mask Preview (`eic-banner-mask-preview.php`)**
  - New editor-only mu-plugin that renders the banner fade live on the ACF image thumbnail in the meta box, so the mask can be dialed in without a save-and-check loop. The overlay mirrors the front-end `::after` gradient (solid background from `0%` to Fade Start, fading to transparent by Fade End), with edge markers and percentage tags on the start and end stops.
  - Renders two stacked previews once the mobile fields exist: Desktop overlaid on ACF's real thumbnail, and Mobile as a tile pinned directly beneath it. The mobile tile measures the thumbnail's real left offset and width and shows the full image at natural aspect (not a cropped strip), so both previews present the same image at the same size and the mask is the only difference. Each tracks its own slider pair and updates live via an `input` listener, with a MutationObserver re-rendering on image change. Editor-only; emits no front-end output.
  - mu-plugins/eic-banner-mask-preview.php

- **Genes Database — Gene Group Checkbox Filters (Mitochondrial Involvement, ARS Genes, Unknown Gene)**
  - Added three checkbox facets to the Genes & Subtypes Database filter UI, driven by the existing ACF true/false fields `mitochondrial_involvement`, `ars_gene`, and `unknown_gene`. No new data model: the same flags already feed `subtype-jsonld.php`, the semantic ARS branch of platform search, and the totals line's "Unknown Gene" tally.
  - Emits `mito`, `ars`, and `unknown` GET params alongside the four taxonomy selectors, applied to both the page-load and AJAX query paths. State is carried to the shared fragment as a `genes_flags` query var, mirroring how `qs` already travels, so page load and AJAX resolve identically.
  - Flags are applied at the end of the fragment, after the search branch, because that branch reassigns `meta_query` wholesale. When a search is also active the search OR block is nested inside an AND with the flag block rather than replaced.
  - The flags AND against each other and against the selectors, for parity with the four dropdowns, which already AND. Checking Mito and ARS narrows to the intersection (currently CMT-DARS2, the sole mitochondrial ARS gene) rather than widening the set.
  - Exclusivity guard: `unknown_gene` is definitionally incompatible with `mitochondrial_involvement` and `ars_gene`, since a subtype with no identified causative gene cannot carry a mito or ARS gene, so any combination returns zero. The UI disables the opposing boxes rather than silently unchecking them (the constraint stays visible), and the query drops the conflicting flags independently so hand-edited URLs and no-JS loads are covered too.
  - Clean URLs required no change: `cleanParams()` passes unrecognized keys through, so `?mito=1&ars=1` survives sharing and reload.
  - inc/content/filters/genes-filter.php
  - inc/content/loops/genes-loop.php
  - inc/ajax/genes-loop-endpoints.php
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - assets/js/genes-ajax.js
  - assets/css/genes-filters.css

- **Genes & Dorsal Root — Filter-Aware Facet Counts**
  - Every filter control now shows how many results it would return under the rest of the current state: each Genes selector option and all three checkboxes, and each Dorsal Root category. Zero-count options are disabled (the current selection is never disabled, so it stays possible to leave it), extending the visible-dead-end pattern from the checkbox exclusivity guard to the whole filter set.
  - A facet's own selection is excluded when counting its own options, the standard faceted-search contract. Counting a dimension against itself would report the selected term's total and zero for every other option, stranding the user in their first pick; each dimension is therefore counted against all others.
  - Genes counting is a dedicated engine: one ID-only base query, one relationship query per taxonomy, one primed meta cache, then all set math in PHP, which at this catalog size beats a query per option. Dorsal Root, being a single-taxonomy filter, keeps its ~40-line counter inline in the filter file.
  - Counts render server-side on first paint and then ride along in the AJAX payload, so labels update in place without re-rendering the controls (which would drop focus). During an active search the selectors stop constraining the loop, and the counts mirror that rather than pretending the dropdowns still apply.
  - inc/content/filters/genes-facet-counts.php
  - inc/content/filters/genes-filter.php
  - inc/content/filters/dr-filter.php
  - inc/ajax/genes-loop-endpoints.php
  - inc/ajax/loop-endpoints.php
  - assets/js/genes-ajax.js
  - assets/js/dr-ajax.js

- **Interpost Return — Persisted Listing State (all five post types)**
  - The `[context_nav]` Return button now brings the user back to where they actually left the listing, not a reset archive view. Genes and Dorsal Root restore filter selection and pagination; Glossary restores its search term and pagination; What Is CMT and Breathing (no filters) restore scroll position so Return lands roughly where the user was.
  - A small session-scoped module records each listing page's current URL and scroll position in `sessionStorage`, keyed by post type. Because the AJAX clean-URL work already encodes filters, sort, pagination and search in the listing URL, restoring that URL restores all of it server-side; a one-shot flag restores scroll on arrival.
  - Deliberate scoping: state is per-tab and clears with the tab (nothing leaks between visits or visitors), expires after 30 minutes (a Return click from a tab left open overnight falls back to the default archive), and prev/next between single posts never overwrites the saved listing state (only listing pages record it), so walking through several entries still returns to the original entry point.
  - assets/js/return-state.js
  - inc/shortcodes/context-nav-shortcode.php
  - functions.php

- **Loop AJAX — Back/Forward History (Genes, Dorsal Root, Glossary)**
  - All three loop stacks now push a history entry on discrete filter actions (selector change, checkbox toggle, sort, pagination, submit, reset), so the browser Back and Forward buttons step through filter states instead of leaving the page entirely. Debounced typing still replaces rather than pushes, so a search term doesn't leave one history entry per keystroke, and a no-op change (re-selecting the same value) never stacks a duplicate.
  - Each stack gained a `popstate` handler that re-syncs the visible controls to the restored URL and refetches without writing a new entry. For Genes this also restores the three gene-group checkboxes and re-runs the exclusivity logic. Hydration was tightened so an absent param returns a selector to its "All" state, which is what makes Back actually clear a filter rather than strand it.
  - assets/js/genes-ajax.js
  - assets/js/dr-ajax.js
  - assets/js/glossary-ajax.js

- **About the Author shortcode (`[about_author]`)**
  - Moved the standing author block out of Code Snippets and into the theme as a version-controlled file, auto-loaded by the existing `inc/shortcodes/*.php` glob. Registered via an anonymous closure so it cannot collide with the database-stored snippet during changeover.
  - The block is now opt-in, placed per post as a Shortcode block rather than injected from the post template, so co-authored pieces that supply their own author section simply omit it.
  - inc/shortcodes/about-author-shortcode.php

- **Glossary Importer (`eic-glossary-importer.php`)**
  - New Tools page that imports authored glossary terms from JSON (single record, array, or `{"glossary":[...]}`), mirroring the Subtype Importer: dry-run then commit, paste box plus `.json` upload, cap-gated (`manage_options`) and nonce-protected, on the shared EIC admin shell with a red commit behind a backup checkbox.
  - Upserts by normalized `canonical_term`, reusing the glossary uniqueness guard's normalizer and also matching on title, so stale canonicals (e.g. a leftover "Auto Draft") can never cause a duplicate. Found terms update, new terms are created; safe to re-run.
  - Writes the term (title), definition (body), excerpt, and the ACF text fields (`canonical_term`, `short_definition`, `source_url`, `source_label`, `aka_synonyms`, `common_misspellings`, `banner_title`, `banner_intro`, `notes_admin`). Deliberately does not write `term_image` or `banner_image` (owned by their tools) and leaves the auto-synced `glossary_letter` taxonomy alone. An update payload that omits `definition`/`excerpt` never blanks live body content.
  - mu-plugins/eic-glossary-importer.php

- **Glossary Exporter (`eic-glossary-exporter.php`)**
  - New Tools page that exports every `glossary` term as a single lossless JSON file (core columns, full body and excerpt, all ACF and Yoast postmeta, taxonomies with term meta, and the Yoast indexable row), mirroring the Subtype Exporter. Cap-gated and nonce-protected, on the shared EIC admin shell.
  - mu-plugins/eic-glossary-exporter.php

- **CMT Glossary — Full Term Set + Voice Pass**
  - Expanded and standardized the CMT glossary to 75 terms (48 new, 27 existing revised) via the Glossary Importer. Each term carries a CMT-forward teaser `short_definition`, an inline plain-language pronunciation where warranted, a witty `banner_intro` tagline, curated synonyms and common misspellings, cross-links to related terms, and a source standardized onto durable institutional references (NHGRI genome.gov, MedlinePlus, NINDS, NCI, Merriam-Webster Medical) in place of rot-prone consumer links. EIC voice rules enforced throughout: CMT is only ever a "disease," never a "condition" or "disorder."
  - Fixed a factual error in the live Chromosome definition (a set of chromosomes had been described as "an allele").

- **Header Banner — Overlay Redesign (`header-banner.php`, `header-banner.css`)**
  - Site-wide header banner rebuilt from a two-column split-grid layout to an overlay pattern matching the Genes DB App Hero: image rendered as a CSS custom property (`--banner-img`) sitting behind the text as a background layer, rather than in its own `<img>`/grid column.
  - Live left-side fade implemented via `::after` gradient overlay, driven by two new ACF Range fields (`banner_fade_start`, `banner_fade_end`; defaults 33/66), admin-adjustable per page without re-editing the source image.
  - Radius applied to all four corners (`28px`), independent of the Genes DB App Hero's top-only radius, since the header banner isn't docked against another element.
  - Vertical centering achieved via `align-items: center` on the row-direction flex container (`.header-banner`) rather than a percentage-height chain, for reliable cross-browser behavior regardless of title/intro content length.
  - Intro copy (WYSIWYG field) intentionally left unclamped, flows naturally rather than truncating at a fixed line count, appropriate given the single-editor workflow on this site.
  - New dedicated stylesheet `assets/css/header-banner.css`, replacing five/six stacked, partially-conflicting `.header-banner` blocks previously spread across `main.css`.
  - inc/acf/header-banner-fields.php
  - templates/header-banner.php
  - assets/css/header-banner.css
  - functions.php (enqueue block, priority 1000, depends on `experts-main`)

- **Header Banner Fields — Migrated to PHP (`header-banner-fields.php`)**
  - Field group `group_6792eabbf10fc` migrated from ACF-UI/JSON registration to code-registered PHP, following the same pattern as `genes-hero-fields.php`.
  - All three original field keys (`banner_title`, `banner_image`, `banner_intro`) and the group key preserved exactly; existing content on Pages, Posts, subtypes, breathing, what-is-cmt, and glossary posts required no migration.
  - Two new Range fields added: `banner_fade_start` / `banner_fade_end` (defaults 33/66).
  - Legacy ACF-UI group and its `wp-content/acf-json` export removed to eliminate duplicate-key registration conflict.
  - inc/acf/header-banner-fields.php

- **Topic-Specific Hero Imagery**
  - Replaced the generic DNA/neuron header banner image on CMT and Breathing, The Dorsal Root, and the homepage with topic-specific imagery: diaphragm/phrenic nerve for CMT and Breathing, a nerve signal/impulse visualization for The Dorsal Root.
  - All hero images (homepage, general-purpose, CMT and Breathing, The Dorsal Root) unified under a shared light, clinical color palette (Photoshop Photo Filter: Cooling Filter 82, Soft Light blend, ~25–28% opacity) for visual consistency across pages previously running a darker, cooler palette.

- **Genes DB App Hero (`[genes_hero]`)**
  - New shortcode rendering an app-style hero above the Genes & Subtypes Database filter bar: background image, title, intro copy, and a three-item stats line.
  - Title renders as a plain `<h2>` with no custom styling override, inheriting the global heading rule. Avoids a duplicate `<h1>` on the page (the page's own `<h1>` continues to render via `header-banner.php`).
  - Live left-side fade implemented as a `::after` gradient overlay (background color fading to transparent), not a CSS `mask-image`. Driven by two new ACF Range fields (`genes_hero_fade_start`, `genes_hero_fade_end`) so the fade is admin-adjustable without re-editing the source image.
  - Stats line: `genes_hero_subtypes_count` and `genes_hero_genes_count` (ACF Number fields, each stat skipped entirely if left empty) plus a static third stat ("Affects 1 in 2,500 people") matching the epidemiology copy in `educational-jsonld.php` / `pages-jsonld.php` verbatim.
  - Filter bar overlaps the hero's bottom edge via a negative `margin-top`, anchored to true viewport center (`left: 50%` + `width: 100vw` + `translateX(-50%)`) rather than relying on parent container width, so it stays aligned regardless of the containing block's layout mode.
  - inc/acf/genes-hero-fields.php
  - inc/shortcodes/genes-hero-shortcode.php
  - assets/css/genes-hero.css
  - functions.php (enqueue block, self-contained, checks own shortcode/page context)

- **Genes Filter — Author Search**
  - Documented an existing fuzzy-match capability against the subtype's publication-author bibliography field: search now explicitly supports author name lookup (e.g. "Zuchner", "Shy") in addition to subtype, gene, and year of discovery.
  - Search label and placeholder text updated to reflect the added capability.
  - inc/content/filters/genes-filter.php

- **Dorsal Root Filter — Dedicated Stylesheet (`dr-filter.css`)**
  - New dedicated stylesheet bringing the Dorsal Root filter/search bar (`[dr_filter]`) visually in line with the Genes DB filter: bordered card, order-based flex row split (search + actions on row one, category select forced to row two via `order`), visually-hidden labels, matching input/button styling and `:focus` states.
  - Scoped entirely to `form.site-search[data-loop="dr"]` (and `.site-searchwrap:has(...)` for the outer wrapper) since `.site-searchwrap` / `.site-search__*` classes are shared with Glossary search and general Search; no changes made to `dr-filter.php` markup, no impact to the other two components.
  - No PHP changes required, existing `.site-search__field`, `.site-search__field--select`, `.site-search__field--input`, and `.site-search__actions` class hooks in `dr-filter.php` were already sufficient.
  - assets/css/dr-filter.css
  - functions.php (enqueue block, priority 1003, gated on `has_shortcode(..., "dr_filter")`)

- **Glossary Filter — Dedicated Stylesheet (`glossary-filter.css`)**
  - New dedicated stylesheet bringing the Glossary search bar (`[glossary_search_filter]`) visually in line with the Genes DB filter: bordered card, visually-hidden label, matching input/button styling and `:focus` states.
  - Scoped to `form.site-search[data-loop="gl"]`; no changes made to `glossary-search-filter.php` markup.
  - assets/css/glossary-filter.css
  - functions.php (enqueue block, priority 1004, gated on `has_shortcode(..., "glossary_search_filter")`)

- **Clean, Slug-Based AJAX URL Architecture (Genes, Dorsal Root, Glossary)**
  - New shared front-end helper `assets/js/loop-url-utils.js` (global `EICLoop`) that builds clean query strings: strips `per_page`, drops unselected/zero filters, removes a default `*_paged` value of `1` and a default/empty sort, and translates a select's `term_id` value into its taxonomy slug via a localized map. Enqueued before each `*-ajax.js` stack as a dependency, so all three stacks share one implementation.
  - New shared server resolver `inc/ajax/loop-tax-resolver.php`: `eic_resolve_tax_field()` resolves a filter value by slug when a matching term exists (correctly handling numeric slugs such as chromosome `10`) and falls back to `term_id` for legacy numeric links; `eic_build_tax_slug_map()` builds the `term_id` to `slug` map localized per loop, keyed by URL param name (so `dr_cat` can map to the `dorsal-root` taxonomy).
  - Filter URLs now read as, e.g., `?cmt_type=cmt1&inheritance=autosomal-dominant&neuropathy=demyelinating&chromosome=1` (previously `?cmt_type=6&inheritance=145&...&per_page=12`, with zeroed filters and `per_page` always present). POST payloads to the endpoints still carry raw `term_id` + `per_page`, so query logic is unchanged; only the visible URL is slugified.
  - Page-load hydration: each stack reads the URL on load, sets its select(s) from the slug/id, and normalizes a legacy numeric URL into the clean slug form. Endpoints and page-load resolvers accept slug-or-id, so a shared slug link filters correctly server-side with no unfiltered flash. Back-compat for existing numeric links is preserved.
  - Glossary receives the URL-cleanup pass only (its `alpha` value is already clean and it has no taxonomy `term_id`).
  - assets/js/loop-url-utils.js
  - inc/ajax/loop-tax-resolver.php
  - functions.php (shared-helper enqueue + per-loop `taxSlugs` localize)
  - assets/js/genes-ajax.js, inc/ajax/genes-loop-endpoints.php, inc/content/loops/genes-loop.php
  - assets/js/dr-ajax.js, inc/ajax/loop-endpoints.php, inc/content/loops/dr-posts.php
  - assets/js/glossary-ajax.js

- **Genes Loop - Subtype Card Redesign (`fragment-loop-genes-loop.php`, `genes-loop.css`)**
  - Card rebuilt into a structured reference layout: a type dot plus the subtype title, an optional `aka:` line (from `subtype_alias`), a Gene / Discovered / Inheritance attribute strip with hairline dividers (gene emphasized), and an Updated plus arrow footer. Replaces the prior gene-meta line and prose summary; the block-parsing first-sentence extractor was removed.
  - Type dot color: a 14-step brand-blue ramp derived from `--primary` (`#174777`) to `--primary-light` (`#5ea0c9`), mapped to the canonical `type_classification` order and keyed via a `data-cmt-type` attribute (title color unchanged), naming the dot's meaning without a legend.
  - Neuropathy pill (upper-right): the term's neuropathy type (`Axonal` / `Demyelinating` / `Intermediate`) as a filled pill on a light navy tint; the head wraps so the pill drops to its own right-aligned line on long `CMT-`gene titles instead of colliding.
  - Empty `year_of_discovery` / inheritance values render as "Unknown".
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - assets/css/genes-loop.css

- **Genes Totals Inline - Updated Date (`genes-totals-inline.php`)**
  - `[genes_totals_inline]` now emits a subordinate "Updated: {date}" line driven by the most recent published-subtype `post_modified` (self-maintaining), and absorbs the "Currently Indexed" caption into the shortcode so the whole stack renders in one place with a guaranteed order.
  - inc/shortcodes/genes-totals-inline.php
  - assets/css/main.css (inline-totals sizing + `.genes-totals-updated` subordinate treatment)

- **Glossary - Term Card Restyled to Match Genes (`glossary-loop.php`)**
  - Glossary term cards rebuilt on the shared `eic-subtype-card` visual language: a dot plus the term title, an `aka:` line (from `aka_synonyms`), the `short_definition` as the card body, and an Updated plus arrow footer, as a single full-card link. Drops the prior featured-image header, "Definition" read-more, and centered date.
  - `genes-loop.css` now also loads on the Glossary page (`cmt-words`) so the reused card classes are styled; its enqueue dependency relaxed to `experts-main` only.
  - inc/content/loops/glossary-loop.php
  - functions.php (genes-loop.css enqueue gating + dependency)

- **Dorsal Root - Editorial List Redesign (`dr-loop.css`, `dr-list-item.php`, `dr-category-color.php`)**
  - The `[dr_posts]` blog listing on `/dorsal-root` rebuilt from a 3-up grid into a single-column editorial list (image-left / text-right) in the canon type and color language: a category dot, a category pill, an uppercase date, a clamped excerpt, and a "READ" affordance with a hover arrow slide.
  - Row markup lives in one shared renderer (`eic_dr_render_list_item()`) used by both the page-load shortcode and the AJAX endpoint, so the two paths never drift.
  - Dynamic category color: each `dorsal-root` category is assigned a color from a curated pool by term order (`eic_dr_category_color()`), so a new category auto-assigns a distinct, on-brand color with no config. Each row outputs one `--dr-cat` custom property and the CSS derives the dot, pill tint, and pill text from it.
  - inc/content/loops/dr-category-color.php
  - inc/content/loops/dr-list-item.php
  - assets/css/dr-loop.css
  - inc/content/loops/dr-posts.php, inc/ajax/loop-endpoints.php (both render paths)

- **Component Stylesheet Glob Loader (`functions.php`)**
  - Every stylesheet in `assets/css` now auto-enqueues on the front end via a single glob loader (depending on `experts-main`), replacing the eight-plus individual per-file enqueue blocks. Dropping a new component sheet into `assets/css` needs no functions.php edit. Excludes `main.css` (loaded separately as `experts-main`) and `editor-style.css` (editor only); `platform-search.css` (outside `assets/css`) keeps its own enqueue.
  - functions.php

- **Dorsal Root Feature Section - Dedicated Stylesheet (`section-dorsal-root.css`)**
  - New stylesheet for the `[dorsal_root_section]` teaser (deployed above the footer across the platform), on its own `dr-feature` namespace so it is fully decoupled from the shared loop/card `.dr-*` classes. Includes a cohesive hover: a gentle image zoom paired with the card's bg/shadow lift, the image scaling within a fixed 16:9 `overflow:hidden` frame.
  - assets/css/section-dorsal-root.css

- **Dorsal Root Pagination - Shared Renderer (`dr-pagination.php`)**
  - New shared pager renderer `eic_dr_render_pagination()` producing the canonical `.wp-block-query-pagination` markup (Prev / numbered pages / current / Next). Both the page-load shortcode (`dr-posts.php`) and the AJAX endpoint (`loop-endpoints.php`) now call it, so the two paths emit identical pager markup and can no longer drift. Auto-loads via the loops glob; AJAX links preserve the active search, category, and sort.
  - inc/content/loops/dr-pagination.php
  - inc/content/loops/dr-posts.php, inc/ajax/loop-endpoints.php (both render paths)

- **EIC Admin Tools - Shared Branding + Header Shell (`eic-admin-tools.php`, `eic-admin-tools.css`)**
  - New shared admin layer for the custom utility tools under Tools. One loader (`eic-admin-tools.php`) enqueues a single stylesheet on any admin screen whose `page` slug starts with `eic-`, so every current tool is covered and any future `eic-` tool is picked up automatically, with no per-tool list and no effect on core wp-admin.
  - Shared header-shell helpers `eic_admin_tool_open()` / `eic_admin_tool_close()` give every tool the same branded opener: a navy "Experts in CMT / Site Tools" bar, a navy (`--primary` #174777) title, and a bordered card body. Replaces each tool's ad-hoc `<div class="wrap"><h1>` opener.
  - Branding brings the admin buttons off default WordPress admin-blue onto brand navy for primary actions, with red reserved for destructive actions (Commit/Apply in the Subtype Importer, Body Maintenance, and Subtype Maintenance). URL/backfill Commits and the read-only Subtype Export stay navy. All rules scoped under `.eic-tool` so nothing leaks outside a tool page. Albert Sans, navy code chips, and consistent inputs/notices/spacing round it out.
  - Applied across all 11 tools: Header Banner Image, Featured Image, ClinGen URL Builder, ClinVar URL Builder, Gene Name Backfill, OMIM Backfill, Schema Backfill, Body Maintenance, Subtype Importer, Subtype Maintenance, Subtype Export.
  - Dry-run button label shortened from "Run dry run (no writes)" to "Dry run (no writes)" across all eight tools that expose a dry run.
  - Subtype Importer now accepts a `.json` file upload alongside the paste box (form set to `multipart/form-data`). An uploaded file takes precedence over the textarea and is read only inside the existing nonce/capability check; because the textarea re-populates from it, the dry-run then commit flow still works without re-uploading.
  - mu-plugins/eic-admin-tools.php
  - mu-plugins/eic-admin-tools.css
  - mu-plugins/eic-banner-image-tool.php, eic-featured-image-tool.php, eic-clingen-url-tool.php, eic-clinvar-url-tool.php, eic-gene-name-tool.php, eic-omim-tool.php, eic-schema-backfill.php, eic-body-maintenance.php, eic-subtype-importer.php, eic-subtype-maintenance.php, eic-subtype-exporter.php

### Changed

- **Header Banner Fields: Location Rules Scoped to All Pages, Posts, and CPTs (`header-banner-fields.php`)**
  - The field group's location rules included standalone `post_template == default` and `page_template == default` OR groups, which match any post of any type on the default template, surfacing the banner fields on unintended CPTs and making the enumerated post-type rules redundant. Removed the front-page, posts-page, and default-template groups; the fields now show intentionally on all Pages, all Posts, and the `subtype`, `glossary`, `what-is-cmt`, and `breathing` CPTs.
  - inc/acf/header-banner-fields.php

- **Genes Filter: "Mitochondrial Involvement" Label Capitalization (`genes-filter.php`)**
  - Capitalized the gene-group checkbox label to "Mitochondrial Involvement", matching the title case of "ARS Genes" and "Unknown Gene". Applied to both the visible text and the `data-facet-label` the AJAX layer reads, so the capitalization survives a facet-count refresh.
  - inc/content/filters/genes-filter.php

- **Subtype Cards — Inheritance Title Case**
  - Inheritance values on the subtype cards now display in title case (e.g. "Autosomal Dominant", "X-Linked Recessive", "Mitochondrial Inheritance"). Presentation-only via CSS `text-transform: capitalize` scoped to a new `eic-subtype-card__inheritance` hook, so the stored lowercase taxonomy term and search are untouched. Capitalize also handles the hyphenated X-linked terms.
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - assets/css/genes-loop.css

- **The Dorsal Root — Landing Copy**
  - Subtitle updated to "Nerves talk. We listen." Body paragraph's closing descriptor changed from "always available" to "connecting with you," tying the copy to the dorsal root ganglion's function as a convergence point for sensory signal, and better matching the page's "we listen" framing than an access/uptime-flavored close.

- **Genes Filter — Layout Reorder (`genes-filter.php`)**
  - Search field and Browse/Reset actions moved above the four taxonomy selects (previously selects rendered first).
  - Dropdown placeholder option text standardized to "By All [Taxonomy]" across all four selects.
  - Search label/placeholder reordered subtype-first, matching the hierarchy used in the hero, totals line, and cards.
  - inc/content/filters/genes-filter.php

- **Genes Filter — Styling (`genes-filters.css`)**
  - Filter bar split into two explicit rows (search + actions, then four selects), forced via an `order`-based flex line-break rather than relying on natural wrap.
  - Four taxonomy selects changed from fixed-width to equal flex-grow, filling the row.
  - Filter wrapper and hero both anchored to true viewport center to resolve a parent-container width mismatch that had thrown off right-edge alignment between the two.
  - Top-right corner of the filter pill squared off (`border-radius: 28px 0 28px 28px`) as a deliberate aesthetic choice.
  - assets/css/genes-filters.css

- **Genes Loop — Card Typography (`genes-loop.css`)**
  - Subtype card title `font-weight` reduced from `700` to `400`.
  - Gene symbol (`.eic-subtype-card__gene`) `font-weight` adjusted to `500`.
  - assets/css/genes-loop.css

- **Genes Totals — State-Aware Label (`fragment-loop-genes-loop.php`, `main.css`)**
  - Totals line now prefixes with "Currently Curated:" when no filters or search are active, and "Results:" when a filter or search term has been applied.
  - Separator changed from bullet (`•`) to pipe (`|`) for visual consistency with the card meta line and hero stats line.
  - `.genes-totals` `font-weight` reduced from `600` to `400`; `font-size` clamp upper bound adjusted to accommodate the longer prefixed string on one line.
  - inc/content/loops/fragment-loop-genes-loop.php
  - assets/css/main.css

- **Subtype Taxonomy Registration — Disable Public Archives (`register-subtype-taxes.php`)**
  - Set `"public" => false`, `"publicly_queryable" => false`, and `"rewrite" => false`
    on all four subtype taxonomies (`cmt_type`, `inheritance`, `neuropathy`, `chromosome`).
  - Eliminates public-facing taxonomy archive routes that were generating bot-crawl
    404s and driving malformed Yoast breadcrumb ancestry on subtype pages.
  - Admin UI, ACF integration, and filter loop functionality are unaffected.
  - themes/twentytwentyfive/inc/taxonomies/register-subtype-taxes.php

- **Header Banner - Right-Aligned Title and Subtitle (`header-banner.php`, `header-banner.css`)**
  - The banner `<h1>` and its WYSIWYG intro now right-align to a shared right edge: the content box shrinks to the title's width so the title holds its original left position, while the intro's right edge lands under the end of the title (extending left as a single line). Multi-line titles right-align their own lines to that same edge.
  - Manual line breaks supported in `banner_title` via `<br>` (rendered through `wp_kses($title, ['br' => []])`), letting a long title split at a chosen point (e.g. `CMT Subtype and<br>Gene Database`); titles without the tag remain plain text and unaffected.
  - Mobile (`≤600px`) reverts to normal left-aligned stacked flow.
  - templates/header-banner.php
  - assets/css/header-banner.css

- **Genes Totals - Unknown-Gene Label Wording (`fragment-loop-genes-loop.php`)**
  - Trailing totals segment reworded to "{n} Subtype(s) with an Unknown Gene" (singular "an Unknown Gene" retained in the plural form), describing subtype records that lack a single causative gene rather than implying a count of distinct unknown genes.
  - inc/content/loops/partials/fragment-loop-genes-loop.php

- **Dorsal Root Feature Section - Namespaced + Title Weight (`section-dorsal-root.php`, `section-dorsal-root.css`)**
  - The teaser section's markup and CSS moved off the generic `.dr-wrap` / `.dr-card` / `.dr-grid` / `.dr-media` classes onto a self-contained `dr-feature` namespace (`.dr-feature__card`, `.dr-feature__media`, etc.), resolving a cascade collision where the section's `.dr-card` was being overridden by the loop's `.dr-card`.
  - Card title set to navy (`--primary`) at weight 500, matching the genes/glossary card voice instead of the previous dark bold.
  - templates/parts/section-dorsal-root.php
  - assets/css/section-dorsal-root.css

- **main.css - Dorsal Root Rule Cleanup (`main.css`)**
  - Removed the section-only `.dr-*` rules now owned by `section-dorsal-root.css` (`.dr-wrap`, `.dr-head`, `.dr-title`, `.dr-media`, `.dr-body`, `.dr-h`, `.dr-excerpt`, `.dr-grid.three-wide`). Kept the shared `.dr-grid` / `.dr-card` base still used by the genes and glossary grids.
  - assets/css/main.css

- **Loop AJAX Scripts - filemtime Cache-Busting (`functions.php`)**
  - The shared loop-URL utility and each loop's AJAX script now enqueue with a `filemtime()`-based version instead of a static `"1.0"`, so edits to the AJAX JS bust the browser cache automatically and no longer require a hard refresh.
  - functions.php

### Fixed

- **Genes Database: Mobile Card Attribute Strip Overflow (`genes-loop.css`)**
  - On phones the Gene / Discovered / Inheritance strip only stacked below 360px, so at typical widths (~390 to 430px) the three columns stayed in a row where the single-word labels ("DISCOVERED", "INHERITANCE") and long values ("Autosomal Dominant") collided and clipped past the card edge, which also visually clipped the neuropathy pill. Stacking now applies across the full mobile range (≤600px) as full-width label/value rows, so nothing overflows.
  - assets/css/genes-loop.css

- **Genes Database: Inheritance Value Title Case on Cards (`genes-loop.css`)**
  - The subtype card's inheritance value rendered the raw stored value, which is mixed-case across records ("autosomal dominant" vs "Autosomal Dominant"), so adjacent cards disagreed. Normalized the last-attribute value to Title Case via CSS, matching the documented inheritance-title-case intent; gene symbols and years are untouched so casings like PMP22 are preserved.
  - assets/css/genes-loop.css

- **Genes Totals: Mobile Size and Spacing (`main.css`)**
  - The totals line was pinned to `16px !important` while the mobile body copy is 18px, so it read as "tiny" against everything around it; it also sat cramped under the sort toolbar. Bumped it to 22px with slightly more weight, and opened the gap below it by increasing the line's relative lift so the spacing above and below balances.
  - assets/css/main.css

- **Site Header: Mobile Search Input and Button Alignment (`main.css`)**
  - In the stacked mobile header the Site Search input and the SEARCH button sized differently (the submit button is styled by the global button rule), so their right edges did not line up inside the 150px search block. Pinned both to `width: 100%` and `box-sizing: border-box` so their left and right edges align.
  - assets/css/main.css

- **Back-to-Top Button: Mobile Footprint (`main.css`)**
  - The floating button covered more content than necessary while scrolling on phones. Shrunk it and tucked it tighter into the corner on mobile, keeping the spacing that clears the browser's bottom toolbar.
  - assets/css/main.css

- **Dorsal Root Filter: Mobile Hollow Gap (`dr-filter.css`)**
  - The field wrappers use `flex: 1 1 320px`/`1 1 0`; when the row flips to a column on mobile, that basis applied to HEIGHT, inflating each field and leaving a large hollow gap in the filter card. Reset the field flex to size-to-content on mobile, so SEARCH/RESET sit right below the input.
  - assets/css/dr-filter.css

- **Dorsal Root Blog: Featured Image Frame Fill (`dr-loop.css`)**
  - The media frame uses a fixed `16/10` aspect ratio, but WordPress's width/height thumbnail attributes plus its global `img { height: auto }` left the image shorter than the frame on iOS, showing a light strip below it. Absolutely-pinned the image (and placeholder) to the frame's edges so it always fills the box. Corrects desktop as well.
  - assets/css/dr-loop.css

- **Header Banner: Mobile Text Readability (`header-banner.css`)**
  - On mobile the text container had been opened to full width (the desktop layout caps it to the left ~55% readable zone), so the intro ran across the banner and over the DNA image on the right, where the fade had gone transparent, leaving it unreadable. Capped the text to the left readable zone and widened the fade's solid backdrop (solid through the text, then a gentle reveal across the right half), so the copy reads on flat colour while the image still shows.
  - assets/css/header-banner.css

- **Genes Database: Canonical Sort Overridden by Debug Clauses (`fragment-loop-genes-loop.php`, `genes-loop.php`)**
  - The fragment attached a second `posts_clauses` callback (`eic_genes_custom_sort_clauses`, self-labeled "debug", empties-first) on the same query the canonical sorter (`eic_genes_type_ordering_clauses`, empties-last) already ordered. Both overwrote `orderby` at priority 10, and the fragment's was added later, so it won: subtypes with an empty `type_classification` sorted to the top of the database instead of the bottom, plus a redundant JOIN was emitted.
  - Removed the debug add/remove block from the fragment; setting the `eic_genes_custom_sort` query var now triggers only the globally-registered canonical sorter in `genes-type-order.php`, the single source of truth for Genes ordering. The orphaned debug function was removed from `genes-loop.php`.
  - inc/content/loops/partials/fragment-loop-genes-loop.php
  - inc/content/loops/genes-loop.php

- **Dorsal Root Filter: Category Revert + Slug-URL Highlight (`dr-filter.php`)**
  - The hidden-input preservation loop excluded only `qs`/`dr_paged`/`dr_sort`, so when the URL already carried `dr_cat` a stale hidden `dr_cat` was emitted alongside the live select. On a native (no-JS) submit PHP kept the last duplicate, so changing category and pressing Search silently reverted to the old category; under AJAX the stale value could leak into the history URL. `dr_cat` is now excluded from the preserved inputs.
  - The select's active-option highlight cast `dr_cat` with `(int)`, which turned any slug-form URL (what dr-ajax.js writes) into `0`, so a shared clean URL rendered "All Categories" until JS rehydrated. `dr_cat` is now resolved via `eic_resolve_tax_field()` to a term_id (slug or legacy numeric) before driving the select, so the right category shows on first paint, including for no-JS visitors.
  - inc/content/filters/dr-filter.php

- **Glossary Loop: AJAX Pagination + Alpha-Change Search Loss (`glossary-ajax.js`, `glossary-loop.php`)**
  - Pagination bound clicks on `.genes-pagination`, but the glossary pager renders as `.wp-block-query-pagination`, so every page click fell back to a full navigation; the handler also used `{once: true}`, so a click on a non-link part of the pager consumed the listener. Pagination is now delegated off the results root (surviving result swaps without rebinding) and matched to `.wp-block-query-pagination`, with the `{once}` removed.
  - Changing the alpha letter built params from the sort form only, silently discarding an active `qs` search (the input still showed it); the no-JS fallback had the same hole. A shared `overlayControls()` helper now mirrors the live search text and alpha letter into every glossary action (live controls win over stale source params), and the sort form emits a hidden `qs` input when a search is active for no-JS parity.
  - assets/js/glossary-ajax.js
  - inc/content/loops/glossary-loop.php

- **Dorsal Root Loop: AJAX State Loss + Search Parity (`dr-ajax.js`, `loop-endpoints.php`, `dr-posts.php`)**
  - `paramsFromForm()` read only the search form, which carries neither `dr_sort` (separate toolbar) nor `dr_paged` (address bar only), so a shared/bookmarked URL was rewritten without them on load, popstate refetched page 1 at default sort, and submitting dropped an active sort. It now folds in `dr_sort` from the sort select and the current `dr_paged`, while callers that intend to reset paging still delete it explicitly.
  - The AJAX endpoint used native `s` with an AND `tax_query` while the page-load shortcode used a union of text and term-name matches intersected with category, so the first AJAX interaction could change the result set for the same URL, and the union-based facet counts could contradict the AND-based list. The union/intersection logic is now extracted into a shared `eic_dr_apply_search_filters()` that both paths call, so results and facet counts stay in sync. The endpoint also gained `post_status => publish` (private posts no longer surfaced to logged-in users) and a `per_page` clamp (`min(48, ...)`).
  - assets/js/dr-ajax.js
  - inc/ajax/loop-endpoints.php
  - inc/content/loops/dr-posts.php

- **Context Nav: Inverted Prev/Next Order on Dorsal Root Posts (`context-nav-shortcode.php`)**
  - The Dorsal Root prev/next query passed `"order" => "DSC"`, an invalid value WordPress silently coerces to DESC, contradicting the documented "publish date ASC (oldest to newest)" intent and inverting the walk order. Corrected to `"ASC"`.
  - inc/shortcodes/context-nav-shortcode.php

- **Genes Loop AJAX Endpoint: Dead Search `meta_query` (`genes-loop-endpoints.php`)**
  - The endpoint built an `$args['meta_query']` for the search term, but the fragment reassigns `meta_query` wholesale whenever a search is present (the same condition), so the endpoint's block was always overwritten and its field list had drifted from the fragment's real search fields. Removed the dead block; the fragment is the single source of truth for search fields, with `$search` still passed through via the `qs` query var.
  - inc/ajax/genes-loop-endpoints.php

- **Genes Filter: Fallback Page Slug (`genes-filter.php`)**
  - When `get_permalink()` returned false, the form action and RESET link fell back to the slug `genes` / `home_url("/genes/")`, but the real page slug is `cmt-genetics-database`, so the fallback pointed at a non-existent page. Corrected the fallback to `cmt-genetics-database`.
  - inc/content/filters/genes-filter.php

- **Genes Loop: Pagination Filter Loss During AJAX (`fragment-loop-genes-loop.php`)**
  - Pagination links were built from `$_GET`, which is empty during an AJAX POST, so injected page links dropped the active filters (harmless in-session, since the JS rebuilds params from form state, but a link copied out of the injected DOM lost its filters). Links now build from the effective request (`$_GET` on page load, `$_POST` during AJAX), stripping the AJAX plumbing (`action`, `nonce`, `per_page`, paged keys).
  - inc/content/loops/partials/fragment-loop-genes-loop.php

- **Featured Admin Column: Sort Dropped Unfeatured Posts (`functions.php`)**
  - Sorting the post list by the Featured column used a `meta_key` orderby, whose INNER JOIN on `postmeta` silently excluded every post lacking `_is_featured`. Replaced with an OR `meta_query` (`EXISTS` / `NOT EXISTS`) so the sort LEFT JOINs and keeps all posts, featured first with a date tiebreak.
  - functions.php

- **Loop Empty-State: Duplicate and Inconsistent IDs (`dr-posts.php`, `loop-endpoints.php`, `glossary-loop.php`)**
  - The DR empty-state ID differed between the server render (`genes-no-results`) and the AJAX render (`dr-no-results`), and the glossary reused `genes-no-results` too (duplicate-ID risk on a page, and anything targeting the ID behaving differently after a swap). Standardized: DR uses `dr-no-results` on both paths; glossary uses its own `glossary-no-results`.
  - inc/content/loops/dr-posts.php
  - inc/ajax/loop-endpoints.php
  - inc/content/loops/glossary-loop.php

- **Section Dorsal Root: Missing `ABSPATH` Guard (`section-dorsal-root.php`)**
  - The homepage feature partial was the only file in the set without a direct-access guard. Added the standard `if (!defined("ABSPATH")) exit;`.
  - templates/parts/section-dorsal-root.php

- **main.css: Invalid Values and Dead Duplicates**
  - Closed an unterminated comment that was swallowing the entire mobile font-size `@media` block (it had never applied since the comment broke, and was only accidentally terminated by the next section's close).
  - `padding-top: -10px !important` (invalid negative padding, silently dropped) corrected to `0`; a duplicate `background` declaration on the mobile hamburger resolved (keeping the gradient that draws the center bar); both deprecated `word-break: break-word` uses replaced (`overflow-wrap`); and a byte-for-byte duplicate of the Stackable CTA block removed.
  - The misleading global-link-style comment was corrected to describe the intended site-wide behavior (underline plus hover scale on every anchor).
  - assets/css/main.css

- **Admin Image + OMIM Tools: Dry-Run Ignored Selected Scope (`eic-banner-image-tool.php`, `eic-featured-image-tool.php`, `eic-omim-tool.php`)**
  - Each tool's `do_dryrun()` previewed "differs" logic unconditionally, ignoring the chosen scope, so a dry run under scope `all` or `empty` reported a count that did not match what Commit would write, defeating the preview. `do_dryrun()` now takes the scope and gates preview rows through the same `want()` logic the commit uses.
  - mu-plugins/eic-banner-image-tool.php
  - mu-plugins/eic-featured-image-tool.php
  - mu-plugins/eic-omim-tool.php

- **Body Maintenance: NCV Exemption + HTML-Aware Term Rewrite (`eic-body-maintenance.php`)**
  - The NCV-frame flag-only check exempted any sentence containing "process", yet the tool's stated purpose is to catch invented phrasings "like 'an axonal process'", so the one example it documents was the one it silently ignored. Removed the "process" exemption.
  - `term_fix()` (and its matching `term_scan()` preview) ran over raw `post_content` with no HTML awareness, so a rule token inside an href, alt text, or attribute would be rewritten. Both now operate only on text between HTML tags, leaving tags and URLs untouched.
  - mu-plugins/eic-body-maintenance.php

- **ClinVar + ClinGen Tools: HGNC Cache Short-Circuit (`eic-clinvar-url-tool.php`, `eic-clingen-url-tool.php`)**
  - `hgnc_symbol()` short-circuited on any existing cache entry, even one written by another tool without an `approved` key (the tools share `HGNC_OPTION`), so symbol normalization could silently no-op depending on tool run order. Both now short-circuit only when the entry carries the `approved` key, otherwise falling through to the live fetch.
  - mu-plugins/eic-clinvar-url-tool.php
  - mu-plugins/eic-clingen-url-tool.php

- **Subtype Template — Gene OMIM "Word Salad" on Unknown-Gene Subtypes (`subtype-fields-template.php`)**
  - On a subtype whose gene is unknown, the gene OMIM block read its label from `$gene_symbol`, which is reassigned to the placeholder "Gene is Unknown at This Time", producing "Gene is Unknown at This Time OMIM Entry" with a redundant "No Entry" button. The block is now suppressed entirely when `unknown_gene` is set, since a gene OMIM entry is meaningless without a gene; the subtype OMIM entry still renders. (ClinGen already self-suppressed via an empty `clingen_url`, so it needed no change.)
  - templates/subtype-fields-template.php

- **Subtype Taxonomies — Chromosome Seeder Version Gate (`register-subtype-taxes.php`)**
  - The term seeder was gated behind a one-time `subtype_taxonomies_seeded_v4` option, so terms added to the arrays after the initial seed (notably the mitochondrial `MT` chromosome) were never created. Removed the version gate; the seeder now runs every load, staying idempotent via `term_exists()`, so any term added to the arrays appears on the next reload and receives its numeric `sort` meta. `MT` added to the chromosome `terms` and `order` arrays. (Fix predated this cycle but had not been migrated to production; deployed now.)
  - inc/taxonomies/register-subtype-taxes.php

- **Dorsal Root Pagination - AJAX Swap + Scroll to `#blog` (`dr-ajax.js`)**
  - Page-number clicks now AJAX-swap the results in place (delegated on the results root, so they survive result swaps without rebinding) and update the URL to a clean `?dr_paged=N`, instead of full-navigating. After the swap the view scrolls to the on-page `#blog` anchor. With JS off, clicks still full-navigate to `#blog` via the link hrefs.
  - `focusResults()` gained a `focus` option; pagination passes `{ scroll: false, focus: false }` so it no longer moves focus/scroll to `#results` and overshoots the `#blog` target.
  - assets/js/dr-ajax.js

- **Dorsal Root Pagination - Unstyled Pager After AJAX Swap (`loop-endpoints.php`, `dr-posts.php`)**
  - AJAX-loaded pages had lost their pager styling: the endpoint rendered `paginate_links()` inside a `.dr-pagination` div the CSS did not target, while the initial page render used `.wp-block-query-pagination`. Both paths now share `eic_dr_render_pagination()`, emitting identical markup so the pager keeps its styling on every page.
  - inc/ajax/loop-endpoints.php
  - inc/content/loops/dr-posts.php

- **Dorsal Root Filter — Focus Ring Specificity Conflict (`main.css`)**
  - Removed a legacy DR-specific override block (`.site-searchwrap form.site-search[data-loop="dr"] .site-search__select { ... }`) that predated the dedicated stylesheet migration. Its class-based selector carried higher specificity than the new `dr-filter.css` rules, silently pinning the category select's border to grey and suppressing the `:focus` blue ring regardless of state.
  - All properties from the removed block (height, padding, border, radius, background, custom arrow) are now sourced from `dr-filter.css`, using genes-filter's exact values rather than the old block's DR-specific ones (height changed `46px` → `48px`; border `1.5px` → `1px`; padding widened for arrow clearance).
  - assets/css/main.css

- **Genes Loop — SMA-LEP Sort Key (`genes-loop.php`, `genes-type-order.php`)**
  - Corrected the sort key for the SMA-LEP subtype (`SMA-LEP` → `smalep`), fixing incorrect placement in type-ordered listings. Also corrected CMT4/CMTX ordering. Deferred from the bulk subtype importer prototyping work; both files carried the same stale key.
  - inc/content/loops/genes-loop.php
  - inc/content/sort/genes-type-order.php

- **Genes Loop — Card Summary First-Sentence Extraction (`fragment-loop-genes-loop.php`)**
  - Card summary extractor now skips the leading "What Is…?" heading present in imported subtype bodies, pulling the actual answer sentence instead of the heading text. Also deferred from the bulk importer prototyping work.
  - Gene name emphasis corrected to stay roman (non-italic) inside hyphenated subtype names, rather than italicizing the full hyphenated string.
  - inc/content/loops/partials/fragment-loop-genes-loop.php

- **Loop Filters - Numeric-Slug Resolution (`loop-tax-resolver.php`)**
  - Chromosome, and any taxonomy whose slugs are numeric (e.g. `10`), previously risked being misread as a `term_id` on a cold load of a shared slug URL. The resolver now prefers an actual slug match first and only falls back to `term_id` when no slug exists, fixing chromosome slug URLs while preserving legacy numeric back-compat.
  - inc/ajax/loop-tax-resolver.php

- **Genes Loop - Card Overflow on Very Narrow Viewports (`genes-loop.css`)**
  - At `≤360px` the Gene / Discovered / Inheritance attribute strip stacks into full-width rows with horizontal hairlines instead of three columns, eliminating the horizontal overflow and clipping that occurred below roughly `320px`. Viewports `375px` and up are unchanged.
  - assets/css/genes-loop.css

- **Dorsal Root Feature Section - Shared `.dr-more` CTA (`main.css`, `section-dorsal-root.css`)**
  - `.dr-more` turned out to be a shared CTA button (glossary source links, OMIM "no entry" buttons, fact boxes, form buttons), not section-only, so its base rule was restored in `main.css` after the section cleanup. The section's own button was renamed to `.dr-feature__more`, which re-adds the global underline-on-hover exemption that had been keyed to `.dr-more`.
  - assets/css/main.css
  - assets/css/section-dorsal-root.css

### Removed

- **Context Nav: Retired `resource` CPT Handling (`context-nav-shortcode.php`)**
  - Removed the `resource` post type from the supported-types list, its label set, and its back-URL branch. The `resource` CPT no longer exists, so the handling was dead. (The corresponding `return-state.js` archive map already had no `resource` entry, so it needed no change.)
  - inc/shortcodes/context-nav-shortcode.php

- **Glossary Loop: Unused Fallback-Image Closure (`glossary-loop.php`)**
  - Removed the `$get_fallback_img` closure, defined but never invoked, so glossary cards rendered no image. There is no image to render on the card any longer, so it was dead code.
  - inc/content/loops/glossary-loop.php

- **Genes Filters Stylesheet: Dead `.genesdb-filter-wrap .genes-sort*` Block (`genes-filters.css`)**
  - Removed a scoped sort-UI block that never matched: the sort toolbar (`.genes-sort--results`) is emitted by the separate `[genes_loop]` shortcode outside `.genesdb-filter-wrap`, and is styled by the unscoped `.genes-sort--results` rules in `main.css`. A note was left in place of the block.
  - assets/css/genes-filters.css

- **SSO Auto-Login mu-plugin (`sso.php`)**
  - Removed the stock Newfold/Bluehost `sso.php` mu-plugin, an unauthenticated `admin-ajax` endpoint (`sso-check`) that logged a user in (defaulting to the first administrator) whenever a request's `nonce`+`salt` hashed to a host-set `sso_token` option. Vestigial on Ionos, which does not use it. The `sso_token` option was confirmed absent from the production database, so the endpoint was already inert; removing the file prevents it being re-armed if a token were ever written again.

- **Force Theme File Editor mu-plugin (`force-theme-file-editor.php`)**
  - Removed a local-development-only mu-plugin that re-surfaced the Appearance to Theme File Editor, which allows arbitrary PHP execution for any `edit_themes` user. Its own header warned it must not reach staging or production; removed so it cannot.

- **Author Block toggle ACF field (`inc/acf/author-block-fields.php`)**
  - Removed the `suppress_auto_author` true/false field. The `[about_author]` block is now opt-in, placed per post as a Shortcode block, so there is no auto-injected block left to suppress and the toggle had nothing to act on. The `about_author` shortcode itself (Code Snippets) is unaffected.
  - inc/acf/author-block-fields.php

### Maintenance

- **Pre-Deploy QC: Lint + Whitespace Normalization**
  - Full pre-rollout QC pass over the changed file set (theme plus mu-plugins): every PHP file passes `php -l`, every JS file passes `node --check`, and CSS passes stylelint with no value, property, or deprecation errors and balanced braces/comments.
  - Normalized whitespace across the touched files: stripped trailing whitespace, collapsed runs of three or more blank lines, and enforced exactly one end-of-file newline. `genes-loop.css` was converted from CRLF to LF to match the rest of the repository, and its duplicate `:root` and superseded `.eic-subtype-card__footer` block were merged.
  - Removed a dead `loop_no_results` unhook in `functions.php` (TT25 registers no such action or `twentytwentyfive_no_results` callback, so it did nothing; empty-state hiding is handled by CSS).

- **Export/Import Tools: Round-Trip Documentation Notes**
  - Added a note to the glossary and subtype exporter/importer pairs clarifying that exports are a lossless backup shape (post columns, raw meta, taxonomies, Yoast row) and importers expect the authored shape, so export files restore via the database rather than through the importer. Formats are intentionally different; behavior unchanged.
  - mu-plugins/eic-glossary-exporter.php, eic-glossary-importer.php, eic-subtype-exporter.php, eic-subtype-importer.php

- **Deferred: main.css Duplicate-Selector Cleanup**
  - The remaining `no-duplicate-selectors` warnings in `main.css` (`.dr-card`, `.dr-grid`, `:root`, and the `.eic-fact`/`.eic-subtype-fields` blocks) are intentional cascade layering: the current rendering is correct because later rules override earlier ones in place. Deferred to a dedicated session, since deduplicating means recomputing the full cascade and risks changing the working layout. No change shipped beyond the value-bug fixes noted under Fixed.

- **Database Cleanup — Legacy Domain Migration Artifacts**
  - Removed stale `staging_config` option from `kvu1_options` and `6CV_options`,
    left over from Installatron migration records.
  - Updated 39 rows in `kvu1_yoast_indexable` where `permalink` contained the
    legacy staging domain (`expertsincmt-wu8cfkwyb0.live-website.com`), replacing
    all instances with `https://expertsincmt.org`.
  - Corrected `kvu1_yoast_indexable` row 18 (`post-type-archive` / `subtype`),
    which was storing the legacy `cmtgenes.com` domain as the subtype archive
    permalink. Updated to `https://expertsincmt.org/subtype/`.
  - Root cause: database search-replace during migration to Ionos did not target
    `kvu1_yoast_indexable` and operated against the wrong table prefix (`6CV_`
    instead of `kvu1_`), leaving all Yoast indexable records on legacy domains.
  - Resolved downstream effects: malformed breadcrumb `@id` values in Yoast
    WebPage schema on subtype pages, legacy domain appearing in social URL
    previews on mobile, and bot-crawl 404s sourced from stale permalink records.

---
## [2.1.0] - 2026-05-04

### Added

- **Pages JSON-LD Schema (`pages-jsonld.php`)**
  - New file: injects `MedicalCondition` and `MedicalWebPage` JSON-LD blocks in `<head>` for WordPress Pages.
  - Fires only when at least one Medical Specialty is selected, excluding non-medical pages from schema injection entirely.
  - Specialty output is always an array of `MedicalSpecialty` nodes, supporting multi-select.
  - `about` node points to Charcot-Marie-Tooth disease as the `MedicalCondition`.
  - Supports audience, aspect, lastReviewed, reviewedBy, publisher, isPartOf, and mentions.
  - Mentions whitelist consistent with existing CPT JSON-LD files: `/subtype/`, `/glossary/`, `/what-is-cmt/`, `/cmt-and-breathing/`.
  - Added `code` (`MedicalCode` / OMIM) and `associatedAnatomy` (`AnatomicalStructure` / Peripheral nervous system) to the `MedicalCondition` block for consistency with the subtype schema.
  - inc/acf/pages-jsonld.php

- **Medical Specialty Checkbox Field — Subtype, What Is CMT, CMT and Breathing**
  - Added `medical_specialty` checkbox field to the Schema Markup tab in `subtype-fields.php`, `what-is-cmt-fields.php`, and `cmt-and-breathing-fields.php`.
  - Field renders full width above all other Schema Markup fields on all three CPTs.
  - Choices match the `pages-fields.php` specialty set for consistency across all content types.
  - `subtype-fields.php`: field exempted from the global 33% width normalization loop via `_keep_wrapper` flag; ACF ignores unknown keys.
  - inc/acf/subtype-fields.php
  - inc/acf/what-is-cmt-fields.php
  - inc/acf/cmt-and-breathing-fields.php

- **Standalone `MedicalCondition` Block — Educational CPTs (`educational-jsonld.php`)**
  - Added Block 1 `MedicalCondition` output before the existing `MedicalWebPage` block for What Is CMT and CMT and Breathing CPT posts.
  - Canonical URL always points to `/what-is-cmt/` as the disease entity anchor, regardless of which CPT the post belongs to.
  - Added `is_page()` guard to prevent the file from firing on WordPress pages.
  - Replaced hardcoded `"Neurology"` specialty with dynamic read from `medical_specialty` ACF field; falls back to `Neurologic` when unpopulated.
  - Normalized `about` URL to `/what-is-cmt/` — removed post-type conditional that previously pointed breathing posts to `/cmt-and-breathing/`.
  - Added `code` (`MedicalCode` / OMIM) and `associatedAnatomy` (`AnatomicalStructure` / Peripheral nervous system) to the `MedicalCondition` block for consistency with the subtype schema.
  - inc/acf/educational-jsonld.php

### Changed

- **Subtype JSON-LD — Dynamic Specialty + Unknown Gene Node (`subtype-jsonld.php`)**
  - Replaced hardcoded `"Neurology"` specialty node in the `MedicalWebPage` block with a dynamic read from the `medical_specialty` ACF field.
  - Output is always an array of `MedicalSpecialty` nodes, consistent with all other JSON-LD files.
  - Falls back to `Neurologic` for subtypes with no specialty selection saved.
  - Added `elseif ($unknown_gene)` branch to the `additionalProperty` block: outputs `"Gene unknown at this time"` as the `Associated Gene Symbol` `PropertyValue` node when the Unknown Gene flag is checked; gene symbol, full name, and alias nodes are suppressed in that state.
  - inc/acf/subtype-jsonld.php

### Fixed

- **CMT and Breathing Fields — Key Typo (`cmt-and-breathing-fields.php`)**
  - Corrected mismatched ACF field key on `reviewed_by_name`: was `field_eic_wic_reviewed_by_name` (copied from What Is CMT), now correctly `field_eic_breathing_reviewed_by_name`.
  - inc/acf/cmt-and-breathing-fields.php

---

## [2.0.9] - 2026-03-13

### Fixed
- Closed CSRF vulnerability in Genes AJAX endpoint by enforcing nonce validation.
  - themes/twentytwentyfive/inc/ajax/genes-loop-endpoints.php

- Replaced timing-unsafe token comparison in SSO authentication with constant-time verification.
  - mu-plugins/sso.php

- Sanitized `$bounce` redirect parameter in SSO authentication flow.
  - mu-plugins/sso.php

- Restored IPv6 rate-limiting by replacing incorrect `REMOTE_ADDR` handling with validated IP detection and hashed transient keys.
  - mu-plugins/sso.php

### Performance
- Increased subtype ID transient cache TTL from 60 seconds to `DAY_IN_SECONDS`.
  - mu-plugins/cmtgenes-helpers.php

### Maintenance
- Consolidated ACF JSON path registration into MU-plugin loader to prevent conflicting save locations.
  - mu-plugins/acf-json-loader.php
  - mu-plugins/acf-admin-stability.php

## [2.0.0] - 2026-02-16

### Added

- **ClinGen Gene Curation Integration**
  - Added direct ClinGen gene curation links across the Genetics Database where authoritative curation exists.
  - Subtype pages now conditionally render ClinGen links alongside existing ClinVar references.
  - External links resolve to gene-specific ClinGen curation entries.
  - Rendering logic suppresses the link when no valid curation record is present.

- **Platform Search Production Lock**
  - Finalized deterministic if/else intent tree.
  - Implemented full no-results handling with conditional heading replacement.
  - Added structured two-line no-results messaging with independent styling classes.
  - Confirmed canonical bucket ordering across Subtypes, Types, Genes, and Content.

- **Semantic Variable Expansion**
  - Added chromosome-based semantic resolution.
  - Added inheritance-pattern semantic handling with noise tolerance.
  - Implemented slash normalization for subtype variables (e.g., 1F/2E).
  - Anchored subtype-specific semantic URLs at render-time for deterministic routing.

### Changed

- **Subtype More Info Surface**
  - Expanded subtype external reference layer to include ClinGen alongside ClinVar.
  - Refined conditional CTA rendering logic for authoritative genetics resources.

- **Search Banner Architecture**
  - Liberated header banner from native WordPress search context.
  - Converted Search Results template into a shell-only renderer.
  - Moved query echo logic into `platform_search_results` layer.

- **Resolver Stability**
  - Eliminated accidental early returns blocking inheritance queries.
  - Refined semantic clamp + widening behavior for multi-token input.
  - Validated stacking logic across noisy or partial inheritance phrases.
  - Confirmed exact gene symbol resolution (e.g., PMP ≠ PMP22).

- **Branch Governance**
  - Rebasing and linearization of `dev` prior to merge.
  - Clean promotion of `main` as first viable production baseline.

### Fixed

- **General Search Fall-Through**
  - Restored native WordPress fallback for non-intent-based content queries.

- **Semantic Variable Invocation**
  - Ensured Roussy-Lévy variable is defined and invoked exactly once.
  - Removed duplicate or competing semantic triggers.

- **Deployment Integrity**
  - Resolved SSH trust prompt and public key authentication issues.
  - Eliminated non-fast-forward rejection through controlled rebase workflow.


## [1.8.0] – 2026-01-01

### Added

- **Platform Search System**
  - Fully custom, intent-aware platform search spanning Subtypes, Genes, Classifications, and curated content.
  - Deterministic resolver pipeline (normalization → intent resolution → variable anchoring → result builder → renderer).
  - Semantic handling for subtype-specific variables (e.g., CMT1A, CMT1F/2E, chromosome and inheritance queries).
  - Canonical ordering enforced across all result buckets (Types → Subtypes → Genes → Content).
  - Graceful fallback to native WordPress search for general content queries.
  - Result buckets labeled with contextual clarity (“Related to Your Search”) and accurate singular/plural handling.

- **Search Results UX**
  - Grid-based layout for identifiers (Types, Subtypes, Genes) with responsive column scaling.
  - Editorial, vertical layout for Content results with title, source label, and excerpt support.
  - Explicit prevention of identifier wrapping while preserving readable flow for content excerpts.
  - Intentional spacing and negative space for scannability and reduced cognitive load.
  - Wide-screen density scaling (≥1600px) without over-stretching sparse result sets.

- **Genetics Database Enhancements**
  - Added **ClinVar pathogenic variant** links where applicable.
  - Added **ClinGen** gene curation links for authoritative external reference.
  - Improved gene and subtype page layouts for clearer hierarchy and readability.
  - Strengthened outbound link handling for safety and consistency.

- **Custom Mobile Navigation Menu**
  - Purpose-built mobile navigation experience independent of desktop constraints.
  - Improved tap targets, spacing rhythm, and visual grouping.
  - Reduced navigation depth to prioritize core user paths.
  - Fully aligned with site-wide UX and accessibility standards.

- **Footer Enhancements**
  - Reworked footer layout to improve readability and visual rhythm across breakpoints.
  - Mobile-specific refinements to prevent stacking congestion and accidental taps.
  - Improved link grouping and negative space for easier scanning.
  - Verified consistency across all major surfaces (Genes, Glossary, Subtypes, Search, Dorsal Root).

### Changed

- **Search Architecture**
  - Eliminated reliance on native WordPress search rendering while preserving WP search as a fallback engine.
  - Header banner behavior decoupled from WP search context for deterministic search presentation.
  - Search results template now functions as a shell, with all logic handled by the platform search stack.

- **Search UI & Typography**
  - Matched clickable result titles across all buckets for visual consistency.
  - Ensured content excerpts are visually subordinate to clickable titles.
  - Removed legacy inline font rules in favor of dedicated, scoped CSS.

- **Mobile UX Consistency**
  - Unified spacing, typography, and interaction patterns across navigation, footer, and search.
  - Reduced cramped layouts and visual pressure points on small screens.

### Fixed

- **Search Result Clarity**
  - Prevented identifier wrapping that caused grid instability for long subtype and gene labels.
  - Resolved spacing conflicts between grid-based and editorial result buckets.
  - Removed rogue inline font-weight rules from content source labels.

- **Layout Hygiene**
  - Eliminated leftover debug artifacts and unused helpers across the search stack.
  - Normalized spacing and margins to maintain consistent negative space site-wide.
  - Verified no residual debug output or temporary instrumentation remains.

---

### **v1.8.0 Summary**

This release delivers a first-class, intent-aware search experience and closes long-standing UX gaps across mobile navigation, footer layout, and genetics content surfaces. Search is no longer an afterthought. It is now a guided, readable, and predictable system that respects how people actually look for information about CMT.


## [1.0.0] - 2025-11-20

### Added
- **Staging Deployment Pipeline (SSH + GitHub Integration):**  
  Fully enabled SSH access on staging; added server-side deploy key; connected staging `wp-content` repo to GitHub via SSH; converted origin remote from HTTPS to SSH; authenticated server with GitHub; validated secure Git operations.
- **Staging Environment Git Architecture:**  
  Staging now tracks the `main` branch directly, establishing a stable production-ready deployment workflow.
- **Complete Path Mapping:**  
  Verified and documented staging root at `/home1/zdqowomy/public_html/staging/9105/wp-content` as the canonical remote Git root.
- **SQL Performance Indexes:**  
  Implemented all required database indexes on staging (`idx_postmeta_key_post`, `idx_term_relationships`, `idx_postmeta_subtype_unique`) for Genes, Glossary, Subtypes, and Dorsal Root query acceleration.

### Changed
- **Deployment Workflow:**  
  Updated staging instance to track `main` rather than `dev`, aligning staging with production-intent code and keeping development isolated to local `dev`.
- **Remote Configuration:**  
  Replaced legacy HTTPS GitHub remote with authenticated SSH remote (`git@github.com:CMTKennyB/Experts-in-CMT.git`) for secure and passwordless deployment.
- **Staging Branch Alignment:**  
  Switched staging worktree from `dev` to `main` cleanly, resolving file deltas and removing environment discrepancies.

### Fixed
- **AIO Migration Remote Drift:**  
  Resolved issues where staging inherited incorrect Git origins following AIO Migration import.
- **Permission Denied (publickey):**  
  Fixed GitHub authentication failures on staging by generating server-side SSH keys and registering them as a GitHub deploy key.
- **Shell Access & Host Key Trust:**  
  Enabled shell access, cleared host key trust prompts, added GitHub host fingerprint, and validated secure SSH communication.

---

### **v1.0.0 Summary**
This release establishes a stable, production-ready foundation for Experts in CMT. All core interactive stacks (Genes, Glossary, Dorsal Root, Subtypes, What Is CMT, DNSMI modal) are in sync across Local → GitHub → Staging. Staging is now a true deployment target, fully backed by Git, SSH, and validated branch structure.

This marks the project’s transition from MVP development to stable release status.


## [0.9.6] - 2025-11-20
### Added
- **Do Not Sell My Information (DNSMI) Modal System**  
  - Full modal experience built via `[do_not_sell_modal]` shortcode.  
  - Success screen with EIC-standard button design and UX flow.  
  - Cookie-based state: once submitted, visitors see a disabled confirmation link (“Your data will not be sold to any 3rd party.”).  
  - Admin bypass: logged-in users bypass cookie restrictions for testing.

### Changed
- **Modal UX & UI Enhancements**  
  - Applied full EIC button styling to both submit and success buttons.  
  - Updated close button to circular EIC style with correct hover colors.  
  - Upgraded inputs to global field patterns (radius, borders, focus ring, placeholder styling).  
  - Fixed spacing around labels and fields; resolved rogue `<br>` behavior with markup cleanup.  
  - Rebased modal to `<body>` to correct z-index and overlay behavior.

### Fixed
- Blocked LastPass/password manager interference inside modal.  
- Success state now properly hides the intro text and form.  
- Resolved hover-locked close button caused by stacking and propagation issues.  
- Removed rogue `<p>` injection from submit button label.


## [0.9.5] – 2025-11-18

### Added
- **CMT and Breathing CPT**
  - Full CPT stack (`breathing`) mirroring the What Is CMT system.
  - Supports modular educational topics with block-template rendering.
  - URL coexistence with the static `/cmt-and-breathing/` page using unified slug rules.
  - Admin menu integration and REST support.

- **CMT and Breathing ACF Group**
  - Same schema as What Is CMT: Overview, Key Points, CTA Link, Updated By.
  - Fully PHP-registered inside `/inc/acf/cmt-and-breathing-fields.php`.

- **Shortcodes**
  - `[cmt_and_breathing_fields]` — inline field renderer for future expansion.
  - `[topic_updated]` — shared dynamic updated-line for What Is CMT + CMT and Breathing.
  - Modularized `[context_nav]` shortcode into its own include file for clarity and consistency.

- **Block Template**
  - `single-breathing.html` created and assigned, matching the What Is CMT template architecture.

### Changed
- **Shortcode architecture cleanup**
  - Unified updated-line shortcode and removed the old single-CPT version.
  - Context navigation now supports both What Is CMT and Breathing CPTs with correct labels and back-links.

- **functions.php organization**
  - Added require statements for all new CPT/ACF/shortcode files.
  - Ensured load order remains intact and predictable across all includes.

- **UI/UX parity**
  - CMT and Breathing topics now follow the exact UX flow as What Is CMT topics, including nav layout, updated line, and Dorsal Root footer integration.

### Fixed
- **404 resolution for new Breathing CPT**
  - Required rewrite flush after CPT registration.
  - CPT now resolves correctly under `/cmt-and-breathing/topic-slug/`.

- **Context nav display logic**
  - Corrected detection for the new CPT.
  - Ensured nav appears appropriately once more than one topic exists.

- **Shortcode autoload duplication prevention**
  - Removed accidental duplicate What Is CMT CPT declaration.
  - Ensured correct modular loading of CPT, ACF, and shortcode files via `/inc/` directories.


## [0.9.0] – 2025-11-16

### Added

- **Genes AJAX Stack Completion**: Implemented full DR-parity AJAX system for the Genes Database, including live search, taxonomy/meta filtering, canonical sorting preservation, pagination transport, and fragment-only replacement via `genes-loop-endpoints.php`.
- **Genes Live Search**: Added live search across all key ACF/meta fields with stable URL state and preserved FIELD() canonical order.
- **Genes Pagination Overrides**: Added shortcode-controlled per-page overrides and integrated them into AJAX query transport.
- **Glossary Sort UI + Search UI**: Added Genes-style sort toolbar and search controls to the Glossary loop, with global search facet integration.
- **Glossary AJAX Loader**: Implemented smooth AJAX swap behavior identical to Dorsal Root, with scroll management and parameter preservation.
- **DR Filter UI**: Added `[dr_filter]` shortcode with category selector (`dorsal-root`), search input, auto-submit behavior, and fully responsive Genes/Glossary-style UI.
- **DR Query Expansion**: Implemented OR-based search across title, excerpt, content, tags, and taxonomy term names.
- **DR Static Page Rewrite (2025)**: Completed migration of Dorsal Root from WP archive to static page using ACF and a custom loop (`dr-posts.php`).
- **Maintenance Toolbox**: Added `/tools/` directory containing Prettier, Stylelint, PHP CS Fixer, PHPCS, EditorConfig, and npm/composer scripts (`fmt:all`, `lint:all`, etc.) for unified theme formatting.
- **Subtype Publication Notes**: Added new WYSIWYG fields (Publication Note, Alt Publication Note) with grid-aligned rendering inside `subtype-fields-template.php`.
- **Subtype CTA Block**: Added 2×2 “More Info” section with external-link CTAs (Symptoms, Research, What is CMTX, What is Intermediate CMT), plus custom `$research_label` support.
- **Subtype Updated Line**: Added final metadata footer (“Updated: {date} | By: K. Raymond”) to subtype single template.
- **Genes/Glossary/DR Shared Scripts**: Standardized toolbar, reset behavior, parameter handling, scroll logic, and event interception across all loops.

### Changed

- **Canonical Sort Enforcement**: Restored and protected the canonical FIELD() sort order for Genes under all conditions (default load, reset, clear, AJAX reloads, and URL state).
- **Genes/Glossary/DR Pagination**: Unified paging behavior; all loops now reset pagination on filter changes and maintain position on reload.
- **Global Form Handling**: Replaced default WP form bubbling with custom JS to prevent duplicate reloads, lost params, and anchor jumps across all CPT loops.
- **AJAX Transport Model**: Standardized POST/GET handling (`$req = array_merge($_GET, $_POST)`) across all endpoints.
- **DR Taxonomy Scope**: Switched all legacy `category` references to the `dorsal-root` taxonomy.
- **Glossary Rendering**: Updated glossary loop to match Genes/DR structure (bagpipe card parity, featured image fallback, bottom alignment).
- **Glossary & Genes Scroll Behavior**: Reworked anchors and scroll offsets to eliminate jump scrolling on reloads and resets.
- **Subtype Single Template Refinements (v0.7.2)**: Updated grid spacing, alignment, note placement, CTAs, dividers, and universal padding rhythm.
- **Genes Loop Restructure**: Split the Genes loop into shortcode wrapper + fragment (`loop-fragment-genes-loop.php`) for endpoint parity.
- **Codebase Cleanup**: Ran full theme through formatting + maintenance QC (JS/PHP/CSS), removed redundant wrappers, corrected loader paths, fixed invalid markup, and normalized indentation.
- **Filter File Restructure**: Moved all filter PHP files into `/inc/content/filters` for modular organization.

### Fixed

- **Genes AJAX Regression**: Resolved full breakdown of filter logic, sort state, and pagination caused by WP form-hook conflicts and redundant reloads.
- **Genes Canonical Sorting Breakage**: Fixed sort resets that previously killed the FIELD() order; canonical ordering now persists across every reload type.
- **Glossary AJAX Jitter**: Eliminated double-render jitter by adding global `window.GL_AJAX` guards and ensuring single pipeline execution.
- **Glossary Bottom Alignment Issue**: Restored bagpipe card alignment via glossary-scoped flex/calc fix without affecting DR or Genes.
- **DR Tax Query Bug**: Corrected `tax_query` shape for category-only views and removed duplicated sort switch.
- **DR Reset Jump Scroll**: Fixed jump scroll on DR selector reset; now reloads smoothly without anchor jump.
- **DR Button Autop Injection**: Fixed WP auto-`<p>` and `<br>` insertion around “More From The Dorsal Root” by wrapping the anchor in `<span class="dr-more-wrap">`.
- **Genes Loader Path Issues**: Fixed loader script inconsistencies between shortcode and endpoint.
- **Genes Fragment Mismatch**: Corrected swapped file names (`fragment-loop-genes-loop.php`) and restored consistent include paths.
- **Filter Reset Behavior**: RESET links on all loops now correctly clear filter params without breaking scroll or losing state.
- **Multiple Markup Hygiene Issues**: Removed duplicate closing tags in `genes-filter.php`, fixed rogue `<br>` injection, and normalized HTML structure across templates.

### Removed

- Deprecated smooth-scroll scripts from pre-AJAX Genes and Glossary loops (now handled by unified anchor-based navigation).
- Legacy pagination wrappers from Genes loop after AJAX parity implementation.

## [0.7.7] - 2025-11-07

### Added

- **Dorsal Root Filters UI:** Introduced the `[dr_filter]` shortcode with category dropdown (taxonomy: `dorsal-root`) and search input styled via the global `.site-search__row`. The dropdown auto-submits, resets pagination, and anchors to `#results`.
- **Glossary (CMT Words) AJAX pipeline:** Implemented a modular AJAX loader with the `glossary_get_loop` endpoint returning identical inner `#results` markup for parity with the non-AJAX shortcode render.
- **AJAX guards:** Unified `DR_AJAX` and `GL_AJAX` safeguards across both stacks to prevent double-handling, jitter, and redundant reloads. Added `stopImmediatePropagation()` to ensure single-path events.
- **Search UX polish:** Improved accessibility and mobile typing with `inputmode="search"`, `autocomplete="on"`, `autocapitalize="none"`, `spellcheck="false"`, and `enterkeyhint="search"`.
- **Dorsal Root filter styling:** Added a page-specific CSS rule to match the category `<select>` height (46 px) to the search input for consistent visual rhythm.

### Changed

- **DR loop query logic:** Search now performs a union across post title, excerpt, and content plus tag names and `dorsal-root` terms. When a category is selected, results are the intersection (Category ∩ Union). “Only category” path uses a single `tax_query` with `include_children`.
- **Taxonomy scope:** Replaced core `category` references with the custom `dorsal-root` taxonomy.
- **Parameter handling:** Unified GET handling for DR (`qs`, `dr_paged`, `dr_sort`, `dr_cat`) with hidden inputs preserving all other parameters. Reset clears `qs`, `dr_paged`, and `dr_cat`.
- **Form behavior standardization:** Overrode native WP form bubbling to deliver consistent submit, reset, and pagination actions across DR, Glossary, and Genes.
- **CSS structure:** Added a dedicated `/* DORSAL ROOT FILTERS */` section at the end of `main.css` for scoped styling, maintaining modular cascade order.

### Fixed

- **Duplicate sort switch** removed; ensured a single `new WP_Query($args)` call.
- **`tax_query` shape** corrected for “only category” case.
- **Anchor jump trimming:** Pagination and submit flows now preserve `#results` and scroll position.
- **Glossary jitter:** Eliminated through AJAX guards and single-path event handling.
- **Minor CSS/JS hygiene:** Normalized margins, corrected invalid values, and cleaned inline script placement.
- **Visual offset:** Fixed mismatch between DR category selector and search input height; full pixel-perfect parity achieved.

### Known Issues / Next

- **RESET jump edge case:** A native anchor jump may still occur on DR reset; planned refinement via `history.replaceState` + programmatic reload.
- **Glossary render-offset investigation:** Occasional overlap behind hero/search wrapper remains under review (layout flow / z-index vs space reservation).
- **Genes AJAX stack:** Next milestone will port this validated DR/Glossary architecture to the Genes Database loop.

## [0.7.6] - 2025-11-05

### Added

- **Development Toolbox** under `/tools/`: Prettier, Stylelint, PHP CS Fixer, PHPCS, and EditorConfig with npm scripts to format and lint JS, CSS, and PHP.
- **Dorsal Root Filters UI**: `[dr_filter]` renders a `dorsal-root` category dropdown and a search input using global form styles. Input UX set with `inputmode="search"`, `autocomplete="on"`, `autocapitalize="none"`, `spellcheck="false"`, and `enterkeyhint="search"`.
- **Auto-submit on category change**: Resets pagination and appends `#results`. “All Categories” omits `dr_cat`.
- **Glossary polish pass**: Global spacing, focus states, and typography rhythm aligned to Genes and DR card styles.
- **Glossary loop** improvements toward Genes parity:
  - Title-only search and alpha-range filtering (A–E, F–J, K–O, P–T, U–Z, 0–9).
  - Smooth submit behavior that resets pagination and reloads with `#results`.
  - Scoped UI classes for consistent grid cards and button rhythm.

### Changed

- **DR Loop Query Logic**: Union search across title, excerpt, content, tag names, and `dorsal-root` term names. When a category is selected, results are intersection of Category ∩ Union. “Only category” path uses a single `tax_query` with `include_children`.
- **Taxonomy Scope**: All DR taxonomy references switched from core `category` to custom `dorsal-root`.
- **Param Handling**: Unified GET management for DR (`qs`, `dr_paged`, `dr_sort`, `dr_cat`). Hidden inputs preserve other params and skip these.
- **Filter Reset Behavior**: RESET and built-in search clear now remove `qs`, `dr_paged`, and `dr_cat`, then reload anchored to `#results`.
- Moved all filter PHP files into `/inc/content/filters` to match the modular include structure.
- Verified Glossary scroll and anchor behavior for full parity with the Genes Database.
- **Glossary loop UI**: Removed the search toolbar while preserving the sort toolbar and existing scroll and pagination behavior.
- **Subtype Single Template v0.7.2**: Publication Note and Alt Publication Note fields, “More Info” CTA section, dynamic research label, and consistent spacing and divider rhythm.

### Fixed

- Removed duplicate sort switch and ensured a single `WP_Query` execution for DR loop.
- Corrected `tax_query` array shape for the “only category” path.
- Restored Glossary Y-axis card alignment by scoping DR button margin rules and adding glossary-only flex adjustments.
- Minor CSS and JS hygiene: cleaned invalid values, verified script placement after forms, and enforced pagination reset on submit and category change.
- Clean removal avoided regressions to card markup and equal-height grid behavior.
- Resolved PHP notice in `glossary-loop.php` by guarding the cleanup callback variable.
- Eliminated jumpy behavior on pagination by standardizing anchor flow.
- Rogue `<br>` injection and top padding mismatch fixed in Subtype template. Verified Gutenberg block integration.

### Known Issue / Next

- **Jump scroll** on DR RESET click. Planned solution: client-side navigation that preserves position with a smooth reload and no anchor jump.

---

## [0.7.2] - 2025-10-30

### Added

- **Subtype Single Template v0.7.2**: Publication Note and Alt Publication Note (WYSIWYG) fields.
- **Subtype Single Template**: “More Info” CTA grid (2×2) using `.dr-more` styling with external link safety attributes.
- **Subtype Single Template**: Footer metadata — `Updated: {date} | By: K. Raymond`.
- **Genes Loop**: User-select **Sort** toolbar (Default, Gene A–Z, Subtype A–Z, Oldest→Newest, Newest→Oldest).
- **Genes Filter/Loop**: No-jump JS reloads for filter apply/reset, sort change, and sort clear.

### Changed

- **Subtype Single Template**: Inline “Note:” label + field on one line; label in roman bold; only gene symbols (e.g., _PMP22_) italicized.
- **Subtype Single Template**: Standardized divider/spacing rhythm (50px between `.eic-block` sections); bottom divider restored on final block.
- **Subtype Single Template**: Unified WYSIWYG + inline typography (`font-size: 0.95rem; line-height: 1.45`) and font inheritance across the section.
- **Genes Loop**: Integrated sort logic for `gene_symbol`, `subtype`, and `year_of_discovery`; preserved canonical `type_classification` FIELD() order when no explicit sort is selected.
- **Genes Filter**: Restored **APPLY FILTERS** and **RESET** buttons; centered results-level sort toolbar; matched CLEAR styling to filter RESET.
- Layout refinements across Overview, Clinical & Genetic Context, More Info, Key Publications, Alt Publications, and Updated line for clear hierarchy.

### Fixed

- **Subtype Single Template**: Removed rogue `<br>` inside CTA buttons; corrected top-padding mismatch.
- **Genes Loop/Filter**: Verified clean URL behavior (anchors/pagination) and stable no-jump interactions.
- **Subtype Single Template**: Rogue `<br>` injection and top padding mismatch. Verified Gutenberg block integration.

---

## [0.7.1] - 2025-10-28

### Added

- MU plugin `cmtgenes-subtype-uniqueness.php` to hard-stop duplicate Subtype saves.
- SQL index `idx_postmeta_subtype_unique` for rapid duplicate checks.

### Changed

- Converted Subtype ACF group to code-registered PHP (`/inc/acf/subtype-fields.php`); deactivated UI group.
- Updated meta mappings (e.g., `gene → gene_symbol`) across search, filter, and counts.

### Fixed

- Added ACF validation filter in `cmtgenes-helpers.php` to prevent duplicate Subtype entries before save.
- Verified core indexes active: `idx_postmeta_key_post`, `idx_term_relationships`, `idx_postmeta_subtype_unique`.

---

## [0.7.0] - 2025-10-27

### Added

- **Genes Database**: Integrated `[genes_filter]` + `[genes_loop]` into a cohesive system.
- **Totals**: Real-time counts with plural handling (Subtypes, Genes, Unknown Gene(s)).
- **Shortcode**: `[genes_totals_inline]` for standalone totals display.
- **ACF**: `unknown_gene` True/False field on Subtype.

### Changed

- Filter grid: 2×2 layout with full-width search row; removed per-option counts for cleaner UX.
- Typography and spacing unified to brand tokens; cleaned anchors and query strings.

### Removed

- Legacy smooth-scroll scripts; adopted anchor-only navigation.
- Global WASD navigation script disabled to avoid editor conflicts.

---

## [0.6.4] - 2025-10-22

### Added

- `[genes_loop]` shortcode: Subtype cards in a 3-column layout using existing DR handles.
- Fallback `single-subtype.html` and `ensure-single-subtype.php` to persist the TT25 “Single Item: Subtype” template.
- SQL index for performance:
  ```sql
  CREATE INDEX idx_postmeta_key_post ON wp_postmeta (meta_key(191), post_id);
  ```

### Changed

- Strict custom ASC order for `type_classification`  
  (CMT1 → CMT2 → CMTX → CMT4 → CMTDI → CMTRI → dHMN → dSMA → GAN → HMSN → HSAN → HSN → SMA-LEP → Unclassified).
- Subtype title displayed above gene metadata; “Updated” footer centered for layout consistency.

---

## [0.6.3] - 2025-10-21

### Added

- Site-wide keyboard navigation via `global-keyboard-nav.js` (WASD + Arrow Keys).
- Screen-reader live region injected via `wp_body_open`.
- Unified `:focus-visible` outline tied to `--primary` token; smooth focus scrolling.

### Changed

- Escape-key handling to exit form/edit contexts; cross-browser compatibility verified.

---

## [0.6.2] - 2025-10-21

### Added

- Text search field to Dorsal Root filter alongside category dropdown.
- Reset button that mirrors native clear (“×”) and returns to `#blog`.
- ARIA roles/labels and unified button styling via global tokens.

### Changed

- Cleaned scripts; removed legacy live search remnants.
- Verified responsive scaling and equal heights (Hi-Tek Squish Test™).

---

## [0.6.0] - 2025-10-19

### Added

- `[dr_posts]` shortcode: PHP-chunked rows of 3 with auto-centering for 2/1-card final rows.
- `[dr_filter]` shortcode: Category selection preserving query vars; in-page reloads.
- `.dr-grid` / `.dr-row` wrappers governing structure and equal-height logic.

### Changed

- Preserved `.dr-blog` ecosystem; unified height rhythm; “Read More” buttons aligned on shared Y-axis.
- Mobile scaling and label alignment refined to 150px (Hi-Tek Squish Test™ certified).

---

## [0.5.2] - 2025-10-18

### Added

- Featured Dorsal Root section (via shortcode) with 3-wide responsive layout (16:9 media, stable rhythm).
- `.has-separator-border` token for section dividers and header accents.
- Floating “Back to Top” button with smooth scroll and breakpoint validation.

### Changed

- Query loop card architecture refactored: equalized heights, pinned “Read More,” subtle resting shadow.
- Standardized `.dr-blog` for symmetry and future reuse.

---

## [0.5.1] - 2025-10-16

### Changed

- Merged Dorsal Root blog setup to main.

---

## [0.5.0] - 2025-10-16

### Added

- Dedicated Dorsal Root blog home and single post templates.
- Post navigation and “Return to Blog” logic via `functions.php` and `main.css`.
- “Featured” taxonomy (code-registered) for a three-article featured section.
