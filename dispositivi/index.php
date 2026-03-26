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
$isOwner = $ruolo === 'Proprietario';

// Filtro per stanza (opzionale, passato via GET)
$filtroStanza = isset($_GET['stanza']) ? (int)$_GET['stanza'] : 0;

// Tutte le stanze (per il filtro select)
$stanze = $conn->query("SELECT id_stanza, nome FROM stanze ORDER BY nome")->fetchAll();

// Dispositivi con ultima misurazione
if ($filtroStanza > 0) {
    $sql = "SELECT d.id_dispositivo, d.nome, d.tipo, d.unita_misura,
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
    $sql = "SELECT d.id_dispositivo, d.nome, d.tipo, d.unita_misura,
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

function sensorIcon(string $unit): string {
    if ($unit === '°C')  return '🌡';
    if ($unit === '%')   return '💧';
    if ($unit === 'AQI') return '🌬';
    return '📡';
}
function fuoriSoglia(mixed $v, mixed $min, mixed $max): bool {
    if ($v === null) return false;
    $v = (float)$v;
    return ($min !== null && $v < (float)$min) || ($max !== null && $v > (float)$max);
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
  <title>SmartHome — Dispositivi</title>
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
    <a href="../home/index.php" class="nav__item" title="Panoramica">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
      <span class="nav__label">Panoramica</span>
    </a>
    <a href="../home/index.php?page=stanze" class="nav__item" title="Stanze">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
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
    <a href="../dispositivi/index.php" class="nav__item active" title="Tutti i dispositivi">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
      <span class="nav__label">Dispositivi</span>
    </a>
    <?php if ($isOwner): ?>
    <a href="../dispositivi/crea.php" class="nav__item" title="Crea dispositivo">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea dispositivo</span>
    </a>
    <?php endif; ?>
  </nav>

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

    <a href="../home/index.php?page=notifiche" class="nav__item" title="Notifiche">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
      </span>
      <span class="nav__label">Notifiche</span>
    </a>

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

    <!-- Filtro stanza -->
    <div style="display:flex;align-items:center;gap:var(--sp-3)">
      <span style="font-size:12px;color:var(--text-muted)">Filtra per stanza:</span>
      <form method="GET" action="index.php" style="display:flex;gap:var(--sp-2)">
        <select name="stanza" class="input" style="width:180px;padding:6px 10px;font-size:12px"
                onchange="this.form.submit()">
          <option value="0" <?= $filtroStanza===0?'selected':'' ?>>Tutte le stanze</option>
          <?php foreach ($stanze as $s): ?>
          <option value="<?= $s['id_stanza'] ?>"
                  <?= $filtroStanza===$s['id_stanza']?'selected':'' ?>>
            <?= htmlspecialchars($s['nome']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <span style="font-size:12px;color:var(--text-muted)">
        <?= count($dispositivi) ?> dispositivi trovati
      </span>
    </div>

    <!-- Griglia dispositivi -->
    <?php if ($dispositivi): ?>
    <div class="grid-3">
      <?php foreach ($dispositivi as $d):
        $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
        $cardClass = $fuori ? 'card--warn' : '';
      ?>
      <div class="card <?= $cardClass ?>" style="display:flex;flex-direction:column;gap:var(--sp-3)">

        <!-- Intestazione card -->
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
          <span class="badge <?= $d['tipo']==='Sensore'?'badge--info':'badge--muted' ?>">
            <?= htmlspecialchars($d['tipo']) ?>
          </span>
        </div>

        <!-- Valore attuale (solo sensori) -->
        <?php if ($d['tipo'] === 'Sensore'): ?>
        <div style="background:var(--bg-elevated);border-radius:var(--radius-md);
                    padding:var(--sp-4);text-align:center;
                    <?= $fuori ? 'border:1px solid rgba(240,160,48,0.35)' : '' ?>">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Valore attuale</div>
          <div style="font-family:var(--font-mono);font-size:26px;font-weight:500;
                      color:<?= $fuori?'var(--warn)':'var(--brand)' ?>">
            <?= $d['valore'] !== null
                ? htmlspecialchars($d['valore']) . htmlspecialchars($d['unita_misura'] ?? '')
                : '—' ?>
          </div>
          <?php if ($fuori): ?>
          <div style="font-size:10px;color:var(--warn);margin-top:4px">⚠ Fuori soglia</div>
          <?php endif; ?>
        </div>

        <!-- Soglie -->
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

        <!-- Ultima lettura e link misurazioni -->
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

<script>
document.getElementById('sidebar-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-area').classList.toggle('expanded');
});
</script>
</body>
</html>
