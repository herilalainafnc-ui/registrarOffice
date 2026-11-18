<?php
/**
 * Page de chargement après connexion
 * Affiche une animation avec le message de bienvenue et la photo de profil
 */

require('../data/backdb.php');
require('../data/middleware.php');

// Calculer le chemin de base pour les URLs
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') {
    $app_base = '';
}

// Initialiser le middleware
initMiddleware($dtb);

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/login');
    exit;
}

// Une personne du personnel qui est aussi enseignante choisit d'abord son espace (src/choisir-espace.php)
if (empty($_SESSION['espace_choisi']) && Middleware::espacesDeLaPersonne() !== null) {
    header('Location: ' . $app_base . '/choisir-espace');
    exit;
}

// Récupérer les informations de l'utilisateur
$user = Middleware::getCurrentUser();
$userName = htmlspecialchars($user['nom'] ?? $user['pseudo'] ?? 'Utilisateur');
$userFirstName = htmlspecialchars($user['prenom'] ?? '');
$userPhoto = $user['photos'] ?? '';
$userLevel = (int)($user['level'] ?? 3);
$userType = $user['user_type'] ?? 'admin';

// Pour les étudiants, récupérer les infos depuis tbl_2024_etudiant
if (Middleware::isStudent()) {
    $studentInfo = Middleware::getStudentInfo();
    if ($studentInfo) {
        // Utiliser les informations de l'étudiant
        $userName = htmlspecialchars($studentInfo['student_nom'] ?? $userName);
        $userFirstName = htmlspecialchars($studentInfo['student_prenom'] ?? $userFirstName);
        // La photo de l'étudiant est dans image_student
        if (!empty($studentInfo['image_student'])) {
            $userPhoto = $studentInfo['image_student'];
            $userType = 'student'; // Forcer le type pour le chemin de la photo
        }
    }
}

// Pour les enseignants, récupérer les infos depuis la table teacher
if (Middleware::isTeacher()) {
    $teacherInfo = Middleware::getTeacherInfo();
    if ($teacherInfo) {
        if (!empty($teacherInfo['lastName'])) {
            $userName = htmlspecialchars($teacherInfo['lastName']);
        }
        if (!empty($teacherInfo['name'])) {
            $userFirstName = htmlspecialchars($teacherInfo['name']);
        }
        if (!empty($teacherInfo['teacher_image'])) {
            $userPhoto = $teacherInfo['teacher_image'];
            $userType = 'teacher';
        }
    }
}

// Déterminer le chemin de la photo
$photoPath = '';
if (!empty($userPhoto)) {
    if ($userType === 'student' || $userLevel === 8) {
        // Photos des étudiants
        $photoPath = $app_base . '/app/photosetudiants/' . $userPhoto;
    } elseif ($userType === 'teacher' || $userLevel === 7) {
        // Photos des enseignants
        $photoPath = $app_base . '/app/photosenseignants/' . $userPhoto;
    } else {
        // Photos des utilisateurs (staff)
        $photoPath = $app_base . '/app/photosuser/' . $userPhoto;
    }
}

// Si pas de photo ou fichier inexistant, utiliser le logo
if (empty($photoPath) || empty($userPhoto)) {
    $photoPath = $app_base . '/file/logo-coldbloud.png';
}

// Récupérer la destination
$destination = $_SESSION['login_redirect'] ?? $app_base . accueilDuCompte($user);
/* Repli si la destination ne repond pas (plus bas, fallbackDestination) : la
   liste des etudiants (Mes notes pour un etudiant), pages les plus anciennes
   et les plus sures. */
unset($_SESSION['login_redirect']);

// Déterminer l'heure pour le message de salutation (ajusté côté client)
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Bonjour";
    $timeEmoji = "☀️";
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = "Bon après-midi";
    $timeEmoji = "🌤️";
} elseif ($hour >= 18 && $hour < 22) {
    $greeting = "Bonsoir";
    $timeEmoji = "🌅";
} else {
    $greeting = "Bonne nuit";
    $timeEmoji = "🌙";
}

