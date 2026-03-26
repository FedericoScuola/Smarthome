<?php
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}
// Solo i proprietari possono creare dispositivi
if ($_SESSION['ruolo'] !== 'Proprietario') {
    header("Location: index.php");
    exit;
}

include_once '../lib/conn.php';

$nome    = $_SESSION['nome'];
$cognome = $_SESSION['cognome'];
$ruolo   = $_SESSION['ruolo'];

$errore  = '';
$success = '';

// ── Gestione POST: salva il nuovo dispositivo ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome_disp  = trim($_POST['nome']        ?? '');
    $tipo       = trim($_POST['tipo']        ?? '');
    $id_stanza  = (int)($_POST['id_stanza']  ?? 0);
    $unita      = trim($_POST['unita_misura']?? '');
    $soglia_min = trim($_POST['soglia_min']  ?? '');
    $soglia_max = trim($_POST['soglia_max']  ?? '');

    // Validazione
    if ($nome_disp === '') {
        $errore = 'Il nome del dispositivo è obbligatorio.';
    } elseif (!in_array($tipo, ['Sensore', 'Attuatore'])) {
        $errore = 'Seleziona un tipo valido.';
    } elseif ($id_stanza <= 0) {
        $errore = 'Seleziona una stanza.';
    } elseif ($tipo === 'Sensore' && $unita === '') {
        $errore = "L'unità di misura è obbligatoria per un sensore.";
    } else {
        // Verifica stanza esistente
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM stanze WHERE id_stanza = :id");
        $stmtCheck->execute(['id' => $id_stanza]);
        if ($stmtCheck->fetchColumn() == 0) {
            $errore = 'Stanza non trovata.';
        }
    }

    if ($errore === '') {
        $sqlIns = "INSERT INTO dispositivi
                       (nome, tipo, id_stanza, unita_misura, soglia_minima, soglia_massima)
                   VALUES
                       (:nome, :tipo, :id_stanza, :unita, :min, :max)";
        $stmtIns = $conn->prepare($sqlIns);
        $stmtIns->execute([
            'nome'      => $nome_disp,
            'tipo'      => $tipo,
            'id_stanza' => $id_stanza,
            'unita'     => $tipo === 'Sensore' ? $unita      : null,
            'min'       => $tipo === 'Sensore' && $soglia_min !== '' ? (float)$soglia_min : null,
            'max'       => $tipo === 'Sensore' && $soglia_max !== '' ? (float)$soglia_max : null,
        ]);
        $nuovoId = $conn->lastInsertId();
        $success = "Dispositivo <strong>" . htmlspecialchars($nome_disp) . "</strong> creato con successo!";
    }
}

// Tutte le stanze per il <select>
$stanze = $conn->query("SELECT id_stanza, nome FROM stanze ORDER BY nome")->fetchAll();

function iniziali(string $n, string $c): string {
    return strtoupper(mb_substr($n,0,1) . mb_substr($c,0,1));
}

