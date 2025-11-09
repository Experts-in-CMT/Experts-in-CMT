/**
 * ============================================================
 *  genes-ajax.js
 *  ------------------------------------------------------------
 *  Genes Database AJAX Stack (DR parity)
 *  Handles filter, search, sort, and pagination actions.
 *  Replaces inner #genes-results-root content with fresh results.
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.site-search.genes-filter[data-loop="genes"]');
  const root = document.querySelector('#genes-results-root');
  if (!form || !root || !window.GENES_AJAX) return;

  /* ============================================================
     ===================== [ SECTION: CONFIG ] ===================
     ============================================================ */
  const cfg       = window.GENES_AJAX || {};
  const ACTION    = cfg.action || 'genes_get_loop';
  const AJAX_URL  = cfg.url || (window.ajaxurl || '/wp-admin/admin-ajax.php');
  const NONCE     = cfg.nonce || '';

  const searchKey = form.dataset.searchParam || 'qs';
  const pagedKey  = form.dataset.pagedParam  || 'gd_paged';
  const sortKey   = form.dataset.sortParam   || 'gd_sort';
  const anchorSel = form.dataset.anchor      || '#results';
  const perPage   = form.dataset.perPage     || '12';

  const facetKeys = ['cmt_type', 'inheritance', 'neuropathy', 'chromosome'];

  /* ============================================================
     ===================== [ SECTION: HELPERS ] =================
     ============================================================ */

  function paramsFromForm() {
    const fd = new FormData(form);
    const p = new URLSearchParams();
    fd.forEach((val, key) => {
      if (typeof val === 'string' && val.trim() !== '') {
        p.set(key, val.trim());
      }
    });
    facetKeys.forEach((key) => {
      const v = p.get(key);
      if (v === '0' || v === '' || v == null) p.delete(key);
    });
    return p;
  }

  function updateUrl(params, opts = {}) {
    const { scroll = false } = opts;
    const url = new URL(window.location.href);
    url.search = params.toString();
    window.history.replaceState(null, '', url.toString());
    if (scroll) focusResults();
  }

  function setLoading(isLoading) {
    const el = root.querySelector('.genes-totals');
    if (!el) return;
    el.setAttribute('aria-busy', isLoading ? 'true' : 'false');
  }

  function focusResults() {
    const target = document.querySelector(anchorSel) || root;
    if (!target) return;
    target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
  }

  /* ============================================================
     ===================== [ SECTION: FETCH ] ===================
     ============================================================ */

  async function fetchResults(params, opts = {}) {
    const { scroll = true } = opts;
    setLoading(true);

    try {
      const body = new URLSearchParams();
      body.set('action', ACTION);
      if (NONCE) body.set('nonce', NONCE);

      body.set(pagedKey, params.get(pagedKey) || '1');
      if (searchKey) body.set(searchKey, params.get(searchKey) || '');
      if (sortKey) body.set(sortKey, params.get(sortKey) || '');
      if (perPage) body.set('per_page', perPage);

      facetKeys.forEach((key) => {
        const v = params.get(key);
        if (v != null) body.set(key, v);
      });

      const res = await fetch(AJAX_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body: body.toString(),
      });

      const json = await res.json();
      if (!json || json.success !== true) throw new Error('AJAX error');

      root.innerHTML = json.data.html || '';
      setLoading(false);
      if (scroll) focusResults();

    } catch (err) {
      console.error('[Genes AJAX]', err);
      setLoading(false);
    }
  }

  /* ============================================================
     ===================== [ SECTION: FORM HOOKS ] ===============
     ============================================================ */

  // Submit button → full form reload
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = paramsFromForm();
    params.delete(pagedKey);
    updateUrl(params);
    fetchResults(params);
  });

  // Facet dropdowns (auto-submit)
  const facetSelects = form.querySelectorAll('.genes-filter__select');
  facetSelects.forEach((el) => {
    el.addEventListener('change', function () {
      const params = paramsFromForm();
      params.delete(pagedKey);
      updateUrl(params);
      fetchResults(params);
    });
  });

  // Search input (debounced)
  const searchInput = form.querySelector(`input[name="${searchKey}"]`);
  if (searchInput) {
    let timer = null;
    searchInput.addEventListener('input', function () {
      const params = paramsFromForm();
      params.delete(pagedKey);
      updateUrl(params, { scroll: false });
      clearTimeout(timer);
      timer = setTimeout(() => fetchResults(params), 350);
    });

    // Built-in “x” clear behavior
    searchInput.addEventListener('search', function () {
      if (searchInput.value === '') {
        const params = paramsFromForm();
        params.delete(searchKey);
        params.delete(pagedKey);
        updateUrl(params, { scroll: false });
        fetchResults(params);
      }
    });
  }

  // RESET link (clear all filters)
  const resetLink = form.querySelector('[data-role="genes-reset"], .genes-filter__link');
  if (resetLink) {
    resetLink.addEventListener('click', function (e) {
      e.preventDefault();
      const params = new URLSearchParams();
      updateUrl(params);
      fetchResults(params);
    });
  }

  /* ============================================================
     ===================== [ SECTION: SORT TOOLBAR ] =============
     ============================================================ */

  const sortSel = document.querySelector(`[name="${sortKey}"]`);
  let suppressSortChange = false;

  // Sort dropdown change
  if (sortSel) {
    sortSel.addEventListener('change', function () {
      if (suppressSortChange) {
        suppressSortChange = false;
        return;
      }
      const p = paramsFromForm();
      p.delete(pagedKey);
      if (!sortSel.value) p.delete(sortKey);
      else p.set(sortKey, sortSel.value);
      updateUrl(p);
      fetchResults(p, { scroll: false });
    });
  }

  // Sort clear button
  const clearBtn = document.querySelector('.genes-sort__clear');
  if (clearBtn) {
    clearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (sortSel) {
        suppressSortChange = true;
        sortSel.value = '';
      }
      const p = paramsFromForm();
      p.delete(sortKey);
      p.delete(pagedKey);
      updateUrl(p, { includeAnchor: false });
      fetchResults(p, { scroll: false });
    });
  }

  /* ============================================================
     ===================== [ SECTION: PAGINATION ] ===============
     ============================================================ */

  root.addEventListener('click', function (e) {
    const a = e.target.closest('a.page-numbers, a.prev, a.next');
    if (!a) return;
    e.preventDefault();
    e.stopImmediatePropagation();

    const link = new URL(a.href, window.location.origin);
    const linkParams = new URLSearchParams(link.search);
    const nextPage = linkParams.get(pagedKey) || '1';

    const current = paramsFromForm();
    current.delete(pagedKey);
    current.set(pagedKey, nextPage);

    updateUrl(current);
    fetchResults(current);
  }, true);
});
