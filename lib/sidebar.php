<?php
/**
 * sidebar.php — Barra laterale condivisa
 *
 * Da includere con require in ogni pagina dopo aver definito:
 *   - $_SESSION['nome'], $_SESSION['cognome'], $_SESSION['ruolo']
 *   - $hasFire  (bool)  — true se c'è un incendio attivo
 *   - $paginaAttiva     — nome della voce da evidenziare (es. 'home', 'stanze', ...)
 */

// Calcola le iniziali del nome per l'avatar
function iniziali($nome, $cognome) {
    return strtoupper(mb_substr($nome, 0, 1) . mb_substr($cognome, 0, 1));
}

$nome    = $_SESSION['nome'];
$cognome = $_SESSION['cognome'];
$ruolo   = $_SESSION['ruolo'];
$isOwner = ($ruolo === 'Proprietario');

// Aiuta a capire se un link è "attivo" (evidenziato in blu)
function classeAttiva($voce, $paginaAttiva) {
    return ($voce === $paginaAttiva) ? 'nav__item active' : 'nav__item';
}
?>

<aside class="sidebar" id="sidebar">

  <!-- Logo e nome app -->
  <div class="sidebar__header">
    <div class="sidebar__logo">
      <svg viewBox="0 0 24 24" fill="#080b10" width="17" height="17">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
      </svg>
    </div>
    <span class="sidebar__brand">Smart<span>Home</span></span>
  </div>

  <!-- Voci di navigazione -->
  <nav class="sidebar__nav">

    <div class="nav__section-label">Principale</div>

    <a href="../home/index.php" class="<?= classeAttiva('home', $paginaAttiva) ?>" title="Panoramica">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
      </span>
      <span class="nav__label">Panoramica</span>
    </a>

    <a href="../stanze/index.php" class="<?= classeAttiva('stanze', $paginaAttiva) ?>" title="Stanze">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </span>
      <span class="nav__label">Stanze</span>
    </a>

    <!-- Sezione Stanze (solo Proprietario) -->
    <?php if ($isOwner): ?>
    <div class="nav__section-label">Stanze</div>
    <a href="../stanze/crea.php" class="<?= classeAttiva('stanze_crea', $paginaAttiva) ?>" title="Crea stanza">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="16"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Crea stanza</span>
    </a>
    <?php endif; ?>

    <div class="nav__section-label">Dispositivi</div>

    <a href="../dispositivi/index.php" class="<?= classeAttiva('dispositivi', $paginaAttiva) ?>" title="Dispositivi">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="3" width="20" height="14" rx="2"/>
          <line x1="8" y1="21" x2="16" y2="21"/>
          <line x1="12" y1="17" x2="12" y2="21"/>
        </svg>
      </span>
      <span class="nav__label">Dispositivi</span>
    </a>

    <?php if ($isOwner): ?>
    <a href="../dispositivi/crea.php" class="<?= classeAttiva('dispositivi_crea', $paginaAttiva) ?>" title="Crea dispositivo">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="16"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Crea dispositivo</span>
    </a>

    <div class="nav__section-label">Sistema</div>

    <a href="../utenti/index.php" class="<?= classeAttiva('utenti', $paginaAttiva) ?>" title="Utenti">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/>
          <path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </span>
      <span class="nav__label">Utenti</span>
    </a>

    <a href="../utenti/crea.php" class="<?= classeAttiva('utenti_crea', $paginaAttiva) ?>" title="Crea utente">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="16"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Crea utente</span>
    </a>
    <?php endif; ?>

    <div class="nav__section-label">Alert</div>

    <a href="../home/index.php#notifiche" class="<?= classeAttiva('notifiche', $paginaAttiva) ?>" title="Notifiche">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
      </span>
      <span class="nav__label">Notifiche</span>
      <?php if ($hasFire): ?>
      <span class="nav__badge">!</span>
      <?php endif; ?>
    </a>

  </nav>

  <!-- Piede sidebar: logout + info utente -->
  <div class="sidebar__footer">
    <a href="../lib/logout.php" class="nav__item nav__item--danger" title="Esci">
      <span class="nav__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </span>
      <span class="nav__label">Esci</span>
    </a>

    <div class="sidebar__user">
      <?php if (!empty($_SESSION['avatar'])): ?>
        <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="avatar" style="object-fit:cover" alt="avatar">
      <?php else: ?>
        <div class="avatar" style="background:linear-gradient(135deg,#00d4b4,#0088aa)">
          <?= iniziali($nome, $cognome) ?>
        </div>
      <?php endif; ?>
      <div class="sidebar__user-info">
        <div class="sidebar__user-name"><?= htmlspecialchars("$nome $cognome") ?></div>
        <div class="sidebar__user-role"><?= htmlspecialchars($ruolo) ?></div>
      </div>
    </div>
  </div>

</aside>
