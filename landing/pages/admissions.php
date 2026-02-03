<?php
// Configuration email
$destinataire = "registraroffice@zurcher.edu.mg";
$success = false;
$error = "";

// Traitement du formulaire de pré-inscription
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données
    $fullname = htmlspecialchars(trim($_POST['fullname'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $mention_value = htmlspecialchars(trim($_POST['mention'] ?? ''));
    $bac_year = htmlspecialchars(trim($_POST['bac_year'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));
    
    // Mapping des mentions
    $mentions = [
        'theologie' => 'Théologie',
        'gestion' => 'Gestion',
        'informatique' => 'Informatique',
        'sciences-infirmieres' => 'Sciences Infirmières',
        'education' => 'Éducation',
        'communication' => 'Communication',
        'etudes-anglophones' => 'Études Anglophones',
        'droit' => 'Droit'
    ];
    $mention_text = $mentions[$mention_value] ?? 'Non spécifié';
    
    // Validation
    if (empty($fullname) || empty($phone) || empty($mention_value)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } else {
        // Construction du message
        $email_subject = "[Pré-inscription UAZ] Nouvelle demande - " . $mention_text;
        
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a365d; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .field { margin-bottom: 15px; padding: 10px; background: white; border-radius: 5px; }
                .label { font-weight: bold; color: #1a365d; display: block; margin-bottom: 5px; }
                .value { color: #333; }
                .highlight { background: #e8f4fd; border-left: 4px solid #1a365d; }
                .footer { text-align: center; padding: 15px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>📚 Nouvelle Demande de Pré-inscription</h2>
                    <p style='margin: 0;'>Année académique 2025-2026</p>
                </div>
                <div class='content'>
                    <div class='field highlight'>
                        <span class='label'>Mention souhaitée :</span>
                        <span class='value' style='font-size: 18px; font-weight: bold;'>{$mention_text}</span>
                    </div>
                    <div class='field'>
                        <span class='label'>Nom complet :</span>
                        <span class='value'>{$fullname}</span>
                    </div>
                    <div class='field'>
                        <span class='label'>Téléphone :</span>
                        <span class='value'>{$phone}</span>
                    </div>
                    <div class='field'>
                        <span class='label'>Email :</span>
                        <span class='value'>" . ($email ?: 'Non renseigné') . "</span>
                    </div>
                    <div class='field'>
                        <span class='label'>Année d'obtention du BAC :</span>
                        <span class='value'>" . ($bac_year ?: 'Non renseigné') . "</span>
                    </div>
                    <div class='field'>
                        <span class='label'>Motivation :</span>
                        <span class='value'>" . ($message ? nl2br($message) : 'Non renseignée') . "</span>
                    </div>
                </div>
                <div class='footer'>
                    Demande reçue le " . date('d/m/Y à H:i') . " depuis le site web de l'UAZ
                </div>
            </div>
        </body>
        </html>";
        
        // Headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        if (!empty($email)) {
            $headers .= "From: {$fullname} <{$email}>\r\n";
            $headers .= "Reply-To: {$email}\r\n";
        } else {
            $headers .= "From: UAZ Website <noreply@zurcher.edu.mg>\r\n";
        }
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Envoi du mail
        if (mail($destinataire, $email_subject, $email_body, $headers)) {
            $success = true;
        } else {
            $error = "Une erreur s'est produite lors de l'envoi. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admissions - Université Adventiste Zurcher</title>
    <link rel="shortcut icon" href="../../file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./pages.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Inscriptions Ouvertes 2025-2026
            </div>
            <h1 class="hero-title">Rejoignez l'UAZ</h1>
            <p class="hero-subtitle">
                Commencez votre parcours académique dans une université d'excellence. 
                Découvrez les étapes pour intégrer notre communauté étudiante.
            </p>
        </div>
    </section>

    <!-- Timeline Section -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Processus d'Admission</span>
            <h2 class="section-title">Les Étapes pour Rejoindre l'UAZ</h2>
            <p class="section-subtitle">
                Un processus simple et transparent pour votre inscription
            </p>
        </div>

        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 1</span>
                    <h3 class="timeline-title">Candidature en ligne</h3>
                    <p class="timeline-text">
                        Remplissez le formulaire de candidature en ligne avec vos informations personnelles 
                        et vos choix de formation.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 2</span>
                    <h3 class="timeline-title">Dossier Académique</h3>
                    <p class="timeline-text">
                        Rassemblez et soumettez vos documents académiques : relevés de notes, 
                        diplômes, certificats et pièces d'identité.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 3</span>
                    <h3 class="timeline-title">Test d'Entrée</h3>
                    <p class="timeline-text">
                        Passez les tests d'évaluation selon votre filière choisie. 
                        Les dates sont communiquées après validation du dossier.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 4</span>
                    <h3 class="timeline-title">Entretien</h3>
                    <p class="timeline-text">
                        Un entretien avec le comité d'admission pour évaluer votre motivation 
                        et votre projet professionnel.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 5</span>
                    <h3 class="timeline-title">Résultats & Inscription</h3>
                    <p class="timeline-text">
                        Recevez les résultats de votre candidature et procédez à l'inscription 
                        définitive avec le paiement des frais.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Requirements Section -->
    <section class="section section-alt">
        <div class="section-header">
            <span class="section-label">Prérequis</span>
            <h2 class="section-title">Conditions d'Admission</h2>
            <p class="section-subtitle">
                Les documents et critères nécessaires pour candidater
            </p>
        </div>

        <div class="cards-grid">
            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <h3 class="card-title">Documents Requis</h3>
                <p class="card-text">
                    • Certificat de fin d'études secondaires (BACC ou équivalent)<br>
                    • Relevés de notes des 3 dernières années<br>
                    • Copie de la carte d'identité nationale<br>
                    • 4 photos d'identité récentes<br>
                    • Certificat médical<br>
                    • Lettre de recommandation
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="card-title">Critères d'Éligibilité</h3>
                <p class="card-text">
                    • Être titulaire du Baccalauréat ou équivalent<br>
                    • Bonne moralité et engagement éthique<br>
                    • Capacité à respecter les valeurs adventistes<br>
                    • Motivation pour le domaine d'études choisi
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="card-title">Frais de Scolarité</h3>
                <p class="card-text">
                    • Frais d'inscription : 250 000 Ar<br>
                    • Frais de scolarité annuels : variables selon la filière<br>
                    • Possibilité de paiement échelonné<br>
                    • Remboursements disponibles pour les étudiants méritants<br>
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                </div>
                <h3 class="card-title">Calendrier</h3>
                <p class="card-text">
                    • Ouverture des candidatures : Dès maintenant<br>
                    • Tests d'entrée : 09 et 10 mars 2026<br>
                    • Inscription et réinscription : 09 - 13 mars 2026<br>
                    • Prochaine Rentrée : 16 mars 2026
                </p>
            </div>
        </div>
    </section>

    <!-- Application Form Section -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Inscription</span>
            <h2 class="section-title">Demande de Pré-inscription</h2>
            <p class="section-subtitle">
                Remplissez ce formulaire pour commencer votre candidature
            </p>
        </div>

        <div class="form-container">
            <?php if ($success): ?>
            <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
                <strong style="font-size: 18px;">Pré-inscription envoyée avec succès !</strong><br><br>
                Merci pour votre intérêt pour l'Université Adventiste Zurcher.<br>
                Notre équipe vous contactera prochainement pour la suite du processus.
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;">
                <strong>✗ Erreur :</strong> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                <div class="form-group">
                    <label class="form-label" for="fullname">Nom complet *</label>
                    <input type="text" id="fullname" name="fullname" class="form-input" placeholder="Votre nom et prénom" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Adresse email </label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="votre.email@exemple.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Numéro de téléphone *</label>
                    <input type="tel" id="phone" name="phone" class="form-input" placeholder="+261 34 XX XXX XX" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="mention">Mention souhaitée *</label>
                    <select id="mention" name="mention" class="form-select" required>
                        <option value="">Sélectionnez une mention</option>
                        <option value="theologie">Théologie</option>
                        <option value="gestion">Gestion</option>
                        <option value="informatique">Informatique</option>
                        <option value="sciences-infirmieres">Sciences Infirmières</option>
                        <option value="education">Éducation</option>
                        <option value="communication">Communication</option>
                        <option value="etudes-anglophones">Études Anglophones</option>
                        <option value="droit">Droit</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bac_year">Année d'obtention du BAC</label>
                    <input type="text" id="bac_year" name="bac_year" class="form-input" placeholder="Ex: 2025" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">Motivation</label>
                    <textarea id="message" name="message" class="form-textarea" placeholder="Décrivez brièvement vos motivations pour rejoindre l'UAZ..."></textarea>
                </div>

                <button type="submit" class="submit-btn">Soumettre ma pré-inscription</button>
            </form>
        </div>
    </section>

    <?php include 'footer.php'; ?>

    <script src="./pages.js"></script>
</body>
</html>
