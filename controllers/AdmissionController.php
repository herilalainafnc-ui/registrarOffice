<?php

/**
 * AdmissionController — instruction des candidatures de pre-admission.
 *
 * Les candidatures sont deposees par le public via le formulaire de la page
 * Admissions (landing/pages/admissions.php) et stockees dans la table
 * `candidat`. Ce controleur permet au bureau des admissions de les consulter
 * et de les instruire (en cours / accepte / refuse).
 *
 * Acces reserve au superadmin et au registrariat (privileges, voir
 * BaseController::requireAnyPrivileges) : les candidatures contiennent des
 * donnees personnelles. Il etait ouvert jusqu'au niveau 6 (comptabilite,
 * media, chefs de mention). La barre laterale applique la meme regle
 * (hasAnyPrivilege dans init/menubar.php).
 */
class AdmissionController extends BaseController
{
    /** Statuts qu'un agent peut poser depuis l'interface. */
    private const STATUTS_AUTORISES = ['nouveau', 'en_cours', 'accepte', 'refuse'];

    /** Liste des candidatures recues. */
    public function candidats()
    {
        $this->requireAnyPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès aux candidatures.");

        // Bascule de filtre sans rechargement : le navigateur demande la
        // seule zone du tableau, pas la page entiere.
        if (!empty($_GET['fragment'])) {
            $this->renderSrc('admissions.candidats.partie.php');
            return;
        }

        $this->renderSrc('admissions.candidats.php');
    }

    /**
     * Change le statut d'une candidature.
     *
     * POST /candidatures/statut  { id, statut, csrf_token }
     *
     * « converti » ne figure pas parmi les statuts autorises ici : il sera
     * pose par la conversion en etudiant, pas a la main, pour que le lien
     * candidat -> etudiant reste fiable.
     */
    public function changerStatut()
    {
        $this->requireAnyPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès aux candidatures.");

        // Le CSRF protege contre une action declenchee depuis un autre site.
        if (!function_exists('verify_csrf') || !verify_csrf()) {
            $this->retour('erreur', 'Jeton de securite invalide. Veuillez reessayer.');
            return;
        }

        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $statut = isset($_POST['statut']) ? trim((string) $_POST['statut']) : '';

        if ($id <= 0 || !in_array($statut, self::STATUTS_AUTORISES, true)) {
            $this->retour('erreur', 'Demande invalide.');
            return;
        }

        require_once ROOT_DIR . '/data/connectdb.php';
        /** @var PDO $dtb */

        // Qui traite : on garde une trace lisible plutot qu'un simple id.
        //
        // La session est la source la plus sure : le login y place nom, prenom
        // et pseudo. currentUser() interroge la base via le middleware, dont la
        // connexion n'est pas forcement initialisee dans le chemin controleur —
        // il ne sert donc que de repli.
        $nomAgent = trim(
            ($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? '')
        );
        if ($nomAgent === '') {
            $nomAgent = $_SESSION['user_pseudo'] ?? '';
        }
        if ($nomAgent === '' && function_exists('initMiddleware')) {
            initMiddleware($dtb); // donne au middleware la connexion qui lui manque
            $agent = function_exists('currentUser') ? currentUser() : null;
            if ($agent) {
                $nomAgent = trim(($agent['prenom'] ?? '') . ' ' . ($agent['nom'] ?? ''));
                if ($nomAgent === '') { $nomAgent = $agent['pseudo'] ?? ''; }
            }
        }
        $nomAgent = $nomAgent !== '' ? $nomAgent : null;

        try {
            // « converti » est terminal : une candidature deja transformee en
            // etudiant ne doit pas etre rebasculee par une action d'interface.
            $maj = $dtb->prepare(
                'UPDATE candidat
                    SET statut = ?, traite_par = ?, date_traitement = NOW()
                  WHERE id = ? AND statut <> "converti"'
            );
            $maj->execute([$statut, $nomAgent, $id]);

            if ($maj->rowCount() === 0) {
                $this->retour('erreur', 'Candidature introuvable, deja convertie, ou statut inchange.');
                return;
            }
        } catch (Throwable $e) {
            error_log('Admissions: echec changement de statut: ' . $e->getMessage());
            $this->retour('erreur', 'Erreur lors de la mise a jour.');
            return;
        }

        // Le candidat est prevenu d'une acceptation ou d'un refus. L'envoi
        // est « au mieux » : la decision est deja enregistree et ne doit pas
        // dependre de la disponibilite du serveur de messagerie.
        $complement = '';
        if (in_array($statut, ['accepte', 'refuse'], true)) {
            require_once ROOT_DIR . '/data/admissions.mail.php';
            $lire = $dtb->prepare('SELECT nom_complet, email, telephone, mention, mention_libelle, diplome_vise FROM candidat WHERE id = ?');
            $lire->execute([$id]);
            $candidat = $lire->fetch(PDO::FETCH_ASSOC);
            if ($candidat) {
                $envoi = admissionNotifierCandidat($candidat, $statut);
                $complement = ' ' . $envoi['raison'];
                if (!empty($envoi['envoye'])) {
                    $dtb->prepare('UPDATE candidat SET notifie_le = NOW() WHERE id = ?')->execute([$id]);
                }

                // SMS : le telephone est obligatoire dans le formulaire
                // public alors que l'email est facultatif, ce canal touche
                // donc davantage de candidats.
                require_once ROOT_DIR . '/data/admissions.sms.php';
                $sms = admissionNotifierCandidatSms($candidat, $statut);
                $complement .= ' ' . $sms['raison'];
                if (!empty($sms['envoye'])) {
                    $dtb->prepare('UPDATE candidat SET notifie_sms_le = NOW() WHERE id = ?')->execute([$id]);
                }
            }
        }

        $this->retour('ok', 'Candidature mise a jour.' . $complement);
    }

