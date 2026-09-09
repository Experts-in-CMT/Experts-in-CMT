<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*
 * ------------------------------------------------------------
 * MU Plugin: EIC Search Tools
 * ------------------------------------------------------------
 * Purpose:
 * • Admin-managed alias table for the platform search resolver
 *   (Settings → EIC Search). Each row maps a term patients type
 *   to typed targets: subtype:cmt2a, gene:mfn2, post:slug,
 *   page:slug, content:slug, type:cmt2, search:phrase,
 *   highlight:A|B. Bare tokens become highlight terms.
 * • Search query log (custom table) with zero-result rollup,
 *   recent-search readout, 90-day retention, and CSV export.
 *
 * Notes:
 * • The theme's resolver consumes eic_search_alias_entries();
 *   rows compile into the same entry shape as the code-side
 *   semantic table, and run BEFORE it (admin overrides code).
 * • Log rows are query text only: no IPs, users, or sessions.
 *   Administrator searches are skipped by default (filterable).
 * • MU-plugin stays self-contained: no theme dependencies.
 */

if (!defined("ABSPATH")) {
    exit();
}

/* ============================================================
   CONSTANTS + VERSIONING
   ============================================================ */

const EIC_SEARCH_ALIASES_OPTION = "eic_search_aliases";
const EIC_SEARCH_ALIASES_VER_OPTION = "eic_search_aliases_ver";
const EIC_SEARCH_LOG_SCHEMA_OPTION = "eic_search_log_schema";
const EIC_SEARCH_LOG_SCHEMA = 1;

/**
 * Alias-table version, folded into the platform search cache key
 * so saving aliases invalidates cached search payloads instantly.
 */
function eic_search_alias_ver(): int
{
    return (int) get_option(EIC_SEARCH_ALIASES_VER_OPTION, 1);
}

function eic_search_alias_bump_ver(): void
{
    update_option(
        EIC_SEARCH_ALIASES_VER_OPTION,
        eic_search_alias_ver() + 1,
        false
    );
}

/* ============================================================
   LOG TABLE (created/updated via schema version check)
   ============================================================ */

function eic_search_log_table(): string
{
    global $wpdb;
    return $wpdb->prefix . "eic_search_log";
}

function eic_search_log_maybe_install(): void
{
    if (
        (int) get_option(EIC_SEARCH_LOG_SCHEMA_OPTION, 0) ===
        EIC_SEARCH_LOG_SCHEMA
    ) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . "wp-admin/includes/upgrade.php";

    $table = eic_search_log_table();
    $charset = $wpdb->get_charset_collate();

    dbDelta(
        "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            query_raw VARCHAR(191) NOT NULL DEFAULT '',
            query_normalized VARCHAR(191) NOT NULL DEFAULT '',
            resolver VARCHAR(60) NOT NULL DEFAULT '',
            result_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(10) NOT NULL DEFAULT 'page',
            created DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY normalized (query_normalized),
            KEY created (created)
        ) {$charset};"
    );

    update_option(EIC_SEARCH_LOG_SCHEMA_OPTION, EIC_SEARCH_LOG_SCHEMA, true);
}
add_action("init", "eic_search_log_maybe_install", 5);

/* ============================================================
   LOGGING API
   ============================================================ */

/**
 * Record one search event. Query text only: no visitor identity.
 *
 * @param array $args {raw, normalized, resolver, results, source}
 */
function eic_search_log_event(array $args): void
{
    if (!apply_filters("eic_search_log_enabled", true)) {
        return;
    }

    // Keep the dataset patient-realistic: skip admin test searches
    if (
        apply_filters("eic_search_log_skip_admins", true) &&
        current_user_can("manage_options")
    ) {
        return;
    }

    $raw = trim((string) ($args["raw"] ?? ""));

    if (mb_strlen($raw) < 2) {
        return;
    }

    global $wpdb;

    $wpdb->insert(
        eic_search_log_table(),
        [
            "query_raw" => mb_substr($raw, 0, 191),
            "query_normalized" => mb_substr(
                trim((string) ($args["normalized"] ?? "")),
                0,
                191
            ),
            "resolver" => mb_substr(
                trim((string) ($args["resolver"] ?? "")),
                0,
                60
            ),
            "result_count" => max(0, (int) ($args["results"] ?? 0)),
            "source" =>
                ($args["source"] ?? "page") === "live" ? "live" : "page",
            "created" => current_time("mysql"),
        ],
        ["%s", "%s", "%s", "%d", "%s", "%s"]
    );
}

