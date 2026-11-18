<?php
/* =============================================================================
   Grille du bareme — fragment partage par la page et le rafraichissement AJAX.

   Attend, fournis par l'appelant :
     $dtb              connexion PDO
     $app_base         chemin de base de l'application
     $cibleId          session editee (BAREME_BASE pour le bareme de base)
     $semestreChoisi   numero de semestre
     $anneeChoisie     annee scolaire affichee
     $sessionCourante  ligne du catalogue, ou null
     $editeBase        true si l'on edite le bareme de base
   ========================================================================== */

require_once __DIR__ . '/../../data/mentions.php';
require_once __DIR__ . '/../../data/finance.frais.php';

/* Seules les mentions ouvertes (réglage /settings/mentions) sont affichées :
   cartes et liste « Appliquer aussi à… ». Le barème d'une mention fermée reste
   en base, sert toujours aux étudiants déjà inscrits et réapparaît ici à la
   réouverture. Repli sur toutes les mentions si la colonne n'existe pas. */
$mentions = $dtb->query('SELECT filiere_sigle, filiere_description FROM filiere WHERE '
                . (mentionsOuvertureDisponible($dtb) ? 'ouverte = 1' : '1')
                . ' ORDER BY filiere_sigle')
                ->fetchAll(PDO::FETCH_ASSOC);

/* Mentions deja ajustees pour cette session : sert a distinguer « tarif
   propre » de « herite de la base ». */
$mentionsAjustees = $cibleId === BAREME_BASE ? [] : baremeMentionsSession($dtb, $cibleId);

$champs  = baremeChamps();
$niveaux = baremeNiveaux();
$statuts = baremeStatuts();

/* Frais supplementaires ajoutes par le registrariat (data/finance.frais.php).
   Les frais desactives ne sont pas rendus dans la grille, mais restent
   listes dans le panneau pour pouvoir etre reactives. */
$fraisPersoActifs = fraisPersoDefinitions($dtb);
$fraisPersoTous   = fraisPersoDefinitions($dtb, false);

/* Un frais reserve au deuxieme semestre n'a pas de case a remplir au
   premier : sa ligne n'est rendue que dans les semestres ou il
   s'applique, comme la page le fait deja pour le frais de graduation. */
$fraisPersoSemestre = array_values(array_filter(
    $fraisPersoActifs,
    static fn (array $f): bool => fraisPersoSemestreApplique($f['semestres'], $semestreChoisi)
));

if (!function_exists('baremeChampPertinent')) {
    /**
     * Le champ a-t-il un sens pour cette mention et ce semestre ?
     *
     * Un champ non pertinent n'est pas rendu. C'est aussi ce qui rend
     * l'enregistrement sur : l'ancien endpoint initialisait les onze montants
     * a 0 puis ne reprenait que ceux presents dans le POST, si bien que chaque
     * enregistrement remettait a zero les montants non affiches. Le nouveau
     * n'ecrit que ce qu'il recoit.
     */
    function baremeChampPertinent(string $col, string $sigle, int $semestre): bool
    {
        if ($col === 'frais_graduation') { return $semestre === 2; }
        if ($col === 'frais_costume')    { return $sigle === 'THEO' && $semestre === 1; }

        return true;
    }
}
?>
<div id="baremeZone"
     data-session-id="<?= (int) $cibleId ?>"
     data-semestre="<?= (int) $semestreChoisi ?>"
     data-base="<?= $editeBase ? '1' : '0' ?>">

<?php /* Panneau des frais supplementaires. Il vit dans la grille : il se
         reaffiche donc apres chaque changement d'annee ou de semestre, et
         apres l'ajout d'un frais, sans traitement particulier. */ ?>
<div class="frais-perso-panneau">
    <div class="frais-perso-entete">
        <div>
            <b><i class="bi bi-plus-square-dotted"></i> Frais supplémentaires</b>
            <span class="frais-perso-note">Un frais ajouté ici devient une ligne du tableau, saisissable par filière, niveau et statut. Il est facturé à l'étudiant que le barème désigne et figure sur sa fiche d'inscription.</span>
        </div>
        <form class="frais-perso-ajout js-frais-ajout">
            <input type="text" class="frais-perso-champ js-frais-libelle" maxlength="120"
                   placeholder="Nom du frais (ex. Alliance française)" aria-label="Nom du frais à ajouter">
            <button type="submit" class="bareme-action"><i class="bi bi-plus-lg"></i> Ajouter</button>
        </form>
    </div>
