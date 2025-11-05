document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.site-search[data-loop="dr"]');
  const root = document.querySelector('#dr-results-root');
  if (!form || !root || !window.DR_AJAX) return;

  const anchor   = form.dataset.anchor || '#results';
  const pagedKey = form.dataset.pagedParam || 'dr_paged';
  const searchKey= form.dataset.searchParam || 'qs';
  const catKey   = form.dataset.categoryParam || 'dr_cat';
  const sortKey  = form.dataset.sortParam || 'dr_sort';
  const perPage  = form.dataset.perPage || '';

  function paramsFromForm(){
    return new URLSearchParams(new FormData(form));
  }
  function updateUrl(params){
    const qs = params.toString();
    const url = window.location.pathname + (qs ? '?' + qs : '') + anchor;
    window.history.replaceState(null, '', url);
  }
  function setLoading(on){ root.classList.toggle('is-loading', !!on); }
  function focusResults(){
    const t = root.querySelector(anchor);
    if (!t) return;
    t.setAttribute('tabindex','-1');
    t.focus({ preventScroll: true });
    t.scrollIntoView({ behavior:'smooth', block:'start' });
  }

  async function fetchResults(params){
    setLoading(true);
    try{
      const body = new URLSearchParams();
      body.set('action', 'dr_get_posts');
      body.set('nonce', DR_AJAX.nonce);
      if (searchKey) body.set('qs', params.get(searchKey) || '');
      if (catKey)    body.set('dr_cat', params.get(catKey) || '');
      if (sortKey)   body.set('dr_sort', params.get(sortKey) || '');
      body.set('dr_paged', params.get(pagedKey) || '1');
      if (perPage)   body.set('per_page', perPage);

      const res  = await fetch(DR_AJAX.url, {
        method: 'POST',
        headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      });
      const json = await res.json();
      if (!json.success) throw new Error('AJAX error');
      root.innerHTML = json.data.html;   // swap
      setLoading(false);
      focusResults();
      bindPagination();
    }catch(e){
      setLoading(false);
      console.error(e);
    }
  }

  // Submit → reset page
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const p = paramsFromForm();
    p.delete(pagedKey);
    updateUrl(p);
    fetchResults(p);
  });

  // Category change → auto-submit
  const cat = form.querySelector(`[name="${catKey}"]`);
  if (cat){
    cat.addEventListener('change', function(){
      const p = paramsFromForm();
      p.delete(pagedKey);
      if (!cat.value) p.delete(catKey);
      updateUrl(p);
      fetchResults(p);
    });
  }

  // Sort change → fetch new results (dropdown lives in dr-posts.php)
  const sortSel = document.querySelector(`[name="${sortKey}"]`);
  if (sortSel) {
    sortSel.addEventListener('change', function () {
      const p = new URLSearchParams(new FormData(form));
      p.delete(pagedKey); // reset pagination
      if (!sortSel.value) p.delete(sortKey); else p.set(sortKey, sortSel.value);
      updateUrl(p);
      fetchResults(p);
    });
  }


  // Built-in clear on <input type="search">
  const searchInput = form.querySelector(`input[name="${searchKey}"]`);
  if (searchInput){
    searchInput.addEventListener('search', function(){
      if (searchInput.value === ''){
        const p = paramsFromForm();
        [searchKey, pagedKey, catKey].forEach(k => k && p.delete(k));
        updateUrl(p);
        fetchResults(p);
      }
    });
  }

  // RESET link
  const resetLink = form.querySelector('.site-search__reset');
  if (resetLink){
    resetLink.addEventListener('click', function(e){
      e.preventDefault();
      const p = paramsFromForm();
      [searchKey, pagedKey, catKey].forEach(k => k && p.delete(k));
      updateUrl(p);
      fetchResults(p);
    });
  }

  // Pagination delegation (rebind after swap)
  function bindPagination(){
    const pager = root.querySelector('.genes-pagination');
    if (!pager) return;
    pager.addEventListener('click', function(e){
      const a = e.target.closest('a');
      if (!a) return;
      e.preventDefault();
      const u = new URL(a.href, window.location.origin);
      const p = new URLSearchParams(u.search);
      updateUrl(p);
      fetchResults(p);
    }, { once:true });
  }
  bindPagination();
});
