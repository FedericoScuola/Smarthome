<?php
// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}

// ── Include librerie condivise ────────────────────────────────
require_once '../lib/conn.php';
require_once '../lib/helpers.php';

// Dati utente dalla sessione
$id_utente = $_SESSION['utente'];
$isOwner   = ($_SESSION['ruolo'] === 'Proprietario');

// ── Carica dati principali ────────────────────────────────────
$stanze  = caricaStanze($conn);
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Calcola statistiche riassuntive ──────────────────────────
$nFire = 0;
$nWarn = 0;
$temps = [];
$hums  = [];

foreach ($stanze as $s) {
    if ($s['status'] === 'fire') $nFire++;
    if ($s['status'] === 'warn') $nWarn++;
    foreach ($s['dispositivi'] as $d) {
        if ($d['valore'] === null) continue;
        if ($d['unita_misura'] === '°C') $temps[] = (float)$d['valore'];
        if ($d['unita_misura'] === '%')  $hums[]  = (float)$d['valore'];
    }
}

// Media temperatura e umidità (mostra '—' se non ci sono sensori)
$avgTemp = count($temps) ? number_format(array_sum($temps) / count($temps), 1) : '—';
$avgHum  = count($hums)  ? round(array_sum($hums) / count($hums))              : '—';

// ── Ultimi 5 eventi ───────────────────────────────────────────
$ultimiEventi = $conn->query(
    "SELECT e.timestamp, t.descrizione AS tipo, t.id_tipo,
            d.nome AS dispositivo, s.nome AS stanza
     FROM eventi e
     JOIN tipo_evento t ON t.id_tipo = e.id_tipo
     JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
     JOIN stanze s      ON s.id_stanza = d.id_stanza
     ORDER BY e.timestamp DESC LIMIT 5"
)->fetchAll();

// ── Ultime 4 notifiche (per il riquadro laterale) ────────────
$notifiche = $conn->query(
    "SELECT n.testo, n.tipo_notifica, n.timestamp_invio,
            t.descrizione AS tipo_evento, t.id_tipo,
            s.nome AS stanza
     FROM notifiche n
     JOIN eventi e      ON e.id_evento = n.id_evento
     JOIN tipo_evento t ON t.id_tipo = e.id_tipo
     JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
     JOIN stanze s      ON s.id_stanza = d.id_stanza
     ORDER BY n.timestamp_invio DESC LIMIT 4"
)->fetchAll();

// ── Tutte le notifiche (per la sezione Notifiche) ────────────
if ($isOwner) {
    // Il proprietario vede le notifiche di tutti gli utenti
    $tutteNotifiche = $conn->query(
        "SELECT n.testo, n.tipo_notifica, n.timestamp_invio,
                t.descrizione AS tipo_evento, t.id_tipo,
                d.nome AS dispositivo, s.nome AS stanza,
                u.nome AS utente_nome, u.cognome AS utente_cognome
         FROM notifiche n
         JOIN eventi e      ON e.id_evento = n.id_evento
         JOIN tipo_evento t ON t.id_tipo = e.id_tipo
         JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
         JOIN stanze s      ON s.id_stanza = d.id_stanza
         JOIN utenti u      ON u.id_utente = n.id_utente
         ORDER BY n.timestamp_invio DESC"
    )->fetchAll();
} else {
    // L'ospite vede solo le proprie notifiche
    $stmt = $conn->prepare(
        "SELECT n.testo, n.tipo_notifica, n.timestamp_invio,
                t.descrizione AS tipo_evento, t.id_tipo,
                d.nome AS dispositivo, s.nome AS stanza
         FROM notifiche n
         JOIN eventi e      ON e.id_evento = n.id_evento
         JOIN tipo_evento t ON t.id_tipo = e.id_tipo
         JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
         JOIN stanze s      ON s.id_stanza = d.id_stanza
         WHERE n.id_utente = :uid
         ORDER BY n.timestamp_invio DESC"
    );
    $stmt->execute(['uid' => $id_utente]);
    $tutteNotifiche = $stmt->fetchAll();
}

// ── Variabile per la sidebar (indica la voce attiva) ─────────
$paginaAttiva = 'home';
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

<?php require '../lib/sidebar.php'; ?>

<!-- ══════════════════════════════════════
     CONTENUTO PRINCIPALE
