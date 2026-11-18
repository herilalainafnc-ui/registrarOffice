<?php
/* =============================================================================
   Volet « Ajout de cours » d'une fiche etudiant : la liste des cours, par
   niveau et par semestre, pour UNE annee scolaire.

   Une seule copie, incluse par src/student/new.cours.php (premier affichage)
   et par app/.student/get-cours-list.php (rechargement apres un ajout). Il y
   en avait deux, et la seconde choisissait la session d'apres le mois : en
   octobre, apres un premier ajout, le bloc « Semestre 2 » proposait « Premier
   semestre », et un cours de second semestre s'inscrivait au premier.

   Attend : $dtb, $profil (ligne de tbl_2024_etudiant), $app_base, $rg_id,
   les couleurs $bg_*_color, $academicYear (annee affichee),
   $toutProgramme (vrai : tout le programme du niveau, pas seulement les
   cours offerts) et $avecChoixAnnee (faux au guichet d'inscription, qui
   n'a ni choix d'annee ni case « programme »).

   L'annee choisie vaut pour tout le volet : chaque formulaire l'envoie telle
   quelle (champ cache), les cours ne peuvent pas partir dans une autre annee
   que celle dont on regarde la liste.
   ========================================================================== */
require_once __DIR__ . '/../../data/cours.offerts.php';
require_once __DIR__ . '/../../data/cours.sigle.php';

$etude_envisage = $profil['etude_envisage'] ?? '';
$etude_option = $profil['etude_option'] ?? '';
$id = $profil['id'] ?? ($id ?? '');
$student_id = $profil['student_id'] ?? ($student_id ?? '');

$codesMention = [
	'Théologie' => 'THEO', 'Gestion' => 'GEST', 'Informatique' => 'INFO', 'Sciences Infirmières' => 'NURS',
	'Education' => 'EDUC', 'Communication' => 'COMM', 'Etudes anglophones' => 'LANG', 'Droit' => 'DROI',
];
$eE = $codesMention[$etude_envisage] ?? '';

$stParcours = $dtb->prepare('SELECT shortcode FROM filiere_parcours WHERE description = ? LIMIT 1');
$stParcours->execute([$etude_option]);
$parcours = (string) ($stParcours->fetchColumn() ?: '');

/* Licence : les trois niveaux ; Master : les deux. */
$niveauEtudiant = (int) ($profil['annee_etude'] ?? 1);
[$init, $dernier] = $niveauEtudiant <= 3 ? [1, 3] : [4, 5];

/* Les cours deja au cursus de l'etudiant, lus en UNE requete. On en faisait
   une par cours affiche (0,2 s chacune en local) : le programme complet d'un
   niveau, une cinquantaine de cours, mettait cinq secondes a s'afficher. La
   premiere note de chaque cours fait foi, comme le fetch() d'avant. */
$notesEtudiant = [];
$stNotes = $dtb->prepare('SELECT * FROM t_2023_notes WHERE student_id = :student_id AND ajout = 1 AND remove = 0 ORDER BY id');
$stNotes->execute(['student_id' => $student_id]);
foreach ($stNotes->fetchAll(PDO::FETCH_ASSOC) as $ligneNote) {
	$notesEtudiant[(string) $ligneNote['id_cours']] ??= $ligneNote;
}
$anneePassee = coursAnneePassee($academicYear);

