<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * MU Plugin: EIC Site Tools (hub)
 * ------------------------------------------------------------
 * Tools > Site Tools.
 *
 * One landing page for every EIC utility, in the style of the
 * Wordfence Login Security screen: a tab strip across the top
 * (All Tools + one tab per category), a title row with the
 * signed-in user, a status banner with the tool count and the
 * last tool opened, a card grid of tools, and a three-step
 * "How it works" strip (back up, dry run, commit).
 *
 * Tools are DISCOVERED, not listed. Anything registered under
 * Tools or Settings whose `page` slug starts with `eic-` shows up
 * automatically with its own title and capability. The registry
 * below only adds what WordPress does not know about a page:
 * category, one-line description, icon, and a badge. A page with
 * no registry entry lands in "Other tools" rather than vanishing.
 *
 * The tab strip is also rendered at the top of every tool page by
 * eic_admin_tool_open() (eic-admin-tools.php), so each tool looks
 * like a tab of the hub, with its category highlighted.
 *
 * Extend from another plugin:
 *     add_filter('eic_site_tools_registry', fn($r) => $r + [...]);
 *     add_filter('eic_site_tools_categories', fn($c) => $c + [...]);
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Site_Tools
{
    const CAP        = "manage_options";
    const SLUG       = "eic-site-tools";
    const LAST_META  = "eic_last_tool";
    const OTHER      = "other";

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"], 5);
        add_action("admin_init", [__CLASS__, "remember_last_tool"]);
    }

    /* ----------------------------------------------------------
       Menu
       ---------------------------------------------------------- */

    public static function menu(): void
    {
        add_management_page(
            "Site Tools",
            "Site Tools",
            self::CAP,
            self::SLUG,
            [__CLASS__, "render"],
            1
        );
    }

    /* ----------------------------------------------------------
       Categories + registry (metadata only)
       ---------------------------------------------------------- */

    /**
     * Category key => [label, dashicon]. Order here is tab order.
     */
    public static function categories(): array
    {
        $cats = [
            "backfills"     => ["Backfills",       "dashicons-database-add"],
            "urls"          => ["URL Builders",    "dashicons-admin-links"],
            "import-export" => ["Import & Export", "dashicons-database-export"],
            "maintenance"   => ["Maintenance",     "dashicons-admin-tools"],
            "media"         => ["Media",           "dashicons-format-image"],
            "search"        => ["Search & Index",  "dashicons-search"],
            self::OTHER     => ["Other tools",     "dashicons-admin-generic"],
        ];
        return (array) apply_filters("eic_site_tools_categories", $cats);
    }

    /**
     * Page slug => [category, description, dashicon, badge].
     * Badge is a short trust cue: what the tool does to data.
     */
    public static function registry(): array
    {
        $r = [
            // Backfills: fill or correct fields on existing records.
            "eic-gene-name-tool" => [
                "backfills",
                "Resolve each record's gene symbol to its full HGNC name and fill the gene_name field.",
                "dashicons-editor-spellcheck", "Dry-run gated",
            ],
            "eic-omim-tool" => [
                "backfills",
                "Fill phenotype and gene MIM numbers from the curated dataset and live HGNC lookups.",
                "dashicons-book-alt", "Dry-run gated",
            ],
            "eic-hgnc-ids-tool" => [
                "backfills",
                "Bulk-fetch HGNC, Ensembl, NCBI and UniProt identifiers for every gene.",
                "dashicons-id-alt", "Dry-run gated",
            ],
            "eic-external-records-backfill" => [
                "backfills",
                "Pull ClinGen validity and dosage, PanelApp, and Orphanet records into each gene.",
                "dashicons-cloud-saved", "Dry-run gated",
            ],
            "eic-genesis-backfill" => [
                "backfills",
                "Flag genes discovered through the GENESIS platform from TGP's public list.",
                "dashicons-flag", "Re-runnable",
            ],
            "eic-schema-backfill" => [
                "backfills",
                "Set the standard Schema.org defaults (specialty, audience, reviewer) on subtype records.",
                "dashicons-editor-code", "Dry-run gated",
            ],
            "eic-genereviews-corrections" => [
                "backfills",
                "Set or clear the GeneReviews URL on subtype records from a corrections list.",
                "dashicons-edit-page", "Dry-run gated",
            ],
            "eic-gene-posts" => [
                "backfills",
                "Create the shell gene post for every gene symbol that does not have one yet.",
                "dashicons-plus-alt", "Insert only",
            ],

            // URL builders.
            "eic-clinvar-url-tool" => [
                "urls",
                "Build and repair the ClinVar link on every record, with HGNC symbol normalisation.",
                "dashicons-admin-links", "Dry-run gated",
            ],
            "eic-clingen-url-tool" => [
                "urls",
                "Build the ClinGen curation link per gene; multi-curation genes are flagged to choose.",
                "dashicons-admin-links", "Dry-run gated",
            ],

            // Import & export.
            "eic-subtype-importer" => [
                "import-export",
                "Upsert subtype records from the authored JSON with a field-level diff.",
                "dashicons-upload", "Run once",
            ],
            "eic-subtype-export" => [
                "import-export",
                "Download the whole subtype database as one lossless JSON file.",
                "dashicons-download", "Read only",
            ],
            "eic-glossary-importer" => [
                "import-export",
                "Import authored glossary terms (JSON), matched by canonical term.",
                "dashicons-upload", "Run once",
            ],
            "eic-glossary-export" => [
                "import-export",
                "Download the whole glossary as one lossless JSON file.",
                "dashicons-download", "Read only",
            ],
            "eic-mechanism-importer" => [
                "import-export",
                "Import variant mechanism records from the authored dataset.",
                "dashicons-upload", "Run once",
            ],
            "eic-candidate-importer" => [
                "import-export",
                "Import candidate gene records from the authored dataset.",
                "dashicons-upload", "Run once",
            ],
            "eic-dataset-export" => [
                "import-export",
                "Generate the licensed CMT dataset (DLC) for public distribution.",
                "dashicons-media-spreadsheet", "Read only",
            ],

            // Maintenance.
            "eic-subtype-maintenance" => [
                "maintenance",
                "Scan the subtype database for known inconsistencies and apply targeted, reversible fixes.",
                "dashicons-admin-tools", "Scan first",
            ],
            "eic-body-maintenance" => [
                "maintenance",
                "Post-content hygiene passes across a chosen post type.",
                "dashicons-editor-paste-text", "Dry-run gated",
            ],
            "eic-yoast-title" => [
                "maintenance",
                "Remove the stray space before the question mark in Yoast SEO title templates.",
                "dashicons-editor-textcolor", "Dry-run gated",
            ],

            // Media.
            "eic-featured-image" => [
                "media",
                "Bulk-set the native featured image across every post of a chosen type.",
                "dashicons-format-image", "Dry-run gated",
            ],
            "eic-banner-image" => [
                "media",
                "Replace the header banner image across a post type from the media library.",
                "dashicons-cover-image", "Dry-run gated",
            ],

            // Search & index.
            "eic-variant-index" => [
                "search",
                "Status of the site-wide ClinVar P/LP variant index, with Rebuild Now.",
                "dashicons-list-view", "Rebuilds",
            ],
            "eic-search" => [
                "search",
                "Alias table and query log for the platform search resolver.",
                "dashicons-search", "Settings",
            ],
        ];
        return (array) apply_filters("eic_site_tools_registry", $r);
    }

    /* ----------------------------------------------------------
       Discovery
       ---------------------------------------------------------- */

    /**
     * Every EIC tool page the current user may open, keyed by slug:
     *   [title, url, category, description, icon, badge]
     * Walks the registered submenus so new tools appear automatically.
     */
    public static function tools(): array
    {
        global $submenu;

        $registry = self::registry();
        $cats     = self::categories();
        $out      = [];

        foreach (["tools.php", "options-general.php"] as $parent) {
            if (empty($submenu[$parent]) || !is_array($submenu[$parent])) {
                continue;
            }
            foreach ($submenu[$parent] as $item) {
                // $item = [menu_title, capability, slug, page_title]
                $slug = isset($item[2]) ? (string) $item[2] : "";
                if ($slug === "" || $slug === self::SLUG || strpos($slug, "eic-") !== 0) {
                    continue;
                }
                if (!current_user_can((string) $item[1])) {
                    continue;
                }
                $meta = $registry[$slug] ?? [self::OTHER, "", "", ""];
                $cat  = isset($cats[$meta[0]]) ? $meta[0] : self::OTHER;
                $out[$slug] = [
                    "title"    => wp_strip_all_tags((string) ($item[3] ?? $item[0])),
                    "url"      => add_query_arg("page", $slug, admin_url($parent)),
                    "category" => $cat,
                    "desc"     => (string) ($meta[1] ?? ""),
                    "icon"     => ($meta[2] ?? "") !== "" ? $meta[2] : $cats[$cat][1],
                    "badge"    => (string) ($meta[3] ?? ""),
                ];
            }
        }

        uasort($out, fn($a, $b) => strcasecmp($a["title"], $b["title"]));
        return $out;
    }

    /**
     * Category of a given tool slug (for the tab strip on tool pages).
     */
    public static function category_of(string $slug): string
    {
        $registry = self::registry();
        $cats     = self::categories();
        $cat      = $registry[$slug][0] ?? self::OTHER;
        return isset($cats[$cat]) ? $cat : self::OTHER;
    }

    /* ----------------------------------------------------------
       Last tool opened (per user)
       ---------------------------------------------------------- */

    public static function remember_last_tool(): void
    {
        if (!function_exists("eic_admin_tools_is_tool_page") || !eic_admin_tools_is_tool_page()) {
            return;
        }
        $slug = sanitize_key(wp_unslash($_GET["page"]));
        if ($slug === self::SLUG) {
            return;
        }
        update_user_meta(get_current_user_id(), self::LAST_META, [
            "slug" => $slug,
            "time" => time(),
        ]);
    }

    private static function last_tool(array $tools): ?array
    {
        $last = get_user_meta(get_current_user_id(), self::LAST_META, true);
        if (!is_array($last) || empty($last["slug"]) || !isset($tools[$last["slug"]])) {
            return null;
        }
        return ["tool" => $tools[$last["slug"]], "time" => (int) ($last["time"] ?? 0)];
    }

    /* ----------------------------------------------------------
       Tab strip (shared with every tool page)
       ---------------------------------------------------------- */

    /**
     * Render the tab strip. $active is a category key, or "all" for
     * the hub's All Tools view.
     */
    public static function tabs(string $active = "all"): void
    {
        $hub  = add_query_arg("page", self::SLUG, admin_url("tools.php"));
        $cats = self::categories();
        $used = array_count_values(array_column(self::tools(), "category"));

        echo '<nav class="eic-tabs" aria-label="Site Tools">';
        self::tab($hub, "dashicons-screenoptions", "All Tools", $active === "all");
        foreach ($cats as $key => [$label, $icon]) {
            if (empty($used[$key])) {
                continue; // no tools in this category for this user
            }
            self::tab(add_query_arg("tab", $key, $hub), $icon, $label, $active === $key);
        }
        echo '<span class="eic-tabs__brand">Experts in CMT</span>';
        echo "</nav>";
    }

    private static function tab(string $url, string $icon, string $label, bool $active): void
    {
        printf(
            '<a class="eic-tabs__tab%s" href="%s"%s><span class="dashicons %s" aria-hidden="true"></span>%s</a>',
            $active ? " is-active" : "",
            esc_url($url),
            $active ? ' aria-current="page"' : "",
            esc_attr($icon),
            esc_html($label)
        );
    }

    /* ----------------------------------------------------------
       Hub page
       ---------------------------------------------------------- */

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }

        $cats  = self::categories();
        $tools = self::tools();
        $tab   = isset($_GET["tab"]) ? sanitize_key(wp_unslash($_GET["tab"])) : "all";
        if ($tab !== "all" && !isset($cats[$tab])) {
            $tab = "all";
        }

        $user = wp_get_current_user();
        $env  = function_exists("wp_get_environment_type") ? wp_get_environment_type() : "production";
        $last = self::last_tool($tools);

        echo '<div class="wrap eic-tool eic-tool--hub">';
        self::tabs($tab);
        echo '<div class="eic-tool__panel">';

        /* ---- Title row ---- */
        echo '<div class="eic-hub__head">';
        echo '<div class="eic-hub__titlewrap">';
        echo '<h1 class="eic-tool__title">Site Tools</h1>';
        echo '<span class="eic-pill">' . get_avatar($user->ID, 22) .
            '<span class="eic-pill__label">User:</span> <code>' . esc_html($user->user_login) . ' (you)</code></span>';
        echo '<span class="eic-pill eic-pill--env"><span class="eic-pill__label">Environment:</span> <code>' .
            esc_html($env) . "</code></span>";
        echo "</div>";
        echo '<a class="eic-hub__learn" href="#eic-how-it-works"><span class="dashicons dashicons-info" aria-hidden="true"></span> How these tools work</a>';
        echo "</div>";
        echo '<p class="eic-tool__sub">Utilities for the Experts in CMT platform: backfills, importers and exporters, ' .
            "maintenance passes, and the search index. Write tools are dry-run gated: nothing changes until you commit.</p>";

        /* ---- Status banner ---- */
        $count = count($tools);
        echo '<div class="eic-status">';
        echo '<div class="eic-status__icon"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></div>';
        echo '<div class="eic-status__text">';
        printf(
            '<strong>%s available to you.</strong>',
            esc_html(sprintf(_n("%d tool", "%d tools", $count), $count))
        );
        if ($last) {
            printf(
                "<p>Last opened: <a href=\"%s\">%s</a>, %s ago.</p>",
                esc_url($last["tool"]["url"]),
                esc_html($last["tool"]["title"]),
                esc_html(human_time_diff($last["time"], time()))
            );
        } else {
            echo "<p>Pick a tool below, or jump straight to the ongoing maintenance scan.</p>";
        }
        echo "</div>";
        echo '<div class="eic-status__actions">';
        if ($last) {
            printf(
                '<a class="button button-primary" href="%s"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> Resume %s</a>',
                esc_url($last["tool"]["url"]),
                esc_html($last["tool"]["title"])
            );
        }
        if (isset($tools["eic-subtype-maintenance"])) {
            printf(
                '<a class="button%s" href="%s">Subtype Maintenance</a>',
                $last ? "" : " button-primary",
                esc_url($tools["eic-subtype-maintenance"]["url"])
            );
        }
        if (isset($tools["eic-subtype-export"])) {
            printf(
                '<a class="button" href="%s">Back up: Subtype Export</a>',
                esc_url($tools["eic-subtype-export"]["url"])
            );
        }
        echo "</div>";
        echo "</div>"; // .eic-status

        /* ---- Tool cards ---- */
        if (!$tools) {
            echo '<p class="eic-hub__empty">No EIC tools are registered for your account.</p>';
        } else {
            foreach ($cats as $key => [$label, $icon]) {
                if ($tab !== "all" && $tab !== $key) {
                    continue;
                }
                $group = array_filter($tools, fn($t) => $t["category"] === $key);
                if (!$group) {
                    continue;
                }
                echo '<section class="eic-hub__group">';
                echo '<h2 class="eic-hub__heading">' . esc_html($label) .
                    ' <span class="eic-hub__count">' . count($group) . "</span></h2>";
                echo '<div class="eic-grid">';
                foreach ($group as $slug => $t) {
                    self::card($slug, $t);
                }
                echo "</div>";
                echo "</section>";
            }
        }

        /* ---- How it works ---- */
        echo '<section class="eic-hub__group" id="eic-how-it-works">';
        echo '<h2 class="eic-hub__heading">How it works</h2>';
        echo '<ol class="eic-steps">';
        self::step(1, "Back up first",
            "Take a fresh database backup, or run the Subtype and Glossary exports, before any commit.");
        self::step(2, "Dry run",
            "Every write tool previews current versus proposed values for each record. Nothing is written.");
        self::step(3, "Commit",
            "Review the preview, tick the confirm box, then commit. Run-once importers say so on their card.");
        echo "</ol>";
        echo "</section>";

        echo "</div>"; // .eic-tool__panel
        echo "</div>"; // .eic-tool.wrap
    }

    private static function card(string $slug, array $t): void
    {
        echo '<article class="eic-card">';
        echo '<div class="eic-card__icon"><span class="dashicons ' . esc_attr($t["icon"]) . '" aria-hidden="true"></span></div>';
        echo '<div class="eic-card__body">';
        echo '<h3 class="eic-card__title"><a href="' . esc_url($t["url"]) . '">' . esc_html($t["title"]) . "</a></h3>";
        if ($t["desc"] !== "") {
            echo '<p class="eic-card__desc">' . esc_html($t["desc"]) . "</p>";
        }
        echo '<div class="eic-card__foot">';
        if ($t["badge"] !== "") {
            echo '<span class="eic-badge eic-badge--' . esc_attr(sanitize_title($t["badge"])) . '">' .
                esc_html($t["badge"]) . "</span>";
        }
        echo '<span class="eic-card__open">Open <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>';
        echo "</div>";
        echo "</div>";
        echo "</article>";
    }

    private static function step(int $n, string $title, string $text): void
    {
        echo '<li class="eic-step">';
        echo '<span class="eic-step__num">' . (int) $n . "</span>";
        echo '<div class="eic-step__text"><strong>' . esc_html($title) . "</strong><p>" . esc_html($text) . "</p></div>";
        echo "</li>";
    }
}

EIC_Site_Tools::init();
