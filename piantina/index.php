<?php
// ── Controllo sessione ────────────────────────────────────────
session_start();
if (!isset($_SESSION['utente'])) {
    header("Location: ../login/index.php");
    exit;
}

// ── Include librerie condivise ────────────────────────────────
require_once '../lib/conn.php';
require_once '../lib/helpers.php';

$isOwner = ($_SESSION['ruolo'] === 'Proprietario');

// ── Carica stanze con dispositivi e status ────────────────────
$stanze  = caricaStanze($conn);
$fireRow = cercaIncendioAttivo($conn);
$hasFire = (bool)$fireRow;

$nFire = count(array_filter($stanze, fn($s) => $s['status'] === 'fire'));
$nWarn = count(array_filter($stanze, fn($s) => $s['status'] === 'warn'));
$nOk   = count(array_filter($stanze, fn($s) => $s['status'] === 'ok'));

// ── Palette colori status ─────────────────────────────────────
$colori = [
    'ok'   => [
        'fill'       => 'rgba(0,212,180,0.06)',
        'fillHover'  => 'rgba(0,212,180,0.11)',
        'stroke'     => '#00d4b4',
        'strokeDim'  => 'rgba(0,212,180,0.35)',
        'text'       => '#00d4b4',
        'glow'       => 'rgba(0,212,180,0.18)',
        'wall'       => 'rgba(0,212,180,0.55)',
        'header'     => 'rgba(0,212,180,0.12)',
    ],
    'warn' => [
        'fill'       => 'rgba(240,160,48,0.06)',
        'fillHover'  => 'rgba(240,160,48,0.11)',
        'stroke'     => '#f0a030',
        'strokeDim'  => 'rgba(240,160,48,0.40)',
        'text'       => '#f0a030',
        'glow'       => 'rgba(240,160,48,0.18)',
        'wall'       => 'rgba(240,160,48,0.55)',
        'header'     => 'rgba(240,160,48,0.12)',
    ],
    'fire' => [
        'fill'       => 'rgba(240,64,96,0.08)',
        'fillHover'  => 'rgba(240,64,96,0.14)',
        'stroke'     => '#f04060',
        'strokeDim'  => 'rgba(240,64,96,0.55)',
        'text'       => '#f04060',
        'glow'       => 'rgba(240,64,96,0.25)',
        'wall'       => 'rgba(240,64,96,0.70)',
        'header'     => 'rgba(240,64,96,0.14)',
    ],
];

// ── Icone per tipo di stanza (SVG path) ───────────────────────
// Mappa keyword nel nome → icona simbolica SVG
function iconaStanza(string $nome): string {
    $n = mb_strtolower($nome);
    if (str_contains($n, 'cucina') || str_contains($n, 'kitchen'))
        return '<path d="M6 2v6M10 2v6M8 8v4M4 12h12v6H4z" stroke-width="1.3"/>';
    if (str_contains($n, 'bagno') || str_contains($n, 'wc') || str_contains($n, 'toilet'))
        return '<path d="M5 4h10v5a5 5 0 01-10 0V4z" stroke-width="1.3"/><line x1="5" y1="4" x2="19" y2="4" stroke-width="1.3"/><line x1="12" y1="14" x2="12" y2="17" stroke-width="1.3"/>';
    if (str_contains($n, 'camera') || str_contains($n, 'letto') || str_contains($n, 'bedroom'))
        return '<rect x="3" y="8" width="18" height="10" rx="2" stroke-width="1.3"/><rect x="5" y="5" width="5" height="5" rx="1" stroke-width="1.3"/><rect x="14" y="5" width="5" height="5" rx="1" stroke-width="1.3"/>';
    if (str_contains($n, 'salone') || str_contains($n, 'soggiorno') || str_contains($n, 'living'))
        return '<rect x="3" y="10" width="18" height="7" rx="2" stroke-width="1.3"/><rect x="3" y="7" width="4" height="6" rx="1" stroke-width="1.3"/><rect x="17" y="7" width="4" height="6" rx="1" stroke-width="1.3"/>';
    if (str_contains($n, 'garage') || str_contains($n, 'box'))
        return '<rect x="3" y="8" width="18" height="12" rx="1" stroke-width="1.3"/><path d="M3 8l9-5 9 5" stroke-width="1.3"/><rect x="8" y="13" width="8" height="7" stroke-width="1.3"/>';
    if (str_contains($n, 'studio') || str_contains($n, 'ufficio') || str_contains($n, 'office'))
        return '<rect x="4" y="6" width="16" height="12" rx="1" stroke-width="1.3"/><line x1="8" y1="18" x2="7" y2="20" stroke-width="1.3"/><line x1="16" y1="18" x2="17" y2="20" stroke-width="1.3"/><line x1="4" y1="10" x2="20" y2="10" stroke-width="1.3"/>';
    if (str_contains($n, 'ingresso') || str_contains($n, 'corridoio') || str_contains($n, 'hallway'))
        return '<path d="M3 12h18M12 3v18" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke-width="1.3"/>';
    if (str_contains($n, 'lavandr') || str_contains($n, 'lavanderia'))
        return '<rect x="4" y="4" width="16" height="16" rx="2" stroke-width="1.3"/><circle cx="12" cy="13" r="4" stroke-width="1.3"/><line x1="7" y1="8" x2="11" y2="8" stroke-width="1.3"/>';
    // Default — casa generica
    return '<path d="M3 12L12 3l9 9M5 10v9h4v-4h6v4h4V10" stroke-width="1.3"/>';
}

