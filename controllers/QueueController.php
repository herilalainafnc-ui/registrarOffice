<?php
/**
 * QueueController - Système de file d'attente pour les inscriptions
 * Routes: /queue, /queue/display, /queue/api
 */
class QueueController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    /**
     * Écran Agent - Gestion de la file d'attente
     */
    public function index() {
        $this->requireLevelAccess(1);
        $this->renderSrc('queue/queue.agent.php');
    }

    /**
     * Écran Public - Affichage grand écran (lecture seule)
     */
    public function display() {
        $this->renderSrc('queue/queue.display.php');
    }

    /**
     * API AJAX - Endpoints pour la gestion des tickets
     */
    public function api() {
        $this->requireLevelAccess(1);
        $this->renderSrc('queue/queue.api.php');
    }
}
