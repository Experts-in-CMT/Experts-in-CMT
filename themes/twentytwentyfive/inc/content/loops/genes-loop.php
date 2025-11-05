<?php
/* ============================================================
   GENES DATABASE LOOP SHORTCODE (responsive to filter UI)
   Simplified, reliable search using qs= and ACF/meta/tax filters.
   Keeps all layout + styling from original.
   ============================================================ */

/* ============================================================
   ============================================================
   ===================== [ SECTION: SORT LOGIC ] ===============
   ============================================================
   ============================================================ */

/**
 * Custom sorter for 'type_classification' — immutable order.
 * Empties FIRST (debug), then FIELD() sequence, then post_title ASC.
 */
function eic_genes_custom_sort_clauses($clauses, $wp_query)
{
    if (!$wp_query->get("eic_genes_custom_sort")) {
        return $clauses;
    }

    global $wpdb;
    $custom_type_order = [
        "CMT1",
        "CMT2",
        "CMTX",
        "CMT4",
        "CMTDI",
        "CMTRI",
        "dHMN",
        "dSMA",
        "GAN",
        "HMSN",
        "HSAN",
        "HSN",
        "SMA-LEP",
        "Unclassified",
    ];

    // Ensure join alias `mt1` exists for type_classification
    if (strpos($clauses["join"] ?? "", " mt1 ") === false) {
        $clauses["join"] .= " LEFT JOIN {$wpdb->postmeta} mt1
		                      ON (mt1.post_id = {$wpdb->posts}.ID AND mt1.meta_key = 'type_classification')";
    }

    // Build FIELD() list
    $quoted = array_map(function ($v) use ($wpdb) {
        return trim($wpdb->prepare("%s", $v), "'");
    }, $custom_type_order);
    $field_list = "'" . implode("','", $quoted) . "'";

    // Empties first → then FIELD() order → then title ASC
    $clauses["orderby"] =
        "CASE WHEN mt1.meta_value IS NULL OR mt1.meta_value = '' THEN 0 ELSE 1 END ASC, " .
        "FIELD(mt1.meta_value, {$field_list}) ASC, " .
        "{$wpdb->posts}.post_title ASC";

    return $clauses;
}

/* ============================================================
   ============================================================
   ===================== [ SECTION: SHORTCODE ] ================
   ============================================================
   ============================================================ */

