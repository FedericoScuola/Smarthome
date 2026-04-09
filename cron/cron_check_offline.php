<?php
// ============================================================
//  cron/cron_check_offline.php
//  Controlla sensori senza letture recenti e notifica Telegram.
//
//  Configurare come cron job su Oracle VirtualBox (Linux):
//
//  1. Aprire il crontab:
//       crontab -e
//
//  2. Aggiungere questa riga per eseguirlo ogni 30 minuti:
//       */30 * * * * php /var/www/html/Smarthome/cron/cron_check_offline.php >> /var/log/smarthome_cron.log 2>&1
//
//  3. Salvare e uscire.
// ============================================================

// Percorso assoluto necessario per i cron job
$baseDir = dirname(__DIR__);
require_once $baseDir . '/lib/conn.php';
require_once $baseDir . '/lib/telegram_notifiche.php';

// Cerca sensori che non hanno mandato misurazioni negli ultimi 30 minuti
$sql = "SELECT d.id_dispositivo, d.nome, s.nome AS stanza
        FROM dispositivi d
        JOIN stanze s ON s.id_stanza = d.id_stanza
        LEFT JOIN misurazioni m
          ON m.id_dispositivo = d.id_dispositivo
          AND m.timestamp >= NOW() - INTERVAL 30 MINUTE
        WHERE d.tipo = 'Sensore'
          AND m.id_misurazione IS NULL";

$sensoriOffline = $conn->query($sql)->fetchAll();

if (empty($sensoriOffline)) {
    echo date('d/m/Y H:i:s') . " — Tutti i sensori sono attivi.\n";
    exit;
}

foreach ($sensoriOffline as $s) {
    $risultato = telegram_dispositivo_offline($s['nome'], $s['stanza']);
    $stato = $risultato ? 'OK' : 'ERRORE';
    echo date('d/m/Y H:i:s') . " — Offline: {$s['nome']} ({$s['stanza']}) — Telegram: {$stato}\n";
}
