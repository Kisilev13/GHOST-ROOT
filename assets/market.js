(() => {
  'use strict';

  // Hydrates the SECONDARY MARKET module and the per-identity MARKET STATE panel
  // from ghostroot.site's own cached proxy (never OpenSea directly). On any error
  // the server-rendered fallback text is left untouched — OpenSea is never a
  // hard dependency for the page.

  const dash = '—';
  const fetchJson = (url) =>
    fetch(url, { cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(9000) })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))));

  const num = (n) => (typeof n === 'number' && isFinite(n) ? n.toLocaleString() : dash);
  const money = (v, sym) =>
    typeof v === 'number' && isFinite(v)
      ? `${v < 1 ? v.toFixed(4).replace(/0+$/, '').replace(/\.$/, '') : v.toFixed(3)}${sym ? ' ' + sym : ''}`
      : dash;

  const set = (root, sel, text) => {
    const el = root.querySelector(sel);
    if (el && typeof text === 'string') el.textContent = text;
  };

  document.querySelectorAll('.gr-market[data-endpoint]').forEach(async (root) => {
    // Only hydrate when the collection is actually tradable.
    const state = root.dataset.state || '';
    if (state !== 'verified' && state !== 'partial') return;
    try {
      const s = await fetchJson(root.dataset.endpoint);
      if (!s || s.available === false || s.source === 'none') return;
      if (s.floor_price != null) set(root, '[data-market-floor]', money(s.floor_price, s.floor_symbol));
      if (s.listed_count != null) set(root, '[data-market-listed]', num(s.listed_count));
      if (s.num_owners != null) set(root, '[data-market-owners]', num(s.num_owners));
      if (s.volume_24h != null) set(root, '[data-market-vol]', money(s.volume_24h, s.volume_symbol));
      if (s.cache_state === 'stale') {
        const attr = root.querySelector('.gr-market-attr');
        if (attr) attr.textContent = 'DATA: OPENSEA API · CACHED (REFRESHING)';
      }
    } catch (_) {
      /* keep server fallback */
    }
  });

  document.querySelectorAll('.gr-market-state[data-endpoint]').forEach(async (root) => {
    const id = root.dataset.ghostId;
    try {
      const a = await fetchJson(root.dataset.endpoint);
      if (!a || a.available === false || !Array.isArray(a.rows)) return;
      const name = id ? 'GHOST//' + String(id).padStart(4, '0') : null;
      const mine = name ? a.rows.filter((r) => r.identity === name) : a.rows;
      const sale = mine.find((r) => r.type === 'SALE');
      const listing = mine.find((r) => r.type === 'LISTING');
      if (sale && sale.price) set(root, '[data-ms-last]', sale.price);
      if (listing && listing.price) set(root, '[data-ms-listing]', listing.price);
      const owner = (sale && sale.to) || (mine[0] && mine[0].to);
      if (owner) set(root, '[data-ms-owner]', owner);
    } catch (_) {
      /* keep server fallback */
    }
  });
})();
