/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*!
 * ------------------------------------------------------------
 * RETURN-STATE — persist "where the user was" for [context_nav]
 * ------------------------------------------------------------
 * The interpost nav's Return button links to a fixed archive URL.
 * This restores the state the user actually left:
 *
 *   - Genes / Dorsal Root : filter selection + pagination (carried
 *                           by the listing URL's query string) and
 *                           scroll position.
 *   - Glossary            : search term + pagination (query string)
 *                           and scroll position.
 *   - What Is CMT /        : scroll position (these have no filters),
 *     Breathing             so Return lands roughly where they were.
 *
 * Mechanism: on a listing page the current URL and scrollY are kept
 * in sessionStorage, keyed by post type. On a single post the Return
 * link is repointed at that saved URL, and a one-shot flag tells the
 * listing to restore scroll when the user arrives back.
 *
 * sessionStorage is per-tab and clears with the tab, so state never
 * leaks between sessions or visitors.
 */
(function () {
	"use strict";

	if (!("sessionStorage" in window)) return;

	// Archive page slug → return key (post type). Keys are shared with
	// the [context_nav] Return link's data-return-key attribute.
	var ARCHIVES = {
		"dorsal-root": "post",
		"cmt-subtype-browser": "subtype",
		"cmt-words": "glossary",
		"what-is-cmt": "what-is-cmt",
		"cmt-and-breathing": "breathing"
	};

	var STATE = "eicReturn:"; // saved listing state, per key
	var RESTORE = "eicReturnTo:"; // pending restore-on-arrival flag, per key
	var MAX_AGE = 30 * 60 * 1000; // ignore state older than 30 minutes

	function lastSegment(path) {
		var parts = path.replace(/\/+$/, "").split("/");
		return parts[parts.length - 1] || "";
	}

	function read(key) {
		try {
			var raw = sessionStorage.getItem(STATE + key);
			if (!raw) return null;
			var st = JSON.parse(raw);
			if (!st || Date.now() - (st.t || 0) > MAX_AGE) return null;
			return st;
		} catch (e) {
			return null;
		}
	}

	function write(key, st) {
		try {
			sessionStorage.setItem(STATE + key, JSON.stringify(st));
		} catch (e) {}
	}

	// ============================================================
	//  LISTING / ARCHIVE PAGE
	// ============================================================
	var listKey = ARCHIVES[lastSegment(window.location.pathname)] || null;

	if (listKey) {
		// (a) Arrived via a Return click → restore scroll position.
		//     Capture the target synchronously before the load-time
		//     save() below overwrites the stored scrollY with 0.
		try {
			if (sessionStorage.getItem(RESTORE + listKey)) {
				sessionStorage.removeItem(RESTORE + listKey);
				var restoreSt = read(listKey);
				if (restoreSt && typeof restoreSt.scrollY === "number") {
					var target = restoreSt.scrollY;
					var settle = function () {
						window.scrollTo(0, target);
					};
					// After first paint, then again after late layout
					// (card images can change document height).
					requestAnimationFrame(function () {
						requestAnimationFrame(settle);
					});
					window.addEventListener("load", function () {
						setTimeout(settle, 80);
					});
				}
			}
		} catch (e) {}

		// (b) Continuously remember the current state. The URL already
		//     reflects filters, sort, pagination and search via the
		//     AJAX stack's clean-URL updates; scrollY carries position.
		var save = function () {
			write(listKey, {
				url: window.location.pathname + window.location.search,
				scrollY: window.scrollY || window.pageYOffset || 0,
				t: Date.now()
			});
		};

		save(); // seed immediately so an early card click has state

		window.addEventListener("pagehide", save);
		window.addEventListener("beforeunload", save);
		window.addEventListener("popstate", save);

		var scrollTimer;
		window.addEventListener(
			"scroll",
			function () {
				clearTimeout(scrollTimer);
				scrollTimer = setTimeout(save, 200);
			},
			{ passive: true }
		);
	}

	// ============================================================
	//  SINGLE POST — repoint the Return button
	// ============================================================
	function wireReturnLink() {
		var link = document.querySelector(
			".eicmt-ctnav__col--back a[data-return-key]"
		);
		if (!link) return;

		var key = link.getAttribute("data-return-key");
		var st = read(key);

		// Restore filters / pagination / search exactly. Scroll is
		// restored on arrival by the listing-page branch above.
		if (st && st.url) {
			link.setAttribute("href", st.url);
		}

		link.addEventListener("click", function () {
			try {
				sessionStorage.setItem(RESTORE + key, "1");
			} catch (e) {}
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", wireReturnLink);
	} else {
		wireReturnLink();
	}
})();
