<?php
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}

include_once '../lib/conn.php';

$id_utente = $_SESSION['utente'];
$nome      = $_SESSION['nome'];
$cognome   = $_SESSION['cognome'];
$ruolo     = $_SESSION['ruolo'];           // 'Proprietario' | 'Ospite'
$isOwner   = $ruolo === 'Proprietario';

// ── Dati stanze con ultima misurazione per ogni sensore ──────
$sqlStanze = "SELECT s.id_stanza, s.nome, s.volumetria FROM stanze s ORDER BY s.id_stanza";
$stanze    = $conn->query($sqlStanze)->fetchAll();

// Per ogni stanza: recupera sensori con ultima misurazione
foreach ($stanze as &$stanza) {
    $sid  = $stanza['id_stanza'];

    $sqlDisp = "SELECT d.id_dispositivo, d.nome, d.tipo, d.unita_misura,
                       d.soglia_minima, d.soglia_massima,
                       m.valore, m.timestamp AS ultima_lettura
                FROM dispositivi d
                LEFT JOIN misurazioni m ON m.id_misurazione = (
                    SELECT id_misurazione FROM misurazioni
                    WHERE id_dispositivo = d.id_dispositivo
                    ORDER BY timestamp DESC LIMIT 1
                )
                WHERE d.id_stanza = :sid
                ORDER BY d.tipo DESC, d.id_dispositivo";
    $stmtD = $conn->prepare($sqlDisp);
    $stmtD->execute(['sid' => $sid]);
    $stanza['dispositivi'] = $stmtD->fetchAll();

    // Calcola status stanza
    $status = 'ok';
    foreach ($stanza['dispositivi'] as $d) {
        if ($d['valore'] === null) continue;
        $v   = (float)$d['valore'];
        $min = $d['soglia_minima']  !== null ? (float)$d['soglia_minima']  : null;
        $max = $d['soglia_massima'] !== null ? (float)$d['soglia_massima'] : null;
        if (($min !== null && $v < $min) || ($max !== null && $v > $max)) {
            $status = 'warn';
        }
    }

    // Controlla incendio attivo (evento tipo 1 negli ultimi 10 min)
    $sqlFire = "SELECT COUNT(*) FROM eventi e
                JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
                WHERE d.id_stanza = :sid AND e.id_tipo = 1
                  AND e.timestamp >= NOW() - INTERVAL 10 MINUTE";
    $stmtF = $conn->prepare($sqlFire);
    $stmtF->execute(['sid' => $sid]);
    if ($stmtF->fetchColumn() > 0) $status = 'fire';

    $stanza['status'] = $status;
}
unset($stanza);

// ── Incendio attivo (per banner e modal) ─────────────────────
$sqlFire = "SELECT s.nome AS stanza, e.timestamp
            FROM eventi e
            JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
            JOIN stanze s      ON s.id_stanza = d.id_stanza
            WHERE e.id_tipo = 1
              AND e.timestamp >= NOW() - INTERVAL 10 MINUTE
            ORDER BY e.timestamp DESC LIMIT 1";
$fireRow = $conn->query($sqlFire)->fetch();
$hasFire = (bool)$fireRow;

// ── Statistiche riepilogative ─────────────────────────────────
$nFire  = 0;
$nWarn  = 0;
$temps  = [];
$hums   = [];
foreach ($stanze as $s) {
    if ($s['status'] === 'fire') $nFire++;
    if ($s['status'] === 'warn') $nWarn++;
    foreach ($s['dispositivi'] as $d) {
        if ($d['valore'] === null) continue;
        if ($d['unita_misura'] === '°C') $temps[] = (float)$d['valore'];
        if ($d['unita_misura'] === '%')  $hums[]  = (float)$d['valore'];
    }
}
$avgTemp = count($temps) ? number_format(array_sum($temps)/count($temps), 1) : '—';
$avgHum  = count($hums)  ? round(array_sum($hums)/count($hums))              : '—';

// ── Ultimi 5 eventi ───────────────────────────────────────────
$sqlEventi = "SELECT e.timestamp, t.descrizione AS tipo, t.id_tipo,
                     d.nome AS dispositivo, s.nome AS stanza
              FROM eventi e
              JOIN tipo_evento t ON t.id_tipo = e.id_tipo
              JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
              JOIN stanze s      ON s.id_stanza = d.id_stanza
              ORDER BY e.timestamp DESC LIMIT 5";
