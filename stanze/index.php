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
$ruolo     = $_SESSION['ruolo'];
$isOwner   = $ruolo === 'Proprietario';
 
// ── Dati stanze con ultima misurazione per ogni sensore ──────
$sqlStanze = "SELECT s.id_stanza, s.nome, s.volumetria FROM stanze s ORDER BY s.id_stanza";
$stanze    = $conn->query($sqlStanze)->fetchAll();
 
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
 
// ── Incendio attivo ───────────────────────────────────────────
$sqlFire = "SELECT s.nome AS stanza, e.timestamp
            FROM eventi e
            JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
            JOIN stanze s      ON s.id_stanza = d.id_stanza
            WHERE e.id_tipo = 1
              AND e.timestamp >= NOW() - INTERVAL 10 MINUTE
            ORDER BY e.timestamp DESC LIMIT 1";
$fireRow = $conn->query($sqlFire)->fetch();
$hasFire = (bool)$fireRow;
 
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
  <title>SmartHome — Stanze</title>
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
 
    <a href="../home/index.php" class="nav__item" title="Panoramica">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
      </span>
      <span class="nav__label">Panoramica</span>
    </a>
 
    <a href="../stanze/index.php" class="nav__item active" title="Stanze">
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
 
    <a href="../home/index.php?page=notifiche" class="nav__item" title="Notifiche">
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
 
      <div class="notif-bell" onclick="window.location.href='../home/index.php?page=notifiche'">
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
 
  <!-- CONTENT -->
  <section class="page active" id="page-stanze">
    <div class="section-header">
      <span class="section-title">Tutte le Stanze</span>
    </div>
 
    <!-- Alert soglie superate -->
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
 
        <!-- Tutti i sensori con dettaglio soglie -->
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
          <?php foreach ($s['dispositivi'] as $d):
            if ($d['tipo'] !== 'Sensore') continue;
            $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
            $col   = $fuori ? ($s['status']==='fire'?'var(--danger)':'var(--warn)') : 'var(--brand)';
          ?>
          <div style="display:flex;align-items:center;justify-content:space-between;
                      padding:8px 10px;background:var(--bg-elevated);border-radius:8px;
                      <?= $fuori ? 'border:1px solid '.($s['status']==='fire'?'rgba(240,64,96,0.3)':'rgba(240,160,48,0.3)') : '' ?>">
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
 
        <!-- Attuatori -->
        <?php $attuatori = array_filter($s['dispositivi'], fn($d) => $d['tipo'] === 'Attuatore'); ?>
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
              onclick="window.location.href='../home/index.php?page=notifiche'">
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
// Sidebar toggle
const sidebarToggle = document.getElementById('sidebar-toggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('main-area').classList.toggle('expanded');
    });
}
 
<?php if ($hasFire): ?>
setTimeout(function() {
    document.getElementById('fire-modal').classList.add('open');
}, 700);
<?php endif; ?>
</script>
</body>
</html>
 