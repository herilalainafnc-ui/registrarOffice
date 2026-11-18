<?php
/**
 * Page d'au revoir après déconnexion
 * Affiche une animation de salut avec un message personnalisé
 *
 * Style : un ciel étoilé en parallaxe (codepen.io/sarazond/pen/LYGbwj) aux
 * couleurs du sceau — voir src/css/ciel.css.
 */

// Récupérer les infos de l'utilisateur déconnecté depuis les paramètres GET
$userName = isset($_GET['name']) ? urldecode($_GET['name']) : '';
$userFirstName = isset($_GET['firstname']) ? urldecode($_GET['firstname']) : '';
$userPhoto = isset($_GET['photo']) ? urldecode($_GET['photo']) : '';
$userType = isset($_GET['type']) ? urldecode($_GET['type']) : 'staff';

/* La photo vient de l'adresse : n'importe qui peut la fabriquer. On n'en
   garde qu'un nom de fichier sage, jamais un chemin ni du balisage — elle
   etait auparavant recopiee telle quelle dans la page. */
$userPhoto = basename(str_replace('\\', '/', (string) $userPhoto));
if ($userPhoto === '.' || $userPhoto === '..' || !preg_match('~^[^<>"\\\\/\x00-\x1F]{1,160}$~u', $userPhoto)) {
    $userPhoto = '';
}
if (!in_array($userType, ['student', 'teacher', 'staff'], true)) {
    $userType = 'staff';
}

// Nom complet
$fullName = trim($userFirstName . ' ' . $userName);
if (empty($fullName)) {
    $fullName = 'Utilisateur';
}

$app_base = $app_base ?? (defined('APP_BASE') ? APP_BASE : '');

// Chemin de la photo selon le type d'utilisateur
$photoPath = $app_base . '/file/logo-coldbloud.png';
if (!empty($userPhoto)) {
    if ($userType === 'student') {
        $photoPath = $app_base . '/app/photosetudiants/' . rawurlencode($userPhoto);
    } elseif ($userType === 'teacher') {
        $photoPath = $app_base . '/app/photosenseignants/' . rawurlencode($userPhoto);
    } else {
        $photoPath = $app_base . '/app/photosuser/' . rawurlencode($userPhoto);
    }
}

// Messages d'au revoir aléatoires
$goodbyeMessages = [
    "À bientôt !",
    "À très vite !",
    "Bonne continuation !",
    "À la prochaine !",
    "Prenez soin de vous !"
];
$randomMessage = $goodbyeMessages[array_rand($goodbyeMessages)];

