/**
 * ============================================================
 *  GENES AJAX STACK (namespaced for genes-filter only)
 *  ------------------------------------------------------------
 *  Scope:
 *    - Handles AJAX reloading of results (#genes-results-root)
 *    - Listens to form submissions, dropdown changes, pagination
 *    - Uses ONLY .genes-filter__* classes
 *    - Keeps scroll position smooth, no anchor jumps
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.genes-filter[data-loop="genes"]');
  const root = document.querySelector('#genes-results-root');
  if (!form || !root || !window.GENES_AJAX) return;

  const endpoint = GENES_AJAX.url;
  const nonce = GENES_AJAX.nonce;

  // ------------------------------------------------------------
  // Helper: Serialize form to FormData
  // ------------------------------------------------------------
  function getFormData() {
    const data = new FormData(form);
    data.append('action', 'genes_get_loop');
    data.append('nonce', nonce);
    return data;
  }

  // ------------------------------------------------------------
  // Helper: Smoothly replace results (optional scroll)
  // ------------------------------------------------------------
  function updateResults(html, doScroll = false) {
    root.style.opacity = '0.3';
    setTimeout(() => {
      root.innerHTML = html;
      root.style.opacity = '1';
      if (doScroll) {
        const resultsEl = document.querySelector('#results');
        if (resultsEl) {
          window.scrollTo({
            top: resultsEl.offsetTop - 120,
            behavior: 'smooth'
          });
        }
      }
    }, 150);
  }


// ------------------------------------------------------------
// AJAX Fetch
// ------------------------------------------------------------
async function fetchResults(formData, doScroll = false) {
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      body: formData,
    });
    const json = await response.json();

    if (json.success && json.data && json.data.html) {
      updateResults(json.data.html, doScroll);

      // Smooth-scroll to #results ONLY after Apply click
      if (window.lastApplyClick) {
        const resultsEl = document.querySelector('#results');
        if (resultsEl) {
          resultsEl.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
          });
          resultsEl.focus({ preventScroll: true });
        }
        window.lastApplyClick = false; // reset flag
      }
    } else {
      console.error('Genes AJAX → Invalid response:', json);
    }
  } catch (err) {
    console.error('Genes AJAX → Fetch error:', err);
  }
}


// ------------------------------------------------------------
// Track Apply button clicks
// ------------------------------------------------------------
const applyBtn = form.querySelector('.genes-filter__btn');
if (applyBtn) {
  applyBtn.addEventListener('click', () => {
    window.lastApplyClick = true;
  });
}

// ------------------------------------------------------------
// Event: Submit form manually (Enter key or Apply button)
// ------------------------------------------------------------
form.addEventListener('submit', function (e) {
  e.preventDefault();
  const fd = getFormData();
  fetchResults(fd);
});


  // ------------------------------------------------------------
  // Event: Change any dropdown (.genes-filter__select)
  // ------------------------------------------------------------
form.querySelectorAll('.genes-filter__select').forEach(select => {
  select.addEventListener('change', function () {
    // Reset pagination on filter change
    if (form.querySelector('[name="gd_paged"]')) {
      form.querySelector('[name="gd_paged"]').value = 1;
    }
    const fd = getFormData();

    // ✅ Debug snapshot
    console.log('FormData snapshot:', Array.from(fd.entries()));

    fetchResults(fd);
  });
});


  // ------------------------------------------------------------
  // Event: Typing in search input (.genes-filter__input)
  // ------------------------------------------------------------
  const searchInput = form.querySelector('.genes-filter__input');
  if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        if (form.querySelector('[name="gd_paged"]')) {
          form.querySelector('[name="gd_paged"]').value = 1;
        }
        const fd = getFormData();
        fetchResults(fd);
      }, 400);
    });
  }

  // ------------------------------------------------------------
  // Event: Pagination links inside results
  // ------------------------------------------------------------
  root.addEventListener('click', function (e) {
    const link = e.target.closest('.page-numbers');
    if (!link || !link.href) return;

    e.preventDefault();
    const url = new URL(link.href);
    const params = new URLSearchParams(url.search);
    const paged = params.get('gd_paged') || 1;

    if (form.querySelector('[name="gd_paged"]')) {
      form.querySelector('[name="gd_paged"]').value = paged;
    } else {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'gd_paged';
      hidden.value = paged;
      form.appendChild(hidden);
    }

    const fd = getFormData();
    fetchResults(fd);
  });

// ------------------------------------------------------------
// Event: Reset button (.genes-filter__link)
// ------------------------------------------------------------

const resetLink = form.querySelector('.genes-filter__link');
if (resetLink) {
  resetLink.addEventListener('click', function (e) {
    e.preventDefault();

    // Keep current sort value (if not default)
    const sortValue = form.querySelector('[name="gd_sort"]')?.value || '';

    // Clear selects and search inputs
    form.querySelectorAll('.genes-filter__select, .genes-filter__input').forEach(el => {
      if (el.tagName === 'SELECT') el.selectedIndex = 0;
      else el.value = '';
    });

    // Restore the sort value in form (for non-default sorts only)
    if (form.querySelector('[name="gd_sort"]')) {
      form.querySelector('[name="gd_sort"]').value = sortValue;
    }

    // Rebuild the URL — clear all filters and pagination
    const url = new URL(window.location);
    ['gd_paged', 'qs', 'cmt_type', 'inheritance', 'neuropathy', 'chromosome'].forEach(param => {
      url.searchParams.delete(param);
    });

    // Keep sort only if it’s a real alternate sort
    if (sortValue && sortValue !== 'default') {
      url.searchParams.set('gd_sort', sortValue);
    } else {
      url.searchParams.delete('gd_sort');
    }

    // Push clean state to browser
    window.history.replaceState({}, '', url.pathname + (url.search || ''));

    // Fetch canonical results if gd_sort absent
    const fd = getFormData();
    fetchResults(fd);
  });
}

  console.info('Genes AJAX stack initialized (genes-filter only)');
});
