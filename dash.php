<?php
require_once("lib/conn.php");

// ══════════════════════════════════════
// UTENTE CORRENTE (simulato: primo Proprietario)
// In produzione sostituire con sessione: $_SESSION['id_utente']
// ══════════════════════════════════════
session_start();

$id_utente_corrente = $_SESSION['utente'];

// Query: dati utente corrente (nome, cognome, immagine_profilo, ruolo)
$stmt = $pdo->prepare("
    SELECT id_utente, nome, cognome, email, immagine_profilo, ruolo
    FROM utenti
    WHERE id_utente = ?
");
$stmt->execute([$id_utente_corrente]);
$utente_corrente = $stmt->fetch();

// Iniziali avatar (fallback se immagine_profilo è assente)
$iniziali_avatar = strtoupper(substr($utente_corrente['nome'], 0, 1) . substr($utente_corrente['cognome'], 0, 1));
$nome_completo   = htmlspecialchars($utente_corrente['nome'] . ' ' . $utente_corrente['cognome']);
$ruolo_utente    = htmlspecialchars($utente_corrente['ruolo']);
$immagine_profilo = !empty($utente_corrente['immagine_profilo'])
    ? htmlspecialchars($utente_corrente['immagine_profilo'])
    : null;

// ══════════════════════════════════════
// QUERY: CONTEGGIO NOTIFICHE NON LETTE
// (notifiche dell'utente corrente nelle ultime 24h)
// ══════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS totale
    FROM notifiche
    WHERE id_utente = ?
      AND timestamp_invio >= NOW() - INTERVAL 24 HOUR
");
$stmt->execute([$id_utente_corrente]);
$notifiche_badge = $stmt->fetchColumn();

// ══════════════════════════════════════
// QUERY: STORICO RECENTE — ultimi 3 eventi con stanza e dispositivo
// Usata nella tabella "Storico recente" della Panoramica
// ══════════════════════════════════════
$stmt = $pdo->query("
    SELECT
        e.timestamp,
        s.nome          AS stanza,
        te.descrizione  AS tipo_evento,
        m.valore        AS valore,
        d.unita_misura
    FROM eventi e
    JOIN dispositivi  d  ON e.id_dispositivo = d.id_dispositivo
    JOIN stanze       s  ON d.id_stanza      = s.id_stanza
    JOIN tipo_evento  te ON e.id_tipo        = te.id_tipo
    LEFT JOIN misurazioni m ON e.id_misurazione = m.id_misurazione
    ORDER BY e.timestamp DESC
    LIMIT 3
");
$storico_recente = $stmt->fetchAll();

// ══════════════════════════════════════
// QUERY: ULTIME 3 NOTIFICHE per l'utente corrente
// Usata nel widget "Notifiche" della Panoramica
// ══════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT
        n.testo,
        n.tipo_notifica,
        n.timestamp_invio,
        te.descrizione AS tipo_evento,
        s.nome         AS stanza
    FROM notifiche n
    JOIN eventi      e  ON n.id_evento      = e.id_evento
    JOIN dispositivi d  ON e.id_dispositivo = d.id_dispositivo
    JOIN stanze      s  ON d.id_stanza      = s.id_stanza
    JOIN tipo_evento te ON e.id_tipo        = te.id_tipo
    WHERE n.id_utente = ?
    ORDER BY n.timestamp_invio DESC
    LIMIT 3
");
$stmt->execute([$id_utente_corrente]);
$notifiche_overview = $stmt->fetchAll();

// ══════════════════════════════════════
// QUERY: TUTTI GLI UTENTI
// Usata nella pagina "Utenti & Permessi"
// ══════════════════════════════════════
$stmt = $pdo->query("
    SELECT id_utente, nome, cognome, email, immagine_profilo, ruolo, chiave_telegram
    FROM utenti
    ORDER BY ruolo ASC, nome ASC
");
$tutti_utenti = $stmt->fetchAll();

// Conta proprietari e ospiti per il sottotitolo card
$n_proprietari    = count(array_filter($tutti_utenti, fn($u) => $u['ruolo'] === 'Proprietario'));
$n_ospiti         = count(array_filter($tutti_utenti, fn($u) => $u['ruolo'] === 'Ospite'));
$n_totale_utenti  = count($tutti_utenti);

// ══════════════════════════════════════
// QUERY: TUTTE LE NOTIFICHE (pagina Notifiche)
// Ordinate per data decrescente
// ══════════════════════════════════════
$stmt = $pdo->query("
    SELECT
        n.id_notifica,
        n.testo,
        n.tipo_notifica,
        n.timestamp_invio,
        u.nome         AS utente_nome,
        u.cognome      AS utente_cognome,
        te.descrizione AS tipo_evento,
        s.nome         AS stanza,
        te.flag_notifica
    FROM notifiche n
    JOIN utenti      u  ON n.id_utente      = u.id_utente
    JOIN eventi      e  ON n.id_evento      = e.id_evento
    JOIN dispositivi d  ON e.id_dispositivo = d.id_dispositivo
    JOIN stanze      s  ON d.id_stanza      = s.id_stanza
    JOIN tipo_evento te ON e.id_tipo        = te.id_tipo
    ORDER BY n.timestamp_invio DESC
");
$tutte_notifiche = $stmt->fetchAll();

// ══════════════════════════════════════
// QUERY: TUTTE LE MISURAZIONI (storico completo)
// Con dati dispositivo e stanza per la pagina History
// ══════════════════════════════════════
$stmt = $pdo->query("
    SELECT
        m.id_misurazione,
        m.valore,
        m.timestamp,
        d.nome         AS dispositivo,
        d.unita_misura,
        d.soglia_minima,
        d.soglia_massima,
        s.nome         AS stanza
    FROM misurazioni m
    JOIN dispositivi d ON m.id_dispositivo = d.id_dispositivo
    JOIN stanze      s ON d.id_stanza      = s.id_stanza
    ORDER BY m.timestamp DESC
");
$storico_completo = $stmt->fetchAll();

// ══════════════════════════════════════
// QUERY: ULTIMA MISURAZIONE PER OGNI SENSORE
// Usata per le barre AQI e le card stanze
// ══════════════════════════════════════
$stmt = $pdo->query("
    SELECT
        d.id_dispositivo,
        d.nome         AS dispositivo,
        d.tipo,
        d.unita_misura,
        d.soglia_minima,
        d.soglia_massima,
        s.id_stanza,
        s.nome         AS stanza,
        s.volumetria,
        m.valore       AS ultima_misura,
        m.timestamp    AS ultima_lettura
    FROM dispositivi d
    JOIN stanze s ON d.id_stanza = s.id_stanza
    LEFT JOIN misurazioni m ON m.id_misurazione = (
        SELECT id_misurazione
        FROM misurazioni
        WHERE id_dispositivo = d.id_dispositivo
        ORDER BY timestamp DESC
        LIMIT 1
    )
    WHERE d.tipo = 'Sensore'
    ORDER BY s.id_stanza, d.id_dispositivo
");
$sensori = $stmt->fetchAll();

// ══════════════════════════════════════
// QUERY: CONTEGGI STAT-CARD PANORAMICA
// ══════════════════════════════════════
$n_sensori_attivi = $pdo->query("SELECT COUNT(*) FROM dispositivi WHERE tipo = 'Sensore'")->fetchColumn();
$n_stanze         = $pdo->query("SELECT COUNT(*) FROM stanze")->fetchColumn();
$n_alert_attivi   = $pdo->query("
    SELECT COUNT(DISTINCT e.id_evento)
    FROM eventi e
    JOIN tipo_evento te ON e.id_tipo = te.id_tipo
    WHERE te.flag_notifica = 1
      AND e.timestamp >= NOW() - INTERVAL 24 HOUR
")->fetchColumn();
$n_notifiche_oggi = $pdo->query("
    SELECT COUNT(*) FROM notifiche WHERE timestamp_invio >= CURDATE()
")->fetchColumn();

// ══════════════════════════════════════
// FUNZIONI HELPER PHP
// ══════════════════════════════════════

/**
 * Restituisce lo stato (ok/warn/danger) confrontando il valore con le soglie del sensore.
 */
function getStatoSensore(float $valore, ?float $min, ?float $max): string {
    if ($min !== null && $valore < $min) return 'warn';
    if ($max !== null && $valore > $max) return 'danger';
    return 'ok';
}

/**
 * Restituisce l'icona emoji per nome stanza.
 */
function getIconaStanza(string $nome): string {
    $nome = strtolower($nome);
    if (str_contains($nome, 'cucina'))    return '🍳';
    if (str_contains($nome, 'soggiorno')) return '🛋';
    if (str_contains($nome, 'camera'))    return '🛏';
    if (str_contains($nome, 'bagno'))     return '🚿';
    return '🏠';
}

/**
 * Restituisce l'icona emoji per tipo di evento.
 */
function getIconaEvento(string $descrizione): string {
    $d = strtolower($descrizione);
    if (str_contains($d, 'incendio'))                               return '🔥';
    if (str_contains($d, 'alta') || str_contains($d, 'anomalo'))    return '⚠';
    if (str_contains($d, 'bassa'))                                  return '🌡';
    if (str_contains($d, 'aria'))                                   return '💨';
    if (str_contains($d, 'luce'))                                   return '💡';
    return 'ℹ';
}

/**
 * Renderizza l'avatar utente:
 * - <img> con immagine_profilo se presente nel DB
 * - <div> con le iniziali come fallback
 */
function renderAvatar(array $utente, string $classeCSS = 'avatar'): string {
    $iniziali = strtoupper(substr($utente['nome'], 0, 1) . substr($utente['cognome'], 0, 1));
    if (!empty($utente['immagine_profilo'])) {
        $src = htmlspecialchars($utente['immagine_profilo']);
        $alt = htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']);
        return "<img src=\"{$src}\" alt=\"{$alt}\" class=\"{$classeCSS}\" style=\"object-fit:cover;border-radius:50%;\">";
    }
    // Nessuna immagine: mostra le iniziali
    return "<div class=\"{$classeCSS}\">{$iniziali}</div>";
}
?>
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
        <!-- Badge dinamico: notifiche ultime 24h per l'utente corrente -->
        <?php if ($notifiche_badge > 0): ?>
          <span class="nav__badge"><?= $notifiche_badge ?></span>
        <?php endif; ?>
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

    <!-- Footer sidebar: avatar e nome utente dal DB -->
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
        <!-- Avatar: immagine profilo dal DB se presente, altrimenti iniziali -->
        <?= renderAvatar($utente_corrente, 'avatar') ?>
        <div class="sidebar__user-info">
          <div class="sidebar__user-name" id="topbar-user-name"><?= $nome_completo ?></div>
          <!-- Ruolo dell'utente dal DB -->
          <div class="sidebar__user-role"><?= $ruolo_utente ?></div>
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

      <!-- ── STAT CARDS — dati conteggio dal DB ── -->
      <div class="grid-4" id="stat-cards">
        <div class="stat-card">
          <div class="stat-card__label">Sensori attivi</div>
          <div class="stat-card__value"><?= $n_sensori_attivi ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-card__label">Stanze</div>
          <div class="stat-card__value"><?= $n_stanze ?></div>
        </div>
        <!-- Alert attivi nelle ultime 24h dal DB -->
        <div class="stat-card">
          <div class="stat-card__label">Alert oggi</div>
          <div class="stat-card__value" style="color:var(--danger)"><?= $n_alert_attivi ?></div>
        </div>
        <!-- Totale notifiche inviate oggi dal DB -->
        <div class="stat-card">
          <div class="stat-card__label">Notifiche inviate</div>
          <div class="stat-card__value"><?= $n_notifiche_oggi ?></div>
        </div>
      </div>

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

          <!-- ── STORICO RECENTE — ultimi 3 eventi dal DB ── -->
          <div class="card">
            <div class="card__header">
              <div>
                <div class="card__title">Storico recente</div>
                <div class="card__subtitle">Ultimi eventi sensori</div>
              </div>
              <button class="btn btn--sm btn--ghost" onclick="Router.navigate('history')">Storico completo →</button>
            </div>
            <table class="data-table">
              <thead><tr>
                <th>Orario</th><th>Stanza</th><th>Valore</th><th>Evento</th>
              </tr></thead>
              <tbody>
                <?php foreach ($storico_recente as $riga):
                    // Calcola colore riga in base al tipo di evento
                    $stato = 'ok';
                    if (str_contains(strtolower($riga['tipo_evento']), 'incendio')) $stato = 'danger';
                    elseif (str_contains(strtolower($riga['tipo_evento']), 'alta')    ||
                            str_contains(strtolower($riga['tipo_evento']), 'anomalo') ||
                            str_contains(strtolower($riga['tipo_evento']), 'scarsa'))  $stato = 'warn';

                    $orario  = date('H:i', strtotime($riga['timestamp']));
                    $colore  = $stato === 'danger' ? 'var(--danger)' : ($stato === 'warn' ? 'var(--warn)' : 'var(--brand)');
                    // Formatta valore con unità di misura se disponibile
                    $val_fmt = $riga['valore'] !== null
                        ? htmlspecialchars($riga['valore'] . ($riga['unita_misura'] ? ' ' . $riga['unita_misura'] : ''))
                        : '—';
                ?>
                <tr>
                  <td style="color:<?= $colore ?>"><?= $orario ?></td>
                  <td><?= getIconaStanza($riga['stanza']) . ' ' . htmlspecialchars($riga['stanza']) ?></td>
                  <td style="color:<?= $colore ?>"><?= $val_fmt ?></td>
                  <td style="color:<?= $colore ?>"><?= getIconaEvento($riga['tipo_evento']) . ' ' . htmlspecialchars($riga['tipo_evento']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>

        <!-- RIGHT -->
        <div style="display:flex;flex-direction:column;gap:var(--sp-4)">

          <!-- ── ULTIME 3 NOTIFICHE dell'utente corrente dal DB ── -->
          <div class="card">
            <div class="card__header">
              <div>
                <div class="card__title">Notifiche</div>
                <div class="card__subtitle">Ultime 3</div>
              </div>
              <button class="btn btn--xs btn--ghost" onclick="Router.navigate('notifications')">Tutte</button>
            </div>
            <div class="notif-list" id="notif-list-overview">
              <?php foreach ($notifiche_overview as $n):
                  $icona_ch = $n['tipo_notifica'] === 'Telegram' ? '✈' : '✉';
                  $ts = date('H:i', strtotime($n['timestamp_invio']));
              ?>
              <div class="notif-item">
                <span class="notif-item__icon"><?= $icona_ch ?></span>
                <div class="notif-item__body">
                  <div class="notif-item__title"><?= htmlspecialchars($n['testo']) ?></div>
                  <div class="notif-item__meta">
                    <?= htmlspecialchars($n['stanza']) ?> · <?= $ts ?> · <?= htmlspecialchars($n['tipo_notifica']) ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
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

      <!-- ── BARRE AQI — solo sensori con unità AQI dal DB ── -->
      <div class="card">
        <div class="card__header">
          <div>
            <div class="card__title">Qualità Aria per Stanza</div>
            <div class="card__subtitle">Sensore MQ135 — ultima rilevazione</div>
          </div>
        </div>
        <div id="aqi-bars-rooms">
          <?php
          // Filtra sensori AQI dall'array già caricato
          $sensori_aqi = array_filter($sensori, fn($s) => $s['unita_misura'] === 'AQI');
          foreach ($sensori_aqi as $s):
              $val   = (float)($s['ultima_misura'] ?? 0);
              $max   = (float)($s['soglia_massima'] ?? 100);
              $perc  = min(100, round(($val / $max) * 100));
              $stato = getStatoSensore($val, $s['soglia_minima'], $s['soglia_massima']);
              $colore = $stato === 'danger' ? 'var(--danger)' : ($stato === 'warn' ? 'var(--warn)' : 'var(--brand)');
          ?>
          <div style="margin-bottom:var(--sp-3)">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
              <span><?= getIconaStanza($s['stanza']) . ' ' . htmlspecialchars($s['stanza']) ?></span>
              <span style="color:<?= $colore ?>;font-family:var(--font-mono)"><?= $val ?> AQI</span>
            </div>
            <div style="background:var(--bg-elevated);border-radius:4px;height:6px;overflow:hidden">
              <div style="width:<?= $perc ?>%;height:100%;background:<?= $colore ?>;border-radius:4px;transition:width .4s"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
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

      <!-- Filter chips: filtri statici + stanze dinamiche dal DB -->
      <div style="display:flex;gap:var(--sp-2);flex-wrap:wrap">
        <button class="btn btn--xs btn--ghost" style="border-color:var(--danger);color:var(--danger)">🔥 Incendi</button>
        <button class="btn btn--xs btn--ghost" style="border-color:var(--warn);color:var(--warn)">⚠ Avvisi</button>
        <button class="btn btn--xs btn--ghost" style="border-color:var(--brand);color:var(--brand)">✓ OK</button>
        <?php
        // Chip filtro per ogni stanza presente nel DB
        $stmt_stanze = $pdo->query("SELECT nome FROM stanze ORDER BY id_stanza");
        foreach ($stmt_stanze as $st):
        ?>
        <button class="btn btn--xs btn--ghost"><?= getIconaStanza($st['nome']) . ' ' . htmlspecialchars($st['nome']) ?></button>
        <?php endforeach; ?>
      </div>

      <!-- ── TABELLA STORICO — tutte le misurazioni dal DB ── -->
      <div class="card" id="history-container">
        <table class="data-table">
          <thead><tr>
            <th>Data/Ora</th><th>Stanza</th><th>Dispositivo</th><th>Valore</th><th>Stato</th>
          </tr></thead>
          <tbody>
            <?php foreach ($storico_completo as $row):
                $stato  = getStatoSensore(
                    (float)$row['valore'],
                    $row['soglia_minima']  !== null ? (float)$row['soglia_minima']  : null,
                    $row['soglia_massima'] !== null ? (float)$row['soglia_massima'] : null
                );
                $colore = $stato === 'danger' ? 'var(--danger)' : ($stato === 'warn' ? 'var(--warn)' : 'var(--brand)');
                $label  = $stato === 'danger' ? '⚠ Fuori soglia' : ($stato === 'warn' ? '~ Attenzione' : '✓ OK');
                $ts     = date('d/m H:i', strtotime($row['timestamp']));
            ?>
            <tr>
              <td style="font-family:var(--font-mono);font-size:11px"><?= $ts ?></td>
              <td><?= getIconaStanza($row['stanza']) . ' ' . htmlspecialchars($row['stanza']) ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($row['dispositivo']) ?></td>
              <td style="color:<?= $colore ?>;font-family:var(--font-mono)">
                <?= htmlspecialchars($row['valore'] . ($row['unita_misura'] ? ' ' . $row['unita_misura'] : '')) ?>
              </td>
              <td style="color:<?= $colore ?>;font-size:11px"><?= $label ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>


    <!-- ══════════════════════════════════════
         PAGE: NOTIFICATIONS
    ══════════════════════════════════════ -->
    <section class="page" id="page-notifications">
      <div class="section-header">
        <span class="section-title">Notifiche</span>
        <div style="display:flex;gap:var(--sp-2);align-items:center">
          <!-- Badge con conteggio notifiche ultime 24h dal DB -->
          <span class="badge badge--danger"><?= $notifiche_badge ?> non lette</span>
          <button class="btn btn--sm btn--ghost">Segna tutte come lette</button>
        </div>
      </div>

      <!-- ── CARD CANALI — conteggi messaggi inviati oggi per canale ── -->
      <div class="grid-2">
        <?php
        // Conta notifiche inviate oggi per canale (Telegram / Email)
        $stmt_ch = $pdo->query("
            SELECT tipo_notifica, COUNT(*) AS totale
            FROM notifiche
            WHERE timestamp_invio >= CURDATE()
            GROUP BY tipo_notifica
        ");
        $canali = [];
        foreach ($stmt_ch as $c) $canali[$c['tipo_notifica']] = $c['totale'];
        ?>
        <div class="card card--brand">
          <div class="card__header">
            <div><div class="card__title">Telegram</div><div class="card__subtitle">Notifiche push</div></div>
            <span class="badge badge--ok"><span class="badge__dot"></span>Attivo</span>
          </div>
          <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono)">
            Bot: @smartnest_bot · Chat ID: 1234567890<br>
            <!-- Conteggio messaggi Telegram oggi dal DB -->
            Messaggi inviati oggi: <?= $canali['Telegram'] ?? 0 ?>
          </div>
        </div>
        <div class="card card--brand">
          <div class="card__header">
            <div><div class="card__title">Email</div><div class="card__subtitle">Notifiche via posta</div></div>
            <span class="badge badge--ok"><span class="badge__dot"></span>Attivo</span>
          </div>
          <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono)">
            SMTP: smtp.gmail.com<br>
            <!-- Conteggio email inviate oggi dal DB -->
            Email inviate oggi: <?= $canali['Email'] ?? 0 ?>
          </div>
        </div>
      </div>

      <!-- ── LISTA COMPLETA NOTIFICHE dal DB ── -->
      <div class="card">
        <div class="card__header">
          <div class="card__title">Tutti gli eventi</div>
          <div class="card__subtitle">Ordinati per data decrescente</div>
        </div>
        <div class="notif-list" id="notif-list-page">
          <?php foreach ($tutte_notifiche as $n):
              $icona_ch    = $n['tipo_notifica'] === 'Telegram' ? '✈' : '✉';
              $ts          = date('d/m H:i', strtotime($n['timestamp_invio']));
              // Badge colorato in base al flag_notifica (1=alert, 0=info normale)
              $badge_class = $n['flag_notifica'] ? 'badge--danger' : 'badge--info';
          ?>
          <div class="notif-item">
            <span class="notif-item__icon"><?= $icona_ch ?></span>
            <div class="notif-item__body">
              <div class="notif-item__title"><?= htmlspecialchars($n['testo']) ?></div>
              <div class="notif-item__meta">
                <?= getIconaStanza($n['stanza']) . ' ' . htmlspecialchars($n['stanza']) ?>
                · <?= $ts ?>
                · <?= htmlspecialchars($n['utente_nome'] . ' ' . $n['utente_cognome']) ?>
                · <span class="badge <?= $badge_class ?>" style="font-size:10px"><?= htmlspecialchars($n['tipo_notifica']) ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
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
        <span>Gli utenti <strong>Proprietario</strong> possono configurare la casa. Gli <strong>Ospiti</strong> visualizzano solo i dati.</span>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card__header">
            <div>
              <div class="card__title">Utenti registrati</div>
              <!-- Sottotitolo con conteggi dinamici dal DB -->
              <div class="card__subtitle">
                <?= $n_totale_utenti ?> utenti · <?= $n_proprietari ?> proprietari · <?= $n_ospiti ?> ospiti
              </div>
            </div>
          </div>

          <!-- ── LISTA UTENTI — con avatar dinamico dal DB ── -->
          <div id="users-list">
            <?php foreach ($tutti_utenti as $u):
                // Badge ruolo: verde per Proprietario, blu per Ospite
                $badge_ruolo = $u['ruolo'] === 'Proprietario' ? 'badge--ok' : 'badge--info';
            ?>
            <div class="user-item" style="display:flex;align-items:center;gap:var(--sp-3);padding:var(--sp-3) 0;border-bottom:1px solid var(--border)">
              <!-- Avatar: se immagine_profilo è valorizzato nel DB mostra <img>, altrimenti le iniziali -->
              <?= renderAvatar($u, 'avatar avatar--sm') ?>
              <div style="flex:1">
                <div style="font-size:13px;font-weight:600">
                  <?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">
                  <?= htmlspecialchars($u['email']) ?>
                </div>
                <?php if (!empty($u['chiave_telegram'])): ?>
                <div style="font-size:10px;color:var(--text-muted)">
                  ✈ Telegram configurato
                </div>
                <?php endif; ?>
              </div>
              <span class="badge <?= $badge_ruolo ?>"><?= htmlspecialchars($u['ruolo']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Permessi ruoli</div><div class="card__subtitle">Matrice accessi</div></div>
          </div>
          <table class="data-table" style="font-size:12px">
            <thead>
              <!-- Colonne aggiornate con i ruoli reali del DB (Proprietario / Ospite) -->
              <tr><th>Funzione</th><th>Proprietario</th><th>Ospite</th></tr>
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
        <span class="badge badge--info">Solo Proprietario</span>
      </div>

      <div class="grid-2">

        <!-- ── SOGLIE ALERT — pre-compilate con valori dal DB ── -->
        <?php
        // Recupera le soglie massime per ciascuna unità di misura presente nei sensori
        $stmt_soglie = $pdo->query("
            SELECT unita_misura, MIN(soglia_minima) AS soglia_minima, MAX(soglia_massima) AS soglia_massima
            FROM dispositivi
            WHERE unita_misura IN ('°C','%','AQI')
            GROUP BY unita_misura
        ");
        $soglie = [];
        foreach ($stmt_soglie as $sg) $soglie[$sg['unita_misura']] = $sg;
        ?>
        <div class="card">
          <div class="card__header">
            <div><div class="card__title">Soglie Alert</div><div class="card__subtitle">Valori che attivano le notifiche</div></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:var(--sp-4)">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Temperatura massima (°C)</label>
              <!-- Valore pre-compilato con soglia_massima temperatura dal DB -->
              <input class="input" type="number" value="<?= $soglie['°C']['soglia_massima'] ?? 35 ?>" min="20" max="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">Umidità massima (%)</label>
              <!-- Valore pre-compilato con soglia_massima umidità dal DB -->
              <input class="input" type="number" value="<?= $soglie['%']['soglia_massima'] ?? 70 ?>" min="40" max="95">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;color:var(--text-muted)">AQI massimo</label>
              <!-- Valore pre-compilato con soglia_massima AQI dal DB -->
              <input class="input" type="number" value="<?= $soglie['AQI']['soglia_massima'] ?? 100 ?>" min="50" max="300">
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
              <!-- Pre-compila con la chiave_telegram dell'utente corrente dal DB -->
              <input class="input" type="text"
                value="<?= htmlspecialchars($utente_corrente['chiave_telegram'] ?? '') ?>"
                placeholder="Chat ID destinatario">
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
              <!-- Usa la soglia_massima temperatura dal DB come valore di default -->
              <input class="input" type="number" value="<?= $soglie['°C']['soglia_massima'] ?? 50 ?>" min="30" max="100">
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