/**
 * Retention sweep: drop rows older than the retention window.
 * Runs when the admin page renders: no cron required.
 */
function eic_search_log_purge(): void
{
    global $wpdb;

    $days = max(7, (int) apply_filters("eic_search_log_retention_days", 90));

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM " .
                eic_search_log_table() .
                " WHERE created < DATE_SUB(%s, INTERVAL %d DAY)",
            current_time("mysql"),
            $days
        )
    );
}

/* ============================================================
   ALIAS ROWS → RESOLVER ENTRIES
   ============================================================ */

/**
 * Split a comma-separated field into trimmed values.
 */
function eic_search_alias_csv(string $value): array
{
    return array_values(
        array_filter(array_map("trim", explode(",", $value)), function ($v) {
            return $v !== "";
        })
    );
}

/**
 * Normalize one saved row into the per-bucket shape:
 *   [term, mode, notes, subtypes, genes, types, content, search, highlight]
 * Rows saved by the original one-field UI carried a typed "map"
 * string instead; those parse into buckets here so nothing breaks.
 */
function eic_search_alias_row_normalize(array $row): array
{
    $out = [
        "term" => trim((string) ($row["term"] ?? "")),
        "mode" => ($row["mode"] ?? "exact") === "anywhere" ? "anywhere" : "exact",
        "notes" => trim((string) ($row["notes"] ?? "")),
        "subtypes" => trim((string) ($row["subtypes"] ?? "")),
        "genes" => trim((string) ($row["genes"] ?? "")),
        "types" => trim((string) ($row["types"] ?? "")),
        "content" => trim((string) ($row["content"] ?? "")),
        "search" => trim((string) ($row["search"] ?? "")),
        "highlight" => trim((string) ($row["highlight"] ?? "")),
    ];

    // Legacy one-field rows: parse "map" tokens into the buckets
    $map = trim((string) ($row["map"] ?? ""));

    if ($map !== "") {
        $buckets = [
            "subtypes" => [],
            "genes" => [],
            "types" => [],
            "content" => [],
            "search" => [],
            "highlight" => [],
        ];

        foreach (explode(",", $map) as $token) {
            $token = trim($token);
            if ($token === "") {
                continue;
            }

            $parts = explode(":", $token, 2);
            $kind = strtolower(trim($parts[0]));
            $val = trim($parts[1] ?? "");

            if ($val === "") {
                $buckets["highlight"][] = trim($parts[0]);
            } elseif ($kind === "subtype") {
                $buckets["subtypes"][] = $val;
            } elseif ($kind === "gene") {
                $buckets["genes"][] = $val;
            } elseif ($kind === "type") {
                $buckets["types"][] = $val;
            } elseif (in_array($kind, ["post", "page", "content"], true)) {
                $buckets["content"][] = $val;
            } elseif ($kind === "search") {
                $buckets["search"][] = $val;
            } elseif ($kind === "highlight") {
                foreach (explode("|", $val) as $h) {
                    $h = trim($h);
                    if ($h !== "") {
                        $buckets["highlight"][] = $h;
                    }
                }
            }
        }

        foreach ($buckets as $key => $vals) {
            if ($out[$key] === "" && !empty($vals)) {
                $out[$key] = implode(", ", $vals);
            }
        }
    }

    return $out;
}

/**
 * True when a row maps to anything at all.
 */
function eic_search_alias_row_has_targets(array $row): bool
{
    foreach (["subtypes", "genes", "types", "content", "search", "highlight"] as $k) {
        if (($row[$k] ?? "") !== "") {
            return true;
        }
    }

    return false;
}

/**
 * Compile saved alias rows into semantic-table-shaped entries.
 * The theme merges these AHEAD of its code table, so an admin row
 * can override a code entry for the same term.
 */