// ── Icone sensori ─────────────────────────────────────────────
function iconaSensore(string $unita): string {
    return match($unita) {
        '°C'  => '🌡',
        '%'   => '💧',
        'AQI' => '🌬',
        default => '📡',
    };
}

// ════════════════════════════════════════════════════════════
//  ALGORITMO LAYOUT
//  Stanze distribuite su 2 file, dimensioni proporzionali ai m²
//  Muri spessi, proporzioni architettoniche più realistiche
// ════════════════════════════════════════════════════════════

$ALTEZZA_MEDIA = 2.7;
$SCALA         = 11.0;   // px per m² (leggermente più grande)
$WALL          = 8;      // spessore muro in px
$GAP           = $WALL;  // gap = spessore muro
$PAD           = 32;     // margine SVG esterno
$RATIO         = 1.45;   // rapporto W:H stanza

foreach ($stanze as &$s) {
    $mq          = $s['volumetria'] > 0 ? $s['volumetria'] / $ALTEZZA_MEDIA : 10;
    $areaPixel   = $mq * $SCALA;
    $h           = (int)round(sqrt($areaPixel / $RATIO));
    $w           = (int)round($h * $RATIO);
    $s['_w']     = max($w, 125);
    $s['_h']     = max($h, 90);
    $s['_mq']    = round($mq, 1);
}
unset($s);

usort($stanze, fn($a, $b) => $b['volumetria'] <=> $a['volumetria']);

// Distribuisci su 2 file bilanciate
$fila1 = []; $fila2 = [];
$h1 = 0; $h2 = 0;
foreach ($stanze as $s) {
    if ($h1 <= $h2) { $fila1[] = $s; $h1 = max($h1, $s['_h']); }
    else            { $fila2[] = $s; $h2 = max($h2, $s['_h']); }
}

$layoutStanze = [];
$rowH1 = 0; foreach ($fila1 as $s) $rowH1 = max($rowH1, $s['_h']);
$rowH2 = 0; foreach ($fila2 as $s) $rowH2 = max($rowH2, $s['_h']);

$curX = $PAD;
foreach ($fila1 as $s) {
    $offY = (int)(($rowH1 - $s['_h']) / 2);
    $layoutStanze[$s['id_stanza']] = [
        'x' => $curX, 'y' => $PAD + $offY, 'w' => $s['_w'], 'h' => $s['_h'],
    ];
    $curX += $s['_w'] + $GAP;
}
$maxX1 = $curX - $GAP;

$startY2 = $PAD + $rowH1 + $GAP * 2;
$curX    = $PAD;
foreach ($fila2 as $s) {
    $offY = (int)(($rowH2 - $s['_h']) / 2);
    $layoutStanze[$s['id_stanza']] = [
        'x' => $curX, 'y' => $startY2 + $offY, 'w' => $s['_w'], 'h' => $s['_h'],
    ];
    $curX += $s['_w'] + $GAP;
}
$maxX2 = $curX - $GAP;

$VB_W = max($maxX1, $maxX2) + $PAD;
$VB_H = ($rowH2 > 0 ? $startY2 + $rowH2 : $PAD + $rowH1) + $PAD + 30;

$paginaAttiva = 'piantina';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartHome — Piantina</title>
  <link rel="stylesheet" href="../styles/main.css">
  <style>
    /* ── Piantina enhancements ─────────────────────────── */
    .floor-plan-wrap {
      background: var(--bg-elevated);
      border-radius: var(--radius-md);
      border: 1px solid var(--border-subtle);
      overflow: auto;
      position: relative;
    }

    /* Zoom controls */
    .zoom-controls {
      position: absolute;
      top: 12px;
      right: 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      z-index: 10;
    }
    .zoom-btn {
      width: 28px; height: 28px;
      background: var(--bg-overlay);
      border: 1px solid var(--border-default);
      border-radius: var(--radius-sm);
      color: var(--text-secondary);
      font-size: 16px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: border-color var(--transition-fast), color var(--transition-fast);
    }
    .zoom-btn:hover {
      border-color: var(--brand);
      color: var(--brand);
    }

    /* Stanza card animata */
    .room-rect {
      cursor: pointer;
      transition: filter 0.2s;
    }
    .room-rect:hover .room-bg {
      filter: brightness(1.4);
    }

    /* Tooltip stilizzato */
    #room-tooltip {
      position: fixed;
      display: none;
      pointer-events: none;
      z-index: 1000;
      background: var(--bg-overlay);
      border: 1px solid var(--border-default);
      border-radius: var(--radius-md);
      padding: 10px 14px;
      min-width: 180px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.45);
    }
    #room-tooltip .tt-name {
      font-family: var(--font-ui);
      font-weight: 700;
      font-size: 13px;
      color: var(--text-primary);
      margin-bottom: 4px;
    }
    #room-tooltip .tt-mq {
      font-family: var(--font-mono);
      font-size: 10px;
      color: var(--text-muted);
      margin-bottom: 8px;
    }
    #room-tooltip .tt-sensor {
      display: flex;
      justify-content: space-between;
      font-family: var(--font-mono);
      font-size: 11px;
      color: var(--text-secondary);
      padding: 3px 0;
      border-top: 1px solid var(--border-subtle);
    }
    #room-tooltip .tt-val { color: var(--brand); font-weight: 600; }
    #room-tooltip .tt-val.warn { color: var(--warn); }
    #room-tooltip .tt-val.fire { color: var(--danger); }
  </style>
