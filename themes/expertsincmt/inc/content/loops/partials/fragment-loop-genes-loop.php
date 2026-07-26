<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  FRAGMENT: GENES LOOP (AJAX + PAGE LOAD)
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Renders ONLY the inner loop markup injected by the
 *      Genes AJAX endpoint and shortcode wrapper
 *    - Contains search, taxonomy, and canonical sort logic
 *      applied identically for AJAX and non-AJAX paths
 *    - Must remain wrapper-free for proper DOM swapping
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Get the query args from endpoint (AJAX)
 * or build defaults if not set.
 */
$args = get_query_var("genes_args", []);
$qs = get_query_var("qs", "");
$qs_all = get_query_var("qs_all", false);

// Respect per_page from shortcode or fallback to defaults
$shortcode_atts = get_query_var("genes_shortcode_atts", []);
$per_page = isset($shortcode_atts["per_page"])
    ? (int) $shortcode_atts["per_page"]
    : 12;

/**
 * ============================================================
 *  [SECTION: BUILD ARGS IF NOT PROVIDED BY ENDPOINT]
 * ============================================================
 */
if (empty($args)) {
    $a = get_query_var("a", []);
    $tax_query = get_query_var("tax_query", []);

    if (!is_array($a)) {
        $a = [];
    }

    $args = [
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => $per_page,
        "paged" => max(1, (int) ($_GET["gd_paged"] ?? 1)),
        "orderby" => "title",
        "order" => "ASC",
    ];

    if (!empty($tax_query)) {
        $args["tax_query"] = $tax_query;
    }
}

/**
 * ============================================================
 *  [SECTION: SEARCH LOGIC]
 *  v0.8.x — Finalized exact/fuzzy hybrid search for Genes DB
 *  Applies meta_query whenever a search term ($qs) is present.
 *  Runs for BOTH page-load and AJAX (qs comes from query vars).
 * ============================================================
 */
if (!empty($qs)) {
    $qs = trim($qs);

    /**
     * ------------------------------------------------------------
     *  EXACT MATCH FIELDS
     *  These are single-value identifiers that must match precisely.
     *  Use '=' to prevent substring collisions (e.g., PMP2 ≠ PMP22).
     * ------------------------------------------------------------
     */
    $exact_fields = [
        "subtype", // Subtype name (e.g., CMT1A)
        "gene_symbol", // HGNC-approved gene symbol
        "full_gene_name", // Full HGNC-approved name
    ];

    /**
     * ------------------------------------------------------------
     *  FUZZY MATCH FIELDS
     *  These are descriptive, multi-value, or text-rich fields
     *  where partial matches improve usability.
     * ------------------------------------------------------------
     */
    $fuzzy_fields = [
        "acronym", // "CMT" → matches CMT1, CMT2, etc.
        "gene_alias", // Comma-separated aliases
        "year_of_discovery",
        "chromosome",
        "inheritance",
        "neuropathy",
        "research_team",
        "publication",
        "authors", // Main authors (WYSIWYG)
        "alt_authors", // Alternate authors (WYSIWYG)
        "notes", // Optional: general notes
        "alt_publication", // Optional: secondary publication info
    ];

    /**
     * ------------------------------------------------------------
     *  BUILD META QUERY
     *  Combine exact and fuzzy sets into one OR relation.
     * ------------------------------------------------------------
     */
    $meta_query = ["relation" => "OR"];

    foreach ($exact_fields as $field) {
        $meta_query[] = [
            "key" => $field,
            "value" => $qs,
            "compare" => "=",
        ];
    }

    foreach ($fuzzy_fields as $field) {
        $meta_query[] = [
            "key" => $field,
            "value" => $qs,
            "compare" => "LIKE",
        ];
    }

    $args["meta_query"] = $meta_query;

    /**
     * ------------------------------------------------------------
     *  TAXONOMY TERM NAME MATCHING
     *  Still fuzzy — allows searching by taxonomy label.
     * ------------------------------------------------------------
     */
    $args["tax_query"] = [
        "relation" => "OR",
        [
            "taxonomy" => "cmt_type",
            "field" => "name",
            "terms" => $qs,
            "operator" => "LIKE",
        ],
        [
            "taxonomy" => "inheritance",
            "field" => "name",
            "terms" => $qs,
            "operator" => "LIKE",
        ],
        [
            "taxonomy" => "neuropathy",
            "field" => "name",
            "terms" => $qs,
            "operator" => "LIKE",
        ],
    ];
}