function eic_search_alias_entries(): array
{
    static $entries = null;

    if (is_array($entries)) {
        return $entries;
    }

    $entries = [];
    $rows = get_option(EIC_SEARCH_ALIASES_OPTION, []);

    if (!is_array($rows)) {
        return $entries;
    }

    foreach ($rows as $i => $raw_row) {
        if (!is_array($raw_row)) {
            continue;
        }

        $row = eic_search_alias_row_normalize($raw_row);

        if ($row["term"] === "" || !eic_search_alias_row_has_targets($row)) {
            continue;
        }

        // Normalize the term the way the resolver normalizes queries
        $term_norm = strtolower(
            trim(
                preg_replace(
                    "/\s+/",
                    " ",
                    str_replace(["-", "_"], " ", remove_accents($row["term"]))
                )
            )
        );

        if ($term_norm === "") {
            continue;
        }

        $collapsed = str_replace(" ", "", $term_norm);

        // Exact single words match whole tokens; phrases and
        // "anywhere" rows match the space-collapsed query
        $match =
            $row["mode"] === "exact" && strpos($term_norm, " ") === false
                ? ["tokens" => [$term_norm]]
                : ["substrings" => [$collapsed]];

        $payload = [
            "meta" => [
                "label" => $row["term"],
                "note" => "admin alias",
            ],
        ];
        $highlight = [$row["term"]];

        foreach (eic_search_alias_csv($row["subtypes"]) as $slug) {
            $payload["subtypes"][] = [strtolower($slug), "subtype"];
        }

        foreach (eic_search_alias_csv($row["genes"]) as $symbol) {
            $symbol = strtoupper($symbol);

            // Delivered VERBATIM to the Gene bucket: the association
            // is the curator's, not the tool's. If the symbol is also
            // a current causative gene, its subtypes come along
            // silently; if not, the label still renders as declared.
            $payload["meta"]["gene_labels"][] = $symbol;
            $payload["subtypes"][] = [$symbol, "gene_symbol"];
            $highlight[] = $symbol;
        }

        foreach (eic_search_alias_csv($row["types"]) as $type) {
            $payload["types"][] = strtolower($type);
        }

        foreach (eic_search_alias_csv($row["content"]) as $slug) {
            // "any" tries each content type in turn (theme resolver)
            $payload["content"][] = [$slug, "any"];
        }

        foreach (eic_search_alias_csv($row["highlight"]) as $term) {
            $highlight[] = $term;
        }

        $entry = [
            "match" => $match,
            "payload" => $payload,
            "highlight" => array_values(array_unique($highlight)),
        ];

        $search_phrases = eic_search_alias_csv($row["search"]);
        if (!empty($search_phrases)) {
            $entry["content_search"] = $search_phrases;
        }

        $entries["admin_" . $i . "_" . $collapsed] = $entry;
    }

    return $entries;
}

/**
 * Validate one normalized row. Returns human-readable warnings,
 * each prefixed with the field it belongs to, for targets that do
 * not resolve to real records. Search phrases and highlight terms
 * are free text and need no validation.
 */
