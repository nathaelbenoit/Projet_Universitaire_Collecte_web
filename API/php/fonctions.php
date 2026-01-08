<?php
/**
 * FONCTIONS UTILITAIRES
 * Contient les fonctions réutilisables pour la gestion des données CSV
 */

// Augmente la limite mémoire PHP pour traiter de gros fichiers
ini_set('memory_limit', '512M');

/**
 * Charge et traite un fichier CSV
 */
function loadAndProcessData($filename = 'dataFinal.csv') {
    $csvFile = __DIR__ . '/../programme/' . $filename;
    
    // Crée le fichier s'il n'existe pas (évite les erreurs)
    if (!file_exists($csvFile)) {
        @file_put_contents($csvFile, '');
    }

    $data = [];
    $headers = [];
    
    // Lecture ligne par ligne du CSV
    if (($handle = fopen($csvFile, 'r')) !== false) {
        $headers = fgetcsv($handle, 1000, ',');
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // Associe chaque valeur à son en-tête
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
    }
    
    return ['data' => $data, 'headers' => $headers];
}

/**
 * Valide et sécurise le nom de fichier
 */
function sanitize_filename($filename) {
    // Liste blanche: accepte uniquement ces deux fichiers
    return in_array($filename, ['dataFinal.csv', 'dataPoitiers.csv']) ? $filename : 'dataFinal.csv';
}
?>
