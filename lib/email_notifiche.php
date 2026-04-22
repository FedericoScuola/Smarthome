<?php
// ============================================================
//  lib/email_notifiche.php
//  Invia email tramite Gmail quando un sensore supera la soglia.
//
//  NON serve scaricare nulla: usa direttamente PHP con SSL.
//
//  Includere dove serve:
//    require_once '../lib/email_notifiche.php';
//
//  Funzione disponibile:
//    email_soglia($sensore, $stanza, $valore, $unita, $tipoSoglia, $soglia)
// ============================================================


// ============================================================
// ⬇ MODIFICA QUESTI 4 VALORI ⬇
// ============================================================

// La tua email Gmail (mittente)
define('EMAIL_MITTENTE',     'nicologulino8@gmail.com');

// App Password Gmail (16 caratteri, con spazi: es. "abcd efgh ijkl mnop")
// Per generarla: myaccount.google.com → Sicurezza → Verifica in 2 passaggi → Password per le app
define('EMAIL_APP_PASSWORD', 'kfat pcsw rtvp htsl');

// Email destinatario fisso (dove arrivano le notifiche)
define('EMAIL_DESTINATARIO', 'nicologulino10@gmail.com');

// Nome visualizzato come mittente
define('EMAIL_NOME_MITTENTE', 'SmartHome Sistema');

// ============================================================
// ⬆ FINE ZONA MODIFICA ⬆
// ============================================================


// ── Funzione base: connette a Gmail via SSL e invia l'email ──
function email_invia_base(string $oggetto, string $corpo): bool {

    // Apre connessione SSL a Gmail (porta 465)
    $socket = stream_socket_client(
        'ssl://smtp.gmail.com:465',
        $errno,
        $errstr,
        10  // timeout in secondi
    );

    // Se non riesce a connettersi, registra l'errore e termina
    if (!$socket) {
        error_log("[Email] Connessione fallita: $errstr ($errno)");
        return false;
    }

    // Legge il messaggio di benvenuto di Gmail
    fgets($socket, 512);

    // Dice "ciao" al server
    fputs($socket, "EHLO localhost\r\n");

    // Legge tutte le righe di risposta finché non finisce
    while ($riga = fgets($socket, 512)) {
        if (substr($riga, 3, 1) === ' ') {
            break; // ultima riga della risposta EHLO
        }
    }

    // Inizia l'autenticazione
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 512); // risposta "334 Username:"

    // Manda l'email in base64
    fputs($socket, base64_encode(EMAIL_MITTENTE) . "\r\n");
    fgets($socket, 512); // risposta "334 Password:"

    // Manda la password in base64
    fputs($socket, base64_encode(EMAIL_APP_PASSWORD) . "\r\n");
    $rispostaAuth = fgets($socket, 512);

    // Se l'autenticazione fallisce (non risponde con 235), termina
    if (substr($rispostaAuth, 0, 3) !== '235') {
        error_log("[Email] Autenticazione fallita: $rispostaAuth");
        fclose($socket);
        return false;
    }

    // Mittente
    fputs($socket, "MAIL FROM: <" . EMAIL_MITTENTE . ">\r\n");
    fgets($socket, 512);

    // Destinatario
    fputs($socket, "RCPT TO: <" . EMAIL_DESTINATARIO . ">\r\n");
    fgets($socket, 512);

    // Inizia a mandare il messaggio
    fputs($socket, "DATA\r\n");
    fgets($socket, 512);

    // Costruisce l'email (intestazioni + corpo)
    $nomeBase64 = base64_encode(EMAIL_NOME_MITTENTE);
    $oggBase64  = base64_encode($oggetto);
    $corpoBase64 = base64_encode($corpo);

    $email  = "To: " . EMAIL_DESTINATARIO . "\r\n";
    $email .= "From: =?UTF-8?B?{$nomeBase64}?= <" . EMAIL_MITTENTE . ">\r\n";
    $email .= "Subject: =?UTF-8?B?{$oggBase64}?=\r\n";
    $email .= "MIME-Version: 1.0\r\n";
    $email .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $email .= "Content-Transfer-Encoding: base64\r\n";
    $email .= "\r\n";
    $email .= $corpoBase64 . "\r\n";
    $email .= ".\r\n"; // punto da solo = fine messaggio

    fputs($socket, $email);
    fgets($socket, 512);

    // Chiude la connessione
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}


