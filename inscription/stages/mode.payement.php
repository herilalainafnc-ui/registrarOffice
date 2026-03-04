<?php

	require('../../data/backdb.php');

	$student_id = $_POST['student_id'] ?? '';
	$session_id = $_POST['session_id'] ?? '';

	// Fallback: si session_id est vide, le récupérer depuis la DB
	if (empty($session_id) && !empty($student_id)) {
		$stmtSession = $dtb->prepare('SELECT session_id FROM t_2024_inscription_session WHERE student_id = :student_id ORDER BY id DESC LIMIT 1');
		$stmtSession->execute(['student_id' => $student_id]);
		$sessionRow = $stmtSession->fetch();
		if ($sessionRow) {
			$session_id = $sessionRow['session_id'];
		}
	}
	
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
		echo '<div class="bg-orange-500 text-white p-6 rounded-lg text-center m-4">
			<i class="bi bi-exclamation-triangle-fill text-4xl"></i>
			<h3 class="text-lg font-bold mt-2">ÉTUDIANT SUSPENDU</h3>
			<p class="mt-2">Cet étudiant est suspendu et ne peut pas effectuer d\'inscription.</p>
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
<div class="flex gap-2 w-full overflow-auto" style="max-height: calc(100vh - 246px);">
	
	<div class="w-7/12 p-2">
		
		<div class="mx-4 my-2"><i class="bi-file-text-fill text-green-300 text-[30px]"></i><b> Cours ajouté.</b></div>

		<div class="bg-white w-full p-2 text-black text-xs rounded-md">
			<b>Liste des cours </b><a class="text-xs"> -  Session id = <?=$session_id?></a>

			
			<table class="tbl mb-2 text-[10px]" style="width: 100%">
				<thead class="bg-slate-200">
					<tr>
						<th style="width: 70px">Sigle</th>
						<th style="">Titre du cours</th>
						<th style="width: 40px">Crédits</th>
						<th style="width: 60px">Catégorie</th>
						<th style="width: 60px">Coût</th>
						<th style="width: 60px">Labo</th>
						<th style="width: 20px; background: #ed4343; color: white;"><i class="bi-trash-fill"></i></th>
					</tr>	
				</thead>
				<tbody>