for ($a = $init; $a <= $dernier; $a++) {
?>
	<div id="year<?=$a?>" class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<b><?= $a <= 3 ? 'NIVEAU Licence ' . $a : 'NIVEAU Master ' . ($a - 3) ?></b>
<?php
	for ($s = 1; $s <= 2; $s++) {
		$defaultSessionLabel = coursSessionLabel((int) $s);
		/* Le programme remplace la liste des cours offerts : sur demande, ou
		   pour une annee PASSEE qui n'en a pas (avant « Cours par session »).
		   Pour l'annee en cours, une liste vide veut dire « pas encore
		   preparee » : on le dit, sans ouvrir tout le programme. */
		$parProgramme = $toutProgramme || ($anneePassee && !coursSessionAOffres($dtb, $academicYear, $defaultSessionLabel));
		$filtre = ['dep_desc' => $eE, 'yearlevel' => $a, 'parcours' => $parcours];
		$cours = $parProgramme
			? coursProgramme($dtb, $filtre + ['semestre' => $s])
			: coursOfferts($dtb, $filtre + ['session_label' => $defaultSessionLabel, 'academic_year' => $academicYear]);
?>
 <form action="javascript:void(0);" data-action="<?=$app_base?>/app/.student/checkCours.php" method="post" class="form-newCours">
 	<input type="hidden" name="id" value="<?=htmlspecialchars((string) $id)?>">
 	<input type="hidden" name="student_id" value="<?=htmlspecialchars((string) $student_id)?>">
 	<input type="hidden" name="page" value="newCours">
 	<input type="hidden" name="user_id" value="<?=htmlspecialchars((string) ($rg_id ?? ''))?>">
 	<input type="hidden" name="etude_envisage" value="<?=htmlspecialchars($profil['etude_envisage'] ?? '')?>">
 	<input type="hidden" name="status" value="<?=htmlspecialchars($profil['status'] ?? '')?>">
 	<input type="hidden" name="new_student" value="<?=htmlspecialchars((string) ($profil['new_student'] ?? ''))?>">
 	<input type="hidden" name="graduated" value="<?=htmlspecialchars((string) ($profil['graduated'] ?? ''))?>">
 	<input type="hidden" name="student_adresse" value="<?=htmlspecialchars($profil['student_adresse'] ?? '')?>">
 	<input type="hidden" name="etude_option" value="<?=htmlspecialchars($profil['etude_option'] ?? '')?>">
 	<input type="hidden" name="annee_etude" value="<?=htmlspecialchars((string) ($profil['annee_etude'] ?? ''))?>">
 	<?php /* Niveau des cours de ce bloc : pour une annee passee, c'est lui
 	         qu'on inscrit dans la session (app/.student/checkCours.php),
 	         pas le niveau actuel de l'etudiant. */ ?>
 	<input type="hidden" name="niveau_bloc" value="<?=$a?>">
 	<input type="hidden" name="sponsor_nom" value="<?=htmlspecialchars($profil['sponsor_nom'] ?? '')?>">
 	<input type="hidden" name="sponsor_prenom" value="<?=htmlspecialchars($profil['sponsor_prenom'] ?? '')?>">
 	<input type="hidden" name="sponsor_tel" value="<?=htmlspecialchars($profil['sponsor_tel'] ?? '')?>">
 	<input type="hidden" name="sponsor_adresse" value="<?=htmlspecialchars($profil['sponsor_adresse'] ?? '')?>">
 	<input type="hidden" name="situationf" value="<?=htmlspecialchars($profil['situationf'] ?? '')?>">
 	<input type="hidden" name="nom_conjoint" value="<?=htmlspecialchars($profil['nom_conjoint'] ?? '')?>">
 	<input type="hidden" name="nb_enfant" value="<?=htmlspecialchars((string) ($profil['nb_enfant'] ?? 0))?>">
 	<input type="hidden" name="abonment" value="<?=htmlspecialchars((string) ($profil['abonment'] ?? 0))?>">
 	<input type="hidden" name="annee_scolaire" value="<?=htmlspecialchars($academicYear)?>">

		<div class="tbl-x-scroll"><table class="simpleTbl mb-1 w-full">
			<thead>
				<tr class="text-left bg-gradient-to-r from-green-600">
					<th colspan="10">SEMESTRE <?=$s?> — <?=htmlspecialchars($academicYear)?>
<?php if ($parProgramme): ?>
						<span class="ml-2 font-normal text-amber-200">· programme complet du niveau<?= $toutProgramme ? '' : ' (aucune liste de cours offerts pour ce semestre)' ?></span>
<?php endif; ?>
					</th>
				</tr>
			</thead>
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<th class="w-5"></th>
					<th class="w-20">Sigle</th>
					<th class="">Titre du cours</th>
					<th class="sm:w-2/12 lg:w-3/12">Observations</th>
					<th class="w-20">Crédits</th>
					<th class="w-20">Catégorie</th>
				</tr>
			</thead>
			<tbody class="<?=$bg_four_color?>">
<?php
		$nbr = 0;
		$tcredit = 0;
		foreach ($cours as $crs) {
			$note_id = $crs['id'];

			// Le cours existe-t-il deja pour cet etudiant (toutes sessions) ?
			$validExisting = $notesEtudiant[(string) $note_id] ?? false;

			/* Etat du cours pour cet etudiant, decide UNE fois. Codes de
			   statut (src/student/transcriptSS.php) : -2 valide sans note,
			   -3 echec sans note, -4 pas encore note, -1 ancien code. Ce qui
			   est REPRENABLE est enumere ; tout le reste qui existe est deja
			   pris, y compris un code ajoute plus tard. */
			$dejaPris = !empty($validExisting);
			$note = $dejaPris ? $validExisting['grade'] : null;
			$noteChiffree = ($dejaPris && is_numeric($note)) ? (float) $note : null;
			$reprenable = $dejaPris && (
				($noteChiffree !== null && $noteChiffree > 0 && $noteChiffree < 10)
				|| (int) $note === -3
			);

			if (!$dejaPris) {
				$observation = '';
				$motifMarque = '';
			} elseif ((int) $note === -2) {
				$observation = "<em class='text-green-400'><b>OK</b> — Validé</em>";
				$motifMarque = 'Déjà pris — validé sans note';
			} elseif ($noteChiffree !== null && $noteChiffree >= 10) {
				$observation = "<em class='text-green-400'><b>" . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . "</b> de moyenne</em>";
				$motifMarque = 'Déjà pris — moyenne ' . $note;
			} elseif ($noteChiffree !== null && $noteChiffree > 0 && $noteChiffree < 10) {
				$observation = "<em class='text-red-500'><i class='bi-arrow-repeat'></i> <b>" . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . "</b> de moyenne, en état d'échec. <span class='text-orange-400'>Reprise possible</span></em>";
				$motifMarque = '';
			} elseif ((int) $note === -3) {
				$observation = "<em class='text-red-500'><i class='bi-arrow-repeat'></i> Échec, sans note. <span class='text-orange-400'>Reprise possible</span></em>";
				$motifMarque = '';
			} elseif ((int) $note === -4) {
				$observation = "<em class='text-cyan-300'><i class='bi-hourglass-split'></i> Déjà pris — pas encore noté</em>";
				$motifMarque = 'Déjà pris — pas encore noté';
			} elseif ((int) $note === -1) {
				$observation = "<em class='text-cyan-300'>Déjà pris — note non chiffrée</em>";
				$motifMarque = 'Déjà pris — note non chiffrée';
			} elseif ($noteChiffree !== null && $noteChiffree == 0) {
				$observation = "<em class='text-orange-400'>Ajouté le " . htmlspecialchars(substr((string) $validExisting['date_entry'], 0, 10), ENT_QUOTES, 'UTF-8') . "</em>";
				$motifMarque = 'Déjà pris — ajouté le ' . substr((string) $validExisting['date_entry'], 0, 10);
			} else {
				$observation = "<em class='text-cyan-300'>Déjà pris</em>";
				$motifMarque = 'Déjà pris';
			}

			/* L'annee de la note deja saisie, quand elle differe de celle
			   affichee : on voit d'un coup d'oeil OU le cours a ete pris. */
			if ($dejaPris && !empty($validExisting['annee_scolaire']) && $validExisting['annee_scolaire'] !== $academicYear) {
				$observation .= " <span class='text-slate-400 text-xs'>(" . htmlspecialchars((string) $validExisting['annee_scolaire'], ENT_QUOTES, 'UTF-8') . ")</span>";
			}
?>
				<tr
<?php if (!$dejaPris): ?>
				id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black"
<?php elseif ($reprenable): ?>
				id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black bg-red-900/30 border-l-2 border-red-500"
<?php else: ?>
				<?php /* Deja pris et non reprenable : aucune action, pas de survol. */ ?>
				class="bg-slate-700"
<?php endif; ?>>
					<td class="p-0 text-center" style="height: 15px;">
<?php if (!$dejaPris || $reprenable): ?>
						<?php /* Taille fixe : a 100 % de la cellule, la case tombait a
						         0 px dans un bloc sans cours deja pris (la cellule n'a
						         que ses marges), et paraissait non cochable. */ ?>
						<input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>"
						       aria-label="Ajouter <?= htmlspecialchars(coursSigleAffiche($crs), ENT_QUOTES, 'UTF-8') ?>"
						       style="width: 16px; height: 16px; margin: 0; vertical-align: middle; cursor: pointer;"<?= $reprenable ? ' data-retake="1"' : '' ?>>
<?php else: ?>
						<?php /* La marque « deja pris » : un etat, pas un choix. Le
						         motif est en clair dans la colonne Observations. */ ?>
						<i class="bi-check-circle-fill text-green-400"
						   title="<?= htmlspecialchars($motifMarque, ENT_QUOTES, 'UTF-8') ?>"
						   aria-hidden="true"></i>
<?php endif; ?>
					</td>
					<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><?= htmlspecialchars(coursSigleAffiche($crs), ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= $etude_envisage === 'Etudes anglophones' ? $crs['title_english'] : $crs['title'] ?></td>
					<td><?= $observation ?></td>
					<td><?=$crs['nb_crd']?></td>
					<td><?= [0 => 'Général', 1 => 'Majeur', 2 => 'Selective', 3 => 'Additionnel'][(int) $crs['category']] ?? '-' ?></td>
				</tr>
<?php
			$tcredit += $crs['nb_crd'];
			$nbr++;
		}
		if ($nbr === 0) {
?>
				<tr>
					<td colspan="6" class="py-4 text-center text-slate-300">
						<?= $parProgramme
							? 'Aucun cours au programme de ce niveau pour ce semestre.'
							: 'Aucun cours offert pour ' . htmlspecialchars($defaultSessionLabel) . ' en ' . htmlspecialchars($academicYear) . '.'
								. (!empty($avecChoixAnnee) ? ' Cochez « Tout le programme du niveau » pour le voir en entier.' : '') ?>
					</td>
				</tr>
<?php
		}
?>
			</tbody>
			<tfoot class="<?=$bg_one_color?> text-white">
				<tr>
					<th colspan="2"></th>
					<th><?=$nbr?> cours</th>
					<th></th>
					<th><?=$tcredit?></th>
					<th></th>
				</tr>
				<tr>
					<td colspan="6" class="<?=$bg_four_color?>">
						<div class="flex flex-wrap items-center">
							<div class="w-40 pt-1">
								<a href="#" id="selectAll<?=$a.$s;?>" class="px-2 py-0 m-1"><i class="bi-arrow-90deg-up"></i> Cocher tout</a>
								<a href="#" id="deselectAll<?=$a.$s;?>" class="px-2 py-0 m-1 hidden"><i class="bi-arrow-90deg-up"></i> Décocher tout</a>
							</div>
							<div>
								<b>Session :</b>
								<select name="semesterSession" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarSs<?=$a.$s;?>">
<?php foreach (coursSessions() as $libelle): ?>
									<option value="<?=htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8')?>"<?= $libelle === $defaultSessionLabel ? ' selected' : '' ?>><?=htmlspecialchars($libelle)?></option>
<?php endforeach; ?>
								</select>
								<b class="ml-2">Année :</b>
								<span class="<?= $anneePassee ? 'text-amber-200' : '' ?>"><?=htmlspecialchars($academicYear)?></span>
							</div>
							<button id="submit<?=$a.$s;?>" type="submit" class="submiting px-2 text-center py-0 m-1 text-white bg-black">Ajouter au transcript</button>
						</div>
					</td>
				</tr>
			</tfoot>
		</table></div>
</form>
<?php
	}
	echo '</div>';
}
