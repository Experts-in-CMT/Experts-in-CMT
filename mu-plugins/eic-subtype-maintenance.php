<?php
/**
 * Plugin Name: EIC Subtype Maintenance
 * Description: Scans the Subtype database for known data inconsistencies and applies targeted, reversible fixes. Scan-first, apply per-check. Designed as an ongoing maintenance tool.
 * Version: 1.0.0
 * Author: Kenneth Raymond
 *
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform (mu-plugin).
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * Subtype Maintenance — Consistency Scanner & Fixer
 * ------------------------------------------------------------
 * Checks (each scanned read-only on load, applied only on demand):
 *   1. inheritance  ACF field reconciled to the inheritance taxonomy
 *      (taxonomy authoritative; also repairs JSON-LD + Yoast desc which
 *      read the ACF field via %%cf_inheritance%%)
 *   2. neuropathy   ACF <-> taxonomy reconciled (taxonomy authoritative
 *      when present; taxonomy backfilled from ACF when missing)
 *   3. glossary     legacy /glossary/dominant/ link corrected to
 *      /glossary/autosomal-dominant/ in post_content
 *   4. yoast_vars   meta/OG/Twitter description templates standardized
 *      onto resolving variables (%%cf_gene_symbol%%, %%cf_inheritance%%)
 *   5. focuskw      focus keyword normalized to "What Is {Subtype}?".
 *      Single-clause entries are normalized in place. Multi-clause entries
 *      are split: canonical primary keyphrase + full-phrase synonyms merged
 *      (de-duplicated) into _yoast_wpseo_keywordsynonyms.
 *   6. internal_links  internal cross-link markup normalized: CMS artifact
 *      attributes (type, id) stripped from anchors pointing inside the site,
 *      preserving href, anchor text, and any valid attribute. External links
 *      untouched.
 *
 * Safety:
 *   - Read-only scan is the default view. Nothing writes without an
 *     explicit per-check Apply + nonce.
 *   - Take a database backup before applying. Every change is reversible
 *     from a backup.
 *
 * Location:
 *   /wp-content/mu-plugins/eic-subtype-maintenance.php
 *
 * Usage:
 *   Tools -> Subtype Maintenance
 */

if (!defined("ABSPATH")) {
    exit();
}

const EIC_MAINT_PT = "subtype";
const EIC_MAINT_CAP = "manage_options";
const EIC_MAINT_SLUG = "eic-subtype-maintenance";

/**
 * Legacy -> canonical link slugs to correct in post_content.
 * Extend this map as future bad links are identified. While empty, the
 * glossary link check hides itself from the page (see the render loop).
 * The original resolved entry, for reference:
 *   "/glossary/dominant/" => "/glossary/autosomal-dominant/",
 */
function eic_maint_bad_links()
{
    return [];
}

/**
 * Non-resolving -> resolving Yoast description variables.
 */
function eic_maint_yoast_var_map()
{
    return [
        "%%cf_gene%%" => "%%cf_gene_symbol%%",
        "%%cf_inheritance_pattern%%" => "%%cf_inheritance%%",
    ];
}

const EIC_MAINT_YOAST_DESC_FIELDS = [
    "_yoast_wpseo_metadesc",
    "_yoast_wpseo_opengraph-description",
    "_yoast_wpseo_twitter-description",
];

/**
 * Neuropathy ACF-value <-> taxonomy-term-name mapping.
 */
function eic_maint_neuro_value_to_term()
{
    return [
        "demyelinating" => "Demyelinating",
        "axonal" => "Axonal",
        "intermediate" => "Intermediate",
    ];
}

/* ============================================================
 * Admin page
 * ============================================================ */

add_action("admin_menu", function () {
    add_submenu_page(
        "tools.php",
        "Subtype Maintenance",
        "Subtype Maintenance",
        EIC_MAINT_CAP,
        EIC_MAINT_SLUG,
        "eic_maint_render_page"
    );
});

/**
 * All published + working subtype IDs.
 *
 * @return int[]
 */
function eic_maint_ids()
{
    return get_posts([
        "post_type" => EIC_MAINT_PT,
        "post_status" => ["publish", "draft", "pending", "private", "future"],
        "posts_per_page" => -1,
        "fields" => "ids",
        "orderby" => "ID",
        "order" => "ASC",
        "no_found_rows" => true,
        "suppress_filters" => true,
    ]);
}

