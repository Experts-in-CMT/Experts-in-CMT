<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  [variant_mechanism_table] — Variant Mechanisms
 *  ------------------------------------------------------------
 *  A live, database-driven listing of the variant mechanism call
 *  for every subtype. The call is a single curated value in the
 *  `mechanism` field (LoF, Dominant-Negative, GoF, Complex, or
 *  Unknown); the mechanistic basis, confidence, prediction, and
 *  rationale come from the curated mechanism fields.
 *
 *  Presentation: an accordion table. Each subtype is a compact
 *  aligned row (Gene, Subtype, Inheritance, Mechanism,
 *  Confidence) that expands on click to reveal its mechanistic
 *  basis, prediction, and rationale. Facets and search are
 *  client-side (small catalog), so a newly added subtype appears
 *  automatically, reading Unknown until its mechanism is curated.
 *
 *  Usage: [variant_mechanism_table]
 *  Location: /inc/shortcodes/variant-mechanism-table.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

if (shortcode_exists("variant_mechanism_table")) {
    return;
}

/**
 * Human gene symbols named inside a rationale that are NOT a subtype's own
 * primary gene, or that appear in a case the primary-gene set would miss.
 * Kept small and explicit so the italic pass stays limited to genuine gene
 * mentions: aliases (FAM134B = RETREG1), secondary genes (FGF13, KLF7), and
 * co-named partners (CADM4). Mouse-case symbols (Gdap1, Mpz) are deliberately
 * excluded, per "human gene" scope, by the case-sensitive match below.
 */
if (!function_exists("eic_vmech_extra_gene_symbols")) {
    function eic_vmech_extra_gene_symbols(): array
    {
        return [
            "FGF13", // CMTX3 candidate mechanism gene
            "KLF7", // dHMN-2D (FBXO38 coactivator target)
            "FAM134B", // RETREG1 alias (HSAN-2B)
            "MTMR13", // SBF2 alias (CMT4B2)
            "MTMR5", // SBF1 alias (CMT4B3)
            "SEPT9", // SEPTIN9 alias (neuralgic amyotrophy)
            "NGFB", // NGF alias (HSAN-5)
            "C12orf65", // MTRFR alias
            "C19orf12", // C19ORF12 written lower-case in prose
            "C1orf194", // CFAP276 alias
            "CADM4", // CMT2FF (CADM3-CADM4 complex partner)
            "FCP1", // CTDP1 alias
            "GANP", // MCM3AP alias
            "MFN1", // CMT2A / HMSN-6A (MFN2 fusion partner)
            "HDAC6", // CMT2F, dHMN-2B (HSPB1 tubulin-acetylation partner)
            "CHCHD2", // CMT-CHCHD10 (CHCHD10 paralog)
            "CHCHD4", // CMTX4 (AIFM1 mitochondrial-import partner)
            "MIA40", // CMTX4 (CHCHD4 alias)
            "VAC14", // CMT4J (FIG4 complex partner)
            "PIKFYVE", // CMT4J (FIG4 complex partner)
            "HSJ1", // dSMA-5 (DNAJB2 alias)
            "SIMPLE", // CMT1C (LITAF alias)
        ];
    }
}

/**
 * Escape rationale text, then italicize whole-word human gene-symbol matches
 * by wrapping them in <em>. Matching is case-sensitive with alphanumeric
 * boundaries, so it hits real gene tokens only: substrings (PMP2 inside
 * PMP22), mouse-case symbols, and entity fragments are left untouched. The
 * text is HTML-escaped first and only <em> tags are injected, so no arbitrary
 * markup can enter from the field value.
 */
if (!function_exists("eic_vmech_italicize_genes")) {
    function eic_vmech_italicize_genes(string $text, array $symbols): string
    {
        $safe = esc_html($text);
        if ($safe === "" || empty($symbols)) {
            return $safe;
        }
        // Longest symbol first so overlaps resolve to the longer gene.
        usort($symbols, fn($a, $b) => strlen($b) <=> strlen($a));
        $alt = implode(
            "|",
            array_map(fn($s) => preg_quote($s, "/"), $symbols)
        );
        $pattern = '/(?<![A-Za-z0-9])(' . $alt . ')(?![A-Za-z0-9])/';
        return preg_replace($pattern, "<em>$1</em>", $safe);
    }
}

