<?php
/**
 * routes.php - Définition de toutes les routes de l'application
 * 
 * Architecture MVC - Infinit Registrar
 * 
 * Format: $router->method('/url-propre', 'Controleur@methode');
 * 
 * Correspondance ancien → nouveau:
 *   /landing/           →  /
 *   /src/index          →  /login
 *   /src/accueil        →  /etudiants
 *   /src/accueil.prof   →  /professors
 *   /src/accueil.cours  →  /courses
 *   etc.
 */

// ========================================================================
// PAGES PUBLIQUES (Landing - pas d'authentification requise)
// ========================================================================
$router->get('/', 'HomeController@index');
$router->get('/formations', 'HomeController@formations');
$router->any('/admissions', 'HomeController@admissions'); // any : accepte le POST du formulaire de pre-admission
$router->get('/admissions/verifier-email', 'HomeController@verifierEmail'); // adresse du candidat verifiee pendant la frappe
$router->get('/candidatures', 'AdmissionController@candidats'); // liste admin des candidatures (personnel)
$router->post('/candidatures/statut', 'AdmissionController@changerStatut'); // instruction : en cours / accepte / refuse
$router->post('/candidatures/archiver', 'AdmissionController@archiver'); // ranger / sortir des archives (n'affecte pas le statut)
$router->get('/campus', 'HomeController@campus');
$router->any('/contact', 'HomeController@contact'); // any : accepte le POST du formulaire de contact

// ========================================================================
// AUTHENTIFICATION
// ========================================================================
$router->any('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');
$router->get('/loading', 'AuthController@loading');
$router->any('/choisir-espace', 'AuthController@choisirEspace'); // superadmin, media... aussi enseignant : choisir son espace
$router->get('/goodbye', 'AuthController@goodbye');

// ========================================================================
// APPLICATION INSTALLABLE (PWA) : manifeste et page d'ouverture
// ========================================================================
$router->get('/manifest.webmanifest', 'ApplicationController@manifeste'); // lu sans session par le navigateur
$router->get('/espace', 'ApplicationController@ouvrir'); // ouverture de l'application installee : l'espace de chacun

// ========================================================================
// TABLEAUX DE BORD PRINCIPAUX
// ========================================================================
$router->get('/tableau-de-bord', 'DashboardController@tableauDeBord'); // accueil du personnel : a traiter, chiffres cles
$router->get('/didacticiel', 'DashboardController@didacticiel'); // visites guidees (data/didacticiel.php)
$router->get('/messagerie', 'MessagerieController@index'); // messagerie instantanee (data/messagerie.php), pour tous les comptes
$router->any('/etudiants', 'DashboardController@index'); // liste des etudiants
$router->get('/classrooms', 'DashboardController@classrooms');
$router->any('/professors', 'DashboardController@professors');
$router->any('/courses', 'DashboardController@courses');
$router->get('/schedule', 'DashboardController@schedule');
$router->get('/fusion-pdf', 'DashboardController@fusionPdf'); // outil : fusion de PDF, dans le navigateur
$router->get('/qr-generator', 'DashboardController@qrGenerator'); // outil : QR Codes personnalises, dans le navigateur
$router->get('/retouche-photo', 'DashboardController@retouchePhoto'); // outil : retouche photo et fond, dans le navigateur (Media et superadmin)
$router->get('/studio', 'DashboardController@studio'); // Studio UAZ : visuels a partir de presets, dans la charte (Media et superadmin)

// ========================================================================
// ÉVALUATION / SONDAGES
// ========================================================================
$router->any('/evaluation', 'EvaluationController@index'); // Vue utilisateur
$router->any('/evaluation/admin', 'EvaluationController@admin'); // Vue admin dashboard
$router->any('/evaluation/create', 'EvaluationController@create'); // Vue création formulaire
$router->any('/evaluation/edit/{id}', 'EvaluationController@edit'); // Editer formulaire
$router->any('/evaluation/update/{id}', 'EvaluationController@update'); // Sauver modification formulaire
$router->any('/evaluation/delete/{id}', 'EvaluationController@delete'); // Supprimer formulaire
$router->any('/evaluation/stats/{id}', 'EvaluationController@stats'); // Voir les résultats
$router->post('/evaluation/store', 'EvaluationController@store'); // Traitement création

$router->any('/evaluation/automate', 'EvaluationController@automate');
$router->get('/evaluation/form/{id}', 'EvaluationController@viewForm');
$router->post('/evaluation/submit/{id}', 'EvaluationController@submitForm');
$router->any('/api/evaluation/forms', 'EvaluationController@apiForms'); // CRUD API 
$router->any('/api/evaluation/stats/{id}', 'EvaluationController@apiStats'); // Stats API

