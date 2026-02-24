<?php 
require('../init/.forPrint/top.forPrint.php');



if (empty($_GET['session_id'])) {
	
	$semester = $_POST['semester'];
	$annee_scolaire = $_POST['annee_scolaire'];

	$findSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester = "'.$semester.'" AND session_year = "'.$annee_scolaire.'"');

	$showSession = $findSession->fetch();
	$session_id = $showSession['session_id'];

}else{

	$session_id = $_GET['session_id'];
}


$findSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_id = "'.$session_id.'"');

$showSession = $findSession->fetch();


$semester = $showSession['session_semester'];
$session_name = $showSession['session_name'];
$annee_scolaire = $showSession['session_year'];

$student_id = $_GET['student_id'];
//$session_id = $showSession['session_id'];

$now = date('Y-m-d');
$printName = $student_id."-FICHE INSCRIPTION";

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');

$stdA = $searchStd->fetch();
 ?>

 <div class="mb-24" id="exportToExcel">

<center>
	<b class="text-xl">Fiche d'inscription - </b><em class="text-sm"><?=$session_name." ".$annee_scolaire?></em>
</center>
	
	<div class="flex text-xs px-1 py-1" style="border: 1px solid #8e9bb2;">
		<div class="w-10/12" style="display: flex;">
			<div class="text-right w-4/12">
				<label>Matricule - </label><br>
				<label>Noms - </label><br>
				<label>Mention - </label><br>
				<label>Parcours - </label><br>
				<label>Niveau - </label><br>
				<label>Mail / </label>
				<label>Contact - </label><br>
				<label>Adresse - </label>
			</div>
			<div class="w-8/12 pl-1">
				<b><?=$student_id?></b><br>
				<b><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></b><br>
				<b><?=$stdA['etude_envisage']?></b><br>
				<b><?=$stdA['etude_option']?></b><br>
				<b><?php 
if ($stdA['annee_etude'] == 0) {
	echo ('Remise à niveau');
}elseif($stdA['annee_etude'] > 0 AND $stdA['annee_etude'] <= 3){
	echo "Licence ".$stdA['annee_etude'];
}/*elseif($stdA['annee_etude'] > 3){
	echo "Licence ".$stdA['annee_etude'];
}*/

				 ?></b><br>
				<b><?=$stdA['student_email']?></b>
				<b>/ <?=$stdA['student_tel']?></b><br>
				<b><?=$stdA['student_adresse']?></b>
			</div>
		</div>
		<div class="w-2/12">
			<img src="../app/photosetudiants/<?=$stdA['image_student']?>">
		</div>
	</div>
		<b class="text-md">Cours ajouté</b>
	<div>

<!-- 
AFFICHAGE DE LISTE DE COURS SANS FINANCEMENT

		<table class="tbl mb-2">
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">Sigle</th>
					<th style="">Titre du cours</th>
					<th style="width: 60px">Crédits</th>
					<th style="width: 80px">Catégorie</th>
					<th style="width: 80px; text-align: left; padding-right: 6px;">Labo</th>
				</tr>
			</thead>
			<tbody>
				
	<?php 
		$findCours = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id="'.$student_id.'" AND session_id="'.$session_id.'" AND remove != 1');
		$nbr = 0;
		$tCredit = 0;
		$tCout = 0;
		$tLab = 0;
		$n_lab = 0;
		$somm_lab = 0;
		while($showC = $findCours->fetch()) {
	 ?>
	 			<tr>
	 				<td><?=$showC['Sigle']?></td>
	 				<td><?=$showC['title_cours']?></td>
	 				<td><?=$showC['credit']?></td>
	 				<td><?php 
if ($showC['cours_category'] == 0){
	echo "Général";
}elseif ($showC['cours_category'] == 1) {
	echo "Majeur";
}elseif ($showC['cours_category'] == -1 OR $showC['cours_category'] == 2) {
	echo "Selective";
}elseif ($showC['cours_category'] == 3) {
	echo "Additionnel";
}elseif ($showC['cours_category'] == 5) {
	echo "``";
}else{
	echo "-";
}

	 				?></td>
	 				<td><?php 
	 if ($showC['lab']==0) {
	 	echo "";
	 	}else{
	 		echo "Labo". $showC['lab'];
	 	} 				
	 			?></td>
	 			</tr>
	<?php
		$nbr++;
		$tCredit =+ $tCredit + $showC['credit'];
		}
	 ?>
 				
			</tbody>
			<tfoot>
				<tr class="bg-slate-200">
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?=$tCredit?></th>
					<th></th>
					<th class="text-right"><?=$tCout.' ar'?></th>
					<th class="text-right"><?=$tLab.' ar'?></th> 
				</tr>
			</tfoot>
		</table>
 -->		
 		