/**
 * Stable DOM id for a subtype's row, shared by the table markup and by any
 * page that deep-links into it (e.g. the single subtype page). sanitize_title
 * gives the same slug on both sides, so the anchor always resolves.
 */
if (!function_exists("eic_vmech_row_id")) {
    function eic_vmech_row_id(string $code): string
    {
        return "vmech-" . sanitize_title($code);
    }
}

/**
 * Permalink of the page that hosts [variant_mechanism_table], found by its
 * shortcode rather than a hard-coded slug so a page rename does not break the
 * link. Cached per request; falls back to the conventional slug if the page
 * is not published yet (e.g. before the prod deploy).
 */
if (!function_exists("eic_vmech_page_url")) {
    function eic_vmech_page_url(): string
    {
        static $url = null;
        if ($url !== null) {
            return $url;
        }
        $found = get_posts([
            "post_type" => "page",
            "post_status" => "publish",
            "posts_per_page" => 5,
            "no_found_rows" => true,
            "s" => "variant_mechanism_table",
        ]);
        foreach ($found as $p) {
            if (has_shortcode($p->post_content, "variant_mechanism_table")) {
                return $url = get_permalink($p->ID);
            }
        }
        return $url = home_url("/variant-mechanisms/");
    }
}

/**
 * Full human gene-symbol set (every subtype's own gene plus curated aliases),
 * cached per request. Shared so the single subtype page can italicize gene
 * mentions in a rationale exactly as the table does.
 */
if (!function_exists("eic_vmech_gene_symbol_list")) {
    function eic_vmech_gene_symbol_list(): array
    {
        static $list = null;
        if ($list !== null) {
            return $list;
        }
        $ids = get_posts([
            "post_type" => "subtype",
            "post_status" => "publish",
            "posts_per_page" => -1,
            "fields" => "ids",
            "no_found_rows" => true,
        ]);
        $set = [];
        foreach ($ids as $id) {
            $g = trim((string) get_field("gene_symbol", $id));
            if ($g === "") {
                $g = trim((string) get_field("full_gene_name", $id));
            }
            if (
                $g !== "" &&
                strpos($g, " ") === false &&
                preg_match('/[A-Za-z]/', $g)
            ) {
                $set[$g] = true;
            }
        }
        return $list = array_values(
            array_unique(
                array_merge(
                    array_keys($set),
                    eic_vmech_extra_gene_symbols()
                )
            )
        );
    }
}

