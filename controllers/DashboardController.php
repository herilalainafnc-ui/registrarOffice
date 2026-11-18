<?php
/**
 * DashboardController - Tableaux de bord et listes principales
 * Routes: /tableau-de-bord, /etudiants, /classrooms, /professors, /courses, /schedule, /fusion-pdf, /qr-generator
 *
 * /professors et /courses : superadmin et registrariat seulement. Leurs
 * fragments de tri et de recherche (init/.prof, init/.cours) appliquent la
 * meme regle.
 */
class DashboardController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function tableauDeBord() {
        /* Page d'arrivee du personnel apres la connexion. Les etudiants et
           les enseignants ont leur propre espace : ils y sont renvoyes, sans
           message de refus (le lien « accueil » du menu mene ici). */
        $this->refuserEtudiant();
        /* Meme test que refuserEnseignant() : un registraire par ailleurs
           enseignant (niveau 1 a 3) garde le tableau de bord. */
        $niveau = (int) ($_SESSION['user_level'] ?? 99);
        $niveauEnseignant = defined('ROLE_TEACHER') ? (int) ROLE_TEACHER : 7;
        if ($niveau > 3 && ($niveau === $niveauEnseignant || (function_exists('isTeacher') && isTeacher()))) {
            $this->redirectTo('/teacher/dashboard');
        }
        if (function_exists('estWorkEducation') && estWorkEducation()) {
            $this->redirectTo('/work-education');
        }
        $this->renderSrc('tableau-de-bord.php');
    }

    public function didacticiel() {
        /* Les visites guidees du compte (data/didacticiel.php). Personnel et
           enseignants ; les etudiants ont leur propre espace. */
        $this->refuserEtudiant();
        $this->renderSrc('didacticiel.php');
    }

    public function index() {
        /* Liste de TOUS les etudiants de l'universite, avec acces a leur
           dossier. Un enseignant ne voit que les siens, depuis son espace :
           src/teacher.dashboard.php les recense a partir de ses cours. */
        $this->refuserEnseignant("La liste complete des etudiants est reservee au personnel. "
            . "Vos etudiants figurent dans votre espace enseignant.");
        $this->renderSrc('accueil.php');
    }

    public function classrooms() {
        $this->renderSrc('accueil.classroom.php');
    }

    public function professors() {
        $this->requireAnyPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès à la liste des enseignants.");
        $this->renderSrc('accueil.prof.php');
    }

    public function courses() {
        $this->requireAnyPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès à la liste des cours.");
        $this->renderSrc('accueil.cours.php');
    }

    public function schedule() {
        // L'emploi du temps se construit au registrariat ; l'espace enseignant n'y a pas acces.
        $this->refuserEnseignant("L'emploi du temps ne fait pas partie de l'espace enseignant.");
        // Ni de l'espace etudiant : un compte etudiant ouvrait la page et, par
        // son API, pouvait modifier les seances (voir src/emploi-temps.api.php).
        $this->refuserEtudiant();
        $this->renderSrc('accueil.emploi-temps.php');
    }

    public function fusionPdf() {
        /* Outil de la section « Application » du menu : memes portes que
           l'emploi du temps. La fusion se fait dans le navigateur, aucun
           fichier n'est envoye au serveur. */
        $this->refuserEnseignant("La fusion de PDF ne fait pas partie de l'espace enseignant.");
        $this->refuserEtudiant();
        $this->renderSrc('fusion-pdf.php');
    }

    public function qrGenerator() {
        /* Outil de la section « Application » du menu, avec les memes portes
           que la fusion de PDF. Le QR Code se dessine dans le navigateur :
           ni le contenu ni le logo ne sont envoyes au serveur. */
        $this->refuserEnseignant("Le générateur de QR Code ne fait pas partie de l'espace enseignant.");
        $this->refuserEtudiant();
        $this->renderSrc('qr-generator.php');
    }

    public function retouchePhoto() {
        /* Atelier de retouche photo de la section « Application » : le Media
           et le superadmin (demande du 12/10/2026). Les photos sont traitees
           dans le navigateur, aucune n'est envoyee au serveur. */
        $this->requireAnyLevel([ROLE_SUPERADMIN, ROLE_MEDIA], "La retouche photo est réservée au service Media.");
        $this->renderSrc('retouche-photo.php');
    }

    public function studio() {
        /* Studio UAZ : les visuels de l'universite a partir de presets, sans
           pouvoir sortir de la charte (demande du 20/10/2026, kit
           docs/studio-uaz/). Le Media et le superadmin, comme la retouche. */
        $this->requireAnyLevel([ROLE_SUPERADMIN, ROLE_MEDIA], "Le Studio UAZ est réservé au service Media.");
        $this->renderSrc('studio.php');
    }
}
