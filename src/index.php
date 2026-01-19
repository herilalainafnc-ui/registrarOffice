<?php 
/**
 * Page de connexion - Infinit Registrar
 * Mise à jour avec middleware sécurisé
 */

require('../data/backdb.php');
require('../data/middleware.php');

// Initialiser le middleware avec la connexion DB
initMiddleware($dtb);

// Variable pour les erreurs
$loginError = false;

// Si déjà connecté, rediriger vers l'accueil
if (isLoggedIn()) {
    header('Location: ./accueil.php');
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
            header('Location: ./accueil.php');
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/bootstrap-icons.min.css" rel="stylesheet">
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
            <form method="post" action="">
                <?= csrf_field() ?>
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
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>