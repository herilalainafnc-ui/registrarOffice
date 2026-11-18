<?php
/**
 * Mentions et parcours proposes au public.
 *
 * TOUT VIENT DE /settings/mentions
 * --------------------------------
 * Les mentions etaient ecrites en dur a QUATRE endroits — le <select> de
 * landing/pages/_contenu-admissions.php, le tableau $mentions de
 * landing/pages/admissions.php, la correspondance de ce fichier, et celle de
 * src/creat.student.php qui convertit une candidature en etudiant. Creer une
 * mention dans /settings/mentions ne la faisait donc pas apparaitre sur le
 * site public, et en fermer une ne l'en retirait pas : seul un controle
 * serveur refusait la candidature, apres que le candidat avait tout saisi.
 *
 * Les parcours, eux, etaient bien lus en base, mais sans egard ni pour la
 * fermeture de leur mention ni pour leur cycle : un candidat au Master pouvait
 * choisir un parcours de licence.
 *
 * Desormais une seule source : `filiere` pour les mentions ouvertes,
 * `filiere_parcours` pour leurs parcours et leur cycle. Le <select>, la liste
 * deroulante des parcours et la validation cote serveur lisent la meme chose.
 *
 * UN DIPLOME N'EST PROPOSE QUE S'IL A UN PARCOURS
 * -----------------------------------------------
 * Une mention accepte le Master si et seulement si l'un de ses parcours est
 * marque « master » dans /settings/mentions ; de meme pour la Licence. La
 * liste des quatre mentions a Master, jusqu'ici ecrite en dur, se deduit donc
 * du reglage. Consequence a connaitre : une mention dont tous les parcours
 * sont marques « Licence seulement » cesse d'accepter des candidatures en
 * Master, et la page des reglages le signale.
 *
 * LA CLE PUBLIQUE EST STABLE
 * --------------------------
 * Le <select> envoie une cle (« informatique ») qui est ENREGISTREE dans la
 * candidature. Les cles historiques sont donc figees ici : les changer rendrait
 * illisibles les candidatures deja recues. Une mention creee plus tard prend
 * son sigle en minuscules — le sigle est definitif dans cette application, la
 * cle le sera donc aussi, et aucun sigle de deux a quatre lettres ne peut
 * heurter une cle historique.
 *
 * SI LA BASE EST INJOIGNABLE
 * --------------------------
 * On retombe sur les sept mentions historiques, sans parcours. Cette page est
 * aussi la vitrine de l'universite : la laisser sans aucune mention donnerait
 * a lire un etablissement sans filieres. L'incident est journalise, et la
 * candidature echouera de toute facon puisque le parcours est obligatoire.
 */

/**
 * Sigle -> cle publique historique. Figee, voir l'en-tete.
 *
 * EDUC y figure alors que « Education » n'est plus proposee : des candidatures
 * portent encore cette cle, et elles doivent rester convertibles.
 */
function parcoursClesHistoriques(): array
{
    return [
        'THEO' => 'theologie',
        'GEST' => 'gestion',
        'INFO' => 'informatique',
        'NURS' => 'sciences-infirmieres',
        'EDUC' => 'education',
        'COMM' => 'communication',
        'LANG' => 'etudes-anglophones',
        'DROI' => 'droit',
    ];
}

/**
 * Mentions qui proposaient un Master avant que le reglage ne le dise.
 * Ne sert plus qu'au repli decrit dans l'en-tete.
 */
function parcoursMasterHistorique(): array
{
    return ['gestion', 'informatique', 'communication', 'etudes-anglophones'];
}

/** La cle publique d'un sigle. */
function parcoursCleDeSigle(string $sigle): string
{
    $historiques = parcoursClesHistoriques();

    return $historiques[$sigle] ?? strtolower($sigle);
}

