<?php

// Augmenter la limite de mémoire pour les gros fichiers
ini_set('memory_limit', '512M');

/**
 * Charge et traite les données CSV
 */
function loadAndProcessData($filename = 'dataFinal.csv') {
    // Construit le chemin complet du fichier CSV
    $csvFile = __DIR__ . '/../programme/' . $filename;
    
    // Créer un fichier CSV vide s'il n'existe pas
    if (!file_exists($csvFile)) {
        // @ supprime les avertissements PHP, retourne false si erreur
        if (@file_put_contents($csvFile, '') === false) {
            die("Erreur : Impossible de créer le fichier $filename.");
        }
    }

    $data = [];
    $headers = [];
    
    // Ouvre le fichier CSV en lecture
    if (($handle = fopen($csvFile, 'r')) !== false) {
        // Lit la première ligne comme en-têtes
        $headers = fgetcsv($handle, 1000, ',');
        
        // Boucle sur chaque ligne du CSV
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // array_combine associe les en-têtes aux valeurs (crée un objet)
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
    }
    
    return ['data' => $data, 'headers' => $headers];
}

/**
 * Convertit une date en année
 */
function getYear($dateString) {
    return substr($dateString, 0, 4);
}

/**
 * Calcule la distance entre deux points (formule de Haversine)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km - rayon terrestre moyen
    
    // Convertit les degrés en radians pour les calculs trigonométriques
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    // Calcule les différences de latitude et longitude
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    // Formule de Haversine: a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2)
    $a = sin($dlat / 2) * sin($dlat / 2) +
         cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
    
    // c = 2 ⋅ atan2( √a, √(1−a) )
    // d = R ⋅ c (où R est le rayon de la Terre)
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c; // Distance en kilomètres
}

/**
 * Valide et nettoie le nom de fichier
 */
function sanitize_filename($filename) {
    // Liste blanche des fichiers autorisés (prévention du directory traversal)
    $allowed = ['dataFinal.csv', 'dataPoitiers.csv'];
    // Retourne le nom si dans la whitelist, sinon 'dataFinal.csv' par défaut
    return in_array($filename, $allowed) ? $filename : 'dataFinal.csv';
}
?>