// ========================================================================
// ÉTUDIANTS
// ========================================================================
$router->get('/student', 'StudentController@show');
$router->get('/student/home', 'StudentController@home');
$router->get('/student/notes', 'StudentController@dashboard'); // mes notes
$router->get('/student/info', 'StudentController@info');
$router->get('/student/presence', 'StudentController@presence'); // mon assiduite (§472 a §476)
$router->get('/student/inscriptions', 'StudentController@inscriptions'); // mes fiches d'inscription, une par session
$router->get('/student/fiche-inscription', 'StudentController@ficheInscription'); // la fiche d'une session, en PDF
$router->get('/student/news', 'StudentController@news');
$router->get('/student/quiz', 'StudentController@quiz');
$router->get('/student/game', 'StudentController@game');
$router->get('/student/transition', 'StudentController@transition');
$router->any('/students/create', 'StudentController@create');
$router->get('/students/adresse-libre', 'StudentController@adresseLibre'); // adresse universitaire libre pour un nouvel etudiant

// ========================================================================
// ENSEIGNANTS
// ========================================================================
$router->get('/teacher/dashboard', 'TeacherController@dashboard');
$router->get('/teacher/remise-notes', 'TeacherController@remiseNotes'); // saisie des notes par l'enseignant
$router->get('/teacher/presence', 'TeacherController@presence'); // registre de presence (§472 a §476)

// Validation des remises : registrariat et superadmin. C'est le seul chemin
// par lequel une note d'enseignant entre dans l'historique et le bulletin.
$router->get('/remise-notes', 'AdminController@remiseNotes');
// Assiduite : depassements des 15 % et retards des enseignants (§474, §476).
$router->get('/presence', 'AdminController@presence');

// ========================================================================
// PROFESSEURS & COURS (pages individuelles)
// ========================================================================
$router->get('/professor', 'AdminController@professor');
$router->get('/course', 'AdminController@course');
$router->get('/cours', 'AdminController@course');

// ========================================================================
// ADMINISTRATION
// ========================================================================
$router->get('/settings', 'AdminController@settings');
$router->any('/settings/bulk-email', 'AdminController@bulkEmail');
$router->get('/settings/adresses-google', 'AdminController@adressesGoogle'); // adresses de la base face aux comptes Google
$router->any('/settings/diplome', 'AdminController@diplomeSettings');
$router->any('/settings/vitrine', 'AdminController@vitrineSettings'); // contenu de la page publique
$router->get('/settings/mentions', 'AdminController@mentionsSettings'); // filieres de l'universite
$router->get('/settings/espace-etudiant', 'AdminController@espaceEtudiantSettings'); // publication des notes aux etudiants
$router->get('/settings/logs', 'AdminController@logsSettings'); // journal d'activite (superadmin)
$router->get('/settings/qr-generator', 'AdminController@qrGenerator'); // ancienne adresse, redirige vers /qr-generator
$router->any('/newsletter', 'NewsletterController@index');
$router->any('/newsletter/subscribe', 'NewsletterController@subscribe');
$router->any('/newsletter/unsubscribe/{token}', 'NewsletterController@unsubscribe');
$router->any('/accounts/create', 'AdminController@createAccount');
$router->any('/news', 'AdminController@news');
$router->get('/login-locations', 'AdminController@loginLocations');
$router->get('/deans-list', 'AdminController@deansList');
$router->get('/top-students', 'AdminController@topStudents');
$router->get('/session-rankings', 'AdminController@sessionRankings');
$router->any('/sessions', 'AdminController@sessions');
$router->get('/courses-sessions', 'AdminController@manageCoursesSessions');
$router->get('/my-account', 'AdminController@myAccount');
$router->get('/reglement', 'AdminController@reglement');
$router->get('/reglement/telecharger', 'AdminController@reglementDownload');
$router->get('/bulletin-academique', 'AdminController@reglement'); // alias explicite

// ========================================================================
// FINANCE
// ========================================================================
$router->any('/finance', 'FinanceController@index');
$router->get('/finance/account-balance', 'FinanceController@accountBalance');
$router->any('/finance/account-balance/update', 'FinanceController@accountBalanceUpdate');
$router->any('/finance/save', 'FinanceController@save');
$router->any('/finance/insert', 'FinanceController@insert');
$router->any('/finance/authorized-reinscription', 'FinanceController@authorizedReinscription');
$router->any('/finance/echeances', 'FinanceController@echeances'); // dates limites des tranches, par session

// ========================================================================
// WORK EDUCATION (responsable du Work Education, registrariat)
// ========================================================================
$router->any('/work-education', 'WorkEducationController@index'); // tache de chaque etudiant, par session ; POST = enregistrer

// ========================================================================
// INSCRIPTION
// ========================================================================
$router->any('/inscription', 'InscriptionController@index');
$router->any('/inscriptions/tardives', 'InscriptionController@tardives'); // calendrier des inscriptions, retards et penalites (§ 334)

