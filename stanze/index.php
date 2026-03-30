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

$isOwner = ($_SESSION['ruolo'] === 'Proprietario');

// ── Carica dati ───────────────────────────────────────────────
$stanze  = caricaStanze($conn);
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Variabile per la sidebar ──────────────────────────────────
$paginaAttiva = 'stanze';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Stanze</title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">Stanze</div>
      <div class="topbar__subtitle">Dettaglio per stanza</div>
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
      <div class="notif-bell" onclick="window.location.href='../home/index.php'" style="cursor:pointer">
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

  <section class="page active">
    <div class="section-header">
      <span class="section-title">Tutte le Stanze</span>
      <?php if ($isOwner): ?>
      <a href="../stanze/crea.php" class="btn btn--sm btn--ghost">+ Nuova stanza</a>
      <?php endif; ?>
    </div>

    <!-- Banner di avviso per ogni sensore fuori soglia -->
    <?php foreach ($stanze as $s): ?>
    <?php foreach ($s['dispositivi'] as $d): ?>
    <?php if (fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima'])): ?>
    <div class="alert-banner alert-banner--warn" style="font-size:12px">
      <span>
        <?= sensorIcon($d['unita_misura'] ?? '') ?>
        <strong><?= htmlspecialchars($d['nome']) ?></strong>
        in <strong><?= htmlspecialchars($s['nome']) ?></strong>:
        valore <?= htmlspecialchars($d['valore']) ?><?= htmlspecialchars($d['unita_misura'] ?? '') ?>
        (soglia: <?= $d['soglia_minima'] ?? '—' ?> – <?= $d['soglia_massima'] ?? '—' ?>)
      </span>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endforeach; ?>

    <!-- Griglia delle card stanza -->
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

        <!-- Lista sensori con valore e soglie -->
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
          <?php foreach ($s['dispositivi'] as $d):
            if ($d['tipo'] !== 'Sensore') continue;
            $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
            $col   = $fuori ? ($s['status'] === 'fire' ? 'var(--danger)' : 'var(--warn)') : 'var(--brand)';
            $bordo = $fuori ? 'border:1px solid ' . ($s['status'] === 'fire' ? 'rgba(240,64,96,0.3)' : 'rgba(240,160,48,0.3)') : '';
          ?>
          <div style="display:flex;align-items:center;justify-content:space-between;
                      padding:8px 10px;background:var(--bg-elevated);border-radius:8px;<?= $bordo ?>">
            <span style="font-size:12px">
              <?= sensorIcon($d['unita_misura'] ?? '') ?>
              <?= htmlspecialchars($d['nome']) ?>
            </span>
            <div style="text-align:right">
              <div style="font-family:var(--font-mono);font-size:14px;font-weight:600;color:<?= $col ?>">
                <?= $d['valore'] !== null ? htmlspecialchars($d['valore']) . htmlspecialchars($d['unita_misura'] ?? '') : '—' ?>
              </div>
              <?php if ($d['soglia_minima'] !== null || $d['soglia_massima'] !== null): ?>
              <div style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono)">
                soglia: <?= $d['soglia_minima'] ?? '—' ?> – <?= $d['soglia_massima'] ?? '—' ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Attuatori presenti nella stanza -->
        <?php $attuatori = array_filter($s['dispositivi'], function($d) { return $d['tipo'] === 'Attuatore'; }); ?>
        <?php if ($attuatori): ?>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px">Attuatori:</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($attuatori as $a): ?>
          <span class="badge badge--muted">💡 <?= htmlspecialchars($a['nome']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:10px;font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">
          <?= $s['volumetria'] ?> m³ ·
          <a href="../dispositivi/index.php?stanza=<?= $s['id_stanza'] ?>"
             style="color:var(--brand);text-decoration:none">
            Vedi dispositivi →
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </section>

</main>
</div>

<!-- Modal incendio -->
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
              onclick="window.location.href='../home/index.php'">
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
<?php if ($hasFire): ?>
setTimeout(function() {
    document.getElementById('fire-modal').classList.add('open');
}, 700);
<?php endif; ?>
</script>
</body>
</html>