</head>
<body>
<div class="app-layout">

<?php require '../lib/sidebar.php'; ?>

<main class="app-main" id="main-area">

  <header class="topbar">
    <div>
      <div class="topbar__title">Piantina</div>
      <div class="topbar__subtitle">
        Planimetria proporzionale · passa il mouse su una stanza · clicca per i dettagli
      </div>
    </div>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">
      <?php if ($nFire > 0): ?>
      <span class="badge badge--danger"><span class="badge__dot"></span><?= $nFire ?> incendio</span>
      <?php endif; ?>
      <?php if ($nWarn > 0): ?>
      <span class="badge badge--warn"><?= $nWarn ?> avviso</span>
      <?php endif; ?>
      <span class="badge badge--ok"><?= $nOk ?> OK</span>
      <?php if ($hasFire): ?>
      <div class="alert-banner alert-banner--danger"
           style="padding:6px 14px;cursor:pointer;font-size:12px"
           onclick="document.getElementById('fire-modal').classList.add('open')">
        <span>🔥</span>
        <span>INCENDIO — <?= htmlspecialchars($fireRow['stanza']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </header>

  <section class="page active" style="padding:var(--sp-6)">

    <!-- SVG piantina -->
    <div class="card" style="padding:var(--sp-4);position:relative">
      <div class="floor-plan-wrap" id="floor-plan-wrap">

        <!-- Zoom Controls -->
        <div class="zoom-controls">
          <button class="zoom-btn" onclick="zoomIn()" title="Zoom in">+</button>
          <button class="zoom-btn" onclick="zoomOut()" title="Zoom out">−</button>
          <button class="zoom-btn" onclick="zoomReset()" title="Reset" style="font-size:11px">⟳</button>
        </div>

        <svg
          id="floor-svg"
          width="100%"
          viewBox="0 0 <?= $VB_W ?> <?= $VB_H ?>"
          xmlns="http://www.w3.org/2000/svg"
          style="min-width:520px;display:block;transition:transform 0.2s ease">

          <defs>
            <!-- Griglia sottile architettonica -->
            <pattern id="grid-fine" width="10" height="10" patternUnits="userSpaceOnUse">
              <path d="M 10 0 L 0 0 0 10"
                    fill="none" stroke="rgba(255,255,255,0.012)" stroke-width="0.5"/>
            </pattern>
            <pattern id="grid-coarse" width="50" height="50" patternUnits="userSpaceOnUse">
              <path d="M 50 0 L 0 0 0 50"
                    fill="none" stroke="rgba(255,255,255,0.028)" stroke-width="0.8"/>
            </pattern>

            <!-- Filtro glow per stanza in fire -->
            <filter id="glow-fire" x="-30%" y="-30%" width="160%" height="160%">
              <feGaussianBlur stdDeviation="4" result="blur"/>
              <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
            <filter id="glow-warn" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="3" result="blur"/>
              <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
            <filter id="drop-shadow" x="-10%" y="-10%" width="130%" height="140%">
              <feDropShadow dx="0" dy="3" stdDeviation="5" flood-color="rgba(0,0,0,0.5)"/>
            </filter>

            <!-- Pattern parete (hatch diagonale sottile) -->
            <pattern id="wall-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
              <line x1="0" y1="0" x2="0" y2="6" stroke="rgba(255,255,255,0.07)" stroke-width="1.5"/>
            </pattern>

            <!-- Clip per intestazione stanza (bordo arrotondato solo in alto) -->
            <clipPath id="clip-header-<?= $s['id_stanza'] ?? 0 ?>">
              <rect width="200" height="22" rx="0"/>
            </clipPath>
          </defs>

          <!-- ── Sfondo ── -->
          <rect width="<?= $VB_W ?>" height="<?= $VB_H ?>" fill="#080b10"/>
          <rect width="<?= $VB_W ?>" height="<?= $VB_H ?>" fill="url(#grid-fine)"/>
          <rect width="<?= $VB_W ?>" height="<?= $VB_H ?>" fill="url(#grid-coarse)"/>

          <!-- ── Perimetro esterno casa (contorno complessivo) ── -->
          <?php
            $extX = $PAD - $WALL;
            $extY = $PAD - $WALL;
            $extW = max($maxX1, $maxX2) - $PAD + $WALL * 2;
            $extH = $VB_H - $PAD - 22;
          ?>
          <!-- Ombra perimetro -->
          <rect x="<?= $extX + 4 ?>" y="<?= $extY + 4 ?>"
                width="<?= $extW ?>" height="<?= $extH ?>"
                rx="4" fill="rgba(0,0,0,0.5)" filter="url(#drop-shadow)"/>
          <!-- Muro esterno (riempimento) -->
          <rect x="<?= $extX ?>" y="<?= $extY ?>"
                width="<?= $extW ?>" height="<?= $extH ?>"
                rx="4" fill="rgba(255,255,255,0.025)"
                stroke="rgba(255,255,255,0.12)" stroke-width="<?= $WALL ?>"/>
          <!-- Hatch muro esterno -->
          <rect x="<?= $extX ?>" y="<?= $extY ?>"
                width="<?= $extW ?>" height="<?= $extH ?>"
                rx="4" fill="url(#wall-hatch)" opacity="0.4"/>

          <?php foreach ($stanze as $s):
            $lay = $layoutStanze[$s['id_stanza']] ?? null;
            if (!$lay) continue;

            $x  = $lay['x'];
            $y  = $lay['y'];
            $w  = $lay['w'];
            $h  = $lay['h'];
            $c  = $colori[$s['status']] ?? $colori['ok'];
            $id = 'room-' . $s['id_stanza'];

            $sensori = array_values(array_filter(
                $s['dispositivi'],
                fn($d) => $d['tipo'] === 'Sensore' && $d['valore'] !== null
            ));

            // Posizione porta (un quarto della parete in basso)
            $doorW  = max(20, (int)($w * 0.22));
            $doorX  = $x + (int)(($w - $doorW) / 4);
            $doorY  = $y + $h;

            // Posizione finestre (parete destra)
            $winH   = max(14, (int)($h * 0.28));
            $winY   = $y + (int)(($h - $winH) / 2);

            // Icona stanza
            $iconPath = iconaStanza($s['nome']);
          ?>

          <!-- ════ STANZA: <?= htmlspecialchars($s['nome']) ?> ════ -->
          <g class="room-group"
             data-id="<?= $s['id_stanza'] ?>"
             data-nome="<?= htmlspecialchars($s['nome']) ?>"
             data-mq="<?= $s['_mq'] ?>"
             data-status="<?= $s['status'] ?>"
             style="cursor:pointer"
             onclick="window.location='../stanze/index.php'"
             onmouseenter="showTooltip(event, this)"
             onmouseleave="hideTooltip()">

            <!-- Glow di sfondo (solo warn/fire) -->
            <?php if ($s['status'] !== 'ok'): ?>
            <rect x="<?= $x - 4 ?>" y="<?= $y - 4 ?>"
                  width="<?= $w + 8 ?>" height="<?= $h + 8 ?>"
                  rx="8" fill="<?= $c['glow'] ?>"
                  filter="url(#glow-<?= $s['status'] === 'fire' ? 'fire' : 'warn' ?>)"
                  opacity="0.6">
              <?php if ($s['status'] === 'fire'): ?>
              <animate attributeName="opacity" values="0.4;0.9;0.4" dur="2s" repeatCount="indefinite"/>
              <?php endif; ?>
            </rect>
            <?php endif; ?>

            <!-- Rettangolo stanza (riempimento) -->
            <rect class="room-bg"
                  x="<?= $x ?>" y="<?= $y ?>"
                  width="<?= $w ?>" height="<?= $h ?>"
                  rx="3"
                  fill="<?= $c['fill'] ?>"
                  stroke="<?= $c['wall'] ?>"
                  stroke-width="<?= $WALL * 0.75 ?>">
              <?php if ($s['status'] === 'fire'): ?>
              <animate attributeName="stroke-opacity" values="0.7;0.15;0.7" dur="1.5s" repeatCount="indefinite"/>
              <?php endif; ?>
            </rect>

            <!-- Sottile bordo interno (effetto doppio muro) -->
            <rect x="<?= $x + 3 ?>" y="<?= $y + 3 ?>"
                  width="<?= $w - 6 ?>" height="<?= $h - 6 ?>"
                  rx="2" fill="none"
                  stroke="<?= $c['strokeDim'] ?>" stroke-width="0.6"/>

            <!-- ── Intestazione stanza (band colorata in alto) ── -->
            <rect x="<?= $x ?>" y="<?= $y ?>"
                  width="<?= $w ?>" height="22"
                  rx="3" fill="<?= $c['header'] ?>"/>
            <!-- Linea separatrice intestazione -->
            <line x1="<?= $x ?>" y1="<?= $y + 22 ?>"
                  x2="<?= $x + $w ?>" y2="<?= $y + 22 ?>"
                  stroke="<?= $c['strokeDim'] ?>" stroke-width="0.8"/>

            <!-- ── Icona tipo stanza ── -->
            <g transform="translate(<?= $x + 7 ?>, <?= $y + 4 ?>)"
               fill="none" stroke="<?= $c['text'] ?>" opacity="0.7"
               viewBox="0 0 24 24" width="14" height="14">
              <svg width="14" height="14" viewBox="0 0 24 24"
                   fill="none" stroke="<?= $c['text'] ?>" opacity="0.7">
                <?= $iconPath ?>
              </svg>
            </g>

            <!-- Nome stanza -->
            <text x="<?= $x + 25 ?>" y="<?= $y + 15 ?>"
                  fill="<?= $c['text'] ?>"
                  font-size="10"
                  font-family="Outfit, sans-serif"
                  font-weight="700"
                  letter-spacing="0.3">
              <?= htmlspecialchars(mb_strtoupper($s['nome'])) ?>
            </text>

            <!-- ── Pallino status in alto a destra ── -->
            <circle cx="<?= $x + $w - 12 ?>" cy="<?= $y + 11 ?>" r="6"
                    fill="<?= $c['fill'] ?>"
                    stroke="<?= $c['stroke'] ?>" stroke-width="1.5">
              <?php if ($s['status'] === 'fire'): ?>
              <animate attributeName="r" values="5;8;5" dur="1.2s" repeatCount="indefinite"/>
              <?php endif; ?>
            </circle>
            <text x="<?= $x + $w - 12 ?>" y="<?= $y + 15 ?>"
                  text-anchor="middle"
                  fill="<?= $s['status'] === 'fire' ? '#fff' : $c['text'] ?>"
                  font-size="7.5" font-weight="700">
              <?= $s['status'] === 'fire' ? '!' : ($s['status'] === 'warn' ? '~' : '✓') ?>
            </text>

            <!-- ── m² timbro in basso a destra ── -->
            <text x="<?= $x + $w - 5 ?>" y="<?= $y + $h - 5 ?>"
                  text-anchor="end"
                  fill="<?= $c['strokeDim'] ?>"
                  font-size="8"
                  font-family="DM Mono, monospace"
                  font-weight="500">
              <?= $s['_mq'] ?> m²
            </text>

            <!-- ── Sensori ── -->
            <?php
            $nSens   = count($sensori);
            $sensH   = 26;
            $totalSH = $nSens * $sensH;
            $startSY = $y + 28 + (int)(($h - 28 - 20 - $totalSH) / 2);
            if ($startSY < $y + 28) $startSY = $y + 28;

            foreach ($sensori as $si => $d):
              $sy   = $startSY + $si * $sensH;
              $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
              $vCol  = $fuori
                  ? ($s['status'] === 'fire' ? '#f04060' : '#f0a030')
                  : '#00d4b4';
              $icon  = iconaSensore($d['unita_misura'] ?? '');
              $bx    = $x + 7;
              $bw    = $w - 14;
            ?>
            <!-- Box sensore -->
            <rect x="<?= $bx ?>" y="<?= $sy ?>"
                  width="<?= $bw ?>" height="<?= $sensH - 4 ?>"
                  rx="3"
                  fill="rgba(8,11,16,0.65)"
                  stroke="<?= $fuori
                      ? ($s['status'] === 'fire' ? 'rgba(240,64,96,0.35)' : 'rgba(240,160,48,0.35)')
                      : 'rgba(255,255,255,0.05)' ?>"
                  stroke-width="0.8"/>
            <!-- Indicatore laterale colorato -->
            <rect x="<?= $bx ?>" y="<?= $sy ?>"
                  width="3" height="<?= $sensH - 4 ?>"
                  rx="1.5" fill="<?= $vCol ?>" opacity="0.75"/>
            <!-- Icona -->
            <text x="<?= $bx + 9 ?>" y="<?= $sy + 14 ?>"
                  fill="#4a5878" font-size="9"
                  font-family="DM Mono, monospace"><?= $icon ?></text>
            <!-- Nome breve -->
            <text x="<?= $bx + 20 ?>" y="<?= $sy + 14 ?>"
                  fill="#5a6888" font-size="8"
                  font-family="DM Mono, monospace">
              <?= htmlspecialchars(mb_substr(explode(' ', $d['nome'])[0], 0, 10)) ?>
            </text>
            <!-- Valore -->
            <text x="<?= $bx + $bw - 6 ?>" y="<?= $sy + 14 ?>"
                  text-anchor="end"
                  fill="<?= $vCol ?>"
                  font-size="11"
                  font-family="DM Mono, monospace"
                  font-weight="500">
              <?= htmlspecialchars($d['valore']) ?><?= htmlspecialchars($d['unita_misura'] ?? '') ?>
            </text>
            <?php endforeach; ?>

            <?php if ($nSens === 0): ?>
            <text x="<?= $x + $w / 2 ?>" y="<?= $y + $h / 2 + 4 ?>"
                  text-anchor="middle" fill="#2a3450"
                  font-size="9" font-family="DM Mono, monospace">
              nessun sensore
            </text>
            <?php endif; ?>

            <!-- ── Porta (simbolo architettonico) ── -->
            <!-- Apertura nel muro -->
            <line x1="<?= $doorX ?>" y1="<?= $y + $h - 3 ?>"
                  x2="<?= $doorX + $doorW ?>" y2="<?= $y + $h - 3 ?>"
                  stroke="<?= $c['fill'] === 'rgba(0,212,180,0.06)' ? '#080b10' : '#080b10' ?>"
                  stroke-width="6"/>
            <!-- Arco porta (quarto di cerchio) -->
            <path d="M <?= $doorX ?> <?= $y + $h - 3 ?>
                     A <?= $doorW ?> <?= $doorW ?>  0 0 1
                       <?= $doorX + $doorW ?> <?= $y + $h - 3 - $doorW ?>"
                  fill="none"
                  stroke="<?= $c['strokeDim'] ?>"
                  stroke-width="0.8"
                  stroke-dasharray="3,2"
                  opacity="0.6"/>
            <!-- Linea battente -->
            <line x1="<?= $doorX ?>" y1="<?= $y + $h - 3 ?>"
                  x2="<?= $doorX ?>" y2="<?= $y + $h - 3 - $doorW ?>"
                  stroke="<?= $c['strokeDim'] ?>" stroke-width="1" opacity="0.8"/>

            <!-- ── Finestra (simbolo architettonico, parete destra) ── -->
            <!-- Apertura finestra -->
            <line x1="<?= $x + $w - 2 ?>" y1="<?= $winY ?>"
                  x2="<?= $x + $w - 2 ?>" y2="<?= $winY + $winH ?>"
                  stroke="#080b10" stroke-width="5"/>
            <!-- Cornici finestra -->
            <rect x="<?= $x + $w - 5 ?>" y="<?= $winY ?>"
                  width="4" height="<?= $winH ?>"
                  fill="none"
                  stroke="<?= $c['strokeDim'] ?>" stroke-width="0.8" opacity="0.9"/>
            <!-- Traversa centrale -->
            <line x1="<?= $x + $w - 5 ?>" y1="<?= $winY + (int)($winH / 2) ?>"
                  x2="<?= $x + $w - 1 ?>" y2="<?= $winY + (int)($winH / 2) ?>"
                  stroke="<?= $c['strokeDim'] ?>" stroke-width="0.6"/>

            <!-- ── Spruzzi pompa incendio ── -->
            <?php if ($s['status'] === 'fire'):
              $mx = $x + $w / 2;
              $my = $y + $h - 18;
            ?>
            <?php foreach ([[-14, 16, '0s'], [0, 20, '0.2s'], [14, 16, '0.4s']] as [$dx, $dy, $beg]): ?>
            <line x1="<?= $mx ?>" y1="<?= $my ?>"
                  x2="<?= $mx + $dx ?>" y2="<?= $my + $dy ?>"
                  stroke="#3090f0" stroke-width="1.5" stroke-linecap="round">
              <animate attributeName="stroke-opacity"
                       values="0.2;1;0.2" dur="0.8s"
                       repeatCount="indefinite" begin="<?= $beg ?>"/>
            </line>
            <?php endforeach; ?>
            <?php endif; ?>

          </g>

          <?php endforeach; ?>

          <!-- ── Separatore fra le due file (linea corridoio) ── -->
          <?php if ($rowH2 > 0): ?>
          <line x1="<?= $PAD - 4 ?>" y1="<?= $startY2 - ($GAP * 2 / 2) ?>"
                x2="<?= max($maxX1, $maxX2) - $PAD + 14 ?>" y2="<?= $startY2 - ($GAP * 2 / 2) ?>"
                stroke="rgba(255,255,255,0.06)" stroke-width="<?= $GAP * 2 - 2 ?>"/>
          <!-- Etichetta corridoio -->
          <text x="<?= $PAD + 4 ?>" y="<?= $startY2 - ($GAP * 2 / 2) + 4 ?>"
                fill="rgba(255,255,255,0.08)"
                font-size="8" font-family="DM Mono, monospace"
                letter-spacing="3">
            CORRIDOIO
          </text>
          <?php endif; ?>

          <!-- ── Bussola Nord ── -->
          <?php $cx = $VB_W - 28; $cy = 28; ?>
          <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="16"
                  fill="rgba(14,18,25,0.8)"
                  stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
          <path d="M <?= $cx ?> <?= $cy - 12 ?> L <?= $cx + 5 ?> <?= $cy + 4 ?> L <?= $cx ?> <?= $cy + 2 ?> L <?= $cx - 5 ?> <?= $cy + 4 ?> Z"
                fill="rgba(0,212,180,0.8)"/>
          <path d="M <?= $cx ?> <?= $cy + 12 ?> L <?= $cx + 5 ?> <?= $cy - 4 ?> L <?= $cx ?> <?= $cy - 2 ?> L <?= $cx - 5 ?> <?= $cy - 4 ?> Z"
                fill="rgba(255,255,255,0.12)"/>
          <text x="<?= $cx ?>" y="<?= $cy - 14 ?>"
                text-anchor="middle" fill="rgba(0,212,180,0.9)"
                font-size="7" font-weight="700" font-family="DM Mono, monospace">N</text>

          <!-- ── Scala metrica ── -->
          <?php
            // 1 box = 10px = ~1m (con SCALA 11 px/m²)
            $scaleX = $PAD;
            $scaleY = $VB_H - 14;
            $scaleW = 60; // pixel representing ~5.5m roughly
          ?>
          <line x1="<?= $scaleX ?>" y1="<?= $scaleY ?>"
                x2="<?= $scaleX + $scaleW ?>" y2="<?= $scaleY ?>"
                stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/>
          <line x1="<?= $scaleX ?>" y1="<?= $scaleY - 4 ?>"
                x2="<?= $scaleX ?>" y2="<?= $scaleY + 4 ?>"
                stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/>
          <line x1="<?= $scaleX + $scaleW ?>" y1="<?= $scaleY - 4 ?>"
                x2="<?= $scaleX + $scaleW ?>" y2="<?= $scaleY + 4 ?>"
                stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/>
          <text x="<?= $scaleX + $scaleW / 2 ?>" y="<?= $scaleY - 6 ?>"
                text-anchor="middle" fill="rgba(255,255,255,0.22)"
                font-size="7.5" font-family="DM Mono, monospace">≈ 5 m</text>

          <!-- ── Legenda ── -->
          <?php $lx = $scaleX + $scaleW + 24; ?>
          <?php foreach (['fire' => 'Incendio', 'warn' => 'Attenzione', 'ok' => 'OK'] as $st => $lab): ?>
          <?php $lc = $colori[$st]; ?>
          <circle cx="<?= $lx ?>" cy="<?= $scaleY ?>" r="5"
                  fill="<?= $lc['fill'] ?>" stroke="<?= $lc['stroke'] ?>" stroke-width="1.5"/>
          <text x="<?= $lx + 9 ?>" y="<?= $scaleY + 4 ?>"
                fill="rgba(100,120,160,0.8)" font-size="8.5"
                font-family="DM Mono, monospace"><?= $lab ?></text>
          <?php $lx += 62; endforeach; ?>

          <!-- Filigrana misure proporzionali -->
          <text x="<?= $lx ?>" y="<?= $scaleY + 4 ?>"
                fill="rgba(255,255,255,0.06)" font-size="8"
                font-family="DM Mono, monospace">· dimensioni proporzionali ai m²</text>

        </svg>
      </div>

      <div style="margin-top:10px;font-size:11px;color:var(--text-muted);
                  font-family:var(--font-mono);text-align:right">
        Aggiornata al <?= date('d/m/Y H:i:s') ?> ·
        <a href="index.php" style="color:var(--brand);text-decoration:none">↻ Aggiorna</a>
      </div>
    </div>

    <!-- Card riepilogo stanze -->
    <div class="grid-3" style="margin-top:0">
      <?php foreach ($stanze as $s):
        $sensoriAttivi = array_filter(
            $s['dispositivi'],
            fn($d) => $d['tipo'] === 'Sensore' && $d['valore'] !== null
        );
      ?>
      <div class="card"
           style="<?= $s['status'] === 'fire'
               ? 'border-color:rgba(240,64,96,0.5)'
               : ($s['status'] === 'warn' ? 'border-color:rgba(240,160,48,0.35)' : '') ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    margin-bottom:var(--sp-3)">
          <div style="font-size:13px;font-weight:700">
            🏠 <?= htmlspecialchars($s['nome']) ?>
          </div>
          <?= badgeStatus($s['status']) ?>
        </div>
        <div style="font-size:10px;color:var(--text-muted);
                    font-family:var(--font-mono);margin-bottom:var(--sp-2)">
          <?= $s['volumetria'] ?> m³ · <?= $s['_mq'] ?> m²
        </div>
        <?php foreach ($sensoriAttivi as $d):
          $fuori = fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']);
          $col   = $fuori
              ? ($s['status'] === 'fire' ? 'var(--danger)' : 'var(--warn)')
              : 'var(--brand)';
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    font-size:12px;padding:4px 0;
                    border-bottom:1px solid var(--border-subtle)">
          <span style="color:var(--text-muted)">
            <?= sensorIcon($d['unita_misura'] ?? '') ?>
            <?= htmlspecialchars($d['nome']) ?>
          </span>
          <span style="font-family:var(--font-mono);font-weight:600;color:<?= $col ?>">
            <?= htmlspecialchars($d['valore']) ?><?= htmlspecialchars($d['unita_misura'] ?? '') ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (!$sensoriAttivi): ?>
        <div style="font-size:11px;color:var(--text-muted)">
          Nessuna misurazione disponibile.
        </div>
        <?php endif; ?>
        <div style="margin-top:8px">
          <a href="../stanze/index.php"
             class="btn btn--ghost btn--sm"
             style="width:100%;text-align:center;font-size:11px">
            Dettagli →
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </section>
</main>
</div>

<!-- Tooltip -->
<div id="room-tooltip"></div>

<!-- Modal incendio -->
<?php if ($hasFire): ?>
<div class="modal-overlay" id="fire-modal">
  <div class="modal modal--danger">
    <span class="modal__icon">🔥</span>
    <h2 class="modal__title" style="color:var(--danger)">INCENDIO RILEVATO</h2>
    <p class="modal__desc">
      <?= htmlspecialchars($fireRow['stanza']) ?>
      · <?= date('H:i:s', strtotime($fireRow['timestamp'])) ?>
    </p>
    <div class="pump-status" style="justify-content:center;margin-bottom:16px">
      <span class="pump-icon">💦</span>
      <span style="color:var(--info);font-weight:700">
        Sistema di estinzione attivato
      </span>
    </div>
    <div class="modal__actions">
      <button class="btn btn--danger"
              onclick="window.location.href='../notifiche/index.php'">
        ⚠ Vedi notifiche
      </button>
      <button class="btn btn--ghost"
              onclick="document.getElementById('fire-modal').classList.remove('open')">
        Visto
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ── Zoom ─────────────────────────────────────────────────────
let currentScale = 1;
const svg = document.getElementById('floor-svg');
function applyZoom() {
    svg.style.transform = `scale(${currentScale})`;
    svg.style.transformOrigin = 'top left';
}
function zoomIn()    { currentScale = Math.min(currentScale + 0.2, 3);   applyZoom(); }
function zoomOut()   { currentScale = Math.max(currentScale - 0.2, 0.5); applyZoom(); }
function zoomReset() { currentScale = 1; applyZoom(); }

// Scroll wheel zoom
document.getElementById('floor-plan-wrap').addEventListener('wheel', function(e) {
    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        e.deltaY < 0 ? zoomIn() : zoomOut();
    }
}, { passive: false });