// ── Notifica soglia superata ──────────────────────────────────
// Chiamata da api/inserisci_misurazione.php quando un sensore
// supera la soglia minima o massima.
//
// Parametri:
//   $sensore   — nome del dispositivo (es. "Sensore temperatura cucina")
//   $stanza    — nome della stanza (es. "Cucina")
//   $valore    — valore rilevato (es. 27.5)
//   $unita     — unità di misura (es. "°C")
//   $tipoSoglia — "minima" o "massima"
//   $soglia    — valore della soglia superata (es. 26.0)
// ─────────────────────────────────────────────────────────────
function email_soglia(
    string $sensore,
    string $stanza,
    float  $valore,
    string $unita,
    string $tipoSoglia,
    float  $soglia
): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "⚠️ SmartHome - Soglia superata: $sensore";

    $corpo = "Attenzione!\n\n"
           . "Il sensore \"$sensore\" nella stanza \"$stanza\"\n"
           . "ha superato la soglia $tipoSoglia.\n\n"
           . "Valore rilevato : $valore $unita\n"
           . "Soglia $tipoSoglia : $soglia $unita\n\n"
           . "Data e ora: $ora\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}


// ── Notifica incendio ─────────────────────────────────────────
// Chiamata da home/index.php quando viene rilevato un incendio.
//
// Parametri:
//   $stanza — nome della stanza interessata (es. "Cucina")
// ─────────────────────────────────────────────────────────────
function email_incendio(string $stanza): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "🔥 EMERGENZA - INCENDIO RILEVATO";

    $corpo = "ALLARME INCENDIO!\n\n"
           . "Un incendio è stato rilevato nella stanza:\n"
           . "$stanza\n\n"
           . "Data e ora: $ora\n\n"
           . "INTERVIENI IMMEDIATAMENTE!\n"
           . "Se necessario, chiama i soccorsi.\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}


// ── Notifica login ────────────────────────────────────────────
// Chiamata da login/auth.php quando un utente effettua il login.
//
// Parametri:
//   $nome    — nome utente
//   $cognome — cognome utente
//   $email   — email utente
// ─────────────────────────────────────────────────────────────
function email_login(string $nome, string $cognome, string $email): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "✅ Accesso riuscito al sistema SmartHome - $nome $cognome";

    $corpo = "Stato di accesso\n\n"
           . "Utente: $nome $cognome\n"
           . "Email: $email\n"
           . "Data e ora: $ora\n\n"
           . "Se non sei stato tu, contatta l'amministratore immediatamente.\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}


// ── Notifica logout ───────────────────────────────────────────
// Chiamata da lib/logout.php quando un utente effettua il logout.
//
// Parametri:
//   $nome    — nome utente
//   $cognome — cognome utente
// ─────────────────────────────────────────────────────────────
function email_logout(string $nome, string $cognome): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "🚪 Accesso terminato da SmartHome - $nome $cognome";

    $corpo = "Stato di uscita\n\n"
           . "Utente: $nome $cognome\n"
           . "Data e ora: $ora\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}


// ── Notifica nuovo utente ─────────────────────────────────────
// Chiamata da utenti/crea.php dopo l'INSERT di un nuovo utente.
//
// Parametri:
//   $nome      — nome del nuovo utente
//   $cognome   — cognome del nuovo utente
//   $ruolo     — ruolo assegnato (Proprietario, Ospite)
//   $email     — email del nuovo utente
// ─────────────────────────────────────────────────────────────
function email_nuovo_utente(string $nome, string $cognome, string $ruolo, string $email): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "👤 Nuovo utente registrato nel sistema SmartHome";

    $corpo = "Nuovo account creato\n\n"
           . "Nome: $nome $cognome\n"
           . "Email: $email\n"
           . "Ruolo: $ruolo\n"
           . "Data e ora: $ora\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}


// ── Notifica nuovo dispositivo ────────────────────────────────
// Chiamata da dispositivi/crea.php dopo l'INSERT di un nuovo dispositivo.
//
// Parametri:
//   $nome   — nome del dispositivo
//   $stanza — stanza in cui è posizionato
// ─────────────────────────────────────────────────────────────
function email_nuovo_dispositivo(string $nome, string $stanza): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "📡 Nuovo dispositivo aggiunto - $nome";

    $corpo = "Dispositivo registrato\n\n"
           . "Nome: $nome\n"
           . "Stanza: $stanza\n"
           . "Data e ora: $ora\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}


// ── Notifica nuova stanza ─────────────────────────────────────
// Chiamata da stanze/crea.php dopo l'INSERT di una nuova stanza.
//
// Parametri:
//   $nome — nome della nuova stanza
// ─────────────────────────────────────────────────────────────
function email_nuova_stanza(string $nome): bool {

    $ora = date('d/m/Y H:i:s');

    $oggetto = "🏠 Nuova stanza aggiunta - $nome";

    $corpo = "Stanza registrata\n\n"
           . "Nome: $nome\n"
           . "Data e ora: $ora\n\n"
           . "---\n"
           . "SmartHome Sistema";

    return email_invia_base($oggetto, $corpo);
}
