<?php
/**
 * Socle commun du module inscription : annee scolaire courante et resolution
 * de « la session sur laquelle on travaille ».
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Quatre fichiers repondaient separement a la meme question, et pas de la
 * meme facon :
 *
 *   inscription/inscription.php      SELECT ... ORDER BY id DESC   (injecte dans le JS)
 *   inscription/app/generate.stage.php  SELECT ... ORDER BY id DESC
 *   inscription/stages/session.php      SELECT ... ORDER BY id DESC LIMIT 1
 *   inscription/stages/top.stages.php   SELECT ... ORDER BY id DESC
 *
 * « L'id le plus grand » n'est pas « la session de l'annee en cours » : il
 * suffit qu'un dossier d'une annee anterieure ait ete cree apres celui de
 * l'annee courante pour que tout le module se trompe de session. Releve en
 * base : 15 etudiants sur 670 sont dans ce cas, dont le dossier 30188 signale
 * par le registraire (sa session 2026-2027 porte l'id 2345, sa session
 * 2025-2026 l'id 2346).
 *
 * Les consequences etaient en cascade : barre des 7 etapes vide, etapes non
 * enregistrees, frais releves pour la mauvaise session — donc fiche
 * d'inscription sans frais de scolarite.
 *
 * La resolution se fait donc sur l'ANNEE SCOLAIRE, l'id ne servant plus qu'a
 * departager deux sessions d'une meme annee.
 *
 * AUTORITE DES DONNEES
 * --------------------
 * t_2023_session.session_year definit la session ; t_2024_inscription_session
 * .annee_scolaire n'en est qu'une copie denormalisee, et les deux divergent sur
 * 25 lignes. Le catalogue fait foi, la copie ne sert que de repli quand la
 * jointure ne ramene rien.
 */

/**
 * Annee scolaire en cours, au format exact de la base (« 2026 - 2027 »).
 *
 * Regle conservee telle quelle du module : l'annee universitaire bascule en
 * juillet. C'est aussi la regle qu'applique le menu deroulant de la page, donc
 * ce que le registraire voit selectionne par defaut.
 */
function inscAnneeCourante(?int $mois = null, ?int $annee = null): string
{
    $mois  = $mois ?? (int) date('m');
    $annee = $annee ?? (int) date('Y');

    return ($mois >= 7)
        ? $annee . ' - ' . ($annee + 1)
        : ($annee - 1) . ' - ' . $annee;
}

/**
 * L'annee scolaire donnee est-elle celle en cours ?
 *
 * Remplace deux fenetres de validite qui se contredisaient :
 * generate.stage.php exigeait « annee != annee precedente » avant juillet
 * (inverse : de janvier a juin il refusait justement les dossiers de l'annee
 * courante), top.stages.php exigeait « annee == annee precedente » (correct).
 */
function inscAnneeEstCourante(?string $annee, ?int $mois = null, ?int $annees = null): bool
{
    if ($annee === null || trim($annee) === '') {
        return false;
    }

    return trim($annee) === inscAnneeCourante($mois, $annees);
}

/**
 * Toutes les sessions d'inscription d'un etudiant, la plus pertinente d'abord.
 *
 * Tri : annee scolaire decroissante, puis id decroissant. Le tri porte sur
 * l'annee du catalogue quand elle existe, sur la copie locale sinon. Le format
 * « AAAA - AAAA » se trie correctement en ordre lexicographique, inutile de le
 * decouper.
 *
 * @return array<int, array<string, mixed>> Lignes enrichies de annee_effective.
 */
