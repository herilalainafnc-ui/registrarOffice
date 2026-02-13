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

// Si déjà connecté, rediriger vers l'accueil
if (isLoggedIn()) {
    header('Location: ./accueil');
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
            
            // Redirection vers la page de chargement avec les infos utilisateur
            $user = Middleware::getCurrentUser();
            $userLevel = (int)($user['level'] ?? 4);
            $userType = $user['user_type'] ?? 'staff';
            
            // Déterminer la destination finale
            if ($userLevel === 8 || $userType === 'student' || ($user['privilege'] ?? '') === 'student') {
                $destination = './student.home';
            } elseif ($userLevel === 7 || $userType === 'teacher' || ($user['privilege'] ?? '') === 'teacher') {
                $destination = './teacher.dashboard';
            } else {
                $destination = './accueil';
            }
            
            // Stocker la destination dans la session
            $_SESSION['login_redirect'] = $destination;
            
            // Rediriger vers la page de chargement
            header('Location: ./loading');
            exit;
        } else {
            // Log de tentative échouée
            Middleware::logSecurityEvent('login_failed', ['pseudo' => $pseudo]);
            $loginError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Infinit Registrar</title>
    <link rel="shortcut icon" href="../file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            --primary-color: #0ea5e9;
            --secondary-color: #1e293b;
            --accent-color: #f97316;
            --background-dark: #0f172a;
            --card-bg: rgba(15, 23, 42, 0.8);
            --input-bg: rgba(30, 41, 59, 0.8);
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --border-color: rgba(148, 163, 184, 0.2);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background: linear-gradient(-45deg, var(--background-dark), #1a1f35, #0f172a, #1e293b);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: moveBackground 20s infinite alternate;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -50%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: moveBackground 15s infinite alternate-reverse;
        }

        .container-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            z-index: 1;
            position: relative;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 48px 32px;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0, 0) scale(1); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .logo-container img {
            width: 50px;
            height: 50px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .logo-container h1 {
            font-size: 28px;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .login-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-light);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .input-wrapper input::placeholder {
            color: var(--text-muted);
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(30, 41, 59, 1);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .input-wrapper input:hover {
            border-color: var(--primary-color);
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            padding: 4px 8px;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .remember-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: -8px;
            margin-bottom: 24px;
        }

        .remember-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .remember-checkbox label {
            margin-bottom: 0;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-muted);
            text-transform: none;
            letter-spacing: normal;
        }

        .submit-btn {
            width: 100%;
            padding: 12px 24px;
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(14, 165, 233, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 600px) {
            .login-card {
                padding: 32px 24px;
            }

            .login-header {
                margin-bottom: 32px;
            }
        }

        /* Google Sign-In Styles */
        .login-divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            gap: 12px;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .login-divider span {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .google-btn {
            width: 100%;
            padding: 12px 24px;
            background: white;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: inherit;
        }

        .google-btn:hover {
            background: #f8fafc;
            border-color: #4285f4;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }

        .google-btn:active {
            transform: translateY(0);
        }

        .google-btn svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .google-btn .btn-text {
            font-size: 13px;
        }

        .google-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .google-btn.loading .btn-text {
            display: none;
        }

        .google-btn.loading::after {
            content: 'Connexion en cours...';
            font-size: 13px;
        }

        .google-info {
            text-align: center;
            margin-top: 8px;
            font-size: 11px;
            color: var(--text-muted);
        }

        .google-info i {
            margin-right: 4px;
        }

        .google-error {
            display: none;
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.4);
            color: #ff6b6b;
            padding: 10px 14px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
            text-align: center;
            animation: fadeIn 0.3s ease;
        }

        .google-error.show {
            display: block;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-wrapper">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-container">
                    <img src="../file/logo-coldbloud.png" alt="Logo Infinit Registrar">
                    <h1>Registrar</h1>
                </div>
                <p class="login-subtitle">Veuillez vous connecter pour continuer</p>
            </div>

            <?php if ($loginError): ?>
            <div class="alert alert-danger" style="background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #ff6b6b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                <i class="bi bi-exclamation-circle"></i> Identifiants incorrects. Veuillez réessayer.
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" action="" id="loginForm">
                <?= csrf_field() ?>
                <input type="hidden" name="gps_latitude" id="gps_latitude" value="">
                <input type="hidden" name="gps_longitude" id="gps_longitude" value="">
                <input type="hidden" name="gps_accuracy" id="gps_accuracy" value="">
                <input type="hidden" name="gps_denied" id="gps_denied" value="0">
                <div class="form-group">
                    <label for="pseudo">Nom d'utilisateur</label>
                    <div class="input-wrapper">
                        <input type="text" id="pseudo" name="infinit_pseudo" placeholder="Entrez votre identifiant" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="infinit_password" placeholder="Entrez votre mot de passe" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <span id="eye-icon">🙉</span>
                        </button>
                    </div>
                </div>

                <div class="remember-checkbox">
                    <input type="checkbox" id="remember" name="infinit_souvenir" value="1">
                    <label for="remember">Se souvenir de moi</label>
                </div>

                <button type="submit" class="submit-btn">Connexion</button>
            </form>

            <!-- Google Sign-In Section -->
            <div class="login-divider">
                <span>ou continuer avec</span>
            </div>

            <!-- Conteneur pour le vrai bouton Google (rendu par Google) -->
            <div id="g_id_signin" style="display:flex;justify-content:center;margin-top:4px;min-height:44px;"></div>

            <p class="google-info">
                <i class="bi bi-shield-check"></i> Connexion rapide avec votre compte Zurcher
            </p>

            <div class="google-error" id="googleError"></div>
        </div>
    </div>

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
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                eyeIcon.textContent = '🙉';
            }
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
            fetch('./api/google-auth', {
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