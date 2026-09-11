(() => {
  'use strict';
  const COMMANDS = ['help', 'status', 'whoami', 'scan', 'nodes', 'signal', 'incident', 'identity', 'archive', 'protocol', 'root', 'clear'];
  const TAKES_ARG = ['incident', 'identity'];
  const commonPrefix = list => {
    let p = list[0] || '';
    for (const s of list) { while (!s.startsWith(p)) p = p.slice(0, -1); }
    return p;
  };

  document.querySelectorAll('.gr-terminal').forEach(root => {
    const form = root.querySelector('form'), input = form.elements.command, output = root.querySelector('[role="log"]'), button = form.querySelector('button');
    const statusLine = root.querySelector('.gr-terminal-status');
    const history = []; let index = 0;
    const setStatus = text => { if (statusLine) statusLine.textContent = text; };
    function line(text) { const p = document.createElement('p'); p.textContent = text; output.append(p); while (output.childElementCount > 200) output.firstElementChild.remove(); output.scrollTop = output.scrollHeight; }

    input.addEventListener('keydown', event => {
      if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault(); index = Math.max(0, Math.min(history.length, index + (event.key === 'ArrowUp' ? -1 : 1))); input.value = history[index] || '';
        return;
      }
      if (event.key === 'Escape' && input.value) { event.preventDefault(); input.value = ''; setStatus('INPUT CLEARED'); return; }
      if (event.key === 'Tab' && !event.shiftKey) {
        const val = input.value.trimStart().toLowerCase();
        if (!val || val.includes(' ')) return; // let Tab move focus out
        const matches = COMMANDS.filter(c => c.startsWith(val));
        if (matches.length === 1) {
          if (matches[0] !== val) { event.preventDefault(); input.value = matches[0] + (TAKES_ARG.includes(matches[0]) ? ' ' : ''); setStatus('COMPLETED: ' + matches[0].toUpperCase()); }
          return; // exact match already typed -> Tab again leaves
        }
        if (matches.length > 1) {
          event.preventDefault();
          const common = commonPrefix(matches);
          if (common.length > val.length) input.value = common;
          line(matches.join('   '));
          setStatus(matches.length + ' MATCHES');
        }
      }
    });

    form.addEventListener('submit', async event => {
      event.preventDefault(); const command = input.value.trim(); if (!command || button.disabled) return;
      history.push(command); if (history.length > 50) history.shift(); index = history.length;
      line('archive://guest> ' + command); input.value = ''; button.disabled = true; setStatus('RUNNING QUERY...');
      try {
        const response = await fetch(root.dataset.endpoint, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({command}), signal: AbortSignal.timeout(10000)});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'SIGNAL UNAVAILABLE');
        if (data.clear) output.replaceChildren();
        else (data.lines || []).forEach(line);
        setStatus('CHANNEL READY');
      } catch (error) { line(error.name === 'TimeoutError' ? 'SIGNAL TIMED OUT. Try again.' : error.message); setStatus('SIGNAL LOST'); }
      finally { button.disabled = false; input.focus(); }
    });
  });
})();
