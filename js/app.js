/**
 * SmartNest — Application Core
 * Navigation, sensor simulation, UI interactions
 */

'use strict';

/* ══════════════════════════════════════════
   SENSOR DATA STORE
══════════════════════════════════════════ */

const SensorStore = (() => {
  const rooms = [
    {
      id: 'soggiorno',
      name: 'Soggiorno',
      icon: '🛋',
      temp: 22.1,
      humidity: 65,
      aqi: 38,
      smoke: false,
      fire: false,
      status: 'ok',   // ok | warn | fire
      m2: '28 m²',
    },
    {
      id: 'cucina',
      name: 'Cucina',
      icon: '🍳',
      temp: 78.0,
      humidity: 62,
      aqi: 310,
      smoke: true,
      fire: true,
      status: 'fire',
      m2: '14 m²',
    },
    {
      id: 'camera',
      name: 'Camera da letto',
      icon: '🛏',
      temp: 20.5,
      humidity: 55,
      aqi: 30,
      smoke: false,
      fire: false,
      status: 'ok',
      m2: '18 m²',
    },
    {
      id: 'bagno',
      name: 'Bagno',
      icon: '🚿',
      temp: 24.0,
      humidity: 80,
      aqi: 55,
      smoke: false,
      fire: false,
      status: 'warn',
      m2: '8 m²',
    },
  ];

  const history = [
    { time: '14:32', room: '🍳 Cucina',    temp: 78.4, hum: '—',  aqi: 'CRITICO', event: 'fire',  label: '🔥 Incendio rilevato' },
    { time: '14:28', room: '🚿 Bagno',     temp: 24.0, hum: 80,   aqi: 55,        event: 'warn',  label: '⚠ Umidità alta' },
    { time: '13:45', room: '🛋 Soggiorno', temp: 22.1, hum: 65,   aqi: 38,        event: 'ok',    label: '✓ Condizioni ottimali' },
    { time: '12:15', room: '🛏 Camera',    temp: 20.5, hum: 55,   aqi: 30,        event: 'ok',    label: '✓ Condizioni ottimali' },
    { time: '11:00', room: '🍳 Cucina',    temp: 28.3, hum: 62,   aqi: 70,        event: 'warn',  label: '⚠ Aria non pulita' },
    { time: '09:30', room: '🛋 Soggiorno', temp: 21.8, hum: 60,   aqi: 35,        event: 'ok',    label: '✓ Condizioni ottimali' },
    { time: '08:00', room: '🛏 Camera',    temp: 19.2, hum: 52,   aqi: 28,        event: 'ok',    label: '✓ Condizioni ottimali' },
  ];

  const notifications = [
    { id: 'n1', type: 'fire',  icon: '🔥', title: 'Incendio rilevato — Cucina',       desc: 'Temp: 78°C · Fumo MQ135: CRITICO · Pompa ATTIVA', channels: ['telegram','mail'],  time: '14:32', unread: true },
    { id: 'n2', type: 'warn',  icon: '💧', title: 'Umidità alta — Bagno',             desc: '80% rilevato · Soglia: 70% · Apri finestra',       channels: ['telegram'],         time: '14:28', unread: true },
    { id: 'n3', type: 'warn',  icon: '🌬', title: 'Qualità aria — Cucina',            desc: 'AQI: 310 · MQ135 elevato · Ventilare',             channels: [],                   time: '11:00', unread: false },
    { id: 'n4', type: 'ok',    icon: '✅', title: 'Aria normalizzata — Soggiorno',    desc: 'AQI: 38 · Condizioni ottimali ripristinate',        channels: [],                   time: '10:15', unread: false },
  ];

  return { rooms, history, notifications };
})();


/* ══════════════════════════════════════════
   NAVIGATION
══════════════════════════════════════════ */

