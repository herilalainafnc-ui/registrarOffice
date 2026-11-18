<?php
/*
 * Mode hors ligne du registre de presence : ce que le SERVEUR garantit quand une ecriture faite
 * sans reseau arrive plus tard (app/.presence/enregistrer.php, app/session.jeton.php).
 *
 *   - session.jeton.php : 401 sans session, sinon compte + jeton frais + heure du serveur ;
 *   - client_le (l'instant ou l'enseignant a agi) : l'ecriture est datee de cet instant, refusee en
 *     409 si la donnee a ete modifiee depuis, sauf « forcer » ; un instant absurde est ignore ;
 *   - la remise des notes (app/.remise/enregistrer.php) suit les memes regles : cellule, ponderation, feuille figee ;
 *   - rejouer deux fois une ecriture ne change rien de plus (suppression : 404 la seconde fois).
 *
 * Il faut le serveur local (php -S 127.0.0.1:8137 ... router.php) et la base locale ; sinon le test
 * le dit et s'arrete sans echec. Il cree puis efface un registre sur le cours de test.
 *
 *   php tests/test_hors_ligne.php
 */
$base = dirname(__DIR__);
require "$base/data/config.php";
require "$base/data/presence.php";
require_once "$base/data/remise.notes.php";

date_default_timezone_set('Indian/Antananarivo');   // le fuseau de l'application : les dates en base sont a cette heure

const HOTE = 'http://127.0.0.1:8137';
$ok = 0; $ko = 0;
function verifier(string $nom, bool $cond, string $detail = ''): void
{
    global $ok, $ko;
    if ($cond) { $ok++; echo "  ok  $nom\n"; }
    else { $ko++; echo "  KO  $nom" . ($detail !== '' ? "  ($detail)" : '') . "\n"; }
}

/** Une requete HTTP : [statut, corps decode]. */
function requete(string $methode, string $chemin, ?string $sid, array $champs = []): array
{
    $entetes = "X-Requested-With: XMLHttpRequest\r\n" . ($sid ? "Cookie: PHPSESSID=$sid\r\n" : '');
    $opts = ['http' => ['method' => $methode, 'header' => $entetes, 'ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]];
    if ($methode === 'POST') {
        $opts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $opts['http']['content'] = http_build_query($champs);
    }
    $corps = @file_get_contents(HOTE . $chemin, false, stream_context_create($opts));
    $statut = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $statut = (int) $m[1]; } }
    return [$statut, json_decode((string) $corps, true) ?? []];
}

if (@file_get_contents(HOTE . '/login', false, stream_context_create(['http' => ['timeout' => 3]])) === false) {
    echo "Serveur local absent (" . HOTE . ") : test non joue.\n";
    exit(0);
}

$dtb = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
$coursId = 74; $annee = '2024 - 2025'; $semestre = 2;
$compte = $dtb->query('SELECT id, level, privilege, pseudo, password FROM compt_utilisateur WHERE id = 190')->fetch(PDO::FETCH_ASSOC);
if (!$compte) { echo "Compte de test #190 absent : test non joue.\n"; exit(0); }

/* Une session locale du compte (comme forger-session-compte). */
session_save_path('C:/laragon/tmp');
$sid = 'testhl' . bin2hex(random_bytes(4));
session_id($sid);
session_start();
$_SESSION['user_id'] = (int) $compte['id']; $_SESSION['user_level'] = (int) $compte['level']; $_SESSION['user_privilege'] = (string) $compte['privilege'];
$_SESSION['infinit_pseudo'] = (string) $compte['pseudo']; $_SESSION['infinit_password'] = (string) $compte['password'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(16)); $_SESSION['csrf_token_time'] = time();
session_write_close();

function poidsSomme(PDO $dtb, int $rid): float
{
    $b = (float) $dtb->query("SELECT poids_quiz + poids_recherche + poids_autres + poids_mi_semestre + poids_final FROM t_remise_notes WHERE id = $rid")->fetchColumn();
    $e = (float) $dtb->query("SELECT COALESCE(SUM(poids), 0) FROM t_remise_notes_colonne WHERE remise_id = $rid")->fetchColumn();
    return round($b + $e, 2);
}

