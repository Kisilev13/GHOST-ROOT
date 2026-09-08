(() => {
  'use strict';
  document.querySelectorAll('.gr-terminal').forEach(root => {
    const form = root.querySelector('form'), input = form.elements.command, output = root.querySelector('[role="log"]'), button = form.querySelector('button');
    const history = []; let index = 0;
    function line(text) { const p = document.createElement('p'); p.textContent = text; output.append(p); while (output.childElementCount > 200) output.firstElementChild.remove(); output.scrollTop = output.scrollHeight; }
    input.addEventListener('keydown', event => {
      if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault(); index = Math.max(0, Math.min(history.length, index + (event.key === 'ArrowUp' ? -1 : 1))); input.value = history[index] || '';
      }
    });
    form.addEventListener('submit', async event => {
      event.preventDefault(); const command = input.value.trim(); if (!command || button.disabled) return;
      history.push(command); if (history.length > 50) history.shift(); index = history.length;
      line('guest@root:~$ ' + command); input.value = ''; button.disabled = true;
      try {
        const response = await fetch(root.dataset.endpoint, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({command}), signal: AbortSignal.timeout(10000)});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'SIGNAL UNAVAILABLE');
        if (data.clear) output.replaceChildren();
        else (data.lines || []).forEach(line);
      } catch (error) { line(error.name === 'TimeoutError' ? 'SIGNAL TIMED OUT. Try again.' : error.message); }
      finally { button.disabled = false; input.focus(); }
    });
  });
})();
