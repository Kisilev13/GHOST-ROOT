(() => {
  'use strict';
  const REDUCE = matchMedia('(prefers-reduced-motion:reduce)').matches;

  document.querySelectorAll('.gr-network-map').forEach(async root => {
    const canvas = root.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const status = root.querySelector('[role="status"]');
    const list = root.querySelector('ul');

    // restrained forensic-telemetry palette — one hue per state
    const PAL = {
      DORMANT:     '#6f6f66',
      ACTIVE:      '#e8e8e3',
      COMPROMISED: '#c23c44',
      ROOTED:      '#9a8348'
    };

    let nodes = [];
    let points = [];
    let hoverIdx = -1;

    // stable per-node pseudo-random in [0,1)
    const hash = n => { const x = Math.sin(n) * 43758.5453; return x - Math.floor(x); };

    function layout(w, h) {
      const cap = w < 600 ? 24 : 48;
      const visible = nodes.slice(0, cap);
      const R = Math.min(w, h) * 0.46;
      // squash the spiral only on wide, short canvases (desktop); keep it round on
      // near-square / portrait mobile canvases so it fills the available height.
      const squash = h / w < 0.62 ? 0.82 : 1;
      return visible.map((node, i) => {
        // golden-angle spiral, widened, with a stable symmetric per-node jitter
        const t = i * 2.399963;
        const rad = Math.sqrt((i + 1) / visible.length) * R;
        const jx = (hash(node.ghost_id * 12.9898) - 0.5) * 24;
        const jy = (hash(node.ghost_id * 78.233) - 0.5) * 24;
        return {
          ...node,
          x: w / 2 + Math.cos(t) * rad + jx,
          y: h / 2 + Math.sin(t) * rad * squash + jy
        };
      });
    }

    function syncLegend(pts) {
      const counts = { DORMANT: 0, ACTIVE: 0, COMPROMISED: 0, ROOTED: 0 };
      pts.forEach(p => { if (counts[p.state] != null) counts[p.state]++; });
      root.querySelectorAll('[data-state-count]').forEach(el => {
        el.textContent = String(counts[el.dataset.stateCount] ?? 0);
      });
    }

    function draw() {
      const w = canvas.clientWidth;
      const h = canvas.clientHeight;
      const scale = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = w * scale;
      canvas.height = h * scale;
      ctx.setTransform(scale, 0, 0, scale, 0, 0);
      ctx.clearRect(0, 0, w, h);

      // faint grid — texture, not competition
      ctx.strokeStyle = '#131313';
      ctx.lineWidth = 1;
      for (let x = 0; x < w; x += 36) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke(); }
      for (let y = 0; y < h; y += 36) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }

      points = layout(w, h);
      const maxLen = w * 0.38;

      // edges: nearest earlier node, dropped if they'd span the whole map
      ctx.strokeStyle = 'rgba(210,210,200,0.12)';
      ctx.lineWidth = 1;
      points.forEach((p, i) => {
        if (i === 0) return;
        const q = points[Math.floor((i - 1) / 2)];
        if (Math.hypot(p.x - q.x, p.y - q.y) > maxLen) return;
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineTo(q.x, q.y);
        ctx.stroke();
      });

      // nodes
      const placed = [];
      const showLabels = w >= 600;
      points.forEach((p, i) => {
        const col = PAL[p.state] || PAL.DORMANT;
        const hot = i === hoverIdx;
        ctx.save();
        if (p.state !== 'DORMANT') {
          ctx.shadowColor = col;
          ctx.shadowBlur = hot ? 12 : 6;
        }
        ctx.fillStyle = col;
        ctx.strokeStyle = col;
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        if (p.state === 'ROOTED') {
          const s = hot ? 7 : 5;
          ctx.rect(p.x - s / 2, p.y - s / 2, s, s);
          ctx.fill();
        } else if (p.state === 'COMPROMISED') {
          const s = hot ? 7 : 5;
          ctx.moveTo(p.x - s, p.y - s); ctx.lineTo(p.x + s, p.y + s);
          ctx.moveTo(p.x + s, p.y - s); ctx.lineTo(p.x - s, p.y + s);
          ctx.stroke();
        } else if (p.state === 'ACTIVE') {
          const s = hot ? 5.5 : 3.7;
          ctx.moveTo(p.x, p.y - s); ctx.lineTo(p.x + s, p.y);
          ctx.lineTo(p.x, p.y + s); ctx.lineTo(p.x - s, p.y);
          ctx.closePath();
          ctx.fill();
          ctx.shadowBlur = 0;
          ctx.beginPath();
          ctx.arc(p.x, p.y, s + 3, 0, Math.PI * 2);
          ctx.globalAlpha = 0.35;
          ctx.stroke();
        } else {
          ctx.arc(p.x, p.y, hot ? 4 : 2.5, 0, Math.PI * 2);
          ctx.fill();
        }
        ctx.restore();

        // labels: interesting states + hovered, collision-avoided, with a halo
        const label = String(p.ghost_id).padStart(4, '0');
        const wantLabel = hot || (showLabels && p.state !== 'DORMANT');
        if (!wantLabel) return;
        const lx = p.x + 9;
        const ly = p.y + 3;
        const box = { x: lx, y: ly - 8, w: 30, h: 11 };
        const clash = !hot && placed.some(b => !(box.x > b.x + b.w || box.x + box.w < b.x || box.y > b.y + b.h || box.y + box.h < b.y));
        if (clash) return;
        placed.push(box);
        ctx.font = '8px monospace';
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#050505';
        ctx.strokeText(label, lx, ly);
        ctx.fillStyle = hot ? '#e8e8e3' : (PAL[p.state] || PAL.DORMANT);
        ctx.fillText(label, lx, ly);
      });

      if (hoverIdx < 0) {
        status.textContent = points.length + ' OF ' + nodes.length + ' SAMPLED RECORDS';
      }
      // legend reflects exactly what is drawn (24 on mobile, 48 on desktop)
      syncLegend(points);
    }

    let raf = 0;
    const schedule = () => { if (!raf) raf = requestAnimationFrame(() => { raf = 0; draw(); }); };

    canvas.addEventListener('pointermove', e => {
      const rect = canvas.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;
      let best = -1;
      let bd = 18;
      points.forEach((p, i) => {
        const d = Math.hypot(p.x - mx, p.y - my);
        if (d < bd) { bd = d; best = i; }
      });
      if (best !== hoverIdx) {
        hoverIdx = best;
        if (best >= 0) {
          status.textContent = 'GHOST//' + String(points[best].ghost_id).padStart(4, '0') + ' — ' + points[best].state;
        }
        schedule();
      }
    });
    canvas.addEventListener('pointerleave', () => {
      if (hoverIdx !== -1) { hoverIdx = -1; schedule(); }
    });

    try {
      const r = await fetch(root.dataset.endpoint, { signal: AbortSignal.timeout(10000) });
      if (!r.ok) throw new Error();
      nodes = (await r.json()).items || [];
      nodes.forEach(n => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        const url = new URL(n.url, location.href);
        if (url.origin !== location.origin) return;
        a.href = url.href;
        a.textContent = n.title + ' / ' + n.state;
        li.append(a);
        list.append(li);
      });
      new ResizeObserver(schedule).observe(canvas);
      draw();
    } catch {
      status.textContent = 'SIGNAL UNAVAILABLE';
    }
  });
})();
