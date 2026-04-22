<?php
session_start();
require_once 'telegram_notifiche.php';
require_once 'email_notifiche.php';
require_once 'conn.php';

$nome = $_SESSION['nome'] ?? 'Utente';
$cognome = $_SESSION['cognome'] ?? '';
$id_utente = $_SESSION['utente'] ?? 0;

// ── Invia notifiche di logout ────────────────────────────────
telegram_logout($nome, $cognome);
email_logout($nome, $cognome);

// ── Crea evento di logout nel database ───────────────────────
if ($id_utente > 0) {
    $stmtEvento = $conn->prepare(
        "INSERT INTO eventi (id_tipo, id_dispositivo, timestamp)
         VALUES (3, 1, NOW())"
    );
    $stmtEvento->execute();
    $id_evento = $conn->lastInsertId();
    
    // ── Crea notifica nel database ──────────────────────────────
    $stmtNotif = $conn->prepare(
        "INSERT INTO notifiche (id_evento, id_utente, testo, tipo_notifica, timestamp_invio)
         VALUES (:id_ev, :id_ut, :testo, :tipo, NOW())"
    );
    
    $stmtNotif->execute([
        'id_ev' => $id_evento,
        'id_ut' => $id_utente,
        'testo' => "Logout di $nome $cognome",
        'tipo' => 'Telegram'
    ]);
    
    $stmtNotif->execute([
        'id_ev' => $id_evento,
        'id_ut' => $id_utente,
        'testo' => "Logout di $nome $cognome",
        'tipo' => 'Email'
    ]);
}

session_destroy();
header("Location: ../login/index.php");
exit;
?>
