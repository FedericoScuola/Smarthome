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

$errore  = '';
$success = '';

// ── Gestione POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nuovo_nome     = trim($_POST['nome']            ?? '');
    $nuovo_cognome  = trim($_POST['cognome']         ?? '');
    $nuovo_email    = trim($_POST['email']           ?? '');
    $nuovo_password = trim($_POST['password']        ?? '');
    $conferma_pwd   = trim($_POST['conferma_password']?? '');
    $nuovo_ruolo    = trim($_POST['ruolo']           ?? '');
    $nuovo_telegram = trim($_POST['chiave_telegram'] ?? '');

    // Validazione
    if ($nuovo_nome === '' || $nuovo_cognome === '') {
        $errore = 'Nome e cognome sono obbligatori.';
    } elseif ($nuovo_email === '' || !filter_var($nuovo_email, FILTER_VALIDATE_EMAIL)) {
        $errore = 'Inserisci un indirizzo email valido.';
    } elseif (strlen($nuovo_password) < 6) {
        $errore = 'La password deve essere di almeno 6 caratteri.';
    } elseif ($nuovo_password !== $conferma_pwd) {
        $errore = 'Le password non coincidono.';
    } elseif (!in_array($nuovo_ruolo, ['Proprietario', 'Ospite'])) {
        $errore = 'Seleziona un ruolo valido.';
    } else {
        // Email duplicata
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM utenti WHERE email = :email");
        $stmtCheck->execute(['email' => $nuovo_email]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errore = "Esiste già un account con l'email \"" . htmlspecialchars($nuovo_email) . "\".";
        }
    }

    if ($errore === '') {
        $hash = password_hash($nuovo_password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare(
            "INSERT INTO utenti (nome, cognome, email, password, ruolo, chiave_telegram)
             VALUES (:nome, :cognome, :email, :password, :ruolo, :telegram)"
        );
        $stmt->execute([
            'nome'     => $nuovo_nome,
            'cognome'  => $nuovo_cognome,
            'email'    => $nuovo_email,
            'password' => $hash,
            'ruolo'    => $nuovo_ruolo,
            'telegram' => $nuovo_telegram !== '' ? $nuovo_telegram : null,
        ]);

        $success = "Utente <strong>" . htmlspecialchars("$nuovo_nome $nuovo_cognome") . "</strong> creato con ruolo <strong>" . htmlspecialchars($nuovo_ruolo) . "</strong>.";
    }
}

// Ripopola dopo errore (mai ripopolare la password)
$val = [
    'nome'     => htmlspecialchars($_POST['nome']            ?? ''),
    'cognome'  => htmlspecialchars($_POST['cognome']         ?? ''),
    'email'    => htmlspecialchars($_POST['email']           ?? ''),
    'ruolo'    => $_POST['ruolo']            ?? '',
    'telegram' => htmlspecialchars($_POST['chiave_telegram'] ?? ''),
];

function iniziali(string $n, string $c): string {
    return strtoupper(mb_substr($n,0,1) . mb_substr($c,0,1));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Crea Utente</title>
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
    <a href="index.php" class="nav__item" title="Utenti">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
      <span class="nav__label">Utenti</span>
    </a>
    <a href="crea.php" class="nav__item active" title="Crea utente">
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
      <div class="topbar__title">Crea Utente</div>
      <div class="topbar__subtitle">Aggiungi un nuovo accesso al sistema</div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="index.php" class="btn btn--ghost btn--sm">← Torna agli utenti</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);max-width:560px">

    <!-- Successo -->
    <?php if ($success !== ''): ?>
    <div class="alert-banner alert-banner--info" style="margin-bottom:var(--sp-5)">
      <span>✅</span>
      <span><?= $success ?></span>
      <div class="alert-banner__actions">
        <a href="index.php"  class="btn btn--sm btn--ghost">Vedi lista</a>
        <a href="crea.php"   class="btn btn--sm btn--primary">Crea un altro</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Errore -->
    <?php if ($errore !== ''): ?>
    <div class="alert-banner alert-banner--danger" style="margin-bottom:var(--sp-5)">
      <span>⚠</span>
      <span><?= htmlspecialchars($errore) ?></span>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Nuovo utente</div>
          <div class="card__subtitle">I campi contrassegnati con * sono obbligatori</div>
        </div>
      </div>

      <form method="POST" action="crea.php"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <!-- Nome + Cognome -->
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="nome"
                   style="font-size:11px;color:var(--text-muted)">Nome *</label>
            <input class="input" type="text" id="nome" name="nome"
                   placeholder="es. Marco" value="<?= $val['nome'] ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="cognome"
                   style="font-size:11px;color:var(--text-muted)">Cognome *</label>
            <input class="input" type="text" id="cognome" name="cognome"
                   placeholder="es. Rossi" value="<?= $val['cognome'] ?>" required>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email"
                 style="font-size:11px;color:var(--text-muted)">Email *</label>
          <input class="input" type="email" id="email" name="email"
                 placeholder="es. marco.rossi@example.com"
                 value="<?= $val['email'] ?>" required>
        </div>

        <!-- Password + Conferma -->
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="password"
                   style="font-size:11px;color:var(--text-muted)">Password * (min. 6 car.)</label>
            <input class="input" type="password" id="password" name="password"
                   placeholder="••••••••" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label" for="conferma_password"
                   style="font-size:11px;color:var(--text-muted)">Conferma password *</label>
            <input class="input" type="password" id="conferma_password" name="conferma_password"
                   placeholder="••••••••" required minlength="6">
          </div>
        </div>

        <!-- Ruolo -->
        <div class="form-group">
          <label class="form-label" for="ruolo"
                 style="font-size:11px;color:var(--text-muted)">Ruolo *</label>
          <select class="input" id="ruolo" name="ruolo" required>
            <option value="">— Seleziona —</option>
            <option value="Proprietario" <?= $val['ruolo']==='Proprietario'?'selected':'' ?>>
              🔑 Proprietario — accesso completo
            </option>
            <option value="Ospite" <?= $val['ruolo']==='Ospite'?'selected':'' ?>>
              👤 Ospite — solo visualizzazione
            </option>
          </select>
        </div>

        <!-- Telegram (opzionale) -->
        <div class="form-group">
          <label class="form-label" for="chiave_telegram"
                 style="font-size:11px;color:var(--text-muted)">
            Chiave Telegram
            <span style="color:var(--text-muted);font-weight:400">(opzionale)</span>
          </label>
          <input class="input" type="text" id="chiave_telegram" name="chiave_telegram"
                 placeholder="es. TGKEY123" value="<?= $val['telegram'] ?>">
        </div>

        <div style="height:1px;background:var(--border-subtle)"></div>

        <div style="display:flex;gap:var(--sp-3)">
          <button type="submit" class="btn btn--primary">✓ Crea utente</button>
          <a href="index.php" class="btn btn--ghost">Annulla</a>
        </div>

      </form>
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