function eic_search_alias_validate_row(array $row): array
{
    $warnings = [];
    $content_types = ["post", "page", "what-is-cmt", "breathing", "glossary"];

    foreach (eic_search_alias_csv($row["subtypes"] ?? "") as $slug) {
        if (!get_page_by_path(strtolower($slug), OBJECT, "subtype")) {
            $warnings[] = "Subtypes: no subtype record with slug “{$slug}”";
        }
    }

    // Genes are deliberately NOT validated: the field delivers the
    // declared symbols verbatim (retracted/historical genes included).

    $known_types = function_exists("eic_ps_type_classification_anchors")
        ? array_keys(eic_ps_type_classification_anchors())
        : [];

    foreach (eic_search_alias_csv($row["types"] ?? "") as $type) {
        if (
            !empty($known_types) &&
            !in_array(strtolower($type), $known_types, true)
        ) {
            $warnings[] = "Types: “{$type}” is not a known type classification";
        }
    }

    foreach (eic_search_alias_csv($row["content"] ?? "") as $slug) {
        $found = false;
        foreach ($content_types as $pt) {
            if (get_page_by_path($slug, OBJECT, $pt)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $warnings[] = "Content: no published content with slug “{$slug}”";
        }
    }

    return $warnings;
}

/* ============================================================
   ADMIN PAGE (Settings → EIC Search)
   ============================================================ */

function eic_search_tools_admin_menu(): void
{
    add_options_page(
        "EIC Search",
        "EIC Search",
        "manage_options",
        "eic-search",
        "eic_search_tools_render_page"
    );
}
add_action("admin_menu", "eic_search_tools_admin_menu");

/**
 * Handle the alias-table save (runs before render on POST).
 * Returns [saved(bool), warnings(array)].
 */
function eic_search_tools_handle_save(): array
{
    if (
        empty($_POST["eic_search_aliases_save"]) ||
        !current_user_can("manage_options")
    ) {
        return [false, []];
    }

    check_admin_referer("eic_search_aliases");

    $in = $_POST["alias"] ?? [];
    $rows = [];
    $warnings = [];

    if (is_array($in)) {
        foreach ($in as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $row = [];
            foreach (
                [
                    "term",
                    "notes",
                    "subtypes",
                    "genes",
                    "types",
                    "content",
                    "search",
                    "highlight",
                ]
                as $field
            ) {
                $row[$field] = sanitize_text_field(
                    (string) ($raw[$field] ?? "")
                );
            }
            $row["mode"] =
                ($raw["mode"] ?? "exact") === "anywhere" ? "anywhere" : "exact";

            $has_targets = eic_search_alias_row_has_targets($row);

            if (trim($row["term"]) === "" && !$has_targets) {
                continue; // blank row
            }

            if (trim($row["term"]) === "" || !$has_targets) {
                $warnings[] =
                    "Row skipped: a term and at least one target are required.";
                continue;
            }

            foreach (eic_search_alias_validate_row($row) as $w) {
                $warnings[] = "“{$row["term"]}”: {$w}";
            }

            $rows[] = $row;
        }
    }

    update_option(EIC_SEARCH_ALIASES_OPTION, $rows, false);
    eic_search_alias_bump_ver();

    return [true, $warnings];
}

function eic_search_tools_render_page(): void
{
    if (!current_user_can("manage_options")) {
        return;
    }

    [$saved, $warnings] = eic_search_tools_handle_save();

    $tab = isset($_GET["tab"]) && $_GET["tab"] === "log" ? "log" : "aliases";
    $base = admin_url("options-general.php?page=eic-search");
    ?>
    <div class="wrap">
        <h1>EIC Search</h1>

        <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible"><p>Alias table saved. Search caches refreshed.</p></div>
        <?php endif; ?>

        <?php foreach ($warnings as $w): ?>
            <div class="notice notice-warning"><p><?php echo esc_html(
                $w
            ); ?></p></div>
        <?php endforeach; ?>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(
                $base
            ); ?>" class="nav-tab <?php echo $tab === "aliases"
    ? "nav-tab-active"
    : ""; ?>">Aliases</a>
            <a href="<?php echo esc_url(
                $base . "&tab=log"
            ); ?>" class="nav-tab <?php echo $tab === "log"
    ? "nav-tab-active"
    : ""; ?>">Search Log</a>
        </h2>

        <?php $tab === "log"
            ? eic_search_tools_render_log_tab()
            : eic_search_tools_render_alias_tab(); ?>
    </div>
    <?php
}

