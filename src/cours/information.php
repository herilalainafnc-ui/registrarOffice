<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion(); ?>
<?php
/* La fiche d'information d'un cours : une seule fiche, toujours modifiable.
   Chaque champ modifie se signale (filet or, valeur d'avant, « Rétablir ») et
   la barre d'enregistrement n'apparait qu'a la premiere modification. Le
   comportement est dans src/js/cours.information.js, l'habillage dans
   src/css/cours-information.css. Les champs envoyes sont ceux qu'attend
   app/.cours/updateCours.php, qui n'a pas change de contrat.

   Pas de mode lecture seule : la page (AdminController::course) et
   l'enregistrement refusent les memes comptes (refuserEnseignant). Qui voit
   la fiche peut l'enregistrer. */
require_once(__DIR__ . '/../../data/mentions.php');
require_once(__DIR__ . '/../../data/finance.bareme.php');

$ciH = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/* ---------------------------------------------------------------- valeurs */

$ciMention    = (string) ($profil['dep_desc'] ?? '');
$ciParcours   = (string) ($profil['parcours'] ?? '');
$ciEnseignant = (string) ($profil['id_teacher'] ?? '');
$ciNiveau     = (string) (int) ($profil['yearlevel'] ?? 0);
$ciSemestre   = (string) (int) ($profil['semester'] ?? 0);
$ciCredits    = max(0, min(20, (int) ($profil['nb_crd'] ?? 1)));   // 0 : cours sans credit (ex. soutien de francais), rien a payer
$ciCategorie  = (string) ($profil['category'] ?? '');
$ciLabo       = (string) (int) ($profil['lab'] ?? 0);   // vide ou NULL : sans laboratoire, comme a l'affichage

/* Mentions : les ouvertes, plus celle du cours meme fermee. Sans elle, la
   liste n'aurait pas la valeur du cours, le navigateur choisirait la
   premiere, et le premier enregistrement rattacherait le cours a une autre
   mention sans que personne ne le demande. */
mentionsOuvertureDisponible($dtb);
$ciReqMentions = $dtb->prepare(
	'SELECT * FROM filiere
	  WHERE (' . mentionsFiltreOuvertes() . ') OR filiere_sigle = :actuelle
	  ORDER BY filiere_sigle ASC'
);
$ciReqMentions->execute(['actuelle' => $ciMention]);
$ciMentions = $ciReqMentions->fetchAll(PDO::FETCH_ASSOC);

/* Parcours de toutes les mentions : la liste suit la mention choisie. */
$ciParcoursParMention = [];
foreach ($dtb->query('SELECT shortcode, description, departement, cycles FROM filiere_parcours ORDER BY description ASC')->fetchAll(PDO::FETCH_ASSOC) as $p) {
	$ciParcoursParMention[(string) $p['departement']][] = [
		'code'  => (string) $p['shortcode'],
		'nom'   => (string) $p['description'],
		'cycle' => (string) $p['cycles'],
	];
}
$ciParcoursMention = $ciParcoursParMention[$ciMention] ?? [];
$ciParcoursConnu = $ciParcours === 'all' || in_array($ciParcours, array_column($ciParcoursMention, 'code'), true);
$ciCycles = ['L' => 'Licence', 'M' => 'Master', 'L,M' => 'Licence et master'];

/* Enseignants : plus de 150, on les cherche par le nom au lieu de les faire
   defiler (src/js/cours.information.js). Un cours sans enseignant (244 le
   13/10/2026) le reste : l'ancienne liste n'avait pas de choix vide, et
   l'enregistrement lui donnait le premier enseignant de la liste. */
$ciEnseignants = [];
foreach ($dtb->query('SELECT uid, name, lastName FROM teacher ORDER BY name ASC, lastName ASC')->fetchAll(PDO::FETCH_ASSOC) as $e) {
	$ciEnseignants[] = [(string) $e['uid'], trim(mb_strtoupper(trim((string) $e['name'])) . ' ' . trim((string) $e['lastName']))];
}
$ciEnseignantNom = 'Aucun enseignant';
if ($ciEnseignant !== '') {
	$ciEnseignantNom = 'Enseignant introuvable (n° ' . $ciEnseignant . ')';
	foreach ($ciEnseignants as [$uid, $nom]) {
		if ($uid === $ciEnseignant) { $ciEnseignantNom = $nom; break; }
	}
}

