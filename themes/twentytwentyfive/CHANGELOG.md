**# Changelog**



\## v0.5.0 — Release Date:2025-10-16

\*\*Established Dorsal Root blog system\*\*



**Summary:**

\- Created dedicated blog home and single post templates

\- Built post navigation and “return to blog” logic via functions and main.css

\- Registered “Featured” taxonomy in code for three-article featured section

\- Committed and pushed full environment to origin



\## v0.5.1 — Release Date:2025-10-16



**Summary:**

\- Dorsal Root blog setup merged to main



\## v0.5.2 — Release Date: 2025-10-18

\*\*Dorsal Root Feature Build \& Symmetry Enforcement\*\*



**Summary:**

\- Brought visual balance, motion polish, and elevated logic to the Dorsal Root ecosystem.

\- Featured Dorsal Root Section:

\- Built, compiled, and validated the Featured Dorsal Root section for deployment via shortcode.

\- Locked 3-wide responsive layout with 16:9 media and consistent typography rhythm.



**Header Separator Styling:**



\- Added a reusable .has-separator-border class token for sleek section dividers and header accents.

\- Engineered with ultra-precision line density management technology (0.5px CMTA light blue hairline).



**Back to Top Button:**



\- Added site-wide floating “Back to Top” button with smooth scroll and breakpoint validation.

\- Passed the High-Tech Squish Test™ with full mobile and responsive compliance.

\- Optimal placement achieved (32px right / 96px bottom, Kenny-approved).

\- Query Loop Card Architecture:

\- Refactored blog cards to equalize height using flexbox logic and pinned “Read More” buttons.

\- Implemented subtle resting shadow (0 2px 6px rgba(0,0,0,.06)) to add life without clutter.

\- Standardized the .dr-blog class for symmetry, cohesion, and future reuse across archives.



**Result:**



The Dorsal Root environment now breathes — symmetrical, responsive, and perfectly squish-safe.



**##** v0.6.0 — Release Date: 2025-10-19

\*\*PHP-Driven Filtering, Query Loop Render Card Equal-Height Logic w/ True 3-2-1 Grid Alignment\*\*



**Summary:**



\- Elevated The Dorsal Root from plugin-dependent layout to fully PHP-driven architecture.

\- Achieved consistent, equal-height cards and perfectly centered 3-2-1 row alignment to remove the reliance on expensive plugins

\- Locked filter logic, label polish, and mobile micro-layout refinements.



**The Dorsal Root Query Loop and Category Filtering Shortcode System Logic Architecture:**



\- Built \[dr\_posts] shortcode to output PHP-chunked rows of 3 posts, automatically centering 2- and 1-card final rows.

\- Built \[dr\_filter] shortcode for category selection with preserved query vars and smooth in-page reloads.

\- Introduced .dr-grid and .dr-row wrappers to govern row structure and equal-height logic with surgical precision.



**CSS \& Layout Consistency:**



\- Preserved benchmark .dr-blog class ecosystem for legacy styling continuity.

\- Unified height logic and vertical rhythm — extra spacing correctly follows excerpts, not dates.

\- Centered “Read More” buttons on a shared Y-axis across all cards.

\- Enforced proper mobile scaling and label alignment down to 150px (Hi-Tek Squish Test™ certified).



**Result:**



The Dorsal Root now operates on a clean, scalable codebase that renders a UX card array that is perfectly centered, equalized, and free of consumer-grade plugin reliance.



\##v0.6.2 — Release Date: 2025-10-21



\*\*Filter Enhancements — Add Search to Dropdown Filter\*\*



**Summary:**



This release finalizes the Dorsal Root Filter MVP with functional search integration, a working reset button, accessibility markup, and unified styling across both form controls and global tokens. The filter is now fully responsive, accessible, and structurally stable.



**Changes:**



\- Added text search field alongside category dropdown using native GET submission.

\- Implemented Reset button that mirrors the browser’s native clear (“×”) behavior, clearing all active filters and returning to #blog.