<!-- $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$ -->
<!-- AFFICHAGE AVEC FINANCEMENT DE LISTE DE COURS -->
 		<table class="tbl mb-2">
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">Sigle</th>
					<th style="">Titre du cours</th>
					<th style="width: 60px">Crédits</th>
					<th style="width: 80px">Catégorie</th>
					<th style="width: 80px; text-align: right; padding-right: 6px;">coût</th>
					<th style="width: 80px; text-align: right; padding-right: 6px;">Labo</th>
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
					<td class="text-right"><?=number_format($showCF['cours_cout'], 0, '', ' ').' ar'?></td>
					<td class="text-right"><?php 
					
					if ($showCat['lab'] != 0) {						
					
						$n_lab =+ $n_lab + 1;

						if ($n_lab <= 2) {

							echo number_format($showCat['cout_lab'], 0, '', ' ');
							$somm_lab =+ $somm_lab + $showCat['cout_lab'];
					
						}else{
						
							echo "<a style='text-decoration: line-through; color: orange;'>".number_format($showCat['cout_lab'], 0, '', ' ')."</a>";
						
						}

					}


					echo ' ar';?></td>
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
					 <th></th>
					<th class="text-right"><?=number_format($tCout, 0, '', ' ').' ar'?></th>
					<th class="text-right"><?=number_format($somm_lab, 0, '', ' ').' ar'?></th>
				</tr>
			</tfoot>
		</table>

		
<!-- <table class="tbl">
<tr>
<?php
	$findCours = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id="'.$student_id.'" AND session_id="'.$session_id.'" AND remove != 1');
	while($showC = $findCours->fetch()) {
?>
	
		<td><?php echo $showC['Sigle'];?></td>
	
<?php 
	}
 ?>
 </tr>
</table> -->

<em class="text-xs">Résidence : <b><?=$stdA['status']?></b></em><br>
		<em class="text-xs">Cafétéria : <b><?php if ($stdA['abonment'] == 1) { echo "Abonnée"; }else{ echo "Non abonnée"; }?></b></em><br><br>

<!-- AFFICHAGE DES FRAIS A PAYER -->		
 
