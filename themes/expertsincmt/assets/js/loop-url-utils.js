/*!
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 *
 * ------------------------------------------------------------
 * Shared loop URL helper
 * ------------------------------------------------------------
 * Used by the Genes / Dorsal Root / Glossary AJAX stacks to
 * produce clean, slug-based URLs and to translate between a
 * select's term_id value and its taxonomy slug.
 *
 * Exposes window.EICLoop. Loaded before each *-ajax.js script.
 */
(function (w) {
  'use strict';

  function isId(v) {
    return /^\d+$/.test(String(v));
  }

  function slugForId(slugMap, tax, id) {
    var m = slugMap && slugMap[tax];
    return m && m[id] != null ? m[id] : null;
  }

  function idForSlug(slugMap, tax, slug) {
    var m = slugMap && slugMap[tax];
    if (!m) return null;
    for (var id in m) {
      if (Object.prototype.hasOwnProperty.call(m, id) && m[id] === slug) {
        return id;
      }
    }
    return null;
  }

  /**
   * Build a clean, slugified URLSearchParams from a raw state source.
   *
   * cfg = {
   *   drop:     ['per_page'],                 // keys always stripped from the URL
   *   taxKeys:  ['cmt_type', ...],            // id->slug, and dropped when empty/'0'
   *   pagedKey: 'gd_paged',                   // dropped when '1'
   *   sortKey:  'gd_sort',                    // dropped when '' or 'default'
   *   slugMap:  { taxonomy: { id: slug } }
   * }
   */
  function cleanParams(src, cfg) {
    cfg = cfg || {};
    var drop = cfg.drop || [];
    var taxKeys = cfg.taxKeys || [];
    var out = new URLSearchParams();

    src.forEach(function (value, key) {
      if (value === '' || value == null) return;
      if (drop.indexOf(key) !== -1) return;
      if (key === cfg.pagedKey && value === '1') return;
      if (key === cfg.sortKey && (value === '' || value === 'default')) return;

      if (taxKeys.indexOf(key) !== -1) {
        if (value === '0') return;
        var slug = slugForId(cfg.slugMap, key, value);
        out.set(key, slug != null ? slug : value);
        return;
      }

      out.set(key, value);
    });

    return out;
  }

  w.EICLoop = {
    isId: isId,
    slugForId: slugForId,
    idForSlug: idForSlug,
    cleanParams: cleanParams
  };
})(window);
