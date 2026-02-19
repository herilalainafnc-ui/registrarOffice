<?php
/**
 * Page d'au revoir après déconnexion
 * Affiche une animation de salut avec un message personnalisé
 */

// Récupérer les infos de l'utilisateur déconnecté depuis les paramètres GET
$userName = isset($_GET['name']) ? urldecode($_GET['name']) : '';
$userFirstName = isset($_GET['firstname']) ? urldecode($_GET['firstname']) : '';
$userPhoto = isset($_GET['photo']) ? urldecode($_GET['photo']) : '';
$userType = isset($_GET['type']) ? urldecode($_GET['type']) : 'staff';

// Nom complet
$fullName = trim($userFirstName . ' ' . $userName);
if (empty($fullName)) {
    $fullName = 'Utilisateur';
}

// Chemin de la photo selon le type d'utilisateur
$photoPath = '../file/logo-coldbloud.png';
if (!empty($userPhoto)) {
    if ($userType === 'student') {
        $photoPath = '../app/photosetudiants/' . $userPhoto;
    } elseif ($userType === 'teacher') {
        $photoPath = '../app/photosenseignants/' . $userPhoto;
    } else {
        $photoPath = '../app/photosuser/' . $userPhoto;
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Au revoir - Infinit Registrar</title>
    <link rel="shortcut icon" href="../file/logo-coldbloud.png" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /* Fond animé avec dégradé */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(-45deg, #0f172a, #1a365d, #0f172a, #1e3a5f);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            z-index: 0;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Photo floue en arrière-plan */
        .background-photo {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?= $photoPath ?>');
            background-size: cover;
            background-position: center;
            filter: blur(30px) brightness(0.2);
            transform: scale(1.2);
            z-index: 1;
            opacity: 0.5;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, transparent 0%, rgba(15, 23, 42, 0.9) 100%);
            z-index: 2;
        }

        /* Container principal */
        .goodbye-container {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 40px;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Main qui salue */
        .waving-hand {
            font-size: 80px;
            margin-bottom: 20px;
            animation: wave 1.5s ease-in-out infinite;
            transform-origin: 70% 70%;
            display: inline-block;
        }

        @keyframes wave {
            0% { transform: rotate(0deg); }
            10% { transform: rotate(14deg); }
            20% { transform: rotate(-8deg); }
            30% { transform: rotate(14deg); }
            40% { transform: rotate(-4deg); }
            50% { transform: rotate(10deg); }
            60% { transform: rotate(0deg); }
            100% { transform: rotate(0deg); }
        }

        /* Photo de profil */
        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(14, 165, 233, 0.6);
            box-shadow: 0 0 30px rgba(14, 165, 233, 0.3);
            margin-bottom: 25px;
            animation: scaleIn 0.6s ease-out 0.3s both;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Texte d'au revoir */
        .goodbye-title {
            color: #ffffff;
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
            animation: fadeIn 1s ease-out 0.5s both;
        }

        .user-name {
            color: #0ea5e9;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            animation: fadeIn 1s ease-out 0.7s both;
        }

        .goodbye-message {
            color: #94a3b8;
            font-size: 18px;
            margin-bottom: 10px;
            animation: fadeIn 1s ease-out 0.9s both;
        }

        .time-message {
            color: #64748b;
            font-size: 14px;
            font-style: italic;
            margin-bottom: 40px;
            animation: fadeIn 1s ease-out 1.1s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Étoiles animées */
        .stars-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
            pointer-events: none;
            overflow: hidden;
        }

        .star {
            position: absolute;
            width: 3px;
            height: 3px;
            background: #fff;
            border-radius: 50%;
            animation: twinkle 2s infinite;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Cœurs flottants */
        .hearts-container {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 4;
            pointer-events: none;
            overflow: hidden;
        }

        .heart {
            position: absolute;
            bottom: -50px;
            font-size: 20px;
            animation: floatUp 6s ease-in infinite;
            opacity: 0;
        }

        @keyframes floatUp {
            0% {
                opacity: 0;
                transform: translateY(0) rotate(0deg);
            }
            10% {
                opacity: 0.8;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                opacity: 0;
                transform: translateY(-100vh) rotate(360deg);
            }
        }

        /* Bouton de retour */
        .return-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 20px rgba(14, 165, 233, 0.4);
            transition: all 0.3s ease;
            animation: fadeIn 1s ease-out 1.3s both;
        }

        .return-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 30px rgba(14, 165, 233, 0.5);
        }

        .return-btn i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .return-btn:hover i {
            transform: translateX(-5px);
        }

        /* Compteur de redirection */
        .redirect-counter {
            color: #475569;
            font-size: 12px;
            margin-top: 25px;
            animation: fadeIn 1s ease-out 1.5s both;
        }

        .redirect-counter span {
            color: #0ea5e9;
            font-weight: 600;
        }

        /* Message de remerciement */
        .thank-you {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 13px;
            margin-top: 30px;
            padding: 10px 20px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(14, 165, 233, 0.2);
            animation: fadeIn 1s ease-out 1.7s both;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .goodbye-title {
                font-size: 32px;
            }
            .user-name {
                font-size: 20px;
            }
            .waving-hand {
                font-size: 60px;
            }
        }

        /* Transition de sortie */
        body.fade-out {
            animation: fadeOut 0.5s ease forwards;
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: scale(0.95);
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Fond animé -->
    <div class="animated-bg"></div>
    <div class="background-photo"></div>
    <div class="overlay"></div>

    <!-- Étoiles -->
    <div class="stars-container" id="starsContainer"></div>

    <!-- Cœurs flottants -->
    <div class="hearts-container" id="heartsContainer"></div>

    <!-- Container principal -->
    <div class="goodbye-container">
        <!-- Main qui salue -->
        <div class="waving-hand">👋</div>

        <!-- Photo de profil -->
        <img src="<?= $photoPath ?>" alt="Photo de profil" class="profile-photo" onerror="this.src='../file/logo-coldbloud.png'">

        <!-- Message d'au revoir -->
        <div class="goodbye-title"><?= $randomMessage ?></div>
        <div class="user-name"><?= htmlspecialchars($fullName) ?></div>
        <div class="goodbye-message"><?= $timeMessage ?></div>
        <div class="time-message">Vous avez été déconnecté avec succès.</div>

        <!-- Bouton de retour -->
        <a href="<?=$app_base?>/login" class="return-btn">
            <i class="bi bi-arrow-left-circle"></i>
            Se reconnecter
        </a>

        <!-- Compteur de redirection -->
        <div class="redirect-counter">
            Redirection automatique dans <span id="countdown">5</span> secondes...
        </div>

        <!-- Message de remerciement -->
        <div class="thank-you">
            <i class="bi bi-heart-fill" style="color: #ef4444;"></i>
            Merci d'avoir utilisé Infinit Registrar
        </div>
    </div>

    <script>
        // Générer les étoiles
        function createStars() {
            const container = document.getElementById('starsContainer');
            for (let i = 0; i < 50; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 2 + 's';
                star.style.animationDuration = (Math.random() * 2 + 1) + 's';
                container.appendChild(star);
            }
        }

        // Générer les cœurs flottants
        function createHearts() {
            const container = document.getElementById('heartsContainer');
            const emojis = ['💙', '✨', '⭐', '💫', '🌟'];
            
            setInterval(() => {
                const heart = document.createElement('div');
                heart.className = 'heart';
                heart.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                heart.style.left = Math.random() * 100 + '%';
                heart.style.animationDuration = (Math.random() * 3 + 4) + 's';
                heart.style.fontSize = (Math.random() * 15 + 15) + 'px';
                container.appendChild(heart);
                
                // Supprimer après l'animation
                setTimeout(() => heart.remove(), 6000);
            }, 500);
        }

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
                    window.location.href = '<?php echo (defined("APP_BASE") ? APP_BASE : ""); ?>/login';
                }, 500);
            }
        }, 1000);

        // Initialisation
        createStars();
        createHearts();

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
