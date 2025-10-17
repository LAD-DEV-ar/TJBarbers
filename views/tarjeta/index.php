<?php
// views/tarjeta/index.php
$miTarjeta = $miTarjeta ?? null;
$alerts = $alerts ?? [];

// Asegurarnos sesión para obtener user id
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = $_SESSION['id'] ?? null;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$link = $scheme . '://' . $host . '/tarjeta/vincular?code=' . rawurlencode($miTarjeta->codigo);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($link);
?>
<!-- Modal de confirmación de canje (colocar dentro de views/tarjeta/index.php) -->
<div id="modalRedeem" class="modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalRedeemTitle" style="display:none;">
  <div class="modal-dialog" role="document">
    <header class="modal-header">
      <h2 id="modalRedeemTitle">Confirmar canje</h2>
      <button type="button" class="modal-close" id="modalCloseBtn" aria-label="Cerrar">&times;</button>
    </header>

    <div class="modal-body">
      <p class="modal-desc">Vas a solicitar el canje de la recompensa. Revisa los datos y confirma.</p>

      <div class="modal-recompensa">
        <div class="mr-left">
          <strong id="modalRecompensaTitulo">Descripción de la recompensa</strong>
          <div id="modalRecompensaCasilla" class="muted">Casilla: —</div>
        </div>
        <div class="mr-right" id="modalRecompensaMeta">
          <!-- aquí podemos mostrar estado o iconos -->
        </div>
      </div>
      
      <div class="modal-feedback" id="modalFeedback" aria-live="polite" style="display:none;"></div>
    </div>

    <footer class="modal-footer">
      <button id="modalCancelBtn" class="btn btn--ghost" type="button">Cancelar</button>
      <button id="modalConfirmBtn" class="btn btn--primary" type="button">Confirmar canje</button>
    </footer>
  </div>
</div>

<main class="tarjeta-page container">
  <section class="card tarjeta-hero" aria-live="polite" aria-labelledby="tarjeta-title">
    <header class="tarjeta-header">
      <div class="tarjeta-header__left">
        <h1 id="tarjeta-title" class="title">Mi Tarjeta de Fidelidad</h1>
        <p class="subtitle">Sigue tu progreso y canjea recompensas del local.</p>
        <p class="subtitle">Puedes compartir este QR o el Codigo para que tus conocidos
            tambien esten vinculados a la tarjeta de fidelidad de la barberia</p>
      </div>

      <div class="tarjeta-header__right" style="display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;">
        <div style="display:flex; gap:.5rem; align-items:center;">
          <button id="btnToggleSound" class="btn btn--ghost" type="button">Activar sonido</button>
        </div>

        <!-- ID del usuario visible con botón copiar -->
        <div class="user-id-box small" style="display:flex; gap:.5rem; align-items:center;">
          <span class="muted">Tu ID:</span>
          <code id="userIdCode" style="padding:0.87rem 1rem; border-radius:6px; background:#f3f4f6; font-weight:700;"><?= htmlspecialchars($userId ?? '—') ?></code>
          <button id="btnCopyUserId" class="btn btn--mini" type="button" title="Copiar ID">Copiar</button>
        </div>
      </div>
    </header>

    <!-- Main card body renderizado por JS -->
    <div id="tarjeta-root" class="tarjeta-root" role="region" aria-label="Tarjeta de fidelidad">
      <div class="tarjeta-loader">Cargando tarjeta…</div>
    </div>

    <!-- Recompensas lista (renderizado por JS) -->
    <div id="recompensas-root" class="recompensas-root" aria-live="polite"></div>
  </section>
</main>

