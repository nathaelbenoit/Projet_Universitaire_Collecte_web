<?php
/**
 * Script pour exécuter API.py avec les dates sélectionnées
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_debut = isset($_POST['date_debut']) ? sanitize_date($_POST['date_debut']) : null;
    $date_fin = isset($_POST['date_fin']) ? sanitize_date($_POST['date_fin']) : null;
    
    if ($date_debut && $date_fin) {
        $commande = sprintf(
            'python "%s" "%s" "%s"',
            __DIR__ . '/../scripts/API.py',
            $date_debut,
            $date_fin
        );
        
        // Exécuter le script
        $output = [];
        $return_code = 0;
        exec($commande, $output, $return_code);
        
        // Retourner le résultat en JSON
        header('Content-Type: application/json');
        echo json_encode([
            'succes' => $return_code === 0,
            'message' => implode("\n", $output),
            'code_erreur' => $return_code
        ]);
        exit;
    }
}

function sanitize_date($date) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    return null;
}
?>
