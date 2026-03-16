<?php
$errorMap = [
    "password" => "Password errata",
    "utente"   => "Utente non trovato",
];
$error = isset($_GET["error"]) ? ($errorMap[$_GET["error"]] ?? "") : "";
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartNest — Accedi</title>
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
        <h1>Smart<span>Nest</span></h1>
        <p>Il tuo ecosistema domestico intelligente</p>
      </div>
    </div>

    <form class="login-form" method="POST" action="auth/index.php">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-input" type="email" name="username"
               placeholder="nome@smartnest.io" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" name="password"
               placeholder="••••••••" required>
      </div>
      <div class="form-footer">
        <label class="form-check">
          <input type="checkbox" name="remember">
          <span class="form-check__label">Ricordami</span>
        </label>
        <span class="form-link">Password dimenticata?</span>
      </div>

      <?php if ($error !== "") { ?>
      <div class="form-error visible">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php } ?>

      <button type="submit" class="btn-login">Accedi</button>
    </form>
  </div>
  <p class="login-note">
    SmartNest © 2025 · <span>Tutti i dati sono simulati</span>
  </p>
</div>
</body>
</html>