// Déterminer le message selon le type d'utilisateur
$welcomeTitle = $greeting;
if ($userLevel === 1) {
    $roleText = "Superadmin";
    $personalMessage = "Votre tableau de bord superadmin est prêt.";
    $tip = "Vous avez un accès complet à toutes les fonctionnalités du système.";
} elseif ($userLevel === 2) {
    $roleText = "Administrateur";
    $personalMessage = "Votre tableau de bord administrateur est prêt.";
    $tip = "Vous avez accès à toutes les fonctionnalités du système.";
} elseif ($userLevel === 3) {
    $roleText = "Registraire";
    $personalMessage = "Prêt à gérer les inscriptions et les dossiers étudiants.";
    $tip = "N'oubliez pas de vérifier les nouvelles demandes d'inscription.";
} elseif ($userLevel === 4) {
    $roleText = "Comptabilité";
    $personalMessage = "Votre espace comptabilité est en cours de chargement.";
    $tip = "Consultez les finances et les paiements des étudiants.";
} elseif ($userLevel === 5) {
    $roleText = "Média";
    $personalMessage = "Votre espace média est prêt.";
    $tip = "Gérez les contenus médias et les communications.";
} elseif ($userLevel === 6) {
    $roleText = "Chef de mention";
    $personalMessage = "Votre espace de gestion de mention est prêt.";
    $tip = "Consultez les étudiants et les cours de votre mention.";
} elseif ($userLevel === 7 || $userType === 'teacher') {
    $roleText = "Enseignant";
    $personalMessage = "Vos cours et étudiants vous attendent.";
    $tip = "Consultez vos classes pour voir les dernières mises à jour.";
} elseif ($userLevel === 8 || $userType === 'student') {
    $roleText = "Étudiant";
    $personalMessage = "Votre espace étudiant est en cours de chargement.";
    $tip = "Vérifiez vos notes et votre emploi du temps.";
} elseif ($userLevel === 9 || $userType === 'work_education') {
    $roleText = "Work Education";
    $personalMessage = "La liste des étudiants de la session est prête.";
    $tip = "Entrée enregistre une tâche et passe à l'étudiant suivant.";
} else {
    $roleText = "Utilisateur";
    $personalMessage = "Préparation de votre espace de travail...";
    $tip = "Bienvenue sur Infinit Registrar.";
}

// Date formatée (compatible PHP 8+, sans strftime déprécié)
$currentDate = '';
if (class_exists('IntlDateFormatter')) {
    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $formatter->setPattern('EEEE dd MMMM yyyy');
    $formatted = $formatter->format(new DateTime());
    if ($formatted !== false) {
        $currentDate = (string)$formatted;
    }
}

// Fallback manuel si l'extension intl n'est pas disponible
if ($currentDate === '') {
    $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $currentDate = $days[(int)date('w')] . ' ' . date('d') . ' ' . $months[(int)date('m')] . ' ' . date('Y');
}

