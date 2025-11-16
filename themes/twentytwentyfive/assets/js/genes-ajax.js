/*!
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * GENES DATABASE — AJAX STACK
 * ------------------------------------------------------------
 * Purpose:
 *   - Handles live filtering, search, sorting, and pagination
 *     for the Genes Database loop.
 *   - Replaces ONLY the #results wrapper inside
 *       #genes-results-root
 *     using fragment HTML from genes-loop-endpoints.php.
 *
 * Architecture:
 *   - Full parity with the DR and Glossary AJAX stacks
 *   - Single-source-of-truth param model
 *   - Clean URL-state preservation
 *   - Smooth, predictable focus + scroll behavior
 *
 * Notes:
 *   - Canonical FIELD() order is inviolate
 *   - Per-page overrides respected bi-directionally
 *   - Zero jump-scroll on internal interactions
 */


document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.genes-filter[data-loop="genes"]');
  const root = document.querySelector('#genes-results-root');

  if (!form || !root || !window.GENES_AJAX) return;

  const anchor = form.dataset.anchor || '#results';
  
  const endpoint = GENES_AJAX.url;
  const nonce = GENES_AJAX.nonce;

  // External sort dropdown + clear button (lives outside the form)
  const sortSel = document.querySelector('.genes-sort__select');
  const sortClearBtn = document.querySelector('.genes-sort__clear');

  // ------------------------------------------------------------
  // Helper: Collect params from the CURRENT UI state
  // ------------------------------------------------------------
  function collectParamsFromState() {
    const params = new URLSearchParams();

    // 1) Start with form fields (visible + hidden)
    const fd = new FormData(form);
    for (const [key, value] of fd.entries()) {
      if (value === '' || value === null) continue;
      // Skip transport-only keys (these go only in POST)
      if (key === 'action' || key === 'nonce') continue;
      params.set(key, value);
    }

    // 2) per_page from data attribute (wire this to shortcode per_page in PHP)
    const perPageAttr = form.dataset.perPage || root.dataset.perPage || '';
    if (perPageAttr) {
      params.set('per_page', perPageAttr);
    } else {
      params.delete('per_page');
    }

    // 3) Sort dropdown lives outside the form
    if (sortSel) {
      const sortVal = sortSel.value || '';
      if (sortVal && sortVal !== 'default') {
        params.set('gd_sort', sortVal);
      } else {
        params.delete('gd_sort');
      }
    }

    // 4) Clean pagination if explicitly set to 1
    if (params.get('gd_paged') === '1') {
      params.delete('gd_paged');
    }

    return params;
  }

  // ------------------------------------------------------------
  // Helper: Build FormData payload from params
  // ------------------------------------------------------------
  function buildFormData(params) {
    const fd = new FormData();
    fd.append('action', 'genes_get_loop');
    fd.append('nonce', nonce);

    for (const [key, value] of params.entries()) {
      fd.append(key, value);
    }

    return fd;
  }

    // ------------------------------------------------------------
  // Helper: Update URL to mirror current params
  // ------------------------------------------------------------
  function updateUrl(params, opts = {}) {
    const { includeAnchor = true } = opts;
    const qs = params.toString();
    const base = window.location.pathname;
    let newUrl = qs ? `${base}?${qs}` : base;

    if (includeAnchor && anchor) {
      newUrl += anchor;
    }

    window.history.replaceState({}, '', newUrl);
  }

  // ------------------------------------------------------------
  // Helper: Focus and scroll to results block
  // ------------------------------------------------------------
  function focusResults(opts = {}) {
    const { scroll = true } = opts;
    const t = document.querySelector(anchor);
    if (!t) return;

    t.setAttribute('tabindex', '-1');
    t.focus({ preventScroll: true });

    if (scroll) {
      t.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }


  // ------------------------------------------------------------
  // Helper: Smoothly replace results
  // ------------------------------------------------------------
   function updateResults(html, doScroll) {
    root.style.opacity = '0.3';
    setTimeout(() => {
      root.innerHTML = html;
      root.style.opacity = '1';

      if (doScroll) {
        // DR-style focusing on the results anchor
        focusResults({ scroll: true });
      }
    }, 150);
  }

  // ------------------------------------------------------------
  // Core AJAX fetch
  // ------------------------------------------------------------
  async function fetchResults(params, options = {}) {
    const { scroll = false } = options;
    const fd = buildFormData(params);

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        body: fd
      });

      const json = await response.json();

      if (json.success && json.data && json.data.html) {
        updateResults(json.data.html, scroll);
      } else {
        console.error('Genes AJAX → Invalid response:', json);
      }
    } catch (err) {
      console.error('Genes AJAX → Fetch error:', err);
    }
  }

  // ------------------------------------------------------------
  // Live input search (debounced)
  // ------------------------------------------------------------
  function debounce(fn, wait) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  document.addEventListener(
    'input',
    debounce(function (e) {
      const input = e.target.closest('input[name="qs"]');
      if (!input) return;

      const activeForm = input.closest('form.genes-filter[data-loop="genes"]');
      if (!activeForm || activeForm !== form) return;

      // Reset pagination on new search
      const pagedField = form.querySelector('[name="gd_paged"]');
      if (pagedField) pagedField.value = '1';

      const params = collectParamsFromState();
      updateUrl(params);
      fetchResults(params, { scroll: false });
    }, 400),
    false
  );

  // ------------------------------------------------------------
  // Event: Apply submit (Enter key or BROWSE button)
  // ------------------------------------------------------------
  
