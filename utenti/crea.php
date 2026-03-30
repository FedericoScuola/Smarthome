<?php
// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}
if ($_SESSION['ruolo'] !== 'Proprietario') {
    header("Location: ../home/index.php");
    exit;
}

// ── Include librerie condivise ────────────────────────────────
require_once '../lib/conn.php';
require_once '../lib/helpers.php';

$errore  = '';
$success = '';

// ── Gestione del form (quando si preme "Crea utente") ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nuovo_nome     = trim($_POST['nome']             ?? '');
    $nuovo_cognome  = trim($_POST['cognome']          ?? '');
    $nuovo_email    = trim($_POST['email']            ?? '');
    $nuovo_password = trim($_POST['password']         ?? '');
    $conferma_pwd   = trim($_POST['conferma_password'] ?? '');
    $nuovo_ruolo    = trim($_POST['ruolo']            ?? '');
    $nuovo_telegram = trim($_POST['chiave_telegram']  ?? '');

    // Validazione campi
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
        // Controlla che l'email non sia già registrata
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM utenti WHERE email = :email");
        $stmtCheck->execute(['email' => $nuovo_email]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errore = 'Esiste già un account con l\'email "' . htmlspecialchars($nuovo_email) . '".';
        }
    }

    // Se non ci sono errori, inserisce l'utente con password cifrata
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
            // Se il campo telegram è vuoto, salva null nel database
            'telegram' => $nuovo_telegram !== '' ? $nuovo_telegram : null,
        ]);

        $success = 'Utente <strong>' . htmlspecialchars("$nuovo_nome $nuovo_cognome") . '</strong>'
                 . ' creato con ruolo <strong>' . htmlspecialchars($nuovo_ruolo) . '</strong>.';
    }
}

// Ripopola i campi con i valori inseriti (MAI ripopolare le password)
$val = [
    'nome'     => htmlspecialchars($_POST['nome']            ?? ''),
    'cognome'  => htmlspecialchars($_POST['cognome']         ?? ''),
    'email'    => htmlspecialchars($_POST['email']           ?? ''),
    'ruolo'    => $_POST['ruolo']            ?? '',
    'telegram' => htmlspecialchars($_POST['chiave_telegram'] ?? ''),
];

// Incendio attivo (per il badge nella sidebar)
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Variabile per la sidebar ──────────────────────────────────
$paginaAttiva = 'utenti_crea';
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

<?php require '../lib/sidebar.php'; ?>

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

    <!-- Messaggio di successo -->
    <?php if ($success !== ''): ?>
    <div class="alert-banner alert-banner--info" style="margin-bottom:var(--sp-5)">
      <span>✅</span>
      <span><?= $success ?></span>
      <div class="alert-banner__actions">
        <a href="index.php" class="btn btn--sm btn--ghost">Vedi lista</a>
        <a href="crea.php"  class="btn btn--sm btn--primary">Crea un altro</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Messaggio di errore -->
    <?php if ($errore !== ''): ?>
    <div class="alert-banner alert-banner--danger" style="margin-bottom:var(--sp-5)">
      <span>⚠</span>
      <span><?= htmlspecialchars($errore) ?></span>
    </div>
    <?php endif; ?>

    <!-- Form di creazione utente -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Nuovo utente</div>
          <div class="card__subtitle">I campi contrassegnati con * sono obbligatori</div>
        </div>
      </div>

      <form method="POST" action="crea.php"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <!-- Nome e Cognome affiancati -->
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="nome" style="font-size:11px;color:var(--text-muted)">Nome *</label>
            <input class="input" type="text" id="nome" name="nome"
                   placeholder="es. Marco" value="<?= $val['nome'] ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="cognome" style="font-size:11px;color:var(--text-muted)">Cognome *</label>
            <input class="input" type="text" id="cognome" name="cognome"
                   placeholder="es. Rossi" value="<?= $val['cognome'] ?>" required>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email" style="font-size:11px;color:var(--text-muted)">Email *</label>
          <input class="input" type="email" id="email" name="email"
                 placeholder="es. marco.rossi@example.com"
                 value="<?= $val['email'] ?>" required>
        </div>

        <!-- Password e conferma affiancate -->
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="password" style="font-size:11px;color:var(--text-muted)">
              Password * (min. 6 car.)
            </label>
            <input class="input" type="password" id="password" name="password"
                   placeholder="••••••••" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label" for="conferma_password" style="font-size:11px;color:var(--text-muted)">
              Conferma password *
            </label>
            <input class="input" type="password" id="conferma_password" name="conferma_password"
                   placeholder="••••••••" required minlength="6">
          </div>
        </div>

        <!-- Ruolo -->
        <div class="form-group">
          <label class="form-label" for="ruolo" style="font-size:11px;color:var(--text-muted)">Ruolo *</label>
          <select class="input" id="ruolo" name="ruolo" required>
            <option value="">— Seleziona —</option>
            <option value="Proprietario" <?= $val['ruolo'] === 'Proprietario' ? 'selected' : '' ?>>
              🔑 Proprietario — accesso completo
            </option>
            <option value="Ospite" <?= $val['ruolo'] === 'Ospite' ? 'selected' : '' ?>>
              👤 Ospite — solo visualizzazione
            </option>
          </select>
        </div>

        <!-- Chiave Telegram (opzionale) -->
        <div class="form-group">
          <label class="form-label" for="chiave_telegram" style="font-size:11px;color:var(--text-muted)">
            Chiave Telegram
            <span style="color:var(--text-muted);font-weight:400">(opzionale)</span>
          </label>
          <input class="input" type="text" id="chiave_telegram" name="chiave_telegram"
                 placeholder="es. TGKEY123" value="<?= $val['telegram'] ?>">
        </div>

        <div style="height:1px;background:var(--border-subtle)"></div>

        <!-- Bottoni -->
        <div style="display:flex;gap:var(--sp-3)">
          <button type="submit" class="btn btn--primary">✓ Crea utente</button>
          <a href="index.php" class="btn btn--ghost">Annulla</a>
        </div>

      </form>
    </div>

  </div>
</main>
</div>
</body>
</html>
