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

    $nome_stanza = trim($_POST['nome']       ?? '');
    $volumetria  = trim($_POST['volumetria'] ?? '');

    if ($nome_stanza === '') {
        $errore = 'Il nome della stanza è obbligatorio.';
    } elseif ($volumetria === '' || !is_numeric($volumetria) || (int)$volumetria <= 0) {
        $errore = 'Inserisci una volumetria valida (numero intero positivo).';
    } else {
        // Controlla duplicati
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM stanze WHERE nome = :nome");
        $stmtCheck->execute(['nome' => $nome_stanza]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errore = "Esiste già una stanza con il nome \"" . htmlspecialchars($nome_stanza) . "\".";
        }
    }

    if ($errore === '') {
        $stmt = $conn->prepare("INSERT INTO stanze (nome, volumetria) VALUES (:nome, :vol)");
        $stmt->execute([
            'nome' => $nome_stanza,
            'vol'  => (int)$volumetria,
        ]);
        $success = "Stanza <strong>" . htmlspecialchars($nome_stanza) . "</strong> creata con successo!";
    }
}

// Ripopola dopo errore
$val = [
    'nome' => htmlspecialchars($_POST['nome']       ?? ''),
    'vol'  => htmlspecialchars($_POST['volumetria'] ?? ''),
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
  <title>SmartHome — Crea Stanza</title>
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

    <a href="crea.php" class="nav__item active" title="Crea stanza">
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
      <div class="topbar__title">Crea Stanza</div>
      <div class="topbar__subtitle">Aggiungi una nuova stanza alla casa</div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="../home/index.php" class="btn btn--ghost btn--sm">← Torna alla dashboard</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);max-width:500px">

    <!-- Successo -->
    <?php if ($success !== ''): ?>
    <div class="alert-banner alert-banner--info" style="margin-bottom:var(--sp-5)">
      <span>✅</span>
      <span><?= $success ?></span>
      <div class="alert-banner__actions">
        <a href="../home/index.php" class="btn btn--sm btn--ghost">Dashboard</a>
        <a href="crea.php"          class="btn btn--sm btn--primary">Crea un'altra</a>
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
          <div class="card__title">Nuova stanza</div>
          <div class="card__subtitle">I campi contrassegnati con * sono obbligatori</div>
        </div>
      </div>

      <form method="POST" action="crea.php"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <div class="form-group">
          <label class="form-label" for="nome"
                 style="font-size:11px;color:var(--text-muted)">
            Nome stanza *
          </label>
          <input class="input" type="text" id="nome" name="nome"
                 placeholder="es. Cucina, Soggiorno, Camera da letto…"
                 value="<?= $val['nome'] ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="volumetria"
                 style="font-size:11px;color:var(--text-muted)">
            Volumetria (m³) *
          </label>
          <input class="input" type="number" id="volumetria" name="volumetria"
                 placeholder="es. 25" min="1" value="<?= $val['vol'] ?>" required>
        </div>

        <div style="display:flex;gap:var(--sp-3);margin-top:var(--sp-2)">
          <button type="submit" class="btn btn--primary">✓ Crea stanza</button>
          <a href="../home/index.php" class="btn btn--ghost">Annulla</a>
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
