<?php
/**
 * QueueController - Système de file d'attente pour les inscriptions
 * Routes: /queue, /queue/display, /queue/plan, /queue/api
 */
class QueueController extends BaseController {

    /* L'ecran public (/queue/display) est ouvert a tous, sans connexion
       (demande du 08/10/2026) : le grand public suit la file depuis son
       telephone. L'ecran agent et l'API gardent chacun leur verrou. */
    public function __construct() {
    }

    /**
     * Écran Agent - Gestion de la file d'attente
     */
    public function index() {
        $this->requireAuthenticated('/login');
        $this->requireLevelAccess(1);
        $this->renderSrc('queue/queue.agent.php');
    }

    /**
     * Écran Public - Affichage grand écran (lecture seule), sans connexion
     */
    public function display() {
        $this->renderSrc('queue/queue.display.php');
    }

    /**
     * Plan du bureau : les pieces, l'entree et les etapes du parcours des
     * etudiants, montres sur l'ecran public. Superadmin seul (demande du
     * 11/10/2026), comme l'ecran agent de la file d'attente.
     */
    public function plan() {
        $this->requireAuthenticated('/login');
        $this->requireLevelAccess(1, 'Le plan du bureau ne se modifie que par le superadmin.');
        $this->renderSrc('queue/queue.plan.php');
    }

    /**
     * API AJAX - Endpoints pour la gestion des tickets
     *
     * Trois actions servent l'ecran public et restent joignables sans
     * connexion (dont « plan », le plan du bureau) ; l'API decide elle-meme de ce qu'elle y montre (src/queue/
     * queue.api.php : pas de nom aux visiteurs, et seul un superadmin fait
     * avancer la musique de la TV). Tout le reste : superadmin connecte.
     */
    public function api() {
        $action = (string) ($_REQUEST['action'] ?? '');
        if (!in_array($action, ['public_status', 'playlist_auto_next', 'plan'], true)) {
            $this->requireAuthenticated('/login');
            $this->requireLevelAccess(1);
        }
        $this->renderSrc('queue/queue.api.php');
    }
}
