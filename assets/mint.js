(() => {
  'use strict';
  document.querySelectorAll('.gr-mint').forEach(async root => {
    const button = root.querySelector('[data-wallet]'), notice = root.querySelector('[data-mint-notice]');
    let config;
    const canConnect = () => config && config.deployed && config.state === 'MINT_LIVE' && window.GhostRootMintAdapter && typeof window.GhostRootMintAdapter.connect === 'function';
    function refresh() {
      button.disabled = !canConnect();
      notice.textContent = !config.deployed ? 'NOT YET DEPLOYED' : (config.state !== 'MINT_LIVE' ? 'INITIALIZATION CLOSED' : (canConnect() ? 'Wallet connection available. Review every request in your wallet.' : 'WALLET INTEGRATION PENDING'));
    }
    try {
      const r = await fetch(root.dataset.endpoint, {cache: 'no-store', signal: AbortSignal.timeout(10000)});
      if (!r.ok) throw new Error(); config = await r.json();
      root.querySelector('[data-mint-price]').textContent = config.price + ' SOL';
      root.querySelector('[data-mint-max]').textContent = config.maxPerWallet;
      root.querySelector('[data-mint-state]').textContent = config.state;
      refresh(); window.addEventListener('ghost-root:wallet-ready', refresh);
    } catch { notice.textContent = 'CONFIGURATION UNAVAILABLE. Initialization remains closed.'; }
    button.addEventListener('click', async () => {
      if (!canConnect()) return;
      button.disabled = true;
      try {
        const fresh = await fetch(root.dataset.endpoint, {cache: 'no-store', signal: AbortSignal.timeout(10000)});
        if (!fresh.ok) throw new Error('Configuration unavailable.');
        config = await fresh.json(); if (!canConnect()) { refresh(); return; }
        await window.GhostRootMintAdapter.connect(Object.freeze({...config}));
        notice.textContent = 'Wallet connected. No transaction has been requested.';
      } catch { notice.textContent = 'Wallet connection was not completed.'; }
      finally { button.disabled = !canConnect(); }
    });
  });
})();