/**
 * Registry of checks: id => [label, scanner, applier].
 */
function eic_maint_checks()
{
    return [
        "inheritance" => [
            "label" =>
                "1. Inheritance ACF field vs taxonomy (taxonomy authoritative)",
            "scan" => "eic_maint_scan_inheritance",
        ],
        "neuropathy" => [
            "label" => "2. Neuropathy ACF <-> taxonomy reconciliation",
            "scan" => "eic_maint_scan_neuropathy",
        ],
        "glossary" => [
            "label" => "3. Legacy /glossary/dominant/ link in post_content",
            "scan" => "eic_maint_scan_glossary",
        ],
        "yoast_vars" => [
            "label" => "4. Yoast description variables (non-resolving)",
            "scan" => "eic_maint_scan_yoast_vars",
        ],
        "focuskw" => [
            "label" => "5. Yoast focus keyword normalization",
            "scan" => "eic_maint_scan_focuskw",
        ],
        "internal_links" => [
            "label" =>
                "6. Internal cross-link markup (strip CMS artifact attributes)",
            "scan" => "eic_maint_scan_internal_links",
        ],
    ];
}

function eic_maint_render_page()
{
    if (!current_user_can(EIC_MAINT_CAP)) {
        return;
    }

    $applied = isset($_GET["applied"]) ? sanitize_key($_GET["applied"]) : "";
    $count = isset($_GET["n"]) ? (int) $_GET["n"] : 0;
    ?>
    <div class="wrap">
        <h1>Subtype Maintenance</h1>
        <p>Scans every <code>subtype</code> record for known inconsistencies.
        Nothing is changed until you apply a specific check.
        <strong>Take a database backup before applying.</strong> Every fix is
        reversible from a backup.</p>

        <?php if ($applied): ?>
            <div class="notice notice-success is-dismissible">
                <p>Applied <strong><?php echo esc_html(
                    $applied
                ); ?></strong>: <?php echo esc_html(
     $count
 ); ?> record(s) updated.</p>
            </div>
        <?php endif; ?>

        <?php
        foreach (eic_maint_checks() as $id => $check) {
            // The glossary link check is dormant while its map is empty:
            // hide it entirely until a bad slug is added to eic_maint_bad_links().
            if ($id === "glossary" && !eic_maint_bad_links()) {
                continue;
            }
            $findings = call_user_func($check["scan"]);
            eic_maint_render_check($id, $check["label"], $findings);
        }
        ?>
    </div>
    <?php
}

/**
 * Render a single check block: count, detail table, apply button.
 */
function eic_maint_render_check($id, $label, $findings)
{
    $auto = array_values(
        array_filter($findings, function ($f) {
            return empty($f["manual"]);
        })
    );
    $manual = array_values(
        array_filter($findings, function ($f) {
            return !empty($f["manual"]);
        })
    );

    echo '<div style="margin:22px 0;padding:16px 18px;border:1px solid #dcdcde;background:#fff;border-radius:6px;">';
    echo "<h2 style='margin-top:0;'>" . esc_html($label) . "</h2>";

    if (!$auto && !$manual) {
        echo '<p style="color:#1a7f37;font-weight:600;">Clean. No issues found.</p>';
        echo "</div>";
        return;
    }

    if ($auto) {
        echo "<p><strong>" .
            count($auto) .
            "</strong> record(s) to fix:</p>";
        echo '<table class="widefat striped" style="max-width:920px;"><thead><tr>';
        echo "<th>Record</th><th>Current</th><th>Proposed</th></tr></thead><tbody>";
        foreach ($auto as $f) {
            echo "<tr><td><strong>" .
                esc_html($f["title"]) .
                "</strong></td><td><code>" .
                esc_html($f["current"]) .
                "</code></td><td><code>" .
                esc_html($f["proposed"]) .
                "</code></td></tr>";
        }
        echo "</tbody></table>";

        echo '<form method="post" action="' .
            esc_url(admin_url("admin-post.php")) .
            '" style="margin-top:12px;">';
        echo '<input type="hidden" name="action" value="eic_maint_apply">';
        echo '<input type="hidden" name="check" value="' .
            esc_attr($id) .
            '">';
        wp_nonce_field("eic_maint_apply_" . $id, "eic_maint_nonce");
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Apply ' .
            count($auto) .
            ' fix(es) to live records? Ensure you have a backup.\');">Apply ' .
            count($auto) .
            " fix(es)</button>";
        echo "</form>";
    }

    if ($manual) {
        echo '<p style="margin-top:16px;"><strong>' .
            count($manual) .
            "</strong> record(s) flagged for manual review (not auto-changed):</p>";
        echo '<table class="widefat" style="max-width:920px;"><thead><tr>';
        echo "<th>Record</th><th>Current value</th><th>Note</th></tr></thead><tbody>";
        foreach ($manual as $f) {
            echo "<tr><td><strong>" .
                esc_html($f["title"]) .
                "</strong></td><td><code>" .
                esc_html($f["current"]) .
                "</code></td><td>" .
                esc_html($f["note"]) .
                "</td></tr>";
        }
        echo "</tbody></table>";
    }

    echo "</div>";
}

