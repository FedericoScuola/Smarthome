<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartNest — Dashboard</title>
  <link rel="stylesheet" href="styles/main.css">
</head>
<body>

<div class="app-layout">

  <!-- ══════════════════════════════════════
       SIDEBAR
  ══════════════════════════════════════ -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar__header">
      <div class="sidebar__logo">
        <svg viewBox="0 0 24 24" fill="#080b10" width="17" height="17">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
      </div>
      <span class="sidebar__brand">Smart<span>Nest</span></span>
      <button class="sidebar__toggle" id="sidebar-toggle" title="Comprimi sidebar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
    </div>

    <nav class="sidebar__nav">

      <!-- Principale -->
      <div class="nav__section-label">Principale</div>

      <div class="nav__item" data-page="overview" title="Panoramica">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
        </span>
        <span class="nav__label">Panoramica</span>
      </div>

      <div class="nav__item" data-page="floorplan" title="Piantina">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </span>
        <span class="nav__label">Piantina</span>
      </div>

      <!-- Monitoraggio -->
      <div class="nav__section-label">Monitoraggio</div>

      <div class="nav__item" data-page="rooms" title="Stanze">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
          </svg>
        </span>
        <span class="nav__label">Stanze</span>
      </div>

      <div class="nav__item" data-page="history" title="Storico">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
          </svg>
        </span>
        <span class="nav__label">Storico Dati</span>
      </div>

      <!-- Alert -->
      <div class="nav__section-label">Alert</div>

      <div class="nav__item" data-page="notifications" title="Notifiche">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
          </svg>
        </span>
        <span class="nav__label">Notifiche</span>
        <span class="nav__badge">2</span>
      </div>

      <!-- Sistema -->
      <div class="nav__section-label">Sistema</div>

      <div class="nav__item" data-page="users" title="Utenti">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
          </svg>
        </span>
        <span class="nav__label">Utenti</span>
      </div>

      <div class="nav__item" data-page="settings" title="Impostazioni">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
          </svg>
        </span>
        <span class="nav__label">Impostazioni</span>
      </div>

    </nav>

    <!-- Footer user -->
    <div class="sidebar__footer">
      <div class="nav__item nav__item--danger" id="logout-btn" title="Esci">
        <span class="nav__icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </span>
        <span class="nav__label">Esci</span>
      </div>
      <div class="sidebar__user">
        <div class="avatar" id="topbar-avatar">MA</div>
        <div class="sidebar__user-info">
          <div class="sidebar__user-name" id="topbar-user-name">Utente</div>
          <div class="sidebar__user-role">Connesso</div>
        </div>
      </div>
    </div>

  </aside>

  <!-- ══════════════════════════════════════
       MAIN
  ══════════════════════════════════════ -->
  <main class="app-main" id="main-area">

    <!-- TOPBAR -->
    <header class="topbar">
      <div>
        <div class="topbar__title" id="topbar-title">Panoramica</div>
        <div class="topbar__subtitle" id="topbar-sub">Home · Tutti i sensori</div>
      </div>
      <div class="topbar__spacer"></div>
      <div class="topbar__actions">
        <!-- Channel indicators -->
        <span class="channel-chip channel-chip--telegram">✈ Telegram</span>
        <span class="channel-chip channel-chip--mail">✉ Email</span>
        <!-- Fire alert -->
        <div
          class="alert-banner alert-banner--danger"
          id="topbar-alert"
          style="padding:6px 14px;cursor:pointer;font-size:12px"
        >
          <span>🔥</span>
          <span>INCENDIO — Cucina</span>
        </div>
        <!-- Notification bell -->
        <div class="notif-bell" onclick="Router.navigate('notifications')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
          </svg>
          <div class="notif-bell__dot"></div>
        </div>
      </div>
    </header>

    <!-- ══════════════════════════════════════
         PAGE: OVERVIEW
    ══════════════════════════════════════ -->
    <section class="page" id="page-overview">

      <!-- Fire alert banner -->
      <div class="alert-banner alert-banner--danger">
        <span>🔥</span>
        <strong>INCENDIO RILEVATO — Cucina</strong>
        <span style="font-weight:400;font-family:var(--font-mono);font-size:11px">· Temp: 78°C · Fumo MQ135: CRITICO · 14:32</span>
        <div class="alert-banner__actions">
          <button class="btn btn--sm btn--danger" onclick="openModal()">Gestisci</button>
          <button class="btn btn--sm btn--ghost">Silenzia</button>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="grid-4" id="stat-cards"><!-- rendered by JS --></div>

      <!-- Main grid -->
      <div class="grid-main">

        <!-- LEFT -->
        <div style="display:flex;flex-direction:column;gap:var(--sp-4)">

          <!-- Room cards preview -->
          <div class="section-header">
            <span class="section-title">Stanze</span>
            <button class="btn btn--sm btn--ghost" onclick="Router.navigate('rooms')">Vedi tutte →</button>
          </div>
          <div class="grid-2" id="rooms-grid-overview"><!-- rendered by JS --></div>

          <!-- Storico mini -->
          <div class="card">
            <div class="card__header">
              <div>
                <div class="card__title">Storico recente</div>
                <div class="card__subtitle">Ultimi eventi sensori</div>
              </div>
              <button class="btn btn--sm btn--ghost" onclick="Router.navigate('history')">Storico completo →</button>
            </div>
            <!-- last 3 rows inline -->
            <table class="data-table">
              <thead><tr>
                <th>Orario</th><th>Stanza</th><th>Temp</th><th>Evento</th>
              </tr></thead>
              <tbody>
                <tr><td style="color:var(--danger)">14:32</td><td>🍳 Cucina</td><td style="color:var(--danger)">78.4°C</td><td style="color:var(--danger)">🔥 Incendio rilevato</td></tr>
                <tr><td style="color:var(--warn)">14:28</td><td>🚿 Bagno</td><td>24.0°C</td><td style="color:var(--warn)">⚠ Umidità alta (80%)</td></tr>
                <tr><td style="color:var(--brand)">13:45</td><td>🛋 Soggiorno</td><td>22.1°C</td><td style="color:var(--brand)">✓ Condizioni ottimali</td></tr>
              </tbody>
            </table>
          </div>

        </div>

        <!-- RIGHT -->
        <div style="display:flex;flex-direction:column;gap:var(--sp-4)">

          <!-- Notifications -->
          <div class="card">
            <div class="card__header">
              <div>
                <div class="card__title">Notifiche</div>
                <div class="card__subtitle">Ultime 3</div>
              </div>
              <button class="btn btn--xs btn--ghost" onclick="Router.navigate('notifications')">Tutte</button>
            </div>
            <div class="notif-list" id="notif-list-overview"><!-- rendered by JS --></div>
          </div>

          <!-- AQI -->
          <div class="card">
            <div class="card__header">
              <div>
                <div class="card__title">Qualità Aria</div>
                <div class="card__subtitle">MQ135 per stanza</div>
              </div>
            </div>
            <div id="aqi-bars-overview"><!-- rendered by JS --></div>
          </div>

          <!-- Pump panel -->
          <div class="pump-panel">
            <div class="card__header" style="margin-bottom:var(--sp-3)">
              <div class="card__title" style="color:var(--danger)">🚒 Sistema Estinzione</div>
              <span class="badge badge--danger"><span class="badge__dot"></span>ATTIVO</span>
            </div>
            <div class="pump-status">
              <span class="pump-icon">💦</span>
              <div>
                <div style="font-size:13px;font-weight:700;color:var(--danger)">Pompa ATTIVA — Cucina</div>
                <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">Attuatore P01 · Portata 2.4 L/min</div>
              </div>
            </div>
            <div style="display:flex;gap:var(--sp-2)">
              <button class="btn btn--sm btn--danger">🔴 Ferma pompa</button>
              <button class="btn btn--sm btn--ghost">📋 Log completo</button>
            </div>
          </div>

        </div>
      </div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: FLOORPLAN
    ══════════════════════════════════════ -->
    <section class="page" id="page-floorplan">
      <div class="section-header">
        <span class="section-title">Piantina Virtuale</span>
        <div style="display:flex;gap:var(--sp-2)">
          <span class="badge badge--danger"><span class="badge__dot"></span>1 incendio</span>
          <span class="badge badge--warn">1 avviso</span>
          <span class="badge badge--ok">2 OK</span>
        </div>
      </div>
      <div class="card" style="padding:var(--sp-4)">
        <div class="floorplan" style="height:420px">
          <div class="floorplan__grid"></div>
          <svg width="100%" height="100%" viewBox="0 0 700 380" xmlns="http://www.w3.org/2000/svg">
            <!-- ── Soggiorno ── -->
            <rect x="20" y="20" width="220" height="160" rx="6" fill="rgba(0,212,180,0.05)" stroke="rgba(0,212,180,0.45)" stroke-width="2"/>
            <text x="130" y="90" text-anchor="middle" fill="#00d4b4" font-size="13" font-family="Outfit" font-weight="700">Soggiorno</text>
            <text x="130" y="108" text-anchor="middle" fill="#4a5878" font-size="10" font-family="DM Mono">22.1°C · 65% · AQI 38</text>
            <text x="130" y="124" text-anchor="middle" fill="#30c878" font-size="9" font-family="DM Mono">✓ Condizioni ottimali</text>
            <circle cx="220" cy="36" r="8" fill="rgba(0,212,180,0.2)" stroke="#00d4b4" stroke-width="1.5"/>
            <text x="220" y="40" text-anchor="middle" fill="#00d4b4" font-size="9">✓</text>
            <!-- Area tag -->
            <rect x="90" y="150" width="80" height="18" rx="4" fill="rgba(0,212,180,0.1)" stroke="rgba(0,212,180,0.2)" stroke-width="1"/>
            <text x="130" y="163" text-anchor="middle" fill="#4a5878" font-size="9" font-family="DM Mono">28 m²</text>

            <!-- ── Cucina (FIRE) ── -->
            <rect x="460" y="20" width="220" height="160" rx="6" fill="rgba(240,64,96,0.08)" stroke="rgba(240,64,96,0.7)" stroke-width="2.5">
              <animate attributeName="stroke-opacity" values="0.7;0.25;0.7" dur="1.5s" repeatCount="indefinite"/>
            </rect>
            <text x="570" y="80" text-anchor="middle" fill="#f04060" font-size="13" font-family="Outfit" font-weight="700">Cucina 🔥</text>
            <text x="570" y="98" text-anchor="middle" fill="#f04060" font-size="10" font-family="DM Mono">78°C · FUMO!</text>
            <text x="570" y="114" text-anchor="middle" fill="#4a5878" font-size="9" font-family="DM Mono">Pompa acqua ATTIVA</text>
            <!-- Fire pulse -->
            <circle cx="660" cy="36" r="9" fill="rgba(240,64,96,0.35)" stroke="#f04060" stroke-width="2">
              <animate attributeName="r" values="9;13;9" dur="1.2s" repeatCount="indefinite"/>
              <animate attributeName="opacity" values="1;0.5;1" dur="1.2s" repeatCount="indefinite"/>
            </circle>
            <text x="660" y="41" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold">!</text>
            <!-- Water spray animation -->
            <g opacity="0.7">
              <line x1="570" y1="130" x2="545" y2="158" stroke="#3090f0" stroke-width="1.5" stroke-dasharray="4,3">
                <animate attributeName="stroke-opacity" values="0.7;1;0.7" dur="0.7s" repeatCount="indefinite"/>
              </line>
              <line x1="570" y1="130" x2="570" y2="162" stroke="#3090f0" stroke-width="1.5" stroke-dasharray="4,3">
                <animate attributeName="stroke-opacity" values="0.7;1;0.7" dur="0.7s" repeatCount="indefinite" begin="0.15s"/>
              </line>
              <line x1="570" y1="130" x2="595" y2="158" stroke="#3090f0" stroke-width="1.5" stroke-dasharray="4,3">
                <animate attributeName="stroke-opacity" values="0.7;1;0.7" dur="0.7s" repeatCount="indefinite" begin="0.3s"/>
              </line>
            </g>
            <rect x="520" y="148" width="100" height="18" rx="4" fill="rgba(240,64,96,0.1)" stroke="rgba(240,64,96,0.2)" stroke-width="1"/>
            <text x="570" y="161" text-anchor="middle" fill="#4a5878" font-size="9" font-family="DM Mono">14 m²</text>

            <!-- ── Corridoio ── -->
            <rect x="260" y="160" width="180" height="60" rx="4" fill="rgba(26,33,48,0.6)" stroke="#1c2438" stroke-width="1.5"/>
            <text x="350" y="195" text-anchor="middle" fill="#4a5878" font-size="11" font-family="DM Mono">Corridoio</text>

            <!-- ── Camera da letto ── -->
            <rect x="20" y="210" width="220" height="150" rx="6" fill="rgba(48,144,240,0.04)" stroke="rgba(48,144,240,0.35)" stroke-width="2"/>
            <text x="130" y="278" text-anchor="middle" fill="#3090f0" font-size="13" font-family="Outfit" font-weight="700">Camera da letto</text>
            <text x="130" y="296" text-anchor="middle" fill="#4a5878" font-size="10" font-family="DM Mono">20.5°C · 55% · AQI 30</text>
            <text x="130" y="312" text-anchor="middle" fill="#30c878" font-size="9" font-family="DM Mono">✓ Condizioni ottimali</text>
            <circle cx="220" cy="226" r="8" fill="rgba(48,144,240,0.2)" stroke="#3090f0" stroke-width="1.5"/>
            <text x="220" y="230" text-anchor="middle" fill="#3090f0" font-size="9">✓</text>
            <rect x="90" y="340" width="80" height="18" rx="4" fill="rgba(48,144,240,0.08)" stroke="rgba(48,144,240,0.15)" stroke-width="1"/>
            <text x="130" y="353" text-anchor="middle" fill="#4a5878" font-size="9" font-family="DM Mono">18 m²</text>

            <!-- ── Bagno ── -->
            <rect x="460" y="210" width="220" height="150" rx="6" fill="rgba(240,160,48,0.04)" stroke="rgba(240,160,48,0.35)" stroke-width="2"/>
            <text x="570" y="278" text-anchor="middle" fill="#f0a030" font-size="13" font-family="Outfit" font-weight="700">Bagno</text>
            <text x="570" y="296" text-anchor="middle" fill="#4a5878" font-size="10" font-family="DM Mono">24.0°C · 80% · AQI 55</text>
            <text x="570" y="312" text-anchor="middle" fill="#f0a030" font-size="9" font-family="DM Mono">⚠ Umidità alta</text>
            <circle cx="660" cy="226" r="8" fill="rgba(240,160,48,0.25)" stroke="#f0a030" stroke-width="1.5"/>
            <text x="660" y="230" text-anchor="middle" fill="#f0a030" font-size="10">~</text>
            <rect x="530" y="340" width="80" height="18" rx="4" fill="rgba(240,160,48,0.08)" stroke="rgba(240,160,48,0.15)" stroke-width="1"/>
            <text x="570" y="353" text-anchor="middle" fill="#4a5878" font-size="9" font-family="DM Mono">8 m²</text>

            <!-- Doors -->
            <path d="M240 90 Q260 90 260 110" fill="none" stroke="#2e3f5c" stroke-width="1.2" stroke-dasharray="3,2"/>
            <path d="M240 260 Q260 260 260 240" fill="none" stroke="#2e3f5c" stroke-width="1.2" stroke-dasharray="3,2"/>
            <path d="M460 90 Q440 90 440 110" fill="none" stroke="#2e3f5c" stroke-width="1.2" stroke-dasharray="3,2"/>
            <path d="M460 260 Q440 260 440 240" fill="none" stroke="#2e3f5c" stroke-width="1.2" stroke-dasharray="3,2"/>

            <!-- Legend -->
            <g transform="translate(20, 375)">
              <circle cx="6" cy="6" r="5" fill="rgba(240,64,96,0.5)" stroke="#f04060"/>
              <text x="16" y="10" fill="#4a5878" font-size="9" font-family="DM Mono">Incendio</text>
              <circle cx="85" cy="6" r="5" fill="rgba(240,160,48,0.4)" stroke="#f0a030"/>
              <text x="95" y="10" fill="#4a5878" font-size="9" font-family="DM Mono">Attenzione</text>
              <circle cx="174" cy="6" r="5" fill="rgba(0,212,180,0.3)" stroke="#00d4b4"/>
              <text x="184" y="10" fill="#4a5878" font-size="9" font-family="DM Mono">OK</text>
              <text x="240" y="10" fill="#3090f0" font-size="9" font-family="DM Mono">~~ Pompa attiva</text>
            </g>
          </svg>
        </div>
      </div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: ROOMS
    ══════════════════════════════════════ -->
    <section class="page" id="page-rooms">
      <div class="section-header">
        <span class="section-title">Stanze</span>
        <button class="btn btn--sm btn--primary">+ Aggiungi stanza</button>
      </div>

      <div class="alert-banner alert-banner--warn" style="font-size:12px">
        <span>💧</span>
        <span>Umidità alta nel Bagno (80%) — aprire la finestra o attivare il deumidificatore</span>
      </div>

      <div class="grid-2" id="rooms-grid-rooms"><!-- rendered by JS --></div>

      <div class="card">
        <div class="card__header">
          <div>
            <div class="card__title">Qualità Aria per Stanza</div>
            <div class="card__subtitle">Sensore MQ135 — aggiornato in tempo reale</div>
          </div>
        </div>
        <div id="aqi-bars-rooms"><!-- rendered by JS --></div>
      </div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: HISTORY
    ══════════════════════════════════════ -->
    <section class="page" id="page-history">
      <div class="section-header">
        <span class="section-title">Storico Dati Sensori</span>
        <div style="display:flex;gap:var(--sp-2)">
          <button class="btn btn--sm btn--ghost">⬇ Esporta CSV</button>
          <div class="tabs">
            <div class="tab active">Oggi</div>
            <div class="tab">7 giorni</div>
            <div class="tab">30 giorni</div>
          </div>
        </div>
      </div>

      <!-- Filter chips -->
      <div style="display:flex;gap:var(--sp-2);flex-wrap:wrap">
        <button class="btn btn--xs btn--ghost" style="border-color:var(--danger);color:var(--danger)">🔥 Incendi</button>
        <button class="btn btn--xs btn--ghost" style="border-color:var(--warn);color:var(--warn)">⚠ Avvisi</button>
        <button class="btn btn--xs btn--ghost" style="border-color:var(--brand);color:var(--brand)">✓ OK</button>
        <button class="btn btn--xs btn--ghost">🛋 Soggiorno</button>
        <button class="btn btn--xs btn--ghost">🍳 Cucina</button>
        <button class="btn btn--xs btn--ghost">🛏 Camera</button>
        <button class="btn btn--xs btn--ghost">🚿 Bagno</button>
      </div>

      <div class="card" id="history-container"><!-- rendered by JS --></div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: NOTIFICATIONS
    ══════════════════════════════════════ -->
    <section class="page" id="page-notifications">
      <div class="section-header">
        <span class="section-title">Notifiche</span>
        <div style="display:flex;gap:var(--sp-2);align-items:center">
          <span class="badge badge--danger">2 non lette</span>
          <button class="btn btn--sm btn--ghost">Segna tutte come lette</button>
        </div>
      </div>

      <!-- Channel status -->
      <div class="grid-2">
        <div class="card card--brand">
          <div class="card__header">
            <div><div class="card__title">Telegram</div><div class="card__subtitle">Notifiche push</div></div>
            <span class="badge badge--ok"><span class="badge__dot"></span>Attivo</span>
          </div>
          <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono)">
            Bot: @smartnest_bot · Chat ID: 1234567890<br>
            Ultimi messaggi inviati: 2 · oggi
          </div>
        </div>
        <div class="card card--brand">
          <div class="card__header">
            <div><div class="card__title">Email</div><div class="card__subtitle">Notifiche via posta</div></div>
            <span class="badge badge--ok"><span class="badge__dot"></span>Attivo</span>
          </div>
          <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono)">
            SMTP: smtp.gmail.com · Destinatari: 2<br>
            Ultimi messaggi inviati: 1 · oggi
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__header">
          <div class="card__title">Tutti gli eventi</div>
          <div class="card__subtitle">Ordinati per data decrescente</div>
        </div>
        <div class="notif-list" id="notif-list-page"><!-- rendered by JS --></div>
      </div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: USERS
    ══════════════════════════════════════ -->
    <section class="page" id="page-users">
      <div class="section-header">
        <span class="section-title">Utenti & Permessi</span>
        <button class="btn btn--sm btn--primary">+ Invita utente</button>
      </div>

      <div class="alert-banner alert-banner--info">
        <span>ℹ</span>
        <span>Gli utenti <strong>Admin</strong> possono configurare la casa. I <strong>Viewer</strong> visualizzano solo i dati.</span>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Utenti registrati</div><div class="card__subtitle">3 utenti · 2 admin · 1 viewer</div></div>
          </div>
          <div id="users-list"><!-- rendered by JS --></div>
        </div>

        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Permessi ruoli</div><div class="card__subtitle">Matrice accessi</div></div>
          </div>
          <table class="data-table" style="font-size:12px">
            <thead>
              <tr><th>Funzione</th><th>Admin</th><th>Viewer</th></tr>
            </thead>
            <tbody>
              <tr><td>Visualizza dati</td><td style="color:var(--brand)">✓</td><td style="color:var(--brand)">✓</td></tr>
              <tr><td>Riceve notifiche</td><td style="color:var(--brand)">✓</td><td style="color:var(--brand)">✓</td></tr>
              <tr><td>Configura stanze</td><td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
              <tr><td>Gestisce utenti</td><td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
              <tr><td>Impostazioni sistema</td><td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
              <tr><td>Controlla pompa</td><td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: SETTINGS
    ══════════════════════════════════════ -->
    <section class="page" id="page-settings">
      <div class="section-header">
        <span class="section-title">Impostazioni Sistema</span>
        <span class="badge badge--info">Solo Admin</span>
      </div>

      <div class="grid-2">
        <!-- Soglie sensori -->
        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Soglie Alert</div><div class="card__subtitle">Valori che attivano le notifiche</div></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:var(--sp-4)">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Temperatura massima (°C)</label>
              <input class="input" type="number" value="35" min="20" max="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Umidità massima (%)</label>
              <input class="input" type="number" value="70" min="40" max="95">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">AQI massimo</label>
              <input class="input" type="number" value="100" min="50" max="300">
            </div>
            <button class="btn btn--primary btn--sm" style="width:fit-content">Salva soglie</button>
          </div>
        </div>

        <!-- Notifiche -->
        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Configurazione Notifiche</div><div class="card__subtitle">Canali di invio</div></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:var(--sp-4)">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Telegram Bot Token</label>
              <input class="input" type="password" value="7123456789:ABC-xxx" placeholder="Token bot Telegram">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Chat ID Telegram</label>
              <input class="input" type="text" value="1234567890" placeholder="Chat ID destinatario">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">SMTP Email</label>
              <input class="input" type="email" value="alert@smartnest.io" placeholder="Email mittente">
            </div>
            <div style="display:flex;gap:var(--sp-2)">
              <button class="btn btn--primary btn--sm">Salva</button>
              <button class="btn btn--ghost btn--sm">🧪 Test notifica</button>
            </div>
          </div>
        </div>

        <!-- Casa -->
        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Informazioni Casa</div><div class="card__subtitle">Configurazione proprietà</div></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:var(--sp-4)">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Nome casa</label>
              <input class="input" type="text" value="Casa Milano" placeholder="Es. Casa Milano">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Indirizzo</label>
              <input class="input" type="text" value="Via Roma 12, Milano" placeholder="Indirizzo completo">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Intervallo aggiornamento</label>
              <select class="input">
                <option value="30">Ogni 30 secondi</option>
                <option value="60">Ogni minuto</option>
                <option value="300">Ogni 5 minuti</option>
              </select>
            </div>
            <button class="btn btn--primary btn--sm" style="width:fit-content">Salva</button>
          </div>
        </div>

        <!-- Sistema estinzione -->
        <div class="card card--danger">
          <div class="card__header">
            <div><div class="card__title" style="color:var(--danger)">Sistema Estinzione</div><div class="card__subtitle">Configurazione pompa automatica</div></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:var(--sp-4)">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--sp-3);background:var(--bg-elevated);border-radius:var(--radius-md)">
              <div>
                <div style="font-size:13px;font-weight:600">Attivazione automatica</div>
                <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">Pompa si attiva automaticamente</div>
              </div>
              <div style="width:40px;height:22px;background:var(--brand);border-radius:11px;cursor:pointer;position:relative">
                <div style="position:absolute;right:3px;top:3px;width:16px;height:16px;background:#fff;border-radius:50%"></div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Temperatura soglia attivazione (°C)</label>
              <input class="input" type="number" value="50" min="30" max="100">
            </div>
            <div style="padding:var(--sp-3);background:var(--danger-dim);border:1px solid rgba(240,64,96,0.2);border-radius:var(--radius-md);font-size:11px;color:var(--danger)">
              ⚠ Modificare queste impostazioni solo se si conosce il sistema. Un'attivazione errata può causare danni.
            </div>
            <button class="btn btn--sm" style="background:var(--danger);color:#fff;width:fit-content">Salva configurazione</button>
          </div>
        </div>
      </div>
    </section>

  </main><!-- /app-main -->