// ========================================================================
// EXPORT (PDF, XLSX, impressions)
// ========================================================================
$router->any('/export', 'ExportController@index');
$router->any('/export/pdf', 'ExportController@pdf');
$router->any('/export/pdf-landscape', 'ExportController@pdfLandscape');
$router->any('/export/xlsx', 'ExportController@xlsx');
$router->any('/export/students/xlsx', 'ExportController@studentsXlsx');

// Legacy export aliases (backward compatibility)
$router->any('/data.topdf.php', 'ExportController@pdf');
$router->any('/data.topdf', 'ExportController@pdf');
$router->any('/src/data.topdf.php', 'ExportController@pdf');
$router->any('/src/data.topdf', 'ExportController@pdf');

$router->any('/data.topdf_paysage.php', 'ExportController@pdfLandscape');
$router->any('/data.topdf_paysage', 'ExportController@pdfLandscape');
$router->any('/src/data.topdf_paysage.php', 'ExportController@pdfLandscape');
$router->any('/src/data.topdf_paysage', 'ExportController@pdfLandscape');
$router->any('/export/gen-pdf', 'ExportController@genPdf');
$router->get('/sheet', 'ExportController@sheet');

// ========================================================================
// FILE D'ATTENTE INSCRIPTIONS
// ========================================================================
$router->get('/queue', 'QueueController@index');
$router->get('/queue/display', 'QueueController@display');
$router->any('/queue/plan', 'QueueController@plan'); // plan du bureau et parcours des etudiants (ecran public)
$router->any('/queue/api', 'QueueController@api');

// ========================================================================
// DIVERS
// ========================================================================
$router->get('/reel', 'AdminController@reel');
$router->get('/button', 'AdminController@button');
$router->get('/verify', 'AdminController@verify');

// ========================================================================
// API & SERVICES (AJAX)
// ========================================================================
$router->any('/api/google-auth', 'ApiController@googleAuth');
$router->any('/api/schedule', 'ApiController@schedule');
$router->any('/api/services/document-verification', 'ApiController@documentVerification');
$router->any('/api/services/matricule', 'ApiController@matriculeLive');
$router->any('/api/services/parcours', 'ApiController@parcoursLive');
$router->any('/api/services/parcours/add-cours', 'ApiController@parcoursAddCours');
$router->any('/api/data', 'ApiController@data');
$router->any('/api/update-authorization', 'ApiController@updateAuthorization');
$router->any('/api/update-dossier', 'ApiController@updateDossier');

// ========================================================================
// REDIRECTIONS DE COMPATIBILITÉ (anciens chemins → nouveaux)
// ========================================================================
$router->any('/index', function() { redirectTo('/'); });
$router->any('/landing', function() { redirectTo('/'); });
$router->any('/accueil', function() { redirectTo('/espace'); }); // l'accueil du role
// Adresses renommees le 06/10/2026 pour dire ce que montre la page : la
// liste des etudiants n'etait pas un tableau de bord, ni « Mes notes ».
// Favoris et anciens liens suivent, avec leurs parametres (?q=, ...).
$router->any('/dashboard', function() { redirectTo('/etudiants' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')); });
$router->any('/student/dashboard', function() { redirectTo('/student/notes' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')); });
$router->any('/student.home', function() { redirectTo('/student/home'); });
$router->any('/student.dashboard', function() { redirectTo('/student/notes'); });
$router->any('/teacher.dashboard', function() { redirectTo('/teacher/dashboard'); });
$router->any('/creat.student', function() { redirectTo('/students/create'); });
$router->any('/creat.account', function() { redirectTo('/accounts/create'); });
$router->any('/src/cours', function() { redirectTo('/cours?' . $_SERVER['QUERY_STRING']); });
$router->any('/prof', function() { redirectTo('/professor?' . $_SERVER['QUERY_STRING']); });

// ========================================================================
// PAGE 404 PERSONNALISÉE
// ========================================================================
$router->notFound(function() {
    http_response_code(404);
    $base = defined('APP_BASE') ? APP_BASE : '';
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>404 - Page non trouvée | Infinit Registrar</title>';
    echo '<link rel="shortcut icon" href="' . $base . '/file/logo-coldbloud.png" type="image/x-icon">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:"Inter",sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#0a1628;color:#fff;flex-direction:column;gap:1rem;padding:2rem}';
    echo 'h1{font-size:8rem;font-weight:700;color:#3b82f6;line-height:1}p{font-size:1.2rem;color:#94a3b8;text-align:center}';
    echo 'a{color:#3b82f6;text-decoration:none;margin-top:1rem;padding:0.75rem 2rem;border:1px solid #3b82f6;border-radius:0.75rem;transition:all 0.3s;font-weight:500}';
    echo 'a:hover{background:#3b82f6;color:#fff}</style></head>';
    echo '<body><h1>404</h1><p>La page que vous cherchez n\'existe pas ou a été déplacée.</p>';
    echo '<a href="' . $base . '/">Retour à l\'accueil</a></body></html>';
});
