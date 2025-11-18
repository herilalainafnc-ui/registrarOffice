<?php
require('../../data/backdb.php'); // connexion PDO stockée dans $dtb

$filiere_sigle = $_GET['filiere'];
$semester = $_GET['semester'];
$session_id = 0; 
$std_mention = $filiere_sigle; // j’imagine que mention = sigle de filière

// La liste de TOUS les frais à récupérer dans le formulaire
$allFrais = [
    "frais_generaux",
    "ecolage",
    "laboratory_info",
    "laboratory_lang",
    "dortoir",
    "nb_jours_semestre",
    "cafeteria",
    "fond_depot",
    "frais_graduation",
    "frais_costume",
    "frais_voyage"
];

$stmt = $dtb->prepare("
    INSERT INTO t_2024_finance_detail_licence 
        (categorie, level, std_status, session_id, semester, std_mention,
         frais_generaux, ecolage, laboratory_info, laboratory_lang, dortoir,
         nb_jours_semestre, cafeteria, fond_depot, frais_graduation, frais_costume, frais_voyage)
    VALUES
        (:categorie, :level, :status, :session_id, :semester, :mention,
         :frais_generaux, :ecolage, :laboratory_info, :laboratory_lang, :dortoir,
         :nb_jours_semestre, :cafeteria, :fond_depot, :frais_graduation, :frais_costume, :frais_voyage)
");

for ($level=1; $level < 6; $level++) {
    for ($status=1; $status < 3; $status++) {

        $stt = ($status==1) ? "Interne" : "Externe";

        // Définir la catégorie (L ou M)
        $categorie = ($level < 4) ? "L" : "M";

        // On initialise un tableau avec des valeurs par défaut
        $params = [
            ':categorie'        => $categorie,
            ':level'            => $level,
            ':status'           => $stt,
            ':session_id'       => $session_id,
            ':semester'         => $semester,
            ':mention'          => $std_mention,
            ':frais_generaux'   => 0,
            ':ecolage'          => 0,
            ':laboratory_info'  => 0,
            ':laboratory_lang'  => 0,
            ':dortoir'          => 0,
            ':nb_jours_semestre'=> 0,
            ':cafeteria'        => 0,
            ':fond_depot'       => 0,
            ':frais_graduation' => 0,
            ':frais_costume'    => 0,
            ':frais_voyage'     => 0,
        ];

        // Charger les vraies valeurs depuis $_POST si disponibles
        foreach ($allFrais as $frais) {
            $inputKey = $frais.'_'.$filiere_sigle.'_'.$level.'_'.$semester.'_'.$stt;
            if (isset($_POST[$inputKey]) && $_POST[$inputKey] !== '') {
                $params[":".$frais] = $_POST[$inputKey];
            }
        }

        // Exécution de l’insertion
        $stmt->execute($params);
    }
}

header('location:../gestion_finance.php?#pach_'.$filiere_sigle.'_'.$semester.'');

?>