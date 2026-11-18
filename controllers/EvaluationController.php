<?php
/**
 * EvaluationController - Gestion du module des évaluations et sondages
 */
class EvaluationController extends BaseController {

    /**
     * Le module n'est pas ouvert aux enseignants, quelle que soit la page.
     * Les autres comptes (et les visiteurs d'un formulaire) gardent l'acces
     * qu'ils avaient : refuserEnseignant ne refuse que les enseignants.
     */
    public function __construct() {
        $this->refuserEnseignant("Les évaluations ne font pas partie de l'espace enseignant.");
    }

    /**
     * Interface Utilisateur : Liste des formulaires disponibles et formulaires
     */
    public function index() {
        $this->renderSrc('evaluation/index.php');
    }

    /**
     * Interface Utilisateur : Afficher un formulaire spécifique
     */
    public function viewForm($id) {
        $_GET['id'] = $id;
        $this->renderSrc('evaluation/view.php');
    }

    /**
     * Soumission d'un formulaire
     */
    public function submitForm($id) {
        $_GET['id'] = $id;
        $this->renderSrc('evaluation/process.php');
    }

    /**
     * Tableau de bord Administrateur : CRUD formulaires et statistiques
     */
    public function admin() {
        // Seul le personnel d'administration peut accéder (level <= 3 ou level précis)
        $this->requireLevelAccess(3, "Accès réservé aux administrateurs pour la gestion des évaluations.");
        $this->renderSrc('evaluation/admin.php');
    }

    /**
     * Voir les statistiques (Admin)
     */
    public function stats($id) {
        $_GET['id'] = $id;
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/stats.php');
    }

    /**
     * Editer un formulaire (Admin)
     */
    public function edit($id) {
        $_GET['id'] = $id;
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/edit.php');
    }

    /**
     * Sauvegarder les modifications d'un formulaire
     */
    public function update($id) {
        $_GET['id'] = $id;
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/update.php');
    }

    /**
     * Supprimer un formulaire (Admin)
     */
    public function delete($id) {
        $_GET['id'] = $id;
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/delete.php');
    }

    /**
     * Page de création d'un formulaire
     */
    public function create() {
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/create.php');
    }

    /**
     * Traitement de création du formulaire
     */
    public function store() {
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/store.php');
    }

    /**
     * API pour la gestion CRUD des formulaires en AJAX
     */
    public function apiForms() {
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/api_forms.php');
    }

    /**     * Interface pour l'automatisation (Envoi et création dynamique)
     */
    public function automate() {
        $this->requireLevelAccess(3); // Ou selon la politique de sécurité
        $this->renderSrc('evaluation/automate.php');
    }

    /**     * API pour récupérer les statistiques d'un formulaire
     */
    public function apiStats($id) {
        $_GET['id'] = $id;
        $this->requireLevelAccess(3);
        $this->renderSrc('evaluation/api_stats.php');
    }
}