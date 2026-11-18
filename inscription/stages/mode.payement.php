<?php

	// Fragment charge en AJAX : il rend des donnees de l'etudiant, il doit
	// donc verifier l'acces. _guard.php fournit aussi $dtb et $app_base.
	require_once(__DIR__ . '/../app/_guard.php');
	inscriptionGuardFragment();
	require_once(__DIR__ . '/../../data/paiement.echeances.php'); // dates limites des tranches

	$student_id = $_POST['student_id'] ?? '';

	// Session de reference : celle transmise si elle appartient bien a cet
	// etudiant, sinon celle de l'annee en cours. Le repli precedent prenait
	// « l'id le plus grand », donc parfois une autre annee — et les frais
	// releves ne correspondaient plus aux cours affiches.
	//
	// Le resolveur renvoie un entier : ce que le client envoie n'atteint plus
	// les requetes, qui interpolaient auparavant $_POST['session_id'] tel quel.
	$session_id = inscSessionIdCourant($dtb, (string) $student_id, $_POST['session_id'] ?? null);

	// Utiliser des requêtes préparées pour éviter l'injection SQL
	$stmt = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id AND remove != 1 LIMIT 1');
	$stmt->execute(['student_id' => $student_id]);
	$recupsdt = $stmt;

	$profil = $recupsdt->fetch();
	
	// Vérification si l'étudiant est suspendu
	$isSuspended = false;
	if (isset($profil['suspended']) && $profil['suspended'] == 1) {
		$dateFin = $profil['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			$isSuspended = true;
		}
	}
	
	if ($isSuspended) {
		echo '<div class="insc-alerte insc-alerte--danger" role="alert">
			<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
			<div><b>Étudiant suspendu</b>
			<p>Cet étudiant est suspendu et ne peut pas effectuer d\'inscription.</p></div>
		</div>';
		return;
	}
	
		$id = $profil['id'];
		$student_id = $profil['student_id'];
		$student_nom = $profil['student_nom'];
		$student_prenom = $profil['student_prenom'];
		$level = $profil['annee_etude'];
		$annee_scolaire = $profil['annee_scolaire'];
		$etude_envisage = $profil['etude_envisage'];
		$etude_option = $profil['etude_option'];
		$student_tel = $profil['student_tel'];
		$image_student = $profil['image_student'];
		$lookup_code = $profil['lookup_code'];
		$status = $profil['status'];
		$graduated = $profil['graduated'];
		$abonment = $profil['abonment'];
		$date_entry = $profil['date_entry'];



	// Requête préparée pour cours finance
	$stmtCours = $dtb->prepare('SELECT * FROM t_2024_cours_finance WHERE student_id = :student_id AND session_id = :session_id AND remove != 1');
	$stmtCours->execute(['student_id' => $student_id, 'session_id' => $session_id]);
	$findCoursFinance = $stmtCours;
		$nbr = 0;
		$tCredit = 0;
		$tCout = 0;
		$tLab = 0;



 ?>
<div class="insc-pay">

	<section class="insc-pay-releve" aria-labelledby="inscPayCours">

		<h3 class="insc-pay-titre" id="inscPayCours">Cours de la session <span class="insc-pay-code">session n° <?= (int) $session_id ?></span></h3>

		<div class="insc-pay-cadre">
			<table class="insc-pay-table">
				<thead>
					<tr>
						<th>Sigle</th>
						<th>Titre du cours</th>
						<th class="insc-num">Crédits</th>
						<th>Catégorie</th>
						<th class="insc-num">Coût</th>
						<th class="insc-num">Labo</th>
						<th><span class="sr-only">Retirer</span></th>
					</tr>
				</thead>
				<tbody>
