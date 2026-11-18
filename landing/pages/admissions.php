<?php
// Cette page porte un formulaire : elle ne doit jamais etre servie depuis
// un cache. Une copie perimee reafficherait un formulaire dont l action ou
// le script ont change, et la soumission se perdrait sans message d erreur.
// no-cache impose une revalidation (peu couteuse : 304 si rien n a bouge).
if (!headers_sent()) {
    header("Cache-Control: no-cache, must-revalidate");
}

// Racine de l'application. Le controleur frontal (index.php) la definit quand
// la page est servie par /admissions ; l'ancien chemin /landing/pages/admissions
// charge ce fichier directement, sans lui, et le premier require tombait en
// erreur fatale (500). Meme repli que init/head.php.
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

// Configuration email
$destinataire = "registrar@zurcher.edu.mg";
$success = false;
$error = "";
/* Champs mis en cause par le refus, s'il y en a. Renseigne par la
   validation, renvoye au script de la page pour la surbrillance. */
$champsErreur = [];
// Vrai apres la redirection qui suit un envoi reussi : declenche la modale.
$envoye  = !empty($_GET['envoye']);

/**
 * Vrai si le formulaire a ete envoye par le script de la page, sans
 * rechargement.
 *
 * Detection autonome : data/guard.ajax.php n'est pas charge sur ce chemin —
 * c'est une page publique. On reprend son critere principal, l'en-tete pose
 * par fetch() dans _contenu-admissions.php.
 */
function preadmEnvoiSansRechargement() : bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/**
 * La mention accueille-t-elle des candidatures ?
 *
 * Une mention peut etre fermee depuis /settings/mentions : elle cesse alors
 * d'etre proposee dans l'application, mais le <select> de ce formulaire est
 * ecrit en dur et continue de l'afficher. Ce controle est donc le seul qui
 * empeche une candidature d'aboutir pour une mention qui ne recrute pas.
 *
 * Ouverte par defaut en cas de doute — base injoignable, migration pas encore
 * jouee, mention absente de la table : refuser une candidature sur une erreur
 * technique serait plus grave que d'en accepter une de trop, qui reste
 * rattrapable a la main.
 *
 * @param string $cle Cle publique de la mention (« informatique »...).
 */
function preadmMentionOuverte(string $cle) : bool
{
    $sigles = parcoursMentionVersSigle();
    if (!isset($sigles[$cle])) {
        return true; // mention inconnue : ce n'est pas ce controle qui la refuse
    }

    try {
        require_once ROOT_DIR . '/data/config.php';
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
        require_once ROOT_DIR . '/data/mentions.php';
        mentionsOuvertureDisponible($pdo);

        return mentionEstOuverte($pdo, $sigles[$cle]);
    } catch (Throwable $e) {
        error_log('preadmMentionOuverte: ' . $e->getMessage());

        return true;
    }
}

/* ---------------------------------------------------------------------------
   Parcours proposes au candidat.
   Ils sont lus dans `filiere_parcours`, la meme table que le formulaire
   d'inscription du personnel. Les figer en dur ici avait un defaut serieux :
   les libelles divergeaient de ceux de la base (« Droit public » contre
   « Droit Public », « Genie logiciel et SI » contre « Systemes d'information
   et genie logiciel »...), si bien que le parcours choisi par le candidat ne
   correspondait a aucune option lors de la conversion en etudiant, et la
   pre-selection echouait sans rien signaler.

   La cle publique de la mention (utilisee par le <select>) est reliee au
   sigle de departement de la table.
   --------------------------------------------------------------------------- */
require_once ROOT_DIR . '/data/parcours.php';
require_once ROOT_DIR . '/data/email.verification.php'; // adresse du candidat : son domaine recoit-il les emails ?
$mentionVersSigle     = parcoursMentionVersSigle();
$parcours_par_mention = parcoursParMention();

