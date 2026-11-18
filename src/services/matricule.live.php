<?php
/**
 * Propose le prochain matricule disponible pour une mention et un cycle.
 *
 * Appele en direct par le formulaire de creation d'etudiant
 * (src/creat.student.php) a chaque changement de mention ou de niveau.
 *
 * Le matricule encode la mention dans sa plage numerique (Theologie 10000,
 * Gestion 20000, ...). Les etudiants de master portent en plus un suffixe
 * « M » suivi de l'initiale de la mention : 20481MG = master Gestion.
 *
 * Corrections apportees a la version precedente :
 *
 *  - la mention arrivait directement dans la requete (injection SQL) ;
 *  - le service repondait a une requete anonyme, alors qu'il n'est appele
 *    que depuis une page authentifiee ;
 *  - le plus grand matricule etait cherche avec ORDER BY sur une colonne
 *    VARCHAR : le tri etait alphabetique, donc « 9999 » passait avant
 *    « 10000 ». La proposition pouvait ainsi porter sur un numero deja
 *    attribue. Le tri est desormais numerique ;
 *  - la branche master triait par `id` (ordre d'insertion) et non par
 *    matricule, ce qui renvoyait le dernier inscrit plutot que le plus
 *    grand numero ;
 *  - la proposition n'etait pas verifiee : on avance maintenant jusqu'au
 *    premier matricule reellement libre ;
 *  - les plages par mention etaient recopiees deux fois, et CPRE manquait
 *    dans la liste master.
 */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

require(__DIR__ . '/../../data/backdb.php');

/* Debut de plage numerique par mention. */
$plages = [
    'THEO' => 10000,
    'GEST' => 20000,
    'INFO' => 30000,
    'NURS' => 40000,
    'EDUC' => 50000,
    'COMM' => 60000,
    'LANG' => 70000,
    'CPRE' => 80000,
    'DROI' => 90000,
];

$mention     = isset($_POST['mention']) ? trim((string) $_POST['mention']) : '';
$annee_etude = isset($_POST['annee_etude']) ? (int) $_POST['annee_etude'] : 0;

$findMention = $dtb->prepare('SELECT filiere_description FROM filiere WHERE filiere_sigle = ? LIMIT 1');
$findMention->execute([$mention]);
$etude_envisage = $findMention->fetchColumn();

if ($etude_envisage === false || !isset($plages[$mention])) {
    echo "00000"; // mention inconnue : rien de sensé à proposer
    return;
}

/* Master (au-dela de la 3e annee) : suffixe M + initiale de la mention. */
$estMaster = ($annee_etude > 3);
$suffixe   = $estMaster ? 'M' . substr($mention, 0, 1) : '';

/* Plus grand numero deja attribue dans cette mention et ce cycle.
   CAST(... AS UNSIGNED) lit la partie numerique de tete, ce qui donne un
   ordre correct malgre le type VARCHAR de la colonne et le suffixe des
   masters. */
$sql = 'SELECT MAX(CAST(student_id AS UNSIGNED))
          FROM tbl_2024_etudiant
         WHERE etude_envisage = ?
           AND annee_etude ' . ($estMaster ? '> 3' : '<= 3');
$plusGrand = $dtb->prepare($sql);
$plusGrand->execute([$etude_envisage]);
$max = $plusGrand->fetchColumn();

$numero = ($max !== null && $max !== false && (int) $max > 0)
    ? ((int) $max) + 1
    : $plages[$mention];

/* On ne propose jamais un matricule deja pris : la table peut contenir des
   numeros attribues hors de ce cycle, ou des doublons anciens. La creation
   d'etudiant refait cette verification au moment d'ecrire, mais autant
   afficher d'emblee une valeur juste. */
$test = $dtb->prepare('SELECT 1 FROM tbl_2024_etudiant WHERE student_id = ? LIMIT 1');
for ($i = 0; $i < 1000; $i++) {
    $candidat = $numero . $suffixe;
    $test->execute([$candidat]);
    if ($test->fetchColumn() === false) {
        echo $candidat;
        return;
    }
    $numero++;
}

/* Plage saturee : on le signale plutot que de renvoyer un numero faux. */
error_log('matricule.live: aucun matricule libre pour ' . $mention . ($estMaster ? ' (master)' : ''));
echo "00000";