function inscSessionsEtudiant(PDO $dtb, string $student_id): array
{
    if (trim($student_id) === '') {
        return [];
    }

    $req = $dtb->prepare(
        'SELECT ins.*,
                s.session_name,
                s.session_year,
                s.session_semester,
                COALESCE(NULLIF(s.session_year, ""), ins.annee_scolaire) AS annee_effective,
                (ins.data_completion + ins.data_verification + ins.cours_selected
                 + ins.mode_payement + ins.verification_signatures + ins.depot_list
                 + ins.caisse_verification) AS avancement
           FROM t_2024_inscription_session ins
           LEFT JOIN t_2023_session s ON s.session_id = ins.session_id
          WHERE ins.student_id = :student_id
            AND ins.remove != 1
          ORDER BY annee_effective DESC, ins.id DESC'
    );
    $req->execute(['student_id' => $student_id]);
    $lignes = $req->fetchAll(PDO::FETCH_ASSOC);

    /* Doublons d'une MEME session.
       12 couples (etudiant, session) existent en plusieurs exemplaires faute
       d'index unique, et 10 portent des avancements differents : un « LIMIT 1 »
       sans ORDER BY en prenait un au hasard, si bien que la barre des etapes
       pouvait reculer d'un affichage a l'autre. On retient le plus avance.

       Ce departage est volontairement limite aux lignes de meme session_id.
       L'appliquer au tri general reordonnait des SEMESTRES distincts d'une
       meme annee : un semestre 2 tout juste cree (avancement 1) passait
       derriere un semestre 1 termine (avancement 7), et le module revenait sur
       le semestre precedent. Entre deux sessions differentes, c'est donc l'id
       le plus grand qui decide — la plus recemment creee, comme avant. */
    $parSession = [];
    foreach ($lignes as $ligne) {
        $cle = (string) $ligne['session_id'];
        if (!isset($parSession[$cle])
            || (int) $ligne['avancement'] > (int) $parSession[$cle]['avancement']) {
            $parSession[$cle] = $ligne;
        }
    }

    return array_values($parSession);
}

/**
 * Session sur laquelle le module doit travailler pour cet etudiant.
 *
 * Ordre de preference :
 *   1. la session explicitement demandee, si elle appartient bien a cet
 *      etudiant (c'est le cas des le moment ou le registraire a choisi un
 *      semestre : son choix ne doit jamais etre reinterprete) ;
 *   2. a defaut, une session de l'annee scolaire en cours ;
 *   3. a defaut, la session de l'annee la plus recente.
 *
 * Retourne null si l'etudiant n'a aucune inscription.
 *
 * @param int|string|null $session_id_demande
 */
function inscSessionCourante(PDO $dtb, string $student_id, $session_id_demande = null): ?array
{
    $sessions = inscSessionsEtudiant($dtb, $student_id);
    if ($sessions === []) {
        return null;
    }

    // 1. Choix explicite — honore uniquement s'il correspond a cet etudiant,
    //    pour qu'un session_id force dans l'URL ne donne pas acces au dossier
    //    d'un autre.
    $demande = ($session_id_demande === null || $session_id_demande === '')
        ? null
        : (int) $session_id_demande;

    if ($demande !== null && $demande > 0) {
        foreach ($sessions as $s) {
            if ((int) $s['session_id'] === $demande) {
                return $s;
            }
        }
    }

    // 2. Annee en cours.
    foreach ($sessions as $s) {
        if (inscAnneeEstCourante($s['annee_effective'])) {
            return $s;
        }
    }

    // 3. Annee la plus recente (le tri l'a deja placee en tete).
    return $sessions[0];
}


/**
 * Motif de refus d'une inscription, ou null si rien ne s'y oppose.
 *
 * La page affiche ces verrous en modale (inscription/inscription.php) : le
 * serveur, lui, les ignorait. generate.student.php etant joignable
 * directement en HTTP, la modale n'etait qu'un voile. Regles des deux
 * drapeaux : data/reinscription.php — la caisse autorise, le registrariat
 * valide le dossier.
 *
 * @param array<string, mixed> $etudiant ligne de tbl_2024_etudiant
 */
function inscriptionMotifDeRefus(array $etudiant): ?string
{
    if ((int) ($etudiant['suspended'] ?? 0) === 1) {
        $fin = $etudiant['date_fin_suspension'] ?? '';
        if (empty($fin) || strtotime((string) $fin) >= strtotime(date('Y-m-d'))) {
            return 'Étudiant suspendu : inscription bloquée'
                . (!empty($fin) ? " jusqu'au " . date('d/m/Y', strtotime((string) $fin)) : '') . '.';
        }
    }

    if ((int) ($etudiant['retrait_universite'] ?? 0) >= 2) {
        return "Étudiant retiré de l'université : inscription bloquée.";
    }

    if ((int) ($etudiant['authorized_reinscription'] ?? 0) !== 1) {
        return 'Réinscription non autorisée par la caisse. Contactez le service de caisse.';
    }

    if ((int) ($etudiant['dossier_ok'] ?? 0) !== 1) {
        return 'Dossier non validé par le registrariat.';
    }

    return null;
}
/**
 * Identifiant de session a utiliser, ou '' si l'etudiant n'a aucune
 * inscription. Forme courte pour les appelants qui n'ont besoin que de l'id.
 *
 * @param int|string|null $session_id_demande
 */
