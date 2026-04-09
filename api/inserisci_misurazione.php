<?php
// ============================================================
//  api/inserisci_misurazione.php  (con notifiche Telegram)
//  Riceve i dati via GET, inserisce la misurazione e invia
//  una notifica Telegram se il valore supera la soglia.
//
//  Uso: api/inserisci_misurazione.php?id_dispositivo=1&valore=23.5
// ============================================================

include_once '../lib/conn.php';
include_once '../lib/telegram_notifiche.php';

$id_dispositivo = $_GET['id_dispositivo'] ?? '';
$valore         = $_GET['valore']         ?? '';

if ($id_dispositivo === '' || $valore === '') {
    http_response_code(400);
    echo "Errore: parametri mancanti. Usa ?id_dispositivo=X&valore=Y";
    exit;
}

if (!is_numeric($id_dispositivo) || !is_numeric($valore)) {
    http_response_code(400);
    echo "Errore: id_dispositivo e valore devono essere numerici.";
    exit;
}

$id_dispositivo = (int)$id_dispositivo;
$valore         = (float)$valore;

$stmt = $conn->prepare(
    "SELECT id_dispositivo, nome, tipo FROM dispositivi WHERE id_dispositivo = :id LIMIT 1"
);
$stmt->execute(['id' => $id_dispositivo]);
$dispositivo = $stmt->fetch();

if (!$dispositivo) {
    http_response_code(404);
    echo "Errore: dispositivo con id $id_dispositivo non trovato.";
    exit;
}

if ($dispositivo['tipo'] !== 'Sensore') {
    http_response_code(400);
    echo "Errore: il dispositivo \"" . $dispositivo['nome'] . "\" e' un Attuatore, non un Sensore.";
    exit;
}

$insert = $conn->prepare(
    "INSERT INTO misurazioni (id_dispositivo, valore) VALUES (:id, :valore)"
);
$insert->execute([
    'id'     => $id_dispositivo,
    'valore' => $valore,
]);

// Notifica Telegram se fuori soglia (la funzione controlla da sola)
telegram_soglia($conn, $id_dispositivo, $valore);

echo "OK: misurazione inserita. Dispositivo=\"" . $dispositivo['nome'] . "\" Valore=$valore";
?>
