<?php
/**
 * Dashboard d'analyse météorologique
 * Page principale affichant les indicateurs visuels
 */

// Charger les fonctions utilitaires
require_once __DIR__ . '/api/functions.php';

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
    <link rel="stylesheet" href="public/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🌤️ Dashboard Météorologique</h1>
            <p>Projet universitaire collecte de données web - Analyse complète des données de stations météorologiques françaises </p>
            <div class="nav-buttons">
                <a href="./api/page_api.php" style="opacity: 0.6;">API</a>
                <a href="#web">Web Scraping</a>
            </div>
        </header>

        <!-- Zone de sélection de période -->
        <div id="web" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
        </div>
    </div>
</body>
</html>
