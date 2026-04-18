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

require_once '../lib/conn.php';
require_once '../lib/helpers.php';

$errore  = '';
$success = '';

// ── Recupera ID stanza dall'URL ───────────────────────────────
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// ── Gestione ELIMINAZIONE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'elimina') {
    $idDel = (int)($_POST['id_stanza'] ?? 0);

    if ($idDel <= 0) {
        $errore = 'ID stanza non valido.';
    } else {
        try {
            // Sicurezza referenziale:
            // La FK è: eventi.id_misurazione → misurazioni.id_misurazione
            // Ordine obbligatorio: eventi → misurazioni → dispositivi → stanza

            // 1. Conta dispositivi della stanza
            $stmtDisp = $conn->prepare("SELECT COUNT(*) FROM dispositivi WHERE id_stanza = :id");
            $stmtDisp->execute(['id' => $idDel]);
            $totalDispositivi = (int)$stmtDisp->fetchColumn();

            // 2. Elimina gli eventi legati alle misurazioni dei dispositivi di questa stanza
            $conn->prepare("
                DELETE e FROM eventi e
                INNER JOIN misurazioni m ON m.id_misurazione = e.id_misurazione
                INNER JOIN dispositivi d ON d.id_dispositivo = m.id_dispositivo
                WHERE d.id_stanza = :id
            ")->execute(['id' => $idDel]);

            // 3. Elimina le misurazioni dei dispositivi di questa stanza
            $stmtMis = $conn->prepare("
                DELETE m FROM misurazioni m
                INNER JOIN dispositivi d ON d.id_dispositivo = m.id_dispositivo
                WHERE d.id_stanza = :id
            ");
            $stmtMis->execute(['id' => $idDel]);
            $totalMisurazioni = $stmtMis->rowCount();

            // 4. Elimina i dispositivi della stanza
            $conn->prepare("DELETE FROM dispositivi WHERE id_stanza = :id")->execute(['id' => $idDel]);

            // 5. Elimina la stanza
            $conn->prepare("DELETE FROM stanze WHERE id_stanza = :id")->execute(['id' => $idDel]);

            $_SESSION['flash'] = "Stanza eliminata con successo. Rimossi {$totalDispositivi} dispositivi e {$totalMisurazioni} misurazioni associate.";
            header("Location: index.php");
            exit;

        } catch (PDOException $e) {
            $errore = 'Errore durante l\'eliminazione: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Carica la stanza dal DB ───────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM stanze WHERE id_stanza = :id");
$stmt->execute(['id' => $id]);
$stanza = $stmt->fetch();

if (!$stanza) {
    header("Location: index.php");
    exit;
}

// ── Gestione MODIFICA ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'modifica') {

    $nome       = trim($_POST['nome']       ?? '');
    $volumetria = trim($_POST['volumetria'] ?? '');

    if ($nome === '') {
        $errore = 'Il nome della stanza è obbligatorio.';
    } elseif ($volumetria === '' || !is_numeric($volumetria) || (int)$volumetria <= 0) {
        $errore = 'Inserisci una volumetria valida (numero intero positivo).';
    } else {
        // Controlla duplicati di nome (escludendo sé stessa)
        $stmtChk = $conn->prepare("SELECT COUNT(*) FROM stanze WHERE nome = :nome AND id_stanza != :id");
        $stmtChk->execute(['nome' => $nome, 'id' => $id]);
        if ($stmtChk->fetchColumn() > 0) {
            $errore = 'Esiste già un\'altra stanza con il nome "' . htmlspecialchars($nome) . '".';
        }
    }

    if ($errore === '') {
        try {
            $conn->prepare("UPDATE stanze SET nome = :nome, volumetria = :vol WHERE id_stanza = :id")
                 ->execute(['nome' => $nome, 'vol' => (int)$volumetria, 'id' => $id]);

            // Ricarica dati
            $stmt = $conn->prepare("SELECT * FROM stanze WHERE id_stanza = :id");
            $stmt->execute(['id' => $id]);
            $stanza  = $stmt->fetch();
            $success = 'Stanza aggiornata con successo!';

        } catch (PDOException $e) {
            $errore = 'Errore durante l\'aggiornamento: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Conta dispositivi e misurazioni associate (per il messaggio di conferma)
$stmtInfo = $conn->prepare("
    SELECT COUNT(DISTINCT d.id_dispositivo) AS num_disp,
           COUNT(m.id_misurazione)          AS num_mis
    FROM dispositivi d
    LEFT JOIN misurazioni m ON m.id_dispositivo = d.id_dispositivo
    WHERE d.id_stanza = :id
");
$stmtInfo->execute(['id' => $id]);
$infoEliminazione = $stmtInfo->fetch();

$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;
$paginaAttiva = 'stanze';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Modifica Stanza</title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">Modifica Stanza</div>
      <div class="topbar__subtitle"><?= htmlspecialchars($stanza['nome']) ?></div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="index.php" class="btn btn--ghost btn--sm">← Torna alle stanze</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);max-width:540px;display:flex;flex-direction:column;gap:var(--sp-5)">

    <!-- Flash successo -->
    <?php if ($success !== ''): ?>
    <div class="alert-banner alert-banner--info">
      <span>✅</span>
      <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>

    <!-- Errore -->
    <?php if ($errore !== ''): ?>
    <div class="alert-banner alert-banner--danger">
      <span>⚠</span>
      <span><?= htmlspecialchars($errore) ?></span>
    </div>
    <?php endif; ?>

    <!-- Form modifica -->
    <div class="card">
      <div class="card__header">
        <div>
          <div class="card__title">🏠 Modifica dati stanza</div>
          <div class="card__subtitle">Puoi modificare nome e metratura</div>
        </div>
      </div>

      <form method="POST" action="modifica.php?id=<?= $id ?>"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">
        <input type="hidden" name="azione" value="modifica">

        <!-- Nome -->
        <div class="form-group">
          <label class="form-label" for="nome" style="font-size:11px;color:var(--text-muted)">
            Nome stanza *
          </label>
          <input class="input" type="text" id="nome" name="nome"
                 value="<?= htmlspecialchars($stanza['nome']) ?>" required>
        </div>

        <!-- Volumetria -->
        <div class="form-group">
          <label class="form-label" for="volumetria" style="font-size:11px;color:var(--text-muted)">
            Metratura (m³) *
          </label>
          <input class="input" type="number" id="volumetria" name="volumetria"
                 min="1" value="<?= htmlspecialchars($stanza['volumetria']) ?>" required>
        </div>

        <div style="display:flex;gap:var(--sp-3);margin-top:var(--sp-2)">
          <button type="submit" class="btn btn--primary">✓ Salva modifiche</button>
          <a href="index.php" class="btn btn--ghost">Annulla</a>
        </div>
      </form>
    </div>

    <!-- Zona pericolosa -->
    <div class="card" style="border-color:rgba(240,64,96,0.25)">
      <div class="card__header">
        <div>
          <div class="card__title" style="color:var(--danger)">⚠ Zona pericolosa</div>
          <div class="card__subtitle">Eliminazione definitiva della stanza e di tutto ciò che contiene</div>
        </div>
      </div>

      <div style="padding:14px;background:var(--danger-dim);border-radius:var(--radius-md);
                  font-size:12px;color:var(--text-secondary);margin-bottom:var(--sp-4)">
        <strong style="color:var(--danger)">Attenzione:</strong>
        L'eliminazione rimuoverà anche
        <strong style="color:var(--text-primary)"><?= (int)$infoEliminazione['num_disp'] ?> dispositivi</strong>
        e
        <strong style="color:var(--text-primary)"><?= (int)$infoEliminazione['num_mis'] ?> misurazioni</strong>
        associate. L'operazione è <strong>irreversibile</strong>.
      </div>

      <button class="btn btn--sm" onclick="apriModalElimina()"
              style="background:transparent;border:1px solid var(--danger);
                     color:var(--danger);padding:8px 18px;border-radius:var(--radius-md)">
        🗑 Elimina stanza
      </button>
    </div>

  </div>
</main>
</div>

<!-- ── Modal di conferma eliminazione stanza ──────────────── -->
<div class="modal-overlay" id="modal-elimina">
  <div class="modal modal--danger">
    <span class="modal__icon">🗑</span>
    <h2 class="modal__title" style="color:var(--danger)">Conferma eliminazione</h2>
    <p class="modal__desc" style="text-align:center;line-height:1.6">
      Stai per eliminare la stanza<br>
      <strong style="color:var(--text-primary)"><?= htmlspecialchars($stanza['nome']) ?></strong><br>
      con <strong style="color:var(--danger)"><?= (int)$infoEliminazione['num_disp'] ?> dispositivi</strong>
      e <strong style="color:var(--danger)"><?= (int)$infoEliminazione['num_mis'] ?> misurazioni</strong>.<br>
      <span style="font-size:11px;color:var(--text-muted)">Questa operazione è irreversibile.</span>
    </p>
    <div class="modal__actions">
      <form method="POST" action="modifica.php?id=<?= $id ?>">
        <input type="hidden" name="azione" value="elimina">
        <input type="hidden" name="id_stanza" value="<?= $id ?>">
        <button type="submit" class="btn btn--danger">
          Sì, elimina definitivamente
        </button>
      </form>
      <button class="btn btn--ghost" onclick="chiudiModalElimina()">Annulla</button>
    </div>
  </div>
</div>

<script>
function apriModalElimina()  { document.getElementById('modal-elimina').classList.add('open'); }
function chiudiModalElimina(){ document.getElementById('modal-elimina').classList.remove('open'); }
document.getElementById('modal-elimina').addEventListener('click', function(e) {
  if (e.target === this) chiudiModalElimina();
});
</script>
</body>
</html>
