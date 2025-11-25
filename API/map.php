<?php
// map.php - Génère une carte interactive Leaflet avec moyennes de température par commune
// Hypothèse sur le CSV: en-têtes possibles parmi:
//   commune|ville|municipalite   latitude|lat   longitude|lon|lng   temperature|temp|t
// Une ligne = une mesure individuelle. Le script calcule la moyenne par commune.
// Placez le fichier dans la racine du projet et ouvrez via un serveur PHP: php -S localhost:8000

$csvPath = __DIR__ . '/data.csv';
if (!file_exists($csvPath)) {
    die('Fichier CSV introuvable: ' . htmlspecialchars($csvPath));
}

$raw = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$raw || count($raw) === 0) {
    $rows = [];
} else {
    // Détecter séparateur (virgule par défaut, sinon point-virgule)
    $firstLine = $raw[0];
    $delimiter = strpos($firstLine, ';') !== false && strpos($firstLine, ',') === false ? ';' : ',';
    $headers = array_map('trim', str_getcsv($firstLine, $delimiter));
    $rows = [];
    for ($i = 1; $i < count($raw); $i++) {
        $cols = str_getcsv($raw[$i], $delimiter);
        if (count($cols) != count($headers)) continue; // ignorer lignes invalides
        $rowAssoc = [];
        foreach ($headers as $idx => $h) {
            $rowAssoc[strtolower($h)] = trim($cols[$idx]);
        }
        $rows[] = $rowAssoc;
    }
}

// Fonctions utilitaires pour retrouver noms de colonnes
function findColumn(array $headers, array $candidates) {
    foreach ($candidates as $c) {
        foreach ($headers as $h) {
            if (strtolower($h) === strtolower($c)) return $h;
        }
    }
    return null;
}
$lowerHeaders = [];
foreach ($rows as $r) { $lowerHeaders = array_unique(array_merge($lowerHeaders, array_keys($r))); }

$colCommune = findColumn($lowerHeaders, ['commune','ville','municipalite','city']);
$colLat     = findColumn($lowerHeaders, ['latitude','lat']);
$colLon     = findColumn($lowerHeaders, ['longitude','lon','lng']);
$colTemp    = findColumn($lowerHeaders, ['temperature','temp','t']);

$communeData = []; // [commune] => ['sum'=>..., 'count'=>..., 'lat'=>..., 'lon'=>...]
if ($colCommune && $colTemp) {
    foreach ($rows as $r) {
        if (!isset($r[$colCommune]) || !isset($r[$colTemp])) continue;
        $name = $r[$colCommune];
        $tempVal = str_replace(',', '.', $r[$colTemp]);
        if (!is_numeric($tempVal)) continue;
        if (!isset($communeData[$name])) {
            $communeData[$name] = [
                'sum' => 0.0,
                'count' => 0,
                'lat' => null,
                'lon' => null,
            ];
        }
        $communeData[$name]['sum'] += (float)$tempVal;
        $communeData[$name]['count']++;
        // Prendre première coordonnée disponible
        if ($colLat && $colLon && isset($r[$colLat]) && isset($r[$colLon])) {
            $latVal = str_replace(',', '.', $r[$colLat]);
            $lonVal = str_replace(',', '.', $r[$colLon]);
            if (is_numeric($latVal) && is_numeric($lonVal) && $communeData[$name]['lat'] === null) {
                $communeData[$name]['lat'] = (float)$latVal;
                $communeData[$name]['lon'] = (float)$lonVal;
            }
        }
    }
}

// Préparer tableau final
$markers = [];
foreach ($communeData as $name => $data) {
    $avg = $data['count'] > 0 ? $data['sum'] / $data['count'] : null;
    $markers[] = [
        'name' => $name,
        'avg' => $avg,
        'lat' => $data['lat'],
        'lon' => $data['lon'],
    ];
}

// Fonction couleur par classe
function temperatureColor($t) {
    if ($t === null) return '#999999';
    if ($t < 5) return '#2c7bb6';
    if ($t < 10) return '#abd9e9';
    if ($t < 15) return '#ffffbf';
    if ($t < 20) return '#fdae61';
    if ($t < 25) return '#f46d43';
    return '#d73027';
}

