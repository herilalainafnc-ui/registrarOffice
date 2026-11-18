<?php
/* =============================================================================
   Sigle d'un cours : il doit être unique

   Le sigle rattache les notes à leur cours (t_2023_notes.Sigle, filtre des
   pages de notes, relevés, listes par cours). Deux cours sous le même sigle
   mélangent leurs étudiants et leurs notes. Relevé en production le
   15/09/2026 : 49 sigles en double, soit 102 cours — dont 28 sigles portés
   par des cours différents (« ANAT 4211 » : « Anatomie II » et « Anatomie II
   et Génétique »).

   Comparaison sans casse ni espaces superflus : « INFO 101 », « info 101 »
   et « INFO  101 » sont le même sigle. Un cours retiré garde son sigle : le
   réutiliser rattacherait l'historique de ses notes au nouveau cours.

   Utilisé par app/.cours/sigle.verifier.php (avis pendant la frappe),
   app/.cours/addCours.php et app/.cours/updateCours.php (refus côté serveur).
   ========================================================================== */

if (!function_exists('coursSigleNormalise')) {

    function coursSigleNormalise(string $sigle): string
    {
        return mb_strtoupper(trim((string) preg_replace('~\s+~u', ' ', $sigle)));
    }

    function coursNiveauLibelle(int $niveau): string
    {
        if ($niveau <= 0) {
            return 'Remise à niveau';
        }
        return $niveau <= 3 ? 'Licence ' . $niveau : 'Master ' . ($niveau - 3);
    }

    /**
     * Cours qui portent déjà ce sigle.
     * @param int|null $sauf id du cours en cours de modification, exclu
     */
    function coursSigleDoublons(PDO $dtb, string $sigle, ?int $sauf = null): array
    {
        $normalise = coursSigleNormalise($sigle);
        if ($normalise === '') {
            return [];
        }
        $sql = "SELECT id, Sigle, title, dep_desc, yearlevel, semester, remove
                  FROM t_2023_cours
                 WHERE UPPER(TRIM(REGEXP_REPLACE(Sigle, '[[:space:]]+', ' '))) = :sigle"
             . ($sauf ? ' AND id <> :sauf' : '')
             . ' ORDER BY remove, id LIMIT 20';
        $st = $dtb->prepare($sql);
        $st->bindValue(':sigle', $normalise);
        if ($sauf) {
            $st->bindValue(':sauf', $sauf, PDO::PARAM_INT);
        }
        $st->execute();

        return array_map(static fn(array $r) => [
            'id'       => (int) $r['id'],
            'sigle'    => trim((string) $r['Sigle']),
            'titre'    => trim((string) $r['title']),
            'mention'  => (string) $r['dep_desc'],
            'niveau'   => coursNiveauLibelle((int) $r['yearlevel']),
            'semestre' => (int) $r['semester'],
            'retire'   => (int) $r['remove'] === 1,
        ], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    function coursSigleMessage(array $doublons): string
    {
        if (!$doublons) {
            return '';
        }
        $d = $doublons[0];
        $message = 'Le sigle « ' . $d['sigle'] . ' » est déjà utilisé par « ' . $d['titre'] . ' » ('
                 . $d['mention'] . ', ' . $d['niveau'] . ', semestre ' . $d['semestre']
                 . ($d['retire'] ? ', cours retiré' : '') . ')';
        $autres = count($doublons) - 1;
        if ($autres > 0) {
            $message .= ' et ' . $autres . ' autre' . ($autres > 1 ? 's cours' : ' cours');
        }
        return $message . '.';
    }

    /* =========================================================================
       Sigle affiché : un alias, pour renommer sans rien rendre orphelin

       Le bulletin académique 2027-2030 impose un plan de numérotation neuf
       (§IV.26 : quatre lettres puis trois chiffres) et les mentions Sciences
       Infirmières, Théologie et Droit y changent entièrement de codes
       (ANAT 4211 -> NRBC 216, RELT 241 -> THCO 241, DRT 321 -> DRCI 211…).

       Or 41 412 notes historiques n'ont pas d'id_cours : leur SEUL lien avec
       un cours est le sigle qu'elles portent, et 17 260 d'entre elles sont
       aujourd'hui retrouvées par ce seul moyen. Renommer t_2023_cours.Sigle
       les détacherait sans retour possible.

       D'où la séparation :

         Sigle           clé technique, JAMAIS modifiée. C'est par elle que
                         les notes retrouvent leur cours (page des étudiants
                         d'un cours, page des notes, liste imprimable des
                         inscrits, fiche de contrôle, fiche d'inscription).
         sigle_affiche   code montré au personnel. Libre, et unique. Vide,
                         on affiche Sigle : le comportement d'avant.

       Les relevés et bulletins lisent t_2023_notes.Sigle, gelé au moment où
       la note est saisie : un cours suivi en 2018 garde son code de 2018,
       ce qui est le comportement académique attendu.

       Utilisé par app/.cours/updateCours.php (écriture), les pages du
       catalogue (affichage) et app/.cours/sigle.verifier.php (unicité).
       ====================================================================== */

    /** Ajoute la colonne au premier passage. Voir data/migration_sigle_affiche.sql. */
    function coursSigleAliasInstaller(PDO $dtb): void
    {
        static $fait = false;
        if ($fait) {
            return;
        }
        $fait = true;

        $presente = (int) $dtb->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 't_2023_cours'
                AND COLUMN_NAME = 'sigle_affiche'"
        )->fetchColumn();

        if ($presente === 0) {
            $dtb->exec("ALTER TABLE t_2023_cours
                ADD COLUMN sigle_affiche VARCHAR(45) NULL AFTER Sigle");
        }
    }

    /**
     * Le code à montrer. Repli sur la clé technique quand aucun alias n'est
     * posé, et quand la requête n'a pas ramené la colonne : dans les deux
     * cas l'affichage reste celui d'avant.
     *
     * @param array|string $cours ligne de t_2023_cours, ou un sigle brut
     */
    function coursSigleAffiche($cours): string
    {
        if (!is_array($cours)) {
            return trim((string) $cours);
        }
        $alias = trim((string) ($cours['sigle_affiche'] ?? ''));
        return $alias !== '' ? $alias : trim((string) ($cours['Sigle'] ?? ''));
    }

    /**
     * Cours qui AFFICHENT déjà ce code : leur alias, ou leur clé technique
     * quand ils n'ont pas d'alias. Deux cours ne doivent pas se montrer sous
     * le même sigle.
     *
     * La clé technique d'un cours qui affiche un AUTRE code ne compte pas.
     * Elle bloquait les renumérotations en chaîne du bulletin : « Leadership »
     * (clé GEMG 212) doit afficher GEMG 311, clé de « Gestion des ressources
     * humaines », qui affiche GEMG 312, clé de la « Gestion de production »…
     * Aucun étudiant n'y perd sa place : les pages d'un cours (étudiants,
     * notes, liste imprimable) retrouvent les notes par la clé technique,
     * jamais par le code affiché, et une note est toujours écrite avec la clé
     * (app/.student/checkCours.php).
     *
     * Pour un cours NEUF, dont le sigle devient une clé, voir
     * coursSigleCreationDoublons() : là, les clés comptent aussi.
     *
     * @param int|null $sauf id du cours en cours de modification, exclu
     */
    function coursSigleAliasDoublons(PDO $dtb, string $affiche, ?int $sauf = null): array
    {
        coursSigleAliasInstaller($dtb);

        $normalise = coursSigleNormalise($affiche);
        if ($normalise === '') {
            return [];
        }

        $n = "UPPER(TRIM(REGEXP_REPLACE(%s, '[[:space:]]+', ' ')))";
        $sql = "SELECT id, COALESCE(NULLIF(TRIM(sigle_affiche), ''), Sigle) AS Sigle,
                       title, dep_desc, yearlevel, semester, `remove`
                  FROM t_2023_cours
                 WHERE " . sprintf($n, "COALESCE(NULLIF(TRIM(sigle_affiche), ''), Sigle)") . " = :affiche"
             . ($sauf ? ' AND id <> :sauf' : '')
             . ' ORDER BY `remove`, id LIMIT 20';

        $st = $dtb->prepare($sql);
        $st->bindValue(':affiche', $normalise);
        if ($sauf) {
            $st->bindValue(':sauf', $sauf, PDO::PARAM_INT);
        }
        $st->execute();

        return array_map(static fn(array $r) => [
            'id'       => (int) $r['id'],
            'sigle'    => trim((string) $r['Sigle']),
            'titre'    => trim((string) $r['title']),
            'mention'  => (string) $r['dep_desc'],
            'niveau'   => coursNiveauLibelle((int) $r['yearlevel']),
            'semestre' => (int) $r['semester'],
            'retire'   => (int) $r['remove'] === 1,
        ], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Cours qui empêchent de CRÉER un cours sous ce sigle. Le sigle d'un cours
     * neuf devient sa clé technique : il ne doit égaler ni la clé d'un autre
     * cours (les deux se partageraient leurs étudiants et leurs notes), ni le
     * code qu'un autre affiche.
     */
    function coursSigleCreationDoublons(PDO $dtb, string $sigle): array
    {
        $tous = [];
        foreach (array_merge(coursSigleDoublons($dtb, $sigle), coursSigleAliasDoublons($dtb, $sigle)) as $d) {
            $tous[$d['id']] = $tous[$d['id']] ?? $d;
        }
        return array_values($tous);
    }

    /**
     * Les notes deja saisies qui suivent la fiche d'un cours quand on
     * l'enregistre (app/.cours/updateCours.php), et que la fiche annonce
     * (src/cours/information.php) : une condition sur t_2023_notes.
     *
     * Deux filtres :
     *   - la note porte encore le sigle technique de la fiche : id_cours seul
     *     ne suffit pas, des fiches ont ete reutilisees pour un autre cours
     *     (921 notes relevees le 15/09/2026) ;
     *   - la note est de l'annee scolaire donnee, l'annee en cours (decision du
     *     13/10/2026). Une note d'une annee passee garde les credits,
     *     l'intitule, l'enseignant... du cours tel qu'il etait quand l'etudiant
     *     l'a suivi : changer les credits d'un cours ne refait pas les moyennes
     *     des annees closes. L'annee d'une note est celle de sa session
     *     (t_2023_session fait foi), a defaut celle recopiee dans la note.
     *
     * @return array{0:string,1:array<string,mixed>} clause SQL et parametres
     */
    function coursNotesSuivantFiche(int $idCours, string $sigleTechnique, string $annee): array
    {
        return [
            "id_cours = :suivi_cours
             AND UPPER(TRIM(REGEXP_REPLACE(Sigle, '[[:space:]]+', ' '))) = :suivi_sigle
             AND REPLACE(COALESCE(
                     (SELECT s.session_year FROM t_2023_session s WHERE s.session_id = t_2023_notes.session_id LIMIT 1),
                     annee_scolaire), ' ', '') = :suivi_annee",
            [
                'suivi_cours' => $idCours,
                'suivi_sigle' => coursSigleNormalise($sigleTechnique),
                'suivi_annee' => str_replace(' ', '', $annee),
            ],
        ];
    }
}