<script>
(function () {
  // -------- CONFIG / ENDPOINTS --------
  const API = {
    tarjeta: '/api/tarjeta',
    recompensas: '/api/tarjeta/recompensas',
    solicitar: '/api/tarjeta/solicitar-canje' // acepta JSON { id_recompensa }
  };

  // -------- DOM NODES --------
  const root = document.getElementById('tarjeta-root');
  const recompensasRoot = document.getElementById('recompensas-root');
  const btnToggleSound = document.getElementById('btnToggleSound');
  const btnCopyUserId = document.getElementById('btnCopyUserId');
  const userIdCode = document.getElementById('userIdCode');

  // Modal nodes (deben existir en DOM)
  const modal = document.getElementById('modalRedeem');
  const modalCloseBtn = document.getElementById('modalCloseBtn');
  const modalCancelBtn = document.getElementById('modalCancelBtn');
  const modalConfirmBtn = document.getElementById('modalConfirmBtn');
  const modalTitle = document.getElementById('modalRecompensaTitulo');
  const modalCasilla = document.getElementById('modalRecompensaCasilla');
  const modalFeedback = document.getElementById('modalFeedback');

  // -------- STATE --------
  let firstLoad = true;
  let polling = null;
  let lastState = null; // { casilla_actual, casilla_max, tarjetaId, recompensas: [...] }
  let _currentRedeemId = null;
  let _lastFocusedBeforeModal = null;

  // -------- Audio (Web Audio API) --------
  let audioCtx = null, masterGain = null, soundEnabled = false;
  function initAudio(){
    if (audioCtx) return;
    try {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      masterGain = audioCtx.createGain();
      masterGain.gain.value = 0.22;
      masterGain.connect(audioCtx.destination);
    } catch(e){
      audioCtx = null;
      console.warn('Audio no disponible', e);
    }
  }
  function playBeep(freq=880, dur=120){
    if (!audioCtx || !soundEnabled) return;
    const now = audioCtx.currentTime;
    const osc = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    g.gain.setValueAtTime(0, now);
    g.gain.linearRampToValueAtTime(1.0, now + 0.01);
    g.gain.exponentialRampToValueAtTime(0.001, now + dur / 1000);
    osc.connect(g);
    g.connect(masterGain);
    osc.start(now);
    osc.stop(now + dur / 1000 + 0.02);
  }

  // Toggle sound UI
  function updateSoundUI(){
    if (!btnToggleSound) return;
    if (soundEnabled) { btnToggleSound.textContent = 'Desactivar sonido'; btnToggleSound.classList.add('active'); }
    else { btnToggleSound.textContent = 'Activar sonido'; btnToggleSound.classList.remove('active'); }
  }
  if (btnToggleSound) {
    btnToggleSound.addEventListener('click', async () => {
      initAudio();
      if (!audioCtx) return alert('Audio no disponible en este navegador');
      try { await audioCtx.resume(); } catch(e){ /* ignore */ }
      soundEnabled = !soundEnabled;
      updateSoundUI();
      if (soundEnabled) playBeep(880,120);
    });
    updateSoundUI();
  }

  // Clipboard: copiar user id
  if (btnCopyUserId && userIdCode) {
    btnCopyUserId.addEventListener('click', async () => {
      const text = userIdCode.textContent.trim();
      if (!text || text === '—') return alert('ID no disponible');
      try {
        await navigator.clipboard.writeText(text);
        btnCopyUserId.textContent = 'Copiado';
        setTimeout(()=> btnCopyUserId.textContent = 'Copiar', 1200);
      } catch (e) {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); btnCopyUserId.textContent = 'Copiado'; } catch(e){ alert('No se pudo copiar'); }
        document.body.removeChild(ta);
        setTimeout(()=> btnCopyUserId.textContent = 'Copiar', 1200);
      }
    });
  }

  // -------- Helpers --------
  function esc(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function fetchJSON(url, opts = {}) {
    try {
      const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
      if (!r.ok) return { ok: false, status: r.status, json: null };
      const json = await r.json();
      return { ok: true, status: r.status, json };
    } catch (e) {
      console.error('fetch error', e, url);
      return { ok: false, status: 0, json: null };
    }
  }

  // -------- Modal logic (open / close / confirm) --------
  function openRedeemModal(obj, triggerEl = null) {
    if (!modal) return;
    _currentRedeemId = obj.id;
    _lastFocusedBeforeModal = document.activeElement;
    modalTitle.textContent = obj.descripcion || 'Recompensa';
    modalCasilla.textContent = 'Casilla: ' + (obj.casilla ?? '—');
    modalFeedback.style.display = 'none';
    modalFeedback.textContent = '';
    const meta = document.getElementById('modalRecompensaMeta');
    if (meta) {
      meta.innerHTML = obj.canjeado ? '<span class="badge badge--danger">Canjeada</span>' : (obj.eligible ? '<span class="badge badge--success">Disponible</span>' : '<span class="badge">No disponible</span>');
    }
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('modal-open');
    setTimeout(()=> modalConfirmBtn && modalConfirmBtn.focus(), 30);
  }

  function closeRedeemModal() {
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
    _currentRedeemId = null;
    document.documentElement.classList.remove('modal-open');
    if (_lastFocusedBeforeModal && typeof _lastFocusedBeforeModal.focus === 'function') {
      _lastFocusedBeforeModal.focus();
    }
  }

  // Confirm -> POST using fetch JSON
  async function confirmRedeemFromModal() {
    if (!_currentRedeemId) return;
    modalConfirmBtn.disabled = true;
    modalCancelBtn.disabled = true;
    modalFeedback.style.display = 'block';
    modalFeedback.textContent = 'Enviando solicitud...';

    // CSRF: seek meta token if exists
    const meta = document.querySelector('meta[name="csrf-token"]');
    const csrf = meta ? meta.getAttribute('content') : null;

    try {
      const res = await fetch(API.solicitar, {
        method: 'POST',
        credentials: 'same-origin',
        headers: Object.assign({'Content-Type':'application/json'}, csrf ? {'X-CSRF-Token': csrf} : {}),
        body: JSON.stringify({ id_recompensa: _currentRedeemId })
      });
      const json = await res.json();
      if (!res.ok || !json.ok) {
        modalFeedback.textContent = json.error || 'Error al solicitar el canje.';
        modalConfirmBtn.disabled = false;
        modalCancelBtn.disabled = false;
        return;
      }

      // success
      modalFeedback.textContent = 'Solicitud enviada correctamente.';
      playBeep(1100,120);
      setTimeout(async () => {
        closeRedeemModal();
        await loadAll(); // refresh data
      }, 700);
    } catch (e) {
      console.error('redeem error', e);
      modalFeedback.textContent = 'Error de red. Intenta nuevamente.';
      modalConfirmBtn.disabled = false;
      modalCancelBtn.disabled = false;
    }
  }

  // Modal event listeners (if modal exists)
  if (modal) {
    modalCloseBtn && modalCloseBtn.addEventListener('click', closeRedeemModal);
    modalCancelBtn && modalCancelBtn.addEventListener('click', closeRedeemModal);
    modalConfirmBtn && modalConfirmBtn.addEventListener('click', confirmRedeemFromModal);
    modal.addEventListener('click', function(ev){ if (ev.target === modal) closeRedeemModal(); });
    document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape' && modal.style.display === 'block') closeRedeemModal(); });
  }

  // -------- Rendering logic --------

  // Full initial render (tarjeta + badges + progress)
  function renderFull(tarjeta, recompensasData){
    if (!root) return;
    const max = Number(tarjeta.casilla_max || 0);
    const act = Number(tarjeta.casilla_actual || 0);

    const rewardMap = {};
    (recompensasData?.recompensas || []).forEach(r => rewardMap[Number(r.casilla)] = r);

    let cells = '';
    for (let i=1;i<=max;i++){
      const r = rewardMap[i];
      const filled = i <= act;
      const classes = ['cell', filled ? 'cell--filled' : ''].join(' ').trim();
      const badge = r ? `<span class="cell-badge ${r.canjeado||!r.eligible ? 'disabled' : 'available'}" data-id="${r.id}" data-casilla="${r.casilla}" title="${esc(r.descripcion)}">🎁</span>` : '';
      cells += `<div class="${classes}" data-index="${i}" role="listitem">
                  <div class="cell-number">${filled ? '✓' : i}</div>
                  ${badge}
                </div>`;
    }

    // next reward summary
    let nextRewardHTML = '<div class="muted">Sin recompensas configuradas</div>';
    if ((recompensasData?.recompensas || []).length) {
      const next = recompensasData.recompensas.find(r => !r.canjeado && !r.eligible) || recompensasData.recompensas.find(r => r.eligible && !r.canjeado);
      if (next) {
        const status = next.canjeado ? 'Canjeada' : (next.eligible ? 'Disponible' : `En casilla ${next.casilla}`);
        nextRewardHTML = `<div class="next-reward">
                            <div class="nr-left"><strong>Próxima recompensa</strong><div class="nr-desc">${esc(next.descripcion)}</div></div>
                            <div class="nr-right"><span class="nr-badge">${esc(status)}</span></div>
                          </div>`;
      }
    }

    root.innerHTML = `
      <div class="tarjeta-shell">
        <div class="tarjeta-top">
          <div class="tarjeta-qrcode">
            <div class="qr-box" aria-hidden="true">
              <img class="qr-img" src="<?= htmlspecialchars($qrUrl, ENT_QUOTES) ?>" alt="QR tarjeta">
            </div>
            <div class="tarjeta-code">Código: <strong class="code-value">${esc(tarjeta.codigo||'')}</strong></div>
          </div>

          <div class="tarjeta-progress">
            <div class="progress-ring" data-max="${max}" data-value="${act}" aria-hidden="true">
              <svg viewBox="0 0 120 120" class="ring-svg"><circle cx="60" cy="60" r="52" class="ring-bg"/><circle cx="60" cy="60" r="52" class="ring-fg"/></svg>
            </div>
            <div class="progress-meta">
              <div class="progress-num"><strong>${act}</strong> / ${max}</div>
              <div class="progress-label muted">Visitas completadas</div>
              ${nextRewardHTML}
            </div>
          </div>
        </div>

        <div class="tarjeta-grid" role="list">${cells}</div>

       <div class="tarjeta-header__left">
          <h2 class="title">Recompensas Disponibles:</h2>
        </div>
      </div>
    `;

    // delegate click for badges
    root.removeEventListener('click', onRootClick);
    root.addEventListener('click', onRootClick);

    updateRing(max, act);

    lastState = {
      casilla_actual: act,
      casilla_max: max,
      tarjetaId: tarjeta.id,
      recompensas: (recompensasData?.recompensas || []).map(r => ({ id:r.id, casilla:r.casilla, eligible:!!r.eligible, canjeado:!!r.canjeado, descripcion: r.descripcion }))
    };

    root.setAttribute('aria-busy', 'false');
  }

  // Render the rewards list panel (with fallback non-AJAX forms included by server)
  function renderRecompensasList(data) {
    if (!recompensasRoot) return;
    if (!data || !data.ok) { recompensasRoot.innerHTML = ''; return; }
    const items = (data.recompensas || []).map(r => {
      const status = r.canjeado ? '<span class="badge badge--danger">Canjeada</span>' : (r.eligible ? '<span class="badge badge--success">Disponible</span>' : `<span class="badge">Casilla ${r.casilla}</span>`);
      const action = (r.eligible && !r.canjeado) ? `<form method="POST" action="/tarjeta/solicitar-canje" class="nojs-solicitar"><input type="hidden" name="id_recompensa" value="${r.id}"><button class="btn btn--mini" type="submit">Solicitar canje</button></form>` : '';
      return `<div class="recomp-item"><div class="ri-left"><strong>${esc(r.descripcion)}</strong><div class="muted">Casilla ${r.casilla}</div></div><div class="ri-right">${status}${action}</div></div>`;
    }).join('');
    recompensasRoot.innerHTML = `<div class="card recompensas-panel">${items || '<div class="muted">No hay recompensas configuradas.</div>'}</div>`;
  }

  // Update small diffs without full re-render to avoid flicker
  function updateDifferential(tarjeta, recompensasData) {
    if (!root) return;
    const max = Number(tarjeta.casilla_max || 0), act = Number(tarjeta.casilla_actual || 0);

    // if max changed -> full re-render
    if (!lastState || lastState.casilla_max !== max) { renderFull(tarjeta, recompensasData); return; }

    // update progress number
    const progNum = root.querySelector('.progress-num strong');
    if (progNum && lastState.casilla_actual !== act) progNum.textContent = act;

    // update ring
    if (lastState.casilla_actual !== act) updateRing(max, act);

    // highlight new filled cell
    if (act > (lastState.casilla_actual || 0)) {
      const cell = root.querySelector(`.cell[data-index="${act}"]`);
      if (cell) {
        cell.classList.add('cell--filled');
        cell.animate([{ transform: 'translateY(0)' },{ transform: 'translateY(-8px)' },{ transform: 'translateY(0)' }], { duration: 420, easing:'ease-out' });
      }
      if (soundEnabled && audioCtx) { playBeep(720,100); setTimeout(()=>playBeep(920,90),120); }
    }

    // badges update
    const prevMap = {};
    (lastState.recompensas || []).forEach(r => prevMap[r.id] = r);
    const cur = (recompensasData?.recompensas || []).map(r => ({ id: r.id, casilla: r.casilla, eligible: !!r.eligible, canjeado: !!r.canjeado, descripcion: r.descripcion }));

    cur.forEach(r => {
      let badge = root.querySelector(`.cell-badge[data-id="${r.id}"]`);
      const cell = root.querySelector(`.cell[data-index="${r.casilla}"]`);
      if (!cell) return;
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'cell-badge';
        badge.dataset.id = r.id;
        badge.dataset.casilla = r.casilla;
        badge.title = r.descripcion || '';
        badge.textContent = '🎁';
        cell.insertBefore(badge, cell.firstChild);
      }
      if (r.canjeado || !r.eligible) badge.classList.add('disabled'); else badge.classList.remove('disabled');

      const prev = prevMap[r.id];
      if ((!prev || !prev.eligible) && r.eligible) {
        badge.animate([{ transform:'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 420 });
        if (soundEnabled && audioCtx) playBeep(1000,110);
      }
    });

    // remove badges that disappeared
    (lastState.recompensas || []).forEach(prev => {
      if (!cur.find(x => x.id === prev.id)) {
        const old = root.querySelector(`.cell-badge[data-id="${prev.id}"]`);
        if (old && old.parentNode) old.parentNode.removeChild(old);
      }
    });

    lastState = { casilla_actual: act, casilla_max: max, tarjetaId: tarjeta.id, recompensas: cur };
  }

  function updateRing(max, value) {
    const ring = root.querySelector('.ring-fg');
    if (!ring) return;
    const circumference = 2 * Math.PI * 52; // r=52
    const pct = max > 0 ? Math.max(0, Math.min(1, value / max)) : 0;
    const offset = Math.round(circumference * (1 - pct));
    ring.style.strokeDasharray = `${circumference}`;
    ring.style.strokeDashoffset = `${offset}`;
  }

  // Delegated click handler for badges - now opens modal
  function onRootClick(e) {
    const badge = e.target.closest('.cell-badge');
    if (!badge) return;
    e.preventDefault(); e.stopPropagation();
    const id = Number(badge.dataset.id || 0);
    const cas = Number(badge.dataset.casilla || 0);
    const rr = (lastState?.recompensas || []).find(x => x.id === id);
    if (!rr) return;
    if (!rr.eligible) return alert('Aún no disponible. Llegar a la casilla indicada para poder canjear.');
    if (rr.canjeado) return alert('Recompensa ya canjeada.');

    // Build object with more info if available
    const obj = {
      id: id,
      casilla: cas,
      descripcion: rr.descripcion || 'Recompensa',
      eligible: rr.eligible,
      canjeado: rr.canjeado
    };
    openRedeemModal(obj, badge);
  }

  // -------- Orchestrator: load tarjeta + recompensas together on first load --------
  async function loadAll() {
    if (firstLoad) root.setAttribute('aria-busy','true');

    // parallel requests to ensure recompensas available on first paint
    const [tResp, rResp] = await Promise.all([fetchJSON(API.tarjeta), fetchJSON(API.recompensas)]);

    if (!tResp.ok || !rResp.ok) {
      if (tResp.status === 401 || rResp.status === 401) { window.location.href = '/'; return; }
      if (firstLoad) root.innerHTML = '<div class="tarjeta-error">Error al cargar la tarjeta</div>';
      return;
    }

    const tar = tResp.json.tarjeta ?? (rResp.json.tarjeta ?? {});
    if (!tResp.json.tieneTarjeta && !rResp.json.tieneTarjeta) {
      if (firstLoad) root.innerHTML = `<div class="tarjeta-empty card"><p>No tienes una tarjeta asociada. Vinculate a una: </p><a class="btn btn--primary" href="/tarjeta/vincular">Vincular Tarjeta</a></div>`;
      return;
    }

    if (firstLoad) {
      renderFull(tar, rResp.json);
      renderRecompensasList(rResp.json); // ensure rewards panel shows immediately
      firstLoad = false;
      startPolling();
    } else {
      updateDifferential(tar, rResp.json);
      renderRecompensasList(rResp.json);
    }
  }

  function startPolling() {
    if (polling) clearInterval(polling);
    polling = setInterval(loadAll, 7000);
  }

  // start
  loadAll().catch(e => console.error(e));
  window.addEventListener('beforeunload', () => { if (polling) clearInterval(polling); });

  // expose some functions to window for debugging (optional)
  window._tarjeta_debug = { loadAll };

})();
</script>