// Traitement du formulaire de pré-inscription
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données.
    // Ces variables-ci sont echappees : elles servent au corps HTML de
    // l'email. Les valeurs brutes destinees a la base sont reprises plus bas.
    $nom      = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $prenom   = htmlspecialchars(trim($_POST['prenom'] ?? ''));
    $fullname = trim($nom . ' ' . $prenom);
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $mention_value = htmlspecialchars(trim($_POST['mention'] ?? ''));
    $bac_year = htmlspecialchars(trim($_POST['bac_year'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    /* Mentions habilitees : les mentions OUVERTES de /settings/mentions. Cette
       liste etait ecrite en dur ici, en plus du <select> du formulaire et de
       data/parcours.php — trois endroits a tenir d'accord, donc trois endroits
       ou l'un pouvait accepter ce qu'un autre ne proposait pas. */
    $mentions = parcoursLibellesMentions();
    $mention_text = $mentions[$mention_value] ?? 'Non spécifié';

    // $parcours_par_mention est deja renseigne depuis `filiere_parcours` en
    // tete de fichier. Le navigateur remplit la liste deroulante a partir de
    // la meme source, mais rien n'empeche d'envoyer un couple fantaisiste :
    // le serveur revalide donc l'appartenance du parcours a sa mention.
    $parcours_saisi = trim($_POST['parcours'] ?? '');
    $diplome_saisi  = trim($_POST['diplome_vise'] ?? '');
    $naiss_date     = trim($_POST['date_naissance'] ?? '');
    $naiss_annee    = trim($_POST['annee_naissance'] ?? '');

    /* Validation.

       Chaque refus nomme desormais LE CHAMP en cause, et pas seulement sa
       raison. Le script de la page s'en sert pour le mettre en surbrillance :
       sans cela il aurait fallu deviner le champ en comparant le texte du
       message, ce qui casse au premier mot reformule.

       Cela couvre en particulier des cas que le navigateur ne peut pas voir :
       la date de naissance n'est pas « required » en HTML — l'annee seule
       suffit — c'est donc uniquement ici qu'on sait laquelle des deux
       manque. */
    /* La motivation est obligatoire (« Motivation * » dans le formulaire) :
       le required du navigateur ne suffit pas, un envoi sans le script ou
       fabrique a la main passait sans elle. */
    $champsVides = [];
    foreach (['nom' => $nom, 'prenom' => $prenom, 'phone' => $phone, 'email' => $email,
              'mention' => $mention_value, 'parcours' => $parcours_saisi,
              'genre' => (string) ($_POST['genre'] ?? ''), 'message' => $message] as $champ => $valeur) {
        if ($valeur === '') { $champsVides[] = $champ; }
    }

    if ($champsVides) {
        $error = "Veuillez remplir tous les champs obligatoires.";
        $champsErreur = $champsVides;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
        $champsErreur = ['email'];
    } elseif (($verifEmail = emailVerifier(trim((string) ($_POST['email'] ?? ''))))['bloquant']) {
        /* Domaine inexistant ou qui ne recoit pas d'emails : le mail
           d'acceptation se perdrait (data/email.verification.php). Le script
           de la page le dit deja pendant la frappe ; ceci vaut sans lui. */
        $error = $verifEmail['message'];
        $champsErreur = ['email'];
    } elseif (!isset($mentions[$mention_value])) {
        $error = "La mention choisie n'est pas proposée.";
        $champsErreur = ['mention'];
    } elseif (!preadmMentionOuverte($mention_value)) {
        /* Mention fermee aux inscriptions depuis /settings/mentions. Le
           <select> ne la propose plus, et $mentions ne la contient plus : ce
           controle est donc devenu une seconde barriere. Elle reste utile —
           une mention peut etre fermee entre l'affichage du formulaire et son
           envoi, et une requete forgee ne passe par aucun <select>. */
        $error = "La mention « " . $mentions[$mention_value]
            . " » n'accueille pas de nouvelles candidatures pour le moment.";
        $champsErreur = ['mention'];
    } elseif (!in_array($diplome_saisi, ['licence', 'master'], true)) {
        $error = "Veuillez indiquer le diplôme visé.";
        $champsErreur = ['diplome_vise'];
    } elseif (!parcoursMentionAccepteDiplome($mention_value, $diplome_saisi)) {
        /* Une mention ne prepare un diplome que si l'un de ses parcours porte
           le cycle correspondant, regle dans /settings/mentions. Le formulaire
           filtre deja, mais le filtre est en JavaScript : une requete forgee,
           ou un formulaire soumis avant que le script n'ait pris la main,
           arrive ici. C'est donc ici que la regle tient.

           AVANT le controle du parcours, et non apres : le couple
           diplome/mention est l'erreur la plus fondamentale, et c'est lui qui
           determine quels parcours ont un sens. Place ensuite, il ne se
           declenchait jamais — on recevait « le parcours ne correspond pas a
           la mention » pour un Master de Theologie, ce qui envoyait corriger
           le mauvais champ. */
        $error = "La mention « " . $mentions[$mention_value] . " » ne propose pas de "
            . parcoursNomDiplome($diplome_saisi) . ".";
        $champsErreur = ['mention', 'diplome_vise'];
    } elseif (!in_array($parcours_saisi,
                        parcoursDeMentionPourDiplome($mention_value, $diplome_saisi), true)) {
        /* Le parcours est verifie POUR LE DIPLOME demande : un parcours de
           licence envoye avec un Master doit etre refuse, sans quoi le cycle
           regle dans /settings/mentions ne vaudrait que pour l'affichage. */
        $error = "Le parcours choisi ne correspond pas à la mention et au diplôme.";
        $champsErreur = ['parcours'];
    } elseif (!in_array($_POST['genre'] ?? '', ['M', 'F'], true)) {
        $error = "Veuillez indiquer votre genre.";
        $champsErreur = ['genre'];
    } elseif ($naiss_date === '' && $naiss_annee === '') {
        // L'un ou l'autre suffit : beaucoup de candidats ne connaissent que
        // leur annee de naissance.
        $error = "Indiquez votre date de naissance, ou à défaut votre année de naissance.";
        $champsErreur = ['date_naissance', 'annee_naissance'];
    } elseif ($naiss_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $naiss_date)) {
        $error = "La date de naissance n'est pas valide.";
        $champsErreur = ['date_naissance'];
    } elseif ($naiss_annee !== '' && !preg_match('/^\d{4}$/', $naiss_annee)) {
        $error = "L'année de naissance doit comporter quatre chiffres.";
        $champsErreur = ['annee_naissance'];
    } elseif ($bac_year !== '' && !preg_match('/^\d{4}$/', $bac_year)) {
        $error = "L'année d'obtention du BACC doit comporter quatre chiffres.";
        $champsErreur = ['bac_year'];
    } else {
        // ---------------------------------------------------------------
        // Enregistrement en base : la candidature devient la source de
        // verite. L'email n'est plus qu'une notification (voir plus bas) :
        // une candidature ne doit jamais etre perdue parce que mail() a
        // echoue (frequent en local ou serveur mal configure).
        //
        // On stocke les valeurs BRUTES (trim seul) via une requete
        // preparee ; l'echappement se fait a l'affichage, cote admin. Les
        // variables htmlspecialchars ci-dessus restent, elles, dediees au
        // corps HTML de l'email.
        // ---------------------------------------------------------------
        // Un champ laisse vide vaut NULL en base, pas chaine vide : la
        // distinction « non renseigne » / « renseigne vide » compte pour le
        // bureau des admissions.
        $ouNull = static function (string $champ) {
            $v = trim($_POST[$champ] ?? '');
            return $v !== '' ? $v : null;
        };

        $raw_nom     = trim($_POST['nom'] ?? '');
        $raw_prenom  = trim($_POST['prenom'] ?? '');
        $raw_email   = trim($_POST['email'] ?? '');
        $raw_tel     = trim($_POST['phone'] ?? '');
        $raw_mention = trim($_POST['mention'] ?? '');
        $raw_bac     = trim($_POST['bac_year'] ?? '');
        $raw_message = trim($_POST['message'] ?? '');
        $candidat_ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // `nom_complet` reste alimente : la liste d'instruction et la
        // conversion en etudiant s'en servent encore.
        $raw_complet = trim($raw_nom . ' ' . $raw_prenom);

        try {
            require_once ROOT_DIR . '/data/config.php';
            require_once ROOT_DIR . '/data/audit.php'; // la candidature apparaît au journal d'activité
            $dtb = auditConnexion(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
            $ins = $dtb->prepare(
                'INSERT INTO candidat
                    (nom_complet, nom, prenom,
                     date_naissance, annee_naissance, lieu_naissance, genre, etat_civil,
                     pays_origine, nationalite, ville, adresse,
                     nom_pere, nom_mere, contact_parent, tuteur, contact_tuteur,
                     email, telephone,
                     mention, mention_libelle, diplome_vise, parcours,
                     annee_bacc, message, ip)
                 VALUES (?, ?, ?,  ?, ?, ?, ?, ?,  ?, ?, ?, ?,  ?, ?, ?, ?, ?,  ?, ?,  ?, ?, ?, ?,  ?, ?, ?)'
            );
            $ins->execute([
                $raw_complet,
                $raw_nom,
                $raw_prenom,

                $ouNull('date_naissance'),
                $ouNull('annee_naissance'),
                $ouNull('lieu_naissance'),
                $ouNull('genre'),
                $ouNull('etat_civil'),

                $ouNull('pays_origine'),
                $ouNull('nationalite'),
                $ouNull('ville'),
                $ouNull('adresse'),

                $ouNull('nom_pere'),
                $ouNull('nom_mere'),
                $ouNull('contact_parent'),
                $ouNull('tuteur'),
                $ouNull('contact_tuteur'),

                $raw_email,
                $raw_tel,

                $raw_mention,
                $mention_text,
                $ouNull('diplome_vise'),
                $ouNull('parcours'),

                $raw_bac !== '' ? $raw_bac : null,
                $raw_message !== '' ? $raw_message : null,
                $candidat_ip,
            ]);
            $success = true; // candidature enregistree : c'est le vrai succes
        } catch (Throwable $e) {
            error_log('Pre-admission: echec enregistrement candidat: ' . $e->getMessage());
            $error = "Une erreur s'est produite lors de l'enregistrement. Veuillez réessayer.";
        }

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
                    <p style='margin: 0;'>Année académique 2026-2027</p>
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
        //
        // INJECTION D'EN-TETES : le nom du candidat vient du formulaire
        // PUBLIC, donc de n'importe qui. htmlspecialchars ne touche pas aux
        // retours chariot : un nom contenant « \r\nBcc: cible@exemple.com »
        // ajoutait ses propres en-tetes, transformant le serveur de
        // l'universite en relais de hameconnage — les messages partaient
        // reellement de zurcher.edu.mg, donc conformes SPF et DKIM.
        //
        // L'adresse, elle, est deja contrainte par FILTER_VALIDATE_EMAIL, qui
        // rejette tout caractere de controle.
        $nomExpediteur = preg_replace('/[\r\n]+/', ' ', $fullname);
        // Les guillemets fermeraient la partie « display name » de l'en-tete.
        $nomExpediteur = trim(str_replace(['"', '<', '>'], '', $nomExpediteur));
        $nomExpediteur = mb_substr($nomExpediteur, 0, 80);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        if (!empty($email)) {
            $headers .= "From: \"{$nomExpediteur}\" <{$email}>\r\n";
            $headers .= "Reply-To: {$email}\r\n";
        } else {
            $headers .= "From: UAZ Website <noreply@zurcher.edu.mg>\r\n";
        }
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Notification par email au bureau des admissions — best-effort.
        // Un echec d'envoi n'annule pas la candidature deja enregistree ;
        // il est seulement journalise.
        if ($success) {
            if (!@mail($destinataire, $email_subject, $email_body, $headers)) {
                error_log('Pre-admission: candidature #enregistree mais notification email non envoyee');
            }
        }

        /* Redirection apres POST : sans elle, un rafraichissement renvoyait le
           formulaire et creait une candidature en double. Le parametre sert
           aussi de signal pour afficher la modale.

           Ecartee pour un envoi sans rechargement : la page ne repart pas,
           donc aucun rafraichissement ne rejouera le POST, et un script ne
           peut pas suivre une 303 vers une page HTML sans perdre son etat. La
           reponse JSON est construite juste apres cette branche. */
        if ($success && !headers_sent() && !preadmEnvoiSansRechargement()) {
            $base = defined('APP_BASE') ? APP_BASE : '';
            // On revient la ou le formulaire a ete rempli. La valeur est
            // verifiee : seul un chemin local connu est accepte, pour qu un
            // champ forge ne puisse pas rediriger ailleurs.
            $origine = $_POST['origine'] ?? '';
            $permis  = ['/', $base . '/', $base . '/admissions', '/admissions'];
            $cible   = in_array($origine, $permis, true) ? $origine : $base . '/admissions';
            $ancre   = ($cible === $base . '/' || $cible === '/') ? '#admissions' : '';
            header('Location: ' . $cible . '?envoye=1' . $ancre, true, 303);
            exit;
        }
    }

    /* ------------------------------------------------------------------
       Envoi sans rechargement

       Place ICI, apres les deux branches de la validation, pour couvrir
       aussi bien le succes que l'erreur — et APRES la redirection, qui ne
       s'applique qu'a une soumission classique. Un script ne peut pas
       suivre une 303 vers une page HTML sans perdre son etat : il attend
       une reponse, pas une nouvelle page.

       On s'arrete avant tout HTML : ce fichier rend une page entiere
       ensuite, et elle n'a rien a faire dans une reponse JSON.
       ------------------------------------------------------------------ */
    if (preadmEnvoiSansRechargement()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode([
            'ok' => (bool) $success,
            'message' => $success
                ? 'Votre pré-inscription a bien été enregistrée.'
                : ($error !== '' ? $error : "L'envoi n'a pas abouti."),
            // Noms des champs a signaler a l'ecran (attribut name).
            'champs' => $champsErreur,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'init-landing.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admissions - Université Adventiste Zurcher</title>
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

    <?php /* Contenu partage avec la vitrine en page unique. */ include '_contenu-admissions.php'; ?>

    <?php include 'footer.php'; ?>

    <?php /* Toujours incluse, masquee par defaut : voir vitrine.php. */ ?>
    <?php include '_modale-preinscription.php'; ?>

    <script src="<?=$app_base?>/landing/pages/pages.js?v=<?=@filemtime(__DIR__."/pages.js")?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="<?=$app_base?>/landing/pages/effets-gsap.js?v=<?=@filemtime(__DIR__."/effets-gsap.js")?>"></script>
</body>
</html>