/* ============================================================
 * Apply handler
 * ============================================================ */

add_action("admin_post_eic_maint_apply", function () {
    if (!current_user_can(EIC_MAINT_CAP)) {
        wp_die("Insufficient permissions.");
    }
    $id = isset($_POST["check"]) ? sanitize_key($_POST["check"]) : "";
    $checks = eic_maint_checks();
    if (!isset($checks[$id])) {
        wp_die("Unknown check.");
    }
    check_admin_referer("eic_maint_apply_" . $id, "eic_maint_nonce");

    // Re-scan fresh; never trust state from the rendered page.
    $findings = call_user_func($checks[$id]["scan"]);
    $findings = array_values(
        array_filter($findings, function ($f) {
            return empty($f["manual"]);
        })
    );

    $applier = "eic_maint_apply_" . $id;
    $n = function_exists($applier) ? (int) call_user_func($applier, $findings) : 0;

    wp_safe_redirect(
        add_query_arg(
            [
                "page" => EIC_MAINT_SLUG,
                "applied" => $id,
                "n" => $n,
            ],
            admin_url("tools.php")
        )
    );
    exit();
});

/* ============================================================
 * Check 1 — inheritance ACF vs taxonomy
 * ============================================================ */

/**
 * Canonical ACF `inheritance` choice values, keyed by the normalized
 * (lowercased, trimmed) taxonomy term name. This maps taxonomy terms to
 * exactly the values the select field accepts, regardless of the casing
 * used on the taxonomy terms themselves.
 */
function eic_maint_inheritance_choices()
{
    return [
        "autosomal dominant" => "autosomal dominant",
        "autosomal recessive" => "autosomal recessive",
        "autosomal dominant or autosomal recessive" =>
            "autosomal dominant or autosomal recessive",
        "x-linked dominant" => "X-linked dominant",
        "x-linked recessive" => "X-linked recessive",
        "mitochondrial inheritance" => "mitochondrial inheritance",
    ];
}

/**
 * Map the record's taxonomy terms to the single canonical ACF value.
 * Returns null only when the term set has no valid ACF choice
 * (e.g. an unrecognized term, or a multi-pattern combination the field
 * does not define), in which case the record is flagged for manual review.
 */
function eic_maint_inheritance_proposed($terms)
{
    $map = eic_maint_inheritance_choices();
    $vals = [];
    foreach ($terms as $t) {
        $key = strtolower(trim($t->name));
        if (!isset($map[$key])) {
            return null; // unknown term; cannot map safely
        }
        $vals[$map[$key]] = true;
    }
    $vals = array_keys($vals);
    sort($vals);

    if (count($vals) === 1) {
        return $vals[0];
    }
    // The one multi-pattern combination the field provides a value for.
    if ($vals === ["autosomal dominant", "autosomal recessive"]) {
        return "autosomal dominant or autosomal recessive";
    }
    return null; // no combined ACF choice exists for this term set
}

function eic_maint_scan_inheritance()
{
    $out = [];
    foreach (eic_maint_ids() as $pid) {
        $terms = wp_get_object_terms($pid, "inheritance");
        if (is_wp_error($terms) || !$terms) {
            continue;
        }
        $acf = (string) get_field("inheritance", $pid);
        $proposed = eic_maint_inheritance_proposed($terms);
        $termnames = implode(
            ", ",
            array_map(function ($t) {
                return $t->name;
            }, $terms)
        );

        if ($proposed === null) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => $acf === "" ? "(empty)" : $acf,
                "note" =>
                    "Taxonomy terms (" .
                    $termnames .
                    ") have no matching ACF choice. Set manually.",
                "manual" => true,
            ];
            continue;
        }
        // Exact comparison so an out-of-vocabulary or mis-cased value
        // (e.g. Title Case) is corrected to the canonical choice.
        if ($acf !== $proposed) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => $acf === "" ? "(empty)" : $acf,
                "proposed" => $proposed,
            ];
        }
    }
    return $out;
}

