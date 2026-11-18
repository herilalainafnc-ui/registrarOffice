<?php
$dtb = $dtb ?? ($GLOBALS['dtb'] ?? null);
if (!($dtb instanceof PDO) && function_exists('getDbConnection')) {
    $dtb = getDbConnection();
    if (class_exists('DB')) {
        DB::init($dtb);
    }
}
if (!($dtb instanceof PDO)) {
    throw new RuntimeException('Connexion base de donnees indisponible pour la newsletter.');
}

$result = $_SESSION['newsletter_unsubscribe_result'] ?? ['success' => false, 'message' => 'Lien de desabonnement invalide.'];
unset($_SESSION['newsletter_unsubscribe_result']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Desabonnement</title>
    <style>
        body { background: linear-gradient(135deg, rgb(var(--c-canvas)) 0%, rgb(var(--c-canvas)) 100%); color: rgb(var(--c-text)); }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: min(560px, 100%); background: rgb(var(--c-canvas) / 0.92); border: 1px solid rgb(var(--c-line-strong) / 0.2); border-radius: 24px; padding: 28px; box-shadow: 0 24px 60px rgba(0,0,0,0.28); }
        .badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <span class="badge <?=!empty($result['success']) ? 'bg-emerald-500/20 text-emerald-200' : 'bg-rose-500/20 text-rose-200'?>"><?=!empty($result['success']) ? 'Confirme' : 'Erreur'?></span>
        <h1 class="text-3xl font-bold mt-4">Desabonnement newsletter</h1>
        <p class="mt-3 text-slate-300 leading-7"><?=e((string)($result['message'] ?? ''))?></p>
        <div class="mt-6">
            <a href="<?=defined('APP_BASE') ? APP_BASE : ''?>/" class="inline-flex items-center rounded-full bg-sky-500 px-5 py-3 font-semibold text-white">Retour a l'accueil</a>
        </div>
    </section>
</main>
</body>
</html>
