<?php
/**
 * StudentController - Pages étudiants
 * Routes: /student, /student/home, /student/notes, /student/info, etc.
 */
class StudentController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function show() {
        /* Dossier complet d'un etudiant : etat civil, finances, releve, bulletin,
           historique des notes. Ses onglets n'etaient fermes aux niveaux > 3 que
           par une classe CSS — « ?page=histNotes » les rouvrait tous.

           Un enseignant n'a pas a y entrer : ce qui le concerne, ce sont les
           etudiants inscrits a ses cours, qu'il retrouve nommes dans sa feuille
           de remise. */
        $this->refuserEnseignant("Le dossier d'un etudiant est reserve au personnel. "
            . "Les etudiants de vos cours figurent dans votre feuille de remise des notes.");
        $this->renderSrc('student.php');
    }

    public function home() {
        try {
            $this->renderSrc('student.home.php');
        } catch (Throwable $e) {
            die('Fatal Error in student.home.php on Line ' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    public function dashboard() {
        $this->renderSrc('student.dashboard.php');
    }

    public function info() {
        $this->renderSrc('student.info.php');
    }

    /** Mon assiduite : les presences relevees par les enseignants (§472 a §476). */
    public function presence() {
        $this->renderSrc('student.presence.php');
    }

    /** Mes fiches d'inscription : une par session (src/student.inscriptions.php). */
    public function inscriptions() {
        $this->exigerCompteEtudiant();
        $this->renderSrc('student.inscriptions.php');
    }
    /** La fiche d'une session, a telecharger en PDF ; seulement les siennes. */
    public function ficheInscription() {
        $this->exigerCompteEtudiant();
        $this->renderSrc('student.fiche-inscription.php');
    }

    /* Avant tout affichage : la page, elle, ne redirige plus une fois son
       en-tete envoye (un enseignant y recevait une page vide). Ces fiches
       sont celles du compte connecte : seul un compte etudiant en a. Meme
       test que refuserEtudiant(), sur la session : la base n'est pas encore
       ouverte ici. Les autres comptes vont a l'accueil de leur role. */
    private function exigerCompteEtudiant() {
        $this->initSecurity();
        $niveauEtudiant = defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8;
        if ((int) ($_SESSION['user_level'] ?? 0) !== $niveauEtudiant
            && ($_SESSION['user_privilege'] ?? '') !== 'student') {
            $this->redirectTo('/espace');
        }
    }
    public function news() {
        $this->renderSrc('student.actus.php');
    }

    public function quiz() {
        $this->renderSrc('student.quiz.php');
    }

    public function game() {
        $this->renderSrc('student.game.php');
    }

    public function transition() {
        $this->renderSrc('student.transition.php');
    }

    public function create() {
        $this->requireLevelAccess(3);
        $this->renderSrc('creat.student.php');
    }

    /**
     * GET /students/adresse-libre?nom=...&prenom=...
     *
     * L'adresse universitaire que recevra le nouvel etudiant : la premiere
     * libre (data/adresse.etudiant.php). Appelee par le formulaire de creation
     * pendant la saisie du nom ; addStd.php la revalide a l'enregistrement.
     * Dit aussi qui porte deja l'adresse habituelle, quand elle est prise.
     */
    public function adresseLibre() {
        $this->requireLevelAccess(3);
        require_once ROOT_DIR . '/data/connectdb.php';
        require_once ROOT_DIR . '/data/adresse.etudiant.php';
        /** @var PDO $dtb */
        $dtb = $dtb ?? ($GLOBALS['dtb'] ?? null); // connexion deja ouverte ailleurs dans la requete
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $r = adresseEtudiantLibre($dtb,
            mb_substr((string) ($_GET['nom'] ?? ''), 0, 120),
            mb_substr((string) ($_GET['prenom'] ?? ''), 0, 120));
        $r['prise_par'] = ($r['base'] !== '' && $r['adresse'] !== $r['base']) ? adresseEtudiantPorteur($dtb, $r['base']) : '';
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
    }

}
