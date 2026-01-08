<?php
/**
 * COLLECTE DE DONNÉES VIA API
 * Exécute le script Python pour récupérer de nouvelles données météo
 */

function sanitize_date($date) {
    // Expression régulière: 4 chiffres - 2 chiffres - 2 chiffres
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

// Traite uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupère et valide les dates
    $date_debut = isset($_POST['date_debut']) ? sanitize_date($_POST['date_debut']) : null;
    $date_fin = isset($_POST['date_fin']) ? sanitize_date($_POST['date_fin']) : null;
    
    if ($date_debut && $date_fin) {
        // Construit la commande Python avec les dates
        $commande = sprintf('python "%s" "%s" "%s"', __DIR__ . '/../programme/API.py', $date_debut, $date_fin);
        
        // exec() exécute la commande et récupère le résultat
        exec($commande, $output, $return_code);
        
        // Renvoie le résultat en JSON
        header('Content-Type: application/json');
        echo json_encode([
            'succes' => $return_code === 0,  // 0 = succès, autre = erreur
            'message' => implode("\n", $output),
            'code_erreur' => $return_code
        ]);
        exit;
    }
}
?>
