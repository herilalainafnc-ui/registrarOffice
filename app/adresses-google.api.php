<?php
/* =============================================================================
   Adresses Google — reponse JSON de src/adresses-google.php

   GET  ?action=rapport[&forcer=1] : les adresses @zurcher.edu.mg de la base
        (fiches etudiants, enseignants, comptes de l'application), une ligne par
        adresse, comparees aux comptes Google du domaine (data/google.admin.php).
   POST action=creer, adresse=...  : cree le compte Google d'une adresse
        d'etudiant absente de Google, avec le mot de passe de sa fiche — ce que
        faisait l'import CSV. Le serveur refait toutes les verifications : le
        navigateur n'envoie que l'adresse.

   SUPERADMIN UNIQUEMENT, comme la page (AdminController::adressesGoogle) :
   ce fichier est joignable hors du routeur. Aucun mot de passe ne quitte le
   serveur.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion(1); refuserEtudiant(); refuserEnseignant();
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/google.admin.php');
require_once(__DIR__ . '/../data/adresse.etudiant.php');
require_once(__DIR__ . '/../data/etudiants.annee.php');
require_once(__DIR__ . '/../data/inscription.session.php'); // inscAnneeCourante()

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
@set_time_limit(120);

function agRepondre(array $charge, int $code = 200): void
{
	http_response_code($code);
	echo json_encode($charge, JSON_UNESCAPED_UNICODE);
	exit;
}

/** « NOM Prenom » d'une fiche, pour comparer deux fiches de la meme adresse. */
function agPersonne(array $e): string
{
	return adresseEtudiantNormaliser((string) $e['student_nom']) . '|' . adresseEtudiantNormaliser((string) $e['student_prenom']);
}

function agNiveau($n): string
{
	$n = (int) $n;
	return $n === 0 ? 'Remise à niveau' : ($n <= 3 ? 'Licence ' . $n : 'Master ' . ($n - 3));
}

$domaine = '@' . GOOGLE_ADMIN_DOMAINE;
$motif = '%' . $domaine;
$action = (string) ($_REQUEST['action'] ?? 'rapport');
/* Google peut prendre quelques secondes : la session est relachee pour ne pas
   bloquer les autres pages de l'agent pendant ce temps. */
if ($action !== 'creer') { session_write_close(); }

/** Fiches etudiantes actives qui portent cette adresse, la plus recente d'abord. */
function agFichesDe(PDO $dtb, string $adresse): array
{
	$st = $dtb->prepare('SELECT id, student_id, student_nom, student_prenom, student_email, password, student_tel, student_adresse,
			annee_etude, etude_envisage, etude_option
		FROM tbl_2024_etudiant WHERE `remove` = 0 AND LOWER(TRIM(student_email)) = ? ORDER BY id DESC');
	$st->execute([$adresse]);
	return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------- creation */
if ($action === 'creer') {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { agRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405); }
	refuserRequeteCroisee();
	if (!verify_csrf($_POST['csrf_token'] ?? null)) { agRepondre(['ok' => false, 'message' => 'Session expirée : rechargez la page.'], 419); }
	session_write_close();
	if (!googleAdminConfiguree()) { agRepondre(['ok' => false, 'message' => "L'API Google Admin n'est pas configurée sur ce serveur."], 503); }

	$adresse = strtolower(trim((string) ($_POST['adresse'] ?? '')));
	$fiches = agFichesDe($dtb, $adresse);
	if (!$fiches) { agRepondre(['ok' => false, 'message' => "Aucune fiche étudiante active ne porte l'adresse {$adresse}."], 404); }
	/* Une adresse portee par deux personnes differentes : on ne choisit pas a
	   la place de l'agent a qui donner le compte. La meme personne (Licence
	   puis Master, deux matricules) : la fiche la plus recente. */
	if (count(array_unique(array_map('agPersonne', $fiches))) > 1) {
		agRepondre(['ok' => false, 'message' => "{$adresse} est portée par plusieurs étudiants différents : corrigez les fiches avant de créer le compte."], 409);
	}
	if (adresseEtudiantPourGoogle($adresse) !== $adresse) {
		agRepondre(['ok' => false, 'message' => "{$adresse} contient un caractère que Google refuse (accent, espace) : corrigez la fiche d'abord."], 422);
	}
	$f = $fiches[0];
	$r = googleAdminCreerEtudiant([
		'adresse' => $adresse, 'nom' => $f['student_nom'], 'prenom' => $f['student_prenom'], 'mot_de_passe' => (string) $f['password'],
		'matricule' => (string) $f['student_id'], 'mention' => (string) $f['etude_envisage'], 'parcours' => (string) $f['etude_option'],
		'telephone' => (string) $f['student_tel'], 'domicile' => (string) $f['student_adresse'],
	]);
	agRepondre(['ok' => $r['cree'], 'existait' => $r['existait'], 'message' => $r['raison']], $r['cree'] ? 200 : ($r['existait'] ? 409 : 502));
}

if ($action !== 'rapport') { agRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400); }

/* ------------------------------------------------------------- rapport */
if (!googleAdminConfiguree()) {
	agRepondre(['ok' => true, 'configuree' => false, 'manque' => googleAdminCeQuiManque(), 'lignes' => []]);
}

/* L'annee en cours, celle du tableau de bord (du 1er juillet au 30 juin) : la
   session selectionnee retarde a la rentree (2025-2026 le 08/10/2026), et les
   nouveaux etudiants, admis pour l'annee qui commence, en sortaient. */
