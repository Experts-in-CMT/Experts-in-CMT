<?php
/**
 * ============================================================
 *  FRAGMENT: GENES LOOP
 * ============================================================
 */
if (!defined('ABSPATH')) exit;

/**
 * Get the query args from endpoint (AJAX)
 * or build defaults if not set.
 */
$args   = get_query_var('genes_args', []);
$qs     = get_query_var('qs', '');
$qs_all = get_query_var('qs_all', false);

// Respect per_page from shortcode or fallback to defaults
$shortcode_atts = get_query_var('genes_shortcode_atts', []);
$per_page       = isset($shortcode_atts['per_page']) ? (int) $shortcode_atts['per_page'] : 12;

/**
 * ============================================================
 *  [SECTION: BUILD ARGS IF NOT PROVIDED BY ENDPOINT]
 * ============================================================
 */
if (empty($args)) {
    $a         = get_query_var('a', []);
    $tax_query = get_query_var('tax_query', []);

    if (!is_array($a)) {
        $a = [];
    }

    $args = [
        'post_type'      => 'subtype',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => max(1, (int) ($_GET['gd_paged'] ?? 1)),
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
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
        'subtype',           // Subtype name (e.g., CMT1A)
        'gene_symbol',       // HGNC-approved gene symbol
        'full_gene_name',    // Full HGNC-approved name
    ];

    /**
     * ------------------------------------------------------------
     *  FUZZY MATCH FIELDS
     *  These are descriptive, multi-value, or text-rich fields
     *  where partial matches improve usability.
     * ------------------------------------------------------------
     */
    $fuzzy_fields = [
        'acronym',           // "CMT" → matches CMT1, CMT2, etc.
        'gene_alias',        // Comma-separated aliases
        'year_of_discovery',
        'chromosome',
        'inheritance',
        'neuropathy',
        'research_team',
        'publication',
        'authors',           // Main authors (WYSIWYG)
        'alt_authors',       // Alternate authors (WYSIWYG)
        'notes',             // Optional: general notes
        'alt_publication',   // Optional: secondary publication info
    ];

    /**
     * ------------------------------------------------------------
     *  BUILD META QUERY
     *  Combine exact and fuzzy sets into one OR relation.
     * ------------------------------------------------------------
     */
    $meta_query = ['relation' => 'OR'];

    foreach ($exact_fields as $field) {
        $meta_query[] = [
            'key'     => $field,
            'value'   => $qs,
            'compare' => '=',
        ];
    }

    foreach ($fuzzy_fields as $field) {
        $meta_query[] = [
            'key'     => $field,
            'value'   => $qs,
            'compare' => 'LIKE',
        ];
    }

    $args['meta_query'] = $meta_query;

    /**
     * ------------------------------------------------------------
     *  TAXONOMY TERM NAME MATCHING
     *  Still fuzzy — allows searching by taxonomy label.
     * ------------------------------------------------------------
     */
    $args['tax_query'] = [
        'relation' => 'OR',
        [
            'taxonomy' => 'cmt_type',
            'field'    => 'name',
            'terms'    => $qs,
            'operator' => 'LIKE',
        ],
        [
            'taxonomy' => 'inheritance',
            'field'    => 'name',
            'terms'    => $qs,
            'operator' => 'LIKE',
        ],
        [
            'taxonomy' => 'neuropathy',
            'field'    => 'name',
            'terms'    => $qs,
            'operator' => 'LIKE',
        ],
    ];
}

// ============================================================
// Apply per_page override from shortcode
// ============================================================
$args['posts_per_page'] = $per_page;



/* ============================================================
   ===================== [ SECTION: SORT LOGIC ] ===============
   ============================================================ */

/**
 * Canonical rule:
 * - Always respect the fixed FIELD() order (empties first)
 * - Only bypass when user selects an explicit alternate sort
 */

// Get sort from AJAX endpoint or GET fallback
$sort = get_query_var('gd_sort', '');
$sort = sanitize_key($sort);