<?php
	// Requete preparee : $session_id et $student_id etaient interpoles dans la
	// chaine SQL, et $session_id venait directement du POST.
	$findCoursFinance = $dtb->prepare('SELECT * FROM t_2024_cours_finance
		WHERE student_id = :student_id AND session_id = :session_id AND remove != 1');
	$findCoursFinance->execute(['student_id' => $student_id, 'session_id' => $session_id]);

	// Categorie et cout de labo relus une seule fois par cours, hors boucle
	// d'affichage : la requete etait rejouee a chaque ligne.
	$infosCours = $dtb->prepare('SELECT category, lab, cout_lab FROM t_2023_cours WHERE id = :id');

	$nbr = 0;
	$tCredit = 0;
	$tCout = 0;
	$tLab = 0;
	$n_lab = 0;
	$somm_lab = 0;

	// $session_id n'est plus reaffecte depuis les lignes lues : la valeur sert
	// ensuite a chercher les frais et a construire le formulaire, elle ne doit
	// pas dependre du contenu du tableau.
	while($showCF = $findCoursFinance->fetch()) {
 ?>

				<tr id="coursSupprime<?=$nbr?>">

					<td><span class="insc-pay-sigle"><?= e($showCF['cours_sigle']) ?></span></td>
					<td><?= e($showCF['cours_title']) ?></td>
					<td class="insc-num"><?=$showCF['cours_credit']?></td>
					<td><?php

$infosCours->execute(['id' => (int) $showCF['cours_id']]);
$showCat = $infosCours->fetch();
// Cours absent du catalogue : ne pas planter sur un acces a false. 99 ne
// correspond a aucune categorie connue, la cascade affiche donc « - » — alors
// que null aurait ete egal a 0 et annonce « Général » a tort.
if (!$showCat) { $showCat = ['category' => 99, 'lab' => 0, 'cout_lab' => 0]; }
if ($showCat['category'] == 0){
	echo "Général";
}elseif ($showCat['category'] == 1) {
	echo "Majeur";
}elseif ($showCat['category'] == -1 OR $showCat['category'] == 2) {
	echo "Selective";
}elseif ($showCat['category'] == 3) {
	echo "Additionnel";
}elseif ($showCat['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
				?></td>
					<td class="insc-num"><?=number_format($showCF['cours_cout'], 0, '', ' ')?></td>
					<td class="insc-num"><?php

					if ($showCat['lab'] != 0) {

						$n_lab =+ $n_lab + 1;

						if ($n_lab <= 2) {

							echo number_format($showCat['cout_lab'], 0, '', ' ');
							$somm_lab =+ $somm_lab + $showCat['cout_lab'];
							echo "<a id='cout_lab' style='display:none'>".$showCat['cout_lab']."</a>";

						}else{
							// Au-dela de deux labos, le cout n'est plus facture.
							echo "<s class='insc-pay-offert' title='Au-delà de deux labos, non facturé'>".number_format($showCat['cout_lab'], 0, '', ' ')."</s>";
							echo "<a id='cout_lab' style='display:none'>0</a>";
						}

					} else {
						echo '—';
					}
					?></td>
					<td class="insc-pay-action">
						<button type="button" id="coursInsert<?=$nbr?>" class="insc-pay-retirer" title="Retirer ce cours" aria-label="Retirer <?= e($showCF['cours_sigle']) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
					</td>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#coursInsert<?=$nbr?>').click(function(){

							var cours_id = '<?=$showCF['cours_id']?>';
							var student_id = '<?=$student_id?>';
							var session_id = '<?=$session_id?>';
							var nbr = '<?=$nbr?>';
							var credit = '<?=$showCF['cours_credit']?>';
							var btn = $(this);

							// Désactiver le bouton pendant le traitement
							btn.prop('disabled', true).addClass('opacity-50');

							// POST : un retrait modifie des données, il ne doit pas être
							// déclenchable par une simple URL. Les montants ne sont plus
							// envoyés — le serveur les relit en base et les renvoie.
							$.post(APP_BASE + '/inscription/app/retrait.cours.php', {
								cours_id:   cours_id,
								student_id: student_id,
								session_id: session_id,
								csrf_token: CSRF_TOKEN
							}, function(response) {

								if (!response || response.success !== true) {
									btn.prop('disabled', false).removeClass('opacity-50');
									Toast.error((response && response.message) || 'Erreur lors du retrait du cours');
									return;
								}

					        	$('#coursSupprime<?=$nbr?>').fadeOut(300);

					        	// Montants faisant autorité : ceux confirmés par le serveur.
					        	var coutCours = parseInt(response.cours_cout) || 0;
					        	var coutLabCours = parseInt(response.lab_cout) || 0;

					        	// Utiliser data-value pour les valeurs brutes (pas les valeurs formatées)
					        	var totalCours = parseInt($('#totalCours').data('value')) || 0;
					        	var totalLab = parseInt($('#totalLab').data('value')) || 0;
					        	var montant = parseInt($('#montant').data('value')) || 0;
								var tranchable = parseInt($('#tranchable').data('value')) || 0;

								var tCredit = parseInt($('#tCredit').data('value')) || parseInt($('#tCredit').text()) || 0;
								var nbCours = parseInt($('#nbCours').text()) || 0;

					        	// Calculer les nouvelles valeurs
					        	var restTotalCours = totalCours - coutCours;
					        	var restTotalLab = totalLab - coutLabCours;
					        	var restMontant = montant - coutCours - coutLabCours;
					        	var restTranchable = tranchable - coutCours - coutLabCours;

					        	var restCredit = tCredit - parseInt(credit);
					        	var restnbCours = nbCours - 1;

					        	// Mettre à jour les valeurs affichées ET les data-value
					        	$('#totalCours').text(restTotalCours.toLocaleString('fr-FR')).data('value', restTotalCours);
					        	$('#totalLab').text(restTotalLab).data('value', restTotalLab);
					        	$('#montant').text(restMontant.toLocaleString('fr-FR')).data('value', restMontant);
					        	$('#tranchable').text(restTranchable.toLocaleString('fr-FR')).data('value', restTranchable);

					        	$('#tCredit').text(restCredit).data('value', restCredit);
					        	$('#nbCours').text(restnbCours);

					        	// Mettre à jour les options de paiement
					        	$('.paie100').text(restTranchable.toLocaleString('fr-FR'));
					        	$('.paie50').text(Math.round((restTranchable*50)/100).toLocaleString('fr-FR'));
					        	$('.paie40').text(Math.round((restTranchable*40)/100).toLocaleString('fr-FR'));
					        	$('.paie30').text(Math.round((restTranchable*30)/100).toLocaleString('fr-FR'));
					        	$('.paie25').text(Math.round((restTranchable*25)/100).toLocaleString('fr-FR'));

					        	Toast.success('Cours retiré avec succès');
					        }).fail(function(xhr) {
					        	btn.prop('disabled', false).removeClass('opacity-50');
					        	var motif = (xhr && xhr.responseJSON && xhr.responseJSON.message)
					        		? xhr.responseJSON.message
					        		: 'Erreur lors du retrait du cours';
					        	Toast.error(motif);
					        });

						});
					});

				</script>
				</tr>
