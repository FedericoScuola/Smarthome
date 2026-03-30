<?php
/**
 * helpers.php — Funzioni PHP usate in più pagine
 *
 * Da includere con require in ogni pagina che ne ha bisogno.
 */

// Restituisce il badge HTML colorato in base allo stato della stanza
function badgeStatus($status) {
    $badge = [
        'ok'   => '<span class="badge badge--ok"><span class="badge__dot"></span>OK</span>',
        'warn' => '<span class="badge badge--warn"><span class="badge__dot"></span>Attenzione</span>',
        'fire' => '<span class="badge badge--danger"><span class="badge__dot"></span>INCENDIO</span>',
    ];
    return $badge[$status] ?? $badge['ok'];
}

// Restituisce l'emoji giusta in base all'unità di misura del sensore
function sensorIcon($unita) {
    if ($unita === '°C')  return '🌡';
    if ($unita === '%')   return '💧';
    if ($unita === 'AQI') return '🌬';
    return '📡';
}

// Controlla se il valore di un sensore è fuori soglia
function fuoriSoglia($valore, $min, $max) {
    if ($valore === null) return false;
    $v = (float)$valore;
    if ($min !== null && $v < (float)$min) return true;
    if ($max !== null && $v > (float)$max) return true;
    return false;
}

/**
 * Calcola lo stato di una stanza ('ok', 'warn', 'fire')
 * basandosi sui suoi dispositivi e sugli eventi recenti.
 *
 * $conn     — connessione PDO
 * $idStanza — ID della stanza
 * $dispositivi — array di dispositivi con soglie e valori
 */
function calcolaStatusStanza($conn, $idStanza, $dispositivi) {
    $status = 'ok';

    // Controlla se un sensore è fuori soglia
    foreach ($dispositivi as $d) {
        if (fuoriSoglia($d['valore'], $d['soglia_minima'], $d['soglia_massima'])) {
            $status = 'warn';
        }
    }

    // Controlla incendio negli ultimi 10 minuti (sovrascrive 'warn')
    $sql  = "SELECT COUNT(*) FROM eventi e
             JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
             WHERE d.id_stanza = :sid AND e.id_tipo = 1
               AND e.timestamp >= NOW() - INTERVAL 10 MINUTE";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['sid' => $idStanza]);
    if ($stmt->fetchColumn() > 0) {
        $status = 'fire';
    }

    return $status;
}

/**
 * Recupera tutte le stanze con i loro dispositivi e lo stato calcolato.
 * Usata nella home e nella pagina stanze.
 */
function caricaStanze($conn) {
    $stanze = $conn->query("SELECT id_stanza, nome, volumetria FROM stanze ORDER BY id_stanza")->fetchAll();

    foreach ($stanze as &$stanza) {
        $sid = $stanza['id_stanza'];

        // Prende i dispositivi con l'ultima misurazione disponibile
        $sql = "SELECT d.id_dispositivo, d.nome, d.tipo, d.unita_misura,
                       d.soglia_minima, d.soglia_massima,
                       m.valore, m.timestamp AS ultima_lettura
                FROM dispositivi d
                LEFT JOIN misurazioni m ON m.id_misurazione = (
                    SELECT id_misurazione FROM misurazioni
                    WHERE id_dispositivo = d.id_dispositivo
                    ORDER BY timestamp DESC LIMIT 1
                )
                WHERE d.id_stanza = :sid
                ORDER BY d.tipo DESC, d.id_dispositivo";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['sid' => $sid]);
        $stanza['dispositivi'] = $stmt->fetchAll();

        $stanza['status'] = calcolaStatusStanza($conn, $sid, $stanza['dispositivi']);
    }
    unset($stanza); // rimuove il riferimento dell'ultimo elemento

    return $stanze;
}

/**
 * Recupera l'incendio più recente (se presente negli ultimi 10 min).
 * Restituisce la riga o false.
 */
function cercaIncendioAttivo($conn) {
    $sql = "SELECT s.nome AS stanza, e.timestamp
            FROM eventi e
            JOIN dispositivi d ON d.id_dispositivo = e.id_dispositivo
            JOIN stanze s      ON s.id_stanza = d.id_stanza
            WHERE e.id_tipo = 1
              AND e.timestamp >= NOW() - INTERVAL 10 MINUTE
            ORDER BY e.timestamp DESC LIMIT 1";
    return $conn->query($sql)->fetch();
}
