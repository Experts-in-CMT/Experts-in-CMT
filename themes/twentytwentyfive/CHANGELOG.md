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

## [0.7.2] - 2025-10-30

### Added

- **Subtype Single Template**: Publication Note and Alt Publication Note (WYSIWYG) fields.
- **Subtype Single Template**: “More Info” CTA grid (2×2) using `.dr-more` styling with external link safety attributes.
- **Subtype Single Template**: Footer metadata — `Updated: {date} | By: K. Raymond`.
- **Genes Loop**: User-select **Sort** toolbar (Default, Gene A–Z, Subtype A–Z, Oldest→Newest, Newest→Oldest).
- **Genes Filter/Loop**: No-jump JS reloads for filter apply/reset, sort change, and sort clear.

### Changed

- **Subtype Single Template**: Inline “Note:” label + field on one line; label in roman bold; only gene symbols (e.g., _PMP22_) italicized.
- **Subtype Single Template**: Standardized divider/spacing rhythm (50px between `.eic-block` sections); bottom divider restored on final block.
- **Subtype Single Template**: Unified WYSIWYG + inline typography (`font-size: 0.95rem; line-height: 1.45`) and font inheritance across the section.
- **Genes Loop**: Integrated sort logic for `gene_symbol`, `subtype`, `year_of_discovery`; preserve canonical `type_classification` FIELD() order when no explicit sort is selected.
- **Genes Filter**: Restored **APPLY FILTERS** and **RESET** buttons; centered results-level sort toolbar; matched CLEAR styling to filter RESET.

### Fixed

- **Subtype Single Template**: Removed rogue `<br>` inside CTA buttons; corrected top-padding mismatch.
- **Genes Loop/Filter**: Verified clean URL behavior (anchors/pagination) and stable no-jump interactions.

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
