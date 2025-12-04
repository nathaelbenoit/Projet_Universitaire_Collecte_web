<?php
/**
 * Dashboard d'analyse météorologique
 * Génère 4 indicateurs visuels à partir des données collectées
 */

// Fonction pour charger et traiter les données CSV
function loadAndProcessData() {
    $csvFile = __DIR__ . '/dataFinal.csv';
    
    if (!file_exists($csvFile)) {
        die("Erreur : Le fichier dataFinal.csv n'existe pas.");
    }

    $data = [];
    $headers = [];
    
    if (($handle = fopen($csvFile, 'r')) !== false) {
        $headers = fgetcsv($handle, 1000, ',');
        
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
    }
    
    return ['data' => $data, 'headers' => $headers];
}

// Fonction pour convertir une date en année
function getYear($dateString) {
    return substr($dateString, 0, 4);
}

// Fonction pour calculer la distance entre deux points (formule de Haversine)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat / 2) * sin($dlat / 2) +
         cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

// Charger les données
$processedData = loadAndProcessData();
$data = $processedData['data'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Météorologique - Analyse des Données</title>
    
    <!-- Folium CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Plotly.js -->
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #87CEEB 0%, #E0E0E0 50%, #87CEEB 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        header h1 {
            color: #1E90FF;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        
        .card-header {
            background: linear-gradient(135deg, #1E90FF 0%, #4169E1 100%);
            color: white;
            padding: 20px;
            font-size: 1.4em;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .card-content {
            padding: 20px;
            height: 500px;
            overflow-y: auto;
        }
        
        #map {
            height: 500px;
            border-radius: 0 0 10px 10px;
        }
        
        .chart-container {
            position: relative;
            height: 100%;
            width: 100%;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #4169E1 0%, #1E90FF 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(31, 144, 255, 0.3);
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .temperature-legend {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            padding: 15px 0;
            font-size: 0.9em;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 5px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #666;
        }
        
        footer {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            color: #666;
            margin-top: 30px;
        }
        
        .info-panel {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.9em;
            line-height: 1.6;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🌤️ Dashboard Météorologique</h1>
            <p>Analyse complète des données de stations météorologiques françaises</p>
        </header>

        <!-- Zone de sélection de période -->
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <h3 style="color: #1E90FF; margin-bottom: 15px;">📅 Sélectionner une période</h3>
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label for="datéDebut" style="display: block; margin-bottom: 5px; font-weight: bold;">Date de début :</label>
                    <input type="date" id="datéDebut" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em;" />
                </div>
                <div>
                    <label for="datéFin" style="display: block; margin-bottom: 5px; font-weight: bold;">Date de fin :</label>
                    <input type="date" id="datéFin" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em;" />
                </div>
                <button id="boutonCharger" style="padding: 8px 24px; background: linear-gradient(135deg, #1E90FF 0%, #4169E1 100%); color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1em;">
                    ⬆️ Charger les données
                </button>
                <div id="indicateurChargement" style="display: none; color: #666;">
                    <span>⏳ Chargement en cours...</span>
                </div>
            </div>
            <div id="messageRésultat" style="margin-top: 15px; padding: 12px; border-radius: 5px; display: none; font-weight: bold;"></div>
        </div>

        <div class="dashboard-grid">
            <!-- Indicateur 1: Carte Folium Interactive -->
            <div class="card full-width">
                <div class="card-header">
                    📍 Indicateur 1 : Carte Interactive - Température Moyenne par Commune
                </div>
                <div class="info-panel" style="margin: 20px;">
                    <strong>Description :</strong> Affiche une carte interactive avec des marqueurs colorés représentant la température moyenne par commune. 
                    Les couleurs varient du bleu (froid) au rouge (chaud).
                </div>
                <div id="map"></div>
                <div class="temperature-legend" style="padding: 15px 20px;">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #0033FF;"></div>
                        <span>&lt; 0°C</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #00CCFF;"></div>
                        <span>0-5°C</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #00FF00;"></div>
                        <span>5-10°C</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #FFFF00;"></div>
                        <span>10-15°C</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #FF6600;"></div>
                        <span>15-20°C</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #FF0000;"></div>
                        <span>&gt; 20°C</span>
                    </div>
                </div>
            </div>

            <!-- Indicateur 2: Graphique Comparatif -->
            <div class="card">
                <div class="card-header">
                    📊 Indicateur 2 : Graphique Comparatif
                </div>
                <div class="info-panel" style="margin: 20px; margin-bottom: 10px;">
                    <strong>Comparaison :</strong> Pluviométrie (24h) et Humidité relative (%) par région et année.
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="comparativeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Indicateur 3: Vitesse du Vent par Région -->
            <div class="card">
                <div class="card-header">
                    💨 Indicateur 3 : Vitesse du Vent Moyen par Région
                </div>
                <div class="info-panel" style="margin: 20px; margin-bottom: 10px;">
                    <strong>Analyse :</strong> Comparaison de la vitesse du vent moyen par région (basée sur toutes les observations).
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="yearChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Indicateur 4: Température par Mois -->
            <div class="card">
                <div class="card-header">
                    🗓️ Indicateur 4 : Température Moyenne par Mois
                </div>
                <div class="info-panel" style="margin: 20px; margin-bottom: 10px;">
                    <strong>Analyse :</strong> Évolution des températures moyennes par mois (basée sur les meilleures observations).
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="monthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Indicateur 5: Température par Région -->
            <div class="card">
                <div class="card-header">
                    🌍 Indicateur 5 : Température Moyenne par Région
                </div>
                <div class="info-panel" style="margin: 20px; margin-bottom: 10px;">
                    <strong>Analyse :</strong> Comparaison des températures moyennes par région (basée sur les meilleures observations).
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="regionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Statistiques Globales -->
            <div class="card full-width">
                <div class="card-header">
                    📈 Statistiques Globales
                </div>
                <div class="card-content" style="height: auto; overflow: visible;">
                    <div class="stats">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo count($data); ?></div>
                            <div class="stat-label">Observations totales</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php 
                                $regions = array_unique(array_map(function($row) { 
                                    return isset($row['Nom région']) ? $row['Nom région'] : 'N/A';                                    
                                }, $data));
                                echo count(array_filter($regions, function($r) { return $r !== 'N/A'; }));
                            ?></div>
                            <div class="stat-label">Régions couvertes</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php 
                                $stations = array_unique(array_map(function($row) { 
                                    return $row['Nom de la station']; 
                                }, $data));
                                echo count($stations);
                            ?></div>
                            <div class="stat-label">Stations météo</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">Janv 2025</div>
                            <div class="stat-label">Période d'étude</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 Dashboard Météorologique - Projet Universitaire de Collecte Web</p>
            <p>Données : OpenDataSoft - Données SYNOP essentielles OMM</p>
        </footer>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // ============================================
        // GESTION DU CHARGEMENT DE DONNÉES
        // ============================================
        
        document.getElementById('boutonCharger').addEventListener('click', function() {
            const datéDebut = document.getElementById('datéDebut').value;
            const datéFin = document.getElementById('datéFin').value;
            
            if (!datéDebut || !datéFin) {
                afficherMessage('Veuillez sélectionner les deux dates', 'erreur');
                return;
            }
            
            if (new Date(datéDebut) > new Date(datéFin)) {
                afficherMessage('La date de début doit être antérieure à la date de fin', 'erreur');
                return;
            }
            
            afficherChargement(true);
            
            fetch('chargerDonnees.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `date_debut=${encodeURIComponent(datéDebut)}&date_fin=${encodeURIComponent(datéFin)}`
            })
            .then(response => response.json())
            .then(data => {
                afficherChargement(false);
                if (data.succes) {
                    afficherMessage('Données chargées avec succès ! La page va se rafraîchir...', 'succes');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    afficherMessage('Erreur : ' + data.message, 'erreur');
                }
            })
            .catch(error => {
                afficherChargement(false);
                afficherMessage('Erreur de communication : ' + error.message, 'erreur');
            });
        });
        
        function afficherChargement(afficher) {
            document.getElementById('indicateurChargement').style.display = afficher ? 'block' : 'none';
        }
        
        function afficherMessage(texte, type) {
            const messageDiv = document.getElementById('messageRésultat');
            messageDiv.textContent = texte;
            messageDiv.style.display = 'block';
            
            if (type === 'succes') {
                messageDiv.style.backgroundColor = '#d4edda';
                messageDiv.style.color = '#155724';
            } else {
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
            }
        }
        
        // Données PHP converties en JSON
        const allData = <?php echo json_encode($data); ?>;
        
        
        // ============================================
        // INDICATEUR 1 : CARTE INTERACTIVE FOLIUM
        // ============================================
        
        // Calcul des températures moyennes par commune
        const temperatureByCommune = {};
        
        allData.forEach(row => {
            const commune = row['Libellé géographique'] || 'Inconnu';
            const lat = parseFloat(row['Latitude']);
            const lon = parseFloat(row['Longitude']);
            const temp = parseFloat(row['Température (°C)']);
            
            if (!isNaN(lat) && !isNaN(lon) && !isNaN(temp)) {
                if (!temperatureByCommune[commune]) {
                    temperatureByCommune[commune] = {
                        lat: lat,
                        lon: lon,
                        temps: [],
                        region: row['Nom région']
                    };
                }
                temperatureByCommune[commune].temps.push(temp);
            }
        });
        
        // Calculer la moyenne pour chaque commune
        Object.keys(temperatureByCommune).forEach(commune => {
            const data = temperatureByCommune[commune];
            const avg = data.temps.reduce((a, b) => a + b, 0) / data.temps.length;
            data.avg = avg;
        });
        
        // Fonction pour obtenir la couleur en fonction de la température
        function getTemperatureColor(temp) {
            if (temp < 0) return '#0033FF';      // Bleu foncé
            if (temp < 5) return '#00CCFF';      // Bleu ciel
            if (temp < 10) return '#00FF00';     // Vert
            if (temp < 15) return '#90EE90';     // Vert clair
            if (temp < 20) return '#FF6600';     // Orange
            return '#FF0000';                     // Rouge
        }
        
        // Initialiser la carte
        const map = L.map('map').setView([46.5, 2.0], 5);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Ajouter les marqueurs
        Object.keys(temperatureByCommune).forEach(commune => {
            const data = temperatureByCommune[commune];
            const color = getTemperatureColor(data.avg);
            
            L.circleMarker([data.lat, data.lon], {
                radius: 8,
                fillColor: color,
                color: '#333',
                weight: 1,
                opacity: 0.8,
                fillOpacity: 0.8
            }).bindPopup(
                `<b>${commune}</b><br>` +
                `Température: ${data.avg.toFixed(2)}°C<br>` +
                `Région: ${data.region}<br>` +
                `Observations: ${data.temps.length}`
            ).addTo(map);
        });
        
        // ============================================
        // INDICATEUR 2 : GRAPHIQUE COMPARATIF
        // ============================================
        
        const dataPluieHumiditéParRegionAnnée = {};
        
        allData.forEach(row => {
            const region = row['Nom région'] || 'Inconnu';
            const year = row['Date et heure d\'observation'].substring(0, 4);
            const pluie = parseFloat(row['Précipitations 24h (mm)']) || 0;
            const humidite = parseFloat(row['Humidité relative (%)']) || 0;
            
            const key = `${region}-${year}`;
            if (!dataPluieHumiditéParRegionAnnée[key]) {
                dataPluieHumiditéParRegionAnnée[key] = {
                    region: region,
                    year: year,
                    pluies: [],
                    humidites: []
                };
            }
            
            if (pluie > 0) dataPluieHumiditéParRegionAnnée[key].pluies.push(pluie);
            if (humidite > 0) dataPluieHumiditéParRegionAnnée[key].humidites.push(humidite);
        });
        
        const labels = [];
        const pluieMoyenne = [];
        const humiditeAverage = [];
        
        Object.keys(dataPluieHumiditéParRegionAnnée).slice(0, 15).forEach(key => {
            const data = dataPluieHumiditéParRegionAnnée[key];
            labels.push(`${data.region.substring(0, 10)}`);
            
            const pluieAvg = data.pluies.length > 0 
                ? data.pluies.reduce((a, b) => a + b, 0) / data.pluies.length 
                : 0;
            const humAvg = data.humidites.length > 0 
                ? data.humidites.reduce((a, b) => a + b, 0) / data.humidites.length 
                : 0;
            
            pluieMoyenne.push(pluieAvg);
            humiditeAverage.push(humAvg);
        });
        
        const ctx = document.getElementById('comparativeChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pluviométrie 24h (mm)',
                        data: pluieMoyenne,
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgb(54, 162, 235)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Humidité (%)',
                        data: humiditeAverage,
                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Pluviométrie (mm)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Humidité (%)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
        
        // ============================================
        // INDICATEUR 3 : TEMPÉRATURE PAR ANNÉES
        // ============================================
        
        // Obtenir les meilleures données (températures les plus élevées)
        const donnéesTriéesParTempérature = allData.sort((a, b) => 
            parseFloat(b['Température (°C)']) - parseFloat(a['Température (°C)'])
        );
        const meilleuresDonnées = donnéesTriéesParTempérature.slice(0, Math.ceil(allData.length * 0.3)); // Top 30%
        
        const tempParAnnée = {};
        
        meilleuresDonnées.forEach(row => {
            const année = row['Date et heure d\'observation'].substring(0, 4);
            const temp = parseFloat(row['Température (°C)']) || 0;
            
            if (!tempParAnnée[année]) {
                tempParAnnée[année] = [];
            }
            tempParAnnée[année].push(temp);
        });
        
        const étiquettesAnnées = Object.keys(tempParAnnée).sort();
        const températuresMoyennesAnnées = étiquettesAnnées.map(année => {
            const temperatures = tempParAnnée[année];
            return temperatures.reduce((a, b) => a + b, 0) / temperatures.length;
        });
        
        
        // ============================================
        // INDICATEUR 3 : VITESSE DU VENT PAR RÉGION
        // ============================================
        
        const vitesseVentParRégion = {};
        
        allData.forEach(row => {
            const région = row['Nom région'] || 'Inconnu';
            const vitesseVent = parseFloat(row['Vitesse du vent moyen 10mn']) || 0;
            
            if (!isNaN(vitesseVent) && vitesseVent >= 0) {
                if (!vitesseVentParRégion[région]) {
                    vitesseVentParRégion[région] = [];
                }
                vitesseVentParRégion[région].push(vitesseVent);
            }
        });
        
        const étiquettesVitesseRégions = [];
        const vitesseMoyenneParRégion = [];
        
        Object.keys(vitesseVentParRégion).sort().forEach(région => {
            const vitesses = vitesseVentParRégion[région];
            const moyenne = vitesses.length > 0 ? vitesses.reduce((a, b) => a + b, 0) / vitesses.length : 0;
            étiquettesVitesseRégions.push(région.substring(0, 20));
            vitesseMoyenneParRégion.push(moyenne.toFixed(2));
        });
        
        const ctxAnnée = document.getElementById('yearChart').getContext('2d');
        new Chart(ctxAnnée, {
            type: 'bar',
            data: {
                labels: étiquettesVitesseRégions,
                datasets: [{
                    label: 'Vitesse du vent moyen (m/s)',
                    data: vitesseMoyenneParRégion,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(199, 199, 199, 0.8)',
                        'rgba(83, 102, 255, 0.8)',
                        'rgba(255, 99, 255, 0.8)',
                        'rgba(99, 255, 132, 0.8)'
                    ],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(75, 192, 192)',
                        'rgb(255, 206, 86)',
                        'rgb(153, 102, 255)',
                        'rgb(255, 159, 64)',
                        'rgb(199, 199, 199)',
                        'rgb(83, 102, 255)',
                        'rgb(255, 99, 255)',
                        'rgb(99, 255, 132)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Vitesse du vent (m/s)'
                        }
                    }
                }
            }
        });
        
        // ============================================
        // INDICATEUR 4 : TEMPÉRATURE PAR MOIS
        // ============================================
        
        const tempParMois = {};
        
        // Utiliser toutes les données pour avoir plus de mois
        allData.forEach(row => {
            const chaîneDate = row['Date et heure d\'observation'];
            const mois = chaîneDate.substring(5, 7);
            const nomsMois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            const étiquetteMois = nomsMois[parseInt(mois) - 1] || `Mois ${mois}`;
            const temp = parseFloat(row['Température (°C)']) || 0;
            
            if (!isNaN(temp) && temp !== 0) {
                if (!tempParMois[mois]) {
                    tempParMois[mois] = {
                        étiquette: étiquetteMois,
                        temperatures: []
                    };
                }
                tempParMois[mois].temperatures.push(temp);
            }
        });
        
        const étiquettesMois = Object.keys(tempParMois).sort((a, b) => parseInt(a) - parseInt(b)).map(m => tempParMois[m].étiquette);
        const températuresMoyennesMois = Object.keys(tempParMois).sort((a, b) => parseInt(a) - parseInt(b)).map(m => {
            const temperatures = tempParMois[m].temperatures;
            return temperatures.length > 0 ? temperatures.reduce((a, b) => a + b, 0) / temperatures.length : 0;
        });
        
        const ctxMois = document.getElementById('monthChart').getContext('2d');
        new Chart(ctxMois, {
            type: 'bar',
            data: {
                labels: étiquettesMois,
                datasets: [{
                    label: 'Température moyenne (°C)',
                    data: températuresMoyennesMois,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Température (°C)'
                        }
                    }
                }
            }
        });
        
        // ============================================
        // INDICATEUR 5 : TEMPÉRATURE PAR RÉGION
        // ============================================
        
        const tempParRégion = {};
        
        meilleuresDonnées.forEach(row => {
            const région = row['Nom région'] || 'Inconnu';
            const temp = parseFloat(row['Température (°C)']) || 0;
            
            if (!tempParRégion[région]) {
                tempParRégion[région] = [];
            }
            tempParRégion[région].push(temp);
        });
        
        const étiquettesRégions = [];
        const températuresmoyennesRégions = [];
        
        Object.keys(tempParRégion).sort().forEach(région => {
            const temperatures = tempParRégion[région];
            const moyenne = temperatures.reduce((a, b) => a + b, 0) / temperatures.length;
            étiquettesRégions.push(région.substring(0, 20));
            températuresmoyennesRégions.push(moyenne);
        });
        
        const ctxRégion = document.getElementById('regionChart').getContext('2d');
        new Chart(ctxRégion, {
            type: 'bar',
            data: {
                labels: étiquettesRégions,
                datasets: [{
                    label: 'Température moyenne (°C)',
                    data: températuresmoyennesRégions,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235)',
                        'rgba(75, 192, 192)',
                        'rgba(54, 162, 235)',
                        'rgba(75, 192, 192)',
                        'rgba(54, 162, 235)',
                        'rgba(75, 192, 192)',
                        'rgba(54, 162, 235)',
                        'rgba(75, 192, 192)',
                        'rgba(54, 162, 235)',
                        'rgba(75, 192, 192)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Température (°C)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