function eic_maint_apply_inheritance($findings)
{
    $n = 0;
    foreach ($findings as $f) {
        if (update_field("inheritance", $f["proposed"], $f["id"])) {
            $n++;
        }
    }
    return $n;
}

/* ============================================================
 * Check 2 — neuropathy ACF <-> taxonomy
 * ============================================================ */

function eic_maint_scan_neuropathy()
{
    $val2term = eic_maint_neuro_value_to_term();
    $term2val = array_change_key_case(
        array_flip($val2term),
        CASE_LOWER
    );

    $out = [];
    foreach (eic_maint_ids() as $pid) {
        $acf = strtolower((string) get_field("neuropathy", $pid));
        $terms = wp_get_object_terms($pid, "neuropathy", ["fields" => "names"]);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        // Missing taxonomy but ACF present -> backfill taxonomy from ACF.
        if (!$terms && isset($val2term[$acf])) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => "taxonomy: (none)",
                "proposed" => "taxonomy: " . $val2term[$acf],
                "op" => "tax_from_acf",
                "term" => $val2term[$acf],
            ];
            continue;
        }
        if (!$terms) {
            continue;
        }

        // Taxonomy present and authoritative; fix ACF if it disagrees.
        $primary = $terms[0];
        $expected_val = isset($term2val[strtolower($primary)])
            ? $term2val[strtolower($primary)]
            : "";
        if ($expected_val && $acf !== $expected_val) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => "acf: " . ($acf === "" ? "(empty)" : $acf),
                "proposed" => "acf: " . $expected_val,
                "op" => "acf_from_tax",
                "val" => $expected_val,
            ];
        }
    }
    return $out;
}

function eic_maint_apply_neuropathy($findings)
{
    $n = 0;
    foreach ($findings as $f) {
        if ($f["op"] === "tax_from_acf") {
            $res = wp_set_object_terms($f["id"], $f["term"], "neuropathy", false);
            if (!is_wp_error($res)) {
                $n++;
            }
        } elseif ($f["op"] === "acf_from_tax") {
            if (update_field("neuropathy", $f["val"], $f["id"])) {
                $n++;
            }
        }
    }
    return $n;
}

/* ============================================================
 * Check 3 — legacy glossary link in post_content
 * ============================================================ */

function eic_maint_scan_glossary()
{
    $map = eic_maint_bad_links();
    $out = [];
    foreach (eic_maint_ids() as $pid) {
        $content = get_post_field("post_content", $pid);
        $hits = [];
        foreach ($map as $bad => $good) {
            $c = substr_count($content, $bad);
            if ($c > 0) {
                $hits[] = $bad . " (x" . $c . ")";
            }
        }
        if ($hits) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => implode("; ", $hits),
                "proposed" => "corrected to canonical slug(s)",
            ];
        }
    }
    return $out;
}

function eic_maint_apply_glossary($findings)
{
    global $wpdb;
    $map = eic_maint_bad_links();
    $n = 0;
    foreach ($findings as $f) {
        $content = get_post_field("post_content", $f["id"]);
        $new = strtr($content, $map);
        if ($new !== $content) {
            $wpdb->update(
                $wpdb->posts,
                ["post_content" => $new],
                ["ID" => $f["id"]]
            );
            clean_post_cache($f["id"]);
            $n++;
        }
    }
    return $n;
}

/* ============================================================
 * Check 4 — Yoast description variables
 * ============================================================ */

function eic_maint_scan_yoast_vars()
{
    $map = eic_maint_yoast_var_map();
    $bad = array_keys($map);
    $out = [];
    foreach (eic_maint_ids() as $pid) {
        $hitfields = [];
        foreach (EIC_MAINT_YOAST_DESC_FIELDS as $field) {
            $val = (string) get_post_meta($pid, $field, true);
            foreach ($bad as $b) {
                if (strpos($val, $b) !== false) {
                    $hitfields[$b] = true;
                }
            }
        }
        if ($hitfields) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => implode(", ", array_keys($hitfields)),
                "proposed" => implode(
                    ", ",
                    array_map(function ($b) use ($map) {
                        return $map[$b];
                    }, array_keys($hitfields))
                ),
            ];
        }
    }
    return $out;
}

