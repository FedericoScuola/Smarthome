<?php
// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}
// Solo i proprietari possono vedere la lista utenti
if ($_SESSION['ruolo'] !== 'Proprietario') {
    header("Location: ../home/index.php");
    exit;
}

// ── Include librerie condivise ────────────────────────────────
require_once '../lib/conn.php';
require_once '../lib/helpers.php';

// ── Carica utenti ─────────────────────────────────────────────
$utenti = $conn->query(
    "SELECT id_utente, nome, cognome, email, ruolo, chiave_telegram, immagine_profilo
     FROM utenti
     ORDER BY ruolo DESC, cognome ASC"
)->fetchAll();

// Conta proprietari e ospiti
$nProprietari = count(array_filter($utenti, function($u) { return $u['ruolo'] === 'Proprietario'; }));
$nOspiti      = count(array_filter($utenti, function($u) { return $u['ruolo'] === 'Ospite'; }));

// Colori per gli avatar senza foto
$AVATAR_COLORS = [
    'linear-gradient(135deg,#00d4b4,#0088aa)',
    'linear-gradient(135deg,#f0a030,#f04060)',
    'linear-gradient(135deg,#7c6ff7,#3090f0)',
    'linear-gradient(135deg,#30c878,#0088aa)',
    'linear-gradient(135deg,#4a5878,#1a2130)',
];

// Incendio attivo (per il badge nella sidebar)
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Variabile per la sidebar ──────────────────────────────────
$paginaAttiva = 'utenti';
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

<?php require '../lib/sidebar.php'; ?>

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

    <!-- Riquadri statistici -->
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

    <!-- Tabella riepilogo permessi -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Permessi per ruolo</div>
          <div class="card__subtitle">Cosa può fare ogni ruolo</div>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr><th>Funzione</th><th>Proprietario</th><th>Ospite</th></tr>
        </thead>
        <tbody>
          <tr><td>Visualizza dashboard e sensori</td><td style="color:var(--brand)">✓</td><td style="color:var(--brand)">✓</td></tr>
          <tr><td>Riceve notifiche</td>             <td style="color:var(--brand)">✓</td><td style="color:var(--brand)">✓</td></tr>
          <tr><td>Crea stanze</td>                  <td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
          <tr><td>Crea dispositivi</td>             <td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
          <tr><td>Crea utenti</td>                  <td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
          <tr><td>Vedi lista utenti</td>            <td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Lista completa utenti -->
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
          <tr><th>Utente</th><th>Email</th><th>Telegram</th><th>Ruolo</th></tr>
        </thead>
        <tbody>
          <?php foreach ($utenti as $u):
            // Sceglie il colore avatar in base all'ID (cicla tra i 5 colori)
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
              <span class="badge <?= $u['ruolo'] === 'Proprietario' ? 'badge--info' : 'badge--muted' ?>">
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
</body>
</html>
