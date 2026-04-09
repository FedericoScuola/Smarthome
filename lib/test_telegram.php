<?php
require_once 'telegram_notifiche.php';

$ok = telegram_messaggio("Test Smarthome: notifica Telegram funzionante!");

echo $ok ? "✅ Messaggio inviato con successo!" : "❌ Errore nell'invio, controlla i log.";
?>