$gene_meta_key    = 'gene_symbol';
$subtype_meta_key = 'subtype';
$year_meta_key    = 'year_of_discovery';

// Canonical mode unless user explicitly selects a sort
$use_canonical_sort = ($sort === '' || $sort === 'default');


/* ------------------------------------------------------------
   Apply sort behavior
   ------------------------------------------------------------ */
switch ($sort) {
    case 'subtype_az':
        $args['meta_key']  = $subtype_meta_key;
        $args['meta_type'] = 'CHAR';
        $args['orderby']   = ['meta_value' => 'ASC', 'title' => 'ASC'];
        $args['order']     = 'ASC';
        break;

    case 'gene_az':
        $args['meta_key']  = $gene_meta_key;
        $args['meta_type'] = 'CHAR';
        $args['orderby']   = ['meta_value' => 'ASC', 'title' => 'ASC'];
        $args['order']     = 'ASC';
        break;

    case 'oldest':
        $args['meta_key']  = $year_meta_key;
        $args['orderby']   = ['meta_value_num' => 'ASC', 'date' => 'ASC'];
        $args['order']     = 'ASC';
        break;

    case 'newest':
        $args['meta_key']  = $year_meta_key;
        $args['orderby']   = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
        $args['order']     = 'DESC';
        break;

    default:
        // Default display order (canonical FIELD() hierarchy)
        break;
}

/* ------------------------------------------------------------
   Execute query (canonical sorter when needed)
   ------------------------------------------------------------ */
if ($use_canonical_sort) {
    $args['eic_genes_custom_sort'] = true;
    add_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10, 2);
}

$q = new WP_Query($args);

if ($use_canonical_sort) {
    remove_filter('posts_clauses', 'eic_genes_custom_sort_clauses', 10);
}

/* ============================================================
   ===================== [ SECTION: MARKUP OUTPUT ] ============
   ============================================================ */
?>

<div id="genes-results-root">
<div id="results" class="wp-block-query dr-blog" style="scroll-margin-top:100px;">

<?php
// ============================================================
// [ SECTION: TOTALS ]
// ============================================================

// Use current query object directly
$__total    = (int) $q->found_posts;
$__post_ids = wp_list_pluck($q->posts, 'ID');

$__gene_symbols = [];
$__unknown      = 0;

foreach ($__post_ids as $__id) {
    $symbol     = get_field('gene_symbol', $__id);
    $is_unknown = (bool) get_field('unknown_gene', $__id);

    if ($is_unknown) {
        $__unknown++;
    }

    if (!empty($symbol)) {
        $__gene_symbols[strtoupper(trim($symbol))] = true;
    }
}

$__uniq = count($__gene_symbols);

// Detect whether filters are active (supports GET or POST during AJAX)
$filters_active = false;
$request        = !empty($_GET) ? $_GET : $_POST;

$filter_keys = ['qs', 'cmt_type', 'inheritance', 'neuropathy', 'chromosome'];
foreach ($filter_keys as $key) {
    if (!empty($request[$key]) && $request[$key] !== '0') {
        $filters_active = true;
        break;
    }
}
?>