\- Added ARIA roles and labels (role="search", aria-label, aria-controls) for accessibility and SEO compliance.

\- Unified button styles (Search + Reset) using global design tokens and shared .dr-filter-buttons layout.

\- Cleaned up redundant scripts and removed all legacy live search remnants.

\- Passed full Hi-Tek Squish Test™ — verified equal heights, responsive scaling, and zero layout breakage.

\- Retained native dropdown behavior; confirmed open-state styling limitations are browser-bound.

\- Verified compatibility with Experts in CMT typography, spacing, and color tokens.



**Result:**



A fully functional, accessible, and token-aligned filter component ready for deployment and future scaling (e.g., “Clear All,” live search, or result counts).



\##v0.6.3 — Release Date: 2025-10-21



\*\*Global Keyboard Navigation + Accessibility Polish\*\*



**Summary:**



This release adds full-site keyboard navigation using both arrow keys and WASD, smooth focus scrolling, and a global screen-reader live region to announce focus changes. It also introduces a unified :focus-visible style aligned with the site’s token system, ensuring clear visual feedback for keyboard users while maintaining WCAG 2.2 compliance.



**Changes:**



\- Added global keyboard navigation script (assets/js/global-keyboard-nav.js) supporting WASD + Arrow Keys for spatial nearest-neighbor focus movement.

\- Integrated smooth scrolling and focus transitions between navigable elements.

