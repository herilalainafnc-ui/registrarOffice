<?php 

$student_id = $_GET['student_id'];
$now = date('Y-m-d');
$printName = $student_id."-FICHE INSCRIPTION";

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');

$stdA = $searchStd->fetch();
 ?>

 <div class="mb-24" id="exportToExcel">

<center>
	<b class="text-2xl">Fiche d'inscription</b>
</center>
	
	<div class="border border-black flex text-xs px-1 py-1">
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
				<b>L<?=$stdA['annee_etude']?></b><br>
				<b><?=$stdA['student_email']?></b>
				<b>/ <?=$stdA['student_tel']?></b><br>
				<b><?=$stdA['student_adresse']?></b>
			</div>
		</div>
		<div class="w-2/12">
			<img src="../app/photosetudiants/<?=$stdA['image_student']?>">
		</div>
	</div>
		<b class="text-lg">Liste des cours</b>
	<div>
		
		<table class="tbl simpleTbl mb-2">
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">SIGLE</th>
					<th style="">TITRE DU COURS</th>
					<th style="width: 60px">CREDITS</th>
					<th style="width: 80px">CATÉGORIE</th>
					<th style="width: 80px">COÛT</th>
					<th style="width: 80px">LAB</th>
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
					<!-- <th></th>
					<th class="text-right"><?=$tCout.' ar'?></th>
					<th class="text-right"><?=$tLab.' ar'?></th> -->
				</tr>
			</tfoot>
		</table>

<?php 

	if(!empty($session_id)) {

		$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'" AND remove != 1 LIMIT 1');

		$showFin = $findFinance->fetch();

	
	if (!empty($showFin['session_id']) OR $showFin['session_id'] != 0) {
 ?>
 		<b class="text-lg">Finance</b>
		<table class="tbl simpleTbl mb-2">
			<thead class="bg-sky-200">
				<tr>
					<th>Frais Généraux</th>
					<th>Frais de logement</th>
					<th>Fond Dépôt Dortoir</th>
					<th>Frais Graduation</th>
					<th>Total Cours</th>
					<th>Total Laboratoire</th>
				</tr>
			</thead>
			<tbody>
				<tr class="text-bold text-right">
					<td><?=$showFin['cout_fraix_generaux']?> ar</td>
					<td><?=$showFin['cout_logement']?> ar</td>
					<td><?=$showFin['cout_fondDepot_dortoir']?> ar</td>
					<td><?=$showFin['cout_frais_graduation']?> ar</td>
					<td><?=$showFin['cout_totalCours']?> ar</td>
					<td><?=$showFin['cout_totalLab']?> ar</td>
				</tr>
			</tbody>
		</table>

		<p class="text-sm mb-2">Montant : 
			<b class="bg-orange-300 py-1 px-2"><?=$Montant = $showFin['cout_fraix_generaux'] + 
							$showFin['cout_logement'] +
							$showFin['cout_fondDepot_dortoir'] +
							$showFin['cout_frais_graduation'] +
							$showFin['cout_totalCours'] +
							$showFin['cout_totalLab']
				?> ar</b></p>
		<b class="text-lg">Mode de payement</b>
<?php 
$findPayement = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE student_id="'.$student_id.'" AND session_id="'.$session_id.'"');

$showPayement = $findPayement->fetch();

$modeP = $showPayement['mode_payement'];
?>

	<table class="tbl simpleTbl mb-2 w-5/12">
			<thead>
				<tr>
					<th colspan="3" class="text-center text-bold">TYPE <?=$modeP?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ($modeP == 'A') {  ?>
					<tr>
						<td>100 %</td>
						<td class="text-right"><?=$Montant?> ar</td>
						<td>Le jour d'inscription</td>
					</tr>
				<?php }elseif ($modeP == 'B') {  ?>
					<tr>
						<td>50 %</td>
						<td class="text-right"><?=($Montant*50) /100 ?> ar</td>
						<td>Le jour d'inscription</td>
					</tr>
					<tr>
						<td>50 %</td>
						<td class="text-right"><?=($Montant*50) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
				<?php }elseif ($modeP == 'C') {  ?>
					<tr>
						<td>75 %</td>
						<td class="text-right"><?=($Montant*75) /100 ?> ar</td>
						<td>Le jour d'inscription</td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=($Montant*25) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
				<?php }elseif ($modeP == 'D') {  ?>
					<tr>
						<td>40 %</td>
						<td class="text-right"><?=($Montant*40) /100 ?> ar</td>
						<td>Le jour d'inscription</td>
					</tr>
					<tr>
						<td>30 %</td>
						<td class="text-right"><?=($Montant*30) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
					<tr>
						<td>30 %</td>
						<td class="text-right"><?=($Montant*30) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
				<?php }elseif ($modeP == 'D') {  ?>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=($Montant*25) /100 ?> ar</td>
						<td>Le jour d'inscription</td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=($Montant*25) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=($Montant*25) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
					<tr>
						<td>25 %</td>
						<td class="text-right"><?=($Montant*25) /100 ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full"></td>
					</tr>
				<?php } ?>
			</tbody>
	</table>

<?php } } ?>
	</div>
<br>
<b class="text-sm">WORK EDUCATION CHOISI : <input type="text" placeholder="____________________" class="h-4 p-0 border-0 text-xs"></b><br><br>
	
<b class="text-sm">ENGAGEMENT</b><br>
Je sousigné(e) <?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?>
<p class="text-sm">m'engage, durant mon séjour à l'Université Adventiste Zurcher, à maintenir en tout temps une conduite et attitude exemplaire, et en harmonie avec
la philosophie chrétienne de cette institution qui m'acceuille; à contribuer positivement à la vie de l'université et à vivre en tout temps en conformité
avec ses principes et règlements. Le non-respect de cet engagement pourrait entrainer une sanction ou même un renvoi temporaire.</p>


<div>
		<br><br>
			<p class="text-sm">Sambaina, le <?php
				 echo date('d')." ";
				 $volana = date('m');
				 if($volana == '01'){echo('Janvier ');}
				 else if($volana == '02'){echo('Fevrier ');}
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
				 ?></p>	
		</div>
		<br><br>
		<p class="text-sm">Approuvée par :</p>
		<table class="text-sm" style="width: 100%">
			<tbody>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td style="height: 70px;"></td>
				</tr>
				<tr>
					<td style="border-top: 1px solid black; text-align: center;">Etudiant(e)</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Chef de département</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>financière</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>académique</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>aux affaire Estudiantine</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Registraire</td>
				</tr>
			</tbody>
		</table><br>
<div class="text-sm" style="border-top:1px solid black; width: 100%;">
	<table class="tbl">
		<tr>
			<td><em>Université Adventiste Zurcher</em></td>
		</tr>
	</table>
</div>

</div>