<?php 
/**
 * Page de connexion - Infinit Registrar
 * Mise à jour avec middleware sécurisé
 */

require('../data/backdb.php');
require('../data/middleware.php');

// Initialiser le middleware avec la connexion DB
initMiddleware($dtb);

// Google Client ID
$googleClientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';

// Variable pour les erreurs
$loginError = false;

// Si déjà connecté, rediriger vers l'accueil de son rôle
if (isLoggedIn()) {
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . accueilDuCompte());
    exit;
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    if (isset($_POST['infinit_pseudo']) && isset($_POST['infinit_password'])) {
        
        $pseudo = trim($_POST['infinit_pseudo']);
        $password = $_POST['infinit_password'];
        $remember = isset($_POST['infinit_souvenir']) && !empty($_POST['infinit_souvenir']);
        
        // Authentification sécurisée via le middleware
        if (Middleware::authenticate($pseudo, $password, $remember)) {
            // Log de connexion réussie
            Middleware::logSecurityEvent('login_success', ['pseudo' => $pseudo]);
            
            // Enregistrer la localisation GPS
            $gpsLat = isset($_POST['gps_latitude']) && $_POST['gps_latitude'] !== '' ? floatval($_POST['gps_latitude']) : null;
            $gpsLng = isset($_POST['gps_longitude']) && $_POST['gps_longitude'] !== '' ? floatval($_POST['gps_longitude']) : null;
            $gpsAcc = isset($_POST['gps_accuracy']) && $_POST['gps_accuracy'] !== '' ? floatval($_POST['gps_accuracy']) : null;
            $gpsDenied = isset($_POST['gps_denied']) && $_POST['gps_denied'] === '1';
            Middleware::saveLoginLocation($gpsLat, $gpsLng, $gpsAcc, 'password', $gpsDenied);
            
            // Destination finale : toujours l'accueil du rôle (le tableau de
            // bord pour le personnel), jamais la page consultée avant
            // l'expiration de la session.
            $destination = (defined('APP_BASE') ? APP_BASE : '') . accueilDuCompte(Middleware::getCurrentUser());
            unset($_SESSION['url_demandee']);

            // Stocker la destination dans la session
            $_SESSION['login_redirect'] = $destination;
            
            // Rediriger vers la page de chargement
            header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/loading');
            exit;
        } else {
            // Log de tentative échouée
            Middleware::logSecurityEvent('login_failed', ['pseudo' => $pseudo]);
            $loginError = true;
        }
    }
}