add_shortcode("variant_mechanism_table", function ($atts = []) {
    $q = new WP_Query([
        "post_type" => "subtype",
        "post_status" => "publish",
        "posts_per_page" => -1,
        "no_found_rows" => true,
        "orderby" => "title",
        "order" => "ASC",
        // Candidate gene associations (candidate_gene = true) are not
        // classified subtypes: excluded here, as in the Subtype Browser loop and the
        // homepage totals. NOT EXISTS keeps ordinary subtypes (meta absent/"0").
        "meta_query" => [
            "relation" => "OR",
            ["key" => "candidate_gene", "value" => "1", "compare" => "!="],
            ["key" => "candidate_gene", "compare" => "NOT EXISTS"],
        ],
    ]);

    // Public display labels for the single mechanism call.
    $call_labels = [
        "lof" => "Loss of Function",
        "dominant_negative" => "Dominant-Negative",
        "gof" => "Toxic Gain of Function",
        "complex" => "Complex",
        "unknown" => "Unknown",
    ];

    // Public display labels for the mechanistic basis (mechanism_mode).
    $mode_labels = [
        "haploinsufficiency" => "Haploinsufficiency",
        "complete-loss" => "Complete loss",
        "hypomorphic" => "Hypomorphic",
        "overactivity" => "Overactivity",
        "neomorphic" => "Neomorphic",
        "dosage" => "Dosage",
        "repeat-expansion" => "Repeat expansion",
        "mixed" => "Mixed",
        "unresolved" => "Unresolved",
        "no-gene" => "Gene unknown",
    ];

    $rows = [];
    $gene_symbols = [];
    $counts = [
        "call" => [
            "lof" => 0,
            "dominant_negative" => 0,
            "gof" => 0,
            "complex" => 0,
            "unknown" => 0,
        ],
        "conf" => ["high" => 0, "medium" => 0, "low" => 0, "none" => 0],
    ];

    foreach ($q->posts as $post) {
        $id = $post->ID;

        $code = trim((string) get_field("subtype", $id));
        if ($code === "") {
            $code = get_the_title($id);
        }
        $gene = trim((string) get_field("gene_symbol", $id));
        if ($gene === "") {
            $gene = trim((string) get_field("full_gene_name", $id));
        }
        // Collect single-token gene symbols for the rationale italic pass.
        // Multi-word values (e.g. CMTX3's locus label) are skipped.
        if (
            $gene !== "" &&
            strpos($gene, " ") === false &&
            preg_match('/[A-Za-z]/', $gene)
        ) {
            $gene_symbols[$gene] = true;
        }
        $inheritance = trim((string) get_field("inheritance", $id));

        $call = strtolower(trim((string) get_field("mechanism", $id)));
        if (!isset($call_labels[$call])) {
            $call = "unknown";
        }
        $call_label = $call_labels[$call];
        $counts["call"][$call]++;

        $mode = trim((string) get_field("mechanism_mode", $id));
        $mode_label = $mode_labels[$mode] ?? "";

        $conf = strtolower(trim((string) get_field("mechanism_confidence", $id)));
        if (!in_array($conf, ["high", "medium", "low"], true)) {
            $conf = "";
        }
        $counts["conf"][$conf === "" ? "none" : $conf]++;

        $rows[] = [
            "code" => $code,
            "gene" => $gene,
            "inheritance" => $inheritance,
            "call" => $call,
            "call_label" => $call_label,
            "mode_label" => $mode_label,
            "confidence" => $conf,
            "prediction" => trim(
                (string) get_field("mechanism_prediction", $id)
            ),
            "rationale" => trim((string) get_field("mechanism_rationale", $id)),
            "url" => get_permalink($id),
        ];
    }
    wp_reset_postdata();

    // Full human gene-symbol set: every subtype's own gene plus curated
    // secondary genes/aliases named within rationales.
    $gene_symbol_list = array_values(
        array_unique(
            array_merge(array_keys($gene_symbols), eic_vmech_extra_gene_symbols())
        )
    );

    usort($rows, function ($a, $b) {
        $ga = $a["gene"] === "" ? "zzz" : strtoupper($a["gene"]);
        $gb = $b["gene"] === "" ? "zzz" : strtoupper($b["gene"]);
        return $ga === $gb
            ? strcasecmp($a["code"], $b["code"])
            : strcmp($ga, $gb);
    });

    $total = count($rows);

    $conf_label = [
        "high" => "High",
        "medium" => "Medium",
        "low" => "Low",
        "" => '<span class="vmech-muted">not rated</span>',
    ];

    $inh_abbr = [
        "autosomal dominant or autosomal recessive" => "AD, AR",
        "autosomal dominant" => "AD",
        "autosomal recessive" => "AR",
        "x-linked recessive" => "XLR",
        "x-linked dominant" => "XLD",
        "mitochondrial inheritance" => "Mito",
    ];

    ob_start();
    ?>
<div class="vmech" data-total="<?php echo (int) $total; ?>">

  <div class="vmech-filter" id="vmech-filter" role="search">
    <label class="vmech-filter__search">
      <span class="vmech-filter__label">Search subtype, gene, or rationale</span>
      <input type="search" class="vmech-search"
             placeholder="ex: CMT2A, MFN2, dominant-negative, aggregation..."
             autocomplete="off" spellcheck="false" />
    </label>

    <fieldset class="vmech-filter__group">
      <legend class="vmech-filter__legend">Mechanism</legend>
      <?php
      $call_facets = [
          "lof" => "Loss of Function (LoF)",
          "dominant_negative" => "Dominant-Negative",
          "gof" => "Toxic Gain of Function (GoF)",
          "complex" => "Complex",
          "unknown" => "Unknown",
      ];
      foreach ($call_facets as $key => $label): ?>
        <label class="vmech-check">
          <input type="checkbox" class="vmech-facet" data-facet="call" value="<?php echo esc_attr(
              $key
          ); ?>" />
          <span><?php echo esc_html(
              $label
          ); ?> <span class="vmech-ct">(<?php echo (int) $counts["call"][
    $key
]; ?>)</span></span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="vmech-filter__group">
      <legend class="vmech-filter__legend">Confidence</legend>
      <?php foreach (["high" => "High", "medium" => "Medium", "low" => "Low"]
          as $key => $label): ?>
        <label class="vmech-check">
          <input type="checkbox" class="vmech-facet" data-facet="conf" value="<?php echo esc_attr(
              $key
          ); ?>" />
          <span><?php echo esc_html(
              $label
          ); ?> <span class="vmech-ct">(<?php echo (int) $counts["conf"][
    $key
]; ?>)</span></span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <div class="vmech-filter__actions">
      <button type="button" class="vmech-collapse">Close all</button>
      <button type="button" class="vmech-reset">Reset</button>
      <span class="vmech-count" aria-live="polite">Showing <b><?php echo (int) $total; ?></b> subtypes</span>
    </div>
  </div>

    <?php // Header bar: a standalone table that shares the data table's
    // colgroup so its columns track the body automatically. It alone is sticky
    // (mirroring the gene browser): the filter scrolls away and this column
    // header pins below the admin bar through the whole table. It is
    // aria-hidden; the data table below keeps its own (visually hidden) thead
    // for screen readers. ?>
    <table class="vmech-headtable" aria-hidden="true">
      <colgroup>
        <col class="vmech-col-gene" />
        <col class="vmech-col-code" />
        <col class="vmech-col-inh" />
        <col class="vmech-col-mech" />
        <col class="vmech-col-conf" />
        <col class="vmech-col-toggle" />
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Gene</th>
          <th scope="col">Subtype</th>
          <th scope="col">Inheritance</th>
          <th scope="col">Mechanism</th>
          <th scope="col">Confidence</th>
          <th scope="col"></th>
        </tr>
      </thead>
    </table>

  <div class="vmech-tablewrap">
    <table class="vmech-table">
      <colgroup>
        <col class="vmech-col-gene" />
        <col class="vmech-col-code" />
        <col class="vmech-col-inh" />
        <col class="vmech-col-mech" />
        <col class="vmech-col-conf" />
        <col class="vmech-col-toggle" />
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Gene</th>
          <th scope="col">Subtype</th>
          <th scope="col">Inheritance</th>
          <th scope="col">Mechanism</th>
          <th scope="col">Confidence</th>
          <th scope="col"><span class="vmech-sr">Details</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
            $haystack = strtolower(
                $r["code"] .
                    " " .
                    $r["gene"] .
                    " " .
                    $r["inheritance"] .
                    " " .
                    $r["call_label"] .
                    " " .
                    $r["mode_label"] .
                    " " .
                    $r["prediction"] .
                    " " .
                    $r["rationale"]
            );
            ?>
        <tr class="vmech-row" id="<?php echo esc_attr(
            eic_vmech_row_id($r["code"])
        ); ?>" tabindex="0" role="button" aria-expanded="false"
            aria-controls="<?php echo esc_attr(eic_vmech_row_id($r["code"]) . "-detail"); ?>"
            data-call="<?php echo esc_attr($r["call"]); ?>"
            data-conf="<?php echo esc_attr($r["confidence"]); ?>"
            data-text="<?php echo esc_attr($haystack); ?>">
          <td class="vmech-gene"><?php echo $r["gene"] === ""
              ? '<span class="vmech-muted">n/a</span>'
              : "<em>" . esc_html($r["gene"]) . "</em>"; ?></td>
          <td class="vmech-code"><?php echo esc_html($r["code"]); ?></td>
          <td class="vmech-inh" title="<?php echo esc_attr(
              $r["inheritance"]
          ); ?>"><?php echo esc_html(
    $inh_abbr[strtolower($r["inheritance"])] ?? $r["inheritance"]
); ?></td>
          <td class="vmech-call">
            <span class="vmech-pill vmech-pill--<?php echo esc_attr(
                $r["call"]
            ); ?>"><?php echo esc_html($r["call_label"]); ?></span>
          </td>
          <td class="vmech-conf vmech-conf--<?php echo esc_attr(
              $r["confidence"] === "" ? "none" : $r["confidence"]
          ); ?>"><?php echo $conf_label[$r["confidence"]]; ?></td>
          <td class="vmech-toggle"><span class="vmech-plus" aria-hidden="true"></span></td>
        </tr>
        <tr class="vmech-detail" id="<?php echo esc_attr(eic_vmech_row_id($r["code"]) . "-detail"); ?>" hidden>
          <td colspan="6">
            <div class="vmech-detail__body">
              <?php if ($r["mode_label"] !== ""): ?>
                <p class="vmech-detail__basis"><span class="vmech-detail__basislabel">Mechanistic basis:</span> <?php echo esc_html(
    $r["mode_label"]
); ?></p>
              <?php endif; ?>
              <?php if ($r["prediction"] !== ""): ?>
                <p class="vmech-detail__prediction"><span class="vmech-detail__predlabel">Prediction:</span> <?php echo eic_vmech_italicize_genes(
                    $r["prediction"],
                    $gene_symbol_list
                ); ?></p>
              <?php endif; ?>
              <?php if ($r["rationale"] !== ""): ?>
                <p class="vmech-detail__rationale"><span class="vmech-detail__ratlabel">Rationale:</span> <?php echo eic_vmech_italicize_genes(
                    $r["rationale"],
                    $gene_symbol_list
                ); ?></p>
              <?php endif; ?>
              <?php if ($r["url"]): ?>
                <p class="vmech-detail__link"><a href="<?php echo esc_url(
                    $r["url"]
                ); ?>">Learn more about <?php echo esc_html(
    $r["code"]
); ?> <span class="vmech-detail__arrow" aria-hidden="true">&rarr;</span></a></p>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="vmech-empty" hidden>
          <td colspan="6">No subtypes match the current filters.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<script>