<?php 
	$findCoursFinance = $dtb->query('SELECT * FROM t_2024_cours_finance WHERE student_id="'.$student_id.'" AND session_id="'.$session_id.'" AND remove != 1');
	
	$nbr = 0;
	$tCredit = 0;
	$tCout = 0;
	$tLab = 0;
	$n_lab = 0;
	$somm_lab = 0;
	
	while($showCF = $findCoursFinance->fetch()) {
		$session_id = $showCF['session_id'];
 ?>

				<tr class="hover:bg-slate-200 border" id="coursSupprime<?=$nbr?>">

					<td><?=$showCF['cours_sigle']?></td>
					<td><?=$showCF['cours_title']?></td>
					<td><?=$showCF['cours_credit']?></td>
					<td><?php

$findCat = $dtb->query('SELECT * FROM t_2023_cours WHERE id = "'.$showCF['cours_id'].'"');
$showCat = $findCat->fetch();
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
					<td class="text-right"><?=number_format($showCF['cours_cout'], 0, '', ' ').' ar'?></td>
					<td class="text-right"><?php 
					
					if ($showCat['lab'] != 0) {						
					
						$n_lab =+ $n_lab + 1;
						
						if ($n_lab <= 2) {

							echo number_format($showCat['cout_lab'], 0, '', ' ');
							$somm_lab =+ $somm_lab + $showCat['cout_lab'];
							echo "<a id='cout_lab' style='display:none'>".$showCat['cout_lab']."</a>";
					
						}else{
						
							echo "<a style='text-decoration: line-through; color: orange;'>".number_format($showCat['cout_lab'], 0, '', ' ')."</a>";
							echo "<a id='cout_lab' style='display:none'>0</a>";
						}

					}
					echo ' ar';?></td>
					<td class="bg-red-500 text-center">
						<a id="coursInsert<?=$nbr?>" href="#" class="text-white w-full text-center" title="Retirer" onclick="preventDefault();"><i class="bi-x-lg"></i></a>
						
					
					</td>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#coursInsert<?=$nbr?>').click(function(){
							
							var cours_id = '<?=$showCF['cours_id']?>';
							var student_id = '<?=$student_id?>';
							var session_id = '<?=$session_id?>';
							var coutCours = '<?=$showCF['cours_cout']?>';
							var coutLab = $('#cout_lab').text();
							var nbr = '<?=$nbr?>';
							var credit = '<?=$showCF['cours_credit']?>';
							var btn = $(this);

							var delUrl = "app/retrait.cours.php?cours_id="+cours_id+
							"&student_id="+student_id+
							"&session_id="+session_id+
							"&cours_cout="+coutCours+
							"&lab_cout="+coutLab;

							// Désactiver le bouton pendant le traitement
							btn.prop('disabled', true).addClass('opacity-50');

							$.get(delUrl, function(response) {
					        	
					        	$('#coursSupprime<?=$nbr?>').fadeOut(300);
					        	
					        	var coutCours = parseInt('<?=$showCF['cours_cout']?>') || 0;
					        	var coutLabCours = parseInt('<?=$showCF['lab_cout']?>') || 0;

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
					        }).fail(function() {
					        	btn.prop('disabled', false).removeClass('opacity-50');
					        	Toast.error('Erreur lors du retrait du cours');
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
 ?>
				</tbody>
				<tfoot>
					<tr class="bg-slate-200">
						<th colspan="2"><a id="nbCours"><?=$nbr?></a> cours</th>
						<th id="tCredit" data-value="<?=$tCredit?>"><?=$tCredit?></th>
					</tr>
				</tfoot>
			</table>
<?php 

	if(!empty($session_id)) {

		$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'" AND remove != 1 LIMIT 1');

		$showFin = $findFinance->fetch();

	
	if (!empty($showFin) AND (!empty($showFin['session_id']) OR $showFin['session_id'] != 0)) {
 ?>

 			<b class="text-xs">Finance</b>
		<table class="tbl mb-2 text-[10px]" style="width: 100%">
			<thead class="bg-slate-200">
				<tr>
					<th>Frais Généraux</th>
					<?php if ($status == "Interne" OR $status == "Bungalow") { ?>
						<th>Logement</th>
						<!-- <th>Fond Dépôt</th> -->
					<?php }?>

					<?php if ($abonment == 1) { ?>
						<th>Céféteria</th>
					<?php }?>

					<?php if ($etude_envisage == "Théologie" AND $level == 1) { ?>
						<th>Frais Costume</th>
					<?php }?>

					<?php if ($graduated == 1) { ?>
						<th>Frais Graduation</th>
					<?php }?>
					<th><?php 
						if ($etude_envisage == "Théologie") {
							echo "Colloque";
						}else{
							echo "Voyage d'étude";
						}

					 ?></th>
					<th>Total Cours</th>
					<th>Total Lab</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?=$showFin['cout_fraix_generaux']?></td>

					<?php if ($status == "Interne" OR $status == "Bungalow") { ?>
						<td><?=number_format($showFin['cout_logement'], 0, '', ' ')?></td>
						<!-- <td><?=number_format($showFin['cout_fondDepot_dortoir'], 0, '', ' ')?></td> -->
					<?php }?>

					<?php if ($abonment == 1) { ?>
						<td><?=number_format($showFin['cout_abonment'], 0, '', ' ')?></td>
					<?php }?>

					<?php if ($etude_envisage == "Théologie" AND $level == 1) { ?>
						<td><?=number_format($showFin['cout_costume'], 0, '', ' ')?></td>
					<?php }?>

					<?php if ($graduated == 1) { ?>
						<td><?=number_format($showFin['cout_frais_graduation'], 0, '', ' ')?></td>
					<?php } ?>
					<td><?=number_format($showFin['cout_voyage'], 0, '', ' ')?></td>
					<td><a id="totalCours" data-value="<?=$tCout?>"><?=number_format($tCout, 0, '', ' ')?></a></td>
					<td><a id="totalLab" data-value="<?=$somm_lab?>"><?=$somm_lab?></a></td>
				</tr>
			</tbody>
		</table>

		<table>
			<tr>
				<td class="text-right text-bold">Total = </td>
				<td class="bg-blue-300 p-1 text-right"><a id="montant" data-value="<?=
$Montant = $showFin['cout_fraix_generaux'] + 
							$showFin['cout_logement'] +
							$showFin['cout_fondDepot_dortoir'] +
							$showFin['cout_abonment'] +
							/*$showFin['cout_costume'] +*/
							$showFin['cout_frais_graduation'] +
							/*$showFin['cout_voyage'] +*/
							$tCout +
							$somm_lab
				?>"><?=number_format($Montant, 0, '', ' ')?></a></td>
				<td>ar</td>
			</tr>
			<tr>
				<td class="text-right text-bold">Frais généraux = </td>
				<td><input type="text" id="paymentOnInscription" name="paymentOnInscription" class="border-0 p-1 text-xs text-right" value="<?=number_format($pay_inscription = $showFin['cout_fraix_generaux'], 0, '', ' ')?>"></td>
				<td>ar</td>
			</tr>
			<tr>
				<td class="text-right text-bold">Reste à tranché = </td>
				<td class="bg-orange-300 p-1 text-right"><a id="tranchable" data-value="<?=
							$Montant_sans_fraix_Generaux = $Montant - ($showFin['cout_fraix_generaux'] + $showFin['cout_fondDepot_dortoir'])
				?>"><?=number_format($Montant_sans_fraix_Generaux, 0, '', ' ')?></a></td>
				<td>ar</td>
			</tr>
		</table>

<?php } } ?>


		</div>


	</div>

	<div class="w-5/12 my-2 mr-2">
		<div class="mx-4 my-2"><i class="bi-cash-coin text-orange-400 text-[30px]"></i><b> Options de paiement.</b></div>

<?php if (!empty($Montant)) {
?>		
		<div class="w-full bg-slate-600 p-1 rounded-lg">
<form method="post" action="./app/mode.payement.php?student_id=<?=$student_id?>&session_id=<?=$session_id?>" target="_blank" class="form-payement">
			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">

				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="a" value="A">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="a" class="text-bold choiceType">Type A</label></p>
					<a class="text-slate-300">Payement à 100 %</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-[10px] text-green-300 paie100"><?=number_format($Montant_sans_fraix_Generaux, 0, '', ' ')?> ar</a>
				</div>
				
			</label><hr class="border-1 border-slate-900">

			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">
				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="b" value="B">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="b" class="text-bold choiceType">Type B</label></p>
					<a class="text-slate-300">Tranché par 50% - 50%</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-[10px] text-green-300 paie50"><?=number_format(($Montant_sans_fraix_Generaux*50) /100, 0, '', ' ')?> ar</a><br>
					<a class="text-[10px] text-green-300 paie50"><?=number_format(($Montant_sans_fraix_Generaux*50) /100, 0, '', ' ')?> ar</a><br>
					
				</div>
				
			</label><hr class="border-1 border-slate-900">
<!-- 
			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">
				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="c" value="C">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="c" class="text-bold choiceType">Type C</label></p>
					<a class="text-slate-300">Tranché par 75% - 25%</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-[10px] text-green-300 paie75"><?=number_format(($Montant_sans_fraix_Generaux*75) /100, 0, '', ' ') ?> ar</a><br>
					<a class="text-[10px] text-green-300 paie25"><?=number_format(($Montant_sans_fraix_Generaux*25) /100, 0, '', ' ') ?> ar</a>
				</div>
				
			</label><hr class="border-1 border-slate-900">

			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">
				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="d" value="D">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="d" class="text-bold choiceType">Type D</label></p>
					<a class="text-slate-300">Tranché par 40% - 30% - 30%</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-[10px] text-green-300 paie40"><?=number_format(($Montant_sans_fraix_Generaux*40) /100, 0, '', ' ')?> ar</a><br>
					<a class="text-[10px] text-green-300 paie30"><?=number_format(($Montant_sans_fraix_Generaux*30) /100, 0, '', ' ')?> ar</a><br>
					<a class="text-[10px] text-green-300 paie30"><?=number_format(($Montant_sans_fraix_Generaux*30) /100, 0, '', ' ')?> ar</a>
				</div>
				
			</label><hr class="border-1 border-slate-900"> -->

			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">
				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="e" value="E">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="e" class="text-bold choiceType">Type E</label></p>
					<a class="text-slate-300">Tranché par 25% - 25% - 25% - 25%</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-[10px] text-green-300 paie25"><?=number_format(($Montant_sans_fraix_Generaux*25) /100, 0, '', ' ')?> ar</a><br>
					<a class="text-[10px] text-green-300 paie25"><?=number_format(($Montant_sans_fraix_Generaux*25) /100, 0, '', ' ')?> ar</a><br>
					<a class="text-[10px] text-green-300 paie25"><?=number_format(($Montant_sans_fraix_Generaux*25) /100, 0, '', ' ')?> ar</a><br>
					<a class="text-[10px] text-green-300 paie25"><?=number_format(($Montant_sans_fraix_Generaux*25) /100, 0, '', ' ')?> ar</a>
				</div>
				
			</label><hr class="border-1 border-slate-900">

			<div class="p-3">
				<!-- Ça doit être une éxécution sans chargement de la page, demandez à chatGPT -->
				<button id="submit-payement" type="submit" class="my-2 px-5 py-2 bg-slate-700 rounded-md toolInactive">Valider</button>
				<!-- ;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;; -->
			</div>
</form>
		</div>
<?php	
} else { ?>
		<div class="w-full bg-slate-700 p-4 rounded-lg text-center">
			<i class="bi bi-exclamation-circle text-orange-400 text-3xl"></i>
			<p class="mt-2 text-orange-300">Les options de paiement ne sont pas disponibles.</p>
			<p class="text-xs text-slate-400 mt-1">Vérifiez que les cours ont bien été ajoutés et que les frais financiers sont configurés pour cette session.</p>
		</div>
<?php } ?>		
	</div>

</div>

<script type="text/javascript">
	$(document).ready(function(){
		$('#paymentOnInscription').keyup(function() {

			var paymentOnInscription = $(this).val();
			var montant = $('#montant').text();
			var tranchable = montant - paymentOnInscription;

			$('#tranchable').text(tranchable);
			$('.paie100').text(tranchable);
			$('.paie75').text((tranchable*75)/100);
			$('.paie50').text((tranchable*50)/100);
			$('.paie40').text((tranchable*40)/100);
			$('.paie30').text((tranchable*30)/100);
			$('.paie25').text((tranchable*25)/100);
		});
	
		$('input[name="modePayement"]').change(function() {
	        if ($(this).is(':checked')) {
	           
	            $('#submit-payement').attr('class','my-2 px-5 py-2 bg-cyan-700 rounded-md');
        	}
    	});

		$('.form-payement').on('submit',function(submitP){
			submitP.preventDefault();
			
			var submitBtn = $(this).find('button[type="submit"]');
			var originalText = submitBtn.text();
			submitBtn.prop('disabled', true).text('Enregistrement...');

			var url	= './app/mode.payement.php?student_id=<?=$student_id?>&session_id=<?=$session_id?>';
			var data = $(this).serialize();

			$.post(url,data,function(response){
				Toast.success('Mode de paiement enregistré avec succès!');
				$('#submit-payement').attr('class','my-2 px-5 py-2 bg-slate-700 rounded-md toolInactive');
				$('#upStage').attr('class','px-5 py-2 bg-cyan-700 rounded-md');
				submitBtn.prop('disabled', false).text(originalText);
			}).fail(function(){
				Toast.error('Erreur lors de l\'enregistrement du mode de paiement');
				submitBtn.prop('disabled', false).text(originalText);
			});

		});
	});
</script>