// ── Tooltip ──────────────────────────────────────────────────
<?php
// Genera dati sensori per JS tooltip
$roomData = [];
foreach ($stanze as $s) {
    $sensori = array_values(array_filter(
        $s['dispositivi'],
        fn($d) => $d['tipo'] === 'Sensore' && $d['valore'] !== null
    ));
    $roomData[$s['id_stanza']] = [
        'nome'    => $s['nome'],
        'mq'      => $s['_mq'],
        'status'  => $s['status'],
        'sensori' => array_map(fn($d) => [
            'nome'  => $d['nome'],
            'val'   => $d['valore'],
            'unita' => $d['unita_misura'] ?? '',
            'fuori' => fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima']),
        ], $sensori),
    ];
}
?>
const roomData = <?= json_encode($roomData, JSON_UNESCAPED_UNICODE) ?>;

function showTooltip(e, el) {
    const id = el.dataset.id;
    const d  = roomData[id];
    if (!d) return;

    const tt = document.getElementById('room-tooltip');
    const statusColors = { ok: '#00d4b4', warn: '#f0a030', fire: '#f04060' };
    const sc = statusColors[d.status] || statusColors.ok;

    let html = `
        <div class="tt-name" style="color:${sc}">${d.nome}</div>
        <div class="tt-mq">${d.mq} m²</div>
    `;
    if (d.sensori.length > 0) {
        d.sensori.forEach(s => {
            const vc = s.fuori ? (d.status === 'fire' ? '#f04060' : '#f0a030') : '#00d4b4';
            html += `<div class="tt-sensor">
                <span>${s.nome}</span>
                <span class="tt-val" style="color:${vc}">${s.val}${s.unita}</span>
            </div>`;
        });
    } else {
        html += `<div class="tt-sensor" style="color:#3a4560">Nessun sensore attivo</div>`;
    }

    tt.innerHTML = html;
    tt.style.display = 'block';
    moveTT(e);
}

function moveTT(e) {
    const tt = document.getElementById('room-tooltip');
    const pad = 14;
    let tx = e.clientX + pad;
    let ty = e.clientY + pad;
    if (tx + 200 > window.innerWidth)  tx = e.clientX - 200 - pad;
    if (ty + 150 > window.innerHeight) ty = e.clientY - 120 - pad;
    tt.style.left = tx + 'px';
    tt.style.top  = ty + 'px';
}
document.addEventListener('mousemove', function(e) {
    if (document.getElementById('room-tooltip').style.display === 'block') moveTT(e);
});

function hideTooltip() {
    document.getElementById('room-tooltip').style.display = 'none';
}

// ── Auto-apri modal incendio ──────────────────────────────────
<?php if ($hasFire): ?>
setTimeout(function() {
    document.getElementById('fire-modal').classList.add('open');
}, 700);
<?php endif; ?>
</script>
</body>
</html>