</div><!-- /app-layout -->


<!-- ══════════════════════════════════════
     FIRE MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="fire-modal">
  <div class="modal modal--danger">
    <span class="modal__icon">🔥</span>
    <h2 class="modal__title" style="color:var(--danger)">INCENDIO RILEVATO</h2>
    <p class="modal__desc">Cucina · 14:32:07 · Temp: 78°C · Fumo MQ135: CRITICO</p>

    <div class="pump-status" style="margin-bottom:var(--sp-4);justify-content:center">
      <span class="pump-icon">💦</span>
      <span style="color:var(--info);font-size:13px;font-weight:700">Pompa estintrice ATTIVATA automaticamente</span>
    </div>

    <div style="display:flex;gap:var(--sp-2);justify-content:center;margin-bottom:var(--sp-5)">
      <span class="channel-chip channel-chip--telegram">✈ Telegram inviato</span>
      <span class="channel-chip channel-chip--mail">✉ Email inviata</span>
    </div>

    <div class="modal__actions">
      <button class="btn btn--danger" onclick="closeModal();Router.navigate('rooms')">⚠ Gestisci stanza</button>
      <button class="btn btn--ghost" onclick="closeModal()">Visto</button>
    </div>
  </div>
</div>


<script src="js/auth.js"></script>
<script src="js/app.js"></script>
</body>
</html>
