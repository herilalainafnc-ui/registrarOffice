<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
// Calculer le chemin de base si non défini
if (!isset($app_base)) {
    $_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
    $app_base = substr($_app_root, strlen($_doc_root));
    if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
}
// MVC: détecter la route actuelle
$_nav_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_nav_route = $_nav_uri;
if ($app_base && strpos($_nav_route, $app_base) === 0) {
    $_nav_route = substr($_nav_route, strlen($app_base));
}
$_nav_route = '/' . ltrim($_nav_route ?: '', '/');
?>
<nav class="navbar">
        <div class="nav-left">
            <div class="logo-container">
                <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ Logo" class="nav-logo">
            </div>
            <div class="brand-name">Université Adventiste Zurcher</div>
        </div>
        <div class="nav-center">
            <a href="<?=$app_base?>/" class="nav-link <?php echo ($_nav_route == '/') ? 'active' : ''; ?>">Accueil</a>
            <a href="<?=$app_base?>/formations" class="nav-link <?php echo ($_nav_route == '/formations') ? 'active' : ''; ?>">Formations</a>
            <a href="<?=$app_base?>/admissions" class="nav-link <?php echo ($_nav_route == '/admissions') ? 'active' : ''; ?>">Admissions</a>
            <a href="<?=$app_base?>/campus" class="nav-link <?php echo ($_nav_route == '/campus') ? 'active' : ''; ?>">Campus</a>
            <a href="<?=$app_base?>/contact" class="nav-link <?php echo ($_nav_route == '/contact') ? 'active' : ''; ?>">Contact</a>
        </div>
        <div class="nav-right">
            <a href="<?=$app_base?>/login" class="login-btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                </svg>
                <span>Connexion</span>
            </a>
        </div>
    </nav>