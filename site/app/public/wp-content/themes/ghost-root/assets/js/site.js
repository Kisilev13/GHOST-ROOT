(() => {
  'use strict';
  const toggle = document.querySelector('.gr-menu-toggle'), nav = document.querySelector('.gr-nav');
  function close() {nav.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');document.body.classList.remove('gr-menu-open');}
  toggle?.addEventListener('click', () => {const open = toggle.getAttribute('aria-expanded') !== 'true';nav.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',String(open));document.body.classList.toggle('gr-menu-open',open);if(open)nav.querySelector('a').focus();});
  document.addEventListener('keydown', event => {
    if (toggle?.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') {close();toggle.focus();}
    if (event.key === 'Tab') {
      const last = nav.querySelector('a:last-child');
      if (!event.shiftKey && document.activeElement === last) {event.preventDefault();toggle.focus();}
      else if (event.shiftKey && document.activeElement === toggle) {event.preventDefault();last.focus();}
    }
  });
  matchMedia('(min-width:1025px)').addEventListener('change', e => {if(e.matches)close();});
  nav?.querySelectorAll('a').forEach(a => {if(new URL(a.href).pathname === location.pathname)a.setAttribute('aria-current','page');});
  document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    const status = document.querySelector('[data-copy-status]');
    try {await navigator.clipboard.writeText(button.dataset.copy);status.textContent = 'COPIED TO CLIPBOARD.';}
    catch {status.textContent = 'Clipboard unavailable. Select and copy the displayed value.';}
  }));
  // Command guide chips run the terminal directly.
  const terminal = document.querySelector('.gr-terminal');
  document.querySelectorAll('.gr-command-guide [data-command]').forEach(chip => chip.addEventListener('click', () => {
    const form = terminal?.querySelector('form'), input = form?.elements.command;
    if (!form || !input) return;
    input.value = chip.dataset.command;
    input.focus();
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
  }));
  // The record filter is a wall on small screens: collapse it there until asked for,
  // unless a filter is already active. Full-size layout keeps it open (summary is hidden).
  const filterDisclosure = document.querySelector('.gr-filter-disclosure');
  if (filterDisclosure) {
    const small = matchMedia('(max-width:600px)');
    const hasActiveFilter = !!document.querySelector('.gr-active-filters');
    let userToggled = false;
    filterDisclosure.addEventListener('toggle', () => {userToggled = true;});
    const sync = () => {if (!userToggled) filterDisclosure.open = small.matches ? hasActiveFilter : true;};
    sync();
    small.addEventListener('change', sync);
  }
})();
