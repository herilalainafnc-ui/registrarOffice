<?php
// Cette page porte un formulaire : elle ne doit jamais etre servie depuis
// un cache. Une copie perimee reafficherait un formulaire dont l action ou
// le script ont change, et la soumission se perdrait sans message d erreur.
// no-cache impose une revalidation (peu couteuse : 304 si rien n a bouge).
if (!headers_sent()) {
    header("Cache-Control: no-cache, must-revalidate");
}

// Configuration email
$destinataire = "registrar@zurcher.edu.mg";
$success = false;
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $subject_value = htmlspecialchars(trim($_POST['subject'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));
    
    // Mapping des sujets
    $subjects = [
        'admission' => 'Renseignements sur les admissions',
        'formation' => 'Questions sur les formations',
        'frais' => 'Frais de scolarité et bourses',
        'campus' => 'Vie sur le campus',
        'partenariat' => 'Proposition de partenariat',
        'autre' => 'Autre demande'
    ];
    $subject_text = $subjects[$subject_value] ?? 'Demande de contact';
    
    // Validation
    if (empty($name) || empty($email) || empty($subject_value) || empty($message)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } else {
        // Construction du message
        $email_subject = "[Contact UAZ] " . $subject_text;
        
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a365d; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #1a365d; }
                .footer { text-align: center; padding: 15px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Nouveau Message de Contact</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <span class='label'>Nom :</span> {$name}
                    </div>
                    <div class='field'>
                        <span class='label'>Email :</span> {$email}
                    </div>
                    <div class='field'>
                        <span class='label'>Téléphone :</span> " . ($phone ?: 'Non renseigné') . "
                    </div>
                    <div class='field'>
                        <span class='label'>Sujet :</span> {$subject_text}
                    </div>
                    <div class='field'>
                        <span class='label'>Message :</span><br>
                        " . nl2br($message) . "
                    </div>
                </div>
                <div class='footer'>
                    Message envoyé depuis le site web de l'UAZ - " . date('d/m/Y à H:i') . "
                </div>
            </div>
        </body>
        </html>";
        
        // Headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$name} <{$email}>\r\n";
        $headers .= "Reply-To: {$email}\r\n";
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
    <?php include 'init-landing.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Université Adventiste Zurcher</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/pages.css?v=<?=@filemtime(__DIR__."/pages.css")?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/effets-gsap.css?v=<?=@filemtime(__DIR__."/effets-gsap.css")?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/vitrine-clair.css?v=<?=@filemtime(__DIR__."/vitrine-clair.css")?>">
</head>
<body class="vitrine-claire">
    <!-- Rideau de transition et curseur (voir landing/pages/effets-gsap.js). -->
    <div class="rideau-page" aria-hidden="true"><span class="rideau-page__bande"></span><span class="rideau-page__bande"></span><span class="rideau-page__bande"></span></div>
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <?php /* Contenu partage avec la vitrine en page unique. */ include '_contenu-contact.php'; ?>

    <?php include 'footer.php'; ?>

    <script src="<?=$app_base?>/landing/pages/pages.js?v=<?=@filemtime(__DIR__."/pages.js")?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="<?=$app_base?>/landing/pages/effets-gsap.js?v=<?=@filemtime(__DIR__."/effets-gsap.js")?>"></script>
</body>
</html>