function eic_maint_apply_yoast_vars($findings)
{
    $map = eic_maint_yoast_var_map();
    $n = 0;
    foreach ($findings as $f) {
        $changed = false;
        foreach (EIC_MAINT_YOAST_DESC_FIELDS as $field) {
            $val = (string) get_post_meta($f["id"], $field, true);
            if ($val === "") {
                continue;
            }
            $new = strtr($val, $map);
            if ($new !== $val) {
                update_post_meta($f["id"], $field, $new);
                $changed = true;
            }
        }
        if ($changed) {
            $n++;
        }
    }
    return $n;
}

/* ============================================================
 * Check 5 — Yoast focus keyword
 * ============================================================ */

function eic_maint_scan_focuskw()
{
    $out = [];
    foreach (eic_maint_ids() as $pid) {
        $subtype = (string) get_field("subtype", $pid);
        if ($subtype === "") {
            continue;
        }
        $canonical = "What Is " . $subtype . "?";
        $current = (string) get_post_meta($pid, "_yoast_wpseo_focuskw", true);

        if ($current === $canonical) {
            continue;
        }

        // Multiple "What Is ...?" clauses => split into a canonical primary
        // keyphrase plus full-phrase synonyms. Clauses are the "What Is ...?"
        // questions; the primary is always rebuilt from the subtype (so a
        // malformed source clause like "What IsCMT4G?" is corrected for free),
        // and every non-primary clause becomes a synonym.
        $clauses = eic_maint_split_focus_clauses($current);
        if (count($clauses) > 1) {
            $synonyms = eic_maint_focus_synonyms(
                $clauses,
                $canonical,
                $subtype
            );
            $existing = (string) get_post_meta(
                $pid,
                "_yoast_wpseo_keywordsynonyms",
                true
            );
            $merged = eic_maint_merge_synonyms($existing, $synonyms);

            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" => $current,
                "proposed" =>
                    "primary: " .
                    $canonical .
                    "  |  synonyms: " .
                    ($merged === "" ? "(none)" : $merged),
                "op" => "split",
                "primary" => $canonical,
                "synonyms" => $merged,
            ];
            continue;
        }

        $out[] = [
            "id" => $pid,
            "title" => get_the_title($pid),
            "current" => $current === "" ? "(empty)" : $current,
            "proposed" => $canonical,
            "op" => "primary_only",
            "primary" => $canonical,
        ];
    }
    return $out;
}

/**
 * Split a focus-keyword string into its "What Is ...?" clauses,
 * preserving each full phrase (including the trailing "?").
 *
 * @return string[]
 */
function eic_maint_split_focus_clauses($value)
{
    $value = trim((string) $value);
    if ($value === "") {
        return [];
    }
    // Split before each "What Is"/"What is" occurrence (case-insensitive),
    // keeping the delimiter with the clause that follows it.
    $parts = preg_split(
        '/(?=what\s*is)/i',
        $value,
        -1,
        PREG_SPLIT_NO_EMPTY
    );
    $clauses = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== "") {
            $clauses[] = $p;
        }
    }
    return $clauses;
}

/**
 * Build the full-phrase synonym list from the clauses. Excludes the
 * canonical primary and any clause that resolves to the record's own
 * subtype regardless of spacing/casing (so a malformed source clause like
 * "What IsCMT4G?" is treated as the primary, not kept as a broken synonym).
 * Preserves order and casing of genuine aliases.
 *
 * @return string[]
 */
function eic_maint_focus_synonyms(array $clauses, $primary, $subtype)
{
    // Normalize for identity comparison: lowercase, strip all whitespace,
    // drop a trailing "?".
    $normalize = function ($s) {
        $s = strtolower(trim((string) $s));
        $s = rtrim($s, "?");
        return preg_replace('/\s+/', "", $s);
    };
    $self_keys = [
        $normalize($primary) => true,
        $normalize("What Is " . $subtype) => true,
    ];

    $syn = [];
    $seen = $self_keys;
    foreach ($clauses as $c) {
        $key = $normalize($c);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $syn[] = $c;
    }
    return $syn;
}

/**
 * Merge new synonyms into any existing comma-separated synonyms field,
 * de-duplicating case-insensitively and preserving existing entries first.
 *
 * @param string   $existing comma-separated
 * @param string[] $additions
 * @return string comma-separated
 */
