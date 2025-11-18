<?php
	
	require('../data/backdb.php');

    // Étape 1 : Supprimer les doublons en gardant le plus petit id
    
    $sql = "
        DELETE t1 FROM t_2024_inscription_session t1
        JOIN t_2024_inscription_session t2
          ON t1.student_id = t2.student_id
         AND t1.session_id = t2.session_id
         AND t1.id > t2.id
    ";

    $stmt = $dtb->prepare($sql);
    $stmt->execute();

    echo "Doublons supprimés avec succès.";

?>