// ============================================================
// Apply per_page override from shortcode
// ============================================================
$args["posts_per_page"] = $per_page;

/* ============================================================
   ===================== [ SECTION: SORT LOGIC ] ===============
   ============================================================ */

/**
 * Canonical rule:
 * - Always respect the fixed FIELD() order (empties first)
 * - Only bypass when user selects an explicit alternate sort
 */

// Get sort from AJAX endpoint or GET fallback
$sort = get_query_var("gd_sort", "");
$sort = sanitize_key($sort);

$gene_meta_key = "gene_symbol";
$subtype_meta_key = "subtype";
$year_meta_key = "year_of_discovery";

// Canonical mode unless user explicitly selects a sort
$use_canonical_sort = $sort === "" || $sort === "default";

/* ------------------------------------------------------------
   Apply sort behavior
   ------------------------------------------------------------ */
switch ($sort) {
    case "subtype_az":
        $args["meta_key"] = $subtype_meta_key;
        $args["meta_type"] = "CHAR";
        $args["orderby"] = ["meta_value" => "ASC", "title" => "ASC"];
        $args["order"] = "ASC";
        break;

    case "gene_az":
        $args["meta_key"] = $gene_meta_key;
        $args["meta_type"] = "CHAR";
        $args["orderby"] = ["meta_value" => "ASC", "title" => "ASC"];
        $args["order"] = "ASC";
        break;

    case "oldest":
        $args["meta_key"] = $year_meta_key;
        $args["orderby"] = ["meta_value_num" => "ASC", "date" => "ASC"];
        $args["order"] = "ASC";
        break;

    case "newest":
        $args["meta_key"] = $year_meta_key;
        $args["orderby"] = ["meta_value_num" => "DESC", "date" => "DESC"];
        $args["order"] = "DESC";
        break;

    default:
        // Default display order (canonical FIELD() hierarchy)
        break;
}

/**
 * ============================================================
 *  [SECTION: GENE GROUP FLAGS]
 *  Checkbox facets for the ACF true/false fields
 *  `mitochondrial_involvement` and `ars_gene`.
 *
 *  Applied last so the flags survive the search branch above,
 *  which reassigns meta_query wholesale. When a search is also
 *  active, the existing OR block is nested inside an AND with
 *  the flag block rather than replaced.
 *
 *  Checking multiple boxes ANDs the groups (narrows to the
 *  intersection), for parity with the four selector dropdowns,
 *  which also AND against one another.
 *
 *  Guard: `unknown_gene` is definitionally exclusive of the
 *  other two, since a subtype with no identified causative gene
 *  cannot also carry a mitochondrial or ARS gene. Combining them
 *  would always return zero, so Unknown takes precedence and the
 *  others are dropped. The UI enforces this too, but this guard
 *  also covers hand-edited URLs and no-JS page loads.
 * ============================================================
 */
$genes_flags = get_query_var("genes_flags", []);
if (!is_array($genes_flags)) {
    $genes_flags = [];
}

if (!empty($genes_flags["unknown"])) {
    $genes_flags["mito"] = false;
    $genes_flags["ars"] = false;
}

