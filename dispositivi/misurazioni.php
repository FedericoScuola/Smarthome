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

// Legge l'ID del dispositivo dall'URL (es: misurazioni.php?id=5)
$id_dispositivo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_dispositivo <= 0) {
    header("Location: index.php");
    exit;
}

// Recupera i dati del dispositivo (con nome stanza)
$stmt = $conn->prepare(
    "SELECT d.*, s.nome AS stanza_nome
     FROM dispositivi d
     JOIN stanze s ON s.id_stanza = d.id_stanza
     WHERE d.id_dispositivo = :id LIMIT 1"
);
$stmt->execute(['id' => $id_dispositivo]);
$dispositivo = $stmt->fetch();

// Se il dispositivo non esiste, torna alla lista
if (!$dispositivo) {
    header("Location: index.php");
    exit;
}

// Filtro periodo: 1 giorno, 7 giorni o 30 giorni (default: oggi)
$giorniValidi = [1, 7, 30];
$giorni = in_array((int)($_GET['giorni'] ?? 1), $giorniValidi) ? (int)$_GET['giorni'] : 1;

// Carica le misurazioni del periodo selezionato
$stmtM = $conn->prepare(
    "SELECT id_misurazione, valore, timestamp
     FROM misurazioni
     WHERE id_dispositivo = :id
       AND timestamp >= NOW() - INTERVAL :g DAY
     ORDER BY timestamp DESC"
);
$stmtM->bindValue(':id', $id_dispositivo, PDO::PARAM_INT);
$stmtM->bindValue(':g',  $giorni,         PDO::PARAM_INT);
$stmtM->execute();
$misurazioni = $stmtM->fetchAll();

// Calcola statistiche (min, max, media, fuori soglia)
$valoriNum = array_map('floatval', array_column($misurazioni, 'valore'));
$statMin   = count($valoriNum) ? min($valoriNum)                                   : null;
$statMax   = count($valoriNum) ? max($valoriNum)                                   : null;
$statAvg   = count($valoriNum) ? round(array_sum($valoriNum) / count($valoriNum), 2) : null;

// Soglie del dispositivo (convertite in float o null)
$sMin = $dispositivo['soglia_minima']  !== null ? (float)$dispositivo['soglia_minima']  : null;
$sMax = $dispositivo['soglia_massima'] !== null ? (float)$dispositivo['soglia_massima'] : null;

// Conta quante misurazioni sono fuori soglia
$nFuori = 0;
foreach ($valoriNum as $v) {
    if (($sMin !== null && $v < $sMin) || ($sMax !== null && $v > $sMax)) {
        $nFuori++;
    }
}