/**
 * Le sigle derriere une cle publique, MEME si la mention est fermee.
 *
 * Une candidature recue avant la fermeture d'une mention doit rester
 * convertible en etudiant : on ne passe donc pas par la liste publique, qui
 * est filtree sur l'ouverture. L'appelant verifie que le sigle rendu existe
 * bien en base — ici on traduit, on ne garantit pas.
 */
function parcoursSigleDeCle(string $cle): string
{
    $sigle = array_search($cle, parcoursClesHistoriques(), true);
    if ($sigle !== false) {
        return (string) $sigle;
    }

    // Cle derivee d'un sigle : voir parcoursCleDeSigle().
    return strtoupper($cle);
}

/** Le cycle vise par un diplome : M pour le master, L sinon. */
function parcoursDiplomeVersCycle(string $diplome): string
{
    return $diplome === 'master' ? 'M' : 'L';
}

/** Le nom du diplome, pour un message adresse au candidat. */
function parcoursNomDiplome(string $diplome): string
{
    return $diplome === 'master' ? 'Master' : 'Licence';
}

/**
 * Mentions publiques et leurs parcours, par cycle.
 *
 * @return array<string, array{sigle:string, libelle:string,
 *                             parcours:array<string, list<string>>, master:bool}>
 */
function parcoursMentionsPubliques(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        if (!defined('DB_DSN')) {
            require_once __DIR__ . '/config.php';
        }
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

        /* Chargement tardif : data/mentions.php require deja ce fichier-ci, et
           un require croise au chargement ferait dependre l'ordre des
           definitions du point d'entree. Ces trois fonctions sont partagees a
           dessein — la normalisation du cycle et l'existence des colonnes
           doivent dire la meme chose ici et dans /settings/mentions. */
        require_once __DIR__ . '/mentions.php';
        mentionParcoursInstaller($pdo);     // la colonne `cycles`
        mentionsOuvertureDisponible($pdo);  // la colonne `ouverte`

        $lignes = $pdo->query(
            'SELECT filiere_sigle, filiere_description, ' . mentionsColonneOuverte() . ' AS ouverte
               FROM filiere
              ORDER BY filiere_description'
        )->fetchAll(PDO::FETCH_ASSOC);

        $req = $pdo->prepare(
            'SELECT description, cycles
               FROM filiere_parcours
              WHERE departement = :s
                AND TRIM(COALESCE(description, "")) <> ""
              ORDER BY description'
        );

        $mentions = [];
        foreach ($lignes as $l) {
            // Mention fermee : elle ne doit plus etre proposee du tout.
            if ((int) $l['ouverte'] !== 1) {
                continue;
            }

            $sigle = (string) $l['filiere_sigle'];
            $req->execute(['s' => $sigle]);

            $parCycle = ['L' => [], 'M' => []];
            foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $cycles = mentionParcoursCyclesValides((string) ($p['cycles'] ?? 'L,M'));
                foreach (['L', 'M'] as $cycle) {
                    if (strpos($cycles, $cycle) !== false) {
                        $parCycle[$cycle][] = (string) $p['description'];
                    }
                }
            }

            $mentions[parcoursCleDeSigle($sigle)] = [
                'sigle'    => $sigle,
                'libelle'  => (string) $l['filiere_description'],
                'parcours' => $parCycle,
                'master'   => $parCycle['M'] !== [],
            ];
        }

        /* Un tableau vide est un etat legitime : toutes les mentions peuvent
           etre fermees. On ne se replie donc pas ici — le formulaire le dit au
           visiteur au lieu de lui proposer des mentions qui ne recrutent pas. */
        $cache = $mentions;
    } catch (Throwable $e) {
        error_log('Pre-admission: lecture des mentions impossible: ' . $e->getMessage());
        $cache = parcoursMentionsDeSecours();
    }

    return $cache;
}

