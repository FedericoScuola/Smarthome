<?php
// ============================================================
//  lib/telegram_notifiche.php
//  Notifiche Telegram per il sistema Smarthome.
//
//  Copia questo file in:  Smarthome/lib/telegram_notifiche.php
//
//  Poi includi dove serve:
//    require_once '../lib/telegram_notifiche.php';
//
//  Funzioni disponibili:
//    telegram_soglia($conn, $id_dispositivo, $valore)
//    telegram_incendio($stanza)
//    telegram_dispositivo_offline($nome, $stanza)
//    telegram_nuovo_utente($nome, $cognome, $ruolo)
//    telegram_batteria_scarica($nome, $stanza, $percentuale)
//    telegram_messaggio($testo)
// ============================================================

// ── CREDENZIALI ──────────────────────────────────────────────
if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', '8695518975:AAEqW0R5lGepbmSU1u-ed1JtSO3ROEmIezc');
}
if (!defined('TELEGRAM_CHAT_ID')) {
    // Chat ID gruppo: il # va tolto, il segno meno va mantenuto
    define('TELEGRAM_CHAT_ID', '-1003707216861');
}

// ── FUNZIONE BASE ─────────────────────────────────────────────
function telegram_send(string $testo, bool $markdown = true): bool {
    $url  = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text'    => $testo,
    ];
    if ($markdown) {
        $data['parse_mode'] = 'MarkdownV2';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log("[Telegram] Invio fallito. HTTP $httpCode - $response");
        return false;
    }

    $json = json_decode($response, true);
    if (!($json['ok'] ?? false)) {
        error_log("[Telegram] API error: " . ($json['description'] ?? 'sconosciuto'));
        return false;
    }

    return true;
}

