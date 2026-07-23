> **© 2025-2026 Kenneth Raymond. All rights reserved.**
> Part of the Experts in CMT WordPress theme.
> Do not copy, modify, or redistribute without permission.

# Experts in CMT

Custom WordPress block theme that powers [expertsincmt.org](https://expertsincmt.org), the Experts in CMT platform. Built on the TwentyTwentyFive (TT25) full-site-editing architecture, then forked to its own slug and extended with a database-driven genes catalog, custom post types, AJAX-filtered loops, structured data, and a set of admin maintenance tools.

- **Slug / folder:** `expertsincmt`
- **Current version:** 3.0.0 (see `style.css`)
- **Base architecture:** WordPress block theme (FSE), forked from TwentyTwentyFive
- **Production:** [expertsincmt.org](https://expertsincmt.org), hosted on Ionos, deployed by SFTP
- **Local:** `cmt-genes-clean` (Local by Flywheel)

## Why the slug is `expertsincmt` (read before renaming anything)

This theme used to live in the bundled-default `twentytwentyfive` folder. Because that folder name is the one WordPress and the host key their default-theme maintenance on, Ionos/WordPress kept overwriting it and wiping every live customization: the recurring "ghost" that repeatedly broke the site, which even day-back backup restores could not reliably undo.

Version 3.0.0 fixes that permanently by forking the theme to its own unique slug, `expertsincmt`, that no default-theme updater touches. The folder name is load-bearing:

- **Never rename this folder back to `twentytwentyfive`** or to any other bundled-default slug (`twentytwentyfour`, etc.). That reopens the ghost.
- The theme slug is the **folder name**, not the `Theme Name:` display value in `style.css`. WordPress keys the theme, and its Site Editor templates/parts/global styles (via the `wp_theme` taxonomy), to the folder name.
- All Site Editor content is baked into files in this repo (templates, parts, global styles), so the theme is self-contained. Deploying to production is a plain file upload with no database surgery.

An `eic-shadow` copy exists as a working spare. Its `style.css` `Theme Name:` is "Experts in CMT - Shadow Copy" so it never collides with the live theme in the picker. If the live theme is ever damaged, the shadow is the break-glass restore.

## Version, the single source of truth

The version lives in three places that must always agree: the `Version:` header in `style.css`, the top entry in `CHANGELOG.md`, and the git tag (`vX.Y.Z`).

`style.css` is the source of truth. `functions.php` reads it dynamically through `wp_get_theme()->get("Version")` and busts asset caches with `filemtime()`, so no PHP ever hardcodes a version. Never bump one of the three without the others; the **version-bump skill** exists to run all three as one ritual so they cannot drift.

Semver here: `2.1.0 -> 3.0.0` was a major because the slug migration is a structural change. Patch for bug fixes, minor for backward-compatible features, major for breaking or structural change.

## Directory map

```
expertsincmt/
├── style.css                 Theme header + Version (source of truth); no rules live here
├── style.min.css             Minified build output (npm run build)
├── theme.json                Global styles, palette, typography, layout
├── functions.php             Bootstrap: includes inc/*, enqueues assets, registers shortcodes
├── CHANGELOG.md              Keep a Changelog format; [Unreleased] at top
├── README.md                 This file
├── Short Code Library.md     Living reference of active shortcodes
│
├── templates/                Block templates (.html) + custom PHP templates (.php)
│   ├── archive-subtype.html, single-*.html, page*.html, 404.html, search.html ...
│   ├── header-banner.php             ACF-driven hero banner
│   ├── single-subtype.php            Subtype single wrapper
│   ├── subtype-fields-template.php   Subtype field rendering
│   ├── glossary-fields-template.php  Glossary field rendering
│   └── do-not-sell-modal.php         CCPA "do not sell" modal
│
├── parts/                    Block template parts (header, footer, vertical-header ...)
│
├── inc/                      All PHP logic, included from functions.php
│   ├── cpt/                  Custom post types: glossary, what-is-cmt, cmt-and-breathing
│   ├── taxonomies/           Subtype taxonomies, glossary letter, admin term ordering
│   ├── acf/                  ACF field groups + JSON-LD structured data per content type
│   ├── content/
│   │   ├── filters/          Genes + Dorsal Root + glossary filters, facet counts, totals
│   │   ├── loops/            Genes / DR / glossary loops + AJAX fragment partials
│   │   └── sort/             Genes type ordering
│   ├── ajax/                 AJAX endpoints for the filtered loops
│   ├── rest/                 REST endpoints (authority links, gene symbols)
│   ├── search/               Platform search (filter, render, variables, styles)
│   ├── shortcodes/           Shortcode registrations (see Short Code Library.md)
│   └── util/                 Shared helpers
│
├── assets/
│   ├── css/                  Component stylesheets (main.css is the bulk; per-feature files)
│   ├── js/                   AJAX loops, nav, return-state, modal scripts
│   ├── fonts/                Self-hosted variable fonts (woff2)
│   └── images/               Theme imagery (webp)
│
├── blocks/, patterns/, styles/, template-parts/   Block theme scaffolding
└── package.json              PostCSS/cssnano build for style.css -> style.min.css
```

## Core features

**Genes & Subtypes Database.** The centerpiece. A filterable, AJAX-paginated catalog of CMT subtypes rendered as cards (gene, discovery year, inheritance). Filters combine taxonomy selectors (type, inheritance, neuropathy, chromosome), a free-text search, and gene-group checkboxes (mitochondrial involvement, ARS genes, unknown gene). Every filter control shows filter-aware facet counts and disables dead-end options. Filter and search state travels in clean URL params that survive share and reload, and page-load and AJAX paths resolve to identical results. Rendered through `[genes_filter]` and `[genes_loop]`.

**Dorsal Root section and archive.** A three-feature section (`[dorsal_root_section]`) plus its own filtered, paginated post loop with category colors and a tablet-aware card grid.

**Custom post types and taxonomies.** Glossary (with letter taxonomy), What Is CMT, and CMT and Breathing, plus the subtype taxonomies that drive the genes filters.

**Header banner with per-breakpoint fade masks.** An ACF-driven hero (`[header_banner]`) whose fade gradient is independently tunable for desktop and mobile, with a live editor-only mask preview (mu-plugin) so the mask can be dialed in without a save-and-check loop.

**Structured data (JSON-LD).** Per-content-type schema output (subtype, pages, educational) for SEO and rich results.

**Platform search.** A dedicated search layer (`inc/search/`) with its own filter, renderer, and a large variables map, including a semantic ARS-gene branch.

**Context navigation.** A unified Previous / Back / Next component (`[context_nav]`) that works across post, subtype, glossary, and resource types.

**Admin maintenance tools (mu-plugins).** Kept in `wp-content/mu-plugins/`, not in this theme. The Yoast title space-fix tool (removes a stray space before `?` in SEO titles) and the banner mask editor preview live there. They follow a shared pattern: `manage_options`, nonce, a before/after dry run, and a confirm-gated commit.

Shortcodes are catalogued in `Short Code Library.md`; keep it current when adding or retiring one.

## Local development

Requires Node `>=20.10.0` and npm `>=10.2.3`.

```
npm install          # install PostCSS build tooling
npm run build        # minify style.css -> style.min.css
npm run watch        # re-minify on change
```

`style.css` is intentionally near-empty: it holds only the theme header. Real styling lives in `assets/css/*` (component files) and in `theme.json` global styles. On production, when `SCRIPT_DEBUG` is off, WordPress serves `style.min.css`, so run `npm run build` after touching `style.css`. Prefer the Site Editor or the per-feature CSS files over editing `style.css` directly.

Local runs on Local by Flywheel as `cmt-genes-clean`. On Local's nginx, `.htaccess` files are ignored; on production's Apache they are enforced, so a stray `.htaccess`/`.htpasswd` in the theme folder can silently gate assets behind HTTP Basic Auth. If production ever shows an unexpected login prompt or unstyled pages, check for those dotfiles first.

## Deployment

Production is Ionos, deployed by SFTP (FileZilla). Because all Site Editor content is baked into files, deploying is a file upload of the theme folder; there is no database step. After a release, upload the changed files, then re-snapshot `eic-shadow` for a notable release so the spare stays current.

## Releasing

Use the **version-bump skill**. In order: read the current `Version:` from `style.css`, decide patch/minor/major, bump only that line, roll `CHANGELOG.md` `[Unreleased]` into a dated `[X.Y.Z]` entry with a fresh empty `[Unreleased]` above it, then hand off to GitHub Desktop to commit `Release X.Y.Z` and create/push the tag `vX.Y.Z`. Git is done in GitHub Desktop, not from the theme tooling. Finish by deploying to production over SFTP and re-snapshotting the shadow when warranted.

## Git

The repository root is `wp-content`, and the tracked branch for day-to-day work is `dev`; releases merge to `main`. The `.gitignore` uses an "ignore everything, then whitelist" pattern that includes only `themes/expertsincmt/`, `mu-plugins/`, `.github/`, and the ACF local JSON. If GitHub Desktop shows "0 changes" after editing a theme file, confirm the whitelist still points at `expertsincmt` and not a stale slug.

## Conventions

- No em-dashes in project writing; use colons.
- LF line endings; no compiled artifacts committed beyond `style.min.css`.
- Prefix new PHP functions with `eic_` to avoid collisions.
- CMT is Charcot-Marie-Tooth disease (CMT), an inheritable disease.
