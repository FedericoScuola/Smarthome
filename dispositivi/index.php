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

// Legge il filtro stanza dall'URL (es: index.php?stanza=3)
$filtroStanza = isset($_GET['stanza']) ? (int)$_GET['stanza'] : 0;

// Tutte le stanze per il menu a tendina del filtro
$stanze = $conn->query("SELECT id_stanza, nome FROM stanze ORDER BY nome")->fetchAll();

// Carica i dispositivi, con o senza filtro stanza
if ($filtroStanza > 0) {
    $sql  = "SELECT d.id_dispositivo, d.nome, d.tipo, d.unita_misura,
                    d.soglia_minima, d.soglia_massima, d.data_installazione,
                    s.nome AS stanza_nome, s.id_stanza,
                    m.valore, m.timestamp AS ultima_lettura
             FROM dispositivi d
             JOIN stanze s ON s.id_stanza = d.id_stanza
             LEFT JOIN misurazioni m ON m.id_misurazione = (
                 SELECT id_misurazione FROM misurazioni
                 WHERE id_dispositivo = d.id_dispositivo
                 ORDER BY timestamp DESC LIMIT 1
             )
             WHERE d.id_stanza = :sid
             ORDER BY d.tipo DESC, d.nome";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['sid' => $filtroStanza]);
} else {
    $sql  = "SELECT d.id_dispositivo, d.nome, d.tipo, d.unita_misura,
                    d.soglia_minima, d.soglia_massima, d.data_installazione,
                    s.nome AS stanza_nome, s.id_stanza,
                    m.valore, m.timestamp AS ultima_lettura
             FROM dispositivi d
             JOIN stanze s ON s.id_stanza = d.id_stanza
             LEFT JOIN misurazioni m ON m.id_misurazione = (
                 SELECT id_misurazione FROM misurazioni
                 WHERE id_dispositivo = d.id_dispositivo
                 ORDER BY timestamp DESC LIMIT 1
             )
             ORDER BY s.nome, d.tipo DESC, d.nome";
    $stmt = $conn->query($sql);
}
$dispositivi = $stmt->fetchAll();

// Incendio attivo (per il badge nella sidebar)
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Variabile per la sidebar ──────────────────────────────────
$paginaAttiva = 'dispositivi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Dispositivi</title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">Dispositivi</div>
      <div class="topbar__subtitle">Sensori e attuatori installati</div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <?php if ($isOwner): ?>
      <a href="crea.php" class="btn btn--primary btn--sm">+ Crea dispositivo</a>
      <?php endif; ?>
    </div>
  </header>

  <div style="padding:var(--sp-6);display:flex;flex-direction:column;gap:var(--sp-5)">

    <!-- Filtro per stanza (si aggiorna da solo al cambio del select) -->
    <div style="display:flex;align-items:center;gap:var(--sp-3)">
      <span style="font-size:12px;color:var(--text-muted)">Filtra per stanza:</span>
      <form method="GET" action="index.php" style="display:flex;gap:var(--sp-2)">
        <select name="stanza" class="input" style="width:180px;padding:6px 10px;font-size:12px"
                onchange="this.form.submit()">
          <option value="0" <?= $filtroStanza === 0 ? 'selected' : '' ?>>Tutte le stanze</option>
          <?php foreach ($stanze as $s): ?>
          <option value="<?= $s['id_stanza'] ?>" <?= $filtroStanza === $s['id_stanza'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nome']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <span style="font-size:12px;color:var(--text-muted)">
        <?= count($dispositivi) ?> dispositivi trovati
      </span>
    </div>

    <!-- Griglia card dispositivi -->
    <?php if ($dispositivi): ?>
    <div class="grid-3">
      <?php foreach ($dispositivi as $d):
        $fuori     = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
        $cardClass = $fuori ? 'card--warn' : '';
      ?>
      <div class="card <?= $cardClass ?>" style="display:flex;flex-direction:column;gap:var(--sp-3)">

        <!-- Intestazione: nome, stanza, tipo -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between">
          <div>
            <div style="font-size:14px;font-weight:700">
              <?= $d['tipo'] === 'Sensore' ? '📡' : '💡' ?>
              <?= htmlspecialchars($d['nome']) ?>
            </div>
            <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono);margin-top:2px">
              <?= htmlspecialchars($d['stanza_nome']) ?>
            </div>
          </div>
          <span class="badge <?= $d['tipo'] === 'Sensore' ? 'badge--info' : 'badge--muted' ?>">
            <?= htmlspecialchars($d['tipo']) ?>
          </span>
        </div>

        <!-- Valore attuale (solo per i sensori) -->
        <?php if ($d['tipo'] === 'Sensore'): ?>
        <div style="background:var(--bg-elevated);border-radius:var(--radius-md);
                    padding:var(--sp-4);text-align:center;
                    <?= $fuori ? 'border:1px solid rgba(240,160,48,0.35)' : '' ?>">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Valore attuale</div>
          <div style="font-family:var(--font-mono);font-size:26px;font-weight:500;
                      color:<?= $fuori ? 'var(--warn)' : 'var(--brand)' ?>">
            <?= $d['valore'] !== null
                ? htmlspecialchars($d['valore']) . htmlspecialchars($d['unita_misura'] ?? '')
                : '—' ?>
          </div>
          <?php if ($fuori): ?>
          <div style="font-size:10px;color:var(--warn);margin-top:4px">⚠ Fuori soglia</div>
          <?php endif; ?>
        </div>

        <!-- Soglie e unità di misura -->
        <div style="display:flex;flex-direction:column;gap:4px;font-size:11px;font-family:var(--font-mono)">
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Soglia min</span>
            <span style="color:var(--brand)"><?= $d['soglia_minima'] ?? '—' ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Soglia max</span>
            <span style="color:var(--warn)"><?= $d['soglia_massima'] ?? '—' ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Unità</span>
            <span><?= htmlspecialchars($d['unita_misura'] ?? '—') ?></span>
          </div>
        </div>
        <?php endif; ?>

        <!-- Date -->
        <div style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono)">
          <?php if ($d['ultima_lettura']): ?>
          Ultima lettura: <?= date('d/m/Y H:i', strtotime($d['ultima_lettura'])) ?>
          <?php else: ?>
          Nessuna misurazione registrata
          <?php endif; ?>
        </div>
        <div style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono)">
          Installato: <?= date('d/m/Y', strtotime($d['data_installazione'])) ?>
        </div>

        <!-- Link allo storico misurazioni (solo sensori) -->
        <?php if ($d['tipo'] === 'Sensore'): ?>
        <a href="misurazioni.php?id=<?= $d['id_dispositivo'] ?>"
           class="btn btn--ghost btn--sm" style="width:100%;text-align:center;margin-top:auto">
          📈 Vedi tutte le misurazioni
        </a>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Stato vuoto -->
    <div class="card" style="padding:40px;text-align:center">
      <div style="font-size:32px;margin-bottom:12px">📡</div>
      <div style="font-size:15px;font-weight:700;margin-bottom:8px">Nessun dispositivo trovato</div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:20px">
        <?= $filtroStanza ? 'Nessun dispositivo in questa stanza.' : 'Non ci sono dispositivi registrati.' ?>
      </div>
      <?php if ($isOwner): ?>
      <a href="crea.php" class="btn btn--primary">+ Crea il primo dispositivo</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</main>
</div>
</body>
</html>
