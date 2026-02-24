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
 *   /src/accueil        →  /dashboard
 *   /src/accueil.prof   →  /professors
 *   /src/accueil.cours  →  /courses
 *   etc.
 */

// ========================================================================
// PAGES PUBLIQUES (Landing - pas d'authentification requise)
// ========================================================================
$router->get('/', 'HomeController@index');
$router->get('/formations', 'HomeController@formations');
$router->get('/admissions', 'HomeController@admissions');
$router->get('/campus', 'HomeController@campus');
$router->get('/contact', 'HomeController@contact');

// ========================================================================
// AUTHENTIFICATION
// ========================================================================
$router->any('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');
$router->get('/loading', 'AuthController@loading');
$router->get('/goodbye', 'AuthController@goodbye');

// ========================================================================
// TABLEAUX DE BORD PRINCIPAUX
// ========================================================================
$router->any('/dashboard', 'DashboardController@index');
$router->get('/classrooms', 'DashboardController@classrooms');
$router->any('/professors', 'DashboardController@professors');
$router->any('/courses', 'DashboardController@courses');
$router->get('/schedule', 'DashboardController@schedule');

// ========================================================================
// ÉTUDIANTS
// ========================================================================
$router->get('/student', 'StudentController@show');
$router->get('/student/home', 'StudentController@home');
$router->get('/student/dashboard', 'StudentController@dashboard');
$router->get('/student/info', 'StudentController@info');
$router->get('/student/news', 'StudentController@news');
$router->get('/student/quiz', 'StudentController@quiz');
$router->get('/student/game', 'StudentController@game');
$router->get('/student/transition', 'StudentController@transition');
$router->any('/students/create', 'StudentController@create');
$router->get('/students/debug', 'StudentController@debug');

// ========================================================================
// ENSEIGNANTS
// ========================================================================
$router->get('/teacher/dashboard', 'TeacherController@dashboard');

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
$router->any('/accounts/create', 'AdminController@createAccount');
$router->any('/news', 'AdminController@news');
$router->get('/login-locations', 'AdminController@loginLocations');
$router->get('/deans-list', 'AdminController@deansList');
$router->get('/top-students', 'AdminController@topStudents');
$router->get('/my-account', 'AdminController@myAccount');

// ========================================================================
// FINANCE
// ========================================================================
$router->any('/finance', 'FinanceController@index');
$router->any('/finance/save', 'FinanceController@save');
$router->any('/finance/insert', 'FinanceController@insert');

// ========================================================================
// INSCRIPTION
// ========================================================================
$router->any('/inscription', 'InscriptionController@index');

// ========================================================================
// EXPORT (PDF, XLSX, impressions)
// ========================================================================
$router->any('/export', 'ExportController@index');
$router->any('/export/pdf', 'ExportController@pdf');
$router->any('/export/pdf-landscape', 'ExportController@pdfLandscape');
$router->any('/export/xlsx', 'ExportController@xlsx');
$router->any('/export/gen-pdf', 'ExportController@genPdf');
$router->get('/sheet', 'ExportController@sheet');

// ========================================================================
// FILE D'ATTENTE INSCRIPTIONS
// ========================================================================
$router->get('/queue', 'QueueController@index');
$router->get('/queue/display', 'QueueController@display');
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

// ========================================================================
// REDIRECTIONS DE COMPATIBILITÉ (anciens chemins → nouveaux)
// ========================================================================
$router->any('/index', function() { redirectTo('/'); });
$router->any('/landing', function() { redirectTo('/'); });
$router->any('/accueil', function() { redirectTo('/dashboard'); });
$router->any('/student.home', function() { redirectTo('/student/home'); });
$router->any('/student.dashboard', function() { redirectTo('/student/dashboard'); });
$router->any('/teacher.dashboard', function() { redirectTo('/teacher/dashboard'); });
$router->any('/creat.student', function() { redirectTo('/students/create'); });
$router->any('/creat.account', function() { redirectTo('/accounts/create'); });
$router->any('/src/cours', function() { redirectTo('/cours?' . $_SERVER['QUERY_STRING']); });

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
