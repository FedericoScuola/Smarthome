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

// ── Recupera ID dispositivo dall'URL ─────────────────────────
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// ── Gestione ELIMINAZIONE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'elimina') {
    $idDel = (int)($_POST['id_dispositivo'] ?? 0);

    if ($idDel <= 0) {
        $errore = 'ID dispositivo non valido.';
    } else {
        try {
            // Sicurezza referenziale:
            // 1. eventi ha FK su misurazioni.id_misurazione → elimina prima gli eventi
            //    legati alle misurazioni di questo dispositivo
            $conn->prepare("
                DELETE e FROM eventi e
                INNER JOIN misurazioni m ON m.id_misurazione = e.id_misurazione
                WHERE m.id_dispositivo = :id
            ")->execute(['id' => $idDel]);

            // 2. Ora si possono eliminare le misurazioni senza violare la FK
            $stmtMis = $conn->prepare("DELETE FROM misurazioni WHERE id_dispositivo = :id");
            $stmtMis->execute(['id' => $idDel]);
            $countMis = $stmtMis->rowCount();

            // 3. Elimina il dispositivo
            $stmtDel = $conn->prepare("DELETE FROM dispositivi WHERE id_dispositivo = :id");
            $stmtDel->execute(['id' => $idDel]);

            // Redirect con messaggio di conferma via session
            $_SESSION['flash'] = "Dispositivo eliminato con successo. Rimosse {$countMis} misurazioni associate.";
            header("Location: index.php");
            exit;

        } catch (PDOException $e) {
            $errore = 'Errore durante l\'eliminazione: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Carica il dispositivo dal DB ──────────────────────────────
$stmt = $conn->prepare("SELECT d.*, s.nome AS stanza_nome FROM dispositivi d
                         JOIN stanze s ON s.id_stanza = d.id_stanza
                         WHERE d.id_dispositivo = :id");
$stmt->execute(['id' => $id]);
$disp = $stmt->fetch();

if (!$disp) {
    header("Location: index.php");
    exit;
}

// ── Gestione MODIFICA ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'modifica') {

    $nome       = trim($_POST['nome']       ?? '');
    $soglia_min = trim($_POST['soglia_min'] ?? '');
    $soglia_max = trim($_POST['soglia_max'] ?? '');

    if ($nome === '') {
        $errore = 'Il nome del dispositivo è obbligatorio.';
    } else {
        try {
            if ($disp['tipo'] === 'Sensore') {
                $sql = "UPDATE dispositivi SET nome = :nome, soglia_minima = :min, soglia_massima = :max
                        WHERE id_dispositivo = :id";
                $conn->prepare($sql)->execute([
                    'nome' => $nome,
                    'min'  => ($soglia_min !== '') ? (float)$soglia_min : null,
                    'max'  => ($soglia_max !== '') ? (float)$soglia_max : null,
                    'id'   => $id,
                ]);
            } else {
                $sql = "UPDATE dispositivi SET nome = :nome WHERE id_dispositivo = :id";
                $conn->prepare($sql)->execute(['nome' => $nome, 'id' => $id]);
            }

            // Ricarica dati aggiornati
            $stmt = $conn->prepare("SELECT d.*, s.nome AS stanza_nome FROM dispositivi d
                                     JOIN stanze s ON s.id_stanza = d.id_stanza
                                     WHERE d.id_dispositivo = :id");
            $stmt->execute(['id' => $id]);
            $disp   = $stmt->fetch();
            $success = 'Dispositivo aggiornato con successo!';

        } catch (PDOException $e) {
            $errore = 'Errore durante l\'aggiornamento: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Conta le misurazioni associate (per il messaggio di conferma eliminazione)
$stmtCount = $conn->prepare("SELECT COUNT(*) FROM misurazioni WHERE id_dispositivo = :id");
$stmtCount->execute(['id' => $id]);
$numMisurazioni = $stmtCount->fetchColumn();

$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;
$paginaAttiva = 'dispositivi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Modifica Dispositivo</title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">Modifica Dispositivo</div>
      <div class="topbar__subtitle"><?= htmlspecialchars($disp['nome']) ?> · <?= htmlspecialchars($disp['stanza_nome']) ?></div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <a href="index.php" class="btn btn--ghost btn--sm">← Torna ai dispositivi</a>
    </div>
  </header>

  <div style="padding:var(--sp-6);max-width:580px;display:flex;flex-direction:column;gap:var(--sp-5)">

    <!-- Flash di successo -->
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
          <div class="card__title">
            <?= $disp['tipo'] === 'Sensore' ? '📡' : '💡' ?>
            Modifica dati dispositivo
          </div>
          <div class="card__subtitle">Stanza: <?= htmlspecialchars($disp['stanza_nome']) ?> · Tipo: <?= htmlspecialchars($disp['tipo']) ?></div>
        </div>
      </div>

      <form method="POST" action="modifica.php?id=<?= $id ?>"
            style="display:flex;flex-direction:column;gap:var(--sp-4)">
        <input type="hidden" name="azione" value="modifica">

        <!-- Nome -->
        <div class="form-group">
          <label class="form-label" for="nome" style="font-size:11px;color:var(--text-muted)">
            Nome dispositivo *
          </label>
          <input class="input" type="text" id="nome" name="nome"
                 value="<?= htmlspecialchars($disp['nome']) ?>" required>
        </div>

        <!-- Soglie (solo sensori) -->
        <?php if ($disp['tipo'] === 'Sensore'): ?>
        <div style="height:1px;background:var(--border-subtle)"></div>
        <div style="font-size:12px;color:var(--text-muted)">
          Soglie di allarme
          <span style="font-family:var(--font-mono);font-size:10px;margin-left:6px;
                       color:var(--brand);background:var(--brand-dim);padding:2px 8px;
                       border-radius:var(--radius-full)">
            Unità: <?= htmlspecialchars($disp['unita_misura']) ?>
          </span>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="soglia_min" style="font-size:11px;color:var(--text-muted)">
              Soglia minima
            </label>
            <input class="input" type="number" step="0.01" id="soglia_min" name="soglia_min"
                   placeholder="es. 18"
                   value="<?= $disp['soglia_minima'] !== null ? htmlspecialchars($disp['soglia_minima']) : '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="soglia_max" style="font-size:11px;color:var(--text-muted)">
              Soglia massima
            </label>
            <input class="input" type="number" step="0.01" id="soglia_max" name="soglia_max"
                   placeholder="es. 26"
                   value="<?= $disp['soglia_massima'] !== null ? htmlspecialchars($disp['soglia_massima']) : '' ?>">
          </div>
        </div>

        <div style="padding:10px 14px;background:var(--brand-dim);
                    border:1px solid rgba(0,212,180,0.2);border-radius:var(--radius-md);
                    font-size:11px;color:var(--text-muted)">
          💡 Lascia vuoto un campo per rimuovere la soglia corrispondente.
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:var(--sp-3);margin-top:var(--sp-2)">
          <button type="submit" class="btn btn--primary">✓ Salva modifiche</button>
          <a href="index.php" class="btn btn--ghost">Annulla</a>
        </div>
      </form>
    </div>

    <!-- Zona pericolosa: eliminazione -->
    <div class="card" style="border-color:rgba(240,64,96,0.25)">
      <div class="card__header">
        <div>
          <div class="card__title" style="color:var(--danger)">⚠ Zona pericolosa</div>
          <div class="card__subtitle">
            Eliminazione definitiva del dispositivo e di tutte le sue misurazioni
          </div>
        </div>
      </div>

      <div style="padding:14px;background:var(--danger-dim);border-radius:var(--radius-md);
                  font-size:12px;color:var(--text-secondary);margin-bottom:var(--sp-4)">
        <strong style="color:var(--danger)">Attenzione:</strong>
        L'eliminazione rimuoverà anche
        <strong style="color:var(--text-primary)"><?= $numMisurazioni ?> misurazioni</strong>
        associate a questo dispositivo. L'operazione è <strong>irreversibile</strong>.
      </div>

      <button class="btn btn--sm" onclick="apriModalElimina()"
              style="background:transparent;border:1px solid var(--danger);
                     color:var(--danger);padding:8px 18px;border-radius:var(--radius-md)">
        🗑 Elimina dispositivo
      </button>
    </div>

  </div>
</main>
</div>

<!-- ── Modal di conferma eliminazione ─────────────────────── -->
<div class="modal-overlay" id="modal-elimina">
  <div class="modal modal--danger">
    <span class="modal__icon">🗑</span>
    <h2 class="modal__title" style="color:var(--danger)">Conferma eliminazione</h2>
    <p class="modal__desc" style="text-align:center;line-height:1.6">
      Stai per eliminare il dispositivo<br>
      <strong style="color:var(--text-primary)"><?= htmlspecialchars($disp['nome']) ?></strong><br>
      e le sue <strong style="color:var(--danger)"><?= $numMisurazioni ?> misurazioni</strong> associate.<br>
      <span style="font-size:11px;color:var(--text-muted)">Questa operazione è irreversibile.</span>
    </p>
    <div class="modal__actions">
      <form method="POST" action="modifica.php?id=<?= $id ?>">
        <input type="hidden" name="azione" value="elimina">
        <input type="hidden" name="id_dispositivo" value="<?= $id ?>">
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
// Chiudi cliccando fuori dal modal
document.getElementById('modal-elimina').addEventListener('click', function(e) {
  if (e.target === this) chiudiModalElimina();
});
</script>
</body>
</html>
