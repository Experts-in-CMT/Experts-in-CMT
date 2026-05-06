> **© 2025-2026 Kenneth Raymond — All rights reserved.**  
> Part of the Experts in CMT WordPress theme.  
> Do not copy, modify, or redistribute without permission.

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- (placeholder)

### Changed

- **Subtype Taxonomy Registration — Disable Public Archives (`register-subtype-taxes.php`)**
  - Set `"public" => false`, `"publicly_queryable" => false`, and `"rewrite" => false`
    on all four subtype taxonomies (`cmt_type`, `inheritance`, `neuropathy`, `chromosome`).
  - Eliminates public-facing taxonomy archive routes that were generating bot-crawl
    404s and driving malformed Yoast breadcrumb ancestry on subtype pages.
  - Admin UI, ACF integration, and filter loop functionality are unaffected.
  - themes/twentytwentyfive/inc/taxonomies/register-subtype-taxes.php

### Fixed

- (placeholder)

### Maintenance

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