const Router = (() => {
  let currentPage = 'overview';

  function navigate(pageId) {
    // hide all
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav__item').forEach(n => n.classList.remove('active'));

    const target = document.getElementById(`page-${pageId}`);
    if (target) {
      target.classList.add('active');
      currentPage = pageId;
    }
    // highlight nav
    document.querySelectorAll(`[data-page="${pageId}"]`).forEach(el => {
      el.classList.add('active');
    });
    // update topbar
    const titles = {
      overview:      ['Panoramica',    'Home · Tutti i sensori'],
      floorplan:     ['Piantina',      'Visualizzazione planimetrica'],
      rooms:         ['Stanze',        'Gestione e monitoraggio stanze'],
      history:       ['Storico Dati',  'Dati storici sensori'],
      notifications: ['Notifiche',     'Alert e messaggi'],
      users:         ['Utenti',        'Gestione accessi e ruoli'],
      settings:      ['Impostazioni',  'Configurazione sistema'],
    };
    const [title, sub] = titles[pageId] || ['Dashboard', ''];
    const ttEl = document.getElementById('topbar-title');
    const stEl = document.getElementById('topbar-sub');
    if (ttEl) ttEl.textContent = title;
    if (stEl) stEl.textContent = sub;
  }

  return { navigate, getCurrent: () => currentPage };
})();


/* ══════════════════════════════════════════
   UI HELPERS
══════════════════════════════════════════ */

const UI = {
  /** Format AQI value with color class */
  aqiColor(val) {
    if (val > 150) return 'var(--danger)';
    if (val > 100) return 'var(--warn)';
    if (val > 50)  return 'var(--warn)';
    return 'var(--brand)';
  },
  aqiLabel(val) {
    if (val > 200) return 'Critica';
    if (val > 150) return 'Malsana';
    if (val > 100) return 'Non sana';
    if (val > 50)  return 'Moderata';
    return 'Buona';
  },

  /** Humidity feedback */
  humFeedback(hum) {
    if (hum > 75) return { level: 'warn', msg: '💡 Aprire le finestre o attivare deumidificatore' };
    if (hum < 30) return { level: 'warn', msg: '💡 Umidità troppo bassa, usa umidificatore' };
    return { level: 'ok', msg: '✓ Umidità nella norma' };
  },

  /** Temp feedback */
  tempFeedback(temp) {
    if (temp > 50)  return { level: 'fire', msg: '🔥 Temperatura critica — possibile incendio' };
    if (temp > 28)  return { level: 'warn', msg: '⚠ Temperatura elevata' };
    if (temp < 16)  return { level: 'warn', msg: '⚠ Temperatura troppo bassa' };
    return { level: 'ok', msg: '✓ Temperatura nella norma' };
  },

  badgeHtml(status) {
    const map = {
      ok:   '<span class="badge badge--ok"><span class="badge__dot"></span>OK</span>',
      warn: '<span class="badge badge--warn"><span class="badge__dot"></span>Attenzione</span>',
      fire: '<span class="badge badge--danger"><span class="badge__dot"></span>INCENDIO</span>',
    };
    return map[status] || map.ok;
  },

  /** Animate a number counting up */
  countUp(el, target, unit = '', duration = 800) {
    const start = performance.now();
    const initial = 0;
    function tick(now) {
      const t = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - t, 3);
      el.textContent = (initial + (target - initial) * ease).toFixed(typeof target === 'float' ? 1 : 0) + unit;
      if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  },
};


/* ══════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════ */

function initSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mainArea = document.getElementById('main-area');
  const toggleBtn = document.getElementById('sidebar-toggle');

  if (!sidebar) return;

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainArea?.classList.toggle('expanded');
  });

  // Nav item clicks
  document.querySelectorAll('.nav__item[data-page]').forEach(item => {
    item.addEventListener('click', () => {
      Router.navigate(item.dataset.page);
    });
  });
}


/* ══════════════════════════════════════════
   OVERVIEW PAGE
══════════════════════════════════════════ */

function renderOverview() {
  renderStatCards();
  renderRoomCards('rooms-grid-overview');
  renderAQIBars('aqi-bars-overview');
  renderNotifList('notif-list-overview', 3);
}