/** Repli si la base est injoignable. Voir l'en-tete. */
function parcoursMentionsDeSecours(): array
{
    $master = parcoursMasterHistorique();
    $libelles = [
        'theologie'            => 'Théologie',
        'gestion'              => 'Gestion',
        'informatique'         => 'Informatique',
        'sciences-infirmieres' => 'Sciences Infirmières',
        'communication'        => 'Communication',
        'etudes-anglophones'   => 'Études Anglophones',
        'droit'                => 'Droit',
    ];

    $secours = [];
    foreach (parcoursClesHistoriques() as $sigle => $cle) {
        /* « Education » figure dans les cles historiques pour que d'anciennes
           candidatures restent convertibles, mais elle n'a jamais ete proposee
           depuis qu'elle a ete retiree du formulaire : le repli ne doit pas la
           faire reapparaitre. */
        if ($cle === 'education') {
            continue;
        }

        $secours[$cle] = [
            'sigle'    => $sigle,
            'libelle'  => $libelles[$cle] ?? $sigle,
            'parcours' => ['L' => [], 'M' => []],
            'master'   => in_array($cle, $master, true),
        ];
    }

    return $secours;
}

/** Cle publique -> sigle de departement dans `filiere_parcours`. */
function parcoursMentionVersSigle(): array
{
    $map = [];
    foreach (parcoursMentionsPubliques() as $cle => $m) {
        $map[$cle] = $m['sigle'];
    }

    return $map;
}

/** Cle publique -> libelle affiche. Remplace les listes ecrites en dur. */
function parcoursLibellesMentions(): array
{
    $libelles = [];
    foreach (parcoursMentionsPubliques() as $cle => $m) {
        $libelles[$cle] = $m['libelle'];
    }

    return $libelles;
}

/** Cle publique -> ['L' => [...], 'M' => [...]]. */
function parcoursParMentionEtCycle(): array
{
    $listes = [];
    foreach (parcoursMentionsPubliques() as $cle => $m) {
        $listes[$cle] = $m['parcours'];
    }

    return $listes;
}

/**
 * Cle publique -> tous les parcours, cycles confondus.
 *
 * Conservee pour les appels qui ne connaissent pas le diplome vise. La
 * validation d'une candidature, elle, passe par le cycle : voir
 * parcoursParMentionEtCycle().
 */
function parcoursParMention(): array
{
    $listes = [];
    foreach (parcoursMentionsPubliques() as $cle => $m) {
        $tous = array_values(array_unique(array_merge($m['parcours']['L'], $m['parcours']['M'])));
        sort($tous);
        $listes[$cle] = $tous;
    }

    return $listes;
}

/**
 * Mentions qui proposent un Master.
 *
 * Deduite du reglage : une mention propose un Master si l'un de ses parcours
 * est marque « master ». Sert au formulaire ET a la validation — ecrite deux
 * fois, cette liste finirait par ne plus dire la meme chose des deux cotes, et
 * c'est la version permissive qui gagnerait.
 */
function parcoursMentionsMaster(): array
{
    $cles = [];
    foreach (parcoursMentionsPubliques() as $cle => $m) {
        if ($m['master']) {
            $cles[] = $cle;
        }
    }

    return $cles;
}

/**
 * Les parcours de cette mention pour ce diplome.
 *
 * @return list<string> vide si la mention ne prepare pas ce diplome
 */
function parcoursDeMentionPourDiplome(string $mention, string $diplome): array
{
    $mentions = parcoursMentionsPubliques();
    $cycle = parcoursDiplomeVersCycle($diplome);

    return $mentions[$mention]['parcours'][$cycle] ?? [];
}

/**
 * Cette mention accepte-t-elle ce diplome ?
 *
 * La regle est la meme dans les deux sens : il faut au moins un parcours pour
 * le cycle demande. Une mention sans aucun parcours n'accepte donc rien —
 * refuser ici, avec le nom du diplome, vaut mieux que de laisser le candidat
 * buter sur une liste de parcours vide.
 */
function parcoursMentionAccepteDiplome(string $mention, string $diplome): bool
{
    return parcoursDeMentionPourDiplome($mention, $diplome) !== [];
}
