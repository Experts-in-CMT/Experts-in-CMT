# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- (placeholder)

### Changed
- (placeholder)

### Fixed
- (placeholder)

---

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