<?php 

	if(!empty($session_id)) {

		$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'" AND remove != 1 LIMIT 1');

		$showFin = $findFinance->fetch();

	
	if (!empty($showFin['session_id']) OR $showFin['session_id'] != 0) {
 ?>
 <div class="flex gap-5">
 	<div class="w-6/12">
 <em class="text-xs"><b>NB :</b> Le frais généraux doivent être payés le jour de l'inscription.</em>
 		<table class="w-full tbl mb-2">
 			<thead class="bg-sky-200">
 				<tr class="text-center">
 					<th colspan="2">Frais divers</th>
 				</tr>
 			</thead>
 			<tbody>
 				<tr>
 					<td>Frais Généraux</td>
 					<td class="text-right"><?=number_format($showFin['cout_fraix_generaux'], 0, '', ' ')?> ar</td>
 				</tr>
 				<tr>
 					<?php if ($stdA['etude_envisage'] == "Théologie" AND $stdA['annee_etude'] == 1) { ?>
					<td>Frais Costume</td>
					<?php }?>
 					
 					<?php if ($stdA['etude_envisage'] == "Théologie" AND $stdA['annee_etude'] == 1) { ?>
						<td class="text-right"><?=number_format($showFin['cout_costume'], 0, '', ' ')?> ar</td>
					<?php }?>
 				</tr>
 				<tr>
 					<td>
<?php 
	if ($stdA["etude_envisage"] == "Théologie") {
		echo "Colloque";
	}else{
		echo "Voyage d'étude";
	}

 ?>
					</td>
					<td class="text-right"><?=number_format($showFin['cout_voyage'], 0, '', ' ')?> ar</td>
 				</tr>
 				<!-- <tr>
 					<td>Fond de dépôt</td>
 					<td class="text-right"><?=number_format($showFin['cout_fondDepot_dortoir'], 0, '', ' ')?> ar</td>
 				</tr> -->
 			</tbody>
 		</table>

 		<table class="w-full tbl mb-2">
 			<thead class="bg-sky-200">
 				<tr>
 					<th colspan="2" class="text-center">Frais de scolarité</th>	
 				</tr>
 			</thead>
 			<tbody>
 				<tr>
 					<td>Total du cours</td>
 					<td class="text-right"><?=number_format($tCout, 0, '', ' ')?> ar</td>
 				</tr>
 				<tr>
 					<td>Total de Laboratoire</td>
 					<td class="text-right"><?=number_format($somm_lab, 0, '', ' ')?> ar</td>
 				</tr>
 				<tr>
 					<td>Frais du logement</td>
 					<td class="text-right"><?=number_format($showFin['cout_logement'], 0, '', ' ')?> ar</td>
 				</tr>
 				
 				<tr>
 					<td>Frais de caféteria</td>
 					<td class="text-right"><?=number_format($showFin['cout_abonment'], 0, '', ' ')?> ar</td>
 				</tr>
 				<tr>
 					<td>Frais de Graduation</td>
 					<td class="text-right">
<?php if ($stdA['graduated'] == 1) { ?>
					<?=number_format($showFin['cout_frais_graduation'], 0, '', ' ')?> ar
<?php }else{ echo '0 ar';} ?>
 					</td>
 				</tr>
 				
 			</tbody>
<?php
$Montant = $showFin['cout_fraix_generaux'] + 
							$showFin['cout_logement'] +
							$showFin['cout_fondDepot_dortoir'] +
							$showFin['cout_abonment'] +
							$showFin['cout_frais_graduation'] +
							$tCout +
							$somm_lab;
$pay_inscription = $showFin['cout_fraix_generaux'] + $showFin['cout_fondDepot_dortoir'];
$findPayement = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE student_id="'.$student_id.'" AND session_id="'.$session_id.'"');

$showPayement = $findPayement->fetch();

$modeP = $showPayement['mode_payement'];
?>	
 			<tfoot>
 				<tr>
 					<th>Sous total frais de scolarité</th>
 					<th class="text-right"><?=number_format($Montant_sans_fraix_Generaux = $Montant - $pay_inscription, 0, '', ' ')?> ar</th>
 				</tr>
 			</tfoot>
 		</table>
 	</div>
<div class="w-6/12">
<?php 
	
	if ($stdA['sponsor_nom'] != "") {
?>	
	<div>
		<b class="text-xs">Boursié(e) par : <?=$stdA['sponsor_nom']." ".$stdA['sponsor_prenom']?></b>
	</div>
<?php
	}
 ?>
 <!-- __________________________________________________________________ -->

<!-- Mode de paiement -->
<div>
	<b class="text-md">Mode de paiement choisi :</b>

	<table class="tbl mb-2">
			<thead>
				<tr>
					<th colspan="3" class="text-center text-bold">
<?=number_format($Montant_sans_fraix_Generaux = $Montant - $pay_inscription, 0, '', ' ')?> ar
					</th>
				</tr>
				<tr>
					<th colspan="3" class="text-center text-bold text-[20px]">Tranche <?=$modeP?></th>
				</tr>

			</thead>
			<thead>
				<tr>
					<th>Pourcentage</th>
					<th>Montant</th>
					<th>Date de paiement</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($modeP == 'A') {  ?>
					<tr>
						<td>100 %</td>
						<td class="text-right"><?=number_format($Montant_sans_fraix_Generaux, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="03 octobre 2025"></td>
					</tr>
				<?php }elseif ($modeP == 'B') {  ?>
					<tr>
						<td>50 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *50) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="03 octobre 2025"></td>
					</tr>
					<tr>
						<td>50 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *50) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="30 janvier 2026"></td>
					</tr>
				<?php }elseif ($modeP == 'C') {  ?>
					<tr>
						<td>75 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *75) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="03 octobre 2025"></td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *25) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="30 janvier 2026"></td>
					</tr>
				<?php }elseif ($modeP == 'D') {  ?>
					<tr>
						<td>40 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *40) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="03 octobre 2025"></td>
					</tr>
					<tr>
						<td>30 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *30) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="19 decembre 2025"></td>
					</tr>
					<tr>
						<td>30 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *30) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="30 janvier 2026"></td>
					</tr>
				<?php }elseif ($modeP == 'E') {  ?>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *25) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="24 octobre 2025"></td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *25) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="28 novembre 2025"></td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *25) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="19 decembre 2025"></td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux *25) /100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="30 janvier 2026"></td>
					</tr>
				<?php } ?>
			</tbody>
	</table>
