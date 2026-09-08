(() => {
  'use strict';
  document.querySelectorAll('.gr-arg').forEach(root => {
    let stage = 1;
    const form = root.querySelector('form'), button = form.querySelector('button'), message = root.querySelector('[data-arg-message]');
    async function load() {
      const r = await fetch(root.dataset.endpoint + '?stage=' + stage, {cache: 'no-store', signal: AbortSignal.timeout(10000)});
      const data = await r.json(); if (!r.ok) throw new Error(data.message || 'FRAGMENT UNAVAILABLE');
      stage = data.stage; root.querySelector('[data-arg-stage]').textContent = 'STAGE ' + stage + ' / ' + data.total_stages;
      root.querySelector('[data-arg-prompt]').textContent = data.prompt;
      const hints = root.querySelector('[data-arg-hints]'); hints.replaceChildren();
      (data.hints || []).forEach((hint, i) => {const details = document.createElement('details'), summary = document.createElement('summary'), p = document.createElement('p');summary.textContent = 'HINT ' + (i+1);p.textContent = hint;details.append(summary,p);hints.append(details);});
    }
    load().catch(e => { message.textContent = e.message; button.disabled = true; });
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (button.disabled) return; button.disabled = true;
      try {
        const r = await fetch(root.dataset.endpoint + '/verify', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({stage, answer: form.elements.answer.value}), signal: AbortSignal.timeout(10000)});
        const data = await r.json(); message.textContent = data.message || 'SIGNAL UNAVAILABLE';
        if (data.status === 'complete') { form.hidden = true; return; }
        if (!r.ok) return;
        stage = data.stage || stage; if (data.ok) form.elements.answer.value = ''; await load();
      } catch { message.textContent = 'Signal interrupted. Try again.'; }
      finally { button.disabled = false; }
    });
  });
})();
