<?php
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}
if ($_SESSION['ruolo'] !== 'Proprietario') {
    header("Location: ../home/index.php");
    exit;
}

include_once '../lib/conn.php';

$nome    = $_SESSION['nome'];
$cognome = $_SESSION['cognome'];
$ruolo   = $_SESSION['ruolo'];

// Tutti gli utenti
$utenti = $conn->query(
    "SELECT id_utente, nome, cognome, email, ruolo, chiave_telegram, immagine_profilo
     FROM utenti
     ORDER BY ruolo DESC, cognome ASC"
)->fetchAll();

$nProprietari = count(array_filter($utenti, fn($u) => $u['ruolo'] === 'Proprietario'));
$nOspiti      = count(array_filter($utenti, fn($u) => $u['ruolo'] === 'Ospite'));

function iniziali(string $n, string $c): string {
    return strtoupper(mb_substr($n,0,1) . mb_substr($c,0,1));
}

$AVATAR_COLORS = [
    'linear-gradient(135deg,#00d4b4,#0088aa)',
    'linear-gradient(135deg,#f0a030,#f04060)',
    'linear-gradient(135deg,#7c6ff7,#3090f0)',
    'linear-gradient(135deg,#30c878,#0088aa)',
    'linear-gradient(135deg,#4a5878,#1a2130)',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Utenti</title>
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

    <div class="nav__section-label">Stanze</div>
    <a href="../stanze/crea.php" class="nav__item" title="Crea stanza">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea stanza</span>
    </a>

    <div class="nav__section-label">Dispositivi</div>
    <a href="../dispositivi/index.php" class="nav__item" title="Dispositivi">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
      <span class="nav__label">Dispositivi</span>
    </a>
    <a href="../dispositivi/crea.php" class="nav__item" title="Crea dispositivo">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea dispositivo</span>
    </a>

    <div class="nav__section-label">Sistema</div>
    <a href="index.php" class="nav__item active" title="Utenti">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
      <span class="nav__label">Utenti</span>
    </a>
    <a href="crea.php" class="nav__item" title="Crea utente">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea utente</span>
    </a>

  </nav>

  <div class="sidebar__footer">
    <a href="../lib/logout.php" class="nav__item nav__item--danger" title="Esci">
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
      <div class="topbar__title">Utenti</div>
      <div class="topbar__subtitle">Gestione accessi e privilegi</div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="crea.php" class="btn btn--primary btn--sm">+ Crea utente</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);display:flex;flex-direction:column;gap:var(--sp-5)">

    <!-- Stat Cards -->
    <div class="grid-3">
      <div class="card stat-card">
        <span class="stat-card__label">👥 Utenti totali</span>
        <div class="stat-card__value">
          <?= count($utenti) ?><span class="stat-card__unit"> registrati</span>
        </div>
        <div class="stat-card__trend">Nel sistema</div>
      </div>
      <div class="card stat-card">
        <span class="stat-card__label">🔑 Proprietari</span>
        <div class="stat-card__value" style="color:var(--brand)">
          <?= $nProprietari ?><span class="stat-card__unit"> utenti</span>
        </div>
        <div class="stat-card__trend">Accesso completo</div>
      </div>
      <div class="card stat-card">
        <span class="stat-card__label">👤 Ospiti</span>
        <div class="stat-card__value" style="color:var(--text-secondary)">
          <?= $nOspiti ?><span class="stat-card__unit"> utenti</span>
        </div>
        <div class="stat-card__trend">Solo visualizzazione</div>
      </div>
    </div>

    <!-- Tabella permessi -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Permessi per ruolo</div>
          <div class="card__subtitle">Cosa può fare ogni ruolo</div>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Funzione</th>
            <th>Proprietario</th>
            <th>Ospite</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Visualizza dashboard e sensori</td>
            <td style="color:var(--brand)">✓</td>
            <td style="color:var(--brand)">✓</td>
          </tr>
          <tr>
            <td>Riceve notifiche</td>
            <td style="color:var(--brand)">✓</td>
            <td style="color:var(--brand)">✓</td>
          </tr>
          <tr>
            <td>Crea stanze</td>
            <td style="color:var(--brand)">✓</td>
            <td style="color:var(--danger)">✗</td>
          </tr>
          <tr>
            <td>Crea dispositivi</td>
            <td style="color:var(--brand)">✓</td>
            <td style="color:var(--danger)">✗</td>
          </tr>
          <tr>
            <td>Crea utenti</td>
            <td style="color:var(--brand)">✓</td>
            <td style="color:var(--danger)">✗</td>
          </tr>
          <tr>
            <td>Vedi lista utenti</td>
            <td style="color:var(--brand)">✓</td>
            <td style="color:var(--danger)">✗</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Lista utenti -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Lista utenti</div>
          <div class="card__subtitle"><?= count($utenti) ?> utenti registrati</div>
        </div>
      </div>

      <?php if ($utenti): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Utente</th>
            <th>Email</th>
            <th>Telegram</th>
            <th>Ruolo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($utenti as $i => $u):
            $color = $AVATAR_COLORS[$u['id_utente'] % count($AVATAR_COLORS)];
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php if (!empty($u['immagine_profilo'])): ?>
                  <img src="<?= htmlspecialchars($u['immagine_profilo']) ?>"
                       class="avatar avatar--sm" style="object-fit:cover" alt="">
                <?php else: ?>
                  <div class="avatar avatar--sm" style="background:<?= $color ?>">
                    <?= iniziali($u['nome'], $u['cognome']) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <div style="font-size:13px;font-weight:600">
                    <?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?>
                  </div>
                  <div style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono)">
                    ID <?= $u['id_utente'] ?>
                  </div>
                </div>
              </div>
            </td>
            <td style="font-family:var(--font-mono);font-size:12px">
              <?= htmlspecialchars($u['email']) ?>
            </td>
            <td style="font-family:var(--font-mono);font-size:12px">
              <?php if ($u['chiave_telegram']): ?>
                <span class="channel-chip channel-chip--telegram">
                  ✈ <?= htmlspecialchars($u['chiave_telegram']) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--text-muted)">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $u['ruolo']==='Proprietario' ? 'badge--info' : 'badge--muted' ?>">
                <?= htmlspecialchars($u['ruolo']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div style="padding:20px;text-align:center;color:var(--text-muted)">
        Nessun utente trovato.
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