    /**
     * Range une candidature dans les archives, ou l'en ressort.
     *
     * POST /candidatures/archiver  { id, archiver: 1|0, csrf_token }
     *
     * L'archivage ne touche PAS au statut : un dossier archive reste
     * « refuse » ou « converti ». Ranger n'est pas decider — confondre les
     * deux ferait perdre la trace de ce qui a ete repondu au candidat.
     */
    public function archiver()
    {
        $this->requireAnyPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès aux candidatures.");

        if (!function_exists('verify_csrf') || !verify_csrf()) {
            $this->retour('erreur', 'Jeton de securite invalide. Veuillez reessayer.');
            return;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            $this->retour('erreur', 'Demande invalide.');
            return;
        }
        // Absent ou « 0 » = sortie des archives.
        $ranger = !isset($_POST['archiver']) || (string) $_POST['archiver'] === '1';

        require_once ROOT_DIR . '/data/connectdb.php';

        $nomAgent = trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''));
        if ($nomAgent === '') { $nomAgent = $_SESSION['user_pseudo'] ?? ''; }

        try {
            if ($ranger) {
                $maj = $dtb->prepare(
                    'UPDATE candidat SET archive_le = NOW(), archive_par = ?
                      WHERE id = ? AND archive_le IS NULL'
                );
                $maj->execute([$nomAgent !== '' ? $nomAgent : null, $id]);
            } else {
                $maj = $dtb->prepare(
                    'UPDATE candidat SET archive_le = NULL, archive_par = NULL
                      WHERE id = ? AND archive_le IS NOT NULL'
                );
                $maj->execute([$id]);
            }

            if ($maj->rowCount() === 0) {
                $this->retour('erreur', 'Candidature introuvable, ou deja dans cet etat.');
                return;
            }
        } catch (Throwable $e) {
            error_log('Admissions: echec archivage: ' . $e->getMessage());
            $this->retour('erreur', "L'operation n'a pas abouti.");
            return;
        }

        $this->retour('ok', $ranger ? 'Candidature archivee.' : 'Candidature sortie des archives.');
    }

    /**
     * Vrai si la requete vient du script de la page et non d'une navigation.
     *
     * Detection autonome : la fonction equivalente de data/guard.ajax.php
     * n'est pas chargee sur ce chemin, et s'y fier ferait retomber
     * silencieusement sur une redirection que le script ne peut pas suivre.
     */
    private function estAppelSansRechargement()
    {
        $demande = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($demande) === 'xmlhttprequest';
    }

    /**
     * Repond a une action declenchee sans rechargement.
     *
     * Le serveur applique le changement puis renvoie, dans la meme reponse,
     * la zone de liste deja rafraichie : un seul aller-retour suffit, et les
     * compteurs comme les boutons disponibles restent coherents.
     */
    private function repondreAjax($ok, $message)
    {
        $html = null;
        if ($ok) {
            // La partie de vue lit le filtre dans $_GET : on lui transmet
            // celui que l'utilisateur avait a l'ecran.
            $retour = isset($_POST['retour']) ? (string) $_POST['retour'] : '';
            if (in_array($retour, ['nouveau', 'en_cours', 'accepte', 'refuse', 'converti', 'archive'], true)) {
                $_GET['statut'] = $retour;
            } else {
                unset($_GET['statut']);
            }
            // On inclut la vue directement plutot que par renderSrc() :
            // celui-ci se termine par un exit, si bien que la reponse JSON
            // ne serait jamais construite et que le HTML partirait brut.
            $app_base = defined('APP_BASE') ? APP_BASE : '';
            $dossierInitial = getcwd();
            ob_start();
            chdir(ROOT_DIR . '/src'); // les require relatifs de la vue en dependent
            require ROOT_DIR . '/src/admissions.candidats.partie.php';
            $html = ob_get_clean();
            chdir($dossierInitial);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => (bool) $ok, 'message' => $message, 'html' => $html]);
        exit;
    }

    /**
     * Redirige vers la liste en transmettant le resultat de l'action.
     *
     * Redirection apres POST (motif « post/redirect/get ») : un rafraichissement
     * de la page ne rejoue pas l'action.
     */
    private function retour($type, $message)
    {
        // Appel sans rechargement : on renvoie le resultat et la liste a jour
        // plutot qu'une redirection, que le script ne pourrait pas suivre.
        if ($this->estAppelSansRechargement()) {
            $this->repondreAjax($type === 'ok', $message);
            return;
        }

        $base = defined('APP_BASE') ? APP_BASE : '';
        $params = [$type => $message];
        // Le formulaire transmet le filtre affiche : on y revient plutot
        // que de renvoyer systematiquement sur la liste complete.
        $retour = isset($_POST['retour']) ? (string) $_POST['retour'] : '';
        if (in_array($retour, ['nouveau', 'en_cours', 'accepte', 'refuse', 'converti', 'archive'], true)) {
            $params['statut'] = $retour;
        }
        $url  = $base . '/candidatures?' . http_build_query($params);
        header('Location: ' . $url);
        exit;
    }
}