function nettoyer(PDO $dtb, int $coursId, string $annee, int $semestre): void
{
    foreach ($dtb->query("SELECT id FROM t_presence_feuille WHERE cours_id = $coursId AND annee_scolaire = " . $dtb->quote($annee) . " AND semestre = $semestre")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $dtb->exec("DELETE l FROM t_presence_ligne l JOIN t_presence_seance s ON s.id = l.seance_id WHERE s.feuille_id = $f");
        $dtb->exec("DELETE FROM t_presence_seance WHERE feuille_id = $f");
        $dtb->exec("DELETE FROM t_presence_feuille WHERE id = $f");
    }
}

echo "Session et jeton (app/session.jeton.php)\n";
[$s, $d] = requete('GET', '/app/session.jeton.php', null);
verifier('sans session : 401', $s === 401, (string) $s);
[$s, $d] = requete('GET', '/app/session.jeton.php', $sid);
verifier('avec session : le compte, un jeton, l\'heure du serveur',
    $s === 200 && ($d['utilisateur'] ?? 0) === 190 && strlen((string) ($d['csrf_token'] ?? '')) >= 16 && abs(($d['serveur_le'] ?? 0) - microtime(true) * 1000) < 5000, json_encode($d));
$csrf = $d['csrf_token'];

nettoyer($dtb, $coursId, $annee, $semestre);
$feuille = presenceFeuilleOuvrir($dtb, $coursId, $annee, $semestre);
$ecrire = static function (array $champs) use ($sid, $csrf, $feuille) {
    return requete('POST', '/app/.presence/enregistrer.php', $sid, ['csrf_token' => $csrf, 'feuille_id' => $feuille['id']] + $champs);
};
[$s, $d] = $ecrire(['action' => 'seance_creer', 'date_seance' => date('Y-m-d'), 'heure_prevue' => '08:00', 'arrivee_enseignant' => '08:00']);
$seance = (int) ($d['seance_id'] ?? 0);
verifier('seance creee', $s === 200 && $seance > 0, json_encode($d));
[$s, $d2] = $ecrire(['action' => 'seance_creer', 'date_seance' => date('Y-m-d'), 'heure_prevue' => '08:00', 'arrivee_enseignant' => '08:00']);
verifier('la creer deux fois : la meme seance, sans doublon', $s === 200 && (int) ($d2['seance_id'] ?? 0) === $seance && !empty($d2['existait']), json_encode($d2));
$etu = $dtb->query("SELECT student_id FROM t_presence_ligne WHERE seance_id = $seance ORDER BY student_id LIMIT 1")->fetchColumn();
verifier('les etudiants du cours sont dans l\'appel', (bool) $etu);

$ligne = static fn () => $dtb->query("SELECT statut, minutes_retard, modifie_le FROM t_presence_ligne WHERE seance_id = $seance AND student_id = " . $dtb->quote($etu))->fetch(PDO::FETCH_ASSOC);
$ms = static fn (int $t) => (string) ($t * 1000);

echo "Ecritures datees de l'instant hors ligne (client_le)\n";
[$s] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'present']);
verifier('pointage en ligne', $s === 200 && $ligne()['statut'] === 'present');
$avant = strtotime($ligne()['modifie_le']);

[$s, $d] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'absent', 'client_le' => $ms($avant - 3600)]);
verifier('fait avant la modification du serveur : refuse en 409', $s === 409 && !empty($d['conflit']), $s . ' ' . json_encode($d));
verifier('...et la valeur du serveur reste', $ligne()['statut'] === 'present');

[$s, $d] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'absent', 'client_le' => $ms(time() + 30)]);
verifier('instant dans le futur : ramene a maintenant, accepte', $s === 200 && $ligne()['statut'] === 'absent', $s . ' ' . json_encode($d));

[$s] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'retard', 'minutes_retard' => 7, 'client_le' => $ms(time() - 1800)]);
$l = $ligne();
verifier('conflit avec une ecriture plus recente que l\'instant hors ligne', $s === 409 && $l['statut'] === 'absent', (string) $s);

[$s] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'retard', 'minutes_retard' => 7, 'client_le' => $ms(time() - 1800), 'forcer' => 1]);
$l = $ligne();
verifier('« forcer » : applique quand meme, a l\'heure actuelle',
    $s === 200 && $l['statut'] === 'retard' && (int) $l['minutes_retard'] === 7 && abs(strtotime($l['modifie_le']) - time()) <= 3, json_encode($l));