function eic_search_tools_render_alias_tab(): void
{
    $rows = get_option(EIC_SEARCH_ALIASES_OPTION, []);
    if (!is_array($rows)) {
        $rows = [];
    }

    // Normalize for display (also migrates legacy one-field rows)
    $rows = array_values(array_map("eic_search_alias_row_normalize", $rows));

    if (empty($rows)) {
        $rows = [eic_search_alias_row_normalize([])];
    }

    // [field, label, placeholder, hint]
    $buckets = [
        [
            "subtypes",
            "Subtypes",
            "cmt2a, cmt2a2b",
            "subtype slugs",
        ],
        [
            "genes",
            "Genes",
            "MFN2",
            "gene symbols, shown as declared; current symbols also pull their subtypes",
        ],
        [
            "types",
            "Types",
            "cmt2",
            "type classifications",
        ],
        [
            "content",
            "Pinned content",
            "2a-confliction",
            "post or page slugs, shown first",
        ],
        [
            "search",
            "Search phrases",
            "kif1b",
            "also pull prose mentioning these",
        ],
        [
            "highlight",
            "Highlight terms",
            "KIF1B, CMT2A",
            "marked in excerpts (the term itself always is)",
        ],
    ];
    ?>
    <p>
        Map the terms patients type to what they should find. Fill in any
        fields that apply; separate multiple values with commas. Rows here
        run BEFORE the built-in vocabulary and take effect immediately
        on save.
    </p>

    <style>
        .eic-alias-card { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:12px 16px 16px; margin-bottom:12px; }
        .eic-alias-card .eic-alias-head { display:flex; gap:12px; align-items:flex-end; margin-bottom:4px; }
        .eic-alias-card .eic-alias-head .eic-alias-term { flex:0 0 220px; }
        .eic-alias-card .eic-alias-head .eic-alias-notes { flex:1; }
        .eic-alias-card .eic-alias-buckets { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px 12px; }
        .eic-alias-card label { display:block; font-weight:600; margin:6px 0 2px; }
        .eic-alias-card .eic-hint { font-weight:400; color:#646970; }
        .eic-alias-card input[type=text] { width:100%; }
        @media (max-width: 1100px) { .eic-alias-card .eic-alias-buckets { grid-template-columns:repeat(2, 1fr); } }
    </style>

    <form method="post">
        <?php wp_nonce_field("eic_search_aliases"); ?>
        <input type="hidden" name="eic_search_aliases_save" value="1" />

        <div id="eic-alias-cards">
        <?php foreach ($rows as $i => $row): ?>
            <div class="eic-alias-card">
                <div class="eic-alias-head">
                    <div class="eic-alias-term">
                        <label>Term</label>
                        <input type="text"
                            name="alias[<?php echo (int) $i; ?>][term]"
                            value="<?php echo esc_attr($row["term"]); ?>"
                            placeholder="KIF1B" />
                    </div>
                    <div>
                        <label>Match</label>
                        <select name="alias[<?php echo (int) $i; ?>][mode]">
                            <option value="exact" <?php selected(
                                $row["mode"] === "exact"
                            ); ?>>Exact word</option>
                            <option value="anywhere" <?php selected(
                                $row["mode"] === "anywhere"
                            ); ?>>Anywhere</option>
                        </select>
                    </div>
                    <div class="eic-alias-notes">
                        <label>Notes</label>
                        <input type="text"
                            name="alias[<?php echo (int) $i; ?>][notes]"
                            value="<?php echo esc_attr($row["notes"]); ?>" />
                    </div>
                    <div>
                        <button type="button" class="button eic-alias-remove" title="Remove row">✕</button>
                    </div>
                </div>

                <div class="eic-alias-buckets">
                    <?php foreach ($buckets as [$field, $label, $ph, $hint]): ?>
                        <div>
                            <label><?php echo esc_html($label); ?>
                                <span class="eic-hint">· ?php echo esc_html(
                                    $hint
                                ); ?></span></label>
                            <input type="text"
                                name="alias[<?php echo (int) $i; ?>][<?php echo esc_attr(
    $field
); ?>]"
                                value="<?php echo esc_attr($row[$field]); ?>"
                                placeholder="<?php echo esc_attr($ph); ?>" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button" id="eic-alias-add">Add row</button>
            <?php submit_button("Save Aliases", "primary", "submit", false); ?>
        </p>
    </form>

    <script>
    (function () {
        var wrap = document.getElementById('eic-alias-cards');

        document.getElementById('eic-alias-add').addEventListener('click', function () {
            var cards = wrap.querySelectorAll('.eic-alias-card');
            var next = cards[cards.length - 1].cloneNode(true);
            var index = cards.length;

            next.querySelectorAll('input, select').forEach(function (el) {
                el.name = el.name.replace(/\[\d+\]/, '[' + index + ']');
                if (el.tagName === 'INPUT') { el.value = ''; }
                else { el.selectedIndex = 0; }
            });
            wrap.appendChild(next);
        });

        wrap.addEventListener('click', function (e) {
            if (!e.target.classList.contains('eic-alias-remove')) { return; }
            var cards = wrap.querySelectorAll('.eic-alias-card');
            if (cards.length > 1) { e.target.closest('.eic-alias-card').remove(); }
            else {
                cards[0].querySelectorAll('input').forEach(function (el) { el.value = ''; });
            }
        });
    })();
    </script>
    <?php
}

