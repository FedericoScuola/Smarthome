<?php
// ============================================================
//  notifiche/index.php
//  Pagina dedicata alle notifiche e agli eventi.
//  Visibile a tutti gli utenti (Proprietario e Ospite).
//  I Proprietari vedono le notifiche di tutti;
//  gli Ospiti vedono solo le proprie.
// ============================================================

// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}

// ── Include librerie condivise ────────────────────────────────
require_once '../lib/conn.php';
require_once '../lib/helpers.php';

$id_utente = $_SESSION['utente'];
$isOwner   = ($_SESSION['ruolo'] === 'Proprietario');

// ── Carica notifiche dal DB ───────────────────────────────────
// Il Proprietario vede tutte le notifiche di tutti gli utenti.
// L'Ospite vede solo le notifiche associate al suo id_utente.
if ($isOwner) {
    $notifiche = $conn->query(
        "SELECT
             n.id_notifica,
             n.tipo_notifica,
             n.timestamp_invio,
             n.testo,
             t.descrizione  AS tipo_evento,
             t.id_tipo,
             d.nome         AS dispositivo,
             s.nome         AS stanza,
             u.nome         AS utente_nome,
             u.cognome      AS utente_cognome
         FROM notifiche n
         JOIN eventi e      ON e.id_evento      = n.id_evento
         JOIN tipo_evento t ON t.id_tipo        = e.id_tipo
         JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
         JOIN stanze s      ON s.id_stanza      = d.id_stanza
         JOIN utenti u      ON u.id_utente      = n.id_utente
         ORDER BY n.timestamp_invio DESC"
    )->fetchAll();
} else {
    // Ospite: solo le sue notifiche
    $stmt = $conn->prepare(
        "SELECT
             n.id_notifica,
             n.tipo_notifica,
             n.timestamp_invio,
             n.testo,
             t.descrizione  AS tipo_evento,
             t.id_tipo,
             d.nome         AS dispositivo,
             s.nome         AS stanza
         FROM notifiche n
         JOIN eventi e      ON e.id_evento      = n.id_evento
         JOIN tipo_evento t ON t.id_tipo        = e.id_tipo
         JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
         JOIN stanze s      ON s.id_stanza      = d.id_stanza
         WHERE n.id_utente = :uid
         ORDER BY n.timestamp_invio DESC"
    );
    $stmt->execute(['uid' => $id_utente]);
    $notifiche = $stmt->fetchAll();
}

// ── Contatori per le card statistiche ────────────────────────
$totale    = count($notifiche);
$nEmail    = 0;
$nTelegram = 0;
foreach ($notifiche as $n) {
    if ($n['tipo_notifica'] === 'Email')    $nEmail++;
    if ($n['tipo_notifica'] === 'Telegram') $nTelegram++;
}

// ── Incendio attivo (per la sidebar) ─────────────────────────
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

