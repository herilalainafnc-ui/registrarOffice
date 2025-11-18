<?php
require('../../data/backdb.php'); // connexion PDO stockée dans $dtb

$filiere_sigle = $_GET['filiere'];
$semester = $_GET['semester'];
$std_mention = $filiere_sigle;

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

// --- Préparation de l'UPDATE ---
$updateSQL = "
    UPDATE t_2024_finance_detail_licence SET
        frais_generaux = :frais_generaux,
        ecolage = :ecolage,
        laboratory_info = :laboratory_info,
        laboratory_lang = :laboratory_lang,
        dortoir = :dortoir,
        nb_jours_semestre = :nb_jours_semestre,
        cafeteria = :cafeteria,
        fond_depot = :fond_depot,
        frais_graduation = :frais_graduation,
        frais_costume = :frais_costume,
        frais_voyage = :frais_voyage
    WHERE level = :level 
      AND std_status = :status
      AND semester = :semester
      AND std_mention = :mention
";
$updateStmt = $dtb->prepare($updateSQL);

// --- Boucles ---
for ($level=1; $level < 6; $level++) {
    for ($status=1; $status < 3; $status++) {

        $stt = ($status==1) ? "Interne" : "Externe";

        // Valeurs par défaut
        $params = [
            
            ':level'            => $level,
            ':status'           => $stt,
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

        // Charger les données $_POST si présentes
        foreach ($allFrais as $frais) {
            $inputKey = $frais.'_'.$filiere_sigle.'_'.$level.'_'.$semester.'_'.$stt;
            if (isset($_POST[$inputKey]) && $_POST[$inputKey] !== '') {
                $params[":".$frais] = $_POST[$inputKey];
            }
        }

        // Exécution de l’UPDATE
        $updateStmt->execute($params);
    }
}

header('location:../gestion_finance.php?#pach_'.$filiere_sigle.'_'.$semester.'');

?>
