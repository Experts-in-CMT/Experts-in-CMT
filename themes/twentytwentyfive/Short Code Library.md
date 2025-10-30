Shortcode Library — Experts in CMT

A living reference of all active shortcodes used across the Experts in CMT build.
Updated as of the latest theme commit.
(This doc exists purely for Kenny’s sanity — not for deployment.)

Active Shortcodes
[header_banner]

Renders the page or post header banner using ACF fields.
Template: /templates/header-banner.php
Directory: /wp-content/themes/twentytwentyfive/functions.php
Usage:

[header_banner]


Notes: Used to inject the hero/banner region at the top of templates that need it.

[context_nav]

Unified “Previous / Back / Next” navigation component.
Supports multiple post types, including post, subtype, glossary, and resource.
Directory: /wp-content/themes/twentytwentyfive/functions.php
Usage:

[context_nav]


Notes: Active Subtype post navigation method. Deployed in template footer or nav area.

[dorsal_root_section]

Renders the Dorsal Root 3-feature section using
get_template_part('templates/parts/section', 'dorsal-root', ['show_search' => true]);
Directory: /wp-content/themes/twentytwentyfive/functions.php
Usage:

[dorsal_root_section]


Notes: Outputs the three-feature section used on the main Dorsal Root page.

[genes_filter]

Renders the Genes Database filter UI (search bar and dropdowns).
Submits GET params (cmt_type, inheritance, neuropathy, chromosome, qs) that [genes_loop] reads.
Directory: /wp-content/themes/twentytwentyfive/inc/filters/genes-filter.php
Usage:

[genes_filter]


Notes: Form action anchors to #results.

[genes_loop]

Displays paginated Subtype cards based on filter/search input.
Includes FIELD() ordering and meta/tax query logic.
Directory: /wp-content/themes/twentytwentyfive/inc/content/loops/genes-loop.php
Usage:

[genes_loop per_page="12"]


Notes: Wrapper id results; appends #results to pagination links.

Proposed / Optional
[subtype_header_banner]

Server-renders the Subtype header banner (ACF group) at the top of single items
without appearing in the block editor.
Status: Proposed (alternative to block filter injection)
Suggested Directory: /wp-content/themes/twentytwentyfive/inc/shortcodes/banner.php

Deprecated / Legacy

Add any retired shortcodes here when they’re phased out.

Usage Examples
Genes Database page
[genes_filter]
[genes_loop per_page="12"]

Dorsal Root page
[dr_filter]
[dr_posts per_page="9"]

Subtype single (navigation area)
[context_nav]

Adding a New Shortcode

Create a file in inc/shortcodes/ (or your chosen folder).

Register it with add_shortcode().

Prefix functions with eic_ to avoid collisions.

Document its attributes and defaults at the top of the file.

Add a short entry here under Active Shortcodes.

Tag a new version after adding user-facing shortcodes.

Open Tasks

Confirm exact file paths and update directories if refactored.

Add PHPDoc headers and inline attribute validation for each shortcode.

Move any remaining shortcodes out of functions.php into /inc/shortcodes/.

Add a lint/checklist step for shortcode files.