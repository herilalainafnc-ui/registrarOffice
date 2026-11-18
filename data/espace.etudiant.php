<?php
/**
 * Espace étudiant : ce que les comptes étudiants peuvent voir.
 *
 * PUBLICATION DES NOTES, SESSION PAR SESSION
 * ------------------------------------------
 * Les notes sont saisies au fil du semestre mais ne doivent pas être
 * divulguées avant l'aval du Sénat, qui porte sur une session d'examens.
 * Chaque session est donc « publiée » ou « masquée » (t_notes_publication).
 * Une session sans ligne est masquée : une session créée après l'installation
 * démarre masquée, et rien ne fuit par oubli.
 *
 * Pour un compte étudiant, une session masquée :
 *   - n'apparaît pas dans « Mes Notes » (src/student.dashboard.php) ;
 *   - ne compte pas dans la moyenne affichée, qui la révélerait sinon.
 * Le personnel voit toujours tout.
 *
 * Les autres chemins vers les notes (fiche administrative, relevés, bulletins,
 * impressions, points d'enregistrement, classements) sont fermés aux comptes
 * étudiants par refuserEtudiant() — data/guard.ajax.php et BaseController.
 * Ils ne vérifiaient que la connexion.
 *
 * RUBRIQUES
 * ---------
 * Interrupteurs simples, stockés dans t_espace_etudiant_config. La liste est
 * dans espaceEtudiantRubriques() : en ajouter une se fait là.
 *
 * Réglage : /settings/espace-etudiant (superadmin).
 */

/** À l'installation, les sessions de cette année scolaire et des suivantes démarrent masquées. */
const NOTES_ANNEE_MASQUEE_A_L_INSTALLATION = 2025;

