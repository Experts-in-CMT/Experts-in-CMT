/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*!
 * ------------------------------------------------------------
 * GLOSSARY — AJAX
 * ------------------------------------------------------------
 * Purpose:
 *   - Handles live alpha filtering, sorting, pagination,
 *     and native-search input for Glossary terms.
 *   - Swaps ONLY the #results wrapper inside
 *       #glossary-results-root
 *   - Maintains full parity with Genes and Dorsal Root stacks.
 *
 * Notes:
 *   - Uses unified GET/POST param intake
 *   - Prevents double-handling and jitter
 *   - Preserves slug + query params in URL state
 *   - Focus/scroll behaviors match DR + Genes
 */

(function() {
	if (!window.GL_AJAX) return;

	document.addEventListener("DOMContentLoaded", function() {
		const sortForm = document.querySelector(".genes-sort__form");
		const searchForm = document.querySelector("form.site-search");
		const root = document.querySelector("#gl-results-root");

		if (!root) return;

		const anchor = "#results";

		// =========================================================
		// [HELPERS]
		// =========================================================
		function setLoading(state) {
			root.classList.toggle("is-loading", !!state);
		}

		// Shared URL config: strip per_page, empties, and default paged/sort.
		// Glossary has no taxonomy term to slugify (alpha is already clean).
		const GL_CFG = {
			drop: ["per_page"],
			taxKeys: [],
			pagedKey: "g_paged",
			sortKey: "g_sort",
			slugMap: {}
		};

		function updateUrl(params, opts = {}) {
			const clean = (window.EICLoop && EICLoop.cleanParams) ?
				EICLoop.cleanParams(params, GL_CFG) :
				params;
			const qs = clean.toString();
			let url = window.location.pathname + (qs ? "?" + qs : "");
			if (opts.includeAnchor !== false) url += anchor;

			// Discrete filter actions push a history entry so Back/Forward
			// step through filter states. Debounced typing replaces instead.
			const current =
				window.location.pathname + window.location.search + window.location.hash;
			if (opts.push && url !== current) {
				window.history.pushState({
					eicLoop: "glossary"
				}, "", url);
				return;
			}
			window.history.replaceState({
				eicLoop: "glossary"
			}, "", url);
		}

		// Sync the visible controls to a given URL state, then refetch.
		// Used by Back/Forward so restored history matches the UI.
		function applyUrlToControls(url) {
			if (sortForm) {
				const alphaSel = sortForm.querySelector('[name="alpha"]');
				if (alphaSel) alphaSel.value = url.get("alpha") || "";
			}
			if (searchForm) {
				const qsEl = searchForm.querySelector('input[name="qs"]');
				if (qsEl) qsEl.value = url.get("qs") || "";
			}
		}

		window.addEventListener("popstate", function() {
			const url = new URLSearchParams(window.location.search);
			applyUrlToControls(url);

			const p = {};
			url.forEach(function(value, key) {
				p[key] = value;
			});

			fetchHTML(p, {
				scroll: false
			});
		});

		async function fetchHTML(params, opts = {}) {
			const body = new URLSearchParams({
				action: "glossary_get_loop",
				nonce: GL_AJAX.nonce,
				...params,
			});

			const res = await fetch(GL_AJAX.url, {
				method: "POST",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
				},
				credentials: "same-origin",
				body: body.toString(),
			});

			const json = await res.json();
			if (!json.success) throw new Error("AJAX error");

			root.innerHTML = json.data.html;
			if (opts.scroll !== false) focusResults();
		}

		function focusResults() {
			const t = root.querySelector(anchor);
			if (!t) return;
			t.setAttribute("tabindex", "-1");
			t.focus({
				preventScroll: true
			});
			t.scrollIntoView({
				behavior: "smooth",
				block: "start"
			});
		}

		function paramsFromForm(form) {
			return new URLSearchParams(new FormData(form));
		}

		// Mirror live control state (search text, alpha letter) into a
		// param set built from only one of the two forms, so an action
		// on one form never silently drops (or resurrects a stale copy
		// of) the other's active filter. Live controls always win over
		// whatever the source params carried, including the server-
		// rendered hidden qs input in the sort form. Callers that
		// intend to clear a param still delete it AFTER this runs.
		function overlayControls(p) {
			if (searchForm) {
				const qsEl = searchForm.querySelector('input[name="qs"]');
				if (qsEl) {
					if (qsEl.value.trim() !== "") p.set("qs", qsEl.value);
					else p.delete("qs");
				}
			}
			if (sortForm) {
				const alphaSel = sortForm.querySelector('[name="alpha"]');
				if (alphaSel) {
					if (alphaSel.value) p.set("alpha", alphaSel.value);
					else p.delete("alpha");
				}
			}
			return p;
		}

		// =========================================================
		// [PAGINATION]
		// Delegated on the results root (not the pager element) so it
		// survives innerHTML swaps without rebinding, matching dr-ajax.js.
		// The glossary pager renders as .wp-block-query-pagination
		// (see glossary-loop.php), NOT .genes-pagination.
		// =========================================================
		function bindPagination() {
			root.addEventListener("click", function(e) {
				const a = e.target.closest(".wp-block-query-pagination a");
				if (!a || !root.contains(a)) return;
				e.preventDefault();
				const u = new URL(a.href, window.location.origin);
				const p = overlayControls(new URLSearchParams(u.search));
				updateUrl(p, { push: true });
				setLoading(true);
				fetchHTML(Object.fromEntries(p.entries())).finally(() =>
					setLoading(false)
				);
			});
		}

		// =========================================================
		// [SORT FORM: ALPHA SELECT + CLEAR]
		// =========================================================
		if (sortForm) {
			const sortSel = sortForm.querySelector('[name="alpha"]');

			if (sortSel) {
				sortSel.addEventListener("change", async function() {
					const p = overlayControls(paramsFromForm(sortForm));
					p.delete("g_paged");
					setLoading(true);
					try {
						updateUrl(p, { push: true });
						await fetchHTML(Object.fromEntries(p.entries()));
					} finally {
						setLoading(false);
					}
				});
			}

			const clearBtn = sortForm.querySelector(".genes-sort__clear");
			if (clearBtn) {
				clearBtn.addEventListener("click", async function(e) {
					e.preventDefault();
					if (sortSel) sortSel.value = "";

					const p = overlayControls(paramsFromForm(sortForm));
					p.delete("alpha");
					p.delete("g_paged");

					updateUrl(p, {
						includeAnchor: false,
						push: true
					});
					setLoading(true);
					try {
						await fetchHTML(Object.fromEntries(p.entries()), {
							scroll: false,
						});
						const first = sortForm.querySelector("select, input, button");
						if (first) first.focus({
							preventScroll: true
						});
					} finally {
						setLoading(false);
					}
				});
			}
		}

		// =========================================================
		// [SEARCH FORM: INPUT, SUBMIT, RESET]
		// =========================================================
		if (searchForm) {
			const qsInput = searchForm.querySelector('input[name="qs"]');
			const resetBtn = searchForm.querySelector(".site-search__reset");

			// --- Live Input Search (Debounce) ---
			function debounce(fn, wait) {
				let t;
				return function(...args) {
					clearTimeout(t);
					t = setTimeout(() => fn.apply(this, args), wait);
				};
			}

			document.addEventListener(
				"input",
				debounce(async function(e) {
					const input = e.target.closest('input[name="qs"]');
					if (!input || !searchForm.contains(input)) return;
					const p = overlayControls(paramsFromForm(searchForm));
					p.delete("g_paged");

					setLoading(true);
					try {
						updateUrl(p);
						await fetchHTML(Object.fromEntries(p.entries()), {
							scroll: false,
						});
						input.focus();
					} finally {
						setLoading(false);
					}
				}, 350),
				false
			);

			// --- Clear via search box (× button) ---
			if (qsInput) {
				qsInput.addEventListener("search", async function() {
					if (qsInput.value !== "") return;
					const p = overlayControls(paramsFromForm(searchForm));
					p.delete("qs");
					p.delete("g_paged");
					updateUrl(p, {
						includeAnchor: false,
						push: true
					});
					setLoading(true);
					try {
						await fetchHTML(Object.fromEntries(p.entries()), {
							scroll: false,
						});
						qsInput.focus({
							preventScroll: true
						});
					} finally {
						setLoading(false);
					}
				});
			}

			// --- Submit Button ---
			searchForm.addEventListener("submit", async function(e) {
				e.preventDefault();
				const p = overlayControls(paramsFromForm(searchForm));
				p.delete("g_paged");
				updateUrl(p, {
					includeAnchor: false,
					push: true
				}); // Prevent jump
				await fetchHTML(Object.fromEntries(p.entries()), {
					scroll: true
				}); // Enable smooth scroll
			});

			// --- Reset Button ---
			if (resetBtn) {
				resetBtn.addEventListener("click", async function(e) {
					e.preventDefault();

					const qsEl = searchForm.querySelector('input[name="qs"]');
					if (qsEl) qsEl.value = ""; // Clear input field visually

					const p = overlayControls(paramsFromForm(searchForm));
					["qs", "g_paged"].forEach((k) => p.delete(k));
					updateUrl(p, {
						includeAnchor: false,
						push: true
					});

					setLoading(true);
					try {
						await fetchHTML(Object.fromEntries(p.entries()), {
							scroll: false,
						});
						if (qsEl) qsEl.focus({
							preventScroll: true
						});
					} finally {
						setLoading(false);
					}
				});
			}


			// --- Enter Key Safety ---
			document.addEventListener("keydown", function(e) {
				const input = e.target.closest('input[name="qs"]');
				if (!input || !searchForm.contains(input)) return;
				if (e.key !== "Enter") return;
				e.preventDefault();
				searchForm.dispatchEvent(new Event("submit", {
					bubbles: true
				}));
			});
		}

		// =========================================================
		// [INITIAL PAGINATION BIND]
		// =========================================================
		bindPagination();
	});
})();