$ultimiEventi = $conn->query($sqlEventi)->fetchAll();

// ── Ultime 4 notifiche ────────────────────────────────────────
$sqlNotif = "SELECT n.testo, n.tipo_notifica, n.timestamp_invio,
                    t.descrizione AS tipo_evento, t.id_tipo,
                    s.nome AS stanza
             FROM notifiche n
             JOIN eventi e      ON e.id_evento = n.id_evento
             JOIN tipo_evento t ON t.id_tipo = e.id_tipo
             JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
             JOIN stanze s      ON s.id_stanza = d.id_stanza
             ORDER BY n.timestamp_invio DESC LIMIT 4";
$notifiche = $conn->query($sqlNotif)->fetchAll();

// ── Helper funzioni ───────────────────────────────────────────
function badgeStatus(string $status): string {
    $map = [
        'ok'   => '<span class="badge badge--ok"><span class="badge__dot"></span>OK</span>',
        'warn' => '<span class="badge badge--warn"><span class="badge__dot"></span>Attenzione</span>',
        'fire' => '<span class="badge badge--danger"><span class="badge__dot"></span>INCENDIO</span>',
    ];
    return $map[$status] ?? $map['ok'];
}

function sensorIcon(string $unit): string {
    if ($unit === '°C')  return '🌡';
    if ($unit === '%')   return '💧';
    if ($unit === 'AQI') return '🌬';
    return '📡';
}

function fuoriSoglia(mixed $v, mixed $min, mixed $max): bool {
    if ($v === null) return false;
    $v = (float)$v;
    if ($min !== null && $v < (float)$min) return true;
    if ($max !== null && $v > (float)$max) return true;
    return false;
}