$fullName = trim($userFirstName . ' ' . $userName);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chargement - Infinit Registrar</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/file/logo-coldbloud.png" type="image/x-icon">
    <?php include __DIR__ . '/../init/pwa.php'; /* application installable */ ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/src/css/ciel.css?v=<?= @filemtime(__DIR__ . '/css/ciel.css') ?>">
    <style>
        /* Le seuil de l'application : le meme ciel que la connexion et l'au
           revoir (src/css/ciel.css), aux couleurs du sceau — bleu Zurcher
           #222e65, or #88724b. */
        :root {
            --or: #88724b;
            --or-clair: #ceb480;
            --texte: #eceef6;
            --texte-2: #b3b9d6;
            --texte-3: #8890b5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #07091a;
            color: var(--texte);
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        body.slide-out .loading-container { animation: sortie .45s ease forwards; }
        @keyframes sortie { to { opacity: 0; transform: translateY(-12px); } }

        /* Rideau de sortie : le bleu Zurcher et le logo. */
        .transition-overlay {
            position: fixed;
            inset: 0;
            z-index: 20;
            display: grid;
            place-items: center;
            background: radial-gradient(ellipse at 50% 60%, #2b387f 0%, #222e65 45%, #12162c 100%);
            opacity: 0;
            visibility: hidden;
            transition: opacity .35s ease, visibility .35s;
        }
        .transition-overlay.active { opacity: 1; visibility: visible; }
        .transition-overlay .logo {
            width: 84px;
            height: 84px;
            object-fit: contain;
            transform: scale(.8);
            transition: transform .45s cubic-bezier(.2, .7, .2, 1);
        }
        .transition-overlay.active .logo { transform: scale(1); }

        /* Container principal */
        .loading-container {
            position: relative;
            z-index: 1;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            text-align: center;
        }

        .apparait { animation: apparait .9s cubic-bezier(.2, .7, .2, 1) both; }
        @keyframes apparait {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Photo de profil : l'anneau or du sceau, un halo qui respire. */
        .profile-photo {
            width: 104px;
            height: 104px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 26px;
            box-shadow: 0 0 0 3px #07091a, 0 0 0 4px rgba(206, 180, 128, .85), 0 0 40px rgba(90, 111, 218, .30);
            animation: apparait .9s both, halo 2.4s ease-in-out 1s infinite;
        }
        @keyframes halo {
            0%, 100% { box-shadow: 0 0 0 3px #07091a, 0 0 0 4px rgba(206, 180, 128, .85), 0 0 40px rgba(90, 111, 218, .30); }
            50%      { box-shadow: 0 0 0 3px #07091a, 0 0 0 4px rgba(206, 180, 128, 1),   0 0 64px rgba(90, 111, 218, .55); }
        }

        .welcome-text {
            font-size: 16px;
            letter-spacing: .04em;
            color: var(--texte-2);
            margin-bottom: 10px;
            animation-delay: .15s;
        }

        /* Le nom : le titre fin et espace de la reference. */
        .user-name {
            font-weight: 300;
            font-size: clamp(26px, 4.8vw, 46px);
            line-height: 1.15;
            letter-spacing: .12em;
            padding-left: .12em;
            text-transform: uppercase;
            background: linear-gradient(180deg, #ffffff 30%, #9eacf1 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 16px;
            animation-delay: .3s;
        }

        .user-role {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--or-clair);
            margin-bottom: 22px;
            animation-delay: .45s;
        }
        .user-role::before,
        .user-role::after {
            content: "";
            flex: 0 0 28px;
            height: 1px;
            margin: 0 14px;
            background: rgba(206, 180, 128, .55);
        }

        .personal-message {
            max-width: 520px;
            font-size: 15px;
            color: var(--texte-2);
            animation-delay: .6s;
        }

        /* Chevrons : l'or qui avance. */
        .arrow-loader {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 30px;
            animation: apparait .9s .75s both;
        }
        .arrow-loader span {
            display: block;
            width: 14px;
            height: 14px;
            border-bottom: 3px solid var(--or-clair);
            border-right: 3px solid var(--or-clair);
            transform: rotate(-45deg);
            animation: chevron 1.5s ease-in-out infinite;
        }
        .arrow-loader span:nth-child(2) { animation-delay: .15s; }
        .arrow-loader span:nth-child(3) { animation-delay: .3s; }
        .arrow-loader span:nth-child(4) { animation-delay: .45s; }
        .arrow-loader span:nth-child(5) { animation-delay: .6s; }
        .arrow-loader span:nth-child(6) { animation-delay: .75s; }
        @keyframes chevron {
            0%, 100% { opacity: .18; transform: rotate(-45deg) scale(.8); }
            50%      { opacity: 1;   transform: rotate(-45deg) scale(1.08); }
        }

        .loading-text {
            min-height: 22px;
            margin-top: 22px;
            font-size: 14.5px;
            color: var(--texte);
            transition: opacity .3s ease;
            animation-delay: .85s;
        }

        .tip-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 560px;
            margin-top: 22px;
            padding: 9px 16px;
            border-radius: 999px;
            border: 1px solid rgba(179, 185, 214, .18);
            background: rgba(18, 22, 44, .55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            font-size: 13px;
            color: var(--texte-2);
            animation-delay: .95s;
        }

        .current-date {
            margin-top: 18px;
            font-size: 12.5px;
            letter-spacing: .04em;
            color: var(--texte-3);
            animation-delay: 1.05s;
        }
        .current-date::first-letter { text-transform: uppercase; }

        .progress-container {
            width: min(280px, 70vw);
            height: 3px;
            margin-top: 22px;
            border-radius: 999px;
            background: rgba(179, 185, 214, .15);
            overflow: hidden;
            animation-delay: 1.1s;
        }
        .progress-bar {
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #88724b, #ceb480);
            animation: remplit 4.6s cubic-bezier(.4, 0, .2, 1) .4s forwards;
        }
        @keyframes remplit { to { width: 100%; } }

        @media (max-width: 600px) {
            .user-role::before, .user-role::after { flex-basis: 16px; margin: 0 10px; }
            .tip-text { border-radius: 14px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .apparait, .profile-photo, .arrow-loader, .arrow-loader span, body.slide-out .loading-container { animation: none; }
            .progress-bar { animation-duration: .01s; }
        }
    </style>
</head>
<body>
    <!-- Le ciel étoilé (src/css/ciel.css) -->
    <div class="ciel" data-ciel aria-hidden="true"></div>

    <!-- Container principal -->
    <div class="loading-container">
        <!-- Photo de profil -->
        <img src="<?= htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Photo de profil" class="profile-photo" width="104" height="104" onerror="this.onerror=null; this.src='<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/file/logo-coldbloud.png'">

        <!-- Message de bienvenue -->
        <div class="welcome-text apparait"><?= $timeEmoji ?> <?= $welcomeTitle ?></div>
        <div class="user-name apparait"><?= $fullName ?></div>
        <div class="user-role apparait"><?= $roleText ?></div>

        <!-- Message personnel -->
        <div class="personal-message apparait"><?= $personalMessage ?></div>

        <!-- Animation de flèches -->
        <div class="arrow-loader">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <!-- Texte de chargement animé -->
        <div class="loading-text apparait" id="loadingText" aria-live="polite">Connexion sécurisée établie...</div>

        <!-- Conseil du jour -->
        <div class="tip-text apparait">💡 <?= $tip ?></div>

        <!-- Date actuelle -->
        <div class="current-date apparait"><?= $currentDate ?></div>

        <!-- Barre de progression -->
        <div class="progress-container apparait" role="progressbar" aria-label="Chargement">
            <div class="progress-bar"></div>
        </div>
    </div>

    <!-- Overlay de transition -->
    <div class="transition-overlay" id="transitionOverlay">
        <img src="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/file/logo-coldbloud.png" alt="" class="logo">
    </div>

    <script src="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/src/js/ciel.js?v=<?= @filemtime(__DIR__ . '/js/ciel.js') ?>"></script>
    <script>
        // Messages de chargement qui changent
        const messages = [
            "Connexion sécurisée établie...",
            "Chargement de vos données...",
            <?= json_encode(html_entity_decode($personalMessage, ENT_QUOTES, 'UTF-8'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
            "Préparation de l'interface...",
            "Presque terminé...",
            <?= json_encode('Bienvenue ' . html_entity_decode($userFirstName, ENT_QUOTES, 'UTF-8') . ' ! 🎉', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
        ];
        
        let messageIndex = 0;
        const loadingText = document.getElementById('loadingText');
        
        const messageInterval = setInterval(function() {
            messageIndex++;
            if (messageIndex < messages.length) {
                loadingText.style.opacity = '0';
                setTimeout(function() {
                    loadingText.textContent = messages[messageIndex];
                    loadingText.style.opacity = '1';
                }, 300);
            }
        }, 800);

        const destination = <?= json_encode($destination, JSON_UNESCAPED_SLASHES) ?>;
        const fallbackDestination = <?= json_encode(($userLevel === 8 || $userType === 'student') ? ($app_base . '/student/notes') : ($app_base . '/etudiants'), JSON_UNESCAPED_SLASHES) ?>;

        // Fonction de transition slide-left
        function slideOutAndRedirect() {
            clearInterval(messageInterval);
            
            // Activer l'overlay de transition
            const overlay = document.getElementById('transitionOverlay');
            overlay.classList.add('active');
            
            // Appliquer le slide-out au body
            setTimeout(function() {
                document.body.classList.add('slide-out');
            }, 300);
            
            // Rediriger après l'animation
            setTimeout(function() {
                window.location.replace(destination);
            }, 800);
        }

        // Redirection automatique après l'animation
        setTimeout(function() {
            fetch(destination, { credentials: 'same-origin' })
                .then(function(response) {
                    if (!response.ok) {
                        return fallbackDestination;
                    }
                    return destination;
                })
                .catch(function() {
                    return fallbackDestination;
                })
                .then(function(target) {
                    clearInterval(messageInterval);
                    setTimeout(function() {
                        window.location.replace(target);
                    }, 800);
                });
        }, 5000);

        // Salutation selon l'heure locale
        (function() {
            const h = new Date().getHours();
            let greeting, icon;
            if (h >= 5 && h < 12)       { greeting = 'Bonjour';        icon = '☀️'; }
            else if (h >= 12 && h < 18) { greeting = 'Bon après-midi'; icon = '🌤️'; }
            else if (h >= 18 && h < 22) { greeting = 'Bonsoir';        icon = '🌅'; }
            else                        { greeting = 'Bonne nuit';     icon = '🌙'; }
            const el = document.querySelector('.welcome-text');
            if (el) el.textContent = icon + ' ' + greeting;
        })();
    </script>
</body>
</html>
