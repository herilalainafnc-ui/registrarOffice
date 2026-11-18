<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
require_once('../data/newsletter.php');

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

initMiddleware($dtb);

$flash = $_SESSION['newsletter_flash'] ?? '';
unset($_SESSION['newsletter_flash']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Newsletter</title>
    <style>
        body { background: linear-gradient(180deg, rgb(var(--c-canvas)) 0%, rgb(var(--c-canvas)) 40%, #f4f7fb 40%, #f4f7fb 100%); }
        .nl-shell { max-width: 720px; margin: 0 auto; padding: 64px 20px 40px; }
        .nl-card { background: rgba(255,255,255,0.96); border: 1px solid rgb(var(--c-line-strong)); border-radius: 24px; box-shadow: 0 24px 60px rgba(15,23,42,0.14); overflow: hidden; }
        .nl-hero { padding: 30px; background: linear-gradient(135deg, rgb(var(--c-surface)) 0%, rgb(var(--c-surface-2)) 100%); color: rgb(var(--c-text)); }
        .nl-body { padding: 30px; }
        .nl-input { width: 100%; border: 1px solid rgb(var(--c-line-strong)); border-radius: 14px; padding: 14px 16px; font-size: 15px; }
        .nl-input:focus { outline: none; border-color: rgb(var(--c-accent)); box-shadow: 0 0 0 3px rgba(11,99,206,0.12); }
        .nl-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: rgb(var(--c-accent-strong)); color: #fff; border: 0; border-radius: 999px; padding: 14px 22px; font-weight: 700; }
        .nl-btn:hover { background: rgb(var(--c-accent-strong)); }
        .nl-note { font-size: 13px; color: rgb(var(--c-text-3)); line-height: 1.6; }
    </style>
</head>
<body>
<main class="nl-shell">
    <section class="nl-card">
        <div class="nl-hero">
            <div class="text-sm uppercase tracking-[0.2em] text-sky-200/80">Newsletter</div>
            <h1 class="text-3xl font-bold mt-2">S'abonner aux communications</h1>
            <p class="mt-3 max-w-2xl text-slate-200">Reçois les annonces officielles, les informations de campus et les messages importants par email.</p>
        </div>
        <div class="nl-body">
            <?php if (!empty($flash)): ?>
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800"><?=e($flash)?></div>
            <?php endif; ?>
            <form method="post" class="grid gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Nom complet</label>
                    <input class="nl-input" type="text" name="full_name" placeholder="Votre nom" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Adresse email</label>
                    <input class="nl-input" type="email" name="email" placeholder="vous@exemple.com" required />
                </div>
                <button type="submit" class="nl-btn">S'abonner</button>
                <p class="nl-note">Chaque email contient un lien de desabonnement individuel.</p>
            </form>
        </div>
    </section>
</main>
</body>
</html>