function iniziali(string $nome, string $cognome): string {
    return strtoupper(mb_substr($nome,0,1) . mb_substr($cognome,0,1));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Dashboard</title>
  <link rel="stylesheet" href="../styles/main.css">
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
    <span class="sidebar__brand">Smart<span>Home</span></span>
  </div>

  <nav class="sidebar__nav">

    <div class="nav__section-label">Principale</div>

    <div class="nav__item active" data-page="overview" title="Panoramica">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
      </span>
      <span class="nav__label">Panoramica</span>
    </div>

    <a href="../stanze/index.php" class="nav__item" title="Stanze">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </span>
      <span class="nav__label">Stanze</span>
    </a>

    <div class="nav__section-label">Stanze</div>

    <?php if ($isOwner): ?>
    <a href="../stanze/crea.php" class="nav__item" title="Crea stanza">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea stanza</span>
    </a>
    <?php endif; ?>

    <div class="nav__section-label">Dispositivi</div>

    <a href="../dispositivi/index.php" class="nav__item" title="Tutti i dispositivi">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="3" width="20" height="14" rx="2"/>
          <line x1="8" y1="21" x2="16" y2="21"/>
          <line x1="12" y1="17" x2="12" y2="21"/>
        </svg>
      </span>
      <span class="nav__label">Dispositivi</span>
    </a>

    <?php if ($isOwner): ?>
    <a href="../dispositivi/crea.php" class="nav__item" title="Crea dispositivo">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="16"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Crea dispositivo</span>
    </a>
    <?php endif; ?>

    <div class="nav__section-label">Sistema</div>

    <?php if ($isOwner): ?>
    <a href="../utenti/index.php" class="nav__item" title="Utenti">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/>
          <path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </span>
      <span class="nav__label">Utenti</span>
    </a>
    <a href="../utenti/crea.php" class="nav__item" title="Crea utente">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="16"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Crea utente</span>
    </a>
    <?php endif; ?>

    <div class="nav__section-label">Alert</div>

    <div class="nav__item" data-page="notifiche" title="Notifiche">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
      </span>
      <span class="nav__label">Notifiche</span>
      <?php if ($hasFire): ?>
      <span class="nav__badge">!</span>
      <?php endif; ?>
    </div>

  </nav>

  <div class="sidebar__footer">
    <a href="../lib/logout.php" class="nav__item nav__item--danger" title="Esci">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Esci</span>
    </a>
    <div class="sidebar__user">
      <?php if (!empty($_SESSION['avatar'])): ?>
        <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>"
             class="avatar" style="object-fit:cover" alt="avatar">
      <?php else: ?>
        <div class="avatar" style="background:linear-gradient(135deg,#00d4b4,#0088aa)">
          <?= iniziali($nome, $cognome) ?>
        </div>
      <?php endif; ?>
      <div class="sidebar__user-info">
        <div class="sidebar__user-name"><?= htmlspecialchars("$nome $cognome") ?></div>
        <div class="sidebar__user-role"><?= htmlspecialchars($ruolo) ?></div>
      </div>
    </div>
  </div>

</aside><!-- /sidebar -->

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

      <?php if ($hasFire): ?>
      <div class="alert-banner alert-banner--danger"
           style="padding:6px 14px;cursor:pointer;font-size:12px"
           onclick="document.getElementById('fire-modal').classList.add('open')">
        <span>🔥</span>
        <span>INCENDIO — <?= htmlspecialchars($fireRow['stanza']) ?></span>
      </div>
      <?php endif; ?>

      <div class="notif-bell" onclick="navigate('notifiche')" style="cursor:pointer">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <?php if ($hasFire): ?>
        <div class="notif-bell__dot"></div>
        <?php endif; ?>
      </div>

    </div>
  </header>

  <!-- ══════════════════════════════════════
       PAGE: OVERVIEW (panoramica)
  ══════════════════════════════════════ -->
  <section class="page active" id="page-overview">

    <?php if ($hasFire): ?>
    <div class="alert-banner alert-banner--danger">
      <span>🔥</span>
      <strong>INCENDIO RILEVATO — <?= htmlspecialchars($fireRow['stanza']) ?></strong>
      <span style="font-weight:400;font-family:var(--font-mono);font-size:11px">
        · <?= date('H:i', strtotime($fireRow['timestamp'])) ?>
      </span>
      <div class="alert-banner__actions">
        <button class="btn btn--sm btn--danger"
                onclick="document.getElementById('fire-modal').classList.add('open')">
          Gestisci
        </button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="grid-4">
      <div class="card stat-card <?= $nFire ? 'card--danger' : '' ?>">
        <span class="stat-card__label">🔥 Incendi attivi</span>
        <div class="stat-card__value <?= $nFire ? 'stat-card__value--danger' : '' ?>">
          <?= $nFire ?><span class="stat-card__unit"> attivi</span>
        </div>
        <div class="stat-card__trend <?= $nFire ? 'stat-card__trend--up' : '' ?>">
          <?= $nFire ? '💦 Estintore ATTIVO' : '✓ Nessun incendio' ?>
        </div>
      </div>

      <div class="card stat-card <?= $nWarn ? 'card--warn' : '' ?>">
        <span class="stat-card__label">⚠ Avvisi sensori</span>
        <div class="stat-card__value <?= $nWarn ? 'stat-card__value--warn' : '' ?>">
          <?= $nWarn ?><span class="stat-card__unit"> stanze</span>
        </div>
        <div class="stat-card__trend">
          <?= $nWarn ? 'Soglie superate' : '✓ Tutto nella norma' ?>
        </div>
      </div>

      <div class="card stat-card">
        <span class="stat-card__label">🌡 Temperatura media</span>
        <div class="stat-card__value">
          <?= $avgTemp ?><span class="stat-card__unit"> °C</span>
        </div>
        <div class="stat-card__trend">Media su <?= count($temps) ?> sensori</div>
      </div>

      <div class="card stat-card">
        <span class="stat-card__label">💧 Umidità media</span>
        <div class="stat-card__value">
          <?= $avgHum ?><span class="stat-card__unit"> %</span>
        </div>
        <div class="stat-card__trend">Media su <?= count($hums) ?> sensori</div>
      </div>
    </div>

    <!-- Stanze (preview 2 colonne) + colonna destra -->
    <div class="grid-main">

      <!-- Colonna sinistra: card stanze -->
      <div style="display:flex;flex-direction:column;gap:var(--sp-4)">
        <div class="section-header">
          <span class="section-title">Stanze</span>
          <a href="../stanze/index.php" class="btn btn--sm btn--ghost">Vedi tutte →</a>
        </div>

        <div class="grid-2">
          <?php foreach ($stanze as $s):
            $fireClass = $s['status'] === 'fire' ? 'room-card--fire' : '';
            $warnStyle = $s['status'] === 'warn' ? 'style="border-color:rgba(240,160,48,0.35)"' : '';
          ?>
          <div class="card room-card <?= $fireClass ?>" <?= $warnStyle ?>>
            <div class="room-card__header">
              <div class="room-card__name">🏠 <?= htmlspecialchars($s['nome']) ?></div>
              <?= badgeStatus($s['status']) ?>
            </div>
            <div class="sensor-grid" style="grid-template-columns:repeat(auto-fill,minmax(70px,1fr))">
              <?php foreach ($s['dispositivi'] as $d):
                if ($d['tipo'] !== 'Sensore' || $d['valore'] === null) continue;
                $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
                $col   = $fuori ? ($s['status']==='fire'?'var(--danger)':'var(--warn)') : 'inherit';
                $cls   = $fuori ? ($s['status']==='fire'?'sensor-pill--danger':'sensor-pill--warn') : '';
              ?>
              <div class="sensor-pill <?= $cls ?>">
                <div class="sensor-pill__icon"><?= sensorIcon($d['unita_misura'] ?? '') ?></div>
                <div class="sensor-pill__value" style="color:<?= $col ?>">
                  <?= htmlspecialchars($d['valore']) ?><?= htmlspecialchars($d['unita_misura'] ?? '') ?>
                </div>
                <div class="sensor-pill__label">
                  <?= htmlspecialchars(explode(' ', $d['nome'])[0]) ?>
                </div>
              </div>
              <?php endforeach; ?>

              <?php if ($s['status'] === 'fire'): ?>
              <div class="sensor-pill" style="border-color:rgba(48,144,240,0.35)">
                <div class="sensor-pill__icon pump-icon">💦</div>
                <div class="sensor-pill__value" style="color:var(--info);font-size:10px">PUMP</div>
                <div class="sensor-pill__label">Attiva</div>
              </div>
              <?php endif; ?>
            </div>
            <div style="margin-top:10px;font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">
              <?= $s['volumetria'] ?> m³ ·
              <?= count(array_filter($s['dispositivi'], fn($d)=>$d['tipo']==='Sensore')) ?> sensori
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Ultimi eventi -->
        <div class="card">
          <div class="card__header">
            <div>
              <div class="card__title">Ultimi eventi</div>
              <div class="card__subtitle">Attività recente</div>
            </div>
          </div>
          <?php if ($ultimiEventi): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Ora</th><th>Tipo</th><th>Dispositivo</th><th>Stanza</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimiEventi as $ev):
                $isAlert = in_array($ev['id_tipo'], [1,2,3,4,5]);
                $col = $ev['id_tipo'] == 1 ? 'var(--danger)' : ($isAlert ? 'var(--warn)' : 'var(--brand)');
              ?>
              <tr>
                <td style="color:<?= $col ?>;font-family:var(--font-mono)">
                  <?= date('H:i', strtotime($ev['timestamp'])) ?>
                </td>
                <td style="color:<?= $col ?>"><?= htmlspecialchars($ev['tipo']) ?></td>
                <td><?= htmlspecialchars($ev['dispositivo']) ?></td>
                <td><?= htmlspecialchars($ev['stanza']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div style="padding:12px;font-size:12px;color:var(--text-muted)">Nessun evento registrato.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Colonna destra: notifiche recenti + pompa -->
      <div style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <div class="card">
          <div class="card__header">
            <div>
              <div class="card__title">Notifiche recenti</div>
              <div class="card__subtitle">Ultimi alert inviati</div>
            </div>
            <button class="btn btn--xs btn--ghost" onclick="navigate('notifiche')">Tutte</button>
          </div>
          <div class="notif-list">
            <?php if ($notifiche): ?>
            <?php foreach ($notifiche as $n):
              $isAlert = in_array($n['id_tipo'], [1,2,3,4,5]);
              $typeClass = $n['id_tipo'] == 1 ? 'fire' : ($isAlert ? 'warn' : 'ok');
              $chipClass = $n['tipo_notifica'] === 'Telegram' ? 'channel-chip--telegram' : 'channel-chip--mail';
              $chipIcon  = $n['tipo_notifica'] === 'Telegram' ? '✈' : '✉';
            ?>
            <div class="notif-item notif-item--<?= $typeClass ?>">
              <div class="notif-item__icon">
                <?= $n['id_tipo'] == 1 ? '🔥' : ($isAlert ? '⚠️' : '✅') ?>
              </div>
              <div class="notif-item__body">
                <div class="notif-item__title"><?= htmlspecialchars($n['testo']) ?></div>
                <div class="notif-item__desc">
                  <?= htmlspecialchars($n['stanza']) ?> · <?= htmlspecialchars($n['tipo_evento']) ?>
                </div>
                <div class="notif-item__meta">
                  <span class="channel-chip <?= $chipClass ?>">
                    <?= $chipIcon ?> <?= htmlspecialchars($n['tipo_notifica']) ?>
                  </span>
                </div>
              </div>
              <div class="notif-item__time">
                <?= date('H:i', strtotime($n['timestamp_invio'])) ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="padding:10px;font-size:12px;color:var(--text-muted)">Nessuna notifica.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pompa estintrice (solo se incendio attivo) -->
        <?php if ($hasFire): ?>
        <div class="pump-panel">
          <div class="card__header" style="margin-bottom:12px">
            <div class="card__title" style="color:var(--danger)">🚒 Sistema Estinzione</div>
            <span class="badge badge--danger"><span class="badge__dot"></span>ATTIVO</span>
          </div>
          <div class="pump-status">
            <span class="pump-icon">💦</span>
            <div>
              <div style="font-size:13px;font-weight:700;color:var(--danger)">
                Pompa ATTIVA — <?= htmlspecialchars($fireRow['stanza']) ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">
                Attuatore · Irroga acqua nella stanza
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════
       PAGE: NOTIFICHE
  ══════════════════════════════════════ -->
  <section class="page" id="page-notifiche">
    <?php
    // Carica tutte le notifiche (o solo le proprie se Ospite)
    if ($isOwner) {
        $sqlAllNotif = "SELECT n.testo, n.tipo_notifica, n.timestamp_invio,
                               t.descrizione AS tipo_evento, t.id_tipo,
                               d.nome AS dispositivo, s.nome AS stanza,
                               u.nome AS utente_nome, u.cognome AS utente_cognome
                        FROM notifiche n
                        JOIN eventi e      ON e.id_evento = n.id_evento
                        JOIN tipo_evento t ON t.id_tipo = e.id_tipo
                        JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
                        JOIN stanze s      ON s.id_stanza = d.id_stanza
                        JOIN utenti u      ON u.id_utente = n.id_utente
                        ORDER BY n.timestamp_invio DESC";
        $tutteNotifiche = $conn->query($sqlAllNotif)->fetchAll();
    } else {
        $sqlAllNotif = "SELECT n.testo, n.tipo_notifica, n.timestamp_invio,
                               t.descrizione AS tipo_evento, t.id_tipo,
                               d.nome AS dispositivo, s.nome AS stanza
                        FROM notifiche n
                        JOIN eventi e      ON e.id_evento = n.id_evento
                        JOIN tipo_evento t ON t.id_tipo = e.id_tipo
                        JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
                        JOIN stanze s      ON s.id_stanza = d.id_stanza
                        WHERE n.id_utente = :uid
                        ORDER BY n.timestamp_invio DESC";
        $stmt = $conn->prepare($sqlAllNotif);
        $stmt->execute(['uid' => $id_utente]);
        $tutteNotifiche = $stmt->fetchAll();
    }
    ?>
    <div class="section-header">
      <span class="section-title">Notifiche</span>
      <span class="badge badge--info"><?= count($tutteNotifiche) ?> totali</span>
    </div>

    <div class="grid-2">
      <div class="card card--brand">
        <div class="card__header">
          <div><div class="card__title">Telegram</div><div class="card__subtitle">Notifiche push</div></div>
          <span class="badge badge--ok"><span class="badge__dot"></span>Attivo</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono)">
          Chiave: <?= htmlspecialchars($_SESSION['telegram'] ?? 'Non configurata') ?>
        </div>
      </div>
      <div class="card card--brand">
        <div class="card__header">
          <div><div class="card__title">Email</div><div class="card__subtitle">Notifiche via posta</div></div>
          <span class="badge badge--ok"><span class="badge__dot"></span>Attivo</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono)">
          <?= htmlspecialchars($_SESSION['email'] ?? '') ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__header">
        <div class="card__title">Tutte le notifiche</div>
        <div class="card__subtitle">
          <?= $isOwner ? 'Tutti gli utenti' : 'Le tue notifiche' ?>
        </div>
      </div>
      <div class="notif-list">
        <?php if ($tutteNotifiche): ?>
        <?php foreach ($tutteNotifiche as $n):
          $isAlert   = in_array($n['id_tipo'], [1,2,3,4,5]);
          $typeClass = $n['id_tipo'] == 1 ? 'fire' : ($isAlert ? 'warn' : 'ok');
          $chipClass = $n['tipo_notifica'] === 'Telegram' ? 'channel-chip--telegram' : 'channel-chip--mail';
          $chipIcon  = $n['tipo_notifica'] === 'Telegram' ? '✈' : '✉';
        ?>
        <div class="notif-item notif-item--<?= $typeClass ?>">
          <div class="notif-item__icon">
            <?= $n['id_tipo'] == 1 ? '🔥' : ($isAlert ? '⚠️' : '✅') ?>
          </div>
          <div class="notif-item__body">
            <div class="notif-item__title"><?= htmlspecialchars($n['testo']) ?></div>
            <div class="notif-item__desc">
              <?= htmlspecialchars($n['dispositivo']) ?> · <?= htmlspecialchars($n['stanza']) ?>
              <?php if (isset($n['utente_nome'])): ?>
              · <?= htmlspecialchars($n['utente_nome'] . ' ' . $n['utente_cognome']) ?>
              <?php endif; ?>
            </div>
            <div class="notif-item__meta">
              <span class="channel-chip <?= $chipClass ?>">
                <?= $chipIcon ?> <?= htmlspecialchars($n['tipo_notifica']) ?>
              </span>
            </div>
          </div>
          <div class="notif-item__time">
            <?= date('d/m H:i', strtotime($n['timestamp_invio'])) ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="padding:16px;text-align:center;color:var(--text-muted)">
          Nessuna notifica trovata.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

