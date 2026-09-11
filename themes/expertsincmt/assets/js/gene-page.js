/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Gene page: ClinVar Variants card.
 * ------------------------------------------------------------
 * Fills the three collapsed tiers on first open from
 * GET eic/v1/clinvar/{gene} (mu-plugins/eic-clinvar-variants.php).
 * The section carries the endpoint in data-endpoint, so this file
 * holds no URLs. Enqueued only on single gene pages (functions.php).
 * Location: /assets/js/gene-page.js
 */

(function () {
  // External links always open a new tab (site rule); tell screen reader
  // users so with hidden text. Runs on every gene page, variants card or not.
  function cueNewTabs(scope) {
    scope.querySelectorAll('.gpx a[target="_blank"]').forEach(function (a) {
      if (a.querySelector(".gpx-sr")) { return; }
      var s = document.createElement("span");
      s.className = "gpx-sr";
      s.textContent = " (opens in a new tab)";
      a.appendChild(s);
    });
  }
  cueNewTabs(document);

  var root = document.getElementById("gene-variants");
  if (!root) { return; }
  var endpoint = root.getAttribute("data-endpoint");
  var status = root.querySelector(".gpx-cv-status");
  var icon = root.querySelector(".gpx-cv-icon");
  var meta = root.querySelector(".gpx-cv-meta");
  var loaded = false, loading = false;
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]; }); }
  // Cell label: real text, shown on phones where the header row is hidden,
  // hidden on desktop where the header row carries it (gpx-dl in gene-page.css).
  function dl(label) { return '<span class="gpx-dl">' + esc(label) + "</span>"; }
  function stars(n) {
    var o = '<span class="gpx-stars" aria-hidden="true">';
    for (var i = 0; i < 4; i++) { o += i < n ? '<span class="gpx-star gpx-star--on">\u2605</span>' : '<span class="gpx-star">\u2606</span>'; }
    return o + "</span>";
  }
  // ClinVar sends 1/01/01 (year 1) when it has no evaluation date.
  function evalDate(s) {
    s = String(s || "").slice(0, 10);
    return /^(1|0)\//.test(s) || /^000/.test(s) ? "" : s;
  }
  // Break long HGVS names at their seams (after the transcript colon,
  // before the protein change), never inside a token like "del".
  function seams(name) {
    return esc(name).replace(/:/g, ":<wbr>").replace(/ \(/g, " <wbr>(");
  }
  function row(v, diseaseLabel) {
    var name = v.title || (v.cdna + (v.protein ? " (" + v.protein + ")" : ""));
    // ClinVar repeats a trait once per submitted condition set; show each name once
    var seen = {};
    var conds = (v.conditions || []).filter(function (c) {
      var k = String(c.name || "").trim().toLowerCase();
      if (seen[k]) { return false; }
      seen[k] = true;
      return true;
    }).map(function (c) {
      if (c.legacy) { return '<span class="gpx-cond gpx-cond--legacy" title="A legacy name, not a subtype. See CMT Classifications.">' + esc(c.name) + ' <small>legacy name</small></span>'; }
      return '<span class="gpx-cond gpx-cond--' + esc(c["class"]) + '">' + esc(c.name) + "</span>";
    }).join(" ");
    var cls = /likely/i.test(v.classification) && !/^pathogenic\//i.test(v.classification) ? "lp" : "p";
    return '<tr data-vcv="' + esc(String(v.vcv || "").toUpperCase()) + '">' +
      '<td class="gbx-st" data-l="Variant">' + dl("Variant") + (v.url ? '<a href="' + esc(v.url) + '" target="_blank" rel="noopener">' + seams(name) + "</a>" : seams(name)) +
        (v.rsid ? '<div class="gbx-pub-m">' + esc(v.rsid) + "</div>" : "") + "</td>" +
      '<td data-l="Type">' + dl("Type") + esc(v.type) + "</td>" +
      '<td data-l="Classification">' + dl("Classification") + '<span class="gbx-ev gbx-ev-' + (cls === "p" ? "red" : "amber") + '">' + esc(v.classification) + "</span></td>" +
      '<td class="gbx-yr" data-l="Review" title="' + esc(v.review_status) + '">' + dl("Review") + stars(v.stars) +
        '<span class="gpx-sr">' + esc(v.stars + " of 4 stars" + (v.review_status ? ": " + v.review_status : "")) + "</span></td>" +
      '<td data-l="' + diseaseLabel + '">' + dl(diseaseLabel) + conds + "</td>" +
      '<td class="gbx-yr" data-l="Last Evaluated">' + dl("Last Evaluated") + esc(evalDate(v.last_evaluated)) + "</td>" +
    "</tr>";
  }
  function table(list, tier) {
    if (!list.length) { return ""; }
    var diseaseLabel = tier === "A" ? "Subtype" : "Disease";
    return '<div class="gbx-matrix"><table><thead><tr><th scope="col">Variant</th><th scope="col">Type</th><th scope="col">Classification</th><th scope="col">Review</th><th scope="col">' + diseaseLabel + '</th><th scope="col">Last Evaluated</th></tr></thead><tbody>' +
      list.map(function (v) { return row(v, diseaseLabel); }).join("") + "</tbody></table></div>";
  }
  function render(d) {
    ["A", "B", "C"].forEach(function (t) {
      var det = root.querySelector('.gpx-tier[data-tier="' + t + '"]');
      det.querySelector(".gpx-tier__body").innerHTML = table(d.tiers[t] || [], t);
      det.querySelector(".gpx-tier__count").textContent = d.counts[t];
    });
    cueNewTabs(root);
    status.textContent = "";
    meta.textContent = d.total_plp + " P/LP variant" + (d.total_plp === 1 ? "" : "s") + " with assertion criteria at " + d.gene + " in ClinVar release " + d.release + (d.truncated ? " (list truncated)" : "") + ".";
    targetRow();
  }
  // Arrival from search (?v=VCV000041229#gene-variants): open the tier(s)
  // holding that record, mark the row(s), and bring the first into view
  function targetRow() {
    var m = /[?&]v=([^&#]+)/.exec(window.location.search);
    if (!m) { return; }
    var vcv = decodeURIComponent(m[1]).replace(/[^A-Za-z0-9]/g, "").toUpperCase();
    if (!vcv) { return; }
    var rows = root.querySelectorAll('tr[data-vcv="' + vcv + '"]');
    if (!rows.length) { return; }
    rows.forEach(function (tr) {
      tr.classList.add("is-target");
      var det = tr.closest(".gpx-tier");
      if (det) { det.open = true; }
    });
    rows[0].scrollIntoView({ block: "center" });
  }
  function load() {
    // The structural record's card has no endpoint: nothing to fetch, and
    // no status line to write to.
    if (!endpoint || loaded || loading) { return; }
    loading = true;
    status.textContent = "Loading from ClinVar\u2026";
    if (icon) { icon.removeAttribute("hidden"); } else { status.classList.add("is-loading"); }
    fetch(endpoint, { headers: { "Accept": "application/json" } })
      .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
      .then(function (d) { loaded = true; status.classList.remove("is-loading"); if (icon) { icon.setAttribute("hidden", ""); } render(d); })
      .catch(function () { status.classList.remove("is-loading"); if (icon) { icon.setAttribute("hidden", ""); } status.textContent = "ClinVar is not reachable right now. The tiers will fill when it is."; loading = false; });
  }
  // Sticky tier headers sit below the admin bar when it is present, as the
  // browsers' headers do. The offsets live on the page root so the subtype
  // tier under Relationship to CMT shares them with the variant tiers.
  var page = root.closest(".gpx") || root;
  function stickyTop() {
    var bar = document.getElementById("wpadminbar");
    page.style.setProperty("--gpx-top", (bar ? bar.offsetHeight : 0) + "px");
    var sum = page.querySelector(".gpx-tier > summary");
    page.style.setProperty("--gpx-sum", (sum ? sum.offsetHeight : 0) + "px");
  }
  stickyTop();
  window.addEventListener("resize", stickyTop);
  window.addEventListener("load", stickyTop);

  // Fetch on page load so the counts and totals line show at once; the
  // weekly cache makes this one transient read for every view after the
  // first per ClinVar release. A tier opened before the fetch lands
  // simply fills when it does.
  load();
  // Every tier on the page, the subtype tier included; only the variant
  // tiers trigger the ClinVar fetch.
  page.querySelectorAll(".gpx-tier").forEach(function (det) {
    det.addEventListener("toggle", function () {
      if (det.open) { if (root.contains(det)) { load(); } return; }
      // Closed from a pinned header mid-scroll: hold the viewport at the
      // header bar instead of letting the shortened page pull the footer up
      var bar = document.getElementById("wpadminbar");
      var offset = bar ? bar.offsetHeight : 0;
      var top = det.getBoundingClientRect().top;
      if (top < offset) { window.scrollTo(0, window.pageYOffset + top - offset); }
    });
  });
})();
