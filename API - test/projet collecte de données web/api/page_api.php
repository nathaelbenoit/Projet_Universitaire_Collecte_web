<?php
/**
 * Dashboard d'analyse météorologique
 * Page principale affichant les indicateurs visuels
 */

// Charger les fonctions utilitaires
require_once __DIR__ . '/functions.php';

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
    
    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="../public/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🌤️ Dashboard Météorologique</h1>
            <p>Projet universitaire collecte de données web - Analyse complète des données de stations météorologiques françaises </p>
            <div class="nav-buttons">
                <a href="#api">API</a>
                <a href="../index.php" style="opacity: 0.6;">Web Scraping</a>
            </div>
        </header>
        
        <!-- Zone de sélection de période -->
        <div id="api" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <h3 style="color: #1E90FF; margin-bottom: 15px;">📅 Sélectionner une période | période de 4 mois maximum</h3>
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
                    🌏 Carte Interactive - Température Moyenne aux Stations
                </div>
                <div style="margin: 20px; display: flex; gap: 15px; align-items: center;">
                    <label for="moisFiltre" style="font-weight: bold;">Sélectionner un mois :</label>
                    <select id="moisFiltre" style="padding: 8px 15px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; cursor: pointer;">
                        <option value="">Tous les mois</option>
                    </select>
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
                        <span>20-30°C</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #000000;"></div>
                        <span>&gt; 30°C</span>
                    </div>
                </div>
            </div>

            <!-- Indicateur 2: Graphique Comparatif -->
            <div class="card">
                <div class="card-header">
                    🌦️ Graphique Comparatif Pluviométrie / Humidité
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
                    💨 Vitesse du Vent Moyen par Région
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
                    ☀️ Température Moyenne par Mois
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="monthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Indicateur 5: Hauteur des Nuages par Région -->
            <div class="card">
                <div class="card-header">
                    ☁️ Hauteur Base Nuages Moyenne par Région
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
                            <div class="stat-value"><?php 
                                // Récupérer les dates min et max
                                $dates = array_map(function($row) {
                                    return isset($row['Date et heure d\'observation']) ? $row['Date et heure d\'observation'] : '';
                                }, $data);
                                
                                $dates = array_filter($dates);
                                
                                if (!empty($dates)) {
                                    sort($dates);
                                    $first_date = reset($dates);
                                    $last_date = end($dates);
                                    
                                    // Extraire mois et année
                                    $first_month = substr($first_date, 5, 2);
                                    $first_year = substr($first_date, 0, 4);
                                    $last_month = substr($last_date, 5, 2);
                                    $last_year = substr($last_date, 0, 4);
                                    
                                    // Tableau des noms de mois
                                    $mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 
                                            'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                                    
                                    $first_month_name = $mois[intval($first_month) - 1];
                                    $last_month_name = $mois[intval($last_month) - 1];
                                    
                                    // Si même mois et même année, afficher qu'une seule fois
                                    if ($first_month == $last_month && $first_year == $last_year) {
                                        echo $first_month_name . ' ' . $first_year;
                                    } else {
                                        echo $first_month_name . ' ' . $first_year . ' - ' . $last_month_name . ' ' . $last_year;
                                    }
                                } else {
                                    echo 'N/A';
                                }
                            ?></div>
                            <div class="stat-label">Période d'étude</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 Dashboard Météorologique - Projet Universitaire de Collecte de Données Web</p>
            <p>Données : OpenDataSoft - Données SYNOP | C . Camille P . Jérémi B . Nathaël</p>
        </footer>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Scripts personnalisés -->
    <script src="script.js"></script>
    
    <script>
        // Initialiser les données et afficher les graphiques
        const allData = <?php echo json_encode($data); ?>;
        
        // Initialiser la carte une fois que le DOM est chargé
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap(allData);
            initializeCharts(allData);
        });
    </script>
</body>
</html>
