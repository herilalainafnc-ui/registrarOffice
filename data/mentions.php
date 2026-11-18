<?php
/**
 * Mentions (table filiere) : lecture, usages et ecritures gardees.
 *
 * POURQUOI DES GARDE-FOUS PLUTOT QU'UN SIMPLE CRUD
 * ------------------------------------------------
 * La table filiere n'a aucune contrainte de cle etrangere, mais son sigle sert
 * de cle dans cinq tables et son libelle dans une sixieme. Releve en base au
 * moment de l'ecriture de ce fichier :
 *
 *   SIGLE (filiere_sigle)
 *     t_2024_inscription_session.etude_mention   1 864 lignes
 *     t_2024_etudiant_finace.mention             1 363 lignes
 *     t_2023_cours.dep_desc                        663 lignes
 *     t_2024_finance_detail_licence.std_mention     192 lignes
 *     filiere_parcours.departement                   20 lignes
 *
 *   LIBELLE (filiere_description)
 *     tbl_2024_etudiant.etude_envisage           2 120 etudiants
 *
 * Renommer un sigle orphelinerait donc plus de quatre mille lignes en silence.
 * Le sigle est en consequence IMMUABLE des qu'il est utilise.
 *
 * Le libelle, lui, est compare a etude_envisage par plusieurs requetes
 * (generate.student.php, updateFinance.php : « WHERE filiere_description =
 * :etude_envisage »). Le renommer sans toucher aux fiches etudiantes ferait
 * echouer la recherche du bareme. Le renommage propage donc la nouvelle valeur
 * aux fiches concernees, dans la meme transaction.
 */

require_once __DIR__ . '/parcours.php'; // parcoursMentionVersSigle()

/* =============================================================================
   OUVERTURE / FERMETURE D'UNE MENTION

   LE VOCABULAIRE, ET POURQUOI CELUI-LA
   ------------------------------------
   Une mention qui n'accueille personne en ce moment est dite FERMEE, par
   opposition a OUVERTE. Ni « archivee », qui suggere une fin definitive alors
   que la bascule se reprend a tout moment ; ni « masquee », qui laisserait
   croire a un simple effet d'affichage alors que la mention cesse reellement
   d'etre proposee. « Fermee aux inscriptions » est aussi le terme d'usage en
   administration universitaire.

   CE QUE LA FERMETURE CHANGE
   --------------------------
   La mention disparait des listes ou l'on CHOISIT une mention pour une donnee
   nouvelle :
     - src/creat.student.php     creation d'un etudiant
     - src/cours/information.php rattachement d'un cours a une mention
     - landing/pages/admissions.php  pre-inscription publique (refus explicite)

   CE QUE LA FERMETURE NE CHANGE PAS
   ---------------------------------
   Elle reste visible partout ou des donnees existantes s'y referent : fiches
   etudiantes, releves de notes, statistiques, filtres d'impression.
   Theologie compte 597 etudiants et 736 inscriptions : les faire
   disparaitre des filtres empecherait d'imprimer leurs listes. La fermeture
   arrete le recrutement, elle n'efface pas l'histoire.

   Exception demandee par le registrariat (15/09/2026) : la page /finance
   (src/finance/bareme.grille.php) n'affiche que les mentions ouvertes. Le
   bareme d'une mention fermee reste en base, continue de servir aux
   etudiants deja inscrits et reapparait a la reouverture.

   La colonne est ajoutee par data/migration_filiere_ouverte.sql. Les
   fonctions ci-dessous se replient sur « toutes ouvertes » si la migration
   n'a pas encore ete jouee, pour que l'application ne casse pas entre le
   deploiement des fichiers et celui de la base.
   ============================================================================= */

/**
 * La colonne `ouverte` existe-t-elle ?
 *
 * Teste une seule fois par requete. Sans ce repli, deployer les fichiers avant
 * la migration ferait echouer toutes les lectures de mentions — donc la
 * creation d'etudiants et l'inscription.
 */
function mentionsOuvertureDisponible(?PDO $dtb = null): bool
{
    static $dispo = null;
    if ($dispo !== null) {
        return $dispo;
    }
    /* Appelee sans connexion (mentionsColonneOuverte, mentionsFiltreOuvertes) :
       on reprend celle de la page plutot que de conclure « colonne absente »,
       ce qui affichait toutes les mentions comme ouvertes. */
    if ($dtb === null) {
        $dtb = ($GLOBALS['dtb'] ?? null) instanceof PDO ? $GLOBALS['dtb'] : null;
    }
    if ($dtb === null) {
        return false;
    }

    try {
        $dispo = (bool) $dtb->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'filiere'
                AND COLUMN_NAME = 'ouverte'"
        )->fetchColumn();
    } catch (Throwable $e) {
        $dispo = false;
    }

    return $dispo;
}

/**
 * Expression SQL de l'etat d'ouverture : la colonne si elle existe, 1 sinon.
 *
 * Permet d'ecrire une seule version de chaque requete.
 */
function mentionsColonneOuverte(): string
{
    return mentionsOuvertureDisponible() ? 'ouverte' : '1';
}