form.addEventListener('submit', function (e) {
    e.preventDefault();

    form.setAttribute('action', window.location.pathname);


    // Reset pagination to page 1 on explicit Apply
    const pagedField = form.querySelector('[name="gd_paged"]');
    if (pagedField) pagedField.value = '1';

    const params = collectParamsFromState();
    updateUrl(params);
    fetchResults(params, { scroll: true });
  });

  // ------------------------------------------------------------
  // Event: Change any dropdown (.genes-filter__select)
  // ------------------------------------------------------------
  form.querySelectorAll('.genes-filter__select').forEach((select) => {
    select.addEventListener('change', function () {
      // Reset pagination when filters change
      const pagedField = form.querySelector('[name="gd_paged"]');
      if (pagedField) pagedField.value = '1';

      const params = collectParamsFromState();
      updateUrl(params);
      fetchResults(params, { scroll: false });
    });
  });

  // ------------------------------------------------------------
  // Event: Pagination links inside results
  // ------------------------------------------------------------
  root.addEventListener('click', function (e) {
    const link = e.target.closest('.page-numbers');
    if (!link || !link.href) return;

    e.preventDefault();

    const url = new URL(link.href);
    const newPage = url.searchParams.get('gd_paged') || '1';

    // Ensure hidden pagination field exists
    let pagedField = form.querySelector('[name="gd_paged"]');
    if (!pagedField) {
      pagedField = document.createElement('input');
      pagedField.type = 'hidden';
      pagedField.name = 'gd_paged';
      form.appendChild(pagedField);
    }
    pagedField.value = newPage;

    const params = collectParamsFromState();
    updateUrl(params);
    fetchResults(params, { scroll: true });
  });

  // ------------------------------------------------------------
  // Sort handling (dropdown)
  // ------------------------------------------------------------
  if (sortSel) {
    sortSel.addEventListener('change', function () {
      // Reset pagination when sort changes
      const pagedField = form.querySelector('[name="gd_paged"]');
      if (pagedField) pagedField.value = '1';

      const params = collectParamsFromState();
      updateUrl(params);
      fetchResults(params, { scroll: true });
    });
  }

  // ------------------------------------------------------------
  // Sort CLEAR button
  // ------------------------------------------------------------
  if (sortClearBtn) {
    sortClearBtn.addEventListener('click', function (e) {
      e.preventDefault();

      if (sortSel) {
        sortSel.value = '';
      }

      // Reset pagination
      const pagedField = form.querySelector('[name="gd_paged"]');
      if (pagedField) pagedField.value = '1';

      const params = collectParamsFromState();
      updateUrl(params);
      fetchResults(params, { scroll: false });
    });
  }

  // ------------------------------------------------------------
  // Event: Reset button (.genes-filter__link)
  // ------------------------------------------------------------
  const resetLink = form.querySelector('.genes-filter__link');
  if (resetLink) {
    resetLink.addEventListener('click', function (e) {
      e.preventDefault();

      // Remember current sort selection, if any
      const currentSort = sortSel ? sortSel.value || '' : '';

      // Clear selects and search input
      form.querySelectorAll('.genes-filter__select').forEach((el) => {
        el.selectedIndex = 0;
      });

      const searchField = form.querySelector('input[name="qs"]');
      if (searchField) {
        searchField.value = '';
      }

      // Reset pagination
      const pagedField = form.querySelector('[name="gd_paged"]');
      if (pagedField) pagedField.value = '1';

      // Sort behavior on RESET:
      // - If there was an explicit alternate sort, keep it
      // - Otherwise go back to canonical (no gd_sort)
      if (sortSel) {
        if (currentSort && currentSort !== 'default') {
          sortSel.value = currentSort;
        } else {
          sortSel.value = '';
        }
      }

      const params = collectParamsFromState();
      updateUrl(params);
      fetchResults(params, { scroll: false });
    });
  }

  console.info('Genes AJAX stack initialized (single-source-parity mode)');
});