$flag_clauses = [];
if (!empty($genes_flags["unknown"])) {
    $flag_clauses[] = [
        "key" => "unknown_gene",
        "value" => "1",
        "compare" => "=",
    ];
}
if (!empty($genes_flags["mito"])) {
    $flag_clauses[] = [
        "key" => "mitochondrial_involvement",
        "value" => "1",
        "compare" => "=",
    ];
}
if (!empty($genes_flags["ars"])) {
    $flag_clauses[] = [
        "key" => "ars_gene",
        "value" => "1",
        "compare" => "=",
    ];
}
// Variant Mechanism (single-value `mechanism` field) — OR facet: any of the
// selected mechanism values matches.
$genes_mech = get_query_var("genes_mech", []);
if (!is_array($genes_mech)) {
    $genes_mech = [];
}
$mech_clauses = [];
foreach ($genes_mech as $mval) {
    $mech_clauses[] = [
        "key" => "mechanism",
        "value" => $mval,
        "compare" => "=",
    ];
}

// Assemble the flag block (AND across gene-group flags) and the mechanism
// block (OR across selected mechanisms), then AND both with any existing
// meta_query (e.g. the search branch above).
$extra_blocks = [];
if (!empty($flag_clauses)) {
    $extra_blocks[] =
        count($flag_clauses) === 1
            ? $flag_clauses[0]
            : array_merge(["relation" => "AND"], $flag_clauses);
}
if (!empty($mech_clauses)) {
    $extra_blocks[] =
        count($mech_clauses) === 1
            ? $mech_clauses[0]
            : array_merge(["relation" => "OR"], $mech_clauses);
}

if (!empty($extra_blocks)) {
    if (!empty($args["meta_query"])) {
        $args["meta_query"] = array_merge(
            ["relation" => "AND", $args["meta_query"]],
            $extra_blocks
        );
    } elseif (count($extra_blocks) === 1) {
        $args["meta_query"] = $extra_blocks;
    } else {
        $args["meta_query"] = array_merge(
            ["relation" => "AND"],
            $extra_blocks
        );
    }
}

/* ------------------------------------------------------------
   Execute query (canonical sorter when needed)
   Setting the query var triggers the globally registered
   eic_genes_type_ordering_clauses (inc/content/sort/genes-type-order.php),
   which is the single source of truth for Genes DB ordering
   (empties last). Do not attach a second posts_clauses callback here.
   ------------------------------------------------------------ */
if ($use_canonical_sort) {
    $args["eic_genes_custom_sort"] = true;
}

$q = new WP_Query($args);

/* ============================================================
   ===================== [ SECTION: MARKUP OUTPUT ] ============
   ============================================================ */
?>

<div id="genes-results-root" data-per-page="<?php echo esc_attr($per_page); ?>">
<div id="results" class="wp-block-query dr-blog" style="scroll-margin-top:100px;">

<?php
// ============================================================
// [ SECTION: TOTALS ]
// ============================================================

// Get canonical, filter-aware totals (unpaged)
$__totals = eic_get_genes_totals_from_filters($args);

$__total = (int) ($__totals["subtypes"] ?? 0);
$__uniq = (int) ($__totals["genes"] ?? 0);
$__unknown = (int) ($__totals["unknown"] ?? 0);

// Detect whether filters are active (supports GET or POST during AJAX)
$filters_active = false;
$request = !empty($_GET) ? $_GET : $_POST;

$filter_keys = ["qs", "cmt_type", "inheritance", "neuropathy", "chromosome"];
foreach ($filter_keys as $key) {
    if (!empty($request[$key]) && $request[$key] !== "0") {
        $filters_active = true;
        break;
    }
}
?>

<div class="genes-totals" aria-live="polite">
    <?php
    echo esc_html($filters_active ? "Results: " : "Currently Curated: ");
    echo esc_html(eic_gl_plural($__total, "Subtype")) . " | ";
    echo esc_html(eic_gl_plural($__uniq, "Gene"));

    // Only show unknown count if it exists OR if no filters are active
    if ($__unknown > 0 || !$filters_active) {
        echo " | " .
            esc_html(
                eic_gl_plural(
                    $__unknown,
                    "Subtype with an Unknown Gene",
                    "Subtypes with an Unknown Gene"
                )
            );
    }
    ?>
</div>


