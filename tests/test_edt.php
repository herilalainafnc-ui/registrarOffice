<?php
/* =============================================================================
   Socle de l'emploi du temps

   Couvre les regles qui n'avaient pas de test et qui se sont revelees fausses :
   la lecture des minutes, la validation d'un creneau, la disposition d'une
   journee (l'ancienne grille perdait des seances), et la detection des
   conflits — dont l'angle mort cours/examen dans une meme salle.

   Les deux derniers groupes touchent la base : ils inserent des seances dans
   une annee scolaire fictive, puis les effacent, y compris en cas d'echec.

   Lancement :  php tests/test_edt.php
   ========================================================================== */

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/edt.php';

$ok = 0; $ko = 0;
function verifie(string $quoi, $attendu, $obtenu) : void {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  ok   $quoi\n"; return; }
    $ko++;
    echo "  ECHEC $quoi\n       attendu: " . var_export($attendu, true)
       . "\n       obtenu : " . var_export($obtenu, true) . "\n";
}

echo "== Lecture des horaires\n";
verifie('08:00 -> 480', 480, edtMinutes('08:00'));
verifie('08:30 -> 510', 510, edtMinutes('08:30'));
verifie('08:30:00 (format SQL) -> 510', 510, edtMinutes('08:30:00'));
verifie('vide -> null', null, edtMinutes(''));
verifie('25:00 refuse', null, edtMinutes('25:00'));
verifie('510 -> 08:30', '08:30', edtHeure(510));

echo "\n== Validation d'un creneau\n";
verifie('creneau normal accepte', null, edtValiderCreneau('Lundi', '08:00', '10:00'));
verifie('fin avant debut refusee',
    "L'heure de fin doit suivre l'heure de début.",
    edtValiderCreneau('Lundi', '14:00', '10:00'));
verifie('fin egale au debut refusee',
    "L'heure de fin doit suivre l'heure de début.",
    edtValiderCreneau('Lundi', '10:00', '10:00'));
verifie('jour hors enum refuse', 'Jour invalide.', edtValiderCreneau('Dimanche', '08:00', '10:00'));
verifie('Samedi accepte', null, edtValiderCreneau('Samedi', '08:00', '10:00'));
verifie('duree de neuf heures refusee',
    'Une séance ne peut pas durer plus de huit heures.',
    edtValiderCreneau('Lundi', '08:00', '17:00'));

echo "\n== Disposition d'une journee\n";
/* Le cas exact qui faisait disparaitre des seances dans l'ancienne grille. */
$journee = [
    ['id' => 'A', 'heure_debut' => '08:00', 'heure_fin' => '11:00'],
    ['id' => 'B', 'heure_debut' => '08:00', 'heure_fin' => '09:00'],
    ['id' => 'C', 'heure_debut' => '09:00', 'heure_fin' => '11:00'],
    ['id' => 'D', 'heure_debut' => '10:00', 'heure_fin' => '12:00'],
    ['id' => 'E', 'heure_debut' => '08:30', 'heure_fin' => '10:30'],
];
$pose = edtDisposerJournee($journee);
verifie('aucune seance perdue', 5, count($pose));

$par = [];
foreach ($pose as $s) { $par[$s['id']] = $s; }
$ids = array_keys($par);
sort($ids);   // la fonction rend les seances triees par heure, pas par identifiant
verifie('les cinq sont bien la', ['A','B','C','D','E'], $ids);
verifie('rendues dans l ordre chronologique',
    ['A','B','E','C','D'], array_column($pose, 'id'));
verifie('B garde sa duree d une heure', 60, $par['B']['fin_min'] - $par['B']['debut_min']);
verifie('E est bien a 08:30 et pas a 08:00', 510, $par['E']['debut_min']);

/* Toutes se chevauchent de proche en proche : une seule grappe, donc un
   nombre de colonnes commun. */
$nbs = array_unique(array_column($pose, 'nb_colonnes'));
verifie('une seule largeur de colonne pour la grappe', 1, count($nbs));

/* Deux seances qui se chevauchent ne peuvent pas partager une colonne. */
$fautes = 0;
foreach ($pose as $x) {
    foreach ($pose as $y) {
        if ($x['id'] >= $y['id']) { continue; }
        $chevauche = $x['debut_min'] < $y['fin_min'] && $x['fin_min'] > $y['debut_min'];
        if ($chevauche && $x['colonne'] === $y['colonne']) { $fautes++; }
    }
}
verifie('aucun chevauchement dans une meme colonne', 0, $fautes);

/* Deux seances disjointes peuvent, elles, reutiliser la meme colonne. */
$suite = edtDisposerJournee([
    ['id' => 'X', 'heure_debut' => '08:00', 'heure_fin' => '09:00'],
    ['id' => 'Y', 'heure_debut' => '09:00', 'heure_fin' => '10:00'],
]);
verifie('deux seances qui se suivent tiennent sur une colonne',
    [1, 1], array_column($suite, 'nb_colonnes'));

echo "\n== Plage horaire\n";
verifie('plage par defaut sans seance', [480, 1020], edtPlage([]));
verifie('plage elargie par une seance matinale',
    [420, 1020],
    edtPlage([['heure_debut' => '07:30', 'heure_fin' => '09:00']]));
verifie('plage elargie par une seance tardive',
    [480, 1140],
    edtPlage([['heure_debut' => '17:00', 'heure_fin' => '19:00']]));

echo "\n== Detection des conflits (ecrit puis nettoie)\n";
$annee = 'TEST-EDT';
$poses = [];