function inscSessionIdCourant(PDO $dtb, string $student_id, $session_id_demande = null): string
{
    $session = inscSessionCourante($dtb, $student_id, $session_id_demande);

    return $session ? (string) $session['session_id'] : '';
}

/**
 * Ligne financiere d'un couple (etudiant, session).
 *
 * POURQUOI PAS UN SIMPLE « LIMIT 1 »
 * ----------------------------------
 * t_2024_etudiant_finace n'a pas d'index unique sur (student_id, session_id) :
 * 133 couples existent en plusieurs exemplaires, jusqu'a 13 pour un meme
 * etudiant. Les lectures faisaient « LIMIT 1 » sans ORDER BY, donc prenaient
 * une ligne au hasard — et 60 de ces couples portent des montants DIFFERENTS.
 * Le total affiche pouvait donc changer d'un affichage a l'autre.
 *
 * Deux precautions ici :
 *   - une ligne portant un mode de paiement passe devant une ligne vide. Sur
 *     56 couples, la ligne d'id le plus grand a un mode_payement vide alors
 *     qu'un doublon en porte un : trier seulement par id perdrait le choix
 *     deja enregistre par la caisse ;
 *   - a egalite, l'id le plus grand, c'est-a-dire l'enregistrement le plus
 *     recent.
 *
 * Les doublons ne sont PAS supprimes : 60 couples financiers et 10 couples de
 * session portent des valeurs divergentes, un dedoublonnage automatique
 * detruirait une information que seul un humain peut arbitrer.
 *
 * @param int|string $session_id
 * @return array<string, mixed>|false
 */
function inscFinanceSession(PDO $dtb, string $student_id, $session_id)
{
    if (trim($student_id) === '' || $session_id === '' || $session_id === null) {
        return false;
    }

    $req = $dtb->prepare(
        'SELECT * FROM t_2024_etudiant_finace
          WHERE student_id = :student_id
            AND session_id = :session_id
            AND `remove` != 1
          ORDER BY (mode_payement IS NOT NULL AND mode_payement <> "") DESC, id DESC
          LIMIT 1'
    );
    $req->execute(['student_id' => $student_id, 'session_id' => $session_id]);

    return $req->fetch(PDO::FETCH_ASSOC);
}

/**
 * Un etudiant a-t-il deja suivi une AUTRE session que celle-ci ? Une inscription
 * (t_2024_inscription_session) ou des notes (t_2023_notes) : les notes remontent
 * avant la table des inscriptions, qui n'a ni 2022-2023 ni 2023-2024.
 */
function inscEtudiantAHistorique(PDO $dtb, string $student_id, int $session_id): bool
{
    $req = $dtb->prepare('SELECT EXISTS (SELECT 1 FROM t_2024_inscription_session WHERE student_id = :s1 AND session_id != :ses1)
                              OR EXISTS (SELECT 1 FROM t_2023_notes WHERE student_id = :s2 AND session_id != :ses2)');
    $req->execute(['s1' => $student_id, 'ses1' => $session_id, 's2' => $student_id, 'ses2' => $session_id]);
    return (bool) $req->fetchColumn();
}

/**
 * Nouvel etudiant (badge « Nouveau » du guichet ; jamais penalise aux inscriptions
 * tardives) : 1 ou 0, la valeur du drapeau new_student.
 *
 * Ancien des qu'il a suivi une autre session (inscEtudiantAHistorique) ; sinon
 * nouveau, sauf s'il a ete marque « ancien » (« Type etudiant ») : ce choix tient.
 *
 * Corrige le 20/10/2026 (10852, signale : « je le change en ancien etudiant mais il
 * se remet en nouveau ») : le guichet, l'ajout de cours et « Modifier l'etudiant »
 * recalculaient le drapeau sur le seul nombre d'inscriptions (moins de deux :
 * nouveau), sans les notes des annees d'avant la table, et ecrasaient le choix.
 *
 * @param int|string|null $choix 0 (ancien) ou 1 (nouveau) choisi ou deja enregistre ; null : aucun
 */
function inscNouvelEtudiant(PDO $dtb, string $student_id, int $session_id, $choix = null): int
{
    if (inscEtudiantAHistorique($dtb, $student_id, $session_id)) { return 0; }
    return $choix !== null && $choix !== '' && (int) $choix === 0 ? 0 : 1;
}
