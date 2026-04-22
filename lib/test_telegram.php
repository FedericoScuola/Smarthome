<?php
require_once 'telegram_notifiche.php';

$ok = telegram_messaggio("Test Smarthome: notifica Telegram funzionante!");

echo $ok ? "✅ Messaggio inviato con successo!" : "❌ Errore nell'invio, controlla i log.";
?>
<?php
require_once 'email_notifiche.php';

$ok = email_soglia("Test Sensore", "Test Stanza", 27.5, "°C", "massima", 26.0);

echo $ok ? "✅ Email inviata con successo!" : "❌ Errore nell'invio, controlla i log.";
?>