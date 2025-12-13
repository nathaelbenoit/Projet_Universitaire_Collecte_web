<?php
/**
 * Endpoint pour charger les données d'un dataset spécifique
 */

header('Content-Type: application/json');

function sanitize_filename($filename) {
    // Liste blanche: seuls ces fichiers sont autorisés
    $allowed = ['dataFinal.csv', 'dataPoitiers.csv'];
    // in_array() = check si la valeur existe dans le tableau
    // Retourne le nom si autorisé, sinon 'dataFinal.csv' par sécurité
    return in_array($filename, $allowed) ? $filename : 'dataFinal.csv';
}

// Récupère le paramètre GET 'dataset', le valide, ou utilise 'dataFinal.csv' par défaut
$dataset = isset($_GET['dataset']) ? sanitize_filename($_GET['dataset']) : 'dataFinal.csv';
// Construit le chemin complet du fichier
$csvFile = __DIR__ . '/../programme/' . $dataset;

$data = [];

// Vérifie que le fichier existe avant de le lire
if (file_exists($csvFile)) {
    // Ouvre le fichier en mode lecture ('r')
    if (($handle = fopen($csvFile, 'r')) !== false) {
        // Lit la première ligne comme en-têtes
        $headers = fgetcsv($handle, 1000, ',');
        
        // Lit chaque ligne du CSV
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // Vérifie que le nombre de colonnes correspond aux en-têtes
            if (count($row) === count($headers)) {
                // array_combine = crée un tableau associatif [clé => valeur]
                $data[] = array_combine($headers, $row);
            }
        }
        fclose($handle);
    }
}

echo json_encode([
    'succes' => true,
    'data' => $data,
    'dataset' => $dataset
]);
?>
