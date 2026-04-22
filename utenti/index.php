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

// ── Messaggi flash ────────────────────────────────────────────
$flashOk  = $_SESSION['flash']       ?? '';
$flashErr = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

// ── Carica utenti ─────────────────────────────────────────────
$utenti = $conn->query(
    "SELECT id_utente, nome, cognome, email, ruolo, chiave_telegram, immagine_profilo
     FROM utenti
     ORDER BY ruolo DESC, cognome ASC"
)->fetchAll();

// Conta proprietari e ospiti
$nProprietari = count(array_filter($utenti, fn($u) => $u['ruolo'] === 'Proprietario'));
$nOspiti      = count(array_filter($utenti, fn($u) => $u['ruolo'] === 'Ospite'));
$nTotProp     = $nProprietari; // usato per bloccare eliminazione ultimo proprietario

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

// ID utente loggato (per non mostrare "elimina" su se stesso)
$idSelf = (int)$_SESSION['utente'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Utenti</title>
  <link rel="stylesheet" href="../styles/main.css">
  <style>
    /* ── Modal eliminazione ─────────────────────────────── */
    #confirm-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.65);
      backdrop-filter: blur(4px);
      z-index: 500;
      align-items: center;
      justify-content: center;
    }
    #confirm-modal.open { display: flex; }

    .confirm-box {
      background: var(--bg-elevated);
      border: 1px solid var(--border-default);
      border-radius: var(--radius-lg);
      padding: 28px 32px;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 16px 48px rgba(0,0,0,0.55);
      text-align: center;
    }
    .confirm-box__icon {
      font-size: 36px;
      margin-bottom: 12px;
    }
    .confirm-box__title {
      font-size: 16px;
      font-weight: 700;
      color: var(--danger);
      margin-bottom: 8px;
    }
    .confirm-box__desc {
      font-size: 13px;
      color: var(--text-secondary);
      margin-bottom: 6px;
      line-height: 1.6;
    }
    .confirm-box__name {
      font-family: var(--font-mono);
      font-size: 13px;
      font-weight: 600;
      color: var(--text-primary);
      background: var(--bg-overlay);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-sm);
      padding: 6px 12px;
      display: inline-block;
      margin: 8px 0 18px;
    }
    .confirm-box__warn {
      font-size: 11px;
      color: var(--text-muted);
      margin-bottom: 20px;
    }
    .confirm-box__actions {
      display: flex;
      gap: 10px;
      justify-content: center;
    }

    /* ── Bottone elimina nella tabella ─────────────────── */
    .btn--delete {
      background: transparent;
      border: 1px solid rgba(240,64,96,0.3);
      color: var(--danger);
      border-radius: var(--radius-sm);
      padding: 4px 10px;
      font-size: 11px;
      font-family: var(--font-ui);
      cursor: pointer;
      transition: background var(--transition-fast), border-color var(--transition-fast);
    }
    .btn--delete:hover {
      background: var(--danger-dim);
      border-color: rgba(240,64,96,0.65);
    }
    .btn--delete:disabled {
      opacity: 0.25;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* ── Flash messages ─────────────────────────────────── */
    .flash {
      padding: 12px 18px;
      border-radius: var(--radius-md);
      font-size: 13px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .flash--ok {
      background: rgba(48,200,120,0.10);
      border: 1px solid rgba(48,200,120,0.30);
      color: var(--success);
    }
    .flash--err {
      background: var(--danger-dim);
      border: 1px solid rgba(240,64,96,0.35);
      color: var(--danger);
    }
  </style>
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

    <!-- Flash messages -->
    <?php if ($flashOk): ?>
    <div class="flash flash--ok">✓ <?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
    <div class="flash flash--err">✗ <?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

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
          <tr><td>Elimina utenti</td>               <td style="color:var(--brand)">✓</td><td style="color:var(--danger)">✗</td></tr>
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
          <tr>
            <th>Utente</th>
            <th>Email</th>
            <th>Telegram</th>
            <th>Ruolo</th>
            <th style="text-align:right">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($utenti as $u):
            $color = $AVATAR_COLORS[$u['id_utente'] % count($AVATAR_COLORS)];
            $isSelf       = ((int)$u['id_utente'] === $idSelf);
            $isLastOwner  = ($u['ruolo'] === 'Proprietario' && $nTotProp <= 1);
            $canDelete    = !$isSelf && !$isLastOwner;

            // Motivo del blocco (per tooltip)
            $disabledReason = '';
            if ($isSelf)      $disabledReason = 'Non puoi eliminare te stesso';
            elseif ($isLastOwner) $disabledReason = 'Ultimo proprietario: non eliminabile';
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
                    <?php if ($isSelf): ?>
                    <span style="font-size:10px;color:var(--text-muted);font-weight:400;margin-left:4px">(tu)</span>
                    <?php endif; ?>
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
            <td style="text-align:right">
              <?php if ($canDelete): ?>
              <button
                class="btn--delete"
                onclick="openConfirm(<?= $u['id_utente'] ?>, '<?= htmlspecialchars(addslashes($u['nome'] . ' ' . $u['cognome'])) ?>', '<?= htmlspecialchars($u['ruolo']) ?>')">
                🗑 Elimina
              </button>
              <?php else: ?>
              <button class="btn--delete" disabled title="<?= htmlspecialchars($disabledReason) ?>">
                🗑 Elimina
              </button>
              <?php endif; ?>
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

<!-- ── Modal conferma eliminazione ───────────────────────────── -->
<div id="confirm-modal">
  <div class="confirm-box">
    <div class="confirm-box__icon">⚠️</div>
    <div class="confirm-box__title">Eliminare questo utente?</div>
    <div class="confirm-box__desc">
      Stai per eliminare definitivamente l'utente:
    </div>
    <div class="confirm-box__name" id="modal-nome">—</div>
    <div class="confirm-box__warn">
      Questa azione è irreversibile. Verranno rimosse anche tutte le notifiche associate a questo account.
    </div>
    <form method="POST" action="elimina.php" id="delete-form">
      <input type="hidden" name="azione"     value="elimina">
      <input type="hidden" name="id_utente"  id="modal-id" value="">
      <div class="confirm-box__actions">
        <button type="button" class="btn btn--ghost" onclick="closeConfirm()">
          Annulla
        </button>
        <button type="submit" class="btn btn--danger">
          🗑 Sì, elimina
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openConfirm(id, nome, ruolo) {
    document.getElementById('modal-id').value   = id;
    document.getElementById('modal-nome').textContent = nome + ' (' + ruolo + ')';
    document.getElementById('confirm-modal').classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirm-modal').classList.remove('open');
}
// Chiudi cliccando fuori dalla box
document.getElementById('confirm-modal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
// Chiudi con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirm();
});
</script>
</body>
</html>
