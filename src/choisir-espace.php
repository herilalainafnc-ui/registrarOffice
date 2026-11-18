<?php
/**
 * Choix de l'espace (/choisir-espace)
 *
 * Demande du 10/11/2026 : « des users comme superadmin, média, compta ou autre
 * peuvent être aussi profs ; si leur adresse est dans les deux cases, une page
 * choisit s'il veut entrer dans la plateforme enseignant ou l'autre ».
 *
 * Après la connexion (mot de passe ou Google), src/loading.php envoie ici une
 * personne qui a deux espaces : son compte du personnel et son compte enseignant
 * (Middleware::espacesDeLaPersonne). Le choix ouvre l'un ou l'autre
 * (Middleware::basculerEspace), puis la page de chargement habituelle. Le menu du
 * compte propose « Changer d'espace » (?depuis=menu) : la bascule mène alors
 * directement à l'accueil de l'espace choisi.
 */

require('../data/backdb.php');
require('../data/middleware.php');

$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') {
    $app_base = '';
}
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

initMiddleware($dtb);

if (!isLoggedIn()) {
    header('Location: ' . $app_base . '/login');
    exit;
}

$espaces = Middleware::espacesDeLaPersonne();
if ($espaces === null) {   // un seul espace : rien à choisir
    header('Location: ' . $app_base . accueilDuCompte());
    exit;
}

$depuisMenu = (($_POST['depuis'] ?? $_GET['depuis'] ?? '') === 'menu');
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $erreur = 'La page a expiré : choisissez de nouveau.';
    } else {
        $cible = Middleware::basculerEspace((string) ($_POST['espace'] ?? ''));
        if ($cible) {
            header('Location: ' . $app_base . ($depuisMenu ? accueilDuCompte($cible) : '/loading'));
            exit;
        }
        $erreur = "Cet espace ne s'ouvre pas pour le moment. Réessayez, ou signalez-le au registrariat.";
    }
}

$personnel = $espaces['personnel'];
$niveau = (int) ($personnel['level'] ?? 0);
$roles = [
    1 => ['Superadmin', 'Le tableau de bord, les étudiants, les inscriptions et les paramètres.'],
    2 => ['Administrateur', 'Le tableau de bord, les étudiants, les inscriptions et les paramètres.'],
    3 => ['Registraire', 'Les inscriptions, les dossiers des étudiants et les notes.'],
    4 => ['Comptabilité', 'Les finances et les paiements des étudiants.'],
    5 => ['Média', 'Les visuels, les photos et les communications de l\'université.'],
    ROLE_WORK_EDUCATION => ['Work Education', 'La tâche de chaque étudiant, session par session.'],
];
[$roleNom, $roleAide] = $roles[$niveau] ?? ['Personnel', 'Les outils de votre fonction.'];

$prenom = trim((string) ($personnel['prenom'] ?? ''));
$nom = trim((string) ($personnel['nom'] ?? ''));
$fiche = $espaces['fiche'];
$nomComplet = trim($prenom . ' ' . $nom) ?: trim(($fiche['lastName'] ?? '') . ' ' . ($fiche['name'] ?? ''));

$photo = trim((string) ($personnel['photos'] ?? ''));
$photoUrl = $photo !== '' ? $app_base . '/app/photosuser/' . rawurlencode($photo) : $app_base . '/file/logo-coldbloud.png';
$logo = $app_base . '/file/logo-coldbloud.png';