/**
 * Fragment SQL a coller dans un WHERE pour ne garder que les mentions
 * ouvertes. Destine aux LISTES DE CHOIX, jamais aux affichages de donnees
 * existantes.
 *
 * Usage :
 *   $sql = 'SELECT * FROM filiere WHERE ' . mentionsFiltreOuvertes() . ' ORDER BY …';
 */
function mentionsFiltreOuvertes(): string
{
    return mentionsOuvertureDisponible() ? 'ouverte = 1' : '1';
}

/** Une mention est-elle ouverte aux nouvelles inscriptions ? */
function mentionEstOuverte(PDO $dtb, string $sigle): bool
{
    if (!mentionsOuvertureDisponible($dtb)) {
        return true;
    }

    $req = $dtb->prepare('SELECT ouverte FROM filiere WHERE filiere_sigle = :s LIMIT 1');
    $req->execute(['s' => $sigle]);
    $etat = $req->fetchColumn();

    // Mention inconnue : ce n'est pas a cette fonction de le signaler.
    return $etat === false ? true : (bool) (int) $etat;
}

/**
 * Sigles des mentions ouvertes.
 *
 * @return array<int, string>
 */
function mentionsSiglesOuverts(PDO $dtb): array
{
    if (!mentionsOuvertureDisponible($dtb)) {
        return $dtb->query('SELECT filiere_sigle FROM filiere')->fetchAll(PDO::FETCH_COLUMN);
    }

    return $dtb->query('SELECT filiere_sigle FROM filiere WHERE ouverte = 1')
               ->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Ouvre ou ferme une mention.
 *
 * Reversible et sans effet sur les donnees : rien n'est supprime, rien n'est
 * renomme. C'est ce qui la distingue de la suppression, refusee des qu'une
 * donnee s'y rattache.
 *
 * @return array{ok:bool, message:string, ouverte?:bool}
 */
function mentionsBasculerOuverture(PDO $dtb, string $sigle, bool $ouvrir, ?int $userId = null): array
{
    $mention = mentionParSigle($dtb, $sigle);
    if ($mention === false) {
        return ['ok' => false, 'message' => 'Mention inconnue : ' . $sigle];
    }

    if (!mentionsOuvertureDisponible($dtb)) {
        return ['ok' => false,
                'message' => "La colonne d'ouverture n'existe pas encore sur cette base. "
                    . 'Appliquez data/migration_filiere_ouverte.sql.'];
    }

    $req = $dtb->prepare('UPDATE filiere SET ouverte = :o,
            last_change_user_id = :uid, last_change_datetime = NOW()
          WHERE filiere_sigle = :s');
    $req->execute([
        'o'   => $ouvrir ? 1 : 0,
        'uid' => $userId !== null ? (string) $userId : null,
        's'   => $sigle,
    ]);

    $nom = (string) $mention['filiere_description'];

    return [
        'ok' => true,
        'ouverte' => $ouvrir,
        'message' => $ouvrir
            ? $nom . ' est de nouveau ouverte aux inscriptions.'
            : $nom . ' est fermée : elle ne sera plus proposée pour un nouvel étudiant, '
                . 'un nouveau cours ni une pré-inscription. Les données existantes ne changent pas.',
    ];
}

/** Colonnes modifiables par la page, et leur libelle. */
function mentionsChamps(): array
{
    return [
        'filiere_description'     => 'Libellé (français)',
        'filiere_description_ang' => 'Libellé (anglais)',
        'domaine_signe'           => 'Sigle du domaine',
        'domaine_name'            => 'Domaine',
    ];
}

/**
 * Tables referencant le SIGLE, avec leur colonne.
 *
 * @return array<string, string>
 */
function mentionsDependancesSigle(): array
{
    return [
        't_2024_inscription_session'    => 'etude_mention',
        't_2024_etudiant_finace'        => 'mention',
        't_2023_cours'                  => 'dep_desc',
        't_2024_finance_detail_licence' => 'std_mention',
        'filiere_parcours'              => 'departement',
    ];
}

/** Libelles lisibles des dependances, pour les messages. */
function mentionsLibellesDependances(): array
{
    return [
        't_2024_inscription_session'    => 'inscription(s)',
        't_2024_etudiant_finace'        => 'ligne(s) financière(s)',
        't_2023_cours'                  => 'cours',
        't_2024_finance_detail_licence' => 'ligne(s) de barème',
        'filiere_parcours'              => 'parcours',
        'tbl_2024_etudiant'             => 'étudiant(s)',
    ];
}

/**
 * Nombre de lignes qui dependent d'une mention, par table.
 *
 * @return array<string, int> table => nombre, plus 'total'
 */
function mentionsUsage(PDO $dtb, string $sigle, ?string $libelle = null): array
{
    $usage = [];
    $total = 0;

    foreach (mentionsDependancesSigle() as $table => $colonne) {
        try {
            $req = $dtb->prepare("SELECT COUNT(*) FROM `$table` WHERE `$colonne` = :s");
            $req->execute(['s' => $sigle]);
            $n = (int) $req->fetchColumn();
        } catch (Throwable $e) {
            // Table absente sur cette base : on ne bloque pas pour autant.
            $n = 0;
        }
        $usage[$table] = $n;
        $total += $n;
    }

    /* Les fiches etudiantes se rattachent par le LIBELLE, pas par le sigle.
       Comptees a part : elles n'empechent pas de renommer, elles indiquent
       combien de fiches devront suivre. */
    $usage['tbl_2024_etudiant'] = 0;
    if ($libelle !== null && trim($libelle) !== '') {
        $req = $dtb->prepare('SELECT COUNT(*) FROM tbl_2024_etudiant
            WHERE etude_envisage = :l AND remove != 1');
        $req->execute(['l' => $libelle]);
        $usage['tbl_2024_etudiant'] = (int) $req->fetchColumn();
    }

    $usage['total'] = $total;
    $usage['total_avec_etudiants'] = $total + $usage['tbl_2024_etudiant'];

    return $usage;
}

/**
 * Toutes les mentions, enrichies de leurs usages.
 *
 * @return array<int, array<string, mixed>>
 */
function mentionsListe(PDO $dtb): array
{
    /* Initialise la detection de la colonne AVANT mentionsColonneOuverte() :
       appelee sans connexion, elle concluait « colonne absente » et renvoyait
       1 pour toutes les mentions. La page affichait alors chaque mention comme
       ouverte, meme fermee en base, et « Fermer » semblait sans effet. */
    mentionsOuvertureDisponible($dtb);

    $lignes = $dtb->query(
        'SELECT filiere_id, filiere_sigle, filiere_description, filiere_description_ang,
                domaine_signe, domaine_name, ' . mentionsColonneOuverte() . ' AS ouverte,
                last_change_user_id, last_change_datetime
           FROM filiere
          ORDER BY ' . mentionsColonneOuverte() . ' DESC, filiere_description'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lignes as &$l) {
        $l['usage'] = mentionsUsage($dtb, (string) $l['filiere_sigle'], (string) $l['filiere_description']);
        $l['sigle_modifiable'] = $l['usage']['total'] === 0;
        $l['supprimable'] = $l['usage']['total_avec_etudiants'] === 0;

        /* Visible sur le formulaire public de pre-inscription ?
           La liste y etait ecrite en dur a trois endroits, si bien qu'ajouter
           une mention ici ne la faisait PAS apparaitre sur le site public.
           Elle vient maintenant de cette table (data/parcours.php) : une
           mention ouverte y figure, une mention fermee n'y figure plus. Le
           drapeau ne dit donc plus rien d'autre que l'ouverture, mais la page
           continue de l'afficher — c'est une question differente pour qui la
           lit, et la reponse se trouve au meme endroit. */
        $l['publique'] = (int) $l['ouverte'] === 1;
    }
    unset($l);

    return $lignes;
}

/** Une mention par son sigle, ou false. */
function mentionParSigle(PDO $dtb, string $sigle)
{
    $req = $dtb->prepare('SELECT * FROM filiere WHERE filiere_sigle = :s LIMIT 1');
    $req->execute(['s' => $sigle]);

    return $req->fetch(PDO::FETCH_ASSOC);
}

/**
 * Un sigle est-il recevable ?
 *
 * Quatre caracteres au plus (la colonne est un varchar(4)), lettres majuscules
 * uniquement : c'est la forme de tous les sigles existants, et elle entre dans
 * des cles de cinq tables.
 *
 * @return string|null message d'erreur, ou null si le sigle convient
 */
function mentionsValiderSigle(string $sigle): ?string
{
    $sigle = trim($sigle);

    if ($sigle === '') {
        return 'Le sigle est obligatoire.';
    }
    if (!preg_match('/^[A-Z]{2,4}$/', $sigle)) {
        return 'Le sigle doit comporter de 2 à 4 lettres majuscules, sans accent ni chiffre.';
    }

    return null;
}

/**
 * Cree une mention.
 *
 * @param array<string, string> $valeurs
 * @return array{ok:bool, message:string, sigle?:string}
 */
function mentionsCreer(PDO $dtb, string $sigle, array $valeurs, ?int $userId = null): array
{
    $sigle = strtoupper(trim($sigle));

    $erreur = mentionsValiderSigle($sigle);
    if ($erreur !== null) {
        return ['ok' => false, 'message' => $erreur];
    }

    if (mentionParSigle($dtb, $sigle) !== false) {
        return ['ok' => false, 'message' => 'Le sigle ' . $sigle . ' existe déjà.'];
    }

    $libelle = trim($valeurs['filiere_description'] ?? '');
    if ($libelle === '') {
        return ['ok' => false, 'message' => 'Le libellé en français est obligatoire.'];
    }

    /* Le libelle sert a rattacher les fiches etudiantes : deux mentions
       homonymes rendraient ce rattachement ambigu. */
    $doublon = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = :l LIMIT 1');
    $doublon->execute(['l' => $libelle]);
    $existant = $doublon->fetchColumn();
    if ($existant !== false) {
        return ['ok' => false,
                'message' => 'Le libellé « ' . $libelle . ' » est déjà porté par ' . $existant
                    . '. Les fiches étudiantes se rattachent par le libellé : deux mentions '
                    . 'ne peuvent pas partager le même.'];
    }

    $req = $dtb->prepare(
        'INSERT INTO filiere
            (filiere_sigle, filiere_description, filiere_description_ang,
             domaine_signe, domaine_name, last_change_user_id, last_change_datetime)
         VALUES (:sigle, :fr, :en, :dsigle, :dnom, :uid, NOW())'
    );
    $req->execute([
        'sigle'  => $sigle,
        'fr'     => $libelle,
        'en'     => trim($valeurs['filiere_description_ang'] ?? ''),
        'dsigle' => trim($valeurs['domaine_signe'] ?? ''),
        'dnom'   => trim($valeurs['domaine_name'] ?? ''),
        'uid'    => $userId !== null ? (string) $userId : null,
    ]);

    return ['ok' => true, 'sigle' => $sigle,
            'message' => 'Mention ' . $sigle . ' créée.'];
}

/**
 * Modifie une mention.
 *
 * Le sigle n'est jamais modifie ici : il est immuable des qu'il est utilise, et
 * le changer sur une mention inutilisee revient a la recreer. Le libelle, lui,
 * peut etre propage aux fiches etudiantes qui s'y rattachent.
 *
 * @param array<string, string> $valeurs
 * @return array{ok:bool, message:string, fiches_mises_a_jour?:int}
 */
function mentionsEnregistrer(PDO $dtb, string $sigle, array $valeurs, ?int $userId = null, bool $propagerLibelle = true): array
{
    $mention = mentionParSigle($dtb, $sigle);
    if ($mention === false) {
        return ['ok' => false, 'message' => 'Mention inconnue : ' . $sigle];
    }

    $libelleAvant = (string) $mention['filiere_description'];
    $libelleApres = trim($valeurs['filiere_description'] ?? $libelleAvant);

    if ($libelleApres === '') {
        return ['ok' => false, 'message' => 'Le libellé en français est obligatoire.'];
    }

    if ($libelleApres !== $libelleAvant) {
        $doublon = $dtb->prepare('SELECT filiere_sigle FROM filiere
            WHERE filiere_description = :l AND filiere_sigle <> :s LIMIT 1');
        $doublon->execute(['l' => $libelleApres, 's' => $sigle]);
        $autre = $doublon->fetchColumn();
        if ($autre !== false) {
            return ['ok' => false,
                    'message' => 'Le libellé « ' . $libelleApres . ' » est déjà porté par ' . $autre . '.'];
        }
    }

    $dtb->beginTransaction();
    try {
        $req = $dtb->prepare(
            'UPDATE filiere SET
                filiere_description = :fr,
                filiere_description_ang = :en,
                domaine_signe = :dsigle,
                domaine_name = :dnom,
                last_change_user_id = :uid,
                last_change_datetime = NOW()
              WHERE filiere_sigle = :sigle'
        );
        $req->execute([
            'fr'     => $libelleApres,
            'en'     => trim($valeurs['filiere_description_ang'] ?? (string) $mention['filiere_description_ang']),
            'dsigle' => trim($valeurs['domaine_signe'] ?? (string) $mention['domaine_signe']),
            'dnom'   => trim($valeurs['domaine_name'] ?? (string) $mention['domaine_name']),
            'uid'    => $userId !== null ? (string) $userId : null,
            'sigle'  => $sigle,
        ]);

        /* Propagation du libelle aux fiches etudiantes.
           Sans elle, « WHERE filiere_description = :etude_envisage » ne trouve
           plus rien et la recherche du bareme echoue a l'inscription. */
        $fiches = 0;
        if ($propagerLibelle && $libelleApres !== $libelleAvant) {
            $maj = $dtb->prepare('UPDATE tbl_2024_etudiant
                SET etude_envisage = :apres WHERE etude_envisage = :avant');
            $maj->execute(['apres' => $libelleApres, 'avant' => $libelleAvant]);
            $fiches = $maj->rowCount();
        }

        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }

    $message = 'Mention ' . $sigle . ' enregistrée.';
    if ($libelleApres !== $libelleAvant) {
        $message = 'Mention ' . $sigle . ' renommée en « ' . $libelleApres . ' »'
            . ($fiches > 0 ? ', ' . $fiches . ' fiche(s) étudiante(s) mise(s) à jour.' : '.');
    }

    return ['ok' => true, 'message' => $message, 'fiches_mises_a_jour' => $fiches];
}

/**
 * Supprime une mention, uniquement si rien ne s'y rattache.
 *
 * @return array{ok:bool, message:string}
 */
function mentionsSupprimer(PDO $dtb, string $sigle): array
{
    $mention = mentionParSigle($dtb, $sigle);
    if ($mention === false) {
        return ['ok' => false, 'message' => 'Mention inconnue : ' . $sigle];
    }

    $usage = mentionsUsage($dtb, $sigle, (string) $mention['filiere_description']);
    if ($usage['total_avec_etudiants'] > 0) {
        $libelles = mentionsLibellesDependances();
        $details = [];
        foreach ($usage as $table => $n) {
            if ($table === 'total' || $table === 'total_avec_etudiants' || $n === 0) { continue; }
            $details[] = $n . ' ' . ($libelles[$table] ?? $table);
        }

        return ['ok' => false,
                'message' => 'Suppression impossible : ' . $mention['filiere_description']
                    . ' est utilisée par ' . implode(', ', $details)
                    . '. Ces données feraient référence à une mention inexistante.'];
    }

    $req = $dtb->prepare('DELETE FROM filiere WHERE filiere_sigle = :s');
    $req->execute(['s' => $sigle]);

    return ['ok' => true,
            'message' => 'Mention ' . $mention['filiere_description'] . ' supprimée.'];
}

/**
 * Sigles references par filiere_parcours sans exister dans filiere.
 *
 * Releve : GENE (« Tronc commun ») est dans filiere_parcours sans etre une
 * mention. Le signaler plutot que de le laisser invisible.
 *
 * @return array<int, array{sigle:string, parcours:int}>
 */
function mentionsOrphelines(PDO $dtb): array
{
    try {
        $q = $dtb->query(
            'SELECT fp.departement AS sigle, COUNT(*) AS parcours
               FROM filiere_parcours fp
               LEFT JOIN filiere f ON f.filiere_sigle = fp.departement
              WHERE f.filiere_id IS NULL AND fp.departement <> ""
              GROUP BY fp.departement ORDER BY fp.departement'
        );

        return $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/* =============================================================================
   PARCOURS (table filiere_parcours)

   Un parcours appartient a une mention par sa colonne departement, qui porte le
   sigle de la mention.

   DEUX FORMES DE REFERENCE, ET C'EST LE POINT DELICAT
   ---------------------------------------------------
   Releve en base :

     t_2023_cours.parcours                      le SHORTCODE   324 cours
     tbl_2024_etudiant.etude_option             la DESCRIPTION 1 714 etudiants
     t_2024_inscription_session.parcours_std    la DESCRIPTION 1 362 inscriptions

   Le shortcode est donc immuable des qu'un cours s'y rattache, et renommer une
   description sans propager casse les jointures de src/student/new.cours.php,
   src/student.php, src/extenssionPrint/cheklist.php et
   app/.student/get-cours-list.php, qui font toutes
   « WHERE description = etude_option ».

   VALEUR RESERVEE
   ---------------
   335 cours portent parcours = 'all', qui signifie « tous les parcours de la
   mention » et n'est pas un shortcode. Aucun parcours ne peut donc s'appeler
   ainsi, sinon ces cours basculeraient silencieusement dans un parcours unique.
   ============================================================================= */

/** Valeur reservee par t_2023_cours.parcours : « tous les parcours ». */
const PARCOURS_TOUS = 'all';

/** Colonnes modifiables d'un parcours, et leur libelle. */
/**
 * Cycles ou un parcours est propose.
 *
 * Les parcours de master ne sont pas ceux de la licence. Jusqu'ici la table ne
 * le disait pas : les mêmes parcours etaient proposes aux deux cycles, et l'on
 * retrouve par exemple « Systemes d'information et genie logiciel » chez 194
 * licencies et 26 masters (releve du 23/09/2026). La colonne `cycles` porte
 * desormais cette distinction.
 *
 * Les lignes existantes valent « L,M » : sans reglage, tout reste propose
 * partout, exactement comme avant.
 *
 * @return array<string, string> valeur stockee => libelle affiche
 */
function mentionParcoursCycles(): array
{
    return [
        'L,M' => 'Licence et master',
        'L'   => 'Licence seulement',
        'M'   => 'Master seulement',
    ];
}

/** Valeur de cycle propre : « L », « M » ou « L,M ». */
function mentionParcoursCyclesValides($valeur): string
{
    $valeur = strtoupper((string) $valeur);
    $licence = strpos($valeur, 'L') !== false;
    $master  = strpos($valeur, 'M') !== false;

    if ($licence && $master) { return 'L,M'; }
    if ($master)             { return 'M'; }
    if ($licence)            { return 'L'; }

    /* Valeur vide ou illisible : on ne restreint rien. Masquer un parcours par
       accident se verrait au guichet, pas dans un journal. */
    return 'L,M';
}

/** Cycle d'un niveau d'etude : L jusqu'a 3, M au-dela — coupure du bareme. */
function mentionParcoursCycleDuNiveau($niveau): string
{
    return (int) $niveau >= 4 ? 'M' : 'L';
}

/**
 * Ce parcours est-il propose a ce niveau ?
 *
 * @param array<string, mixed> $parcours ligne de filiere_parcours
 * @param int|string|null      $niveau   null = aucun filtre
 */
function mentionParcoursApplique(array $parcours, $niveau): bool
{
    if ($niveau === null || $niveau === '') {
        return true;
    }

    $cycles = mentionParcoursCyclesValides($parcours['cycles'] ?? 'L,M');

    return strpos($cycles, mentionParcoursCycleDuNiveau($niveau)) !== false;
}

/**
 * Ajoute la colonne `cycles` si elle manque.
 *
 * Meme principe que l'installeur des frais supplementaires : la fonctionnalite
 * s'installe a la premiere lecture, sans intervention en base.
 * data/migration_parcours_cycles.sql porte le meme changement, pour qui
 * prefere l'appliquer a la main.
 */
function mentionParcoursInstaller(PDO $dtb): void
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;

    $presente = (int) $dtb->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'filiere_parcours'
            AND COLUMN_NAME = 'cycles'"
    )->fetchColumn();

    if ($presente === 0) {
        $dtb->exec("ALTER TABLE filiere_parcours
            ADD COLUMN cycles VARCHAR(10) NOT NULL DEFAULT 'L,M' AFTER departement");
    }
}
function mentionParcoursChamps(): array
{
    return [
        'description'     => 'Libellé (français)',
        'description_ang' => 'Libellé (anglais)',
    ];
}

/**
 * Nombre de lignes dependant d'un parcours.
 *
 * Le shortcode et la description sont comptes separement : ils verrouillent des
 * choses differentes. Un cours empeche de changer le shortcode ; un etudiant ou
 * une inscription obligent a propager un renommage.
 *
 * @return array<string, int>
 */
function mentionParcoursUsage(PDO $dtb, string $shortcode, string $description): array
{
    $compter = static function (PDO $dtb, string $sql, string $valeur): int {
        try {
            $req = $dtb->prepare($sql);
            $req->execute(['v' => $valeur]);

            return (int) $req->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };

    $cours = $compter($dtb, 'SELECT COUNT(*) FROM t_2023_cours WHERE parcours = :v', $shortcode);
    $etudiants = $description === '' ? 0 : $compter(
        $dtb,
        'SELECT COUNT(*) FROM tbl_2024_etudiant WHERE etude_option = :v AND `remove` != 1',
        $description
    );
    $inscriptions = $description === '' ? 0 : $compter(
        $dtb,
        'SELECT COUNT(*) FROM t_2024_inscription_session WHERE parcours_std = :v AND `remove` != 1',
        $description
    );

    return [
        't_2023_cours'               => $cours,
        'tbl_2024_etudiant'          => $etudiants,
        't_2024_inscription_session' => $inscriptions,
        // Le shortcode n'est verrouille que par les cours.
        'shortcode_verrouille'       => $cours > 0,
        // Le libellé est suivi par les fiches et les inscriptions.
        'libelle_suivi'              => $etudiants + $inscriptions,
        'total'                      => $cours + $etudiants + $inscriptions,
    ];
}

/**
 * Parcours d'une mention, ou tous si le sigle est omis.
 *
 * @return array<int, array<string, mixed>>
 */
function mentionParcoursListe(PDO $dtb, ?string $sigle = null, $niveau = null): array
{
    mentionParcoursInstaller($dtb);

    if ($sigle !== null) {
        $req = $dtb->prepare('SELECT * FROM filiere_parcours WHERE departement = :d ORDER BY description');
        $req->execute(['d' => $sigle]);
    } else {
        $req = $dtb->query('SELECT * FROM filiere_parcours ORDER BY departement, description');
    }

    $lignes = $req->fetchAll(PDO::FETCH_ASSOC);

    /* Filtre par cycle : un parcours reserve au master n'a rien a faire
       dans la liste d'un licencie, et inversement. */
    if ($niveau !== null && $niveau !== '') {
        $lignes = array_values(array_filter(
            $lignes,
            static fn (array $p): bool => mentionParcoursApplique($p, $niveau)
        ));
    }

    foreach ($lignes as &$p) {
        $p['cycles'] = mentionParcoursCyclesValides($p['cycles'] ?? 'L,M');
        $p['usage'] = mentionParcoursUsage($dtb, (string) $p['shortcode'], (string) $p['description']);
        $p['supprimable'] = $p['usage']['total'] === 0;
    }
    unset($p);

    return $lignes;
}

/** Un parcours par son shortcode, ou false. */
function mentionParcoursParCode(PDO $dtb, string $shortcode)
{
    $req = $dtb->prepare('SELECT * FROM filiere_parcours WHERE shortcode = :s LIMIT 1');
    $req->execute(['s' => $shortcode]);

    return $req->fetch(PDO::FETCH_ASSOC);
}

/**
 * Un shortcode est-il recevable ?
 *
 * La colonne est un varchar(20) et sert de cle primaire. Tous les shortcodes
 * existants suivent la forme MAJUSCULES et tirets bas (C_COM_ENTR, TC).
 *
 * @return string|null message d'erreur, ou null
 */
function mentionParcoursValiderCode(string $shortcode): ?string
{
    $shortcode = trim($shortcode);

    if ($shortcode === '') {
        return 'Le code est obligatoire.';
    }
    if (strcasecmp($shortcode, PARCOURS_TOUS) === 0) {
        return 'Le code « ' . PARCOURS_TOUS . ' » est réservé : sur un cours, il signifie '
            . '« tous les parcours de la mention ». 335 cours l\'utilisent.';
    }
    if (!preg_match('/^[A-Z0-9_]{2,20}$/', $shortcode)) {
        return 'Le code doit comporter de 2 à 20 caractères : majuscules, chiffres et '
            . 'tirets bas, sans accent ni espace.';
    }

    return null;
}

/**
 * Cree un parcours dans une mention.
 *
 * @param array<string, string> $valeurs
 * @return array{ok:bool, message:string, shortcode?:string}
 */
function mentionParcoursCreer(PDO $dtb, string $sigle, string $shortcode, array $valeurs, ?int $userId = null): array
{
    $sigle = strtoupper(trim($sigle));
    $shortcode = strtoupper(trim($shortcode));

    if (mentionParSigle($dtb, $sigle) === false) {
        return ['ok' => false, 'message' => 'Mention inconnue : ' . $sigle];
    }

    $erreur = mentionParcoursValiderCode($shortcode);
    if ($erreur !== null) {
        return ['ok' => false, 'message' => $erreur];
    }

    if (mentionParcoursParCode($dtb, $shortcode) !== false) {
        return ['ok' => false, 'message' => 'Le code ' . $shortcode . ' existe déjà.'];
    }

    $libelle = trim($valeurs['description'] ?? '');
    if ($libelle === '') {
        return ['ok' => false, 'message' => 'Le libellé en français est obligatoire.'];
    }

    /* Le libelle rattache les fiches etudiantes : deux parcours homonymes
       rendraient ce rattachement ambigu, quelle que soit leur mention. */
    $doublon = $dtb->prepare('SELECT shortcode, departement FROM filiere_parcours
        WHERE description = :l LIMIT 1');
    $doublon->execute(['l' => $libelle]);
    $autre = $doublon->fetch(PDO::FETCH_ASSOC);
    if ($autre !== false) {
        return ['ok' => false,
                'message' => 'Le libellé « ' . $libelle . ' » est déjà porté par '
                    . $autre['shortcode'] . ' (' . $autre['departement'] . '). Les fiches '
                    . 'étudiantes se rattachent par le libellé : deux parcours ne peuvent '
                    . 'pas partager le même.'];
    }

    /* La colonne id est NOT NULL, sans valeur par defaut et sans
       auto-increment : elle doit etre fournie. Le vrai identifiant est le
       shortcode, qui porte la cle primaire. */
    $prochain = (int) $dtb->query('SELECT COALESCE(MAX(id), -1) + 1 FROM filiere_parcours')->fetchColumn();

    mentionParcoursInstaller($dtb);

    $req = $dtb->prepare(
        'INSERT INTO filiere_parcours
            (id, shortcode, description, description_ang, departement, cycles,
             date_entry, last_change_user_id, last_change_datetime)
         VALUES (:id, :code, :fr, :en, :dep, :cycles, NOW(), :uid, NOW())'
    );
    $req->execute([
        'id'   => $prochain,
        'code' => $shortcode,
        'fr'   => $libelle,
        'en'   => trim($valeurs['description_ang'] ?? ''),
        'dep'  => $sigle,
        'cycles' => mentionParcoursCyclesValides($valeurs['cycles'] ?? 'L,M'),
        'uid'  => $userId !== null ? (string) $userId : null,
    ]);

    return ['ok' => true, 'shortcode' => $shortcode,
            'message' => 'Parcours « ' . $libelle . ' » créé.'];
}

/**
 * Modifie un parcours.
 *
 * Le shortcode n'est jamais modifie ici : 324 cours s'y rattachent. Le
 * libelle, lui, est propage aux fiches etudiantes et aux inscriptions qui s'y
 * rattachent — sans quoi les jointures « WHERE description = etude_option »
 * ne trouveraient plus rien.
 *
 * @param array<string, string> $valeurs
 * @return array{ok:bool, message:string, fiches?:int, inscriptions?:int}
 */
function mentionParcoursEnregistrer(PDO $dtb, string $shortcode, array $valeurs, ?int $userId = null, bool $propager = true): array
{
    mentionParcoursInstaller($dtb);

    $parcours = mentionParcoursParCode($dtb, $shortcode);
    if ($parcours === false) {
        return ['ok' => false, 'message' => 'Parcours inconnu : ' . $shortcode];
    }

    $avant = (string) $parcours['description'];
    $apres = trim($valeurs['description'] ?? $avant);

    if ($apres === '') {
        return ['ok' => false, 'message' => 'Le libellé en français est obligatoire.'];
    }

    if ($apres !== $avant) {
        $doublon = $dtb->prepare('SELECT shortcode FROM filiere_parcours
            WHERE description = :l AND shortcode <> :s LIMIT 1');
        $doublon->execute(['l' => $apres, 's' => $shortcode]);
        $autre = $doublon->fetchColumn();
        if ($autre !== false) {
            return ['ok' => false,
                    'message' => 'Le libellé « ' . $apres . ' » est déjà porté par ' . $autre . '.'];
        }
    }

    $dtb->beginTransaction();
    try {
        $req = $dtb->prepare(
            'UPDATE filiere_parcours SET
                description = :fr,
                description_ang = :en,
                cycles = :cycles,
                last_change_user_id = :uid,
                last_change_datetime = NOW()
              WHERE shortcode = :s'
        );
        $req->execute([
            'fr'  => $apres,
            'en'  => trim($valeurs['description_ang'] ?? (string) $parcours['description_ang']),
            /* Cycle absent du formulaire : on garde celui en place plutot que
               de tout rouvrir sans le vouloir. */
            'cycles' => mentionParcoursCyclesValides($valeurs['cycles'] ?? ($parcours['cycles'] ?? 'L,M')),
            'uid' => $userId !== null ? (string) $userId : null,
            's'   => $shortcode,
        ]);

        $fiches = 0;
        $inscriptions = 0;
        if ($propager && $apres !== $avant) {
            $majE = $dtb->prepare('UPDATE tbl_2024_etudiant
                SET etude_option = :apres WHERE etude_option = :avant');
            $majE->execute(['apres' => $apres, 'avant' => $avant]);
            $fiches = $majE->rowCount();

            $majI = $dtb->prepare('UPDATE t_2024_inscription_session
                SET parcours_std = :apres WHERE parcours_std = :avant');
            $majI->execute(['apres' => $apres, 'avant' => $avant]);
            $inscriptions = $majI->rowCount();
        }

        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }

    $message = 'Parcours ' . $shortcode . ' enregistré.';
    if ($apres !== $avant) {
        $suivis = [];
        if ($fiches > 0) { $suivis[] = $fiches . ' fiche(s) étudiante(s)'; }
        if ($inscriptions > 0) { $suivis[] = $inscriptions . ' inscription(s)'; }
        $message = 'Parcours renommé en « ' . $apres . ' »'
            . ($suivis !== [] ? ', ' . implode(' et ', $suivis) . ' mise(s) à jour.' : '.');
    }

    return ['ok' => true, 'message' => $message,
            'fiches' => $fiches, 'inscriptions' => $inscriptions];
}

/**
 * Supprime un parcours, uniquement si rien ne s'y rattache.
 *
 * @return array{ok:bool, message:string}
 */
function mentionParcoursSupprimer(PDO $dtb, string $shortcode): array
{
    $parcours = mentionParcoursParCode($dtb, $shortcode);
    if ($parcours === false) {
        return ['ok' => false, 'message' => 'Parcours inconnu : ' . $shortcode];
    }

    $usage = mentionParcoursUsage($dtb, $shortcode, (string) $parcours['description']);
    if ($usage['total'] > 0) {
        $libelles = mentionsLibellesDependances();
        $details = [];
        foreach (['t_2023_cours', 'tbl_2024_etudiant', 't_2024_inscription_session'] as $table) {
            if ($usage[$table] > 0) {
                $details[] = $usage[$table] . ' ' . ($libelles[$table] ?? $table);
            }
        }

        return ['ok' => false,
                'message' => 'Suppression impossible : « ' . $parcours['description']
                    . ' » est utilisé par ' . implode(', ', $details)
                    . '. Ces données feraient référence à un parcours inexistant.'];
    }

    $req = $dtb->prepare('DELETE FROM filiere_parcours WHERE shortcode = :s');
    $req->execute(['s' => $shortcode]);

    return ['ok' => true,
            'message' => 'Parcours « ' . $parcours['description'] . ' » supprimé.'];
}

/**
 * Libelles de parcours portes par des etudiants sans correspondre a aucun
 * parcours.
 *
 * Releve : 382 etudiants, dont 251 avec « None », 74 avec « Communication des
 * entreprises » la ou la table dit « Communication en entreprise », et 35 avec
 * « Administration de l'education » la ou elle dit « Formation en
 * administration de l'education ». Pour ces fiches, les jointures par libelle
 * ne trouvent rien.
 *
 * @return array<int, array{libelle:string, etudiants:int}>
 */
function mentionParcoursLibellesOrphelins(PDO $dtb): array
{
    try {
        $q = $dtb->query(
            'SELECT e.etude_option AS libelle, COUNT(*) AS etudiants
               FROM tbl_2024_etudiant e
               LEFT JOIN filiere_parcours p ON p.description = e.etude_option
              WHERE e.remove != 1 AND p.shortcode IS NULL AND e.etude_option <> ""
              GROUP BY e.etude_option ORDER BY etudiants DESC'
        );

        return $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Libelles d'etude_envisage qui ne correspondent a aucune mention.
 *
 * Releve : « Arts in English Education », un etudiant. Sa fiche ne trouve
 * aucun bareme a l'inscription.
 *
 * @return array<int, array{libelle:string, etudiants:int}>
 */
function mentionsLibellesOrphelins(PDO $dtb): array
{
    try {
        $q = $dtb->query(
            'SELECT e.etude_envisage AS libelle, COUNT(*) AS etudiants
               FROM tbl_2024_etudiant e
               LEFT JOIN filiere f ON f.filiere_description = e.etude_envisage
              WHERE e.remove != 1 AND f.filiere_id IS NULL AND e.etude_envisage <> ""
              GROUP BY e.etude_envisage ORDER BY etudiants DESC'
        );

        return $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