function renderStatCards() {
  const container = document.getElementById('stat-cards');
  if (!container) return;

  const rooms = SensorStore.rooms;
  const avgTemp = (rooms.reduce((s,r) => s + r.temp, 0) / rooms.length).toFixed(1);
  const avgHum  = Math.round(rooms.reduce((s,r) => s + r.humidity, 0) / rooms.length);
  const avgAqi  = Math.round(rooms.reduce((s,r) => s + r.aqi, 0) / rooms.length);
  const fireRooms = rooms.filter(r => r.fire).length;

  container.innerHTML = `
    <div class="card stat-card card--danger">
      <span class="stat-card__label">🔥 Alert Incendio</span>
      <div class="stat-card__value stat-card__value--danger">${fireRooms} <span class="stat-card__unit">attivo</span></div>
      <div class="stat-card__trend stat-card__trend--up">💦 Pompa acqua ATTIVA — Cucina</div>
    </div>
    <div class="card stat-card ${avgTemp > 26 ? 'card--warn' : ''}">
      <span class="stat-card__label">🌡 Temperatura Media</span>
      <div class="stat-card__value">${avgTemp}<span class="stat-card__unit"> °C</span></div>
      <div class="stat-card__trend stat-card__trend--up">⬆ +1.2° rispetto a ieri</div>
    </div>
    <div class="card stat-card ${avgHum > 70 ? 'card--warn' : ''}">
      <span class="stat-card__label">💧 Umidità Media</span>
      <div class="stat-card__value ${avgHum > 70 ? 'stat-card__value--warn' : ''}">${avgHum}<span class="stat-card__unit"> %</span></div>
      <div class="stat-card__trend ${avgHum > 70 ? 'stat-card__trend--up' : ''}">
        ${avgHum > 70 ? '⚠ Sopra soglia — aprire finestre' : '✓ Nella norma'}
      </div>
    </div>
    <div class="card stat-card">
      <span class="stat-card__label">🌬 Qualità Aria (AQI)</span>
      <div class="stat-card__value" style="color:${UI.aqiColor(avgAqi)}">${UI.aqiLabel(avgAqi)}<span class="stat-card__unit"> ${avgAqi}</span></div>
      <div class="stat-card__trend">MQ135 — media stanze</div>
    </div>`;
}


/* ══════════════════════════════════════════
   ROOMS PAGE
══════════════════════════════════════════ */

function renderRoomCards(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = SensorStore.rooms.map(room => {
    const fireClass = room.fire ? 'room-card--fire' : '';
    const warnBorder = room.status === 'warn' ? 'style="border-color:rgba(240,160,48,0.35)"' : '';

    const aqiColor = UI.aqiColor(room.aqi);
    const smokePill = room.smoke
      ? `<div class="sensor-pill sensor-pill--danger">
           <div class="sensor-pill__icon">💨</div>
           <div class="sensor-pill__value" style="color:var(--danger)">HIGH</div>
           <div class="sensor-pill__label">Fumo</div>
         </div>`
      : `<div class="sensor-pill">
           <div class="sensor-pill__icon">🌬</div>
           <div class="sensor-pill__value" style="color:${aqiColor}">${room.aqi > 200 ? '☠' : room.aqi}</div>
           <div class="sensor-pill__label">AQI</div>
         </div>`;

    const pumpPill = room.fire
      ? `<div class="sensor-pill" style="border-color:rgba(48,144,240,0.35)">
           <div class="sensor-pill__icon pump-icon">💦</div>
           <div class="sensor-pill__value" style="color:var(--info);font-size:11px">PUMP</div>
           <div class="sensor-pill__label">Attiva</div>
         </div>`
      : '';

    return `
      <div class="card room-card ${fireClass}" ${warnBorder}>
        <div class="room-card__header">
          <div class="room-card__name">${room.icon} ${room.name}</div>
          ${UI.badgeHtml(room.status)}
        </div>
        <div class="sensor-grid" style="grid-template-columns:repeat(${room.fire ? 3 : 3},1fr)">
          <div class="sensor-pill ${room.temp > 50 ? 'sensor-pill--danger' : ''}">
            <div class="sensor-pill__icon">🌡</div>
            <div class="sensor-pill__value" style="color:${room.temp > 50 ? 'var(--danger)' : 'inherit'}">${room.temp}</div>
            <div class="sensor-pill__label">Temp °C</div>
          </div>
          <div class="sensor-pill ${room.humidity > 75 ? 'sensor-pill--warn' : ''}">
            <div class="sensor-pill__icon">💧</div>
            <div class="sensor-pill__value" style="color:${room.humidity > 75 ? 'var(--warn)' : 'inherit'}">${room.humidity}%</div>
            <div class="sensor-pill__label">Umidità</div>
          </div>
          ${smokePill}
          ${pumpPill}
        </div>
        <div style="margin-top:12px;font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">${room.m2} · Aggiornato 30s fa</div>
      </div>`;
  }).join('');
}


