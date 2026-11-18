<?php
/**
 * HomeController - Pages publiques (landing)
 * Routes: /, /formations, /admissions, /campus, /contact
 */
class HomeController extends BaseController {

    public function index() {
        // Vitrine en page unique : les cinq rubriques se parcourent au
        // defilement. Les pages autonomes restent servies a leurs adresses,
        // pour les liens partages et les envois de formulaires.
        $this->renderLanding('vitrine.php');
    }

    public function formations() {
        $this->render('landing/pages/formations.php', ROOT_DIR . '/landing/pages');
    }

    public function admissions() {
        $this->render('landing/pages/admissions.php', ROOT_DIR . '/landing/pages');
    }

    public function campus() {
        $this->render('landing/pages/campus.php', ROOT_DIR . '/landing/pages');
    }

    public function contact() {
        $this->render('landing/pages/contact.php', ROOT_DIR . '/landing/pages');
    }

    /**
     * GET /admissions/verifier-email?adresse=...
     *
     * Verification d'une adresse pendant que le candidat la tape
     * (data/email.verification.php) : forme, domaine qui recoit les emails,
     * faute de frappe probable. Public, comme le formulaire : limite a 30
     * verifications par minute et par adresse IP, pour ne pas servir de relais
     * a des requetes DNS en masse. Au-dela, la reponse dit « non verifie » et
     * c'est le serveur qui revalide a l'envoi.
     */
    public function verifierEmail() {
        require_once ROOT_DIR . '/data/email.verification.php';
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $adresse = mb_substr(trim((string) ($_GET['adresse'] ?? '')), 0, 254);

        $fichier = sys_get_temp_dir() . '/uaz-verif-email-' . sha1((string) ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
        $maintenant = time();
        $recents = array_filter(
            json_decode((string) @file_get_contents($fichier), true) ?: [],
            static fn ($t) => is_int($t) && $t > $maintenant - 60
        );
        if (count($recents) >= 30) {
            echo json_encode(['etat' => 'non_verifie', 'bloquant' => false, 'message' => '', 'suggestion' => null]);
            return;
        }
        $recents[] = $maintenant;
        @file_put_contents($fichier, json_encode(array_values($recents)), LOCK_EX);

        echo json_encode(emailVerifier($adresse), JSON_UNESCAPED_UNICODE);
    }
}
