<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    include_once '../lib/conn.php';
    include_once '../lib/telegram_notifiche.php';
    include_once '../lib/email_notifiche.php';

    $email    = $_POST['email'];
    $password = $_POST['password'];

    $sql    = "SELECT * FROM utenti WHERE email = :email LIMIT 1";
    $stmt   = $conn->prepare($sql);
    $stmt->execute(['email' => $email]);

    if ($stmt->rowCount() === 1) {
        $utente = $stmt->fetch();

        // Supporta sia bcrypt che password in chiaro (demo DB)
        $ok = password_verify($password, $utente['password'])
           || $utente['password'] === $password;

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['utente']   = $utente['id_utente'];
            $_SESSION['nome']     = $utente['nome'];
            $_SESSION['cognome']  = $utente['cognome'];
            $_SESSION['ruolo']    = $utente['ruolo'];
            $_SESSION['email']    = $utente['email'];
            $_SESSION['telegram'] = $utente['chiave_telegram'];
            $_SESSION['avatar']   = $utente['immagine_profilo'];
            
            // ── Invia notifiche di login ────────────────────────────────
            telegram_login($utente['nome'], $utente['cognome'], $utente['email']);
            email_login($utente['nome'], $utente['cognome'], $utente['email']);
            
            // ── Crea evento di login nel database ─────────────────────
            $stmtEvento = $conn->prepare(
                "INSERT INTO eventi (id_tipo, id_dispositivo, timestamp)
                 VALUES (2, 1, NOW())"
            );
            $stmtEvento->execute();
            $id_evento = $conn->lastInsertId();
            
            // ── Crea notifica nel database ────────────────────────────
            $stmtNotif = $conn->prepare(
                "INSERT INTO notifiche (id_evento, id_utente, testo, tipo_notifica, timestamp_invio)
                 VALUES (:id_ev, :id_ut, :testo, :tipo, NOW())"
            );
            $stmtNotif->execute([
                'id_ev' => $id_evento,
                'id_ut' => $utente['id_utente'],
                'testo' => "Accesso di {$utente['nome']} {$utente['cognome']}",
                'tipo' => 'Telegram'
            ]);
            
            $stmtNotif->execute([
                'id_ev' => $id_evento,
                'id_ut' => $utente['id_utente'],
                'testo' => "Accesso di {$utente['nome']} {$utente['cognome']}",
                'tipo' => 'Email'
            ]);
            
            header("Location: ../home/index.php");
            exit;
        }
    }

    header("Location: index.php?error=1");
    exit;
}
?>
