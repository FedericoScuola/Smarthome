<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["username"]) && isset($_POST["password"])) {

        $formEmail = $_POST["username"];
        $formPassword = $_POST["password"];

        require_once "../lib/conn.php";

        $sql = "SELECT id, password FROM utenti WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["email" => $formEmail]);

        if ($stmt->rowCount() === 1) {
            $utente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($formPassword, $utente["password"])) {
                $_SESSION["utente"] = $utente["id"];
                header("Location: ../dash.php");
                exit;
            } else {
                header("Location: ../login/?error=password");
                exit;
            }
        } else {
            header("Location: ../login/?error=utente");
            exit;
        }
    }
}
?>