<div class="genes-totals" aria-live="polite">
    <?php
    echo esc_html(eic_gl_plural($__total, 'Subtype')) . ' • ';
    echo esc_html(eic_gl_plural($__uniq, 'Gene'));

    // Only show unknown count if it exists OR if no filters are active
    if ($__unknown > 0 || !$filters_active) {
        echo ' • ' . esc_html(
            eic_gl_plural(
                $__unknown,
                'Subtype with Unknown Gene',
                'Subtypes with Unknown Genes'
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

        $gene_symbol    = get_field('gene') ?: get_post_meta(get_the_ID(), 'gene_symbol', true);
        $display_gene   = $gene_symbol ?: get_the_title();
        $year_discovery = get_field('year_of_discovery') ?: '';
        $inherit_label  = get_field('inheritance_pattern') ?: implode(', ', wp_get_post_terms(get_the_ID(), 'inheritance', ['fields' => 'names']));

        ob_start(); ?>
        <article class="dr-card wp-block-post">
            <?php if (has_post_thumbnail()): ?>
                <a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('large', ['loading' => 'lazy', 'decoding' => 'async']); ?>
                </a>
            <?php endif; ?>

            <h2 class="wp-block-post-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h2>

            <div class="wp-block-post-excerpt">
                <p><strong>Gene:</strong> <?php echo esc_html($display_gene); ?></p>
                <?php if ($year_discovery): ?>
                    <p><strong>Discovered:</strong> <?php echo esc_html($year_discovery); ?></p>
                <?php endif; ?>
                <?php if ($inherit_label): ?>
                    <p><strong>Inheritance:</strong> <?php echo esc_html($inherit_label); ?></p>
                <?php endif; ?>

                <a class="wp-block-read-more" href="<?php the_permalink(); ?>">Learn More</a>

                <div class="wp-block-post-date" style="text-align:center; margin-top:12px;">
                    <small>Updated: <?php echo esc_html(get_the_modified_date(get_option('date_format'))); ?></small>
                </div>

                <div style="height:20px;" aria-hidden="true" class="wp-block-spacer"></div>
            </div>
        </article>
        <?php
        $cards[] = ob_get_clean();
    }
    wp_reset_postdata();
} else {
    
}

$rows       = array_chunk($cards, 3);
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
            $count   = count($row_items); ?>
            <div class="dr-row<?php echo $is_last ? ' dr-row--last' : ''; ?>" <?php echo $is_last ? 'data-count="' . (int) $count . '"' : ''; ?>>
                <?php echo implode('', $row_items); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// ============================================================
// [ SECTION: PAGINATION ]
// ============================================================
$total_pages = max(1, (int) $q->max_num_pages);
if ($total_pages > 1) {
    $current   = max(1, (int) ($_GET['gd_paged'] ?? 1));
    $base_url  = get_permalink(get_queried_object_id()) ?: home_url('/cmt-genetics-database/');
    $qs_params = $_GET;
    unset($qs_params['gd_paged']);

    $page_url = function (int $n) use ($base_url, $qs_params) {
        $qs2             = $qs_params;
        $qs2['gd_paged'] = $n;
        return esc_url(add_query_arg($qs2, $base_url) . '#results');
    };

    $items = [];
    if ($current > 1) {
        $items[] = '<li><a class="prev page-numbers" href="' . $page_url($current - 1) . '">« Prev</a></li>';
    } else {
        $items[] = '<li><span class="prev page-numbers">« Prev</span></li>';
    }

    $end   = $total_pages;
    $start = max(1, $current - 2);
    $stop  = min($end, $current + 2);

    if ($start > 1) {
        $items[] = '<li><a class="page-numbers" href="' . $page_url(1) . '">1</a></li>';
        if ($start > 2) $items[] = '<li><span class="page-numbers dots">…</span></li>';
    }
    for ($i = $start; $i <= $stop; $i++) {
        if ($i === $current) {
            $items[] = '<li><span class="page-numbers current">' . $i . '</span></li>';
        } else {
            $items[] = '<li><a class="page-numbers" href="' . $page_url($i) . '">' . $i . '</a></li>';
        }
    }
    if ($stop < $end) {
        if ($stop < $end - 1) $items[] = '<li><span class="page-numbers dots">…</span></li>';
        $items[] = '<li><a class="page-numbers" href="' . $page_url($end) . '">' . $end . '</a></li>';
    }
    if ($current < $total_pages) {
        $items[] = '<li><a class="next page-numbers" href="' . $page_url($current + 1) . '">Next »</a></li>';
    } else {
        $items[] = '<li><span class="next page-numbers">Next »</span></li>';
    }

    echo '<nav class="wp-block-query-pagination"><ul class="page-numbers">' . implode('', $items) . '</ul></nav>';
}
?>
</div>
</div>