add_shortcode("genes_loop", function ($atts = []) {
    $a = shortcode_atts(
        [
            "per_page" => 12,
        ],
        $atts
    );

    /* --------------------------------------------------------
	   INPUTS (GET params)
	   -------------------------------------------------------- */
    $qs_raw = isset($_GET["qs"]) ? (string) $_GET["qs"] : "";
    $qs = sanitize_text_field($qs_raw);
    $qs_all = strtolower(trim($qs)) === "all";

    $sel = [
        "cmt_type" => isset($_GET["cmt_type"]) ? (int) $_GET["cmt_type"] : 0,
        "inheritance" => isset($_GET["inheritance"])
            ? (int) $_GET["inheritance"]
            : 0,
        "neuropathy" => isset($_GET["neuropathy"])
            ? (int) $_GET["neuropathy"]
            : 0,
        "chromosome" => isset($_GET["chromosome"])
            ? (int) $_GET["chromosome"]
            : 0,
    ];

    /* --------------------------------------------------------
	   TAXONOMY FILTERS (AND)
	   -------------------------------------------------------- */
    $tax_query = ["relation" => "AND"];
    foreach ($sel as $tax => $id) {
        if ($id) {
            $tax_query[] = [
                "taxonomy" => $tax,
                "field" => "term_id",
                "terms" => [$id],
            ];
        }
    }
    if (count($tax_query) === 1) {
        $tax_query = [];
    }

    /* --------------------------------------------------------
	   META SEARCH (always OR)
	   -------------------------------------------------------- */
    $args = [
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => max(1, (int) $a["per_page"]),
        "paged" => max(1, (int) ($_GET["gd_paged"] ?? 1)),
        // default ordering (will be overridden if gd_sort present)
        "orderby" => "title",
        "order" => "ASC",
        // flag used by our canonical sorter when no explicit sort is chosen
        "eic_genes_custom_sort" => 1,
    ];

    if (!empty($tax_query)) {
        $args["tax_query"] = $tax_query;
    }

    if (!$qs_all && $qs !== "") {
        $args["meta_query"] = [
            "relation" => "OR",
            ["key" => "gene", "value" => $qs, "compare" => "="],
            ["key" => "gene_symbol", "value" => $qs, "compare" => "="],
            ["key" => "subtype", "value" => $qs, "compare" => "LIKE"],
            ["key" => "year_of_discovery", "value" => $qs, "compare" => "LIKE"],
            ["key" => "alternate_gene_1", "value" => $qs, "compare" => "LIKE"],
            ["key" => "alternate_gene_2", "value" => $qs, "compare" => "LIKE"],
            ["key" => "alternate_gene_3", "value" => $qs, "compare" => "LIKE"],
        ];
    }

    /* --------------------------------------------------------
	   SORT: honor explicit gd_sort over canonical ordering
	   -------------------------------------------------------- */
    $sort = isset($_GET["gd_sort"]) ? sanitize_key($_GET["gd_sort"]) : "";

    $gene_meta_key = "gene_symbol"; // Gene A→Z
    $subtype_meta_key = "subtype"; // Subtype A→Z
    $year_meta_key = "year_of_discovery"; // Oldest/Newest (if used)

    $use_canonical_sort = empty($sort); // only when no explicit sort chosen

    switch ($sort) {
        case "subtype_az":
            unset($args["meta_key"], $args["orderby"], $args["order"]);
            $args["meta_key"] = $subtype_meta_key;
            $args["meta_type"] = "CHAR";
            $args["orderby"] = ["meta_value" => "ASC", "title" => "ASC"];
            $args["order"] = "ASC";
            break;

        case "gene_az":
            unset($args["meta_key"], $args["orderby"], $args["order"]);
            $args["meta_key"] = $gene_meta_key;
            $args["meta_type"] = "CHAR";
            $args["orderby"] = ["meta_value" => "ASC", "title" => "ASC"];
            $args["order"] = "ASC";
            break;

        case "oldest":
            unset($args["meta_key"], $args["orderby"], $args["order"]);
            $args["meta_key"] = $year_meta_key;
            $args["orderby"] = ["meta_value_num" => "ASC", "date" => "ASC"];
            $args["order"] = "ASC";
            break;

        case "newest":
            unset($args["meta_key"], $args["orderby"], $args["order"]);
            $args["meta_key"] = $year_meta_key;
            $args["orderby"] = ["meta_value_num" => "DESC", "date" => "DESC"];
            $args["order"] = "DESC";
            break;

        default:
            // no explicit sort → keep canonical type-classification ordering
            break;
    }

    /* --------------------------------------------------------
	   RUN QUERY (attach canonical sorter only when needed)
	   -------------------------------------------------------- */
    if ($use_canonical_sort) {
        add_filter("posts_clauses", "eic_genes_custom_sort_clauses", 10, 2);
    }

    $q = new WP_Query($args);

    if ($use_canonical_sort) {
        remove_filter("posts_clauses", "eic_genes_custom_sort_clauses", 10);
    }

    /* ========================================================
	   ========================================================
	   =============== [ SECTION: OUTPUT MARKUP ] ==============
	   ========================================================
	   ======================================================== */

    ob_start();
    ?>
	<div id="results" class="wp-block-query dr-blog" style="scroll-margin-top:100px;">

	<?php
 // Totals row — uses current filters, not limited by pagination
 $__eic_ids = eic_gl_current_post_ids();
 $__total = count($__eic_ids);
 $__uniq = eic_gl_count_unique_genes($__eic_ids); // distinct gene, excluding "Unknown"
 $__unknown = eic_gl_count_unknown_genes($__eic_ids);
 // subtypes flagged via ACF toggle
    ?>
	<div class="genes-totals" aria-live="polite">
	  <?php
   echo esc_html(eic_gl_plural($__total, "Subtype"));
   echo " • ";
   echo esc_html(eic_gl_plural($__uniq, "Gene"));

   if ($__unknown > 0) {
       echo " • ";
       echo esc_html(
           eic_gl_plural(
               $__unknown,
               "Subtype with an Unknown Gene",
               "Subtypes with Unknown Genes"
           )
       );
   }
   ?>
	</div>

	<?php
 /* ======================================================================
   RESULTS-LEVEL SORT TOOLBAR
   ----------------------------------------------------------------------
   Location: Directly BELOW the totals line and ABOVE the results grid.
   Purpose : Allows users to reorder results (Subtype A–Z, Gene A–Z, etc.)
             independently of the top filter form.
   Behavior:
     • Submits via GET when dropdown changes.
     • Preserves current filters and search terms.
     • Resets pagination to page 1.
     • "CLEAR" removes only the sort while keeping active filters.
   ====================================================================== */
 $anchor = "results";
 $base = strtok($_SERVER["REQUEST_URI"], "?"); // current path without query
 $action_url = esc_url($base . "#" . $anchor);
 $current_sort = isset($_GET["gd_sort"]) ? sanitize_key($_GET["gd_sort"]) : "";

 // Keep current filters/search; always reset pagination when sorting
 $keep = $_GET;
 unset($keep["gd_paged"]);

 // CLEAR = drop sort & pagination, keep other filters
 $clear_params = $keep;
 unset($clear_params["gd_sort"]);
 $sort_clear_url =
     esc_url(
         $base . ($clear_params ? "?" . http_build_query($clear_params) : "")
     ) .
     "#" .
     $anchor;
 ?>

	<div class="genes-sort genes-sort--results">
	  <form class="genes-sort__form" method="get" action="<?php echo $action_url; ?>">
	    <label class="genes-sort__label" for="gd_sort">Sort by</label>

	    <select id="gd_sort" name="gd_sort" class="genes-sort__select" onchange="this.form.submit()">
	      <option value=""           <?php selected(
           $current_sort,
           ""
       ); ?>>Default</option>
	      <option value="gene_az"    <?php selected(
           $current_sort,
           "gene_az"
       ); ?>>Gene A to Z</option>
	      <option value="subtype_az" <?php selected(
           $current_sort,
           "subtype_az"
       ); ?>>Subtype A to Z</option>
	      <option value="oldest"     <?php selected(
           $current_sort,
           "oldest"
       ); ?>>Oldest to Newest</option>
	      <option value="newest"     <?php selected(
           $current_sort,
           "newest"
       ); ?>>Newest to Oldest</option>
	    </select>

	    <a class="genes-sort__clear" href="<?php echo $sort_clear_url; ?>">CLEAR</a>

	    <?php // Preserve other GET params (filters/search)

    foreach ($keep as $k => $v) {
         if (in_array($k, ["gd_sort", "gd_paged"], true)) {
             continue;
         }
         if (is_scalar($v)) {
             printf(
                 '<input type="hidden" name="%s" value="%s">',
                 esc_attr($k),
                 esc_attr($v)
             );
         }
     } ?>
	    <noscript><button type="submit" class="genes-sort__btn">Apply</button></noscript>
	  </form>

<script>
  // Legacy: sort change / clear (disabled when AJAX is active)
  document.addEventListener('DOMContentLoaded', function () {
    const sortForm = document.querySelector('.genes-sort__form'); // or .dr-sort__form if that’s your markup
    if (!sortForm || window.DR_AJAX) return;  // ← stop if AJAX loader is present

    const clearBtn = document.querySelector('.genes-sort__clear');
    sortForm.addEventListener('change', function (e) {
      if (e.target.name === 'dr_sort') { // adjust name if needed
        e.preventDefault();

        const params = new URLSearchParams(window.location.search);
        params.delete('dr_paged');
        const val = e.target.value;
        if (val) params.set('dr_sort', val); else params.delete('dr_sort');

        const newUrl = window.location.pathname + '?' + params.toString() + '#results';
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        params.delete('dr_sort');
        params.delete('dr_paged');
        const newUrl = window.location.pathname + '?' + params.toString() + '#results';
        window.history.replaceState(null, '', newUrl);
        window.location.reload();
      });
    }
  });
</script>

	</div>

	<?php
 $cards = [];
 if ($q->have_posts()) {
     while ($q->have_posts()) {

         $q->the_post();

         $gene_symbol =
             get_field("gene") ?:
             get_post_meta(get_the_ID(), "gene_symbol", true);
         $display_gene = $gene_symbol ?: get_the_title();
         $year_discovery = get_field("year_of_discovery") ?: "";
         $inherit_label =
             get_field("inheritance_pattern") ?:
             implode(
                 ", ",
                 wp_get_post_terms(get_the_ID(), "inheritance", [
                     "fields" => "names",
                 ])
             );

         ob_start();
         ?>
			<article class="dr-card wp-block-post">
				<?php if (has_post_thumbnail()): ?>
					<a class="wp-block-post-featured-image" href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail("large", [
          "loading" => "lazy",
          "decoding" => "async",
      ]); ?>
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
						<small>Update: <?php echo esc_html(
          get_the_modified_date(get_option("date_format"))
      ); ?></small>
					</div>

					<div style="height:20px;" aria-hidden="true" class="wp-block-spacer"></div>
				</div>
			</article>
			<?php $cards[] = ob_get_clean();
     }
     wp_reset_postdata();
 }

 $rows = array_chunk($cards, 3);
 $total_rows = count($rows);
 ?>

	<?php if (empty($rows)): ?>
		<div id="genes-no-results" class="dr-row dr-row--empty"
			style="
				margin: 0px auto 64px auto;
				display: flex;
				justify-content: center;
				align-items: flex-start;
				max-width: 700px;
				width: 100%;
			">
			<p style="font-size:1.1rem; color:#333; text-align:left;">
				No results found.<br>Try adjusting your filters or search term.
			</p>
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
 /* ====================================================
	   =============== [ SECTION: PAGINATION ] =============
	   ==================================================== */
 $total_pages = max(1, (int) $q->max_num_pages);
 if ($total_pages > 1) {
     $current = max(1, (int) ($_GET["gd_paged"] ?? 1));
     $base_url = get_permalink(get_queried_object_id()) ?: home_url("/genes/");
     $qs_params = $_GET;
     unset($qs_params["gd_paged"]);

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
                 '<li><span class="page-numbers current">' .
                 $i .
                 "</span></li>";
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
	<?php return ob_get_clean();
});