<?php
// ============================================================
// [ SECTION: LOOP CARDS ]
// ============================================================
$cards = [];
if ($q && $q->have_posts()) {
    while ($q->have_posts()) {

        $q->the_post();

        $unknown_gene = (bool) get_post_meta(
            get_the_ID(),
            "unknown_gene",
            true
        );

        $gene_symbol = trim(
            (string) get_post_meta(get_the_ID(), "gene_symbol", true)
        );

        // Optional legacy fallback if older entries used ACF field "gene"
        if ($gene_symbol === "") {
            $legacy = trim((string) get_field("gene"));
            if ($legacy !== "") {
                $gene_symbol = $legacy;
            }
        }

        $display_gene =
            $unknown_gene || $gene_symbol === "" ? "Unknown" : $gene_symbol;

        $year_discovery = get_field("year_of_discovery") ?: "";
        $inherit_label =
            get_field("inheritance_pattern") ?:
            implode(
                ", ",
                wp_get_post_terms(get_the_ID(), "inheritance", [
                    "fields" => "names",
                ])
            );

        // Canonical type bucket → drives the type dot color.
        // CSS keys the dot on data-cmt-type; title color is unaffected.
        $type_class = trim(
            (string) get_post_meta(get_the_ID(), "type_classification", true)
        );

        // Optional subtype alias (aka), surfaced under the title when present.
        $subtype_alias = trim((string) get_field("subtype_alias"));

        // Neuropathy type → quiet upper-right pill. Classification is already
        // carried by the dot color and the subtype title, so it is not repeated.
        $neuro_terms = wp_get_post_terms(get_the_ID(), "neuropathy", [
            "fields" => "names",
        ]);
        $tag_text =
            !is_wp_error($neuro_terms) && !empty($neuro_terms)
                ? (string) $neuro_terms[0]
                : "";

        ob_start();
        ?>
        <article class="dr-card wp-block-post eic-subtype-card" data-cmt-type="<?php echo esc_attr(
            $type_class
        ); ?>">

            <a class="eic-subtype-card__link" href="<?php the_permalink(); ?>">

                <div class="eic-subtype-card__body">

                    <div class="eic-subtype-card__head">
                        <div class="eic-subtype-card__heading">
                            <span class="eic-subtype-card__dot" aria-hidden="true"></span>
                            <h2 class="eic-subtype-card__title"><?php the_title(); ?></h2>
                        </div>
                        <?php if ($tag_text !== ""): ?>
                            <span class="eic-subtype-card__tag"><?php echo esc_html(
                                $tag_text
                            ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($subtype_alias !== ""): ?>
                        <p class="eic-subtype-card__alias">aka: <?php echo esc_html(
                            $subtype_alias
                        ); ?></p>
                    <?php endif; ?>

                    <div class="eic-subtype-card__attrs">

                        <div class="eic-subtype-card__attr">
                            <span class="eic-subtype-card__attr-label">Gene</span>
                            <span class="eic-subtype-card__attr-value eic-subtype-card__gene"><?php echo esc_html(
                                $display_gene
                            ); ?></span>
                        </div>

                        <div class="eic-subtype-card__attr">
                            <span class="eic-subtype-card__attr-label">Discovered</span>
                            <span class="eic-subtype-card__attr-value"><?php echo esc_html(
                                $year_discovery !== ""
                                    ? $year_discovery
                                    : "Unknown"
                            ); ?></span>
                        </div>

                        <div class="eic-subtype-card__attr">
                            <span class="eic-subtype-card__attr-label">Inheritance</span>
                            <span class="eic-subtype-card__attr-value eic-subtype-card__inheritance"><?php echo esc_html(
                                $inherit_label !== ""
                                    ? $inherit_label
                                    : "Unknown"
                            ); ?></span>
                        </div>

                    </div>

                </div>

                <footer class="eic-subtype-card__footer">

                    <time
                        class="eic-subtype-card__updated"
                        datetime="<?php echo esc_attr(get_the_modified_date('Y-m-d')); ?>">
                        Updated: <?php echo esc_html(get_the_modified_date('F j, Y')); ?>
                    </time>

                    <span class="eic-subtype-card__arrow" aria-hidden="true">
                        →
                    </span>

                </footer>

            </a>

        </article>


        <?php $cards[] = ob_get_clean();
    }
    wp_reset_postdata();
} else {
}

