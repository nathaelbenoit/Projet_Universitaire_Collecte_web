<?php
/**
 * Script pour exécuter API.py avec les dates sélectionnées
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifie que date_debut existe, la valide, sinon retourne null
    $date_debut = isset($_POST['date_debut']) ? sanitize_date($_POST['date_debut']) : null;
    $date_fin = isset($_POST['date_fin']) ? sanitize_date($_POST['date_fin']) : null;
    
    // Exécute le script Python seulement si les deux dates sont valides
    if ($date_debut && $date_fin) {
        // Construit la commande Python avec les chemins et paramètres
        // sprintf remplace %s par les valeurs, %s = string
        $commande = sprintf(
            'python "%s" "%s" "%s"',
            __DIR__ . '/../programme/API.py', // Chemin du script Python
            $date_debut,                       // Paramètre 1: date de début
            $date_fin                          // Paramètre 2: date de fin
        );
        
        // Exécuter le script Python
        $output = [];          // Sera rempli avec la sortie du script
        $return_code = 0;      // Code retour du script
        // exec() exécute une commande système et stocke la sortie dans $output
        exec($commande, $output, $return_code);
        
        // Retourner le résultat en JSON
        header('Content-Type: application/json');
        echo json_encode([
            'succes' => $return_code === 0,    // True si pas d'erreur (code 0)
            'message' => implode("\n", $output), // Rejoint les lignes avec des sauts
            'code_erreur' => $return_code      // Code retour du script Python
        ]);
        exit; // Arrête l'exécution
    }
}

function sanitize_date($date) {
    // Regex: ^ = début, \d{4} = 4 chiffres, - = tiret, etc, $ = fin
    // Valide le format YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date; // Format valide, retourne la date
    }
    return null; // Format invalide, retourne null
}
?>