function eic_search_tools_render_log_tab(): void
{
    global $wpdb;

    eic_search_log_purge();

    $table = eic_search_log_table();

    $zero = $wpdb->get_results(
        "SELECT query_normalized, COUNT(*) AS hits, MAX(created) AS last_seen
         FROM {$table}
         WHERE result_count = 0
           AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY query_normalized
         ORDER BY hits DESC, last_seen DESC
         LIMIT 50"
    );

    $recent = $wpdb->get_results(
        "SELECT query_raw, resolver, result_count, source, created
         FROM {$table}
         ORDER BY id DESC
         LIMIT 50"
    );

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

    $export_url = wp_nonce_url(
        admin_url("admin-post.php?action=eic_search_log_export"),
        "eic_search_log_export"
    );
    ?>
    <p>
        <?php echo (int) $total; ?> logged searches (query text only, no
        visitor identity; administrator searches are not logged; rows older
        than 90 days are pruned automatically).
        <a class="button" href="<?php echo esc_url(
            $export_url
        ); ?>">Download CSV</a>
    </p>

    <h3>Zero-result queries, last 30 days</h3>
    <?php if (empty($zero)): ?>
        <p>None: every logged search returned something.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Query</th><th style="width:10%">Hits</th><th style="width:22%">Last seen</th></tr></thead>
            <tbody>
            <?php foreach ($zero as $r): ?>
                <tr>
                    <td><?php echo esc_html($r->query_normalized); ?></td>
                    <td><?php echo (int) $r->hits; ?></td>
                    <td><?php echo esc_html($r->last_seen); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Recent searches</h3>
    <?php if (empty($recent)): ?>
        <p>No searches logged yet.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Query</th><th>Resolved as</th><th style="width:10%">Results</th><th style="width:8%">Source</th><th style="width:20%">When</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?php echo esc_html($r->query_raw); ?></td>
                    <td><?php echo esc_html($r->resolver); ?></td>
                    <td><?php echo (int) $r->result_count; ?></td>
                    <td><?php echo esc_html($r->source); ?></td>
                    <td><?php echo esc_html($r->created); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
}

/* ============================================================
   CSV EXPORT (admin-post)
   ============================================================ */

/**
 * Neutralize spreadsheet formula injection (CWE-1236): log rows
 * are anonymous visitor text, and a leading = + - @ (or tab/CR)
 * would execute as a formula when the admin opens the CSV in
 * Excel/Sheets. Prefix with a single quote so it renders as text.
 */
function eic_search_csv_neutralize(string $value): string
{
    return preg_replace('/^([=+\-@\t\r])/', "'$1", $value);
}

function eic_search_log_export(): void
{
    if (!current_user_can("manage_options")) {
        wp_die("Not allowed.");
    }

    check_admin_referer("eic_search_log_export");

    global $wpdb;
    $table = eic_search_log_table();

    nocache_headers();
    header("Content-Type: text/csv; charset=utf-8");
    header(
        'Content-Disposition: attachment; filename="eic-search-log-' .
            gmdate("Ymd-His") .
            '.csv"'
    );

    $out = fopen("php://output", "w");
    fputcsv($out, [
        "id",
        "query_raw",
        "query_normalized",
        "resolver",
        "result_count",
        "source",
        "created",
    ]);

    // Chunked read keeps memory flat regardless of log size
    $last_id = 0;
    do {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, query_raw, query_normalized, resolver,
                        result_count, source, created
                 FROM {$table}
                 WHERE id > %d
                 ORDER BY id ASC
                 LIMIT 500",
                $last_id
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $last_id = (int) $row["id"];

            // Visitor-controlled text fields get formula-neutralized
            foreach (["query_raw", "query_normalized", "resolver"] as $f) {
                $row[$f] = eic_search_csv_neutralize((string) $row[$f]);
            }

            fputcsv($out, $row);
        }
    } while (count($rows) === 500);

    fclose($out);
    exit();
}
add_action("admin_post_eic_search_log_export", "eic_search_log_export");
