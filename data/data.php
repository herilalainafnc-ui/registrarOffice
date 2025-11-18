<?php 

    // data.php

    // reauire 'backdb.php'; // Assurez-vous que ce fichier ne renvoie pas de HTML

   // Simulez des données que vous souhaitez renvoyer

    date_default_timezone_set('Indian/Antananarivo');

    $now = new DateTime('now', new DateTimeZone('Indian/Antananarivo'));
    $data = [
        'message' => "Aujourd'hui, le " . $now->format('d/m/Y') . ' ' . $now->format('H:i:s'),
        'iso' => $now->format(DateTime::ATOM),
    ];

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;


?>