// ── Escape per MarkdownV2 ─────────────────────────────────────
function tg_esc(string $s): string {
    return preg_replace('/([_\*\[\]\(\)~`>#+\-=|{}\.!\\\\])/', '\\\\$1', $s);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 1: Sensore fuori soglia
//  Chiamato da api/inserisci_misurazione.php ad ogni lettura
// ─────────────────────────────────────────────────────────────
function telegram_soglia(PDO $conn, int $id_dispositivo, float $valore): bool {
    $stmt = $conn->prepare(
        "SELECT d.nome AS dispositivo, d.unita_misura,
                d.soglia_minima, d.soglia_massima,
                s.nome AS stanza
         FROM dispositivi d
         JOIN stanze s ON s.id_stanza = d.id_stanza
         WHERE d.id_dispositivo = :id LIMIT 1"
    );
    $stmt->execute(['id' => $id_dispositivo]);
    $d = $stmt->fetch();
    if (!$d) return false;

    $sMin = $d['soglia_minima']  !== null ? (float)$d['soglia_minima']  : null;
    $sMax = $d['soglia_massima'] !== null ? (float)$d['soglia_massima'] : null;

    if ($sMin !== null && $valore < $sMin) {
        $tipoSoglia = 'minima';
        $soglia     = $sMin;
        $freccia    = '📉';
    } elseif ($sMax !== null && $valore > $sMax) {
        $tipoSoglia = 'massima';
        $soglia     = $sMax;
        $freccia    = '📈';
    } else {
        return false;
    }

    $unita  = tg_esc($d['unita_misura'] ?? '');
    $stanza = tg_esc($d['stanza']);
    $disp   = tg_esc($d['dispositivo']);
    $valStr = tg_esc(number_format($valore, 2));
    $sogStr = tg_esc(number_format($soglia, 2));
    $ora    = tg_esc(date('d/m/Y H:i:s'));

    $msg = "⚠️ *SOGLIA SUPERATA*\n\n"
         . "🏠 Stanza: *{$stanza}*\n"
         . "📡 Sensore: *{$disp}*\n"
         . "{$freccia} Valore: *{$valStr} {$unita}*\n"
         . "🔔 Soglia {$tipoSoglia}: *{$sogStr} {$unita}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 2: Incendio rilevato
//
//  Integrare in home/index.php dopo cercaIncendioAttivo():
//
//    $fireRow = cercaIncendioAttivo($conn);
//    if ($fireRow && empty($_SESSION['fire_notif_' . md5($fireRow['stanza'] . $fireRow['timestamp'])])) {
//        telegram_incendio($fireRow['stanza']);
//        $_SESSION['fire_notif_' . md5($fireRow['stanza'] . $fireRow['timestamp'])] = true;
//    }
// ─────────────────────────────────────────────────────────────
function telegram_incendio(string $stanza): bool {
    $s   = tg_esc($stanza);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "🚨🔥 *INCENDIO RILEVATO\!* 🔥🚨\n\n"
         . "🏠 Stanza: *{$s}*\n"
         . "🕐 {$ora}\n\n"
         . "‼️ _Intervieni immediatamente e chiama i soccorsi se necessario\\._";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 3: Dispositivo offline (nessuna lettura da 30 min)
//
//  Da usare in cron_check_offline.php con questa query:
//
//    SELECT d.nome, s.nome AS stanza
//    FROM dispositivi d
//    JOIN stanze s ON s.id_stanza = d.id_stanza
//    LEFT JOIN misurazioni m
//      ON m.id_dispositivo = d.id_dispositivo
//      AND m.timestamp >= NOW() - INTERVAL 30 MINUTE
//    WHERE d.tipo = 'Sensore' AND m.id_misurazione IS NULL
// ─────────────────────────────────────────────────────────────
function telegram_dispositivo_offline(string $nome, string $stanza): bool {
    $n   = tg_esc($nome);
    $s   = tg_esc($stanza);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "📵 *DISPOSITIVO OFFLINE*\n\n"
         . "🏠 Stanza: *{$s}*\n"
         . "📡 Dispositivo: *{$n}*\n"
         . "⏱ Nessuna lettura da 30 minuti\n"
         . "🕐 {$ora}\n\n"
         . "_Verifica la connessione del sensore\\._";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 4: Nuovo utente registrato
//  Da chiamare in utenti/crea.php dopo l'INSERT
// ─────────────────────────────────────────────────────────────
function telegram_nuovo_utente(string $nome, string $cognome, string $ruolo): bool {
    $n   = tg_esc($nome . ' ' . $cognome);
    $r   = tg_esc($ruolo);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "👤 *NUOVO UTENTE REGISTRATO*\n\n"
         . "🙍 Nome: *{$n}*\n"
         . "🔑 Ruolo: *{$r}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 5: Batteria scarica
//  Da chiamare quando la percentuale scende sotto il 20%
// ─────────────────────────────────────────────────────────────
function telegram_batteria_scarica(string $nome, string $stanza, int $percentuale): bool {
    $n     = tg_esc($nome);
    $s     = tg_esc($stanza);
    $p     = tg_esc((string)$percentuale);
    $ora   = tg_esc(date('d/m/Y H:i:s'));
    $emoji = $percentuale <= 5 ? '🪫' : '🔋';

    $msg = "{$emoji} *BATTERIA SCARICA*\n\n"
         . "🏠 Stanza: *{$s}*\n"
         . "📡 Dispositivo: *{$n}*\n"
         . "⚡ Batteria: *{$p}%*\n"
         . "🕐 {$ora}\n\n"
         . "_Sostituisci o ricarica il dispositivo\\._";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  MESSAGGIO LIBERO — test rapido
//  telegram_messaggio("Sistema smarthome avviato!");
// ─────────────────────────────────────────────────────────────
function telegram_messaggio(string $testo): bool {
    return telegram_send($testo, false);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 6: Login utente
//  Da chiamare in login/auth.php quando un utente effettua il login
// ─────────────────────────────────────────────────────────────
function telegram_login(string $nome, string $cognome, string $email): bool {
    $n   = tg_esc($nome . ' ' . $cognome);
    $e   = tg_esc($email);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "✅ *ACCESSO AL SISTEMA*\n\n"
         . "👤 Utente: *{$n}*\n"
         . "📧 Email: *{$e}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 7: Logout utente
//  Da chiamare in lib/logout.php quando un utente effettua il logout
// ─────────────────────────────────────────────────────────────
function telegram_logout(string $nome, string $cognome): bool {
    $n   = tg_esc($nome . ' ' . $cognome);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "🚪 *USCITA DAL SISTEMA*\n\n"
         . "👤 Utente: *{$n}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 8: Nuovo utente registrato (versione estesa)
//  Da chiamare in utenti/crea.php dopo l'INSERT di un nuovo utente
// ─────────────────────────────────────────────────────────────
function telegram_nuovo_utente_full(string $nome, string $cognome, string $ruolo, string $email): bool {
    $n   = tg_esc($nome . ' ' . $cognome);
    $r   = tg_esc($ruolo);
    $e   = tg_esc($email);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "👤 *NUOVO UTENTE REGISTRATO*\n\n"
         . "🙍 Nome: *{$n}*\n"
         . "🔑 Ruolo: *{$r}*\n"
         . "📧 Email: *{$e}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 9: Nuovo dispositivo aggiunto
//  Da chiamare in dispositivi/crea.php dopo l'INSERT di un nuovo dispositivo
// ─────────────────────────────────────────────────────────────
function telegram_nuovo_dispositivo(string $nome, string $stanza): bool {
    $n   = tg_esc($nome);
    $s   = tg_esc($stanza);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "📡 *NUOVO DISPOSITIVO AGGIUNTO*\n\n"
         . "🏠 Stanza: *{$s}*\n"
         . "📱 Dispositivo: *{$n}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}

// ─────────────────────────────────────────────────────────────
//  EVENTO 10: Nuova stanza aggiunta
//  Da chiamare in stanze/crea.php dopo l'INSERT di una nuova stanza
// ─────────────────────────────────────────────────────────────
function telegram_nuova_stanza(string $nome): bool {
    $n   = tg_esc($nome);
    $ora = tg_esc(date('d/m/Y H:i:s'));

    $msg = "🏠 *NUOVA STANZA AGGIUNTA*\n\n"
         . "📍 Stanza: *{$n}*\n"
         . "🕐 {$ora}";

    return telegram_send($msg);
}
