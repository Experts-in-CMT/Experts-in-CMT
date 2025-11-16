/*!
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
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

(function () {
  if (!window.GL_AJAX) return;

  document.addEventListener("DOMContentLoaded", function () {
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

    function updateUrl(params, opts = {}) {
      const qs = params.toString();
      let url = window.location.pathname + (qs ? "?" + qs : "");
      if (opts.includeAnchor !== false) url += anchor;
      window.history.replaceState(null, "", url);
    }

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
      bindPagination();
      if (opts.scroll !== false) focusResults();
    }

    function focusResults() {
      const t = root.querySelector(anchor);
      if (!t) return;
      t.setAttribute("tabindex", "-1");
      t.focus({ preventScroll: true });
      t.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function paramsFromForm(form) {
      return new URLSearchParams(new FormData(form));
    }

    // =========================================================
    // [PAGINATION]
    // =========================================================
    function bindPagination() {
      const pager = root.querySelector(".genes-pagination");
      if (!pager) return;

      pager.addEventListener(
        "click",
        function (e) {
          const a = e.target.closest("a");
          if (!a) return;
          e.preventDefault();
          const u = new URL(a.href, window.location.origin);
          const p = new URLSearchParams(u.search);
          updateUrl(p);
          setLoading(true);
          fetchHTML(Object.fromEntries(p.entries())).finally(() =>
            setLoading(false)
          );
        },
        { once: true }
      );
    }

    // =========================================================
    // [SORT FORM: ALPHA SELECT + CLEAR]
    // =========================================================
    if (sortForm) {
      const sortSel = sortForm.querySelector('[name="alpha"]');

      if (sortSel) {
        sortSel.addEventListener("change", async function () {
          const p = paramsFromForm(sortForm);
          p.delete("g_paged");
          setLoading(true);
          try {
            updateUrl(p);
            await fetchHTML(Object.fromEntries(p.entries()));
          } finally {
            setLoading(false);
          }
        });
      }

      const clearBtn = sortForm.querySelector(".genes-sort__clear");
      if (clearBtn) {
        clearBtn.addEventListener("click", async function (e) {
          e.preventDefault();
          if (sortSel) sortSel.value = "";

          const p = paramsFromForm(sortForm);
          p.delete("alpha");
          p.delete("g_paged");

          updateUrl(p, { includeAnchor: false });
          setLoading(true);
          try {
            await fetchHTML(Object.fromEntries(p.entries()), {
              scroll: false,
            });
            const first = sortForm.querySelector("select, input, button");
            if (first) first.focus({ preventScroll: true });
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
        return function (...args) {
          clearTimeout(t);
          t = setTimeout(() => fn.apply(this, args), wait);
        };
      }

      document.addEventListener(
        "input",
        debounce(async function (e) {
          const input = e.target.closest('input[name="qs"]');
          if (!input || !searchForm.contains(input)) return;
          const p = paramsFromForm(searchForm);
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
        qsInput.addEventListener("search", async function () {
          if (qsInput.value !== "") return;
          const p = paramsFromForm(searchForm);
          p.delete("qs");
          p.delete("g_paged");
          updateUrl(p, { includeAnchor: false });
          setLoading(true);
          try {
            await fetchHTML(Object.fromEntries(p.entries()), {
              scroll: false,
            });
            qsInput.focus({ preventScroll: true });
          } finally {
            setLoading(false);
          }
        });
      }

      // --- Submit Button ---
      searchForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        const p = paramsFromForm(searchForm);
        p.delete("g_paged");
        updateUrl(p, { includeAnchor: false }); // Prevent jump
        await fetchHTML(Object.fromEntries(p.entries()), { scroll: true }); // Enable smooth scroll
      });

  // --- Reset Button ---
if (resetBtn) {
  resetBtn.addEventListener("click", async function (e) {
    e.preventDefault();

    const qsEl = searchForm.querySelector('input[name="qs"]');
    if (qsEl) qsEl.value = ""; // ✅ Clear input field visually

    const p = paramsFromForm(searchForm);
    ["qs", "g_paged"].forEach((k) => p.delete(k));
    updateUrl(p, { includeAnchor: false });

    setLoading(true);
    try {
      await fetchHTML(Object.fromEntries(p.entries()), {
        scroll: false,
      });
      if (qsEl) qsEl.focus({ preventScroll: true });
    } finally {
      setLoading(false);
    }
  });
}


      // --- Enter Key Safety ---
      document.addEventListener("keydown", function (e) {
        const input = e.target.closest('input[name="qs"]');
        if (!input || !searchForm.contains(input)) return;
        if (e.key !== "Enter") return;
        e.preventDefault();
        searchForm.dispatchEvent(new Event("submit", { bubbles: true }));
      });
    }

    // =========================================================
    // [INITIAL PAGINATION BIND]
    // =========================================================
    bindPagination();
  });
})();
