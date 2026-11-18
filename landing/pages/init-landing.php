<?php
/**
 * init-landing.php - Initialisation commune pour les pages landing
 * Calcule $app_base pour les chemins absolus MVC
 */
if (!isset($app_base)) {
    $_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
    $app_base = substr($_app_root, strlen($_doc_root));
    if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
}