// Prende le ultime 20 misurazioni in ordine crescente per il grafico a barre
$chartData = array_reverse(array_slice($misurazioni, 0, 20));

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
  <title>SmartHome — Misurazioni <?= htmlspecialchars($dispositivo['nome']) ?></title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">
        <?= sensorIcon($dispositivo['unita_misura'] ?? '') ?>
        <?= htmlspecialchars($dispositivo['nome']) ?>
      </div>
      <div class="topbar__subtitle">
        <?= htmlspecialchars($dispositivo['stanza_nome']) ?> · Misurazioni
      </div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="index.php" class="btn btn--ghost btn--sm">← Tutti i dispositivi</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);display:flex;flex-direction:column;gap:var(--sp-5)">

    <!-- Selettore periodo (link che ricaricano la pagina con giorni diversi) -->
    <div style="display:flex;align-items:center;gap:var(--sp-3)">
      <span style="font-size:12px;color:var(--text-muted)">Periodo:</span>
      <div class="tabs">
        <a href="?id=<?= $id_dispositivo ?>&giorni=1"
           class="tab <?= $giorni === 1  ? 'active' : '' ?>">Oggi</a>
        <a href="?id=<?= $id_dispositivo ?>&giorni=7"
           class="tab <?= $giorni === 7  ? 'active' : '' ?>">7 giorni</a>
        <a href="?id=<?= $id_dispositivo ?>&giorni=30"
           class="tab <?= $giorni === 30 ? 'active' : '' ?>">30 giorni</a>
      </div>
    </div>

    <!-- Riquadri statistiche del periodo -->
    <div class="grid-4">
      <div class="card stat-card">
        <span class="stat-card__label">📊 Misurazioni</span>
        <div class="stat-card__value">
          <?= count($misurazioni) ?><span class="stat-card__unit"> tot.</span>
        </div>
        <div class="stat-card__trend">Nel periodo selezionato</div>
      </div>
      <div class="card stat-card">
        <span class="stat-card__label">📉 Minimo</span>
        <div class="stat-card__value">
          <?= $statMin !== null ? $statMin : '—' ?>
          <span class="stat-card__unit"> <?= htmlspecialchars($dispositivo['unita_misura'] ?? '') ?></span>
        </div>
        <div class="stat-card__trend">Valore più basso</div>
      </div>
      <div class="card stat-card">
        <span class="stat-card__label">📈 Massimo</span>
        <div class="stat-card__value">
          <?= $statMax !== null ? $statMax : '—' ?>
          <span class="stat-card__unit"> <?= htmlspecialchars($dispositivo['unita_misura'] ?? '') ?></span>
        </div>
        <div class="stat-card__trend">Valore più alto</div>
      </div>
      <div class="card stat-card <?= $nFuori > 0 ? 'card--warn' : '' ?>">
        <span class="stat-card__label">⚠ Fuori soglia</span>
        <div class="stat-card__value <?= $nFuori > 0 ? 'stat-card__value--warn' : '' ?>">
          <?= $nFuori ?><span class="stat-card__unit"> volte</span>
        </div>
        <div class="stat-card__trend">
          Media: <?= $statAvg !== null ? $statAvg : '—' ?>
          <?= htmlspecialchars($dispositivo['unita_misura'] ?? '') ?>
        </div>
      </div>
    </div>

    <!-- Info dispositivo + grafico a barre affiancati -->
    <div class="grid-2">

      <!-- Scheda informazioni dispositivo -->
      <div class="card">
        <div class="card__header">
          <div><div class="card__title">Informazioni dispositivo</div></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:12px;font-family:var(--font-mono)">
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Nome</span>
            <span><?= htmlspecialchars($dispositivo['nome']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Tipo</span>
            <span><?= htmlspecialchars($dispositivo['tipo']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Stanza</span>
            <span><?= htmlspecialchars($dispositivo['stanza_nome']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Unità</span>
            <span><?= htmlspecialchars($dispositivo['unita_misura'] ?? '—') ?></span>
          </div>
          <div style="height:1px;background:var(--border-subtle)"></div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Soglia min</span>
            <span style="color:var(--brand)"><?= $dispositivo['soglia_minima'] ?? '—' ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Soglia max</span>
            <span style="color:var(--warn)"><?= $dispositivo['soglia_massima'] ?? '—' ?></span>
          </div>
          <div style="height:1px;background:var(--border-subtle)"></div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Installato il</span>
            <span><?= date('d/m/Y', strtotime($dispositivo['data_installazione'])) ?></span>
          </div>
        </div>
      </div>

      <!-- Grafico a barre delle ultime misurazioni -->
      <div class="card">
        <div class="card__header">
          <div>
            <div class="card__title">Grafico ultime misurazioni</div>
            <div class="card__subtitle">Ultime <?= count($chartData) ?> letture</div>
          </div>
        </div>
        <?php if ($chartData):
          // Trova il valore massimo per scalare le barre (evita divisione per zero)
          $maxVal = max(array_map(function($r) { return (float)$r['valore']; }, $chartData));
          $maxVal = $maxVal > 0 ? $maxVal : 1;
        ?>
        <div class="bar-chart" style="margin-top:8px">
          <?php foreach ($chartData as $m):
            $v     = (float)$m['valore'];
            $pct   = round(($v / $maxVal) * 100);
            $fuori = ($sMin !== null && $v < $sMin) || ($sMax !== null && $v > $sMax);
            $cls   = $fuori ? 'bar-chart__bar--danger' : '';
          ?>
          <div class="bar-chart__bar <?= $cls ?>"
               style="height:<?= $pct ?>%"
               title="<?= $v . ' ' . htmlspecialchars($dispositivo['unita_misura'] ?? '') . ' — ' . date('d/m H:i', strtotime($m['timestamp'])) ?>">
          </div>
          <?php endforeach; ?>
        </div>
        <div class="bar-chart__axis">
          <span><?= date('H:i', strtotime($chartData[0]['timestamp'])) ?></span>
          <span><?= date('H:i', strtotime(end($chartData)['timestamp'])) ?></span>
        </div>
        <div style="margin-top:10px;font-size:10px;color:var(--text-muted);font-family:var(--font-mono)">
          <?php if ($sMin !== null || $sMax !== null): ?>
          Soglia: <?= $sMin ?? '—' ?> – <?= $sMax ?? '—' ?>
          <?= htmlspecialchars($dispositivo['unita_misura'] ?? '') ?> ·
          <span style="color:var(--danger)">Rosso = fuori soglia</span>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="padding:20px;text-align:center;color:var(--text-muted)">
          Nessun dato nel periodo selezionato.
        </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Tabella con lo storico completo delle misurazioni -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Storico misurazioni</div>
          <div class="card__subtitle">
            <?= count($misurazioni) ?> rilevazioni ·
            <?= $giorni === 1 ? 'oggi' : "ultimi $giorni giorni" ?>
          </div>
        </div>
      </div>

      <?php if ($misurazioni): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Timestamp</th>
            <th>Valore</th>
            <th>Unità</th>
            <th>Stato</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($misurazioni as $m):
            $v     = (float)$m['valore'];
            $fuori = ($sMin !== null && $v < $sMin) || ($sMax !== null && $v > $sMax);
            $col   = $fuori ? 'var(--danger)' : 'var(--brand)';
          ?>
          <tr>
            <td style="font-family:var(--font-mono);color:var(--text-muted)">
              <?= $m['id_misurazione'] ?>
            </td>
            <td style="font-family:var(--font-mono)">
              <?= date('d/m/Y H:i:s', strtotime($m['timestamp'])) ?>
            </td>
            <td style="font-family:var(--font-mono);font-weight:600;color:<?= $col ?>">
              <?= htmlspecialchars($m['valore']) ?>
            </td>
            <td><?= htmlspecialchars($dispositivo['unita_misura'] ?? '—') ?></td>
            <td>
              <span class="badge <?= $fuori ? 'badge--danger' : 'badge--ok' ?>">
                <?= $fuori ? 'Fuori soglia' : 'Normale' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div style="padding:20px;text-align:center;color:var(--text-muted)">
        Nessuna misurazione nel periodo selezionato.
      </div>
      <?php endif; ?>

    </div>

  </div>
</main>
</div>
</body>
</html>