// Ripopola i campi dopo errore
$val = [
    'nome'      => htmlspecialchars($_POST['nome']        ?? ''),
    'tipo'      => $_POST['tipo']        ?? '',
    'id_stanza' => (int)($_POST['id_stanza']  ?? 0),
    'unita'     => htmlspecialchars($_POST['unita_misura']?? ''),
    'min'       => htmlspecialchars($_POST['soglia_min']  ?? ''),
    'max'       => htmlspecialchars($_POST['soglia_max']  ?? ''),
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Crea Dispositivo</title>
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
    <a href="index.php" class="nav__item" title="Dispositivi">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
      <span class="nav__label">Dispositivi</span>
    </a>
    <a href="crea.php" class="nav__item active" title="Crea dispositivo">
      <span class="nav__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      <span class="nav__label">Crea dispositivo</span>
    </a>
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
      <div class="topbar__title">Crea Dispositivo</div>
      <div class="topbar__subtitle">Aggiungi un nuovo sensore o attuatore</div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="index.php" class="btn btn--ghost btn--sm">← Torna ai dispositivi</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);max-width:600px">

    <!-- Messaggio successo -->
    <?php if ($success !== ''): ?>
    <div class="alert-banner alert-banner--info" style="margin-bottom:var(--sp-5)">
      <span>✅</span>
      <span><?= $success ?></span>
      <div class="alert-banner__actions">
        <a href="index.php" class="btn btn--sm btn--ghost">Vedi lista</a>
        <a href="crea.php" class="btn btn--sm btn--primary">Crea un altro</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Messaggio errore -->
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
          <div class="card__title">Nuovo dispositivo</div>
          <div class="card__subtitle">Compila tutti i campi obbligatori</div>
        </div>
      </div>

      <form method="POST" action="crea.php"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">

        <!-- Nome -->
        <div class="form-group">
          <label class="form-label" for="nome"
                 style="font-size:11px;color:var(--text-muted)">
            Nome dispositivo *
          </label>
          <input class="input" type="text" id="nome" name="nome"
                 placeholder="es. Sensore temperatura cucina"
                 value="<?= $val['nome'] ?>" required>
        </div>

        <!-- Tipo -->
        <div class="form-group">
          <label class="form-label" for="tipo"
                 style="font-size:11px;color:var(--text-muted)">
            Tipo *
          </label>
          <select class="input" id="tipo" name="tipo" required
                  onchange="toggleSensoreFields()">
            <option value="">— Seleziona —</option>
            <option value="Sensore"   <?= $val['tipo']==='Sensore'  ?'selected':'' ?>>📡 Sensore</option>
            <option value="Attuatore" <?= $val['tipo']==='Attuatore'?'selected':'' ?>>💡 Attuatore</option>
          </select>
        </div>

        <!-- Stanza -->
        <div class="form-group">
          <label class="form-label" for="id_stanza"
                 style="font-size:11px;color:var(--text-muted)">
            Stanza *
          </label>
          <select class="input" id="id_stanza" name="id_stanza" required>
            <option value="">— Seleziona stanza —</option>
            <?php foreach ($stanze as $s): ?>
            <option value="<?= $s['id_stanza'] ?>"
                    <?= $val['id_stanza']===$s['id_stanza']?'selected':'' ?>>
              <?= htmlspecialchars($s['nome']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Campi solo per Sensore -->
        <div id="sensore-fields"
             style="display:<?= $val['tipo']==='Sensore'?'flex':'none' ?>;
                    flex-direction:column;gap:var(--sp-4)">

          <div style="height:1px;background:var(--border-subtle)"></div>
          <div style="font-size:12px;color:var(--text-muted)">Impostazioni sensore</div>

          <!-- Unità di misura -->
          <div class="form-group">
            <label class="form-label" for="unita_misura"
                   style="font-size:11px;color:var(--text-muted)">
              Unità di misura *
            </label>
            <select class="input" id="unita_misura" name="unita_misura">
              <option value="">— Seleziona —</option>
              <option value="°C"  <?= $val['unita']==='°C' ?'selected':'' ?>>🌡 °C — Temperatura</option>
              <option value="%"   <?= $val['unita']==='%'  ?'selected':'' ?>>💧 % — Umidità</option>
              <option value="AQI" <?= $val['unita']==='AQI'?'selected':'' ?>>🌬 AQI — Qualità aria</option>
            </select>
          </div>

          <!-- Soglie -->
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label" for="soglia_min"
                     style="font-size:11px;color:var(--text-muted)">
                Soglia minima
              </label>
              <input class="input" type="number" step="0.01" id="soglia_min"
                     name="soglia_min" placeholder="es. 18"
                     value="<?= $val['min'] ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="soglia_max"
                     style="font-size:11px;color:var(--text-muted)">
                Soglia massima
              </label>
              <input class="input" type="number" step="0.01" id="soglia_max"
                     name="soglia_max" placeholder="es. 26"
                     value="<?= $val['max'] ?>">
            </div>
          </div>

          <div style="padding:10px 14px;background:var(--brand-dim);
                      border:1px solid rgba(0,212,180,0.2);border-radius:var(--radius-md);
                      font-size:11px;color:var(--text-muted)">
            💡 Se il valore del sensore esce dall'intervallo soglia,
            verrà generato un avviso nella dashboard.
          </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:var(--sp-3);margin-top:var(--sp-2)">
          <button type="submit" class="btn btn--primary">✓ Crea dispositivo</button>
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

function toggleSensoreFields() {
    var tipo = document.getElementById('tipo').value;
    var fields = document.getElementById('sensore-fields');
    fields.style.display = tipo === 'Sensore' ? 'flex' : 'none';
    // Unità obbligatoria solo per sensori
    document.getElementById('unita_misura').required = tipo === 'Sensore';
}
// Init al caricamento
toggleSensoreFields();
</script>
</body>
</html>