$rows = array_chunk($cards, 3);
$total_rows = count($rows);
?>

<?php if (empty($rows)): ?>
    <div id="genes-no-results" class="dr-row dr-row--empty" style="margin:0 auto 64px; display:flex; justify-content:center; align-items:flex-start; max-width:700px; width:100%;">
        <p style="font-size:1.1rem; color:#333; text-align:left;">No results found.<br>Try adjusting your filters or search term.</p>
    </div>
<?php endif; ?>

<div class="dr-grid">
    <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $i => $row_items):

            $is_last = $i === $total_rows - 1;
            $count = count($row_items);
            ?>
            <div class="dr-row<?php echo $is_last
                ? " dr-row--last"
                : ""; ?>" <?php echo $is_last
    ? 'data-count="' . (int) $count . '"'
    : ""; ?>>
                <?php echo implode("", $row_items); ?>
            </div>
        <?php
        endforeach; ?>
    <?php endif; ?>
</div>

<?php
// ============================================================
// [ SECTION: PAGINATION ]
// ============================================================
$total_pages = max(1, (int) $q->max_num_pages);
if ($total_pages > 1) {
    $current = max(1, (int) ($args["paged"] ?? 1));
    $base_url =
        get_permalink(get_queried_object_id()) ?:
        home_url("/cmt-genetics-database/");
    // Build pagination params from the effective request: $_GET on page
    // load, $_POST during an AJAX fetch (where $_GET is empty). Strip the
    // AJAX plumbing and the paged key so injected page links still carry
    // the active filters instead of losing them.
    $qs_src = !empty($_GET) ? $_GET : $_POST;
    $qs_params = array_diff_key(
        $qs_src,
        array_flip(["action", "nonce", "per_page", "gd_paged", "paged"])
    );

    $page_url = function (int $n) use ($base_url, $qs_params) {
        $qs2 = $qs_params;
        $qs2["gd_paged"] = $n;
        return esc_url(add_query_arg($qs2, $base_url) . "#results");
    };

    $items = [];
    if ($current > 1) {
        $items[] =
            '<li><a class="prev page-numbers" href="' .
            $page_url($current - 1) .
            '">« Prev</a></li>';
    } else {
        $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
    }

    $end = $total_pages;
    $start = max(1, $current - 2);
    $stop = min($end, $current + 2);

    if ($start > 1) {
        $items[] =
            '<li><a class="page-numbers" href="' .
            $page_url(1) .
            '">1</a></li>';
        if ($start > 2) {
            $items[] = '<li><span class="page-numbers dots">…</span></li>';
        }
    }
    for ($i = $start; $i <= $stop; $i++) {
        if ($i === $current) {
            $items[] =
                '<li><span class="page-numbers current">' . $i . "</span></li>";
        } else {
            $items[] =
                '<li><a class="page-numbers" href="' .
                $page_url($i) .
                '">' .
                $i .
                "</a></li>";
        }
    }
    if ($stop < $end) {
        if ($stop < $end - 1) {
            $items[] = '<li><span class="page-numbers dots">…</span></li>';
        }
        $items[] =
            '<li><a class="page-numbers" href="' .
            $page_url($end) .
            '">' .
            $end .
            "</a></li>";
    }
    if ($current < $total_pages) {
        $items[] =
            '<li><a class="next page-numbers" href="' .
            $page_url($current + 1) .
            '">Next »</a></li>';
    } else {
        $items[] = '<li><span class="next page-numbers">Next »</span></li>';
    }

    echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' .
        implode("", $items) .
        "</ul></nav>";
}
?>
</div>
</div>