if (!function_exists('espaceEtudiantInstaller')) {

/**
 * Crée les tables au premier usage, comme data/audit.php.
 *
 * L'état de départ des sessions existantes est posé dans la foulée, jamais
 * plus tard : une table vide masquerait d'un coup toutes les notes, y compris
 * celles des années déjà validées. Décidé avec le registrariat le 15/09/2026 :
 * publiées avant 2025-2026, masquées ensuite en attendant le Sénat.
 */
function espaceEtudiantInstaller(PDO $dtb): void
{
    static $fait = false;
    if ($fait) {
        return;
    }

    $presentes = $dtb->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('t_notes_publication', 't_espace_etudiant_config')"
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('t_notes_publication', $presentes, true)) {
        $dtb->exec(
            "CREATE TABLE IF NOT EXISTS t_notes_publication (
                session_id  INT          NOT NULL,
                publiee     TINYINT(1)   NOT NULL DEFAULT 0,
                modifie_le  DATETIME     DEFAULT NULL,
                modifie_par VARCHAR(100) DEFAULT NULL,
                PRIMARY KEY (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // INSERT IGNORE : deux premiers affichages simultanés ne se gênent pas.
        $depart = $dtb->prepare(
            "INSERT IGNORE INTO t_notes_publication (session_id, publiee, modifie_le, modifie_par)
             SELECT session_id,
                    CASE WHEN CAST(LEFT(TRIM(session_year), 4) AS UNSIGNED) < :annee THEN 1 ELSE 0 END,
                    :le, 'installation'
               FROM t_2023_session"
        );
        $depart->execute(['annee' => NOTES_ANNEE_MASQUEE_A_L_INSTALLATION, 'le' => date('Y-m-d H:i:s')]);
    }

    if (!in_array('t_espace_etudiant_config', $presentes, true)) {
        $dtb->exec(
            "CREATE TABLE IF NOT EXISTS t_espace_etudiant_config (
                cle         VARCHAR(80)  NOT NULL,
                valeur      VARCHAR(255) NOT NULL,
                modifie_le  DATETIME     DEFAULT NULL,
                modifie_par VARCHAR(100) DEFAULT NULL,
                PRIMARY KEY (cle)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $fait = true;
}

/**
 * Sessions regroupées par année scolaire, avec leur état de publication et ce
 * qu'elles contiennent. Année la plus récente d'abord.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function notesPublicationParAnnee(PDO $dtb): array
{
    espaceEtudiantInstaller($dtb);

    $lignes = $dtb->query(
        "SELECT s.session_id, s.session_name, s.session_year, s.session_semester,
                p.session_id IS NOT NULL AS decidee,
                COALESCE(p.publiee, 0) AS publiee, p.modifie_le, p.modifie_par,
                COUNT(n.id) AS nb_notes,
                COUNT(DISTINCT n.student_id) AS nb_etudiants,
                COALESCE(SUM(n.grade IS NULL OR n.grade IN ('', '-1', '-4')), 0) AS nb_non_notees
           FROM t_2023_session s
           LEFT JOIN t_notes_publication p ON p.session_id = s.session_id
           LEFT JOIN t_2023_notes n ON n.session_id = s.session_id AND n.ajout = 1
          GROUP BY s.session_id, s.session_name, s.session_year, s.session_semester,
                   p.session_id, p.publiee, p.modifie_le, p.modifie_par
          ORDER BY s.session_year DESC, s.session_semester ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $parAnnee = [];
    foreach ($lignes as $l) {
        $parAnnee[trim((string) $l['session_year'])][] = $l;
    }

    return $parAnnee;
}

/**
 * Publie ou masque les notes d'une session.
 *
 * @return array{ok:bool, message:string, publiee?:bool}
 */
function notesPublier(PDO $dtb, int $sessionId, bool $publiee, string $par): array
{
    espaceEtudiantInstaller($dtb);

    $lire = $dtb->prepare('SELECT session_name, session_year FROM t_2023_session WHERE session_id = :s LIMIT 1');
    $lire->execute(['s' => $sessionId]);
    $session = $lire->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        return ['ok' => false, 'message' => 'Session inconnue.'];
    }

    $ecrire = $dtb->prepare(
        'INSERT INTO t_notes_publication (session_id, publiee, modifie_le, modifie_par)
         VALUES (:s, :p, :le, :par) AS nouv
         ON DUPLICATE KEY UPDATE publiee = nouv.publiee, modifie_le = nouv.modifie_le, modifie_par = nouv.modifie_par'
    );
    $ecrire->execute([
        's'   => $sessionId,
        'p'   => $publiee ? 1 : 0,
        'le'  => date('Y-m-d H:i:s'),
        'par' => mb_substr($par, 0, 100),
    ]);

    $nom = trim((string) $session['session_name']) . ' ' . trim((string) $session['session_year']);

    return [
        'ok'      => true,
        'publiee' => $publiee,
        'message' => $publiee
            ? 'Notes de « ' . $nom . ' » publiées : les étudiants les voient dès maintenant.'
            : 'Notes de « ' . $nom . ' » masquées aux étudiants.',
    ];
}

/**
 * Rubriques de l'espace étudiant qu'un interrupteur peut masquer.
 *
 * @return array<string, array{libelle:string, aide:string, defaut:bool}>
 */
function espaceEtudiantRubriques(): array
{
    return [
        'notes_promotion' => [
            'libelle' => 'Notes de promotion',
            'aide'    => 'Work Education, remarque académique et participation à la chapelle, '
                       . 'sous chaque session publiée, avec leurs cumuls.',
            'defaut'  => true,
        ],
        'fiches_inscription' => [
            'libelle' => "Fiches d'inscription",
            'aide'    => "La fiche de chaque session (cours, frais, mode de paiement), "
                       . "à consulter et à télécharger en PDF.",
            'defaut'  => true,
        ],
    ];
}

/**
 * Premiere annee dont l'etudiant voit ses fiches d'inscription, par cycle
 * (decision du registrariat, 07/10/2026) : la Licence a partir de 2026-2027,
 * le Master a partir de 2025-2026. Les fiches plus anciennes restent au
 * guichet.
 */
define('ESPACE_FICHES_DEPUIS', ['L' => '2026 - 2027', 'M' => '2025 - 2026']);

/**
 * La fiche d'inscription de cette session est-elle montree a l'etudiant ?
 * Le cycle est celui de l'inscription (niveau 4 et 5 : Master) ; un niveau
 * jamais saisi (0) laisse la place a celui de la fiche.
 */
function espaceEtudiantFicheVisible(string $annee, $niveauInscription, $niveauFiche): bool
{
    $niveau = (int) $niveauInscription > 0 ? (int) $niveauInscription : (int) $niveauFiche;
    $depuis = ESPACE_FICHES_DEPUIS[$niveau >= 4 ? 'M' : 'L'];

    return (int) substr($annee, 0, 4) >= (int) substr($depuis, 0, 4);
}

/** La rubrique est-elle visible par les étudiants ? */
function espaceEtudiantRubriqueVisible(PDO $dtb, string $cle): bool
{
    $rubriques = espaceEtudiantRubriques();
    if (!isset($rubriques[$cle])) {
        return false;
    }

    espaceEtudiantInstaller($dtb);
    $lire = $dtb->prepare('SELECT valeur FROM t_espace_etudiant_config WHERE cle = :c LIMIT 1');
    $lire->execute(['c' => $cle]);
    $valeur = $lire->fetchColumn();

    return $valeur === false ? $rubriques[$cle]['defaut'] : $valeur === '1';
}

/** Dernière modification d'une rubrique, ou null si elle n'a jamais été réglée. */
function espaceEtudiantRubriqueTrace(PDO $dtb, string $cle): ?array
{
    espaceEtudiantInstaller($dtb);
    $lire = $dtb->prepare('SELECT modifie_le, modifie_par FROM t_espace_etudiant_config WHERE cle = :c LIMIT 1');
    $lire->execute(['c' => $cle]);
    $ligne = $lire->fetch(PDO::FETCH_ASSOC);

    return $ligne ?: null;
}

/**
 * Affiche ou masque une rubrique.
 *
 * @return array{ok:bool, message:string, visible?:bool}
 */
function espaceEtudiantRubriqueEnregistrer(PDO $dtb, string $cle, bool $visible, string $par): array
{
    $rubriques = espaceEtudiantRubriques();
    if (!isset($rubriques[$cle])) {
        return ['ok' => false, 'message' => 'Rubrique inconnue.'];
    }

    espaceEtudiantInstaller($dtb);
    $ecrire = $dtb->prepare(
        'INSERT INTO t_espace_etudiant_config (cle, valeur, modifie_le, modifie_par)
         VALUES (:c, :v, :le, :par) AS nouv
         ON DUPLICATE KEY UPDATE valeur = nouv.valeur, modifie_le = nouv.modifie_le, modifie_par = nouv.modifie_par'
    );
    $ecrire->execute([
        'c'   => $cle,
        'v'   => $visible ? '1' : '0',
        'le'  => date('Y-m-d H:i:s'),
        'par' => mb_substr($par, 0, 100),
    ]);

    return [
        'ok'      => true,
        'visible' => $visible,
        'message' => $rubriques[$cle]['libelle'] . ($visible ? ' : visibles par les étudiants.' : ' : masquées aux étudiants.'),
    ];
}

/** Nom de l'utilisateur connecté, pour « modifié par ». */
function espaceEtudiantAuteur(): string
{
    $u = function_exists('currentUser') ? currentUser() : null;
    $nom = trim((string) (($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')));

    return $nom !== '' ? $nom : 'utilisateur n°' . (int) ($_SESSION['user_id'] ?? 0);
}

/** « le 15/09/2026 à 14:05 par Hary » à partir d'une date SQL. */
function espaceEtudiantQuand(?string $le, ?string $par): string
{
    $horodatage = $le ? strtotime($le) : false;
    $texte = $horodatage ? 'le ' . date('d/m/Y', $horodatage) . ' à ' . date('H:i', $horodatage) : '';
    if ($par !== null && $par !== '') {
        $texte .= ($texte !== '' ? ' ' : '') . 'par ' . $par;
    }

    return $texte;
}

}
