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

// ── Gestione del form (quando si preme "Crea stanza") ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome_stanza = trim($_POST['nome']       ?? '');
    $volumetria  = trim($_POST['volumetria'] ?? '');

    // Validazione campi
    if ($nome_stanza === '') {
        $errore = 'Il nome della stanza è obbligatorio.';
    } elseif ($volumetria === '' || !is_numeric($volumetria) || (int)$volumetria <= 0) {
        $errore = 'Inserisci una volumetria valida (numero intero positivo).';
    } else {
        // Controlla che non esista già una stanza con lo stesso nome
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM stanze WHERE nome = :nome");
        $stmtCheck->execute(['nome' => $nome_stanza]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errore = 'Esiste già una stanza con il nome "' . htmlspecialchars($nome_stanza) . '".';
        }
    }

    // Se non ci sono errori, inserisce la stanza nel database
    if ($errore === '') {
        $stmt = $conn->prepare("INSERT INTO stanze (nome, volumetria) VALUES (:nome, :vol)");
        $stmt->execute([
            'nome' => $nome_stanza,
            'vol'  => (int)$volumetria,
        ]);
        $success = 'Stanza <strong>' . htmlspecialchars($nome_stanza) . '</strong> creata con successo!';
    }
}

// Ripopola i campi con i valori inseriti (utile in caso di errore)
$val = [
    'nome' => htmlspecialchars($_POST['nome']       ?? ''),
    'vol'  => htmlspecialchars($_POST['volumetria'] ?? ''),
];

// Incendio attivo (per il badge nella sidebar)
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Variabile per la sidebar ──────────────────────────────────
$paginaAttiva = 'stanze_crea';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Crea Stanza</title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">Crea Stanza</div>
      <div class="topbar__subtitle">Aggiungi una nuova stanza alla casa</div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="../stanze/index.php" class="btn btn--ghost btn--sm">← Torna alle stanze</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);max-width:500px">

    <!-- Messaggio di successo -->
    <?php if ($success !== ''): ?>
    <div class="alert-banner alert-banner--info" style="margin-bottom:var(--sp-5)">
      <span>✅</span>
      <span><?= $success ?></span>
      <div class="alert-banner__actions">
        <a href="../stanze/index.php" class="btn btn--sm btn--ghost">Vedi stanze</a>
        <a href="crea.php"            class="btn btn--sm btn--primary">Crea un'altra</a>
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

    <!-- Form di creazione stanza -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">Nuova stanza</div>
          <div class="card__subtitle">I campi contrassegnati con * sono obbligatori</div>
        </div>
      </div>

      <form method="POST" action="crea.php"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <div class="form-group">
          <label class="form-label" for="nome" style="font-size:11px;color:var(--text-muted)">
            Nome stanza *
          </label>
          <input class="input" type="text" id="nome" name="nome"
                 placeholder="es. Cucina, Soggiorno, Camera da letto…"
                 value="<?= $val['nome'] ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="volumetria" style="font-size:11px;color:var(--text-muted)">
            Volumetria (m³) *
          </label>
          <input class="input" type="number" id="volumetria" name="volumetria"
                 placeholder="es. 25" min="1" value="<?= $val['vol'] ?>" required>
        </div>

        <div style="display:flex;gap:var(--sp-3);margin-top:var(--sp-2)">
          <button type="submit" class="btn btn--primary">✓ Crea stanza</button>
          <a href="../stanze/index.php" class="btn btn--ghost">Annulla</a>
        </div>

      </form>
    </div>

  </div>
</main>
</div>
</body>
</html>
