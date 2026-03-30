<?php
// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}
if ($_SESSION['ruolo'] !== 'Proprietario') {
    header("Location: index.php");
    exit;
}

// ── Include librerie condivise ────────────────────────────────
require_once '../lib/conn.php';
require_once '../lib/helpers.php';

$errore  = '';
$success = '';

// ── Gestione del form (quando si preme "Crea dispositivo") ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome_disp  = trim($_POST['nome']         ?? '');
    $tipo       = trim($_POST['tipo']         ?? '');
    $id_stanza  = (int)($_POST['id_stanza']   ?? 0);
    $unita      = trim($_POST['unita_misura'] ?? '');
    $soglia_min = trim($_POST['soglia_min']   ?? '');
    $soglia_max = trim($_POST['soglia_max']   ?? '');

    // Validazione campi obbligatori
    if ($nome_disp === '') {
        $errore = 'Il nome del dispositivo è obbligatorio.';
    } elseif (!in_array($tipo, ['Sensore', 'Attuatore'])) {
        $errore = 'Seleziona un tipo valido.';
    } elseif ($id_stanza <= 0) {
        $errore = 'Seleziona una stanza.';
    } elseif ($tipo === 'Sensore' && $unita === '') {
        $errore = "L'unità di misura è obbligatoria per un sensore.";
    } else {
        // Controlla che la stanza esista nel database
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM stanze WHERE id_stanza = :id");
        $stmtCheck->execute(['id' => $id_stanza]);
        if ($stmtCheck->fetchColumn() == 0) {
            $errore = 'Stanza non trovata.';
        }
    }

    // Se non ci sono errori, inserisce il dispositivo
    if ($errore === '') {
        $sql  = "INSERT INTO dispositivi (nome, tipo, id_stanza, unita_misura, soglia_minima, soglia_massima)
                 VALUES (:nome, :tipo, :id_stanza, :unita, :min, :max)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'nome'      => $nome_disp,
            'tipo'      => $tipo,
            'id_stanza' => $id_stanza,
            // Per gli attuatori non ha senso avere unità o soglie → null
            'unita'     => $tipo === 'Sensore' ? $unita : null,
            'min'       => ($tipo === 'Sensore' && $soglia_min !== '') ? (float)$soglia_min : null,
            'max'       => ($tipo === 'Sensore' && $soglia_max !== '') ? (float)$soglia_max : null,
        ]);
        $success = 'Dispositivo <strong>' . htmlspecialchars($nome_disp) . '</strong> creato con successo!';
    }
}

// Stanze per il menu a tendina
$stanze = $conn->query("SELECT id_stanza, nome FROM stanze ORDER BY nome")->fetchAll();

// Ripopola i campi con i valori inseriti (utile in caso di errore)
$val = [
    'nome'      => htmlspecialchars($_POST['nome']         ?? ''),
    'tipo'      => $_POST['tipo']         ?? '',
    'id_stanza' => (int)($_POST['id_stanza']   ?? 0),
    'unita'     => htmlspecialchars($_POST['unita_misura'] ?? ''),
    'min'       => htmlspecialchars($_POST['soglia_min']   ?? ''),
    'max'       => htmlspecialchars($_POST['soglia_max']   ?? ''),
];

// Incendio attivo (per il badge nella sidebar)
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Variabile per la sidebar ──────────────────────────────────
$paginaAttiva = 'dispositivi_crea';
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

<?php require '../lib/sidebar.php'; ?>

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

    <!-- Form di creazione dispositivo -->
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
          <label class="form-label" for="nome" style="font-size:11px;color:var(--text-muted)">
            Nome dispositivo *
          </label>
          <input class="input" type="text" id="nome" name="nome"
                 placeholder="es. Sensore temperatura cucina"
                 value="<?= $val['nome'] ?>" required>
        </div>

        <!-- Tipo (Sensore o Attuatore) -->
        <div class="form-group">
          <label class="form-label" for="tipo" style="font-size:11px;color:var(--text-muted)">
            Tipo *
          </label>
          <select class="input" id="tipo" name="tipo" required onchange="toggleSensoreFields()">
            <option value="">— Seleziona —</option>
            <option value="Sensore"   <?= $val['tipo'] === 'Sensore'   ? 'selected' : '' ?>>📡 Sensore</option>
            <option value="Attuatore" <?= $val['tipo'] === 'Attuatore' ? 'selected' : '' ?>>💡 Attuatore</option>
          </select>
        </div>

        <!-- Stanza -->
        <div class="form-group">
          <label class="form-label" for="id_stanza" style="font-size:11px;color:var(--text-muted)">
            Stanza *
          </label>
          <select class="input" id="id_stanza" name="id_stanza" required>
            <option value="">— Seleziona stanza —</option>
            <?php foreach ($stanze as $s): ?>
            <option value="<?= $s['id_stanza'] ?>"
                    <?= $val['id_stanza'] === $s['id_stanza'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['nome']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Campi extra visibili solo se il tipo è "Sensore" -->
        <div id="sensore-fields"
             style="display:<?= $val['tipo'] === 'Sensore' ? 'flex' : 'none' ?>;
                    flex-direction:column;gap:var(--sp-4)">

          <div style="height:1px;background:var(--border-subtle)"></div>
          <div style="font-size:12px;color:var(--text-muted)">Impostazioni sensore</div>

          <!-- Unità di misura -->
          <div class="form-group">
            <label class="form-label" for="unita_misura" style="font-size:11px;color:var(--text-muted)">
              Unità di misura *
            </label>
            <select class="input" id="unita_misura" name="unita_misura">
              <option value="">— Seleziona —</option>
              <option value="°C"  <?= $val['unita'] === '°C'  ? 'selected' : '' ?>>🌡 °C — Temperatura</option>
              <option value="%"   <?= $val['unita'] === '%'   ? 'selected' : '' ?>>💧 % — Umidità</option>
              <option value="AQI" <?= $val['unita'] === 'AQI' ? 'selected' : '' ?>>🌬 AQI — Qualità aria</option>
            </select>
          </div>

          <!-- Soglia minima e massima -->
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label" for="soglia_min" style="font-size:11px;color:var(--text-muted)">
                Soglia minima
              </label>
              <input class="input" type="number" step="0.01" id="soglia_min"
                     name="soglia_min" placeholder="es. 18" value="<?= $val['min'] ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="soglia_max" style="font-size:11px;color:var(--text-muted)">
                Soglia massima
              </label>
              <input class="input" type="number" step="0.01" id="soglia_max"
                     name="soglia_max" placeholder="es. 26" value="<?= $val['max'] ?>">
            </div>
          </div>

          <div style="padding:10px 14px;background:var(--brand-dim);
                      border:1px solid rgba(0,212,180,0.2);border-radius:var(--radius-md);
                      font-size:11px;color:var(--text-muted)">
            💡 Se il valore del sensore esce dall'intervallo soglia,
            verrà generato un avviso nella dashboard.
          </div>
        </div>

        <!-- Bottoni -->
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
// Mostra/nasconde i campi del sensore in base al tipo selezionato
function toggleSensoreFields() {
    var tipo   = document.getElementById('tipo').value;
    var fields = document.getElementById('sensore-fields');
    fields.style.display = (tipo === 'Sensore') ? 'flex' : 'none';
    // Rende obbligatoria l'unità di misura solo per i sensori
    document.getElementById('unita_misura').required = (tipo === 'Sensore');
}
// Esegue subito al caricamento della pagina (ripristina lo stato dopo un errore)
toggleSensoreFields();
</script>
</body>
</html>