<?php if ($fraisPersoTous !== []) { ?>
    <?php /* Un frais porte deux reglages en plus de ses montants : a QUI il
             s'applique et a QUELS semestres. Ce sont les deux regles que le
             code tenait en dur pour les frais fixes — graduation aux
             diplomants au semestre 2, costume aux nouveaux etudiants. */ ?>
    <table class="frais-perso-table">
        <thead>
            <tr>
                <th>Frais</th>
                <th>Facturé à</th>
                <th>Semestres</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fraisPersoTous as $f) { ?>
            <tr class="js-frais-ligne<?= $f['actif'] ? '' : ' frais-perso-off' ?>" data-frais="<?= (int) $f['id'] ?>">
                <td>
                    <span class="js-frais-nom"><?= e($f['libelle']) ?></span>
                    <?php if (!$f['actif']) { ?> <em>désactivé</em><?php } ?>
                    <button type="button" class="frais-perso-bouton js-frais-renommer"
                            data-frais="<?= (int) $f['id'] ?>" data-libelle="<?= e($f['libelle']) ?>"
                            title="Renommer ce frais"><i class="bi bi-pencil"></i></button>
                </td>
                <td>
                    <select class="frais-perso-select js-frais-condition"
                            aria-label="À qui le frais <?= e($f['libelle']) ?> est facturé">
                    <?php foreach (fraisPersoConditions() as $code => $libelle) { ?>
                        <option value="<?= e($code) ?>"<?= $f['condition'] === $code ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php } ?>
                    </select>
                </td>
                <td>
                    <?php foreach ([1, 2, 3, 4] as $s) { ?>
                        <label class="frais-perso-sem">
                            <input type="checkbox" class="js-frais-semestre" value="<?= $s ?>"
                                   <?= ($f['semestres'] !== '' && fraisPersoSemestreApplique($f['semestres'], $s)) ? 'checked' : '' ?>>
                            S<?= $s ?>
                        </label>
                    <?php } ?>
                    <em class="frais-perso-note-inline">aucune case = tous</em>
                </td>
                <td class="frais-perso-actions">
                    <button type="button" class="bareme-action js-frais-regler"
                            data-frais="<?= (int) $f['id'] ?>" disabled>
                        <i class="bi bi-check2"></i> Appliquer
                    </button>
                    <button type="button" class="frais-perso-bouton js-frais-basculer"
                            data-frais="<?= (int) $f['id'] ?>" data-actif="<?= $f['actif'] ? '1' : '0' ?>"
                            data-libelle="<?= e($f['libelle']) ?>"
                            title="<?= $f['actif'] ? 'Désactiver : le frais cesse d\'être facturé, les montants et l\'historique restent' : 'Réactiver ce frais' ?>">
                        <i class="bi <?= $f['actif'] ? 'bi-slash-circle' : 'bi-arrow-counterclockwise' ?>"></i>
                    </button>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
</div>
<?php
foreach ($mentions as $m) {
    $sigle   = $m['filiere_sigle'];
    $ajustee = in_array($sigle, $mentionsAjustees, true);

    $champsMention = array_filter(
        $champs,
        static fn ($lib, $col) => baremeChampPertinent($col, $sigle, $semestreChoisi),
        ARRAY_FILTER_USE_BOTH
    );

    /* Libelle propre a la filiere : en Theologie le deplacement est un colloque. */
    if (isset($champsMention['frais_voyage'])) {
        $champsMention['frais_voyage'] = ($sigle === 'THEO') ? 'Colloque' : "Voyage d'étude";
    }

    $grille = baremeGrille($dtb, $cibleId, $sigle, $semestreChoisi);
    $grillePerso = $fraisPersoSemestre === [] ? [] : fraisPersoGrille($dtb, $cibleId, $sigle, $semestreChoisi);

    /* Filiere sans aucun tarif, ni propre ni de base : la grille serait une
       suite de cases vides sans explication. C'est le cas de CPRE (Cours
       Préparatoire), definie dans filiere mais absente du barème. */
    $sansBareme = count(array_filter($grille, static fn ($l) => $l !== [])) === 0;
?>
    <div class="finance-card" data-mention="<?= e($sigle) ?>"
         data-libelle="<?= e($m['filiere_description']) ?>"
         data-ajustee="<?= $ajustee ? '1' : '0' ?>">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <div class="finance-card-title">
                <i class="bi bi-mortarboard"></i>
                <?= e($m['filiere_description']) ?>
            </div>
            <?php if ($sansBareme) { ?>
                <span class="bareme-etat bareme-etat-absent">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Aucun tarif défini — les inscriptions de cette filière échoueront
                </span>
            <?php } elseif (!$editeBase) { ?>
                <span class="bareme-etat <?= $ajustee ? 'bareme-etat-propre' : 'bareme-etat-base' ?>">
                    <?= $ajustee ? 'Tarif propre à cette session' : 'Hérite du barème de base' ?>
                </span>
            <?php } ?>
            <div class="ml-auto flex items-center gap-2">
                <?php if (!$editeBase) { ?>
                    <button type="button" class="bareme-action js-bareme-copier"
                            data-mention="<?= e($sigle) ?>"
                            title="Créer un tarif propre à cette session, en repartant du barème de base">
                        <i class="bi bi-clipboard-plus"></i> Reprendre la base
                    </button>
                    <?php if ($ajustee) { ?>
                        <button type="button" class="bareme-action bareme-action-danger js-bareme-reinit"
                                data-mention="<?= e($sigle) ?>"
                                title="Supprimer le tarif propre : cette filière reviendra au barème de base">
                            <i class="bi bi-arrow-counterclockwise"></i> Revenir à la base
                        </button>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <div class="overflow-x-auto finance-scroll" style="border-radius:.5rem">
        <table class="tableFnc">
            <thead>
                <tr>
                    <th style="visibility:hidden"></th>
                    <th colspan="<?= count($niveaux) * 2 ?>" class="text-center">
                        <?= $editeBase
                            ? 'BARÈME DE BASE — SEMESTRE ' . (int) $semestreChoisi
                            : e(mb_strtoupper((string) $sessionCourante['session_name'])) . ' — ' . e($anneeChoisie) ?>
                    </th>
                </tr>
                <tr class="text-center">
                    <th style="visibility:hidden"></th>
                    <?php foreach ($niveaux as $n) { ?>
                        <th colspan="2" class="<?= $n['niveau'] === 0 ? 'th-ran' : ($n['categorie'] === 'L' ? 'th-licence' : 'th-master') ?>">
                            <?= e($n['libelle']) ?>
                        </th>
                    <?php } ?>
                </tr>
                <tr class="text-center">
                    <th>Liste des frais</th>
                    <?php foreach ($niveaux as $n) { ?>
                        <th>Int.</th><th>Ext.</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($champsMention as $col => $libelle) { ?>
                <tr>
                    <th><?= $libelle /* libelles internes : certains portent un <em> */ ?></th>
                    <?php foreach ($niveaux as $n) { ?>
                        <?php foreach ($statuts as $statut) {
                            $ligne  = $grille[$n['niveau'] . '|' . $statut] ?? [];
                            $valeur = $ligne[$col] ?? '';
                            $herite = !empty($ligne) && empty($ligne['bareme_surcharge']) && !$editeBase;

                            /* Balisage compact, volontairement sur une ligne.
                               Mis en forme, chaque champ portait neuf lignes
                               indentees de plus de vingt caracteres : sur les
                               quelque mille champs de la page, l'indentation
                               seule pesait plus de 200 ko — transmis a chaque
                               changement d'annee.

                               data-initial a disparu : le JavaScript lit
                               defaultValue, qui EST l'attribut value rendu par
                               le serveur et survit a la saisie. Un attribut de
                               moins par champ, et aucune valeur en double.

                               title uniquement quand la valeur est heritee :
                               c'est la seule information que l'infobulle
                               apporte, l'autre cas etant l'etat normal. */
                            echo '<td class="prntInput"><input type="number" step="any" min="0"'
                                . ' class="inputFnc js-bareme-champ"'
                                . ' data-cle="' . (int) $n['niveau'] . '|' . e($statut) . '"'
                                . ' data-col="' . e($col) . '"'
                                . ($herite ? ' data-herite="1" title="Valeur héritée du barème de base"' : '')
                                . ' value="' . e((string) $valeur) . '"></td>';
                        } ?>
                    <?php } ?>
                </tr>
            <?php } ?>
            <?php /* Frais supplementaires : memes cases, meme enregistrement
                     que les frais fixes. Une case vidée revient au barème de
                     base ; un 0 saisi signifie « rien à payer » pour ce cas. */ ?>
            <?php foreach ($fraisPersoSemestre as $fp) { ?>
                <tr class="frais-perso-ligne">
                    <th>
                        <i class="bi bi-plus-square-dotted frais-perso-marque"></i> <?= e($fp['libelle']) ?>
                        <?php if ($fp['condition'] !== 'aucune') { ?>
                            <em class="frais-perso-cond" title="Ce frais n'est facturé qu'à ces étudiants">
                                <?= e(fraisPersoConditions()[$fp['condition']] ?? $fp['condition']) ?>
                            </em>
                        <?php } ?>
                    </th>
                    <?php foreach ($niveaux as $n) { ?>
                        <?php foreach ($statuts as $statut) {
                            $case   = $grillePerso[$fp['id']][$n['niveau'] . '|' . $statut] ?? ['montant' => '', 'herite' => false];
                            $valeur = $case['montant'] === '' ? '' : (string) (0 + $case['montant']);

                            echo '<td class="prntInput"><input type="number" step="any" min="0"'
                                . ' class="inputFnc js-bareme-champ"'
                                . ' data-cle="' . (int) $n['niveau'] . '|' . e($statut) . '"'
                                . ' data-perso="' . (int) $fp['id'] . '"'
                                . ($case['herite'] ? ' data-herite="1" title="Valeur héritée du barème de base"' : '')
                                . ' value="' . e($valeur) . '"></td>';
                        } ?>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-center" colspan="<?= count($niveaux) * 2 + 1 ?>">
                        <button type="button"
                                class="btn-save btn-save-inactive js-bareme-enregistrer"
                                data-mention="<?= e($sigle) ?>" disabled>
                            <i class="bi bi-check2-circle"></i> Enregistrer
                        </button>
                        <button type="button" class="bareme-action ml-2 js-bareme-partage"
                                aria-expanded="false"
                                title="Appliquer ces mêmes montants à d'autres filières">
                            <i class="bi bi-diagram-2"></i> Appliquer aussi à…
                        </button>
                        <span class="js-bareme-compteur text-slate-500 ml-2" style="font-size:.7rem"></span>
                    </th>
                </tr>
            </tfoot>
        </table>
        </div>

        <?php /* Plusieurs filieres partagent souvent un bareme identique :
                 releve en base, les huit mentions ne forment que cinq profils
                 distincts par semestre. Sans cette liste il fallait retaper
                 les memes montants trois ou quatre fois.

                 Replie par defaut — huit cases a cocher sur chacune des neuf
                 cartes seraient du bruit. L'attribut hidden, plutot qu'un
                 display en style : il retire aussi le panneau du parcours au
                 clavier. */ ?>
        <div class="bareme-partage" hidden>
            <p class="bareme-partage-titre">
                Appliquer les montants de <b><?= e($m['filiere_description']) ?></b> à :
            </p>
            <div class="bareme-partage-liste">
                <?php foreach ($mentions as $autre) {
                    if ($autre['filiere_sigle'] === $sigle) { continue; }
                    $autreAjustee = in_array($autre['filiere_sigle'], $mentionsAjustees, true);
                ?>
                    <label class="bareme-partage-choix">
                        <input type="checkbox" class="js-bareme-cible"
                               value="<?= e($autre['filiere_sigle']) ?>"
                               data-libelle="<?= e($autre['filiere_description']) ?>"
                               data-ajustee="<?= $autreAjustee ? '1' : '0' ?>">
                        <span><?= e($autre['filiere_description']) ?></span>
                        <?php if ($autreAjustee) { ?>
                            <i class="bi bi-pencil-fill bareme-partage-marque"
                               title="Cette filière a déjà un tarif propre à cette session ; il serait remplacé"></i>
                        <?php } ?>
                    </label>
                <?php } ?>
            </div>
            <p class="bareme-partage-note">
                <i class="bi bi-pencil-fill"></i> déjà ajustée pour cette session — son tarif serait remplacé.
            </p>
        </div>
    </div>
<?php } ?>
</div>
