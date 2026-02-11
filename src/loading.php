<?php
/**
 * Page de chargement après connexion
 * Affiche une animation avec le message de bienvenue et la photo de profil
 */

require('../data/backdb.php');
require('../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ./index');
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
        $photoPath = '../app/photosetudiants/' . $userPhoto;
    } elseif ($userType === 'teacher' || $userLevel === 7) {
        // Photos des enseignants
        $photoPath = '../app/photosenseignants/' . $userPhoto;
    } else {
        // Photos des utilisateurs (staff)
        $photoPath = '../app/photosuser/' . $userPhoto;
    }
}

// Si pas de photo ou fichier inexistant, utiliser le logo
if (empty($photoPath) || empty($userPhoto)) {
    $photoPath = '../file/logo-coldbloud.png';
}

// Récupérer la destination
$destination = $_SESSION['login_redirect'] ?? './accueil';
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
} else {
    $roleText = "Utilisateur";
    $personalMessage = "Préparation de votre espace de travail...";
    $tip = "Bienvenue sur Infinit Registrar.";
}

// Date formatée
$currentDate = strftime('%A %d %B %Y');
// Fallback si strftime ne fonctionne pas
if (empty($currentDate)) {
    $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $currentDate = $days[date('w')] . ' ' . date('d') . ' ' . $months[(int)date('m')] . ' ' . date('Y');
}