// ── Voce attiva nella sidebar ─────────────────────────────────
$paginaAttiva = 'notifiche';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Notifiche</title>
  <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <!-- Barra superiore -->
  <header class="topbar">
    <div>
      <div class="topbar__title">Notifiche &amp; Eventi</div>
      <div class="topbar__subtitle">
        <?= $isOwner ? 'Tutti gli utenti' : 'Le tue notifiche' ?>
      </div>
    </div>
    <?php if ($hasFire): ?>
    <div class="topbar__spacer"></div>
    <div class="alert-banner alert-banner--danger" style="padding:6px 14px;font-size:12px">
      <span>🔥</span>
      <span>INCENDIO — <?= htmlspecialchars($fireRow['stanza']) ?></span>
    </div>
    <?php endif; ?>
  </header>

  <!-- ── Card statistiche ─────────────────────────────────── -->
  <div class="grid-4" style="margin-bottom:var(--sp-4)">

    <div class="card stat-card">
      <span class="stat-card__label">🔔 Totale notifiche</span>
      <div class="stat-card__value">
        <?= $totale ?><span class="stat-card__unit"> totali</span>
      </div>
      <div class="stat-card__trend">Tutte le notifiche ricevute</div>
    </div>

    <div class="card stat-card">
      <span class="stat-card__label">✉️ Email inviate</span>
      <div class="stat-card__value">
        <?= $nEmail ?><span class="stat-card__unit"> email</span>
      </div>
      <div class="stat-card__trend">Notifiche via posta</div>
    </div>

    <div class="card stat-card">
      <span class="stat-card__label">💬 Telegram inviati</span>
      <div class="stat-card__value">
        <?= $nTelegram ?><span class="stat-card__unit"> messaggi</span>
      </div>
      <div class="stat-card__trend">Notifiche push Telegram</div>
    </div>

    <?php if ($isOwner): ?>
    <div class="card stat-card <?= $hasFire ? 'card--danger' : '' ?>">
      <span class="stat-card__label">🔥 Incendi attivi</span>
      <div class="stat-card__value <?= $hasFire ? 'stat-card__value--danger' : '' ?>">
        <?= $hasFire ? '1' : '0' ?><span class="stat-card__unit"> attivi</span>
      </div>
      <div class="stat-card__trend">
        <?= $hasFire ? '🏠 ' . htmlspecialchars($fireRow['stanza']) : '✓ Nessun incendio' ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- ── Tabella notifiche ─────────────────────────────────── -->
  <div class="card">
    <div class="card__header">
      <div>
        <div class="card__title">Storico notifiche</div>
        <div class="card__subtitle">
          <?= $totale ?> notifiche trovate
        </div>
      </div>
    </div>

    <?php if (empty($notifiche)): ?>
      <div style="padding:32px;text-align:center;color:var(--text-muted)">
        Nessuna notifica trovata.
      </div>
    <?php else: ?>

    <table class="data-table">
      <thead>
        <tr>
          <th>Data / Ora</th>
          <th>Canale</th>
         
          
          <th>Utente</th>
          <?php if ($isOwner): ?><th>Utente</th><?php endif; ?>
          
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notifiche as $n):
          // Colore riga in base al tipo di evento
          $isAlert = in_array($n['id_tipo'], [1, 2, 3, 4, 5]);
          $colTesto = ($n['id_tipo'] == 1)
                    ? 'var(--danger)'
                    : ($isAlert ? 'var(--warn)' : 'inherit');

          // Icona in base al tipo
          $icona = ($n['id_tipo'] == 1) ? '🔥' : ($isAlert ? '⚠️' : '✅');

          // Badge canale
          $badgeClasse = ($n['tipo_notifica'] === 'Telegram') ? 'badge--warn' : 'badge--ok';
          $badgeIcona  = ($n['tipo_notifica'] === 'Telegram') ? '💬' : '✉️';
        ?>
        <tr>

          <!-- Data e ora -->
          <td style="font-family:var(--font-mono);font-size:12px;color:var(--text-muted)">
            <?= date('d/m/Y', strtotime($n['timestamp_invio'])) ?><br>
            <?= date('H:i:s', strtotime($n['timestamp_invio'])) ?>
          </td>

          <!-- Canale: Email o Telegram -->
          <td>
            <span class="badge <?= $badgeClasse ?>">
              <?= $badgeIcona ?> <?= htmlspecialchars($n['tipo_notifica']) ?>
            </span>
          </td>

         

        

         

          <!-- Utente (solo Proprietario) -->
          <?php if ($isOwner): ?>
          <td style="font-size:12px;color:var(--text-muted)">
            <?= htmlspecialchars($n['utente_nome'] . ' ' . $n['utente_cognome']) ?>
          </td>
          <?php endif; ?>

          <!-- Testo notifica -->
          <td style="font-size:12px"><?= htmlspecialchars($n['testo']) ?></td>

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php endif; ?>
  </div>

</main>
</div>

<script>
  // Toggle sidebar (stesso script delle altre pagine del progetto)
  var toggleBtn = document.getElementById('sidebarToggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('sidebar--open');
      document.getElementById('main-area').classList.toggle('main--shifted');
    });
  }
</script>

</body>
</html>
