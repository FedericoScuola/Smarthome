<?php

session_start();
if (!empty($_SESSION['user_id'])) { header('Location: dash.php'); exit; }

$errori = [
    'password' => 'Password errata. Riprova.',
    'utente'   => 'Nessun account trovato con questa email.',
    'vuoto'    => 'Compila tutti i campi.',
];
$errore = $errori[$_GET['err'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Accedi</title>
  <link rel="stylesheet" href="styles/login.css">
</head>
<body>
<div class="bg-glow bg-glow--tl"></div>
<div class="bg-glow bg-glow--br"></div>

<div class="login-wrapper">
  <div class="login-card">

    <div class="login-brand">
      <div class="login-brand__logo">
        <svg viewBox="0 0 24 24" fill="#080b10" width="22" height="22">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
      </div>
      <div class="login-brand__text">
        <h1>Smart<span>Home</span></h1>
        <p>Pannello di controllo · v3.0</p>
      </div>
    </div>

    <form class="login-form" method="POST" action="login">
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-input" type="email" id="email" name="email"
               placeholder="nome@example.com" autocomplete="email" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="form-input-wrap">
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="••••••••" autocomplete="current-password" required>
          <span class="form-input-wrap__toggle" onclick="togglePwd()">
            <svg id="eye-icon" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </span>
        </div>
      </div>

      <?php if ($errore): ?>
      <div class="form-error visible">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span><?= e($errore) ?></span>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn-login">Accedi</button>
    </form>

    <div class="login-divider" style="margin-top:20px">account demo</div>
    <div class="demo-accounts">
      <div class="demo-account" onclick="fillDemo('marco.rossi@example.com','password')">
        <div class="demo-account__avatar" style="background:linear-gradient(135deg,#00d4b4,#0088aa)">MR</div>
        <div class="demo-account__info">
          <div class="demo-account__name">Marco Rossi</div>
          <div class="demo-account__role">Proprietario · password</div>
        </div>
        <span class="demo-account__arrow">›</span>
      </div>
      <div class="demo-account" onclick="fillDemo('laura.bianchi@example.com','password')">
        <div class="demo-account__avatar" style="background:linear-gradient(135deg,#f0a030,#f04060)">LB</div>
        <div class="demo-account__info">
          <div class="demo-account__name">Laura Bianchi</div>
          <div class="demo-account__role">Proprietario · password</div>
        </div>
        <span class="demo-account__arrow">›</span>
      </div>
      <div class="demo-account" onclick="fillDemo('giulia.verdi@example.com','password')">
        <div class="demo-account__avatar" style="background:linear-gradient(135deg,#4a5878,#1a2130)">GV</div>
        <div class="demo-account__info">
          <div class="demo-account__name">Giulia Verdi</div>
          <div class="demo-account__role">Ospite · password</div>
        </div>
        <span class="demo-account__arrow">›</span>
      </div>
    </div>

  </div>
  <p class="login-note">SmartHome © 2026 · <span>Dati reali da DB</span></p>
</div>

<script>
function togglePwd() {
    const i = document.getElementById('password');
    const ic = document.getElementById('eye-icon');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.innerHTML = i.type === 'text'
        ? '<path d="M17.94 17.94A10 10 0 0112 20C5 20 1 12 1 12a18 18 0 015.06-5.94M9.9 4.24A9 9 0 0112 4c7 0 11 8 11 8a18 18 0 01-2.16 3.19M1 1l22 22"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
function fillDemo(email, pwd) {
    document.getElementById('email').value    = email;
    document.getElementById('password').value = pwd;
    document.querySelector('.login-form').submit();
}
</script>
</body>
</html>