/* ══════════════════════════════════════════
   HISTORY PAGE
══════════════════════════════════════════ */

function renderHistoryTable(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const colorMap = { fire: 'var(--danger)', warn: 'var(--warn)', ok: 'var(--brand)' };

  container.innerHTML = `
    <table class="data-table">
      <thead>
        <tr>
          <th>Orario</th>
          <th>Stanza</th>
          <th>Temp (°C)</th>
          <th>Umidità (%)</th>
          <th>AQI</th>
          <th>Evento</th>
        </tr>
      </thead>
      <tbody>
        ${SensorStore.history.map(row => `
          <tr>
            <td style="color:${colorMap[row.event]}">${row.time}</td>
            <td>${row.room}</td>
            <td style="color:${row.event === 'fire' ? 'var(--danger)' : 'inherit'}">${row.temp}</td>
            <td>${row.hum}</td>
            <td style="color:${row.event === 'fire' ? 'var(--danger)' : 'inherit'}">${row.aqi}</td>
            <td style="color:${colorMap[row.event]}">${row.label}</td>
          </tr>`).join('')}
      </tbody>
    </table>
    <div class="bar-chart" style="margin-top:20px">
      <div class="bar-chart__bar bar-chart__bar--danger" style="height:100%" title="Cucina 78°C"></div>
      <div class="bar-chart__bar" style="height:55%"  title="Soggiorno 22.1°C"></div>
      <div class="bar-chart__bar" style="height:50%"  title="Camera 20.5°C"></div>
      <div class="bar-chart__bar bar-chart__bar--warn" style="height:58%"  title="Bagno 24°C"></div>
      <div class="bar-chart__bar" style="height:45%;opacity:0.3"></div>
      <div class="bar-chart__bar" style="height:52%;opacity:0.3"></div>
      <div class="bar-chart__bar" style="height:48%;opacity:0.3"></div>
      <div class="bar-chart__bar bar-chart__bar--warn" style="height:61%;opacity:0.5"></div>
      <div class="bar-chart__bar" style="height:50%;opacity:0.3"></div>
      <div class="bar-chart__bar" style="height:53%;opacity:0.4"></div>
      <div class="bar-chart__bar" style="height:55%;opacity:0.4"></div>
      <div class="bar-chart__bar bar-chart__bar--danger" style="height:90%;opacity:0.9"></div>
    </div>
    <div class="bar-chart__axis">
      <span>03:00</span><span>06:00</span><span>09:00</span><span>12:00</span><span>15:00</span>
    </div>`;
}


/* ══════════════════════════════════════════
   AQI BARS
══════════════════════════════════════════ */