<?php
	$nbr++;
	$tCredit =+ $tCredit + $showCF['cours_credit'];
	$tCout =+ $tCout + $showCF['cours_cout'];
	$tLab =+ $tLab + $showCF['lab_cout'];
	}
	if ($nbr === 0) {
 ?>
				<tr class="insc-pay-vide"><td colspan="7">Aucun cours pour cette session : ajoutez-les à l'étape 3.</td></tr>
<?php } ?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="2"><span id="nbCours"><?=$nbr?></span> cours</th>
						<th class="insc-num" id="tCredit" data-value="<?=$tCredit?>"><?=$tCredit?></th>
						<th colspan="4"></th>
					</tr>
				</tfoot>
			</table>
		</div>
<?php

	// Requete preparee (les deux valeurs etaient interpolees dans le SQL) et
	// choix deterministe parmi les doublons : voir inscFinanceSession().
	$showFin = inscFinanceSession($dtb, (string) $student_id, $session_id);

	if (empty($showFin)) {
		/* Aucune ligne de frais pour ce couple (etudiant, session). Le cas est
		   loin d'etre marginal — 140 couples de 2025-2026 en base — et il etait
		   traite par un silence : le tableau des frais et le total
		   disparaissaient, la fiche s'imprimait complete en apparence et fausse.
		   Il est desormais nomme, avec la manoeuvre qui le corrige. */
	?>
		<div class="insc-alerte insc-alerte--warning" role="alert">
			<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
			<div>
				<b>Frais de scolarité absents</b>
				<p>Aucune ligne financière n'existe pour cette session
					<?php if (!empty($session_id)) { ?>(session <?= (int) $session_id ?>)<?php } ?> :
					les montants ne peuvent pas être calculés.</p>
				<?php /* « Revenez a l'etape 1 » menait a une impasse : une session deja
				         enregistree y desactive le bouton. Le recalcul cree la ligne
				         (inscription/app/recalculer-frais.php), au bareme ou, pour une
				         session passee, au tarif de l'annee. */ ?>
				<?php if ((int) ($_SESSION['user_level'] ?? 99) <= 3) { ?>
				<p>Créez-les : barème de la session, ou, pour une année passée, ce qui était facturé cette année-là.</p>
				<button id="recalculerFrais" type="button" class="insc-btn insc-btn--primaire insc-btn--petit" style="margin-top:.5rem">
					<i class="bi bi-plus-circle" aria-hidden="true"></i> Créer les frais
				</button>
				<?php } else { ?>
				<p>Le registrariat peut les créer depuis cette étape.</p>
				<?php } ?>
			</div>
		</div>
	<?php
	} else {

		/* Montants calcules ICI, hors du balisage.
		   Ils etaient calcules a l'interieur d'un attribut data-value, sous la
		   forme « <?= $a = ...; $b = ... ?> ». Or <?= est un echo : il n'affiche
		   que la PREMIERE instruction. data-value portait donc les frais de
		   graduation — 0 pour la plupart des etudiants — et non le total.
		   Tout le calcul JavaScript (retrait d'un cours, saisie des frais
		   generaux, options de paiement) repartait de ce 0 et affichait des
		   montants negatifs ou faux. */

		$frais_graduation = ($graduated == 1) ? (float) $showFin['cout_frais_graduation'] : 0;

		$Montant = (float) $showFin['cout_fraix_generaux']
			+ (float) $showFin['cout_logement']
			+ (float) $showFin['cout_fondDepot_dortoir']
			+ (float) $showFin['cout_abonment']
			/* cout_costume et cout_voyage restent exclus du total, comme dans
			   la version precedente : regle metier a trancher, pas un oubli
			   introduit ici. */
			+ $frais_graduation
			+ (float) $tCout
			+ (float) $somm_lab;

		$Montant_sans_fraix_Generaux = $Montant
			- ((float) $showFin['cout_fraix_generaux'] + (float) $showFin['cout_fondDepot_dortoir']);

		$ar = static fn ($n): string => number_format((float) $n, 0, '', ' ');

		/* La date limite de chaque tranche, reglee par session sur
		   /finance/echeances : « 30 oct. 2026 », puis l'annee seulement quand
		   elle change. Sans date : « a definir ». */
		$echeances = echeancesSession($dtb, $session_id);
		$echeancesManquantes = false;
		$datesType = static function (string $type, int $n) use ($echeances, &$echeancesManquantes): array {
			static $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
			$sortie = [];
			$anneePrecedente = null;
			for ($rang = 1; $rang <= $n; $rang++) {
				$date = $echeances[$type][$rang] ?? null;
				if (!$date) {
					$echeancesManquantes = true;
					$sortie[$rang] = '<small class="insc-pay-date is-vide">à définir</small>';
					continue;
				}
				$t = strtotime($date . ' 12:00:00');
				$annee = date('Y', $t);
				$sortie[$rang] = '<small class="insc-pay-date">' . (int) date('j', $t) . " " . $mois[(int) date('n', $t) - 1]
					. ($annee !== $anneePrecedente ? ' ' . $annee : '') . '</small>';
				$anneePrecedente = $annee;
			}
			return $sortie;
		};
		$datesA = $datesType('A', 1);
		$datesB = $datesType('B', 2);
		$datesE = $datesType('E', 4);
 ?>

		<h3 class="insc-pay-titre">Frais de la session</h3>
		<?php /* Les postes affiches dependent du dossier (interne, cafeteria,
		         premiere annee de theologie, graduation). */ ?>
		<dl class="insc-pay-frais">
			<div><dt>Frais généraux</dt><dd><?= $ar($showFin['cout_fraix_generaux']) ?></dd></div>
			<?php if ($status == "Interne" OR $status == "Bungalow") { ?>
			<div><dt>Logement</dt><dd><?= $ar($showFin['cout_logement']) ?></dd></div>
			<?php } ?>
			<?php if ($abonment == 1) { ?>
			<div><dt>Cafétéria</dt><dd><?= $ar($showFin['cout_abonment']) ?></dd></div>
			<?php } ?>
			<?php if ($etude_envisage == "Théologie" AND $level == 1) { ?>
			<div><dt>Frais costume</dt><dd><?= $ar($showFin['cout_costume']) ?></dd></div>
			<?php } ?>
			<?php if ($graduated == 1) { ?>
			<div><dt>Frais graduation</dt><dd><?= $ar($showFin['cout_frais_graduation']) ?></dd></div>
			<?php } ?>
			<div><dt><?= $etude_envisage == "Théologie" ? 'Colloque' : "Voyage d'étude" ?></dt><dd><?= $ar($showFin['cout_voyage']) ?></dd></div>
			<div><dt>Total cours</dt><dd><span id="totalCours" data-value="<?=$tCout?>"><?= $ar($tCout) ?></span></dd></div>
			<div><dt>Total labo</dt><dd><span id="totalLab" data-value="<?=$somm_lab?>"><?=$somm_lab?></span></dd></div>
		</dl>

		<div class="insc-pay-totaux">
			<div class="insc-pay-ligne">
				<span>Total</span>
				<b><span id="montant" data-value="<?=(int) $Montant?>"><?= $ar($Montant) ?></span> ar</b>
			</div>
			<div class="insc-pay-ligne">
				<label for="paymentOnInscription">Frais généraux</label>
				<span class="insc-pay-saisie"><input type="text" inputmode="numeric" id="paymentOnInscription" name="paymentOnInscription" value="<?= $ar($pay_inscription = $showFin['cout_fraix_generaux']) ?>"> ar</span>
			</div>
			<div class="insc-pay-ligne insc-pay-ligne--reste">
				<span>Reste à répartir en tranches</span>
				<b><span id="tranchable" data-value="<?=(int) $Montant_sans_fraix_Generaux?>"><?= $ar($Montant_sans_fraix_Generaux) ?></span> ar</b>
			</div>
		</div>

		<?php /* Recalcul : les montants sont figes a l'inscription. C'est ici,
		         devant les totaux, qu'on s'apercoit d'un bareme change depuis —
		         et non a l'etape 0, ou il n'aurait rien a dire. Reserve au
		         registrariat, comme toute ecriture de montant. */ ?>
		<?php if ((int) ($_SESSION['user_level'] ?? 99) <= 3) { ?>
			<button id="recalculerFrais" type="button" class="insc-btn insc-btn--second insc-btn--petit"
			        title="Reprendre les montants du barème de cette session">
				<i class="bi bi-arrow-repeat" aria-hidden="true"></i> Recalculer les frais
			</button>
		<?php } ?>

<?php } /* fin du else : une ligne de frais existe */ ?>

	</section>

	<section class="insc-pay-options" aria-labelledby="inscPayOptions">
		<h3 class="insc-pay-titre" id="inscPayOptions">Options de paiement</h3>