// Centre carte: calcul moyen des lat/lon valides (compatibilité PHP <7.4 sans fonctions fléchées)
$validCoords = array_filter($markers, function($m) { return $m['lat'] !== null && $m['lon'] !== null; });
$centerLat = 46.5; $centerLon = 2.5; // défaut France
if (count($validCoords) > 0) {
    $centerLat = array_sum(array_column($validCoords, 'lat')) / count($validCoords);
    $centerLon = array_sum(array_column($validCoords, 'lon')) / count($validCoords);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Carte Température Moyenne par Commune</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhI0uY6Ogr3x6FKJC6PqlXQ38Rksm6R3p3J0=" crossorigin="" />
    <style>
        html, body { margin:0; padding:0; height:100%; font-family:Arial, sans-serif; }
        #map { width:100%; height:100vh; }
        .legend { background:white; padding:8px 10px; line-height:1.3; font-size:12px; border:1px solid #ccc; border-radius:4px; }
        .legend-item { display:flex; align-items:center; margin-bottom:4px; }
        .color-box { width:16px; height:16px; margin-right:6px; border:1px solid #666; }
    </style>
</head>
<body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const map = L.map('map').setView([<?= json_encode($centerLat) ?>, <?= json_encode($centerLon) ?>], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '© OpenStreetMap'
}).addTo(map);

function temperatureColor(t) {
    if (t === null) return '#999999';
    if (t < 5) return '#2c7bb6';
    if (t < 10) return '#abd9e9';
    if (t < 15) return '#ffffbf';
    if (t < 20) return '#fdae61';
    if (t < 25) return '#f46d43';
    return '#d73027';
}

const markers = <?= json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
markers.forEach(m => {
    if (m.lat === null || m.lon === null) return; // ignorer si pas de coordonnée
    const color = temperatureColor(m.avg);
    const marker = L.circleMarker([m.lat, m.lon], {
        radius: 8,
        color: '#333',
        weight: 1,
        fillColor: color,
        fillOpacity: 0.85
    }).addTo(map);
    const avgText = m.avg === null ? 'N/A' : m.avg.toFixed(2) + ' °C';
    marker.bindPopup('<strong>' + m.name + '</strong><br/>Température moyenne: ' + avgText);
    marker.bindTooltip(m.name + ' (' + avgText + ')');
});

// Légende
const legend = L.control({position: 'bottomright'});
legend.onAdd = function() {
    const div = L.DomUtil.create('div', 'legend');
    div.innerHTML = '<strong>Température (°C)</strong><br/>';
    const classes = [
        {label:'< 5', c: temperatureColor(0)},
        {label:'5 - 10', c: temperatureColor(7)},
        {label:'10 - 15', c: temperatureColor(12)},
        {label:'15 - 20', c: temperatureColor(17)},
        {label:'20 - 25', c: temperatureColor(22)},
        {label:'≥ 25', c: temperatureColor(27)},
        {label:'Indéfini', c: temperatureColor(null)}
    ];
    classes.forEach(cl => {
        const item = document.createElement('div');
        item.className = 'legend-item';
        const box = document.createElement('span');
        box.className = 'color-box';
        box.style.backgroundColor = cl.c;
        item.appendChild(box);
        const txt = document.createElement('span');
        txt.textContent = cl.label;
        item.appendChild(txt);
        div.appendChild(item);
    });
    return div;
};
legend.addTo(map);
</script>
<?php if (!$colCommune || !$colTemp): ?>
<div style="position:absolute;top:10px;left:10px;background:#fff;padding:10px;border:1px solid #ccc;max-width:300px;">
    <strong>Attention:</strong> Colonnes requises introuvables dans le CSV.<br/>
    Noms attendus (variantes acceptées):<br/>
    Commune: commune|ville|municipalite|city<br/>
    Température: temperature|temp|t<br/>
    Coordonnées (optionnel): latitude|lat & longitude|lon|lng
</div>
<?php endif; ?>
</body>
</html>