</main>
</div>

<!-- FIRE MODAL -->
<?php if ($hasFire): ?>
<div class="modal-overlay" id="fire-modal">
  <div class="modal modal--danger">
    <span class="modal__icon">🔥</span>
    <h2 class="modal__title" style="color:var(--danger)">INCENDIO RILEVATO</h2>
    <p class="modal__desc">
      <?= htmlspecialchars($fireRow['stanza']) ?>
      · <?= date('H:i:s', strtotime($fireRow['timestamp'])) ?>
    </p>
    <div class="pump-status" style="justify-content:center;margin-bottom:16px">
      <span class="pump-icon">💦</span>
      <span style="color:var(--info);font-weight:700">Sistema di estinzione attivato</span>
    </div>
    <div class="modal__actions">
      <button class="btn btn--danger"
              onclick="document.getElementById('fire-modal').classList.remove('open');navigate('notifiche')">
        ⚠ Vedi notifiche
      </button>
      <button class="btn btn--ghost"
              onclick="document.getElementById('fire-modal').classList.remove('open')">
        Visto
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ── Navigazione SPA ──────────────────────────────────────────
const PAGE_META = {
    overview:   ['Panoramica',  'Home · Tutti i sensori'],
    notifiche:  ['Notifiche',   'Alert e messaggi'],
};

function navigate(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav__item[data-page]').forEach(n => n.classList.remove('active'));

    const pg = document.getElementById('page-' + pageId);
    if (pg) pg.classList.add('active');

    document.querySelectorAll('[data-page="' + pageId + '"]').forEach(el => el.classList.add('active'));

    const meta = PAGE_META[pageId] || ['Dashboard', ''];
    document.getElementById('topbar-title').textContent = meta[0];
    document.getElementById('topbar-sub').textContent   = meta[1];
}

// Sidebar toggle
const sidebarToggle = document.getElementById('sidebar-toggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('main-area').classList.toggle('expanded');
    });
}

// Nav click
document.querySelectorAll('.nav__item[data-page]').forEach(function(item) {
    item.addEventListener('click', function() {
        navigate(this.dataset.page);
    });
});

// Apri modal incendio automaticamente
<?php if ($hasFire): ?>
setTimeout(function() {
    document.getElementById('fire-modal').classList.add('open');
}, 700);
<?php endif; ?>
</script>
</body>
</html>