function eicVmechInit(root) {
  if (root.dataset.init) { return; }
  root.dataset.init = '1';

  var search = root.querySelector('.vmech-search');
  var facets = Array.prototype.slice.call(root.querySelectorAll('.vmech-facet'));
  var rows = Array.prototype.slice.call(root.querySelectorAll('.vmech-row'));
  var empty = root.querySelector('.vmech-empty');
  var countEl = root.querySelector('.vmech-count');
  var filter = root.querySelector('.vmech-filter');
  var total = rows.length;

  // ---- Shareable URL state --------------------------------------------
  // Active search + facets are mirrored into the query string so a configured
  // view can be copied and shared, and re-applied when that link is opened.
  // Keys are prefixed `vm_` so they never collide with the gene-browser table
  // or other page params. This is independent of the `#vmech-<code>` row
  // deep-link (query string = filter view; hash = a specific subtype row).
  var VM_URLKEYS = ['vm_q', 'vm_call', 'vm_conf'];

  function vmFacetValues(facet) {
    return facets
      .filter(function (f) { return f.dataset.facet === facet && f.checked; })
      .map(function (f) { return f.value; });
  }

  // Serialize the current UI state into the URL. Discrete filter actions push a
  // new history entry (so Back/Forward step through filter states, matching the
  // subtype browser); typing replaces instead, so a search term does not leave
  // one entry per keystroke. The `#vmech-<code>` row hash is preserved untouched.
  function syncUrl(push) {
    var params = new URLSearchParams(window.location.search);
    VM_URLKEYS.forEach(function (k) { params.delete(k); });

    var q = search ? (search.value || '').trim() : '';
    if (q) { params.set('vm_q', q); }
    var call = vmFacetValues('call'); if (call.length) { params.set('vm_call', call.join(',')); }
    var conf = vmFacetValues('conf'); if (conf.length) { params.set('vm_conf', conf.join(',')); }

    var qs = params.toString();
    var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
    var cur = window.location.pathname + window.location.search + window.location.hash;
    try {
      if (push && url !== cur) { window.history.pushState({ vmech: 1 }, '', url); }
      else { window.history.replaceState({ vmech: 1 }, '', url); }
    } catch (e) {}
  }

  // Apply the URL's state to the controls. Idempotent: a key that is absent
  // resets its control to the default, so a Back/Forward step (popstate) that
  // lands on a sparser or empty URL restores cleanly. Returns whether the URL
  // carried any filter state, which decides the arrival scroll.
  function readUrl() {
    var params = new URLSearchParams(window.location.search);
    var had = VM_URLKEYS.some(function (k) { return params.has(k); });

    if (search) { search.value = params.get('vm_q') || ''; }
    var call = (params.get('vm_call') || '').split(',').filter(Boolean);
    var conf = (params.get('vm_conf') || '').split(',').filter(Boolean);
    facets.forEach(function (f) {
      if (f.dataset.facet === 'call') { f.checked = call.indexOf(f.value) !== -1; }
      else if (f.dataset.facet === 'conf') { f.checked = conf.indexOf(f.value) !== -1; }
    });
    return had;
  }

  function scrollToFilter() {
    var filterEl = root.querySelector('.vmech-filter');
    if (!filterEl) { return; }
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var bar = document.getElementById('wpadminbar');
    var offset = (bar ? bar.offsetHeight : 0) + 12;
    var y = filterEl.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: y > 0 ? y : 0, behavior: reduce ? 'auto' : 'smooth' });
  }

  // Only the column header is sticky (mirroring the gene browser); the filter
  // scrolls away. Offset the header below the WP admin bar (present when a
  // logged-in user views the front end), and tell a deep-linked row how far to
  // clear the pinned header.
  function stickyTops() {
    var bar = document.getElementById('wpadminbar');
    var barH = bar ? bar.offsetHeight : 0;
    var head = root.querySelector('.vmech-headtable');
    var headH = head ? head.offsetHeight : 0;
    root.style.setProperty('--vm-top', barH + 'px');
    // Where a deep-linked row should land: clear of the pinned header.
    root.style.setProperty('--vm-row-top', (barH + headH) + 'px');
  }
  stickyTops();
  window.addEventListener('resize', stickyTops);
  window.addEventListener('load', stickyTops);

  function detailOf(row) {
    var d = row.nextElementSibling;
    return d && d.classList.contains('vmech-detail') ? d : null;
  }

  function setOpen(row, open) {
    row.setAttribute('aria-expanded', open ? 'true' : 'false');
    var d = detailOf(row);
    if (d) { d.hidden = !open || row.hidden; }
  }

  function activeSet(facet) {
    var out = {};
    facets.forEach(function (f) {
      if (f.dataset.facet === facet && f.checked) { out[f.value] = true; }
    });
    return Object.keys(out).length ? out : null;
  }

  // Does a row pass every active filter EXCEPT the named facet group? Used for
  // live facet counts: an option's count is "how many rows I would add if I
  // ticked this", so the option's own group is neutralized while every other
  // filter (the other group + search) still applies.
  function passesExcept(row, exceptFacet) {
    var q = (search.value || '').trim().toLowerCase();
    if (q && row.dataset.text.indexOf(q) === -1) { return false; }
    if (exceptFacet !== 'call') {
      var calls = activeSet('call');
      if (calls && !calls[row.dataset.call]) { return false; }
    }
    if (exceptFacet !== 'conf') {
      var confs = activeSet('conf');
      if (confs && !confs[row.dataset.conf || 'none']) { return false; }
    }
    return true;
  }

  // Rewrite every facet's count to its neutralized total and dim any that would
  // return nothing. A checked facet is never disabled, so it can be turned off.
  function recount() {
    facets.forEach(function (f) {
      var facet = f.dataset.facet, val = f.value, n = 0;
      rows.forEach(function (row) {
        if (!passesExcept(row, facet)) { return; }
        var hit = facet === 'call'
          ? row.dataset.call === val
          : (row.dataset.conf || 'none') === val;
        if (hit) { n++; }
      });
      var label = f.closest('.vmech-check');
      var ct = label ? label.querySelector('.vmech-ct') : null;
      if (ct) { ct.textContent = '(' + n + ')'; }
      var disable = n === 0 && !f.checked;
      f.disabled = disable;
      if (label) { label.classList.toggle('is-disabled', disable); }
    });
  }

  function apply() {
    var calls = activeSet('call');
    var confs = activeSet('conf');
    var q = (search.value || '').trim().toLowerCase();
    var shown = 0;

    rows.forEach(function (row) {
      var ok = true;
      if (calls && !calls[row.dataset.call]) { ok = false; }
      if (ok && confs) {
        var c = row.dataset.conf || 'none';
        if (!confs[c]) { ok = false; }
      }
      if (ok && q && row.dataset.text.indexOf(q) === -1) { ok = false; }
      row.hidden = !ok;
      var d = detailOf(row);
      if (d) { d.hidden = !ok || row.getAttribute('aria-expanded') !== 'true'; }
      if (ok) { shown++; }
    });

    if (empty) { empty.hidden = shown !== 0; }
    if (countEl) {
      // Prefaced like the gene browser: total at rest, shown-of-total when a
      // facet or search is active.
      var filtered = !!(calls || confs || q);
      countEl.innerHTML = filtered
        ? 'Showing <b>' + shown + '</b> of ' + total + ' subtypes'
        : 'Showing <b>' + total + '</b> subtypes';
    }
    recount();
  }

  rows.forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a')) { return; }
      setOpen(row, row.getAttribute('aria-expanded') !== 'true');
    });
    row.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        setOpen(row, row.getAttribute('aria-expanded') !== 'true');
      }
    });
  });

  // Discrete filter changes push a history entry; typing replaces so a search
  // term does not leave one entry per keystroke.
  facets.forEach(function (f) { f.addEventListener('change', function () { apply(); syncUrl(true); }); });
  if (search) { search.addEventListener('input', function () { apply(); syncUrl(false); }); }

  var reset = root.querySelector('.vmech-reset');
  if (reset) {
    reset.addEventListener('click', function () {
      facets.forEach(function (f) { f.checked = false; });
      if (search) { search.value = ''; }
      rows.forEach(function (row) { setOpen(row, false); });
      apply(); syncUrl(true);
    });
  }

  var collapse = root.querySelector('.vmech-collapse');
  if (collapse) {
    collapse.addEventListener('click', function () {
      rows.forEach(function (row) { setOpen(row, false); });
    });
  }

  // Deep-link landing: a #vmech-<code> hash (e.g. from a subtype page's
  // Mechanism link) expands that row, scrolls it clear of the pinned header,
  // and flashes it so the eye lands where the link promised.
  function openFromHash() {
    var id = (location.hash || '').slice(1);
    if (!id) { return; }
    var target = document.getElementById(id);
    if (!target || !root.contains(target) ||
        !target.classList.contains('vmech-row')) { return; }
    // A landing link should win over any leftover filter/search state. Clearing
    // the controls also strips the now-stale vm_ params from the URL (replace,
    // so the row hash landing does not add its own history entry).
    if (target.hidden) {
      facets.forEach(function (f) { f.checked = false; });
      if (search) { search.value = ''; }
      apply();
      syncUrl(false);
    }
    setOpen(target, true);
    try { target.focus({ preventScroll: true }); } catch (e) {}
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    target.classList.remove('vmech-flash');
    void target.offsetWidth; // restart the flash if re-triggered
    target.classList.add('vmech-flash');
  }
  window.addEventListener('hashchange', openFromHash);

  // Back/Forward: restore the controls and table to the URL of the history
  // entry being revisited. No syncUrl here, so no new entry is created.
  window.addEventListener('popstate', function () { readUrl(); apply(); });

  // Hydrate filter/search state from a shared query-string link, then land the
  // reader on the filter. A row deep-link (#vmech-<code>) still wins: openFromHash
  // runs in the same frame and takes precedence over the filter scroll.
  var vmFromUrl = readUrl();
  if (vmFromUrl) { apply(); syncUrl(false); }
  else { recount(); } // at rest: set the initial counts + any zero-count dimming
  var vmHashId = (location.hash || '').slice(1);
  var vmHashRow = !!(vmHashId && document.getElementById(vmHashId) &&
      document.getElementById(vmHashId).classList.contains('vmech-row'));

  // Two frames so sticky offsets and row layout are settled before we scroll.
  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      openFromHash();
      if (vmFromUrl && !vmHashRow) { scrollToFilter(); }
    });
  });
}
(function () {
  var nodes = document.querySelectorAll('.vmech');
  for (var i = 0; i < nodes.length; i++) { eicVmechInit(nodes[i]); }
})();
</script>
<?php return ob_get_clean();
});
?>