[$s] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'present', 'client_le' => $ms(time() - 40 * 86400)]);
verifier('instant de plus de 14 jours : ignore, ecriture normale', $s === 200 && $ligne()['statut'] === 'present');
[$s] = $ecrire(['action' => 'pointer', 'seance_id' => $seance, 'student_id' => $etu, 'statut' => 'absent', 'client_le' => 'pas-un-nombre']);
verifier('instant illisible : ignore, ecriture normale', $s === 200 && $ligne()['statut'] === 'absent');

echo "Heures de la seance et seances prevues\n";
[$s] = $ecrire(['action' => 'seance_modifier', 'seance_id' => $seance, 'heure_prevue' => '09:00', 'arrivee_enseignant' => '09:00', 'message_retard' => '']);
verifier('heures modifiees en ligne', $s === 200);
[$s, $d] = $ecrire(['action' => 'seance_modifier', 'seance_id' => $seance, 'heure_prevue' => '10:00', 'arrivee_enseignant' => '10:00', 'message_retard' => '', 'client_le' => $ms(time() - 3600)]);
$heure = $dtb->query("SELECT heure_prevue FROM t_presence_seance WHERE id = $seance")->fetchColumn();
verifier('heures faites avant : refusees en 409, rien d\'ecrase', $s === 409 && substr((string) $heure, 0, 5) === '09:00', $s . ' ' . $heure);
[$s] = $ecrire(['action' => 'reglage', 'seances_prevues' => 30]);
verifier('seances prevues en ligne', $s === 200);
[$s] = $ecrire(['action' => 'reglage', 'seances_prevues' => 12, 'client_le' => $ms(time() - 3600)]);
$prevues = (int) $dtb->query('SELECT seances_prevues FROM t_presence_feuille WHERE id = ' . (int) $feuille['id'])->fetchColumn();
verifier('seances prevues faites avant : refusees en 409', $s === 409 && $prevues === 30, $s . ' ' . $prevues);

echo "Rejouer sans effet de plus\n";
[$s] = $ecrire(['action' => 'tous_presents', 'seance_id' => $seance]);
[$s2] = $ecrire(['action' => 'tous_presents', 'seance_id' => $seance]);
verifier('« non pointes presents » deux fois : meme resultat', $s === 200 && $s2 === 200);
[$s] = $ecrire(['action' => 'seance_supprimer', 'seance_id' => $seance]);
[$s2] = $ecrire(['action' => 'seance_supprimer', 'seance_id' => $seance]);
verifier('supprimer deux fois : la seconde est un 404 (le client la compte pour faite)', $s === 200 && $s2 === 404, "$s / $s2");

[$s, $d] = requete('POST', '/app/.presence/enregistrer.php', $sid, ['csrf_token' => 'perime', 'action' => 'reglage', 'feuille_id' => $feuille['id'], 'seances_prevues' => 5]);
verifier('jeton perime : csrf_expire (le client en demande un frais et recommence)', $s === 400 && !empty($d['csrf_expire']), $s . ' ' . json_encode($d));