\- Added global screen-reader live region injected via wp\_body\_open (#screenreader-nav-status) for real-time accessibility feedback.

\- Introduced unified :focus-visible outline and scale animation tied to --primary color token for consistent theming.

\- Updated functions.php to enqueue global-keyboard-nav.js at late priority with cache-busting via filemtime().

\- Maintained natural Tab order and form input behavior (no regressions).

\- Added escape-key handling to exit form fields or editable contexts for improved accessibility.

\- Verified compatibility across Chrome, Firefox, Safari, and Edge.



**Result:**



The site is now fully navigable by keyboard, providing clear focus states, smooth motion, and live screen reader updates—achieving an accessible, token-consistent, and future-proof foundation for advanced a11y features (e.g., focus trapping and skip-link restoration).



\##v0.6.4 — Release Date: 2025-10-22



\*\*Genes Database Loop + Subtype Template Persistence\*\*



**Summary:**



This release introduces the MVP for the Genes Database display system, including a fully functional \[genes\_loop] shortcode for subtype entries, a custom sort order for type\_classification, and a reliable fallback system for the “Single Item: Subtype” template. The update ensures consistent rendering and order of genetic subtype data while preserving visual editing flexibility in the TT25 Site Editor.



**Changes:**



\- Added genes-loop.php shortcode (\[genes\_loop]) for displaying subtype (subtype CPT) entries in a 3-column card layout using existing Dorsal Root CSS handles.

\- Integrated ACF data fields for gene, year\_of\_discovery, and inheritance\_pattern with graceful fallbacks.

\- Added subtype title display above gene metadata for consistent labeling and hierarchy.

\- Implemented strict custom ASC sort order for type\_classification (CMT1 → CMT2 → CMT4 → CMTX → CMTDI → CMTRI → dHMN → dSMA → GAN → HMSN → HSAN → HSN → SMA-LEP → Unclassified).

\- Center-aligned and spaced the “Update” footer text to maintain consistent card heights and layout rhythm.

\- Added fallback file single-subtype.html under /templates/ to ensure the subtype single template remains available even if TT25 deactivates DB templates.

\- Added ensure-single-subtype.php script to automatically republish and persist the “Single Item: Subtype” template within the Site Editor.

\- Updated /functions.php modular loader to include genes-loop.php and ensure-single-subtype.php.

\- Indexed performance optimization command added for future Bluehost deployment:

\- CREATE INDEX idx\_postmeta\_key\_post ON wp\_postmeta (meta\_key(191), post\_id);



**Result:**



Subtype pages and loops now render consistently and maintain a strict biological and clinical order regardless of default WordPress sorting behavior. The new fallback system ensures subtype templates remain active and editable, providing a durable and editor-friendly foundation for future Genes Database filtering and display logic.



\##v0.7.0 — Release Date: 2025-10-27



\*\*Genes Database Filter + Totals Integration\*\*



**Summary:**



This release completes the Genes Database filtering and display system, connecting \[genes\_filter] and \[genes\_loop] into a cohesive, data-driven experience. It adds ACF-based logic for subtype filtering, accurate totals output, and a flexible shortcode for global totals display. The update also refines site organization and styling consistency, aligning every component with the CMT. Curated. aesthetic while ensuring version control integrity and predictable UX.



**Changes:**



\- Added real-time counts and totals logic for the Genes Database with plural handling (result(s), gene(s), subtypes with unknown gene(s)).

\- Introduced \[genes\_filter] refinements: fixed term-ID normalization, corrected taxonomy mapping to cmt\_type, and restored stable dropdown widths.

\- Re-arranged the Genes Database filter array into a 2×2 grid with the search field repositioned in row 3, spanning the full width below the dropdowns for optimal layout balance.

\- Removed per-option counts from dropdowns for a cleaner, more intuitive interface.

\- Validated text-search filter functions and stabilized URL outputs—anchor behavior and query strings are now clean and reproducible.

\- Implemented ACF True/False field unknown\_gene to flag subtypes with unconfirmed genes.

\- Updated totals output in \[genes\_loop] to show “Subtype with an Unknown Gene” only when applicable.

\- Added \[genes\_totals\_inline] shortcode for global use (footer, sidebar, standalone sections) with responsive centering.

\- Matched totals typography and spacing to "CMT. Curated." styling—light weight, centered alignment, responsive sizing.

\- Final beauty passes on all loops and filters: consistent padding, grid alignment, and typography across breakpoints.

\- Cleared wayward JS executions from legacy scrollTo logic; implemented lightweight anchor-only navigation for filters and pagination.

\- Disabled global-keyboard-nav.js (WASD navigation) site-wide to prevent editor conflicts; left script dormant for future triaging and re-enable.



**Repository structure:**



\- Restructured repo to include the /mu-plugins/ directory for versioning.

\- Updated .gitignore to track only the active theme (twentytwentyfive) and must use utilities, excluding deprecated builds.



**Result:**



The Genes Database is now production-ready: filters, search, and totals operate predictably; styling is unified with the site’s brand aesthetic; and the codebase is version-controlled with a clean, durable structure for ongoing development.



\##v0.7.1 — Release Date: 2025-10-28

\*\*Subtype Integrity + ACF PHP Migration\*\*



**Data Integrity**



\- Enforced one-record-per-Subtype rule.

\- Added ACF validation filter in `cmtgenes-helpers.php` to prevent duplicate subtype entries before save.

\- Added must-use plugin `cmtgenes-subtype-uniqueness.php` for hard-stop duplicate prevention at save time.

\- Introduced SQL index `idx\_postmeta\_subtype\_unique` for rapid duplicate checks.

\- Confirmed all core indexes active:  

&nbsp; `idx\_postmeta\_key\_post`, `idx\_term\_relationships`, `idx\_postmeta\_subtype\_unique`.



**ACF → PHP Conversion**



\- Converted Subtype field group to code-registered PHP (`inc/acf/subtype-fields.php`); deactivated the old ACF UI group.

\- Mapped all core meta fields to new schema (`gene → gene\_symbol`, `alternate\_gene\_#`, etc.).

\- Verified all search, filter, and count logic reference updated field names.



**Query + Filter Layer**



\- Validated `genes-loop.php` and `genes-filter.php` under new field mapping.

\- Confirmed accurate live counts and grand totals.

\- Taxonomy filters (`cmt\_type`, `inheritance`, `neuropathy`, `chromosome`) verified functional and performant.



**Result**



\- Subtype database now structurally sound and self-validating.

\- No duplicate subtype records are possible.

\- Baseline version for all future subtype, gene-mapping, and admin-integrity enhancements.