$annee = inscAnneeCourante();
$deLAnnee = [];
if (preg_match('/^\d{4} - \d{4}$/', $annee)) {
	foreach ($dtb->query('SELECT id FROM tbl_2024_etudiant WHERE ' . etudiantsDeLAnnee($dtb, $annee, true))->fetchAll(PDO::FETCH_COLUMN) as $id) {
		$deLAnnee[(int) $id] = true;
	}
}

$lignes = [];
$ajouter = static function (string $adresse, string $source, array $personne) use (&$lignes): void {
	if (!isset($lignes[$adresse])) { $lignes[$adresse] = ['adresse' => $adresse, 'sources' => [], 'personnes' => [], 'annee' => false, 'noms' => []]; }
	$lignes[$adresse]['sources'][$source] = true;
	$lignes[$adresse]['personnes'][] = $personne;
};

$st = $dtb->prepare("SELECT id, student_id, student_nom, student_prenom, LOWER(TRIM(student_email)) adresse, annee_etude, etude_envisage
	FROM tbl_2024_etudiant WHERE `remove` = 0 AND LOWER(TRIM(student_email)) LIKE ? ORDER BY id DESC");
$st->execute([$motif]);
foreach ($st as $e) {
	$ajouter($e['adresse'], 'etudiant', [
		'nom' => trim($e['student_nom'] . ' ' . $e['student_prenom']),
		'detail' => trim($e['student_id'] . ' · ' . agNiveau($e['annee_etude']) . ($e['etude_envisage'] ? ' · ' . $e['etude_envisage'] : '')),
		'fiche' => (int) $e['id'],
	]);
	if (isset($deLAnnee[(int) $e['id']])) { $lignes[$e['adresse']]['annee'] = true; }
	$lignes[$e['adresse']]['noms'][agPersonne($e)] = true;
}
$st = $dtb->prepare("SELECT teacher_id, name, lastName, LOWER(TRIM(email)) adresse FROM teacher WHERE COALESCE(`remove`, 0) = 0 AND LOWER(TRIM(email)) LIKE ?");
$st->execute([$motif]);
foreach ($st as $t) {
	$ajouter($t['adresse'], 'enseignant', ['nom' => trim($t['lastName'] . ' ' . $t['name']), 'detail' => 'Enseignant']);
}
$st = $dtb->prepare("SELECT id, nom, prenom, pseudo, post, LOWER(TRIM(mail)) adresse FROM compt_utilisateur WHERE LOWER(TRIM(mail)) LIKE ?");
$st->execute([$motif]);
foreach ($st as $c) {
	$nom = trim($c['nom'] . ' ' . $c['prenom']);
	$ajouter($c['adresse'], 'compte', ['nom' => $nom !== '' ? $nom : (string) $c['pseudo'], 'detail' => 'Compte de l’application' . ($c['post'] ? ' · ' . $c['post'] : '')]);
}

try {
	$google = googleAdminTousLesComptes(!empty($_GET['forcer']));
} catch (Throwable $x) {
	agRepondre(['ok' => false, 'configuree' => true, 'message' => $x->getMessage()], 502);
}
$comptes = $google['comptes'];

$compteurs = ['toutes' => 0, 'active' => 0, 'jamais' => 0, 'suspendue' => 0, 'absente' => 0, 'a_corriger' => 0, 'google_seul' => 0];
$sortie = [];
foreach ($lignes as $adresse => $l) {
	$g = $comptes[$adresse] ?? null;
	$pourGoogle = adresseEtudiantPourGoogle($adresse) !== $adresse;
	$partagee = count($l['noms']) > 1;
	if ($g) {
		$statut = $g['archive'] ? 'archivee' : ($g['suspendu'] ? 'suspendue' : ($g['connexion'] !== '' ? 'active' : 'jamais'));
	} else {
		$statut = $pourGoogle ? 'a_corriger' : 'absente';
	}
	$ligne = [
		'adresse' => $adresse,
		'sources' => array_keys($l['sources']),
		'personnes' => array_slice($l['personnes'], 0, 4),
		'nb_personnes' => count($l['personnes']),
		'annee' => $l['annee'],
		'statut' => $statut,
		'connexion' => $g['connexion'] ?? '',
		'cree_le' => $g['cree_le'] ?? '',
		'alias_de' => $g['alias_de'] ?? '',
		'partagee' => $partagee,
		'creable' => $statut === 'absente' && isset($l['sources']['etudiant']) && !$partagee,
	];
	$sortie[] = $ligne;
	$compteurs['toutes']++;
	$compteurs[$statut === 'archivee' ? 'suspendue' : $statut]++;
}
/* Les comptes Google qu'aucune fiche ne porte : personnel, anciens, tests. */
foreach ($comptes as $adresse => $g) {
	if (isset($g['alias_de']) || isset($lignes[$adresse])) { continue; }
	$sortie[] = [
		'adresse' => $adresse, 'sources' => ['google'], 'personnes' => [['nom' => $g['nom'], 'detail' => 'Compte Google sans fiche' . ($g['unite'] && $g['unite'] !== '/' ? ' · ' . $g['unite'] : '')]],
		'nb_personnes' => 1, 'annee' => false,
		'statut' => 'google_seul', 'etat_google' => $g['archive'] ? 'archivee' : ($g['suspendu'] ? 'suspendue' : ($g['connexion'] !== '' ? 'active' : 'jamais')),
		'connexion' => $g['connexion'], 'cree_le' => $g['cree_le'], 'alias_de' => '', 'partagee' => false, 'creable' => false,
	];
	$compteurs['google_seul']++;
}

agRepondre([
	'ok' => true, 'configuree' => true, 'domaine' => GOOGLE_ADMIN_DOMAINE, 'annee' => $annee,
	'lu_le' => date('c', (int) $google['lu_le']), 'compteurs' => $compteurs, 'lignes' => $sortie,
]);