function eic_maint_merge_synonyms($existing, array $additions)
{
    $out = [];
    $seen = [];
    $existing_list = array_filter(
        array_map("trim", explode(",", (string) $existing)),
        function ($x) {
            return $x !== "";
        }
    );
    foreach (array_merge($existing_list, $additions) as $item) {
        $key = strtolower($item);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $item;
    }
    return implode(", ", $out);
}

function eic_maint_apply_focuskw($findings)
{
    $n = 0;
    foreach ($findings as $f) {
        update_post_meta($f["id"], "_yoast_wpseo_focuskw", $f["primary"]);
        if (($f["op"] ?? "") === "split") {
            update_post_meta(
                $f["id"],
                "_yoast_wpseo_keywordsynonyms",
                $f["synonyms"]
            );
        }
        $n++;
    }
    return $n;
}

/* ============================================================
 * Check 6 — internal cross-link markup normalization
 * ============================================================ */

/**
 * CMS artifact attributes to strip from internal anchors. `type` holds a
 * non-standard value ("glossary"/"subtype") that is invalid on <a>, and
 * `id` carries stale post-ID references that duplicate across a document
 * (an HTML validity error). Extend as future artifacts are identified.
 * The href, the anchor text, and any genuinely valid attribute (title,
 * class, rel, aria-*) are preserved. External links are never touched.
 */
function eic_maint_internal_anchor_strip_attrs()
{
    return ["type", "id"];
}

/**
 * True when an href points inside the site (absolute expertsincmt.org URL
 * or a root-relative path).
 */
function eic_maint_href_is_internal($href)
{
    $href = trim((string) $href);
    if ($href === "") {
        return false;
    }
    if ($href[0] === "/" && substr($href, 0, 2) !== "//") {
        return true;
    }
    $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
    return $host === "expertsincmt.org" ||
        $host === "www.expertsincmt.org";
}

/**
 * Rewrite post_content, stripping artifact attributes from internal anchors.
 * Returns [new_content, links_changed_count].
 */
function eic_maint_strip_internal_anchor_attrs($content)
{
    $strip = eic_maint_internal_anchor_strip_attrs();
    $changed = 0;

    $new = preg_replace_callback(
        '/<a\s+([^>]*?)>/is',
        function ($m) use ($strip, &$changed) {
            $attrs = $m[1];

            // Determine the href to classify internal vs external.
            if (
                !preg_match(
                    '/href\s*=\s*("[^"]*"|\'[^\']*\')/i',
                    $attrs,
                    $hm
                )
            ) {
                return $m[0]; // no href; leave anchor untouched
            }
            $href = trim($hm[1], "\"'");
            if (!eic_maint_href_is_internal($href)) {
                return $m[0]; // external; never touch
            }

            $original = $attrs;
            foreach ($strip as $name) {
                $attrs = preg_replace(
                    '/\s+' .
                        preg_quote($name, "/") .
                        '\s*=\s*("[^"]*"|\'[^\']*\')/i',
                    "",
                    $attrs
                );
            }
            if ($attrs === $original) {
                return $m[0];
            }
            $changed++;
            return "<a " . trim($attrs) . ">";
        },
        $content
    );

    return [$new, $changed];
}

function eic_maint_scan_internal_links()
{
    $out = [];
    foreach (eic_maint_ids() as $pid) {
        $content = get_post_field("post_content", $pid);
        list($new, $changed) = eic_maint_strip_internal_anchor_attrs(
            $content
        );
        if ($changed > 0) {
            $out[] = [
                "id" => $pid,
                "title" => get_the_title($pid),
                "current" =>
                    $changed .
                    " internal link(s) with artifact attributes",
                "proposed" => "stripped to canonical <a href> markup",
            ];
        }
    }
    return $out;
}

function eic_maint_apply_internal_links($findings)
{
    global $wpdb;
    $n = 0;
    foreach ($findings as $f) {
        $content = get_post_field("post_content", $f["id"]);
        list($new, $changed) = eic_maint_strip_internal_anchor_attrs(
            $content
        );
        if ($changed > 0 && $new !== $content) {
            $wpdb->update(
                $wpdb->posts,
                ["post_content" => $new],
                ["ID" => $f["id"]]
            );
            clean_post_cache($f["id"]);
            $n++;
        }
    }
    return $n;
}