function renderAQIBars(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="aqi-row">
      ${SensorStore.rooms.map(r => `
        <div class="aqi-item">
          <span class="aqi-item__label">${r.icon} ${r.name}</span>
          <div class="aqi-bar"><div class="aqi-fill" style="width:${Math.min(r.aqi/3,100)}%;background:${UI.aqiColor(r.aqi)}"></div></div>
          <span class="aqi-item__val" style="color:${UI.aqiColor(r.aqi)}">${r.aqi > 200 ? 'CRITICO' : r.aqi + ' ' + UI.aqiLabel(r.aqi)}</span>
        </div>`).join('')}
    </div>
    <div style="margin-top:14px;padding:10px 14px;background:var(--bg-elevated);border-radius:8px;font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">
      Scala AQI: 0–50 Buona · 51–100 Moderata · 101–150 Non sana · 151+ Pericolosa
    </div>`;
}


/* ══════════════════════════════════════════
   NOTIFICATIONS
══════════════════════════════════════════ */

function renderNotifList(containerId, limit) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const items = limit
    ? SensorStore.notifications.slice(0, limit)
    : SensorStore.notifications;

  const channelChip = ch => ch === 'telegram'
    ? '<span class="channel-chip channel-chip--telegram">✈ Telegram</span>'
    : '<span class="channel-chip channel-chip--mail">✉ Email</span>';

  container.innerHTML = items.map(n => `
    <div class="notif-item notif-item--${n.type}">
      <div class="notif-item__icon">${n.icon}</div>
      <div class="notif-item__body">
        <div class="notif-item__title">${n.title}</div>
        <div class="notif-item__desc">${n.desc}</div>
        ${n.channels.length ? `<div class="notif-item__meta">${n.channels.map(channelChip).join('')}</div>` : ''}
        ${n.type === 'fire' ? `
          <div class="notif-item__actions">
            <button class="btn btn--sm btn--danger">🔕 Silenzia</button>
            <button class="btn btn--sm btn--ghost">Dettagli</button>
          </div>` : ''}
        ${n.type === 'warn' ? `
          <div style="font-size:11px;color:var(--warn);margin-top:6px">
            💡 Azione consigliata: ${n.desc.includes('Umidità') ? 'Aprire finestra o attivare deumidificatore' : 'Attivare ventilazione forzata'}
          </div>` : ''}
      </div>
      <div class="notif-item__time">${n.time}</div>
    </div>`).join('');
}


/* ══════════════════════════════════════════
   USERS PAGE
══════════════════════════════════════════ */

function renderUsers(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const users = Auth.getUsers();
  container.innerHTML = users.map(u => `
    <div class="user-row">
      <div class="avatar" style="background:${u.color}">${u.initials}</div>
      <div class="user-info">
        <div class="user-info__name">${u.name}</div>
        <div class="user-info__email">${u.email}${u.telegram ? ' · ' + u.telegram : ''}</div>
      </div>
      <span class="badge ${u.role === 'admin' ? 'badge--info' : 'badge--muted'}">${u.role === 'admin' ? 'Admin' : 'Viewer'}</span>
      <div class="user-contacts">
        <div class="contact-btn" title="Invia email">✉</div>
        ${u.telegram ? '<div class="contact-btn" title="Telegram" style="color:#29b6f6">✈</div>' : ''}
      </div>
    </div>`).join('');
}


/* ══════════════════════════════════════════
   MODAL
══════════════════════════════════════════ */

function initModal() {
  const overlay = document.getElementById('fire-modal');
  if (!overlay) return;

  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });

  // auto-open on load if fire detected
  const hasFire = SensorStore.rooms.some(r => r.fire);
  if (hasFire) {
    setTimeout(() => overlay.classList.add('open'), 600);
  }
}

function closeModal() {
  document.getElementById('fire-modal')?.classList.remove('open');
}

function openModal() {
  document.getElementById('fire-modal')?.classList.add('open');
}


/* ══════════════════════════════════════════
   TOPBAR SESSION
══════════════════════════════════════════ */

function initTopbar() {
  const session = Auth.getSession();
  if (!session) return;

  const avatarEl = document.getElementById('topbar-avatar');
  const nameEl   = document.getElementById('topbar-user-name');

  if (avatarEl) {
    avatarEl.style.background = session.color;
    avatarEl.textContent = session.initials;
  }
  if (nameEl) nameEl.textContent = session.name;

  document.getElementById('logout-btn')?.addEventListener('click', () => {
    Auth.logout();
    window.location.href = 'login.html';
  });
}


/* ══════════════════════════════════════════
   FLOORPLAN
══════════════════════════════════════════ */

function renderFloorplan() {
  // SVG is static in HTML — just ensure room labels/states are rendered
  // For a real app this would be data-driven
}


/* ══════════════════════════════════════════
   INIT
══════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
  // Guard: redirect to login if not authenticated
  if (typeof Auth !== 'undefined' && !Auth.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  initSidebar();
  initTopbar();
  initModal();

  // Render all pages
  renderOverview();
  renderRoomCards('rooms-grid-rooms');
  renderHistoryTable('history-container');
  renderAQIBars('aqi-bars-rooms');
  renderNotifList('notif-list-page');
  renderUsers('users-list');

  // Default page
  Router.navigate('overview');

  // Topbar alert click
  document.getElementById('topbar-alert')?.addEventListener('click', openModal);

  // Settings admin guard
  const settingsNav = document.querySelector('[data-page="settings"]');
  if (settingsNav && Auth.isAuthenticated() && !Auth.isAdmin()) {
    settingsNav.style.opacity = '0.4';
    settingsNav.style.pointerEvents = 'none';
    settingsNav.title = 'Solo gli admin possono accedere alle impostazioni';
  }
});

window.Router   = Router;
window.closeModal = closeModal;
window.openModal  = openModal;
