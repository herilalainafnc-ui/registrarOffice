<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(6); refuserEtudiant(); refuserEnseignant(); // niveaux 1 a 6, comme le guichet d'inscription (voir checkCours.php)
/**
 * AJAX : la liste du volet « Ajout de cours », rechargee apres un ajout ou
 * quand on change d'annee (src/student/new.cours.php).
 *
 * Le gabarit est celui du premier affichage, src/student/new.cours.liste.php :
 * cette page en tenait une copie, qui avait diverge (session choisie d'apres
 * le mois, annee d'apres la date).
 */

require('../../data/backdb.php');

// Session : ouverte par data/guard.ajax.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$bg_one_color = $_SESSION['bg_one_color'] ?? 'bg-[#1e3a5f]';
$bg_two_color = $_SESSION['bg_two_color'] ?? 'bg-[#2a4a6f]';
$bg_three_color = $_SESSION['bg_three_color'] ?? 'bg-[#3a5a7c]';
$bg_four_color = $_SESSION['bg_four_color'] ?? 'bg-[#0f2744]';
$bg_five_color = $_SESSION['bg_five_color'] ?? 'bg-[#3a5a7c]';
$br_two_color = $_SESSION['br_two_color'] ?? 'border-[#2a4a6f]';

$rg_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

$findProfil = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE id = :id');
$findProfil->execute(['id' => (int) ($_GET['id'] ?? 0)]);
$profil = $findProfil->fetch(PDO::FETCH_ASSOC);

if (!$profil) {
    echo '<div class="text-red-500 p-4">Erreur : étudiant introuvable</div>';
    exit;
}

/* Perimetre du compte (data/perimetre.php) : meme reponse qu'un inconnu. */
require_once __DIR__ . '/../../data/perimetre.php';
if (!perimetreEtudiantVisible($profil)) {
    echo '<div class="text-red-500 p-4">Erreur : étudiant introuvable</div>';
    exit;
}

require_once __DIR__ . '/../../data/cours.offerts.php';
/* choix = 0 : le volet est au guichet d'inscription, sans choix d'annee. */
$avecChoixAnnee = !empty($_GET['choix']);
$academicYear = ($avecChoixAnnee ? coursAnneeValide($_GET['academic_year'] ?? null) : null) ?? coursAnneeScolaire($dtb, null);
$toutProgramme = $avecChoixAnnee && !empty($_GET['programme']);

require __DIR__ . '/../../src/student/new.cours.liste.php';