$actuel = $espaces['actuel'];
$cartes = [
    ['personnel', 'Espace ' . $roleNom, $roleAide, 'bi-grid-1x2-fill'],
    ['enseignant', 'Espace enseignant', 'Mes cours, la remise des notes et les présences.', 'bi-easel2-fill'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choisir mon espace - Infinit Registrar</title>
    <link rel="shortcut icon" href="<?= $h($logo) ?>" type="image/x-icon">
    <?php include __DIR__ . '/../init/pwa.php'; /* application installable */ ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $h($app_base) ?>/src/css/ciel.css?v=<?= @filemtime(__DIR__ . '/css/ciel.css') ?>">
    <style>
        /* Le seuil de l'application : le même ciel que la connexion et le chargement. */
        :root {
            --or: #88724b; --or-clair: #ceb480;
            --texte: #eceef6; --texte-2: #b3b9d6; --texte-3: #8890b5;
            --accent: #5a6fda; --carte: rgba(26, 31, 60, .78); --filet: rgba(179, 185, 214, .22);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { min-height: 100%; }
        body { font-family: 'Inter', -apple-system, 'Segoe UI', Roboto, sans-serif; background: #07091a; color: var(--texte); -webkit-font-smoothing: antialiased; }

        .espace { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px 20px; text-align: center; }
        .apparait { animation: apparait .8s cubic-bezier(.2, .7, .2, 1) both; }
        @keyframes apparait { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        .photo { width: 92px; height: 92px; border-radius: 50%; object-fit: cover; margin-bottom: 22px; box-shadow: 0 0 0 3px #07091a, 0 0 0 4px rgba(206, 180, 128, .85), 0 0 36px rgba(90, 111, 218, .3); }
        .salut { font-size: 15px; letter-spacing: .04em; color: var(--texte-2); margin-bottom: 8px; animation-delay: .1s; }
        h1 { font-weight: 300; font-size: clamp(24px, 4.4vw, 40px); line-height: 1.15; letter-spacing: .1em; text-transform: uppercase; background: linear-gradient(180deg, #fff 30%, #9eacf1 100%); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 14px; animation-delay: .2s; }
        .aide { max-width: 520px; font-size: 14.5px; line-height: 1.6; color: var(--texte-2); margin-bottom: 30px; animation-delay: .3s; }

        .choix { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; width: min(640px, 100%); animation-delay: .4s; }
        .carte {
            position: relative; display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 28px 20px 24px;
            border: 1px solid var(--filet); border-radius: 18px; background: var(--carte); color: var(--texte);
            font: inherit; text-align: center; cursor: pointer; backdrop-filter: blur(6px);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
        }
        .carte:hover { transform: translateY(-3px); border-color: rgba(206, 180, 128, .75); box-shadow: 0 14px 36px -10px rgba(0, 0, 0, .6), 0 0 0 1px rgba(206, 180, 128, .35); background: rgba(34, 40, 78, .9); }
        .carte:active { transform: translateY(-1px) scale(.99); }
        .carte:focus-visible { outline: none; border-color: var(--or-clair); box-shadow: 0 0 0 3px rgba(90, 111, 218, .55); }
        .carte__texte { display: flex; flex-direction: column; align-items: inherit; gap: 4px; }
        .carte__icone { width: 54px; height: 54px; display: grid; place-items: center; border-radius: 16px; background: rgba(90, 111, 218, .16); color: #9eacf1; font-size: 24px; box-shadow: inset 0 0 0 1px rgba(90, 111, 218, .35); }
        .carte--enseignant .carte__icone { background: rgba(206, 180, 128, .14); color: var(--or-clair); box-shadow: inset 0 0 0 1px rgba(206, 180, 128, .4); }
        .carte__titre { font-size: 17px; font-weight: 600; letter-spacing: .01em; }
        .carte__aide { font-size: 13.5px; line-height: 1.5; color: var(--texte-2); }
        .carte__actuel { position: absolute; top: 12px; right: 12px; padding: 2px 9px; border-radius: 999px; background: rgba(52, 211, 153, .15); border: 1px solid rgba(52, 211, 153, .45); color: #34d399; font-size: 11.5px; font-weight: 600; }

        .erreur { margin-top: 20px; max-width: 520px; padding: 10px 16px; border-radius: 10px; background: rgba(248, 113, 113, .12); border: 1px solid rgba(248, 113, 113, .4); color: #fca5a5; font-size: 13.5px; }
        .liens { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 22px; margin-top: 28px; font-size: 13px; animation: apparait .8s .55s both; }
        .liens a { color: var(--texte-3); text-decoration: none; padding: 6px 2px; }
        .liens a:hover { color: var(--or-clair); text-decoration: underline; }
        .liens a:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(90, 111, 218, .55); border-radius: 4px; }

        @media (max-width: 640px) { .choix { grid-template-columns: 1fr; } .carte { flex-direction: row; text-align: left; padding: 18px; gap: 14px; } .carte__icone { flex: none; } .carte__texte { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; } .carte__actuel { top: -9px; right: 14px; } }
        @media (prefers-reduced-motion: reduce) { .apparait, .liens { animation: none; } .carte { transition: none; } }
    </style>
</head>
<body>
    <div class="ciel" data-ciel aria-hidden="true"></div>

    <main class="espace">
        <img class="photo apparait" src="<?= $h($photoUrl) ?>" alt="" width="92" height="92" onerror="this.onerror=null; this.src='<?= $h($logo) ?>'">
        <p class="salut apparait">Bonjour</p>
        <h1 class="apparait"><?= $h($nomComplet) ?></h1>
        <p class="aide apparait">Votre adresse est aussi celle d'un enseignant de l'université. Choisissez l'espace à ouvrir : vous pourrez changer à tout moment depuis le menu de votre compte.</p>

        <form method="post" action="<?= $h($app_base) ?>/choisir-espace" class="choix apparait">
            <?= csrf_field() ?>
            <input type="hidden" name="depuis" value="<?= $depuisMenu ? 'menu' : 'connexion' ?>">
            <?php foreach ($cartes as [$valeur, $titre, $aide, $icone]): ?>
            <button type="submit" name="espace" value="<?= $h($valeur) ?>" class="carte carte--<?= $h($valeur) ?>">
                <?php if ($depuisMenu && $actuel === $valeur): ?><span class="carte__actuel">Espace actuel</span><?php endif; ?>
                <span class="carte__icone"><i class="bi <?= $h($icone) ?>" aria-hidden="true"></i></span>
                <span class="carte__texte">
                    <span class="carte__titre"><?= $h($titre) ?></span>
                    <span class="carte__aide"><?= $h($aide) ?></span>
                </span>
            </button>
            <?php endforeach; ?>
        </form>

        <?php if ($erreur !== ''): ?><p class="erreur" role="alert"><?= $h($erreur) ?></p><?php endif; ?>

        <p class="liens">
            <?php if ($depuisMenu): ?><a href="<?= $h($app_base . accueilDuCompte()) ?>">Rester dans mon espace actuel</a><?php endif; ?>
            <a href="<?= $h($app_base) ?>/logout">Ce n'est pas vous ? Se déconnecter</a>
        </p>
    </main>

    <script src="<?= $h($app_base) ?>/src/js/ciel.js?v=<?= @filemtime(__DIR__ . '/js/ciel.js') ?>"></script>
</body>
</html>
