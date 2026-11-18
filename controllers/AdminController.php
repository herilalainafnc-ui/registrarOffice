<?php
/**
 * AdminController - Pages d'administration
 * Routes: /settings, /accounts/create, /news, /login-locations, /deans-list, etc.
 */
class AdminController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function settings() {
        // Autoriser les utilisateurs administratifs, y compris la comptabilité.
        $this->requireLevelAccess(4);
        $this->renderSrc('settings.php');
    }

    public function qrGenerator() {
        // Le generateur de QR Code a quitte les parametres pour la section
        // « Application » du menu (DashboardController::qrGenerator) :
        // l'ancienne adresse, deja en favori chez certains, y renvoie.
        $this->redirectTo('/qr-generator', 301);
    }

    public function reglement() {
        // Bulletin academique (reglement des etudes) : consultable par tout
        // utilisateur connecte, sans restriction de niveau.
        $this->renderSrc('reglement.php');
    }

    public function reglementDownload() {
        // Sert le .docx d'origine du bulletin academique. Le fichier vit hors
        // racine servie (data/tools est bloque par .htaccess) : il ne peut etre
        // recupere que par ici, donc uniquement par un utilisateur connecte.
        // Nom de fichier volontairement sans accent : evite les surprises
        // d'encodage entre Windows, Linux et git. Le nom accentue est restitue
        // au telechargement via Content-Disposition.
        $fichier = dirname(__DIR__) . '/data/tools/bulletin-academique-licence.docx';

        if (!is_file($fichier)) {
            http_response_code(404);
            exit('Document introuvable.');
        }

        $nom = 'Bulletin academique - LICENCE.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        // Nom simple pour les vieux clients, nom accentue en RFC 5987 pour les autres
        header('Content-Disposition: attachment; filename="' . $nom . '"; '
             . "filename*=UTF-8''" . rawurlencode('Bulletin académique - LICENCE.docx'));
        header('Content-Length: ' . filesize($fichier));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');

        while (ob_get_level()) { ob_end_clean(); }
        readfile($fichier);
        exit;
    }

    public function diplomeSettings() {
        // Parametres du diplome : signatures, arretes, calage d'impression.
        $this->requireLevelAccess(3);
        $this->renderSrc('settings.diplome.php');
    }

    public function vitrineSettings() {
        /* SUPERADMIN UNIQUEMENT. Ce qui se modifie ici part directement sur le
           site public : une date de rentree, un numero de telephone, la phrase
           d'accroche que lit tout candidat. Le niveau 3 l'ouvrait aux six
           registraires et aux administrateurs — trop large pour une page qui
           parle au nom de l'universite. */
        $this->requireLevelAccess(1);
        $this->renderSrc('settings.vitrine.php');
    }

    public function mentionsSettings() {
        /* SUPERADMIN UNIQUEMENT. Le sigle d'une mention sert de cle dans cinq
           tables et son libelle rattache les fiches etudiantes : plus de quatre
           mille lignes en dependent, sans aucune contrainte de cle etrangere
           pour les proteger. Un renommage maladroit ne casse rien visiblement,
           il rend seulement des inscriptions impossibles des semaines plus
           tard. Meme verrou que la page vitrine, pour la meme raison : la
           portee du degat depasse la page. */
        $this->requireLevelAccess(1);
        $this->renderSrc('settings.mentions.php');
    }

    public function espaceEtudiantSettings() {
        /* SUPERADMIN UNIQUEMENT. Publier les notes d'une session avant l'aval
           du Sénat les divulgue d'un coup a tous les etudiants concernes. */
        $this->requireLevelAccess(1);
        $this->renderSrc('settings.espace-etudiant.php');
    }

    public function logsSettings() {
        /* SUPERADMIN UNIQUEMENT. Le journal d'activité montre, pour tout le
           projet, les données envoyées par chaque utilisateur et les valeurs
           modifiées en base. Même verrou dans app/audit.liste.php et
           app/audit.detail.php, joignables hors du routeur. */
        $this->requireLevelAccess(1);
        $this->renderSrc('settings.logs.php');
    }

    /* Registrariat (niveaux 1 a 3) : la boite no-reply. Enseignants et chefs de
       mention (demande du 19/10/2026) : l'envoi depuis l'adresse Google de leur
       connexion, vers des personnes ou leurs etudiants ; la page ne leur montre
       rien d'autre (src/bulk-email.php, data/courriel.enseignant.php).
       Qui enseigne ne se sait qu'avec la base, que le middleware n'a pas encore
       ici : la page refuse elle-meme les autres comptes (403). */
    public function bulkEmail() {
        $this->refuserEtudiant();
        $this->renderSrc('bulk-email.php');
    }

    /* SUPERADMIN UNIQUEMENT (demande du 08/10/2026) : les adresses de la base
       face aux comptes Google, et la creation des comptes manquants. Meme
       verrou dans app/adresses-google.api.php, joignable hors du routeur. */
    public function adressesGoogle() {
        $this->requireLevelAccess(1);
        $this->renderSrc('adresses-google.php');
    }

    public function createAccount() {
        $this->requireLevelAccess(1);
        $this->renderSrc('creat.account.php');
    }

    public function news() {
        $this->requireLevelAccess(3);
        $this->renderSrc('admin.actus.php');
    }

    public function loginLocations() {
        $this->requireLevelAccess(3);
        $this->renderSrc('login-locations.php');
    }

    public function deansList() {
        $this->refuserEtudiant(); // moyennes de tous les etudiants, sessions non publiees comprises
        $this->refuserEnseignant(); // classement de toute l'universite, pas de ses seuls cours
        $this->renderSrc('deans-list.php');
    }

    public function topStudents() {
        $this->refuserEtudiant(); // moyennes de tous les etudiants, sessions non publiees comprises
        $this->refuserEnseignant();
        $this->renderSrc('meilleurs-etudiants.php');
    }

    public function sessionRankings() {
        $this->refuserEtudiant(); // moyennes de tous les etudiants, sessions non publiees comprises
        $this->refuserEnseignant();
        $this->renderSrc('session-rankings.php');
    }

    public function sessions() {
        $this->requireLevelAccess(1);
        $this->renderSrc('sessions.php');
    }

    public function myAccount() {
        $this->renderSrc('my.account.php');
    }

    public function professor() {
        /* Fiche d'un enseignant, avec ses cours et ses coordonnees. Un
           enseignant n'a pas a consulter celle de ses collegues. */
        $this->refuserEtudiant();
        $this->refuserEnseignant();
        $this->renderSrc('prof.php');
    }

    public function course() {
        /* La fiche de cours porte l'onglet « Notes », qui ecrit directement
           dans t_2023_notes. Un enseignant y contournerait le circuit de
           validation : ses notes passent par « Remise des notes ». */
        $this->refuserEtudiant();
        $this->refuserEnseignant();
        $this->renderSrc('cours.php');
    }

    public function reel() {
        $this->renderSrc('reel.php');
    }

    public function button() {
        $this->renderSrc('button.php');
    }

    public function verify() {
        $this->render('verifier/index.php', ROOT_DIR . '/verifier');
    }

    public function remiseNotes() {
        /* Validation des remises de notes des enseignants. Par privilege et non
           par niveau : le niveau 3 rassemble les registraires ET un
           administrateur, et c'est le registrariat qui arbitre les notes.
           requireAnyPrivileges laisse toujours passer le superadmin. */
        $this->requireAnyPrivileges(['superadmin', 'registrar'],
            "Seuls le superadmin et le registrariat valident les remises de notes.");
        $this->renderSrc('remise-notes.php');
    }

    public function presence() {
        /* Assiduite (§474, §476) : le registraire recoit les depassements des
           15 % et suit les retards des enseignants. Memes droits que la
           validation des remises. */
        $this->requireAnyPrivileges(['superadmin', 'registrar'],
            "Seuls le superadmin et le registrariat suivent l'assiduité.");
        $this->renderSrc('presence.php');
    }

    public function manageCoursesSessions() {
        /* Superadmin et registraires. C'est le registrariat qui decide des cours
           offerts par semestre : la page lui etait fermee, alors que son propre
           commentaire annoncait deja « admins et registraires seulement ».

           Par privilege et non par niveau : le niveau 3 rassemble les six
           registraires ET un administrateur, que personne n'a nomme ici. */
        $this->requireAnyPrivileges(['superadmin', 'registrar']);
        $this->renderSrc('manage-courses-sessions.php');
    }
}
