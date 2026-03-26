<?php
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}

include_once '../lib/conn.php';

$nome    = $_SESSION['nome'];
$cognome = $_SESSION['cognome'];
$ruolo   = $_SESSION['ruolo'];

$id_dispositivo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_dispositivo <= 0) {
    header("Location: index.php");
    exit;
}

// Dati dispositivo
$sqlDisp = "SELECT d.*, s.nome AS stanza_nome
            FROM dispositivi d
            JOIN stanze s ON s.id_stanza = d.id_stanza
            WHERE d.id_dispositivo = :id LIMIT 1";
$stmtD = $conn->prepare($sqlDisp);
$stmtD->execute(['id' => $id_dispositivo]);
$dispositivo = $stmtD->fetch();

if (!$dispositivo) {
    header("Location: index.php");
    exit;
}

// Filtro periodo (giorni)
$giorni = in_array((int)($_GET['giorni'] ?? 1), [1, 7, 30]) ? (int)$_GET['giorni'] : 1;

// Misurazioni nel periodo
$sqlMis = "SELECT id_misurazione, valore, timestamp
           FROM misurazioni
           WHERE id_dispositivo = :id
             AND timestamp >= NOW() - INTERVAL :g DAY
           ORDER BY timestamp DESC";
$stmtM = $conn->prepare($sqlMis);
$stmtM->bindValue(':id', $id_dispositivo, PDO::PARAM_INT);
$stmtM->bindValue(':g',  $giorni,         PDO::PARAM_INT);
$stmtM->execute();
$misurazioni = $stmtM->fetchAll();

// Statistiche
$valori    = array_column($misurazioni, 'valore');
$valoriNum = array_map('floatval', $valori);
$statMin   = count($valoriNum) ? min($valoriNum)                                    : null;
$statMax   = count($valoriNum) ? max($valoriNum)                                    : null;
$statAvg   = count($valoriNum) ? round(array_sum($valoriNum)/count($valoriNum), 2)  : null;
$nFuori    = 0;
$sMin = $dispositivo['soglia_minima']  !== null ? (float)$dispositivo['soglia_minima']  : null;
$sMax = $dispositivo['soglia_massima'] !== null ? (float)$dispositivo['soglia_massima'] : null;
foreach ($valoriNum as $v) {
    if (($sMin !== null && $v < $sMin) || ($sMax !== null && $v > $sMax)) $nFuori++;
}

// Ultime 20 misurazioni per il bar chart (ordine ASC)
$chartData = array_reverse(array_slice($misurazioni, 0, 20));

function sensorIcon(string $unit): string {
    if ($unit === '°C')  return '🌡';
    if ($unit === '%')   return '💧';
    if ($unit === 'AQI') return '🌬';
    return '📡';
}
function iniziali(string $n, string $c): string {
    return strtoupper(mb_substr($n,0,1) . mb_substr($c,0,1));
}
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

<!-- SIDEBAR -->
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
    <a href="../home/index.php" class="nav__item">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
      <span class="nav__label">Panoramica</span>
    </a>
    <div class="nav__section-label">Stanze</div>
    <?php if ($ruolo === 'Proprietario'): ?>
    <a href="../stanze/crea.php" class="nav__item" title="Crea stanza">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea stanza</span>
    </a>
    <?php endif; ?>

    <div class="nav__section-label">Dispositivi</div>
    <a href="index.php" class="nav__item active">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
      <span class="nav__label">Dispositivi</span>
    </a>
    <?php if ($ruolo === 'Proprietario'): ?>
    <a href="crea.php" class="nav__item">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea dispositivo</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar__footer">
    <a href="../lib/logout.php" class="nav__item nav__item--danger">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      <span class="nav__label">Esci</span>
    </a>
    <div class="sidebar__user">
      <div class="avatar" style="background:linear-gradient(135deg,#00d4b4,#0088aa)">
        <?= iniziali($nome, $cognome) ?>
      </div>
      <div class="sidebar__user-info">
        <div class="sidebar__user-name"><?= htmlspecialchars("$nome $cognome") ?></div>
        <div class="sidebar__user-role"><?= htmlspecialchars($ruolo) ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
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

    <!-- Filtro periodo -->
    <div style="display:flex;align-items:center;gap:var(--sp-3)">
      <span style="font-size:12px;color:var(--text-muted)">Periodo:</span>
      <div class="tabs">
        <a href="?id=<?= $id_dispositivo ?>&giorni=1"
           class="tab <?= $giorni===1?'active':'' ?>">Oggi</a>
        <a href="?id=<?= $id_dispositivo ?>&giorni=7"
           class="tab <?= $giorni===7?'active':'' ?>">7 giorni</a>
        <a href="?id=<?= $id_dispositivo ?>&giorni=30"
           class="tab <?= $giorni===30?'active':'' ?>">30 giorni</a>
      </div>
    </div>

    <!-- Stat Cards -->
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

    <!-- Info dispositivo + bar chart -->
    <div class="grid-2">

      <!-- Info -->
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

      <!-- Bar chart ultimi valori -->
      <div class="card">
        <div class="card__header">
          <div>
            <div class="card__title">Grafico ultime misurazioni</div>
            <div class="card__subtitle">Ultime <?= count($chartData) ?> letture</div>
          </div>
        </div>
        <?php if ($chartData):
          $maxVal = max(array_map(fn($r) => (float)$r['valore'], $chartData));
          $maxVal = $maxVal > 0 ? $maxVal : 1;
        ?>
        <div class="bar-chart" style="margin-top:8px">
          <?php foreach ($chartData as $m):
            $v    = (float)$m['valore'];
            $pct  = round(($v / $maxVal) * 100);
            $fuori = ($sMin !== null && $v < $sMin) || ($sMax !== null && $v > $sMax);
            $cls  = $fuori ? 'bar-chart__bar--danger' : '';
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

    <!-- Tabella completa misurazioni -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Storico misurazioni</div>
          <div class="card__subtitle">
            <?= count($misurazioni) ?> rilevazioni ·
            ultimi <?= $giorni === 1 ? 'oggi' : "$giorni giorni" ?>
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
          <?php foreach ($misurazioni as $i => $m):
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

<script>
document.getElementById('sidebar-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-area').classList.toggle('expanded');
});
</script>
</body>
</html>