<?php if (!empty($Montant)) {
?>
<form method="post" action="<?=$app_base?>/inscription/app/mode.payement.php?student_id=<?=urlencode($student_id)?>&session_id=<?=urlencode($session_id)?>" class="form-payement">
	<?= csrf_field() ?>
		<?php /* Les montants des tranches suivent la saisie des frais generaux et
		         le retrait d'un cours (scripts plus bas) : ils sont recalcules
		         dans les .paieNN, l'unite reste a cote. Les types C et D ne
		         sont plus proposes. */ ?>
		<fieldset class="insc-pay-choix">
			<legend class="sr-only">Mode de paiement</legend>

			<label class="insc-pay-option">
				<input type="radio" name="modePayement" id="a" value="A">
				<span class="insc-pay-option-corps"><b class="choiceType">Type A</b><span>En une fois, 100&nbsp;%</span></span>
				<span class="insc-pay-tranches"><?= $datesA[1] ?><span><span class="paie100"><?= $ar($Montant_sans_fraix_Generaux) ?></span> ar</span></span>
			</label>

			<label class="insc-pay-option">
				<input type="radio" name="modePayement" id="b" value="B">
				<span class="insc-pay-option-corps"><b class="choiceType">Type B</b><span>Deux tranches de 50&nbsp;%</span></span>
				<span class="insc-pay-tranches">
					<?= $datesB[1] ?><span><span class="paie50"><?= $ar(($Montant_sans_fraix_Generaux*50) /100) ?></span> ar</span>
					<?= $datesB[2] ?><span><span class="paie50"><?= $ar(($Montant_sans_fraix_Generaux*50) /100) ?></span> ar</span>
				</span>
			</label>

			<label class="insc-pay-option">
				<input type="radio" name="modePayement" id="e" value="E">
				<span class="insc-pay-option-corps"><b class="choiceType">Type E</b><span>Quatre tranches de 25&nbsp;%</span></span>
				<span class="insc-pay-tranches">
					<?= $datesE[1] ?><span><span class="paie25"><?= $ar(($Montant_sans_fraix_Generaux*25) /100) ?></span> ar</span>
					<?= $datesE[2] ?><span><span class="paie25"><?= $ar(($Montant_sans_fraix_Generaux*25) /100) ?></span> ar</span>
					<?= $datesE[3] ?><span><span class="paie25"><?= $ar(($Montant_sans_fraix_Generaux*25) /100) ?></span> ar</span>
					<?= $datesE[4] ?><span><span class="paie25"><?= $ar(($Montant_sans_fraix_Generaux*25) /100) ?></span> ar</span>
				</span>
			</label>
		</fieldset>
		<?php if ($echeancesManquantes) { ?>
		<p class="insc-pay-note"><i class="bi bi-calendar-x" aria-hidden="true"></i><span>Des échéances de cette session restent à définir<?php
			if ((int) ($_SESSION['user_level'] ?? 99) <= 3) { ?>&nbsp;: <a href="<?=$app_base?>/finance/echeances?session_id=<?= (int) $session_id ?>" target="_blank" rel="noopener">les définir</a>.<?php } else { ?> par le registrariat.<?php } ?></span></p>
		<?php } ?>

		<button id="submit-payement" type="submit" class="insc-btn insc-btn--primaire toolInactive" disabled>Enregistrer le mode de paiement</button>
</form>
<?php
} else { ?>
		<div class="insc-alerte insc-alerte--warning" role="status">
			<i class="bi bi-exclamation-circle" aria-hidden="true"></i>
			<div>
				<b>Options de paiement indisponibles</b>
				<p>Vérifiez que les cours ont bien été ajoutés et que les frais sont configurés pour cette session.</p>
			</div>
		</div>
<?php } ?>
	</section>