$ciNiveaux = ['1' => ['L1', 'Licence 1'], '2' => ['L2', 'Licence 2'], '3' => ['L3', 'Licence 3'], '4' => ['M1', 'Master 1'], '5' => ['M2', 'Master 2']];
if (!isset($ciNiveaux[$ciNiveau])) {
	$ciNiveaux = [$ciNiveau => $ciNiveau === '0' ? ['RàN', 'Remise à niveau'] : [$ciNiveau, 'Niveau ' . $ciNiveau]] + $ciNiveaux;
}
$ciSemestres = ['1' => ['Semestre 1', 'Semestre 1'], '2' => ['Semestre 2', 'Semestre 2']];
if (!isset($ciSemestres[$ciSemestre])) {
	$ciSemestres[$ciSemestre] = ['Semestre ' . $ciSemestre, 'Semestre ' . $ciSemestre];
}
$ciCategories = ['0' => 'Général', '1' => 'Majeur', '2' => 'Sélective', '3' => 'Additionnel'];
if (!isset($ciCategories[$ciCategorie])) {
	$ciCategories[$ciCategorie] = $ciCategorie === '-1' ? 'Sélective (ancien code)' : 'Non classé';
}
$ciLabos = ['0' => 'Sans laboratoire', '1' => 'Lab 1', '2' => 'Lab 2', '3' => 'Lab 3', '4' => 'Simulation Room', '5' => 'Labo recherche'];
if (!isset($ciLabos[$ciLabo])) {
	$ciLabos[$ciLabo] = 'Laboratoire ' . $ciLabo;
}

/* Le code technique : les notes historiques retrouvent le cours par lui. */
$ciCodeTechnique = (string) $sigle;
$ciAlias = coursSigleNormalise((string) $sigleAffiche) !== coursSigleNormalise($ciCodeTechnique);

/* Les notes de ce cours : celles de l'annee en cours suivent la fiche a
   l'enregistrement, les autres gardent le cours tel qu'il etait (meme regle
   que app/.cours/updateCours.php, data/cours.sigle.php). */
$ciAnnee = inscAnneeCourante();
[$ciClauseNotes, $ciParamsNotes] = coursNotesSuivantFiche((int) $id, $ciCodeTechnique, $ciAnnee);
$ciReqNotes = $dtb->prepare('SELECT COUNT(*) FROM t_2023_notes WHERE ' . $ciClauseNotes);
$ciReqNotes->execute($ciParamsNotes);
$ciNotes = (int) $ciReqNotes->fetchColumn();
$ciReqToutes = $dtb->prepare(
	"SELECT COUNT(*) FROM t_2023_notes
	  WHERE id_cours = :id AND UPPER(TRIM(REGEXP_REPLACE(Sigle, '[[:space:]]+', ' '))) = :sigle"
);
$ciReqToutes->execute(['id' => $id, 'sigle' => coursSigleNormalise($ciCodeTechnique)]);
$ciNotesAnciennes = max(0, (int) $ciReqToutes->fetchColumn() - $ciNotes);

