<?php
/**
 * Fichier de fonctions utilitaires pour le dashboard
 */

// Augmenter la limite de mémoire pour les gros fichiers
ini_set('memory_limit', '512M');

/**
 * Charge et traite les données CSV avec limite d'échantillonnage
 */
function loadAndProcessData() {
    $csvFile = __DIR__ . '/dataFinal.csv';
    
    $data = [];
    $headers = [];
    
    // Si le fichier n'existe pas, retourner des données vides
    if (!file_exists($csvFile)) {
        return ['data' => $data, 'headers' => $headers];
    }

    if (($handle = fopen($csvFile, 'r')) !== false) {
        $headers = fgetcsv($handle, 1000, ',');
        
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
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
?>