</div>

<script type="text/javascript">
	$(document).ready(function(){
		/** Nombre a partir d'une saisie ou d'un texte formate (« 1 234 567 »). */
		function montantNumerique(valeur) {
			var n = parseInt(String(valeur == null ? '' : valeur).replace(/[^0-9-]/g, ''), 10);
			return isNaN(n) ? 0 : n;
		}

		$('#paymentOnInscription').on('input', function() {

			// Le total etait lu dans le TEXTE de #montant, deja formate avec des
			// espaces comme separateur de milliers : « 1 234 567 » - 500 donnait
			// NaN, et « Reste a tranche » comme toutes les options de paiement
			// affichaient NaN. La valeur brute est dans data-value, celle-la
			// meme que le retrait de cours utilise deja.
			var paymentOnInscription = montantNumerique($(this).val());
			var montant = montantNumerique($('#montant').data('value'));
			var tranchable = montant - paymentOnInscription;

			var fr = function (n) { return Math.round(n).toLocaleString('fr-FR'); };

			$('#tranchable').text(fr(tranchable)).data('value', tranchable);
			$('.paie100').text(fr(tranchable));
			$('.paie75').text(fr((tranchable*75)/100));
			$('.paie50').text(fr((tranchable*50)/100));
			$('.paie40').text(fr((tranchable*40)/100));
			$('.paie30').text(fr((tranchable*30)/100));
			$('.paie25').text(fr((tranchable*25)/100));
		});
	
		$('input[name="modePayement"]').change(function() {
			if ($(this).is(':checked')) {
				$('#submit-payement').removeClass('toolInactive').prop('disabled', false);
			}
		});

		$('.form-payement').on('submit',function(submitP){
			submitP.preventDefault();
			
			var submitBtn = $(this).find('button[type="submit"]');
			var originalText = submitBtn.text();
			submitBtn.prop('disabled', true).text('Enregistrement...');

			// Chemin absolu : le chemin relatif « ./app/… » dépendait de l'URL
			// courante et cassait dès que la page était atteinte autrement.
			var url	= APP_BASE + '/inscription/app/mode.payement.php?student_id=<?=urlencode($student_id)?>&session_id=<?=urlencode($session_id)?>';
			var data = $(this).serialize(); // contient le jeton CSRF du formulaire

			$.post(url,data,function(response){
				if (response && response.success === false) {
					Toast.error(response.message || "Erreur lors de l'enregistrement du mode de paiement");
					submitBtn.prop('disabled', false).text(originalText);
					return;
				}
				Toast.success('Mode de paiement enregistré avec succès!');
				// Enregistre : le bouton se rendort jusqu'au prochain choix.
				submitBtn.text(originalText).addClass('toolInactive').prop('disabled', true);
				$('#upStage').removeClass('toolInactive').prop('disabled', false);
			}).fail(function(xhr){
				var motif = (xhr && xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: "Erreur lors de l'enregistrement du mode de paiement";
				Toast.error(motif);
				submitBtn.prop('disabled', false).text(originalText);
			});

		});
	});
</script>