══════════════════════════════════════ -->
<main class="app-main" id="main-area">

  <!-- Barra superiore con titolo e campanella notifiche -->
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
       SEZIONE: PANORAMICA
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

    <!-- Riquadri statistiche -->
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

    <!-- Griglia a due colonne: stanze a sinistra, notifiche a destra -->
    <div class="grid-main">

      <!-- COLONNA SINISTRA: card stanze + tabella eventi -->
      <div style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <div class="section-header">
          <span class="section-title">Stanze</span>
          <a href="../stanze/index.php" class="btn btn--sm btn--ghost">Vedi tutte →</a>
        </div>

        <div class="grid-2">
          <?php foreach ($stanze as $s):
            $fireClass = ($s['status'] === 'fire') ? 'room-card--fire' : '';
            $warnStyle = ($s['status'] === 'warn') ? 'style="border-color:rgba(240,160,48,0.35)"' : '';
          ?>
          <div class="card room-card <?= $fireClass ?>" <?= $warnStyle ?>>
            <div class="room-card__header">
              <div class="room-card__name">🏠 <?= htmlspecialchars($s['nome']) ?></div>
              <?= badgeStatus($s['status']) ?>
            </div>

            <div class="sensor-grid" style="grid-template-columns:repeat(auto-fill,minmax(70px,1fr))">
              <?php foreach ($s['dispositivi'] as $d):
                // Mostra solo i sensori con un valore disponibile
                if ($d['tipo'] !== 'Sensore' || $d['valore'] === null) continue;
                $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
                $col   = $fuori ? ($s['status'] === 'fire' ? 'var(--danger)' : 'var(--warn)') : 'inherit';
                $cls   = $fuori ? ($s['status'] === 'fire' ? 'sensor-pill--danger' : 'sensor-pill--warn') : '';
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
              <?= count(array_filter($s['dispositivi'], function($d) { return $d['tipo'] === 'Sensore'; })) ?> sensori
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Tabella ultimi eventi -->
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
              <tr><th>Ora</th><th>Tipo</th><th>Dispositivo</th><th>Stanza</th></tr>
            </thead>
            <tbody>
              <?php foreach ($ultimiEventi as $ev):
                $isAlert = in_array($ev['id_tipo'], [1,2,3,4,5]);
                $col = ($ev['id_tipo'] == 1) ? 'var(--danger)' : ($isAlert ? 'var(--warn)' : 'var(--brand)');
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

      </div><!-- fine colonna sinistra -->

      <!-- COLONNA DESTRA: notifiche recenti + pompa -->
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
              $isAlert   = in_array($n['id_tipo'], [1,2,3,4,5]);
              $typeClass = ($n['id_tipo'] == 1) ? 'fire' : ($isAlert ? 'warn' : 'ok');
              $chipClass = ($n['tipo_notifica'] === 'Telegram') ? 'channel-chip--telegram' : 'channel-chip--mail';
              $chipIcon  = ($n['tipo_notifica'] === 'Telegram') ? '✈' : '✉';
            ?>
            <div class="notif-item notif-item--<?= $typeClass ?>">
              <div class="notif-item__icon">
                <?= ($n['id_tipo'] == 1) ? '🔥' : ($isAlert ? '⚠️' : '✅') ?>
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

        <!-- Pannello pompa (visibile solo se c'è un incendio attivo) -->
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

      </div><!-- fine colonna destra -->

    </div><!-- fine grid-main -->
  </section>

  <!-- ══════════════════════════════════════
       SEZIONE: NOTIFICHE
  ══════════════════════════════════════ -->
  <section class="page" id="page-notifiche">

    <div class="section-header">
      <span class="section-title">Notifiche</span>
      <span class="badge badge--info"><?= count($tutteNotifiche) ?> totali</span>
    </div>

    <!-- Info canali di notifica dell'utente -->
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

    <!-- Elenco completo notifiche -->
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
          $typeClass = ($n['id_tipo'] == 1) ? 'fire' : ($isAlert ? 'warn' : 'ok');
          $chipClass = ($n['tipo_notifica'] === 'Telegram') ? 'channel-chip--telegram' : 'channel-chip--mail';
          $chipIcon  = ($n['tipo_notifica'] === 'Telegram') ? '✈' : '✉';
        ?>
        <div class="notif-item notif-item--<?= $typeClass ?>">
          <div class="notif-item__icon">
            <?= ($n['id_tipo'] == 1) ? '🔥' : ($isAlert ? '⚠️' : '✅') ?>
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

<!-- Modal incendio (appare in automatico se c'è un incendio attivo) -->
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
              onclick="document.getElementById('fire-modal').classList.remove('open'); navigate('notifiche')">
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
// Titoli delle sezioni: usati per aggiornare la topbar quando si cambia pagina
const PAGE_META = {
    overview:  ['Panoramica', 'Home · Tutti i sensori'],
    notifiche: ['Notifiche',  'Alert e messaggi'],
};

// Cambia la sezione visibile (funziona come una SPA a pagina singola)
function navigate(pageId) {
    // Nasconde tutte le sezioni e deseleziona i link della nav
    document.querySelectorAll('.page').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.nav__item[data-page]').forEach(function(n) { n.classList.remove('active'); });

    // Mostra la sezione richiesta
    var pagina = document.getElementById('page-' + pageId);
    if (pagina) pagina.classList.add('active');

    // Evidenzia la voce di menu corrispondente
    document.querySelectorAll('[data-page="' + pageId + '"]').forEach(function(el) {
        el.classList.add('active');
    });

    // Aggiorna il titolo nella topbar
    var meta = PAGE_META[pageId] || ['Dashboard', ''];
    document.getElementById('topbar-title').textContent = meta[0];
    document.getElementById('topbar-sub').textContent   = meta[1];
}

// Apre il modal di incendio automaticamente dopo 0.7 secondi
<?php if ($hasFire): ?>
setTimeout(function() {
    document.getElementById('fire-modal').classList.add('open');
}, 700);
<?php endif; ?>
</script>
</body>
</html>
