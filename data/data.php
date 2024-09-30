<?php 

    // data.php

    // reauire 'backdb.php'; // Assurez-vous que ce fichier ne renvoie pas de HTML

   // Simulez des données que vous souhaitez renvoyer
    
    $data = [
        'message' => 'Aujourd\'hui, le ' . date('d/m/Y') . ' ' . date('H:i:s'),
    ];

    // Renvoyer les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($data);
    exit; // Assurez-vous de sortir pour éviter d'afficher d'autres contenus


?>