<?php
/**
 * « Template mail CSV » (barre d'outils, Exports) : le fichier a importer tel
 * quel dans Google Admin pour creer les comptes @zurcher.edu.mg des etudiants
 * (Utilisateurs > Importer des utilisateurs).
 *
 * Un vrai fichier CSV, et non plus un PDF de lignes a recopier. Ses colonnes
 * sont exactement celles du modele d'import de Google Admin (fourni par
 * l'utilisateur le 08/10/2026) : l'ancien suivait le modele d'EXPORT de
 * Google, avec des colonnes en lecture seule (« Status [READ ONLY] »,
 * « Last Sign In »...) que l'import refuse.
 *
 * Filtres : annee scolaire (les inscrits de l'annee et les admis pas encore
 * inscrits, data/etudiants.annee.php), mention, niveau (Licence, Master ou
 * tout) et nouveaux etudiants seulement.
 *
 * Le fichier porte les mots de passe : registrariat seul (niveaux 1 a 3).
 * Avant, ce script et son gabarit repondaient a n'importe qui, sans connexion.
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(3); refuserEtudiant(); refuserEnseignant();
refuserRequeteCroisee(); // lance depuis l'application, jamais depuis un autre site
require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/etudiants.annee.php');
require_once(__DIR__ . '/../../data/adresse.etudiant.php'); // textes sans accents, adresses que Google accepte

$annee    = trim((string) ($_POST['yearListCSV'] ?? ''));
$mention  = trim((string) ($_POST['types'] ?? 'TOUT'));
$niveau   = (string) ($_POST['niveau'] ?? 'tout');
$nouveaux = !empty($_POST['nouveaux']);

if (!preg_match('/^\d{4} - \d{4}$/', $annee)) {
	http_response_code(400);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Choisissez l'année scolaire.";
	exit;
}

$where = etudiantsDeLAnnee($dtb, $annee, true) . " AND TRIM(COALESCE(student_email, '')) <> ''";
if ($mention !== '' && $mention !== 'TOUT') { $where .= ' AND etude_envisage = ' . $dtb->quote($mention); }
if ($niveau === 'licence') { $where .= " AND annee_etude IN ('0', '1', '2', '3')"; }
elseif ($niveau === 'master') { $where .= " AND annee_etude IN ('4', '5')"; }
else { $niveau = 'tout'; }
if ($nouveaux) { $where .= ' AND new_student = 1'; }

$etudiants = $dtb->query("SELECT student_nom, student_prenom, student_email, password, student_tel, student_adresse,
		student_id, etude_envisage, etude_option
	FROM tbl_2024_etudiant WHERE $where ORDER BY annee_etude, student_nom, student_prenom")->fetchAll(PDO::FETCH_ASSOC);

/** Telephone malgache au format international (+261...), sinon vide. */
$telephone = static function ($brut): string {
	$chiffres = preg_replace('/\D/', '', (string) $brut);
	if (preg_match('/^0(\d{9})$/', $chiffres, $m)) { return '+261' . $m[1]; }
	if (preg_match('/^261(\d{9})$/', $chiffres, $m)) { return '+261' . $m[1]; }
	return '';
};
/* Tout le fichier sans accents : Excel ouvrait l'UTF-8 en « UniversitÃ© ».
   L'ASCII s'ouvre partout a l'identique, et Google l'importe sans reserve. */
$propre = static fn ($v) => trim(preg_replace('/\s+/', ' ', adresseEtudiantSansAccents((string) $v)));

$nomFichier = 'comptes-google_' . str_replace(' ', '', $annee) . '_' . $niveau
	. ($mention !== 'TOUT' ? '_' . preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $mention) ?: 'mention')) : '')
	. ($nouveaux ? '_nouveaux' : '') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
header('Cache-Control: no-store');

$sortie = fopen('php://output', 'w');
/* L'en-tete du modele, ecrit tel quel : fputcsv mettrait chaque titre entre
   guillemets (ils contiennent des espaces). */
fwrite($sortie, 'First Name [Required],Last Name [Required],Email Address [Required],Password [Required],'
	. 'Password Hash Function [UPLOAD ONLY],Org Unit Path [Required],New Primary Email [UPLOAD ONLY],'
	. 'Recovery Email,Home Secondary Email,Work Secondary Email,Recovery Phone [MUST BE IN THE E.164 FORMAT],'
	. 'Work Phone,Home Phone,Mobile Phone,Work Address,Home Address,Employee ID,Employee Type,Employee Title,'
	. 'Manager Email,Department,Cost Center,Building ID,Floor Name,Floor Section,Change Password at Next Sign-In,'
	. 'New Status [UPLOAD ONLY],New Licenses [UPLOAD ONLY],Advanced Protection Program enrollment' . "\n");

/* Une adresse, une ligne : deux fiches de la meme adresse (une personne creee
   deux fois) donneraient deux lignes, et la seconde ecraserait le mot de passe
   de la premiere dans Google. */
$vues = [];
foreach ($etudiants as $e) {
	/* Les anciennes adresses portent parfois un accent (« stéphanie.c@ ») que
	   Google refuse : la ligne prend l'adresse sans accent. La fiche, elle,
	   garde la sienne tant qu'on ne la corrige pas. */
	$adresse = adresseEtudiantPourGoogle((string) $e['student_email']);
	if (isset($vues[$adresse])) { continue; }
	$vues[$adresse] = true;
	$nom = $propre($e['student_nom']);
	$prenom = $propre($e['student_prenom']);
	fputcsv($sortie, [
		$prenom !== '' ? $prenom : $nom,   // First Name : le prenom (Google l'exige non vide)
		$nom,                              // Last Name : le nom de famille
		$adresse,
		(string) $e['password'],
		'', '/', '', '', '', '', '',       // hachage, unite d'organisation, adresses et telephone de secours
		'', '', $telephone($e['student_tel']),
		'Universite Adventiste Zurcher', $propre($e['student_adresse']),
		(string) $e['student_id'], 'Student', 'Student', '',
		$propre($e['etude_envisage']), $propre($e['etude_option']),
		'', '', '',
		'FALSE', '', '', 'FALSE',
	], ',', '"', '');
}
fclose($sortie);
