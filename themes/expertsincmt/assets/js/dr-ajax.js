/*!
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * DORSAL ROOT — AJAX
 * ------------------------------------------------------------
 * Purpose:
 *   - Handles live filtering, sorting, pagination, and
 *     native-search input for Dorsal Root posts.
 *   - Swaps ONLY the #results wrapper inside
 *       #dr-results-root
 *   - Serves as the gold-standard reference architecture
 *     (Genes and Glossary achieve parity with this stack).
 *
 * Notes:
 *   - Unified GET/POST param intake
 *   - Smooth, no-jump URL and scroll behavior
 *   - Full DR card-grid and sort-toolbar support
 */


document.addEventListener('DOMContentLoaded', function() {
	const form = document.querySelector('form.site-search[data-loop="dr"]');
	const root = document.querySelector('#dr-results-root');
	if (!form || !root || !window.DR_AJAX) return;

	const anchor = form.dataset.anchor || '#results';
	const pagedKey = form.dataset.pagedParam || 'dr_paged';
	const searchKey = form.dataset.searchParam || 'qs';
	const catKey = form.dataset.categoryParam || 'dr_cat';
	const sortKey = form.dataset.sortParam || 'dr_sort';
	const perPage = form.dataset.perPage || '';

	// Shared URL config: clean, slug-based query strings.
	// (POST payload still carries the raw term_id + per_page.)
	const URL_CFG = {
		drop: ['per_page'],
		taxKeys: [catKey],
		pagedKey: pagedKey,
		sortKey: sortKey,
		slugMap: (window.DR_AJAX && DR_AJAX.taxSlugs) || {}
	};

	function paramsFromForm() {
		const p = new URLSearchParams(new FormData(form));

		// Overlay state that lives OUTSIDE the search form so it is
		// never silently dropped from URLs or fetches:
		//  - dr_sort lives in the separate sort toolbar (dr-posts.php)
		//  - dr_paged only exists in the address bar
		// Callers that intend to reset them still call p.delete(...)
		// after this, so filter changes continue to reset paging.
		const sortEl = document.querySelector(`[name="${sortKey}"]`);
		if (sortEl && sortEl.value && !p.get(sortKey)) {
			p.set(sortKey, sortEl.value);
		}
		const current = new URLSearchParams(window.location.search);
		const curPaged = current.get(pagedKey);
		if (curPaged && curPaged !== '1' && !p.get(pagedKey)) {
			p.set(pagedKey, curPaged);
		}
		return p;
	}

	// Updated: allow optional { includeAnchor: true/false, push: true/false }
	function updateUrl(params, opts = {}) {
		const clean = (window.EICLoop && EICLoop.cleanParams) ?
			EICLoop.cleanParams(params, URL_CFG) :
			params;
		const qs = clean.toString();
		let url = window.location.pathname + (qs ? '?' + qs : '');
		if (opts.includeAnchor !== false) url += anchor;

		// Discrete filter actions push a history entry so Back/Forward
		// step through filter states. Debounced typing replaces instead.
		const current = window.location.pathname + window.location.search + window.location.hash;
		if (opts.push && url !== current) {
			window.history.pushState({
				eicLoop: 'dr'
			}, '', url);
			return;
		}
		window.history.replaceState({
			eicLoop: 'dr'
		}, '', url);
	}

	function setLoading(on) {
		root.classList.toggle('is-loading', !!on);
	}

	// Updated: allow optional { scroll: true/false }
	function focusResults(opts = {}) {
		const {
			scroll = true,
			focus = true
		} = opts;
		const t = root.querySelector(anchor);
		if (!t) return;
		if (focus) {
			t.setAttribute('tabindex', '-1');
			t.focus({
				preventScroll: true
			});
		}
		if (scroll) {
			t.scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
		}
	}

	// Repaint category counts from the endpoint payload. Options keep
	// their bare label in data-facet-label so counts never compound.
	// A zero-count option is disabled unless it's the current
	// selection, which must stay selectable so the user can leave it.
	function applyFacetCounts(counts) {
		const sel = form.querySelector(`[name="${catKey}"]`);
		if (!sel) return;

		sel.querySelectorAll('option[data-facet-term]').forEach(function(opt) {
			const termId = opt.getAttribute('data-facet-term');
			const label = opt.getAttribute('data-facet-label') || opt.textContent.trim();
			const n = parseInt(counts[termId], 10) || 0;

			opt.textContent = label + ' (' + n + ')';
			opt.disabled = n === 0 && !opt.selected;
		});
	}

	async function fetchResults(params, opts = {}) {
		setLoading(true);
		try {
			const body = new URLSearchParams();
			body.set('action', 'dr_get_posts');
			body.set('nonce', DR_AJAX.nonce);
			if (searchKey) body.set('qs', params.get(searchKey) || '');
			if (catKey) body.set('dr_cat', params.get(catKey) || '');
			if (sortKey) body.set('dr_sort', params.get(sortKey) || '');
			body.set('dr_paged', params.get(pagedKey) || '1');
			if (perPage) body.set('per_page', perPage);

			const res = await fetch(DR_AJAX.url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			});
			const json = await res.json();
			if (!json.success) throw new Error('AJAX error');
			root.innerHTML = json.data.html;
			if (json.data.facets) applyFacetCounts(json.data.facets);
			setLoading(false);
			focusResults(opts);
		} catch (e) {
			setLoading(false);
			console.error(e);
		}
	}

	// Submit → reset page
	form.addEventListener('submit', function(e) {
		e.preventDefault();
		const p = paramsFromForm();
		p.delete(pagedKey);
		updateUrl(p, { push: true });
		fetchResults(p);
	});

	// Category change → auto-submit
	const cat = form.querySelector(`[name="${catKey}"]`);
	if (cat) {
		cat.addEventListener('change', function() {
			const p = paramsFromForm();
			p.delete(pagedKey);
			if (!cat.value) p.delete(catKey);
			updateUrl(p, { push: true });
			fetchResults(p);
		});
	}

	// Sort change → fetch new results (dropdown lives in dr-posts.php)
	const sortSel = document.querySelector(`[name="${sortKey}"]`);
	let suppressSortChange = false; // prevent double fetch on clear
	if (sortSel) {
		sortSel.addEventListener('change', function() {
			if (suppressSortChange) {
				suppressSortChange = false;
				return;
			}
			const p = new URLSearchParams(new FormData(form));
			p.delete(pagedKey); // reset pagination
			if (!sortSel.value) p.delete(sortKey);
			else p.set(sortKey, sortSel.value);
			updateUrl(p, { push: true });
			fetchResults(p);
		});
	}

	// Sort CLEAR → reset dropdown to Default, no jump scroll
	const clearBtn = document.querySelector('.genes-sort__clear');
	if (clearBtn) {
		clearBtn.addEventListener('click', function(e) {
			e.preventDefault();

			// Reset the dropdown back to Default (empty value)
			if (sortSel) {
				suppressSortChange = true;
				sortSel.value = ''; // or sortSel.selectedIndex = 0;
			}

			const p = paramsFromForm();
			p.delete(sortKey);
			p.delete(pagedKey);

			// Skip adding #results to avoid jump
			updateUrl(p, {
				includeAnchor: false,
				push: true
			});

			// Reload quietly (no scroll animation)
			fetchResults(p, {
				scroll: false
			});
		});
	}

	// Built-in clear on <input type="search"> [Glossary parity]
	const searchInput = form.querySelector(`input[name="${searchKey}"]`);
	if (searchInput) {
		searchInput.addEventListener('search', async function() {
			// Only act when the native "×" clear empties the field
			if (searchInput.value !== '') return;

			const p = paramsFromForm();
			[searchKey, pagedKey, catKey].forEach(k => k && p.delete(k));

			updateUrl(p, {
				includeAnchor: false,
				push: true
			});

			setLoading(true);
			try {
				await fetchResults(p, {
					scroll: false
				});
				// Re-focus the cleared input after results refresh
				const newInput = form.querySelector(`input[name="${searchKey}"]`);
				if (newInput) newInput.focus({
					preventScroll: true
				});
			} finally {
				setLoading(false);
			}
		});
	}


	// --- Live Input Search (Debounced) [Glossary parity] ---
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
			const input = e.target.closest(`input[name="${searchKey}"]`);
			if (!input || !form.contains(input)) return;

			const p = paramsFromForm();
			p.delete(pagedKey);

			updateUrl(p); // Keep anchor
			fetchResults(p, {
				scroll: false
			}).then(() => {
				const newInput = form.querySelector(`input[name="${searchKey}"]`);
				if (newInput) newInput.focus({
					preventScroll: true
				});
			});
		}, 350),
		false
	);


	// RESET link → clear qs, reset category, focus back on category selector
	const resetLink = form.querySelector('.site-search__reset');
	if (resetLink) {
		resetLink.addEventListener('click', async function(e) {
			e.preventDefault();

			// Clear search input if present
			const searchInput = form.querySelector(`input[name="${searchKey}"]`);
			if (searchInput) searchInput.value = '';

			// Reset category to default (empty string)
			const cat = form.querySelector(`[name="${catKey}"]`);
			if (cat) cat.value = '';

			const p = paramsFromForm();
			[searchKey, pagedKey, catKey].forEach(k => k && p.delete(k));

			updateUrl(p, {
				includeAnchor: false,
				push: true
			});

			setLoading(true);
			try {
				await fetchResults(p, {
					scroll: false
				});

				// Focus on category selector after refresh
				if (cat) cat.focus({
					preventScroll: true
				});
			} finally {
				setLoading(false);
			}
		});
	}


	// Pagination: AJAX via event delegation on the results root.
	// Delegated (not bound to the pager element) so it survives result
	// swaps without rebinding. Matches DR's `.page-numbers` links, fetches
	// the page, and scrolls to the #blog section anchor. The link hrefs still
	// carry `#blog`, so with JS disabled a click full-navigates there instead.
	function bindPagination() {
		root.addEventListener('click', function(e) {
			const a = e.target.closest('a.page-numbers');
			if (!a || !root.contains(a)) return;
			e.preventDefault();

			const u = new URL(a.href, window.location.origin);
			const p = new URLSearchParams(u.search);

			updateUrl(p, {
				includeAnchor: false,
				push: true
			});

			fetchResults(p, {
				scroll: false,
				focus: false
			}).then(function() {
				const blog = document.getElementById('blog');
				if (blog) {
					blog.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
				}
			});
		});
	}

	// Hydrate the category select from a slug/id URL on load, and normalize
	// a legacy numeric URL into the clean slug form. The server already
	// rendered the correct results via the slug-or-id resolver.
	function applyUrlToForm(url) {
		const sel = form.querySelector('[name="' + catKey + '"]');
		if (sel) {
			const raw = url.get(catKey);
			if (!raw || raw === '0') {
				sel.selectedIndex = 0;
			} else {
				let id = raw;
				if (window.EICLoop && !EICLoop.isId(raw)) {
					const mapped = EICLoop.idForSlug(URL_CFG.slugMap, catKey, raw);
					if (mapped) id = mapped;
				}
				sel.value = id;
			}
		}

		const qsEl = form.querySelector('input[name="' + searchKey + '"]');
		if (qsEl) qsEl.value = url.get(searchKey) || '';

		if (sortSel) sortSel.value = url.get(sortKey) || '';

		const pagedEl = form.querySelector('[name="' + pagedKey + '"]');
		if (pagedEl) pagedEl.value = url.get(pagedKey) || '1';
	}

	// ------------------------------------------------------------
	// Back / Forward through filter history
	// ------------------------------------------------------------
	window.addEventListener('popstate', function() {
		const url = new URLSearchParams(window.location.search);
		applyUrlToForm(url);
		fetchResults(paramsFromForm(), {
			scroll: false
		});
	});

	(function hydrateFromUrl() {
		const url = new URLSearchParams(window.location.search);
		if (![...url.keys()].length) return;

		applyUrlToForm(url);

		updateUrl(paramsFromForm(), {
			includeAnchor: false
		});
	})();

	bindPagination();
});
