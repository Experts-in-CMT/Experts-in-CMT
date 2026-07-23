/*!
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
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


document.addEventListener('DOMContentLoaded', function() {
	const form = document.querySelector('form.genes-filter[data-loop="genes"]');
	const root = document.querySelector('#genes-results-root');

	if (!form || !root || !window.GENES_AJAX) return;

	const anchor = form.dataset.anchor || '#results';

	const endpoint = GENES_AJAX.url;
	const nonce = GENES_AJAX.nonce;

	// External sort dropdown + clear button (lives outside the form)
	const sortSel = document.querySelector('.genes-sort__select');
	const sortClearBtn = document.querySelector('.genes-sort__clear');

	// Shared URL config: produce clean, slug-based query strings.
	// (POST payload still carries raw term_ids + per_page.)
	const URL_CFG = {
		drop: ['per_page'],
		taxKeys: ['cmt_type', 'inheritance', 'neuropathy', 'chromosome'],
		pagedKey: 'gd_paged',
		sortKey: 'gd_sort',
		slugMap: (window.GENES_AJAX && GENES_AJAX.taxSlugs) || {}
	};

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
		const {
			includeAnchor = true,
				push = false
		} = opts;
		const clean = (window.EICLoop && EICLoop.cleanParams) ?
			EICLoop.cleanParams(params, URL_CFG) :
			params;
		const qs = clean.toString();
		const base = window.location.pathname;
		let newUrl = qs ? `${base}?${qs}` : base;

		if (includeAnchor && anchor) {
			newUrl += anchor;
		}

		// Discrete filter actions push a history entry so Back/Forward
		// step through filter states. Debounced typing replaces instead,
		// so a search term doesn't leave one entry per keystroke.
		if (push && newUrl !== window.location.pathname + window.location.search + window.location.hash) {
			window.history.pushState({
				eicLoop: 'genes'
			}, '', newUrl);
		} else {
			window.history.replaceState({
				eicLoop: 'genes'
			}, '', newUrl);
		}
	}

	// ------------------------------------------------------------
	// Helper: Focus and scroll to results block
	// ------------------------------------------------------------
	function focusResults(opts = {}) {
		const {
			scroll = true
		} = opts;
		const t = document.querySelector(anchor);
		if (!t) return;

		t.setAttribute('tabindex', '-1');
		t.focus({
			preventScroll: true
		});

		if (scroll) {
			t.scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
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
				focusResults({
					scroll: true
				});
			}
		}, 150);
	}

	// ------------------------------------------------------------
	// Helper: Repaint facet counts from the endpoint payload
	//
	// The filter controls sit outside the swapped results root, so
	// labels are rewritten in place. Options carry their bare label
	// in data-facet-label so re-appending a count never compounds.
	// A zero-count option is disabled unless it's the current
	// selection, which must stay selectable so the user can leave it.
	// ------------------------------------------------------------
	function applyFacetCounts(facets) {
		const tax = facets.tax || {};

		Object.keys(tax).forEach(function(taxonomy) {
			const counts = tax[taxonomy] || {};
			form
				.querySelectorAll('option[data-facet-tax="' + taxonomy + '"]')
				.forEach(function(opt) {
					const termId = opt.getAttribute('data-facet-term');
					const label = opt.getAttribute('data-facet-label') || opt.textContent.trim();
					const n = parseInt(counts[termId], 10) || 0;

					opt.textContent = label + ' (' + n + ')';
					opt.disabled = n === 0 && !opt.selected;
				});
		});

		const flags = facets.flags || {};
		Object.keys(flags).forEach(function(flag) {
			const span = form.querySelector('[data-facet-flag="' + flag + '"]');
			if (!span) return;

			const label = span.getAttribute('data-facet-label') || span.textContent.trim();
			const n = parseInt(flags[flag], 10) || 0;
			span.textContent = label + ' (' + n + ')';

			// A zero-count flag is a dead end; leave it clickable only
			// if it's already on, so it can be switched back off.
			const box = form.querySelector('.genes-filter__checkbox[name="' + flag + '"]');
			if (box && !box.checked) {
				box.dataset.emptyFacet = n === 0 ? '1' : '';
			}
		});

		syncFlagExclusivity();
	}

	// ------------------------------------------------------------
	// Core AJAX fetch
	// ------------------------------------------------------------
	async function fetchResults(params, options = {}) {
		const {
			scroll = false
		} = options;
		const fd = buildFormData(params);

		try {
			const response = await fetch(endpoint, {
				method: 'POST',
				body: fd
			});

			const json = await response.json();

			if (json.success && json.data && json.data.html) {
				updateResults(json.data.html, scroll);
				if (json.data.facets) applyFacetCounts(json.data.facets);
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
		return function(...args) {
			clearTimeout(t);
			t = setTimeout(() => fn.apply(this, args), wait);
		};
	}

	document.addEventListener(
		'input',
		debounce(function(e) {
			const input = e.target.closest('input[name="qs"]');
			if (!input) return;

			const activeForm = input.closest('form.genes-filter[data-loop="genes"]');
			if (!activeForm || activeForm !== form) return;

			// Reset pagination on new search
			const pagedField = form.querySelector('[name="gd_paged"]');
			if (pagedField) pagedField.value = '1';

			const params = collectParamsFromState();
			updateUrl(params);
			fetchResults(params, {
				scroll: false
			});
		}, 400),
		false
	);

	// ------------------------------------------------------------
	// Event: Apply submit (Enter key or BROWSE button)
	// ------------------------------------------------------------

	form.addEventListener('submit', function(e) {
		e.preventDefault();

		form.setAttribute('action', window.location.pathname);


		// Reset pagination to page 1 on explicit Apply
		const pagedField = form.querySelector('[name="gd_paged"]');
		if (pagedField) pagedField.value = '1';

		const params = collectParamsFromState();
		updateUrl(params, { push: true });
		fetchResults(params, {
			scroll: true
		});
	});

	// ------------------------------------------------------------
	// Event: Change any dropdown (.genes-filter__select)
	// ------------------------------------------------------------
	form.querySelectorAll('.genes-filter__select').forEach((select) => {
		select.addEventListener('change', function() {
			// Reset pagination when filters change
			const pagedField = form.querySelector('[name="gd_paged"]');
			if (pagedField) pagedField.value = '1';

			const params = collectParamsFromState();
			updateUrl(params, { push: true });
			fetchResults(params, {
				scroll: false
			});
		});
	});

	// ------------------------------------------------------------
	// Gene group exclusivity
	// "Unknown Gene" cannot co-exist with "Mitochondrial
	// involvement" or "ARS Genes": a subtype with no identified
	// causative gene cannot carry a mito or ARS gene, so any
	// combination is guaranteed empty. Disable the opposing side
	// rather than silently unchecking it, so the constraint is
	// visible. The server enforces the same rule independently.
	// ------------------------------------------------------------
	function syncFlagExclusivity() {
		const unknown = form.querySelector('.genes-filter__checkbox[name="unknown"]');
		const others = Array.from(
			form.querySelectorAll('.genes-filter__checkbox[name="mito"], .genes-filter__checkbox[name="ars"]')
		);
		if (!unknown || !others.length) return;

		const anyOther = others.some((b) => b.checked);

		// A box is unavailable if the opposing side is active, or if it
		// would return nothing. Either way a checked box stays clickable
		// so the user can always switch it back off.
		const isEmpty = (b) => b.dataset.emptyFacet === '1' && !b.checked;

		unknown.disabled = anyOther || isEmpty(unknown);
		others.forEach((b) => {
			b.disabled = unknown.checked || isEmpty(b);
		});

		// Reflect disabled state on the wrapping label for styling
		form.querySelectorAll('.genes-filter__check').forEach((label) => {
			const box = label.querySelector('.genes-filter__checkbox');
			label.classList.toggle('is-disabled', !!(box && box.disabled));
		});
	}

	// Apply on load so restored URL state renders correctly
	syncFlagExclusivity();

	// ------------------------------------------------------------
	// Event: Toggle a gene group checkbox (.genes-filter__checkbox)
	// ------------------------------------------------------------
	form.querySelectorAll('.genes-filter__checkbox').forEach((box) => {
		box.addEventListener('change', function() {
			syncFlagExclusivity();

			// Reset pagination when filters change
			const pagedField = form.querySelector('[name="gd_paged"]');
			if (pagedField) pagedField.value = '1';

			const params = collectParamsFromState();
			updateUrl(params, { push: true });
			fetchResults(params, {
				scroll: false
			});
		});
	});

	// ------------------------------------------------------------
	// Event: Pagination links inside results
	// ------------------------------------------------------------
	root.addEventListener('click', function(e) {
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
		updateUrl(params, { push: true });
		fetchResults(params, {
			scroll: true
		});
	});

	// ------------------------------------------------------------
	// Sort handling (dropdown)
	// ------------------------------------------------------------
	if (sortSel) {
		sortSel.addEventListener('change', function() {
			// Reset pagination when sort changes
			const pagedField = form.querySelector('[name="gd_paged"]');
			if (pagedField) pagedField.value = '1';

			const params = collectParamsFromState();
			updateUrl(params, { push: true });
			fetchResults(params, {
				scroll: true
			});
		});
	}

	// ------------------------------------------------------------
	// Sort CLEAR button
	// ------------------------------------------------------------
	if (sortClearBtn) {
		sortClearBtn.addEventListener('click', function(e) {
			e.preventDefault();

			if (sortSel) {
				sortSel.value = '';
			}

			// Reset pagination
			const pagedField = form.querySelector('[name="gd_paged"]');
			if (pagedField) pagedField.value = '1';

			const params = collectParamsFromState();
			updateUrl(params, { push: true });
			fetchResults(params, {
				scroll: false
			});
		});
	}

	// ------------------------------------------------------------
	// Event: Reset button (.genes-filter__link)
	// ------------------------------------------------------------
	const resetLink = form.querySelector('.genes-filter__link');
	if (resetLink) {
		resetLink.addEventListener('click', function(e) {
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
			updateUrl(params, { push: true });
			fetchResults(params, {
				scroll: false
			});
		});
	}

	// ------------------------------------------------------------
	// Hydrate form state from the URL on load.
	// The server already rendered the correct results (slug-or-id
	// resolver), so this only syncs the visible controls and
	// normalizes a legacy numeric URL into the clean slug form.
	// ------------------------------------------------------------
	function applyUrlToForm(url) {
		const slugMap = URL_CFG.slugMap;

		URL_CFG.taxKeys.forEach(function(key) {
			const raw = url.get(key);
			const sel = form.querySelector('[name="' + key + '"]');
			if (!sel) return;

			// Absent or cleared → return the selector to "All"
			if (!raw || raw === '0') {
				sel.selectedIndex = 0;
				return;
			}

			let id = raw;
			if (window.EICLoop && !EICLoop.isId(raw)) {
				const mapped = EICLoop.idForSlug(slugMap, key, raw);
				if (mapped) id = mapped;
			}
			sel.value = id;
		});

		const qsEl = form.querySelector('input[name="qs"]');
		if (qsEl) qsEl.value = url.get('qs') || '';

		if (sortSel) sortSel.value = url.get('gd_sort') || '';

		const pagedEl = form.querySelector('[name="gd_paged"]');
		if (pagedEl) pagedEl.value = url.get('gd_paged') || '1';

		// Gene group flags
		['mito', 'ars', 'unknown'].forEach(function(key) {
			const box = form.querySelector('.genes-filter__checkbox[name="' + key + '"]');
			if (box) box.checked = url.get(key) === '1';
		});

		syncFlagExclusivity();
	}

	// ------------------------------------------------------------
	// Event: Back / Forward through filter history
	// Re-syncs the controls to the restored URL and refetches
	// without writing a new history entry.
	// ------------------------------------------------------------
	window.addEventListener('popstate', function() {
		const url = new URLSearchParams(window.location.search);
		applyUrlToForm(url);

		const params = collectParamsFromState();
		fetchResults(params, {
			scroll: false
		});
	});

	(function hydrateFromUrl() {
		const url = new URLSearchParams(window.location.search);
		if (![...url.keys()].length) return;

		applyUrlToForm(url);

		updateUrl(collectParamsFromState(), {
			includeAnchor: false
		});
	})();

	console.info('Genes AJAX stack initialized (single-source-parity mode)');
});