echo "Remise des notes (app/.remise/enregistrer.php)\n";
/* La feuille se cree a la premiere ouverture de la page. */
requete('GET', '/teacher/remise-notes?cours_id=' . $coursId . '&annee=' . rawurlencode($annee) . '&semestre=' . $semestre, $sid);
$remise = $dtb->query('SELECT * FROM t_remise_notes WHERE cours_id = ' . $coursId . ' AND annee_scolaire = ' . $dtb->quote($annee) . ' AND semestre = ' . $semestre)->fetch(PDO::FETCH_ASSOC);
verifier('feuille de notes ouverte', (bool) $remise);
if ($remise) {
    $rid = (int) $remise['id'];
    $statutInitial = $remise['statut'];
    $poidsInitiaux = array_intersect_key($remise, array_flip(['poids_quiz', 'poids_recherche', 'poids_autres', 'poids_mi_semestre', 'poids_final']));
    $dtb->exec("UPDATE t_remise_notes SET statut = 'brouillon' WHERE id = $rid");
    $ligneNote = $dtb->query("SELECT id, student_id, quiz, modifie_le FROM t_remise_notes_ligne WHERE remise_id = $rid ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $lid = (int) $ligneNote['id']; $sidEtu = $ligneNote['student_id'];
    $dtb->exec("UPDATE t_remise_notes_ligne SET quiz = NULL, modifie_le = NULL WHERE id = $lid");
    $cell = static fn () => $dtb->query("SELECT quiz, modifie_le FROM t_remise_notes_ligne WHERE id = $lid")->fetch(PDO::FETCH_ASSOC);
    $noter = static function (array $champs) use ($sid, $csrf, $rid) {
        return requete('POST', '/app/.remise/enregistrer.php', $sid, ['csrf_token' => $csrf, 'remise_id' => $rid] + $champs);
    };
    $saisie = static fn (string $valeur, array $plus = []) => $noter(['student_id' => $sidEtu, 'champ' => 'quiz', 'valeur' => $valeur] + $plus);

    [$s] = $saisie('12');
    verifier('saisie en ligne', $s === 200 && (float) $cell()['quiz'] === 12.0);
    [$s, $d] = $saisie('15', ['client_le' => $ms(time() - 3600)]);
    verifier('cellule modifiee depuis : 409, rien d\'ecrase', $s === 409 && !empty($d['conflit']) && (float) $cell()['quiz'] === 12.0, $s . ' ' . json_encode($d));
    [$s] = $saisie('12', ['client_le' => $ms(time() - 3600)]);
    verifier('meme valeur que le serveur : acceptee, rien a refuser', $s === 200);
    [$s] = $saisie('14', ['client_le' => $ms(time() + 30)]);
    verifier('instant dans le futur : ramene a maintenant', $s === 200 && (float) $cell()['quiz'] === 14.0);
    [$s] = $saisie('9', ['client_le' => $ms(time() - 3600), 'forcer' => 1]);
    $c = $cell();
    verifier('« forcer » : applique, a l\'heure actuelle', $s === 200 && (float) $c['quiz'] === 9.0 && abs(strtotime($c['modifie_le']) - time()) <= 3, json_encode($c));
    [$s] = $saisie('25', ['client_le' => $ms(time() - 60)]);
    verifier('hors bareme : refus 400 (pas un conflit)', $s === 400);
    [$s] = $saisie('abc', ['client_le' => $ms(time() - 60)]);
    verifier('texte : refus 400', $s === 400);

    echo "Deux saisies hors ligne de la meme cellule, rejouees dans l'ordre\n";
    $dtb->exec('UPDATE t_remise_notes_ligne SET modifie_le = ' . $dtb->quote(date('Y-m-d H:i:s', time() - 3600)) . " WHERE id = $lid");
    [$s1] = $saisie('5', ['client_le' => $ms(time() - 100)]);
    $c1 = $cell();
    verifier('premiere saisie : datee de l\'instant hors ligne', $s1 === 200 && (float) $c1['quiz'] === 5.0 && abs(strtotime($c1['modifie_le']) - (time() - 100)) <= 3, json_encode($c1));
    [$s2] = $saisie('6', ['client_le' => $ms(time() - 50)]);
    verifier('la suivante (plus tardive) passe', $s2 === 200 && (float) $cell()['quiz'] === 6.0);
    [$s3] = $saisie('7', ['client_le' => $ms(time() - 80)]);
    verifier('une saisie plus ancienne que la derniere : 409', $s3 === 409 && (float) $cell()['quiz'] === 6.0, (string) $s3);
    $jr = $dtb->query("SELECT date_action FROM t_remise_notes_journal WHERE remise_id = $rid AND action = 'saisie' AND nouvelle_valeur = '6' ORDER BY id DESC LIMIT 1")->fetchColumn();
    verifier('le journal garde l\'instant reel de la saisie', $jr && abs(strtotime($jr) - (time() - 50)) <= 3, (string) $jr);

    echo "Ponderation\n";
    /* Un point de depart different de ce qu'on va ecrire, sinon rien n'est modifie ni journalise (donc pas de conflit possible). */
    $dtb->exec("UPDATE t_remise_notes SET poids_quiz = 10, poids_recherche = 10, poids_autres = 10, poids_mi_semestre = 10, poids_final = 10 WHERE id = $rid");
    $poids = ['cible' => 'ponderation', 'poids_quiz' => 20, 'poids_recherche' => 20, 'poids_autres' => 20, 'poids_mi_semestre' => 20, 'poids_final' => 20];
    [$s] = $noter($poids);
    verifier('ponderation en ligne', $s === 200);
    $poids30 = ['poids_quiz' => 30, 'poids_recherche' => 10] + $poids;   // 30 + 10 + 20 + 20 + 20 = 100
    [$s, $d] = $noter(['client_le' => $ms(time() - 3600)] + $poids30);
    verifier('ponderation faite avant : 409', $s === 409 && !empty($d['conflit']), (string) $s);
    [$s] = $noter(['client_le' => $ms(time() - 3600), 'forcer' => 1] + $poids30);
    verifier('ponderation forcee', $s === 200);

    echo "La somme des ponderations ne depasse jamais 100 %\n";
    $avantPlafond = poidsSomme($dtb, $rid);
    [$s, $d] = $noter(['cible' => 'ponderation', 'poids_quiz' => 40] + $poids30);
    verifier('120 % : refuse en 422, sans rien ecrire', $s === 422 && !empty($d['plafond']) && poidsSomme($dtb, $rid) === $avantPlafond && (float) $dtb->query("SELECT poids_quiz FROM t_remise_notes WHERE id = $rid")->fetchColumn() === 30.0, $s . ' ' . json_encode($d));
    [$s, $d] = $noter(['cible' => 'ponderation', 'poids_quiz' => 30.01] + $poids30);
    verifier('100,01 % : refuse aussi', $s === 422);
    [$s] = $noter(['cible' => 'ponderation', 'poids_quiz' => 100, 'poids_recherche' => 100, 'poids_autres' => 100, 'poids_mi_semestre' => 100, 'poids_final' => 100]);
    verifier('500 % : refuse', $s === 422);
    [$s] = $noter(['cible' => 'ponderation', 'poids_quiz' => 40, 'poids_recherche' => 10, 'poids_autres' => 10, 'poids_mi_semestre' => 20, 'poids_final' => 20]);
    verifier('un partage qui fait 100 % : accepte', $s === 200 && abs(poidsSomme($dtb, $rid) - 100) < 0.001);
    [$s] = $noter(['cible' => 'ponderation', 'poids_quiz' => 20, 'client_le' => $ms(time() + 30)]);
    verifier('baisser une ponderation : accepte (80 %)', $s === 200 && abs(poidsSomme($dtb, $rid) - 80) < 0.001);
    [$s] = $noter(['cible' => 'ponderation', 'poids_autres' => 30, 'client_le' => $ms(time() + 30)]);
    verifier('hors ligne : monter de 10 points (90 %) accepte', $s === 200);
    [$s] = $noter(['cible' => 'ponderation', 'poids_autres' => 50, 'forcer' => 1]);
    verifier('« forcer » ne contourne pas le plafond (110 %)', $s === 422);
    // une feuille deja au-dela (anciennes donnees) peut etre corrigee, pas aggravee
    $dtb->exec("UPDATE t_remise_notes SET poids_quiz = 60, poids_recherche = 30, poids_autres = 30, poids_mi_semestre = 0, poids_final = 0 WHERE id = $rid");
    [$s] = $noter(['cible' => 'ponderation', 'poids_quiz' => 70]);
    verifier('feuille deja a 120 % : l\'aggraver est refuse', $s === 422);
    [$s] = $noter(['cible' => 'ponderation', 'poids_quiz' => 50]);
    verifier('...la corriger vers le bas est accepte', $s === 200 && abs(poidsSomme($dtb, $rid) - 110) < 0.001);
    $dtb->exec("UPDATE t_remise_notes SET poids_quiz = 30, poids_recherche = 10, poids_autres = 20, poids_mi_semestre = 20, poids_final = 20 WHERE id = $rid");

    echo "Colonnes ajoutees (autant qu'on veut)\n";
    $dtb->exec("UPDATE t_remise_notes SET statut = 'brouillon' WHERE id = $rid");
    $dtb->exec("UPDATE t_remise_notes SET poids_quiz = 40, poids_recherche = 0, poids_autres = 0, poids_mi_semestre = 0, poids_final = 0 WHERE id = $rid");
    $dtb->exec("UPDATE t_remise_notes_ligne SET quiz = NULL, recherche = NULL, autres = NULL, mi_semestre = NULL, examen_final = NULL, statut_special = NULL, modifie_le = NULL WHERE remise_id = $rid");
    [$s, $d] = $noter(['cible' => 'colonne_creer', 'cle' => 'tp1abc', 'libelle' => '  TP   1  ']);
    verifier('colonne creee, nom compacte', $s === 200 && ($d['libelle'] ?? '') === 'TP 1', $s . ' ' . json_encode($d));
    [$s, $d] = $noter(['cible' => 'colonne_creer', 'cle' => 'tp1abc', 'libelle' => 'autre nom']);
    verifier('la creer deux fois : meme colonne, sans doublon', $s === 200 && !empty($d['existait']) && $dtb->query("SELECT COUNT(*) FROM t_remise_notes_colonne WHERE remise_id = $rid AND cle = 'tp1abc'")->fetchColumn() == 1, json_encode($d));
    [$s] = $noter(['cible' => 'colonne_creer', 'cle' => 'Bad Key!', 'libelle' => 'x']);
    verifier('cle invalide : refus 400', $s === 400);
    $noter(['cible' => 'colonne_creer', 'cle' => 'tp2abc', 'libelle' => 'TP 2']);
    foreach (range(3, 8) as $i) { $noter(['cible' => 'colonne_creer', 'cle' => 'tp' . $i . 'abc', 'libelle' => 'TP ' . $i]); }
    verifier('huit colonnes ajoutees (au-dela des cinq de base)', (int) $dtb->query("SELECT COUNT(*) FROM t_remise_notes_colonne WHERE remise_id = $rid")->fetchColumn() === 8);
    [$s, $d] = $noter(['cible' => 'colonne_renommer', 'cle' => 'tp1abc', 'libelle' => 'Travail pratique 1']);
    verifier('colonne renommee', $s === 200 && $dtb->query("SELECT libelle FROM t_remise_notes_colonne WHERE remise_id = $rid AND cle = 'tp1abc'")->fetchColumn() === 'Travail pratique 1');
    [$s] = $noter(['cible' => 'colonne_renommer', 'cle' => 'inconnue1', 'libelle' => 'x']);
    verifier('renommer une colonne absente : 404', $s === 404);

    /* quiz 40 % + TP 1 60 % */
    [$s, $d] = $noter(['cible' => 'ponderation', 'poids_quiz' => 40, 'px_tp1abc' => 60]);
    verifier('ponderation d\'une colonne ajoutee : total 100', $s === 200 && abs(($d['total_poids'] ?? 0) - 100) < 0.01, json_encode($d['total_poids'] ?? null));
    [$s, $d] = $saisie('10');
    verifier('note de base saisie : la ligne est « incomplete » (TP 1 manque)', $s === 200 && ($d['note']['etat'] ?? '') === 'incomplete' && strpos($d['note']['detail'] ?? '', 'Travail pratique 1') !== false, json_encode($d['note'] ?? null));
    [$s, $d] = $noter(['student_id' => $sidEtu, 'champ' => 'x:tp1abc', 'valeur' => '15']);
    verifier('note d\'une colonne ajoutee : moyenne ponderee (10 x 40 + 15 x 60) / 100 = 13', $s === 200 && ($d['note']['etat'] ?? '') === 'calculee' && abs(($d['note']['valeur'] ?? 0) - 13) < 0.001, json_encode($d['note'] ?? null));
    [$s] = $noter(['student_id' => $sidEtu, 'champ' => 'x:tp1abc', 'valeur' => '25']);
    verifier('hors bareme : refus 400', $s === 400);
    [$s] = $noter(['student_id' => $sidEtu, 'champ' => 'x:inconnue1', 'valeur' => '5']);
    verifier('colonne absente : 404', $s === 404);
    $jr = $dtb->query("SELECT champ, nouvelle_valeur FROM t_remise_notes_journal WHERE remise_id = $rid AND action = 'saisie' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verifier('le journal nomme la colonne', ($jr['champ'] ?? '') === 'Col. Travail pratique 1', json_encode($jr));
    $remiseCalc = remiseCharger($dtb, $rid);
    $ligneCalc  = remiseLigne($dtb, $rid, $sidEtu);
    verifier('remiseCalculerNote et remiseControler voient la colonne ajoutee', abs(remiseCalculerNote($ligneCalc, $remiseCalc)['valeur'] - 13) < 0.001 && abs(remiseTotalPoids($remiseCalc) - 100) < 0.01);

    /* hors ligne : la colonne est creee et remplie avant que le serveur la connaisse */
    [$s] = $noter(['student_id' => $sidEtu, 'champ' => 'x:tp1abc', 'valeur' => '18', 'client_le' => $ms(time() - 3600)]);
    verifier('hors ligne : cellule modifiee depuis, 409', $s === 409);
    [$s] = $noter(['student_id' => $sidEtu, 'champ' => 'x:tp2abc', 'valeur' => '12', 'client_le' => $ms(time() - 3600)]);
    verifier('hors ligne : cellule jamais saisie, acceptee et datee de l\'instant hors ligne', $s === 200 && abs(strtotime($dtb->query("SELECT modifie_le FROM t_remise_notes_valeur v JOIN t_remise_notes_colonne c ON c.id = v.colonne_id WHERE c.remise_id = $rid AND c.cle = 'tp2abc' LIMIT 1")->fetchColumn()) - (time() - 3600)) <= 3);

    [$s, $d] = $noter(['cible' => 'vider_colonne', 'champ' => 'x:tp1abc']);
    verifier('colonne ajoutee retiree avec ses notes', $s === 200 && (int) $dtb->query("SELECT COUNT(*) FROM t_remise_notes_colonne WHERE remise_id = $rid AND cle = 'tp1abc'")->fetchColumn() === 0
        && (int) $dtb->query("SELECT COUNT(*) FROM t_remise_notes_valeur v LEFT JOIN t_remise_notes_colonne c ON c.id = v.colonne_id WHERE c.id IS NULL AND v.ligne_id = " . (int) $ligneNote['id'])->fetchColumn() === 0);
    [$s, $d] = $noter(['cible' => 'vider_colonne', 'champ' => 'x:tp1abc']);
    verifier('la retirer deux fois : sans effet', $s === 200 && !empty($d['deja']));
    $dtb->exec("DELETE FROM t_remise_notes_valeur WHERE colonne_id IN (SELECT id FROM t_remise_notes_colonne WHERE remise_id = $rid)");
    $dtb->exec("DELETE FROM t_remise_notes_colonne WHERE remise_id = $rid");

    echo "Feuille devenue lecture seule\n";
    $dtb->exec("UPDATE t_remise_notes SET statut = 'soumise' WHERE id = $rid");
    [$s, $d] = $saisie('3', ['client_le' => $ms(time() - 60)]);
    verifier('transmise entre-temps : 409 « fige », pas un conflit (rien a forcer)', $s === 409 && !empty($d['fige']) && empty($d['conflit']), $s . ' ' . json_encode($d));

    /* remise en etat de la feuille d'essai */
    $sets = [];
    foreach ($poidsInitiaux as $k => $v) { $sets[] = "`$k` = " . $dtb->quote((string) $v); }
    $dtb->exec('UPDATE t_remise_notes SET statut = ' . $dtb->quote($statutInitial) . ', ' . implode(', ', $sets) . " WHERE id = $rid");
    $dtb->exec("UPDATE t_remise_notes_ligne SET quiz = " . ($ligneNote['quiz'] === null ? 'NULL' : $dtb->quote((string) $ligneNote['quiz'])) . ", modifie_le = " . ($ligneNote['modifie_le'] === null ? 'NULL' : $dtb->quote($ligneNote['modifie_le'])) . " WHERE id = $lid");
    $dtb->exec("DELETE FROM t_remise_notes_journal WHERE remise_id = $rid AND date_action >= " . $dtb->quote(date('Y-m-d H:i:s', time() - 3600)));
}

nettoyer($dtb, $coursId, $annee, $semestre);
@unlink('C:/laragon/tmp/sess_' . $sid);

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko ? 1 : 0);
