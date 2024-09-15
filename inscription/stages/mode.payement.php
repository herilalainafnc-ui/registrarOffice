<?php

	require('../../data/backdb.php');

	$student_id = $_POST['student_id'];
	
	$recupsdt = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id ="'.$student_id.'" AND remove != 1 limit 1');

	$profil = $recupsdt->fetch();
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



	$findCoursFinance = $dtb->query('SELECT * FROM t_2024_cours_finance WHERE student_id="'.$student_id.'" AND remove != 1');
		$nbr = 0;
		$tCredit = 0;
		$tCout = 0;
		$tLab = 0;



 ?>
<div class="flex gap-2 w-full">
	
	<div class="w-7/12 p-2">
		
		<div class="mx-4 my-2"><i class="bi-file-text-fill text-green-300 text-[30px]"></i><b> Cours ajouté.</b></div>

		<div class="bg-white w-full p-2 text-black text-xs rounded-md">
			<b>Liste des cours</b>

			
			<table class="tbl mb-2 text-[10px]">
				<thead class="bg-slate-200">
					<tr>
						<th style="width: 70px">SIGLE</th>
						<th style="">TITRE DU COURS</th>
						<th style="width: 40px">CREDITS</th>
						<th style="width: 60px">CATÉGORIE</th>
						<th style="width: 60px">COÛT</th>
						<th style="width: 60px">LAB</th>
					</tr>	
				</thead>
				<tbody>
<?php 
	$findCoursFinance = $dtb->query('SELECT * FROM t_2024_cours_finance WHERE student_id="'.$student_id.'" AND remove != 1');
	$nbr = 0;
	$tCredit = 0;
	$tCout = 0;
	$tLab = 0;
	while($showCF = $findCoursFinance->fetch()) {
		$session_id = $showCF['session_id'];
 ?>
				<tr>
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
					<td class="text-right"><?=$showCF['cours_cout'].' ar'?></td>
					<td class="text-right"><?=$showCF['lab_cout'].' ar'?></td>
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
						<th colspan="2"><?=$nbr?> cours</th>
						<th><?=$tCredit?></th>
					</tr>
				</tfoot>
			</table>
<?php 

	if(!empty($session_id)) {

		$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'" AND remove != 1 LIMIT 1');

		$showFin = $findFinance->fetch();

	
	if (!empty($showFin['session_id']) OR $showFin['session_id'] != 0) {
 ?>

 			<b class="text-xs">Finance</b>
		<table class="tbl mb-2 text-xs">
			<thead class="bg-slate-200">
				<tr>
					<th>Frais Généraux</th>
					<?php if ($status == "Interne" OR $status == "Bungalow") { ?>
						<th>Logement</th>
						<th>Fond Dépôt</th>
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

					<th>Total Cours</th>
					<th>Total Lab</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?=$showFin['cout_fraix_generaux']?> ar</td>

					<?php if ($status == "Interne" OR $status == "Bungalow") { ?>
						<td><?=$showFin['cout_logement']?> ar</td>
						<td><?=$showFin['cout_fondDepot_dortoir']?> ar</td>
					<?php }?>

					<?php if ($abonment == 1) { ?>
						<td><?=$showFin['cout_abonment']?> ar</td>
					<?php }?>

					<?php if ($etude_envisage == "Théologie" AND $level == 1) { ?>
						<td><?=$showFin['cout_costume']?> ar</td>
					<?php }?>

					<?php if ($graduated == 1) { ?>
						<td><?=$showFin['cout_frais_graduation']?> ar</td>
					<?php } ?>

					<td><?=$showFin['cout_totalCours']?> ar</td>
					<td><?=$showFin['cout_totalLab']?> ar</td>
				</tr>
			</tbody>
		</table>

		<p class="text-xs mb-2">Montant : 
			<b class="bg-orange-300 py-1 px-2"><?=$Montant = $showFin['cout_fraix_generaux'] + 
							$showFin['cout_logement'] +
							$showFin['cout_fondDepot_dortoir'] +
							$showFin['cout_abonment'] +
							$showFin['cout_costume'] +
							$showFin['cout_frais_graduation'] +
							$showFin['cout_totalCours'] +
							$showFin['cout_totalLab']
				?> ar</b> <em>(À payer lors de l'inscription : <?=$showFin['cout_fraix_generaux']?> ar)</em>
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Tranchable : <b class="bg-blue-300 py-1 px-2"><?=$Montant_sans_fraix_Generaux =
							$Montant - $showFin['cout_fraix_generaux']
				?> ar</b>
			</p>

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
					<a class="text-slate-300"><?= $Montant_sans_fraix_Generaux ?> ar</a>
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
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *50) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *50) /100 ?> ar</a>
				</div>
				
			</label><hr class="border-1 border-slate-900">

			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">
				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="c" value="C">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="c" class="text-bold choiceType">Type C</label></p>
					<a class="text-slate-300">Tranché par 75% - 25%</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *75) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *25) /100 ?> ar</a>
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
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *40) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *30) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *30) /100 ?> ar</a>
				</div>
				
			</label><hr class="border-1 border-slate-900">

			<label class="m-2 flex hover:bg-slate-500 py-2 px-3 rounded-md">
				<div class="w-1/12 py-2">
					<input class="in" type="radio" name="modePayement" id="e" value="E">
				</div>
				<div class="w-7/12">
					<p class="text-lg"><label for="e" class="text-bold choiceType">Type E</label></p>
					<a class="text-slate-300">Tranché par 25% - 25% - 25% - 25%</a>	
				</div>
				<div class="w-4/12 text-right px-2">
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *25) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *25) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *25) /100 ?> ar</a><br>
					<a class="text-slate-300"><?=($Montant_sans_fraix_Generaux *25) /100 ?> ar</a>
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
} ?>		
	</div>

</div>

<script type="text/javascript">
	$(document).ready(function(){
	
		$('input[name="modePayement"]').change(function() {
	        if ($(this).is(':checked')) {
	           
	            $('#submit-payement').attr('class','my-2 px-5 py-2 bg-cyan-700 rounded-md');
        	}
    	});

		$('.form-payement').on('submit',function(submitP){
			submitP.preventDefault();

			var url	= './app/mode.payement.php?student_id=<?=$student_id?>&session_id=<?=$session_id?>';
			var data = $(this).serialize();

			$.post(url,data,function(response){
				alert("Mode de payement enregistrée !");
				$('#submit-payement').attr('class','my-2 px-5 py-2 bg-slate-700 rounded-md toolInactive');
				$('#upStage').attr('class','px-5 py-2 bg-cyan-700 rounded-md');
			});

		});
	});
</script>