<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    include_once '../lib/conn.php';

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
            header("Location: ../home/index.php");
            exit;
        }
    }

    header("Location: index.php?error=1");
    exit;
}
?>