/* Derniere modification. */
$ciMois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$ciDate = static function ($valeur) use ($ciMois) {
	$t = strtotime((string) $valeur);
	return $t ? (int) date('j', $t) . ' ' . $ciMois[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '';
};
$ciTrace = '';
if (!empty($profil['last_change_user_id']) && $ciDate($profil['last_change_datetime'] ?? '') !== '') {
	$ciAuteur = $dtb->prepare('SELECT prenom FROM compt_utilisateur WHERE id = ?');
	$ciAuteur->execute([(int) $profil['last_change_user_id']]);
	$ciAuteur = trim((string) $ciAuteur->fetchColumn());
	$ciTrace = 'Modifié le ' . $ciDate($profil['last_change_datetime']) . ($ciAuteur !== '' ? ' par ' . $ciAuteur : '');
} elseif ($ciDate($profil['date_entry'] ?? '') !== '') {
	$ciTrace = 'Créé le ' . $ciDate($profil['date_entry']);
}

/* Le cout, recalcule par app/.cours/updateCours.php a chaque enregistrement :
   ecolage par credit de la session en cours pour ce semestre (repli sur le
   bareme de base) x credits, et le laboratoire. La grille reprend la regle de
   baremeLigne() (premiere ligne qui correspond) pour l'apercu en direct. */
$ciSessionsAnnee = baremeSessionsAnnee($dtb, inscAnneeCourante());
$ciSessionDe = [1 => baremeSessionCourante($dtb, 1), 2 => baremeSessionCourante($dtb, 2)];
$ciReqBareme = $dtb->prepare(
	'SELECT session_id, std_mention, level, semester, ecolage, laboratory_info, laboratory_lang
	   FROM t_2024_finance_detail_licence
	  WHERE session_id IN (:base, :s1, :s2)
	  ORDER BY id'
);
$ciReqBareme->execute(['base' => BAREME_BASE, 's1' => $ciSessionDe[1], 's2' => $ciSessionDe[2]]);
$ciLignes = [];
foreach ($ciReqBareme->fetchAll(PDO::FETCH_ASSOC) as $l) {
	$cle = $l['std_mention'] . '|' . (int) $l['level'] . '|' . (int) $l['semester'];
	$ciLignes[(int) $l['session_id']][$cle] ??= [
		(float) $l['ecolage'],
		(float) ((float) $l['laboratory_info'] != 0 ? $l['laboratory_info'] : $l['laboratory_lang']),
	];
}
$ciBareme = [];
foreach ([1, 2] as $s) {
	foreach (array_unique(array_merge(array_keys($ciLignes[$ciSessionDe[$s]] ?? []), array_keys($ciLignes[BAREME_BASE] ?? []))) as $cle) {
		if ((int) explode('|', $cle)[2] !== $s) { continue; }
		$ligne = $ciLignes[$ciSessionDe[$s]][$cle] ?? $ciLignes[BAREME_BASE][$cle] ?? null;
		if ($ligne) { $ciBareme[$cle] = $ligne; }
	}
}
$ciSessionsNoms = [];
foreach ([1, 2] as $s) {
	$ciSessionsNoms[$s] = $ciSessionDe[$s] !== BAREME_BASE && isset($ciSessionsAnnee[$s])
		? trim($ciSessionsAnnee[$s]['session_name'] . ' ' . $ciSessionsAnnee[$s]['session_year'])
		: '';
}

$ciConfig = [
	'id'           => (int) $id,
	'notes'        => ['annee' => $ciAnnee, 'suivent' => $ciNotes, 'gardees' => $ciNotesAnciennes],
	'parcours'     => $ciParcoursParMention,
	'cycles'       => $ciCycles,
	'enseignants'  => $ciEnseignants,
	'enseignant'   => ['uid' => $ciEnseignant, 'nom' => $ciEnseignantNom],
	'bareme'       => $ciBareme,
	'sessions'     => $ciSessionsNoms,
	'enregistre'   => ['cout' => (float) ($profil['cout'] ?? 0), 'labo' => (float) ($profil['cout_lab'] ?? 0)],
	'urlEnregistrer' => $app_base . '/app/.cours/updateCours?id=' . (int) $id,
];
$ciAr = static fn ($n) => number_format((float) $n, 0, ',', "\u{202F}") . ' Ar';
?>

<section class="ci" data-ci aria-labelledby="ciTitre">
	<header class="ci-tete">
		<div class="ci-tete__texte">
			<h2 class="ci-tete__titre" id="ciTitre">Informations du cours</h2>
			<?php if ($ciTrace !== ''): ?>
			<p class="ci-tete__trace"><i class="bi bi-clock-history" aria-hidden="true"></i><?= $ciH($ciTrace) ?></p>
			<?php endif; ?>
		</div>
		<p class="ci-tete__aide">Cliquez sur un champ pour le modifier, puis enregistrez.</p>
	</header>

	<form class="ci-fiche" method="post" action="<?= $ciH($ciConfig['urlEnregistrer']) ?>" novalidate>
	<fieldset class="ci-fiche__champs">

		<section class="ci-bloc" aria-labelledby="ciBlocCours">
			<h3 class="ci-bloc__titre" id="ciBlocCours">Le cours</h3>

			<div class="ci-ligne" data-ci-champ="sigle">
				<label class="ci-ligne__nom" for="ciSigle">Sigle</label>
				<div class="ci-ligne__valeur">
					<input class="ci-saisie ci-saisie--code" id="ciSigle" type="text" name="sigle" maxlength="45" required spellcheck="false" autocomplete="off"
					       value="<?= $ciH($sigleAffiche ?? $sigle) ?>" aria-describedby="ciSigleAide ciSigleAvis">
					<p class="ci-avis" id="ciSigleAvis" aria-live="polite"></p>
					<p class="ci-aide" id="ciSigleAide"><?php if ($ciAlias): ?>Code affiché. Les notes restent rattachées au code d'origine <code><?= $ciH($ciCodeTechnique) ?></code>.<?php else: ?>Code affiché partout. Le changer ne détache aucune note : elles restent rattachées au code d'origine.<?php endif; ?></p>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="title">
				<label class="ci-ligne__nom" for="ciIntitule">Intitulé</label>
				<div class="ci-ligne__valeur">
					<input class="ci-saisie" id="ciIntitule" type="text" name="title" maxlength="200" required value="<?= $ciH(trim((string) $title)) ?>">
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="title_english">
				<label class="ci-ligne__nom" for="ciAnglais">Intitulé en anglais</label>
				<div class="ci-ligne__valeur">
					<input class="ci-saisie" id="ciAnglais" type="text" name="title_english" maxlength="200" lang="en" value="<?= $ciH(trim((string) $title_english)) ?>" placeholder="Pour le relevé de notes en anglais">
					<p class="ci-avant" hidden></p>
				</div>
			</div>
		</section>

		<section class="ci-bloc" aria-labelledby="ciBlocRattachement">
			<h3 class="ci-bloc__titre" id="ciBlocRattachement">Rattachement</h3>

			<div class="ci-ligne" data-ci-champ="dep_desc">
				<label class="ci-ligne__nom" for="ciMention">Mention</label>
				<div class="ci-ligne__valeur">
					<select class="ci-liste" id="ciMention" name="dep_desc">
						<?php if ($ciMention === ''): ?><option value="" selected>Aucune mention</option><?php endif; ?>
						<?php foreach ($ciMentions as $m):
							$fermee = isset($m['ouverte']) && !$m['ouverte'];
							$nom = trim((string) ($m['filiere_description'] ?? '')); ?>
						<option value="<?= $ciH($m['filiere_sigle']) ?>"<?= $m['filiere_sigle'] === $ciMention ? ' selected' : '' ?>><?= $ciH($m['filiere_sigle']) ?><?= $nom !== '' ? ' — ' . $ciH($nom) : '' ?><?= $fermee ? ' (fermée)' : '' ?></option>
						<?php endforeach; ?>
					</select>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="parcours">
				<label class="ci-ligne__nom" for="ciParcours">Parcours</label>
				<div class="ci-ligne__valeur">
					<select class="ci-liste" id="ciParcours" name="parcours" aria-describedby="ciParcoursAvis">
						<option value="all"<?= $ciParcours === 'all' ? ' selected' : '' ?>>Tronc commun</option>
						<?php if (!$ciParcoursConnu): ?>
						<option value="<?= $ciH($ciParcours) ?>" selected data-ci-garde><?= $ciParcours === '' ? 'Non précisé' : $ciH($ciParcours) . ' (hors de la mention)' ?></option>
						<?php endif; ?>
						<?php foreach ($ciCycles as $cycle => $nomCycle):
							$groupe = array_filter($ciParcoursMention, static fn ($p) => $p['cycle'] === $cycle);
							if (!$groupe) { continue; } ?>
						<optgroup label="<?= $ciH($nomCycle) ?>">
							<?php foreach ($groupe as $p): ?>
							<option value="<?= $ciH($p['code']) ?>"<?= $p['code'] === $ciParcours ? ' selected' : '' ?>><?= $ciH($p['nom']) ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endforeach; ?>
						<?php $autres = array_filter($ciParcoursMention, static fn ($p) => !isset($ciCycles[$p['cycle']]));
						foreach ($autres as $p): ?>
						<option value="<?= $ciH($p['code']) ?>"<?= $p['code'] === $ciParcours ? ' selected' : '' ?>><?= $ciH($p['nom']) ?></option>
						<?php endforeach; ?>
					</select>
					<p class="ci-avis" id="ciParcoursAvis" aria-live="polite"></p>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="id_teacher">
				<label class="ci-ligne__nom" for="ciEnseignant">Enseignant</label>
				<div class="ci-ligne__valeur">
					<div class="ci-cherche" data-ci-enseignant>
						<i class="bi bi-search ci-cherche__loupe" aria-hidden="true"></i>
						<input class="ci-saisie ci-cherche__saisie" id="ciEnseignant" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false"
						       aria-controls="ciEnseignantListe" aria-describedby="ciEnseignantAide" autocomplete="off" spellcheck="false"
						       placeholder="Tapez un nom ou un prénom" value="<?= $ciH($ciEnseignantNom) ?>" data-ci-nom-initial="<?= $ciH($ciEnseignantNom) ?>">
						<i class="bi bi-chevron-down ci-cherche__fleche" aria-hidden="true"></i>
						<ul class="ci-cherche__liste" id="ciEnseignantListe" role="listbox" aria-label="Enseignants" hidden></ul>
					</div>
					<input type="hidden" name="id_teacher" value="<?= $ciH($ciEnseignant) ?>">
					<p class="ci-aide" id="ciEnseignantAide"><?= count($ciEnseignants) ?> enseignants : tapez quelques lettres du nom ou du prénom, puis choisissez.</p>
					<p class="ci-avant" hidden></p>
				</div>
			</div>
		</section>

		<section class="ci-bloc" aria-labelledby="ciBlocCursus">
			<h3 class="ci-bloc__titre" id="ciBlocCursus">Place dans le cursus</h3>

			<div class="ci-ligne" data-ci-champ="yearlevel">
				<span class="ci-ligne__nom" id="ciNiveauNom">Niveau</span>
				<div class="ci-ligne__valeur">
					<div class="ci-choix ci-choix--niveau" role="radiogroup" aria-labelledby="ciNiveauNom">
						<?php foreach ($ciNiveaux as $v => [$court, $long]): ?>
						<label title="<?= $ciH($long) ?>"><input type="radio" name="yearlevel" value="<?= $ciH($v) ?>" aria-label="<?= $ciH($long) ?>" data-ci-libelle="<?= $ciH($long) ?>"<?= (string) $v === $ciNiveau ? ' checked' : '' ?>><span><?= $ciH($court) ?></span></label>
						<?php endforeach; ?>
					</div>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="semester">
				<span class="ci-ligne__nom" id="ciSemestreNom">Semestre</span>
				<div class="ci-ligne__valeur">
					<div class="ci-choix" role="radiogroup" aria-labelledby="ciSemestreNom">
						<?php foreach ($ciSemestres as $v => [$court, $long]): ?>
						<label><input type="radio" name="semester" value="<?= $ciH($v) ?>" data-ci-libelle="<?= $ciH($long) ?>"<?= (string) $v === $ciSemestre ? ' checked' : '' ?>><span><?= $ciH($court) ?></span></label>
						<?php endforeach; ?>
					</div>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="nb_crd">
				<label class="ci-ligne__nom" for="ciCredits">Crédits</label>
				<div class="ci-ligne__valeur">
					<div class="ci-pas">
						<button type="button" class="ci-pas__bouton" data-ci-pas="-1" aria-label="Un crédit de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
						<input class="ci-pas__nombre" id="ciCredits" type="number" name="nb_crd" min="0" max="20" step="1" inputmode="numeric" value="<?= $ciCredits ?>" aria-describedby="ciCreditsUnite ciCreditsAide">
						<button type="button" class="ci-pas__bouton" data-ci-pas="1" aria-label="Un crédit de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
						<span class="ci-pas__unite" id="ciCreditsUnite">crédit<?= $ciCredits > 1 ? 's' : '' ?></span>
					</div>
					<p class="ci-aide" id="ciCreditsAide"<?= $ciCredits === 0 ? '' : ' hidden' ?>>Cours sans crédit : rien à payer, et sa note ne compte pas dans les moyennes.</p>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="category">
				<span class="ci-ligne__nom" id="ciCategorieNom">Catégorie</span>
				<div class="ci-ligne__valeur">
					<div class="ci-choix ci-choix--categorie" role="radiogroup" aria-labelledby="ciCategorieNom">
						<?php foreach ($ciCategories as $v => $nom): ?>
						<label><input type="radio" name="category" value="<?= $ciH($v) ?>" data-ci-libelle="<?= $ciH($nom) ?>"<?= (string) $v === $ciCategorie ? ' checked' : '' ?>><span><?= $ciH($nom) ?></span></label>
						<?php endforeach; ?>
					</div>
					<p class="ci-avant" hidden></p>
				</div>
			</div>

			<div class="ci-ligne" data-ci-champ="lab">
				<label class="ci-ligne__nom" for="ciLabo">Laboratoire</label>
				<div class="ci-ligne__valeur">
					<select class="ci-liste ci-liste--courte" id="ciLabo" name="lab">
						<?php foreach ($ciLabos as $v => $nom): ?>
						<option value="<?= $ciH($v) ?>"<?= (string) $v === $ciLabo ? ' selected' : '' ?>><?= $ciH($nom) ?></option>
						<?php endforeach; ?>
					</select>
					<p class="ci-avant" hidden></p>
				</div>
			</div>
		</section>

		<section class="ci-bloc ci-bloc--cout" aria-labelledby="ciBlocCout">
			<h3 class="ci-bloc__titre" id="ciBlocCout">Coût</h3>
			<div class="ci-ligne ci-ligne--calcul">
				<span class="ci-ligne__nom">Écolage</span>
				<div class="ci-ligne__valeur">
					<p class="ci-cout"><span class="ci-cout__calcul" data-ci-cout="calcul"></span><b class="ci-cout__total" data-ci-cout="ecolage"><?= $ciAr($profil['cout'] ?? 0) ?></b></p>
				</div>
			</div>
			<div class="ci-ligne ci-ligne--calcul">
				<span class="ci-ligne__nom">Laboratoire</span>
				<div class="ci-ligne__valeur">
					<p class="ci-cout"><b class="ci-cout__total" data-ci-cout="labo"><?= $ciAr($profil['cout_lab'] ?? 0) ?></b></p>
				</div>
			</div>
			<p class="ci-aide ci-bloc__note" data-ci-cout="note">Calculé à l'enregistrement d'après le barème de la session en cours.</p>
		</section>

	</fieldset>

	<div class="ci-barre" data-ci-barre>
		<p class="ci-barre__etat" aria-live="polite"><b data-ci-compte></b><span class="ci-barre__notes" data-ci-notes hidden></span></p>
		<div class="ci-barre__actions">
			<button type="button" class="ci-bouton ci-bouton--discret" data-ci-annuler>Annuler</button>
			<button type="submit" class="ci-bouton ci-bouton--plein" data-ci-enregistrer title="Enregistrer (Ctrl+S)"><i class="bi bi-check2" aria-hidden="true"></i><span>Enregistrer</span></button>
		</div>
	</div>
	</form>

	<script type="application/json" data-ci-config><?= json_encode($ciConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</section>

<script src="<?=$app_base?>/src/js/cours.sigle.js?v=20260915-1"></script>
<script src="<?=$app_base?>/src/js/cours.information.js?v=<?= @filemtime(__DIR__ . '/../js/cours.information.js') ?>"></script>
<script>if (window.CoursInformation) { CoursInformation.monter(); }</script>