</div>

<b class="text-[10px]">Total Général (frais divers + frais de scolarité ) : <?=number_format($showFin['cout_fraix_generaux']+$showFin['cout_costume']+$showFin['cout_voyage']+$Montant_sans_fraix_Generaux, 0, '', ' ')?> ar</b>
</div>

</div>
	
<div class="w-full gap-3 flex">



<?php  } } ?>

<!-- _______________________________________________________________________________________ -->


<div class="w-full pb-3">
	<hr>
	<div class="flex gap-5 pt-3">
		<b class="text-sm w-3/12">Work education :</b>
		<input type="text" class="p-0 text-mg border-1 w-9/12">
	</div>
	<b class="text-sm">ENGAGEMENT</b><br>

<p class="text-[10px]" style="">Je sousigné(e) <?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?><br>m'engage, durant mon séjour à l'Université Adventiste Zurcher, à maintenir en tout temps une conduite et attitude exemplaire, et en harmonie avec
la philosophie chrétienne de cette institution qui m'acceuille; à contribuer positivement à la vie de l'université et à vivre en tout temps en conformité
avec ses principes et règlements. Le non-respect de cet engagement pourrait entrainer une sanction ou même un renvoi temporaire.</p>
</div>
</div>
	</div>
	

<hr>
<div>
			<em class="text-[10px] mt-1">Sambaina, le <?php
				 echo date('d')." ";
				 $volana = date('m');
				 if($volana == '01'){echo('Janvier ');}
				 else if($volana == '02'){echo('Février ');}
				 else if($volana == '03'){echo('Mars ');}
				 else if($volana == '04'){echo('Avril ');}
				 else if($volana == '05'){echo('Mai ');}
				 else if($volana == '06'){echo('Juin ');}
				 else if($volana == '07'){echo('Juillet ');}
				 else if($volana == '08'){echo('Août ');}
				 else if($volana == '09'){echo('Septembre ');}
				 else if($volana == '10'){echo('Octobre ');}
				 else if($volana == '11'){echo('Novembre ');}
				 else if($volana == '12'){echo('Decembre ');}
				 echo date('Y')
				 ?></em>
				 <b class="text-[10px]"> - Approuvée par :</b>
		</div>
		
		<table class="text-sm text-[10px]" style="width: 100%; page-break-inside: avoid;">
			<tbody>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<!-- <td></td> -->
					<td></td>
					<td></td>
					<td style="height: 30px;"></td>
				</tr>
				<tr style="font-size : 10px; line-height: 10px;">
					<td style="border-top: 1px solid black; text-align: center;">Étudiant(e)</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Chef de mention</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur aux affaires financières <br>/ Controleur</td>
					<td style="width: 20px"></td>
					<!-- <td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>académique</td>
					<td style="width: 20px"></td> -->
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur aux Affaires Estudiantines</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Registraire</td>
				</tr>
			</tbody>
		</table>
<div class="text-[10px] mt-1" style="border-top:1px solid black; width: 100%;">
	<em class="text-[10px]">Université Adventiste Zurcher</em>
</div>

</div>