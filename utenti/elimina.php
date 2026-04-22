<?php
// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}

// Solo i proprietari possono eliminare utenti
if ($_SESSION['ruolo'] !== 'Proprietario') {
    header("Location: ../home/index.php");
    exit;
}

require_once '../lib/conn.php';
require_once '../lib/helpers.php';

// Accetta solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['azione']) || $_POST['azione'] !== 'elimina') {
    header("Location: index.php");
    exit;
}

$idDel = (int)($_POST['id_utente'] ?? 0);

if ($idDel <= 0) {
    $_SESSION['flash_error'] = 'ID utente non valido.';
    header("Location: index.php");
    exit;
}

// ── Sicurezza: non puoi eliminare te stesso ───────────────────
if ($idDel === (int)$_SESSION['utente']) {
    $_SESSION['flash_error'] = 'Non puoi eliminare il tuo stesso account.';
    header("Location: index.php");
    exit;
}

// ── Carica l'utente da eliminare ─────────────────────────────
$stmtCheck = $conn->prepare("SELECT id_utente, nome, cognome, ruolo FROM utenti WHERE id_utente = :id");
$stmtCheck->execute(['id' => $idDel]);
$utenteDaEliminare = $stmtCheck->fetch();

if (!$utenteDaEliminare) {
    $_SESSION['flash_error'] = 'Utente non trovato.';
    header("Location: index.php");
    exit;
}

// ── Sicurezza: non eliminare l'ultimo proprietario ────────────
if ($utenteDaEliminare['ruolo'] === 'Proprietario') {
    $stmtCountProp = $conn->prepare("SELECT COUNT(*) FROM utenti WHERE ruolo = 'Proprietario'");
    $stmtCountProp->execute();
    if ((int)$stmtCountProp->fetchColumn() <= 1) {
        $_SESSION['flash_error'] = 'Impossibile eliminare l\'unico proprietario del sistema.';
        header("Location: index.php");
        exit;
    }
}

// ── Eliminazione con transazione ─────────────────────────────
try {
    $conn->beginTransaction();

    // 1. Elimina gli eventi collegati alle misurazioni dei dispositivi
    //    assegnati ai sensori di questo utente (se la tabella notifiche
    //    è collegata all'utente, rimuovila prima)
    $conn->prepare(
        "DELETE FROM notifiche WHERE id_utente = :id"
    )->execute(['id' => $idDel]);

    // 2. Elimina l'utente
    $stmtDel = $conn->prepare("DELETE FROM utenti WHERE id_utente = :id");
    $stmtDel->execute(['id' => $idDel]);

    $conn->commit();

    $nomeCompleto = htmlspecialchars($utenteDaEliminare['nome'] . ' ' . $utenteDaEliminare['cognome']);
    $_SESSION['flash'] = "Utente \"{$nomeCompleto}\" eliminato con successo.";
    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['flash_error'] = 'Errore durante l\'eliminazione: ' . htmlspecialchars($e->getMessage());
    header("Location: index.php");
    exit;
}