// Permettre les popups Google Sign-In (FedCM / One Tap)
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Infinit Registrar</title>
    <?php
    // Calculer le chemin de base pour les URLs
    $_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $app_base = substr($_app_root, strlen($_doc_root));
    if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
    ?>
    <script>var APP_BASE = '<?=$app_base?>';</script>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <?php include __DIR__ . '/../init/pwa.php'; /* application installable */ ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap">
    <link rel="stylesheet" href="<?=$app_base?>/src/css/ciel.css?v=<?=@filemtime(__DIR__ . '/css/ciel.css')?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        /* ===================================================================
           Page de connexion

           Composition reprise de la page d'authentification d'ABC : un cadre
           unique qui tient deux panneaux — l'encart de marque a gauche, le
           formulaire a droite. Le rembourrage du cadre fait la marge de
           l'encart : c'est ce lisere qui donne sa tenue a l'ensemble.

           Les couleurs, elles, restent celles de l'universite : marine et or
           du logo, la ou ABC emploie son cyan et son vert. Reprendre aussi sa
           palette aurait donne a l'UAZ l'identite d'un autre.
           =================================================================== */

        /* Les valeurs exactes du sceau (voir src/css/tokens.css) : bleu
           Zurcher #222e65, or #88724b. Les fonds et les textes sont ceux
           du back-office, pour que l'on passe de l'un a l'autre sans heurt. */
        :root {
            --uaz-marine:       #222e65;
            --uaz-marine-clair: #4658c2;
            --uaz-or:           #88724b;
            --uaz-or-fonce:     #7a6540;
            --uaz-or-clair:     #ceb480;

            --fond-page:   #0d1023;
            --fond-cadre:  #12162c;
            --encre:       #eceef6;
            --encre-douce: #b3b9d6;
            --filet:       rgba(179, 185, 214, .2);

            --champ-fond:  rgba(255, 255, 255, .05);
            --champ-texte: #f1f4fa;
            /* #7d8798 ne donnerait que 3,4:1 sur le fond des champs. Le texte
               d'indication est du texte : il tombe sous la meme regle que le
               reste. */
            --champ-indication: #8890b5;

            --rayon-cadre: 2rem;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            padding: clamp(.75rem, 2.4vw, 2.25rem);
            font-family: "Inter", -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--fond-page);
            color: var(--encre);
            -webkit-font-smoothing: antialiased;
        }

        .login-layout {
            min-height: calc(100vh - 2 * clamp(.75rem, 2.4vw, 2.25rem));
            display: grid;
            grid-template-columns: minmax(0, 1.06fr) minmax(0, 1fr);
            gap: clamp(1rem, 2vw, 1.75rem);
            padding: clamp(.7rem, 1.3vw, 1.15rem);
            border-radius: var(--rayon-cadre);
            background: var(--fond-cadre);
            box-shadow: 0 30px 70px rgba(2, 8, 23, .45);
        }

        /* ---- Encart de marque -------------------------------------------- */

        .login-brand-panel {
            position: relative;
            overflow: hidden;
            border-radius: calc(var(--rayon-cadre) - .65rem);
            color: #f3f6ff;
            /* Le ciel des pages du seuil : horizon bleu Zurcher en bas, nuit
               en haut, une lueur or. Les etoiles montent (src/css/ciel.css). */
            background:
                radial-gradient(120% 70% at 50% 112%, rgba(70, 88, 194, .45) 0%, transparent 58%),
                radial-gradient(95% 70% at 88% 18%,   rgba(206, 180, 128, .12) 0%, transparent 52%),
                radial-gradient(ellipse at bottom, #222e65 0%, #12162c 58%, #07091a 100%);
        }

        /* Poussiere d'etoiles : des points de tailles inegales, sans image a
           telecharger. */
        /* Les etoiles de l'encart : le ciel anime des pages du seuil. */
        .login-brand-panel .ciel { z-index: 0; }

        .brand-panel-inner {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 2rem;
            padding: clamp(1.6rem, 3vw, 2.75rem);
        }

        .brand-header { display: flex; align-items: center; gap: .9rem; }
        .brand-logo {
            width: 46px; height: 46px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255,255,255,.08);
            padding: 6px;
        }
        .brand-header h1 { font-size: 1.15rem; font-weight: 700; letter-spacing: -.01em; }
        .brand-header p  { font-size: .82rem; color: rgba(243,246,255,.62); }

        .brand-copy h2 {
            font-size: clamp(1.55rem, 2.6vw, 2.25rem);
            line-height: 1.18;
            font-weight: 700;
            letter-spacing: -.02em;
            margin-bottom: .9rem;
        }
        .brand-copy > p {
            max-width: 34rem;
            font-size: .95rem;
            line-height: 1.65;
            color: rgba(243,246,255,.72);
            margin-bottom: 1.9rem;
        }

        .brand-feature-list { list-style: none; display: flex; flex-direction: column; gap: 1.05rem; }
        .brand-feature-list li { display: flex; align-items: flex-start; gap: .85rem; }
        .brand-feature-list strong { display: block; font-size: .92rem; font-weight: 600; margin-bottom: .12rem; }
        .brand-feature-list .desc { font-size: .84rem; line-height: 1.5; color: rgba(243,246,255,.62); }

        .feature-chip {
            flex: 0 0 auto;
            width: 34px; height: 34px;
            display: grid; place-items: center;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color: var(--uaz-or-clair);
        }
        .feature-icon {
            width: 17px; height: 17px;
            fill: none; stroke: currentColor;
            stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round;
        }

        .brand-footer { font-size: .78rem; color: rgba(243,246,255,.45); }

        /* ---- Panneau du formulaire --------------------------------------- */

        .login-auth-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 1.1rem;
            padding: clamp(1.5rem, 3vw, 3rem) clamp(1rem, 3vw, 2.5rem);
        }

        .login-card { width: 100%; max-width: 25rem; }

        .login-card > h2 {
            font-size: clamp(1.6rem, 2.4vw, 2.1rem);
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: -.02em;
            margin-bottom: .35rem;
        }
        .login-subtitle { font-size: .92rem; color: var(--encre-douce); margin-bottom: 1.75rem; }

        .form-alert {
            display: flex; align-items: center; gap: .5rem;
            padding: .75rem 1rem;
            margin-bottom: 1.1rem;
            border-radius: 10px;
            border: 1px solid rgba(248, 113, 113, .45);
            background: rgba(248, 113, 113, .12);
            color: #fca5a5;
            font-size: .87rem;
        }

        .login-form label {
            display: block;
            font-size: .82rem;
            font-weight: 500;
            color: var(--encre-douce);
            margin-bottom: .4rem;
        }

        .input-wrap { position: relative; margin-bottom: 1.05rem; }
        .input-wrap input {
            width: 100%;
            padding: .85rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--filet);
            background: var(--champ-fond);
            color: var(--champ-texte);
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        .input-wrap input::placeholder { color: var(--champ-indication); }
        .input-wrap input:focus {
            outline: none;
            border-color: var(--uaz-or);
            box-shadow: 0 0 0 3px rgba(136, 114, 75, .35);
        }
        .input-wrap.has-toggle input { padding-right: 3rem; }

        .password-toggle {
            position: absolute;
            top: 50%; right: .5rem;
            transform: translateY(-50%);
            /* 40 px de cote : une cible qu'on atteint au doigt. */
            width: 40px; height: 40px;
            display: grid; place-items: center;
            border: 0; border-radius: 10px;
            background: transparent;
            color: var(--encre-douce);
            cursor: pointer;
            transition: color .18s ease, background .18s ease;
        }
        .password-toggle:hover { color: var(--encre); background: rgba(255,255,255,.06); }
        .password-toggle:focus-visible { outline: 2px solid var(--uaz-or-clair); outline-offset: 2px; }
        .password-toggle svg { width: 18px; height: 18px; }
        /* Une seule des deux icones parait ; l'etat vit sur le bouton. */
        .password-toggle .icon-eye-off { display: none; }
        .password-toggle.est-visible .icon-eye     { display: none; }
        .password-toggle.est-visible .icon-eye-off { display: block; }

        .remember-row { display: flex; align-items: center; gap: .55rem; margin: .2rem 0 1.4rem; }
        .remember-row input { width: 16px; height: 16px; accent-color: var(--uaz-or); cursor: pointer; }
        .remember-row label { margin: 0; cursor: pointer; color: var(--encre-douce); font-size: .87rem; }

        .submit-btn {
            width: 100%;
            padding: .95rem 1rem;
            border: 0; border-radius: 12px;
            /* Blanc sur l'or du sceau : 4,6:1. Le marine d'avant n'y
               atteignait que 3,5:1. */
            background: var(--uaz-or);
            color: #ffffff;
            font-family: inherit;
            font-size: .97rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .18s ease, transform .18s ease;
        }
        .submit-btn:hover { background: var(--uaz-or-fonce); transform: translateY(-1px); }
        .submit-btn:focus-visible { outline: 2px solid var(--uaz-or-clair); outline-offset: 3px; }
        .submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .login-divider {
            display: flex; align-items: center; gap: .9rem;
            margin: 1.5rem 0 1.1rem;
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--encre-douce);
        }
        .login-divider::before, .login-divider::after {
            content: ""; flex: 1; height: 1px; background: var(--filet);
        }

        #g_id_signin { display: flex; justify-content: center; min-height: 44px; }

        .google-info {
            margin-top: .85rem;
            text-align: center;
            font-size: .8rem;
            color: var(--encre-douce);
        }

        .google-error {
            display: none;
            margin-top: .8rem;
            padding: .7rem .9rem;
            border-radius: 10px;
            border: 1px solid rgba(248,113,113,.45);
            background: rgba(248,113,113,.12);
            color: #fca5a5;
            font-size: .85rem;
            text-align: center;
        }
        .google-error.show { display: block; }

        .back-link {
            font-size: .85rem;
            color: var(--uaz-or-clair);
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        /* Installer l'application (src/js/pwa.js) : seulement si le navigateur
           le permet. */
        .install-link {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            min-height: 44px;
            margin-top: .25rem;
            padding: 0 1rem;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            background: none;
            color: inherit;
            font: inherit;
            font-size: .85rem;
            cursor: pointer;
        }
        .install-link[hidden] { display: none; }
        .install-link:hover { border-color: var(--uaz-or-clair); }

        /* Repris de l'encart, pour les ecrans qui ne l'affichent pas. */
        .mobile-brand { display: none; text-align: center; }
        .mobile-brand img { width: 52px; height: 52px; object-fit: contain; margin-bottom: .6rem; }
        .mobile-brand h1 { font-size: 1.2rem; font-weight: 700; }
        .mobile-brand p  { font-size: .84rem; color: var(--encre-douce); }

        /* ---- Ecrans etroits ---------------------------------------------- */

        @media (max-width: 980px) {
            .login-layout { grid-template-columns: minmax(0, 1fr); }
            /* L'encart raconte l'application ; sur un ecran etroit il
               repousserait le formulaire hors de vue. Son en-tete revient
               au-dessus du formulaire. */
            .login-brand-panel { display: none; }
            .mobile-brand { display: block; }
        }

        @media (prefers-reduced-motion: reduce) {
            .submit-btn:hover { transform: none; }
        }
    </style>
</head>
<body>
    <!-- Charge des l'ouverture du corps, pas en fin de page : le rideau doit
         etre trace avant le premier affichage, sinon la page de connexion
         paraitrait une fraction de seconde avant d'etre recouverte. -->
    <script src="<?=$app_base?>/landing/pages/stinger.js?v=<?=@filemtime(ROOT_DIR . '/landing/pages/stinger.js')?>"></script>
    <script src="<?=$app_base?>/src/js/ciel.js?v=<?=@filemtime(__DIR__ . '/js/ciel.js')?>" defer></script>

    <main class="login-layout">

        <!-- Encart de marque. Il ne porte aucune information dont le
             formulaire ait besoin : il est donc retire de l'arbre
             d'accessibilite, et masque sur les ecrans etroits. -->
        <section class="login-brand-panel" aria-hidden="true">
            <div class="ciel ciel--local" data-ciel="0.7"></div>
            <div class="brand-panel-inner">

                <div class="brand-header">
                    <img src="<?=$app_base?>/file/logo-coldbloud.png" alt="" class="brand-logo">
                    <div>
                        <h1>Registrar</h1>
                        <p>Université Adventiste Zurcher</p>
                    </div>
                </div>

                <div class="brand-copy">
                    <h2>Toute la scolarité, dans un seul dossier</h2>
                    <p>
                        Inscriptions, notes, relevés et attestations : le registrariat
                        travaille sur un dossier unique par étudiant, de la candidature
                        au diplôme.
                    </p>

                    <ul class="brand-feature-list">
                        <li>
                            <span class="feature-chip">
                                <svg class="feature-icon" viewBox="0 0 24 24">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </span>
                            <div>
                                <strong>Inscriptions et candidatures</strong>
                                <span class="desc">De la pré-inscription en ligne au dossier complet</span>
                            </div>
                        </li>
                        <li>
                            <span class="feature-chip">
                                <svg class="feature-icon" viewBox="0 0 24 24">
                                    <path d="M4 4h11l5 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"></path>
                                    <path d="M15 4v5h5"></path>
                                    <path d="M8 13h7"></path>
                                    <path d="M8 17h5"></path>
                                </svg>
                            </span>
                            <div>
                                <strong>Notes et relevés</strong>
                                <span class="desc">Saisie, délibération et édition des documents officiels</span>
                            </div>
                        </li>
                        <li>
                            <span class="feature-chip">
                                <svg class="feature-icon" viewBox="0 0 24 24">
                                    <path d="M12 3 5.3 6v6c0 4 2.3 7.1 6.7 8.7 4.4-1.6 6.7-4.7 6.7-8.7V6z"></path>
                                    <path d="m9.4 12.2 1.9 1.9 3.4-3.6"></path>
                                </svg>
                            </span>
                            <div>
                                <strong>Accès contrôlés</strong>
                                <span class="desc">Chaque compte ne voit que ce qui le concerne</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <p class="brand-footer">© <?= date('Y') ?> Université Adventiste Zurcher — Antsirabe</p>
            </div>
        </section>

        <section class="login-auth-panel">

            <!-- Reprend l'en-tete de l'encart la ou celui-ci est masque. -->
            <div class="mobile-brand">
                <img src="<?=$app_base?>/file/logo-coldbloud.png" alt="Logo de l'Université Adventiste Zurcher">
                <h1>Registrar</h1>
                <p>Université Adventiste Zurcher</p>
            </div>

            <article class="login-card">
                <h2>Connexion au registrariat</h2>
                <p class="login-subtitle">Veuillez vous connecter pour continuer</p>

                <?php if ($loginError): ?>
                <div class="form-alert" role="alert">
                    <i class="bi bi-exclamation-circle"></i>
                    Identifiants incorrects. Veuillez réessayer.
                </div>
                <?php endif; ?>

                <form method="post" action="" id="loginForm" class="login-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="gps_latitude"  id="gps_latitude"  value="">
                    <input type="hidden" name="gps_longitude" id="gps_longitude" value="">
                    <input type="hidden" name="gps_accuracy"  id="gps_accuracy"  value="">
                    <input type="hidden" name="gps_denied"    id="gps_denied"    value="0">

                    <label for="pseudo">Nom d'utilisateur ou adresse e-mail</label>
                    <div class="input-wrap">
                        <input type="text" id="pseudo" name="infinit_pseudo"
                               autocomplete="username"
                               placeholder="Identifiant ou adresse @zurcher.edu.mg" required>
                    </div>

                    <label for="password">Mot de passe</label>
                    <div class="input-wrap has-toggle">
                        <input type="password" id="password" name="infinit_password"
                               autocomplete="current-password"
                               placeholder="Entrez votre mot de passe" required>
                        <button type="button" class="password-toggle" id="passwordToggle"
                                onclick="togglePassword()"
                                aria-label="Afficher le mot de passe" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.8 21.8 0 0 1 5.06-7.94"></path>
                                <path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-3.22 4.89"></path>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>

                    <div class="remember-row">
                        <input type="checkbox" id="remember" name="infinit_souvenir" value="1">
                        <label for="remember">Se souvenir de moi</label>
                    </div>

                    <button type="submit" class="submit-btn">Connexion</button>
                </form>

                <div class="login-divider"><span>ou continuer avec</span></div>

                <!-- Conteneur du bouton rendu par Google -->
                <div id="g_id_signin"></div>

                <p class="google-info">
                    <i class="bi bi-shield-check"></i> Connexion rapide avec votre compte Zurcher
                </p>

                <div class="google-error" id="googleError"></div>
            </article>

            <a class="back-link" href="<?=$app_base?>/" data-stinger>Retour à l'accueil</a>
            <button type="button" class="install-link" data-pwa-installer hidden><i class="bi bi-download"></i> Installer l'application sur cet appareil</button>
        </section>

    </main>

    <script>
        // ====== Géolocalisation GPS ======
        let gpsData = { latitude: null, longitude: null, accuracy: null, denied: false, ready: false, source: 'none' };

        /**
         * Fallback : géolocalisation par IP via des APIs publiques gratuites
         * Utilisé quand le GPS navigateur est refusé (HTTP non-sécurisé)
         */
        function ipGeoFallback() {
            return fetch('https://ip-api.com/json/?fields=lat,lon,city,country,query', { mode: 'cors' })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.lat && data.lon) {
                        gpsData.latitude = data.lat;
                        gpsData.longitude = data.lon;
                        gpsData.accuracy = 5000; // ~5km (précision IP)
                        gpsData.denied = false;
                        gpsData.source = 'ip';
                        console.log('Géoloc IP:', data.lat, data.lon, '(' + (data.city || '') + ', ' + (data.country || '') + ')');
                    }
                })
                .catch(function() {
                    // 2e tentative avec un autre service
                    return fetch('https://ipwho.is/', { mode: 'cors' })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (data.success !== false && data.latitude && data.longitude) {
                                gpsData.latitude = data.latitude;
                                gpsData.longitude = data.longitude;
                                gpsData.accuracy = 5000;
                                gpsData.denied = false;
                                gpsData.source = 'ip';
                                console.log('Géoloc IP (fallback 2):', data.latitude, data.longitude);
                            }
                        })
                        .catch(function(e) {
                            console.log('Impossible de géolocaliser par IP:', e);
                        });
                });
        }

        // Créer une promesse pour le GPS
        let gpsPromise = new Promise(function(resolve) {
            // Vérifier si le contexte est sécurisé (HTTPS ou localhost)
            var isSecure = window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';

            if (isSecure && navigator.geolocation) {
                // Essayer le GPS natif du navigateur
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        gpsData.latitude = position.coords.latitude;
                        gpsData.longitude = position.coords.longitude;
                        gpsData.accuracy = position.coords.accuracy;
                        gpsData.denied = false;
                        gpsData.ready = true;
                        gpsData.source = 'gps';
                        console.log('GPS navigateur capturé:', gpsData.latitude, gpsData.longitude);
                        resolve(gpsData);
                    },
                    function(error) {
                        console.log('GPS navigateur refusé:', error.message, '→ fallback IP');
                        // Fallback sur la géolocalisation par IP
                        ipGeoFallback().finally(function() {
                            gpsData.ready = true;
                            if (!gpsData.latitude) gpsData.denied = true;
                            resolve(gpsData);
                        });
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 300000 }
                );
                // Timeout de sécurité
                setTimeout(function() {
                    if (!gpsData.ready) {
                        console.log('GPS timeout → fallback IP');
                        ipGeoFallback().finally(function() {
                            gpsData.ready = true;
                            if (!gpsData.latitude) gpsData.denied = true;
                            resolve(gpsData);
                        });
                    }
                }, 5500);
            } else {
                // Pas de contexte sécurisé → directement géolocalisation par IP
                console.log('Contexte non-sécurisé (HTTP) → géolocalisation par IP');
                ipGeoFallback().finally(function() {
                    gpsData.ready = true;
                    if (!gpsData.latitude) gpsData.denied = true;
                    resolve(gpsData);
                });
            }
        });

        // Remplir les champs cachés du formulaire avec les données GPS
        function fillGpsFields() {
            document.getElementById('gps_latitude').value = gpsData.latitude !== null ? gpsData.latitude : '';
            document.getElementById('gps_longitude').value = gpsData.longitude !== null ? gpsData.longitude : '';
            document.getElementById('gps_accuracy').value = gpsData.accuracy !== null ? gpsData.accuracy : '';
            document.getElementById('gps_denied').value = gpsData.denied ? '1' : '0';
        }

        // Intercepter la soumission du formulaire pour attendre le GPS
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            loginForm.addEventListener('submit', function(e) {
                // Si le GPS est déjà prêt, remplir et soumettre immédiatement
                if (gpsData.ready) {
                    fillGpsFields();
                    return; // laisse le formulaire se soumettre normalement
                }
                
                // Sinon, empêcher la soumission et attendre le GPS
                e.preventDefault();
                const submitBtn = loginForm.querySelector('.submit-btn');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Localisation...';
                submitBtn.disabled = true;

                gpsPromise.then(function() {
                    fillGpsFields();
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    loginForm.submit();
                });
            });
        });

        function togglePassword() {
            const champ  = document.getElementById('password');
            const bouton = document.getElementById('passwordToggle');
            const visible = champ.type === 'text';

            champ.type = visible ? 'password' : 'text';

            // L'etat vit sur le bouton : la feuille de style choisit laquelle
            // des deux icones parait, et l'intitule suit pour un lecteur
            // d'ecran. L'ancienne version echangeait deux emoji, que rien ne
            // garantit de voir rendus de la meme facon d'un poste a l'autre.
            bouton.classList.toggle('est-visible', !visible);
            bouton.setAttribute('aria-pressed', String(!visible));
            bouton.setAttribute('aria-label',
                visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
        }

        // ====== Google Sign-In ======
        const GOOGLE_CLIENT_ID = '<?= htmlspecialchars($googleClientId) ?>';

        // Initialiser Google Identity Services au chargement
        window.addEventListener('load', function() {
            if (!GOOGLE_CLIENT_ID) {
                console.warn('Google Client ID non configuré.');
                document.getElementById('g_id_signin').innerHTML = '<p style="color:#94a3b8;font-size:12px;text-align:center;">Connexion Google non disponible</p>';
                return;
            }

            // Attendre que la lib Google soit prête
            function tryInit() {
                if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                    // Initialiser
                    google.accounts.id.initialize({
                        client_id: GOOGLE_CLIENT_ID,
                        callback: handleGoogleCredential,
                        auto_select: false,
                        cancel_on_tap_outside: true
                    });

                    // Rendre le bouton Google officiel dans le conteneur
                    google.accounts.id.renderButton(
                        document.getElementById('g_id_signin'),
                        {
                            type: 'standard',
                            theme: 'outline',
                            size: 'large',
                            text: 'signin_with',
                            shape: 'rectangular',
                            logo_alignment: 'left',
                            width: 356
                        }
                    );
                    console.log('Google Sign-In initialisé avec succès.');
                } else {
                    // Réessayer dans 300ms
                    setTimeout(tryInit, 300);
                }
            }

            tryInit();
            // Abandonner après 15s
            setTimeout(function() {
                const container = document.getElementById('g_id_signin');
                if (container && container.children.length === 0) {
                    container.innerHTML = '<p style="color:#ff6b6b;font-size:12px;text-align:center;">Impossible de charger Google Sign-In. Vérifiez votre connexion internet.</p>';
                }
            }, 15000);
        });

        // Callback quand Google renvoie le credential
        function handleGoogleCredential(response) {
            const errorDiv = document.getElementById('googleError');
            errorDiv.classList.remove('show');

            // Afficher un état de chargement
            const gBtn = document.getElementById('g_id_signin');
            const originalContent = gBtn.innerHTML;
            gBtn.innerHTML = '<p style="color:#94a3b8;font-size:13px;text-align:center;"><i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite;display:inline-block;"></i> Connexion en cours...</p>';

            // Envoyer le token au serveur avec les coordonnées GPS
            fetch(APP_BASE+'/api/google-auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    credential: response.credential,
                    gps_latitude: gpsData.latitude,
                    gps_longitude: gpsData.longitude,
                    gps_accuracy: gpsData.accuracy,
                    gps_denied: gpsData.denied
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    gBtn.innerHTML = originalContent;
                    showGoogleError(data.message || 'Échec de la connexion. Veuillez réessayer.');
                }
            })
            .catch(err => {
                gBtn.innerHTML = originalContent;
                showGoogleError('Erreur de connexion au serveur. Veuillez réessayer.');
                console.error('Google Auth Error:', err);
            });
        }

        function showGoogleError(message) {
            const errorDiv = document.getElementById('googleError');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
        }
    </script>
</body>
</html>