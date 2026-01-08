<?php
/**
 * LECTURE DE FICHIERS CSV
 * Charge un fichier CSV existant et le renvoie en JSON
 */

// Indique que la réponse sera au format JSON
header('Content-Type: application/json');

// Liste blanche des fichiers autorisés (sécurité)
$allowed = ['dataFinal.csv', 'dataPoitiers.csv'];

// Récupère le paramètre GET et valide qu'il est dans la liste autorisée
$dataset = isset($_GET['dataset']) && in_array($_GET['dataset'], $allowed) ? $_GET['dataset'] : 'dataFinal.csv';

// __DIR__ = chemin du répertoire actuel
$csvFile = __DIR__ . '/../programme/' . $dataset;

$data = [];

// Ouvre le fichier CSV en lecture
if (file_exists($csvFile) && ($handle = fopen($csvFile, 'r'))) {
    // Première ligne = en-têtes des colonnes
    $headers = fgetcsv($handle, 1000, ',');
    
    // Lit chaque ligne et la transforme en tableau associatif
    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        // Vérifie que le nombre de colonnes correspond
        if (count($row) === count($headers)) {
            // array_combine associe en-têtes avec valeurs
            $data[] = array_combine($headers, $row);
        }
    }
    fclose($handle);
}

// Renvoie les données au format JSON
echo json_encode(['succes' => true, 'data' => $data, 'dataset' => $dataset]);
?>
