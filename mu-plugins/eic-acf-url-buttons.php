<?php

/*
 * Copyright (c) 2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress build.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: EIC ACF URL Buttons (Subtype editor)
 * ------------------------------------------------------------
 * Adds two one-click buttons in the Subtype editor:
 *   - "Get ClinVar URL"  under the clinvar_url field
 *   - "Get ClinGen URL"   under the clingen_url field
 *
 * Buttons are injected via JavaScript after ACF renders, so they
 * work in both the block editor (where ACF renders fields through
 * its own JS path) and the classic editor.
 *
 * ClinVar: built client-side from the record's gene_symbol using
 *   the verified Pathogenic + Likely Pathogenic gene template.
 * ClinGen: resolved from the harvested CMT GCEP CCID lookup
 *   (EIC_ClinGen_URL_Tool::curations()) plus the per-subtype
 *   override map for the multi-curation genes (PMP22, NEFL).
 *
 * The buttons only populate the field; saving is still manual.
 *
 * Location: wp-content/mu-plugins/eic-acf-url-buttons.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

/**
 * Per-subtype ClinGen override decisions (mirror of the ClinGen tool).
 * "" = intentionally left blank.
 */
function eic_acf_clingen_overrides(): array
{
    return [
        "CMT1A"  => "https://search.clinicalgenome.org/CCID:005837", // PMP22 CMT1A
        "HNPP"   => "https://search.clinicalgenome.org/CCID:008314", // PMP22 HNPP
        "CMT1E"  => "", // PMP22 point-mutation CMT; no CMT1E-specific curation
        "CMT1F"  => "https://search.clinicalgenome.org/CCID:005615", // NEFL AD
        "CMT2E"  => "https://search.clinicalgenome.org/CCID:005615", // NEFL AD
        "CMT2B5" => "https://search.clinicalgenome.org/CCID:005614", // NEFL AR
    ];
}

// Enqueue button logic + localized ClinGen data on the Subtype editor.
add_action("admin_enqueue_scripts", function ($hook) {
    if (!in_array($hook, ["post.php", "post-new.php"], true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== "subtype") {
        return;
    }

    $curations = class_exists("EIC_ClinGen_URL_Tool")
        ? EIC_ClinGen_URL_Tool::curations()
        : [];

    $data = wp_json_encode([
        "curations" => $curations,
        "overrides" => eic_acf_clingen_overrides(),
    ]);

    wp_register_script("eic-acf-url-buttons", false, [], null, true);
    wp_enqueue_script("eic-acf-url-buttons");
    wp_add_inline_script(
        "eic-acf-url-buttons",
        "window.EIC_URLDATA = " . $data . ";\n" . eic_acf_url_buttons_js()
    );
});

function eic_acf_url_buttons_js(): string
{
    return <<<'JS'
(function () {
  function rawurlencode(s) {
    return encodeURIComponent(s).replace(/[!'()*]/g, function (c) {
      return '%' + c.charCodeAt(0).toString(16).toUpperCase();
    });
  }
  function fieldEl(name) {
    return document.querySelector(
      '.acf-field[data-name="' + name + '"] input, ' +
      '.acf-field[data-name="' + name + '"] textarea, ' +
      '.acf-field[data-name="' + name + '"] select'
    );
  }
  function val(name) {
    var el = fieldEl(name);
    return el ? (el.value || '').trim() : '';
  }
  function setVal(name, url) {
    var el = fieldEl(name);
    if (!el) { return false; }
    el.value = url;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }
  function clinvarUrl(gene) {
    if (!gene) { return null; }
    var term = gene +
      '[gene] AND ("clinsig pathogenic"[Properties]' +
      ' OR "clinsig likely pathogenic"[Properties])';
    return 'https://www.ncbi.nlm.nih.gov/clinvar/?term=' + rawurlencode(term);
  }
  function clingenResolve(subtype, gene) {
    var d = window.EIC_URLDATA || {};
    var ov = d.overrides || {};
    var cur = d.curations || {};
    var sk = (subtype || '').toUpperCase();
    if (Object.prototype.hasOwnProperty.call(ov, sk)) {
      return { url: ov[sk], blank: ov[sk] === '' };
    }
    var g = (gene || '').toUpperCase();
    var c = cur[g];
    if (!c) { return { none: true }; }
    if (c.length === 1) { return { url: c[0][0] }; }
    return { multiple: c };
  }

  // Inject the two buttons into their field wrappers (idempotent).
  function injectButtons() {
    var defs = [
      ['clinvar_url', 'clinvar', 'Get ClinVar URL'],
      ['clingen_url', 'clingen', 'Get ClinGen URL']
    ];
    defs.forEach(function (cfg) {
      var wrap = document.querySelector(
        '.acf-field[data-name="' + cfg[0] + '"] .acf-input'
      );
      if (!wrap || wrap.querySelector('.eic-geturl')) { return; }
      var p = document.createElement('p');
      p.style.margin = '6px 0 0';
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'button eic-geturl';
      b.setAttribute('data-kind', cfg[1]);
      b.textContent = cfg[2];
      p.appendChild(b);
      wrap.appendChild(p);
    });
  }

  function onClick(e) {
    var btn = e.target && e.target.closest ? e.target.closest('.eic-geturl') : null;
    if (!btn) { return; }
    e.preventDefault();
    var gene = val('gene_symbol');
    var kind = btn.getAttribute('data-kind');

    if (kind === 'clinvar') {
      var u = clinvarUrl(gene);
      if (!u) { alert('No gene symbol on this record.'); return; }
      setVal('clinvar_url', u);
      return;
    }

    var subtype = val('subtype');
    var r = clingenResolve(subtype, gene);
    if (r.none) {
      alert('No ClinGen CMT curation for gene ' + (gene || '(none)') + '.');
      return;
    }
    if (r.blank) {
      alert('This subtype is intentionally left blank (no subtype-specific ClinGen CMT curation).');
      setVal('clingen_url', '');
      return;
    }
    if (r.multiple) {
      var opts = r.multiple.map(function (x, i) {
        return (i + 1) + ') ' + x[1] + ' (' + x[2] + ')';
      }).join('\n');
      var pick = prompt('Multiple CMT curations for ' + gene + '. Enter number:\n' + opts);
      var idx = parseInt(pick, 10) - 1;
      if (isNaN(idx) || !r.multiple[idx]) { return; }
      setVal('clingen_url', r.multiple[idx][0]);
      return;
    }
    setVal('clingen_url', r.url);
  }

  document.addEventListener('click', onClick);
  document.addEventListener('DOMContentLoaded', injectButtons);
  if (window.acf && typeof acf.addAction === 'function') {
    acf.addAction('ready', injectButtons);
    acf.addAction('append', injectButtons);
  }
  // Fallback for late block-editor rendering.
  var tries = 0;
  var iv = setInterval(function () {
    injectButtons();
    if (++tries > 20) { clearInterval(iv); }
  }, 300);
})();
JS;
}