$fullName = trim($userFirstName . ' ' . $userName);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chargement - Infinit Registrar</title>
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
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease;
        }

        body.slide-out {
            transform: translateX(-100%);
            opacity: 0;
        }

        /* Overlay de transition */
        .transition-overlay {
            position: fixed;
            top: 0;
            right: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            z-index: 1000;
            transition: right 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .transition-overlay.active {
            right: 0;
        }

        .transition-overlay .logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            opacity: 0;
            transition: opacity 0.3s ease 0.3s;
        }

        .transition-overlay.active .logo {
            opacity: 1;
        }

        /* Photo de profil en arrière-plan */
        .background-photo {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?= $photoPath ?>');
            background-size: cover;
            background-position: center;
            filter: blur(20px) brightness(0.3);
            transform: scale(1.1);
            z-index: 0;
            animation: zoomIn 3s ease-out forwards;
        }

        @keyframes zoomIn {
            from {
                transform: scale(1.3);
                filter: blur(30px) brightness(0.2);
            }
            to {
                transform: scale(1.1);
                filter: blur(20px) brightness(0.3);
            }
        }

        /* Overlay gradient */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, transparent 0%, rgba(15, 23, 42, 0.8) 100%);
            z-index: 1;
        }

        /* Container principal */
        .loading-container {
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

        /* Photo de profil circulaire */
        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(14, 165, 233, 0.8);
            box-shadow: 0 0 40px rgba(14, 165, 233, 0.4),
                        0 0 80px rgba(14, 165, 233, 0.2);
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 40px rgba(14, 165, 233, 0.4),
                            0 0 80px rgba(14, 165, 233, 0.2);
            }
            50% {
                box-shadow: 0 0 60px rgba(14, 165, 233, 0.6),
                            0 0 100px rgba(14, 165, 233, 0.3);
            }
        }

        /* Texte de bienvenue */
        .welcome-text {
            color: #94a3b8;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 10px;
            animation: fadeIn 1s ease-out 0.3s both;
        }

        .user-name {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
            animation: fadeIn 1s ease-out 0.5s both;
        }

        .user-role {
            color: #0ea5e9;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 50px;
            animation: fadeIn 1s ease-out 0.7s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Animation de flèches améliorée */
        .arrow-loader {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 6px;
            margin-top: 30px;
            animation: fadeIn 1s ease-out 1s both;
        }

        .arrow-loader span {
            display: block;
            width: 15px;
            height: 15px;
            border-bottom: 4px solid #0ea5e9;
            border-right: 4px solid #0ea5e9;
            transform: rotate(-45deg);
            animation: arrowAnimate 1.5s infinite ease-in-out;
            filter: drop-shadow(0 0 6px rgba(14, 165, 233, 0.6));
        }

        .arrow-loader span:nth-child(1) {
            animation-delay: 0s;
        }

        .arrow-loader span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .arrow-loader span:nth-child(3) {
            animation-delay: 0.3s;
        }

        .arrow-loader span:nth-child(4) {
            animation-delay: 0.45s;
        }

        .arrow-loader span:nth-child(5) {
            animation-delay: 0.6s;
        }

        .arrow-loader span:nth-child(6) {
            animation-delay: 0.75s;
        }

        @keyframes arrowAnimate {
            0%, 100% {
                opacity: 0.2;
                transform: rotate(-45deg) scale(0.8);
                border-color: #64748b;
                filter: drop-shadow(0 0 2px rgba(14, 165, 233, 0.2));
            }
            50% {
                opacity: 1;
                transform: rotate(-45deg) scale(1.1);
                border-color: #0ea5e9;
                filter: drop-shadow(0 0 10px rgba(14, 165, 233, 0.8));
            }
        }

        /* Message personnel */
        .personal-message {
            color: #94a3b8;
            font-size: 15px;
            margin-bottom: 30px;
            font-style: italic;
            animation: fadeIn 1s ease-out 0.9s both;
        }

        /* Message de chargement */
        .loading-text {
            color: #e2e8f0;
            font-size: 14px;
            margin-top: 35px;
            transition: opacity 0.3s ease;
            animation: fadeIn 1s ease-out 1.2s both;
        }

        /* Conseil du jour */
        .tip-text {
            color: #64748b;
            font-size: 12px;
            margin-top: 15px;
            padding: 8px 16px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(14, 165, 233, 0.2);
            animation: fadeIn 1s ease-out 1.5s both;
        }

        /* Date actuelle */
        .current-date {
            color: #475569;
            font-size: 11px;
            margin-top: 15px;
            text-transform: capitalize;
            animation: fadeIn 1s ease-out 1.7s both;
        }

        /* Barre de progression */
        .progress-container {
            width: 200px;
            height: 3px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            margin-top: 20px;
            overflow: hidden;
            animation: fadeIn 1s ease-out 1.4s both;
        }

        .progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #0ea5e9, #38bdf8);
            border-radius: 3px;
            animation: progressFill 5s ease-in-out forwards;
        }

        @keyframes progressFill {
            0% { width: 0%; }
            20% { width: 20%; }
            50% { width: 60%; }
            80% { width: 85%; }
            100% { width: 100%; }
        }

        /* Particules flottantes */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(14, 165, 233, 0.5);
            border-radius: 50%;
            animation: float 8s infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 600px) {
            .user-name {
                font-size: 24px;
            }
            .profile-photo {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Arrière-plan avec photo floue -->
    <div class="background-photo"></div>
    <div class="overlay"></div>

    <!-- Particules flottantes -->
    <div class="particles">
        <?php for ($i = 0; $i < 20; $i++): ?>
        <div class="particle" style="left: <?= rand(0, 100) ?>%; animation-delay: <?= rand(0, 8) ?>s; animation-duration: <?= rand(6, 12) ?>s;"></div>
        <?php endfor; ?>
    </div>

    <!-- Container principal -->
    <div class="loading-container">
        <!-- Photo de profil -->
        <img src="<?= $photoPath ?>" alt="Photo de profil" class="profile-photo" onerror="this.src='../file/logo-coldbloud.png'">

        <!-- Message de bienvenue -->
        <div class="welcome-text"><?= $timeEmoji ?> <?= $welcomeTitle ?></div>
        <div class="user-name"><?= $fullName ?></div>
        <div class="user-role"><?= $roleText ?></div>

        <!-- Message personnel -->
        <div class="personal-message"><?= $personalMessage ?></div>

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
        <div class="loading-text" id="loadingText">Connexion sécurisée établie...</div>

        <!-- Conseil du jour -->
        <div class="tip-text">💡 <?= $tip ?></div>

        <!-- Date actuelle -->
        <div class="current-date"><?= $currentDate ?></div>

        <!-- Barre de progression -->
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
    </div>

    <!-- Overlay de transition -->
    <div class="transition-overlay" id="transitionOverlay">
        <img src="../file/logo-coldbloud.png" alt="Logo" class="logo">
    </div>

    <script>
        // Messages de chargement qui changent
        const messages = [
            "Connexion sécurisée établie...",
            "Chargement de vos données...",
            "<?= $personalMessage ?>",
            "Préparation de l'interface...",
            "Presque terminé...",
            "Bienvenue <?= $userFirstName ?> ! 🎉"
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
                window.location.href = '<?= $destination ?>';
            }, 800);
        }

        // Redirection automatique après l'animation
        setTimeout(slideOutAndRedirect, 5000);

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