// Déterminer le message selon l'heure (ajusté côté client)
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $timeMessage = "Passez une excellente journée !";
} elseif ($hour >= 12 && $hour < 18) {
    $timeMessage = "Bon après-midi !";
} elseif ($hour >= 18 && $hour < 22) {
    $timeMessage = "Passez une bonne soirée !";
} else {
    $timeMessage = "Bonne nuit et à demain !";
}

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Au revoir - Infinit Registrar</title>
    <link rel="shortcut icon" href="<?= $e($app_base) ?>/file/logo-coldbloud.png" type="image/x-icon">
    <?php include __DIR__ . '/../init/pwa.php'; /* application installable */ ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $e($app_base) ?>/src/css/ciel.css?v=<?= @filemtime(__DIR__ . '/css/ciel.css') ?>">
    <style>
        /* Couleurs du sceau : bleu Zurcher #222e65, or #88724b. */
        :root {
            --or: #88724b;
            --or-clair: #ceb480;
            --texte: #eceef6;
            --texte-2: #b3b9d6;
            --texte-3: #8890b5;
            --accent: #9eacf1;
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

        /* Container principal */
        .goodbye-container {
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

        /* Main qui salue */
        .waving-hand {
            font-size: 52px;
            line-height: 1;
            margin-bottom: 18px;
            display: inline-block;
            transform-origin: 70% 70%;
            animation: apparait .9s both, wave 2.2s ease-in-out .6s 2;
        }
        @keyframes wave {
            0%, 60%, 100% { transform: rotate(0deg); }
            10% { transform: rotate(14deg); }
            20% { transform: rotate(-8deg); }
            30% { transform: rotate(14deg); }
            40% { transform: rotate(-4deg); }
            50% { transform: rotate(10deg); }
        }

        /* Photo de profil : l'anneau or du sceau */
        .profile-photo {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 30px;
            box-shadow:
                0 0 0 3px #07091a,
                0 0 0 4px rgba(206, 180, 128, .85),
                0 0 48px rgba(90, 111, 218, .35);
            animation-delay: .1s;
        }

        /* Le titre de la référence : fin, très espacé, en dégradé. */
        .goodbye-title {
            font-weight: 300;
            font-size: clamp(30px, 6.2vw, 58px);
            line-height: 1.15;
            letter-spacing: .22em;
            padding-left: .22em; /* compense l'espacement du dernier caractère */
            text-transform: uppercase;
            background: linear-gradient(180deg, #ffffff 30%, #9eacf1 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 18px;
            animation-delay: .25s;
        }

        .user-name {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 100%;
            font-size: clamp(15px, 2.2vw, 18px);
            font-weight: 500;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--or-clair);
            margin-bottom: 26px;
            animation-delay: .4s;
        }
        .user-name::before,
        .user-name::after {
            content: "";
            flex: 0 0 28px;
            height: 1px;
            margin: 0 14px;
            background: rgba(206, 180, 128, .55);
        }

        .goodbye-message {
            font-size: 17px;
            color: var(--texte-2);
            margin-bottom: 6px;
            animation-delay: .55s;
        }

        .time-message {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 14px;
            color: var(--texte-3);
            margin-bottom: 34px;
            animation-delay: .65s;
        }
        .time-message i { color: #6ee7b7; }

        /* Bouton de retour */
        .return-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 28px;
            border-radius: 999px;
            border: 1px solid rgba(206, 180, 128, .55);
            background: rgba(136, 114, 75, .14);
            color: var(--texte);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: .02em;
            transition: background-color .2s ease, border-color .2s ease;
            animation-delay: .8s;
        }
        .return-btn:hover { background: var(--or); border-color: var(--or); }
        .return-btn:focus-visible { outline: 2px solid var(--or-clair); outline-offset: 3px; }
        .return-btn i { font-size: 18px; transition: transform .2s ease; }
        .return-btn:hover i { transform: translateX(-4px); }

        /* Compteur de redirection */
        .redirect-counter {
            font-size: 12.5px;
            color: var(--texte-3);
            margin-top: 22px;
            animation-delay: .9s;
        }
        .redirect-counter span {
            color: var(--or-clair);
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        /* Message de remerciement */
        .thank-you {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 34px;
            padding: 9px 18px;
            border-radius: 999px;
            border: 1px solid rgba(179, 185, 214, .18);
            background: rgba(18, 22, 44, .55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            font-size: 13px;
            color: var(--texte-2);
            animation-delay: 1s;
        }
        .thank-you i { color: var(--or-clair); }

        /* Transition de sortie */
        body.fade-out .goodbye-container { animation: sortie .5s ease forwards; }
        @keyframes sortie { to { opacity: 0; transform: translateY(-12px); } }

        @media (max-width: 600px) {
            .waving-hand { font-size: 44px; }
            .user-name::before, .user-name::after { flex-basis: 16px; margin: 0 10px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .apparait, .waving-hand, body.fade-out .goodbye-container { animation: none; }
        }
    </style>
</head>
<body>
    <!-- Le ciel étoilé -->
    <div class="ciel" data-ciel aria-hidden="true"></div>

    <!-- Container principal -->
    <main class="goodbye-container">
        <!-- Main qui salue -->
        <div class="waving-hand apparait" aria-hidden="true">👋</div>

        <!-- Photo de profil -->
        <img src="<?= $e($photoPath) ?>" alt="Photo de profil" class="profile-photo apparait" width="92" height="92"
             onerror="this.onerror=null; this.src='<?= $e($app_base) ?>/file/logo-coldbloud.png'">

        <!-- Message d'au revoir -->
        <h1 class="goodbye-title apparait"><?= $e($randomMessage) ?></h1>
        <div class="user-name apparait"><?= $e($fullName) ?></div>
        <div class="goodbye-message apparait"><?= $e($timeMessage) ?></div>
        <div class="time-message apparait"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Vous avez été déconnecté avec succès.</div>

        <!-- Bouton de retour -->
        <a href="<?= $e($app_base) ?>/login" class="return-btn apparait">
            <i class="bi bi-arrow-left-circle" aria-hidden="true"></i>
            Se reconnecter
        </a>

        <!-- Compteur de redirection -->
        <div class="redirect-counter apparait">
            Redirection automatique dans <span id="countdown">5</span> secondes...
        </div>

        <!-- Message de remerciement -->
        <div class="thank-you apparait">
            <i class="bi bi-heart-fill" aria-hidden="true"></i>
            Merci d'avoir utilisé Infinit Registrar
        </div>
    </main>

    <script src="<?= $e($app_base) ?>/src/js/ciel.js?v=<?= @filemtime(__DIR__ . '/js/ciel.js') ?>"></script>
    <script>
        // Compteur de redirection
        let countdown = 5;
        const countdownEl = document.getElementById('countdown');

        const countdownInterval = setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(countdownInterval);
                document.body.classList.add('fade-out');
                setTimeout(() => {
                    window.location.href = <?= json_encode($app_base . '/login', JSON_UNESCAPED_SLASHES) ?>;
                }, 500);
            }
        }, 1000);

        // Message selon l'heure locale
        (function() {
            const h = new Date().getHours();
            let msg;
            if (h >= 5 && h < 12)       { msg = 'Passez une excellente journée !'; }
            else if (h >= 12 && h < 18) { msg = 'Bon après-midi !'; }
            else if (h >= 18 && h < 22) { msg = 'Passez une bonne soirée !'; }
            else                        { msg = 'Bonne nuit et à demain !'; }
            const el = document.querySelector('.goodbye-message');
            if (el) el.textContent = msg;
        })();
    </script>
</body>
</html>
