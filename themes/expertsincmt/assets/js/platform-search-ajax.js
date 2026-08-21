/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ------------------------------------------------------------
 * Platform Search — live results
 * ------------------------------------------------------------
 * Progressive enhancement over the [platform_search_filter] +
 * [platform_search_results] pair on the search page:
 *
 *   - Typing (>= 2 chars) fetches results after a short debounce
 *     and swaps them into #ps-results-root in place.
 *   - Explicit submits are intercepted, fetched the same way, and
 *     push a history entry so Back/Forward steps through searches.
 *   - Typing replaces the URL instead, so a query does not leave
 *     one history entry per keystroke.
 *   - In-flight requests are aborted when a newer one starts.
 *
 * On pages without #ps-results-root (or if this file fails), the
 * form falls back to its native GET submit unchanged.
 */
document.addEventListener('DOMContentLoaded', function () {
	const form = document.querySelector('.ps-form');
	const input = document.getElementById('ps-input');
	const root = document.getElementById('ps-results-root');
	if (!form || !input || !root || !window.PS_AJAX) return;

	const endpoint = PS_AJAX.url;
	const nonce = PS_AJAX.nonce;
	const MIN_CHARS = 2;
	const DEBOUNCE_MS = 350;

	let timer = null;
	let controller = null;
	let lastRendered = (input.value || '').trim();

	function setBusy(busy) {
		root.classList.toggle('is-loading', busy);
		// Tell assistive tech the live region is updating
		root.setAttribute('aria-busy', busy ? 'true' : 'false');
	}

	// Handoff-less overflow: the server ships every pill plus a hidden
	// "Show all N" button; activating the collapse here means a no-JS
	// visit simply sees the full list. Runs on load and after each swap.
	function initCollapsibles() {
		root.querySelectorAll('.ps-list[data-collapsible]').forEach(function (list) {
			list.classList.add('is-collapsed');
			const btn = list.parentElement.querySelector('.ps-show-all');
			if (btn) { btn.hidden = false; }
		});
	}

	// Delegated so it survives every innerHTML swap. One-way reveal.
	root.addEventListener('click', function (e) {
		const btn = e.target.closest('.ps-show-all');
		if (!btn) return;
		const group = btn.closest('.ps-group');
		const list = group && group.querySelector('.ps-list');
		if (list) { list.classList.remove('is-collapsed'); }
		btn.closest('.ps-view-all').hidden = true;
		// Hiding the focused button would drop focus to <body>;
		// hand it to the first pill the reveal just uncovered.
		const first = list && list.querySelector('.ps-item--more a');
		if (first) { first.focus(); }
	});

	initCollapsibles();

	// Mirror the query into the URL so reload/share/Back all work.
	// Stale pagination/sort params from other stacks are dropped.
	function updateUrl(q, push) {
		const params = new URLSearchParams(window.location.search);
		params.delete('gd_paged');
		params.delete('gd_sort');
		if (q) { params.set('s', q); } else { params.delete('s'); }
		const qs = params.toString();
		const url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
		const cur = window.location.pathname + window.location.search + window.location.hash;
		try {
			if (push && url !== cur) { window.history.pushState({ ps: q }, '', url); }
			else { window.history.replaceState({ ps: q }, '', url); }
		} catch (e) {}
	}

	function fetchResults(q, opts) {
		const push = !!(opts && opts.push);
		const scroll = !!(opts && opts.scroll);

		if (controller) { controller.abort(); }
		controller = new AbortController();
		setBusy(true);

		const body = new URLSearchParams();
		body.set('action', 'eic_platform_search');
		body.set('nonce', nonce);
		body.set('s', q);

		fetch(endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
			signal: controller.signal
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success || !res.data) { return; }
				root.innerHTML = res.data.html;
				initCollapsibles();
				lastRendered = q;
				updateUrl(q, push);
				setBusy(false);
				if (scroll) {
					const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
					root.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
				}
			})
			.catch(function (err) {
				// Aborted (newer request took over) or network error:
				// keep whatever is currently rendered.
				if (!err || err.name !== 'AbortError') { setBusy(false); }
			});
	}

	// Live results while typing (replace history, no scroll).
	input.addEventListener('input', function () {
		const q = (input.value || '').trim();
		clearTimeout(timer);
		if (q.length < MIN_CHARS || q === lastRendered) { return; }
		timer = setTimeout(function () { fetchResults(q, { push: false }); }, DEBOUNCE_MS);
	});

	// Explicit submit: same fetch, but a real history entry + scroll.
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		clearTimeout(timer);
		const q = (input.value || '').trim();
		if (q.length < MIN_CHARS) {
			root.innerHTML = '<p>Please enter at least 2 characters.</p>';
			return;
		}
		fetchResults(q, { push: true, scroll: true });
	});

	// Back/Forward: restore the input and re-render that entry's query.
	window.addEventListener('popstate', function () {
		const q = (new URLSearchParams(window.location.search).get('s') || '').trim();
		input.value = q;
		if (q.length >= MIN_CHARS) { fetchResults(q, { push: false }); }
	});
});