$ins = $dtb->prepare(
    "INSERT INTO t_2024_emploi_du_temps
     (cours_id, cours_sigle, cours_title, type_seance, niveau, mention, parcours,
      semester, annee_scolaire, jour_semaine, heure_debut, heure_fin,
      salle_id, salle_code, id_teacher, statut, created_at, updated_at)
     VALUES (0, :sigle, :titre, :type, :niveau, :mention, :parcours,
             1, :annee, 'Mardi', :debut, :fin, :salle, 'TST', :prof,
             'confirme', NOW(), NOW())");

function poser(PDOStatement $ins, array $v, string $annee, array &$poses, PDO $dtb) : int {
    $ins->execute([
        ':sigle' => $v['sigle'], ':titre' => $v['sigle'], ':type' => $v['type'],
        ':niveau' => $v['niveau'], ':mention' => $v['mention'],
        ':parcours' => $v['parcours'], ':annee' => $annee,
        ':debut' => $v['debut'], ':fin' => $v['fin'],
        ':salle' => $v['salle'], ':prof' => $v['prof'],
    ]);
    $id = (int) $dtb->lastInsertId();
    $poses[] = $id;
    return $id;
}

try {
    $dtb->exec("DELETE FROM t_2024_emploi_du_temps WHERE annee_scolaire = " . $dtb->quote($annee));

    // Un cours de 08:00 a 10:00, salle 901, prof 9001, GEST L1, tous parcours.
    poser($ins, ['sigle' => 'REF 101', 'type' => 'cours', 'niveau' => '1',
                 'mention' => 'GEST', 'parcours' => 'all', 'debut' => '08:00',
                 'fin' => '10:00', 'salle' => 901, 'prof' => 9001], $annee, $poses, $dtb);

    $creneau = [
        'jour' => 'Mardi', 'annee_scolaire' => $annee, 'type_seance' => 'cours',
        'mention' => 'INFO', 'niveau' => '2', 'parcours' => '',
        'salle_id' => 0, 'id_teacher' => 0,
    ];

    // -- salle
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00', 'salle_id' => 901]));
    verifie('salle occupee -> conflit bloquant', true, edtBloquant($c));
    verifie('salle occupee -> type salle', 'salle', $c[0]['type']);

    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '10:00', 'fin' => '12:00', 'salle_id' => 901]));
    verifie('creneau adjacent -> aucun conflit', 0, count($c));

    // -- enseignant
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00', 'id_teacher' => 9001]));
    verifie('enseignant occupe -> conflit bloquant', true, edtBloquant($c));

    // -- annee scolaire cloisonnee
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00',
                                                  'salle_id' => 901, 'annee_scolaire' => 'AUTRE-ANNEE']));
    verifie('autre annee scolaire -> aucun conflit', 0, count($c));

    // -- etudiants : meme mention/niveau -> avertissement, non bloquant
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00',
                                                  'mention' => 'GEST', 'niveau' => '1', 'parcours' => 'all']));
    verifie('meme promotion -> un conflit', 1, count($c));
    verifie('meme promotion -> avertissement', 'warning', $c[0]['severity']);
    verifie('meme promotion -> non bloquant', false, edtBloquant($c));

    // -- parcours distincts et precis : pas de conflit etudiants
    $dtb->exec("UPDATE t_2024_emploi_du_temps SET parcours = 'Comptabilité'
                WHERE annee_scolaire = " . $dtb->quote($annee));
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00',
                                                  'mention' => 'GEST', 'niveau' => '1',
                                                  'parcours' => 'Marketing']));
    verifie('deux parcours precis differents -> aucun conflit', 0, count($c));

    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00',
                                                  'mention' => 'GEST', 'niveau' => '1',
                                                  'parcours' => 'Comptabilité']));
    verifie('meme parcours precis -> conflit', 1, count($c));

    /* -- l'angle mort : un COURS pose dans une salle retenue pour un EXAMEN.
          L'ancienne requete excluait les examens de ses resultats, donc ne
          signalait rien. */
    $dtb->exec("DELETE FROM t_2024_emploi_du_temps WHERE annee_scolaire = " . $dtb->quote($annee));
    $poses = [];
    poser($ins, ['sigle' => 'EXA 101', 'type' => 'examen', 'niveau' => '1',
                 'mention' => 'GEST', 'parcours' => 'all', 'debut' => '08:00',
                 'fin' => '10:00', 'salle' => 902, 'prof' => 0], $annee, $poses, $dtb);

    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00',
                                                  'salle_id' => 902, 'type_seance' => 'cours']));
    verifie('cours sur une salle d examen -> conflit bloquant', true, edtBloquant($c));

    // Deux examens, eux, peuvent partager la salle : surveillance commune.
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '09:00', 'fin' => '11:00',
                                                  'salle_id' => 902, 'type_seance' => 'examen']));
    verifie('deux examens partagent une salle -> aucun conflit', 0, count($c));

    // -- exclusion de la seance qu on modifie
    $id = $poses[0];
    $c = edtConflits($dtb, array_merge($creneau, ['debut' => '08:00', 'fin' => '10:00',
                                                  'salle_id' => 902, 'type_seance' => 'cours']), $id);
    verifie('la seance modifiee ne se gene pas elle-meme', 0, count($c));

} finally {
    $dtb->exec("DELETE FROM t_2024_emploi_du_temps WHERE annee_scolaire = " . $dtb->quote($annee));
    $reste = $dtb->query("SELECT COUNT(*) FROM t_2024_emploi_du_temps
                          WHERE annee_scolaire = " . $dtb->quote($annee))->fetchColumn();
    echo "\n  nettoyage : $reste ligne(s) de test restante(s)\n";
}

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko === 0 ? 0 : 1);
