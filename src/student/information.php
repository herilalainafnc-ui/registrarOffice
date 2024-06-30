
<form method="post" action="../app/.student/updateStd.php?id=<?=$id?>&rg_id=<?=$rg_id?>" class="form-no-refrech">
<div class="w-full grid gap-2 grid-cols-2">

	<div class='m-0 p-2 bg-slate-700 hover:bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos personnelle</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitPers rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulPers rounded-md px-2 bg-slate-500 text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editPers" class="text-right"><i class="bi-pencil-square"></i></a>	
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Nom</label>
				<p class="showPers">-- <?=strtoupper($student_nom)?></p><input id="firstPers"  class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="student_nom" value="<?=$student_nom?>"><br>

				<label class="text-sm text-slate-400">Date de naissance</label>
				<p class="showPers">-- <?=$profil['dateNaissance']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="date" name="dateNaissance" value="<?=$profil['dateNaissance']?>"><br>

				<label class="text-sm text-slate-400">Genre</label>
				<p class="showPers">-- <?php if($profil['sex'] == '0'){echo 'Feminin';}else{echo 'Masculin';}?></p>
				<select class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="sex" default>
					<option class="bg-slate-800" value="1" <?php if($profil['sex'] == '1'){echo 'selected';}?>>Masculin</option>
					<option class="bg-slate-800" value="0" <?php if($profil['sex'] == '0'){echo 'selected';}?>>Feminin</option>
				</select><br>

				<label class="text-sm text-slate-400">CIN</label>
				<p class="showPers">-- <?=$profil['num_cin']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="num_cin" value="<?=$profil['num_cin']?>"><br>

				<label class="text-sm text-slate-400">CIN région</label>
				<p class="showPers">-- <?php $findRegi = $dtb->query('SELECT * FROM region WHERE id ="'.$profil['cin_region'].'"'); $showRegi = $findRegi->fetch(); if(!empty($showRegi)){echo $showRegi['region'];}?></p>
				<select class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="cin_region">
					<option class="bg-slate-800"></option>
<?php 
$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
while ($showR = $findRegion->fetch()) {
 ?>	
 					<option class="bg-slate-800" value="<?=$showR['id']?>" <?php if($profil['cin_region'] == $showR['id'] OR $profil['cin_region'] == $showR['region']){echo 'selected';}?>><?=$showR['region']?></option>
 <?php 
}
 ?>
					
				</select>
				<br>

				
				
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Prénom</label>
				<p class="showPers">-- <?=$student_prenom?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="student_prenom" value="<?=$student_prenom?>"><br>

				<label class="text-sm text-slate-400">Lieu de naissance</label>
				<p class="showPers">-- <?=$profil['lieuNaissance']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="lieuNaissance" value="<?=$profil['lieuNaissance']?>"><br>

				<label class="text-sm text-slate-400">Nationalité</label>
				<p class="showPers">-- <?=$profil['nationalite']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="nationalite" value="<?=$profil['nationalite']?>"><br>

				<label class="text-sm text-slate-400">Date de délivrance</label>
				<p class="showPers">-- <?=$profil['cin_date_delivre']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="date" name="cin_date_delivre" value="<?=$profil['cin_date_delivre']?>"><br>

				
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 bg-slate-700 hover:bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos du contact</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitContact rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulContact rounded-md px-2 bg-slate-500 text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editContact" class="text-right"><i class="bi-pencil-square"></i></a>
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Téléphone</label>
				<p class="showContact">-- <?=$profil['student_tel']?></p><input id="firstContact"  class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="student_tel" value="<?=$profil['student_tel']?>"><br>
				<label class="text-sm text-slate-400">Adresse mail</label>
				<p class="showContact">-- <?=$profil['student_email']?></p><input class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="student_email" value="<?=$profil['student_email']?>"><br>
				<label class="text-sm text-slate-400">Pays d'origine</label>
				<p class="showContact">-- <?=$profil['pays_origine']?></p><input class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="pays_origine" value="<?=$profil['pays_origine']?>"><br>
				<label class="text-sm text-slate-400">Région</label>
				<p class="showContact">-- <?php $findRegi = $dtb->query('SELECT * FROM region WHERE id ="'.$profil['student_region'].'"'); $showRegi = $findRegi->fetch(); if (!empty($showRegi)) {echo $showRegi['region'];}?></p>
				<select class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="student_region">
					<option class="bg-slate-800"></option>
<?php 
$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
while ($showR = $findRegion->fetch()) {
 ?>	
 					<option class="bg-slate-800" value="<?=$showR['id']?>" <?php if($profil['student_region'] == $showR['id'] OR $profil['student_region'] == $showR['region']){echo 'selected';}?>><?=$showR['region']?></option>
 <?php 
}
 ?>
					
				</select>
					<br>
				<label class="text-sm text-slate-400">Adresse actuel</label>
				<p class="showContact">-- <?=$profil['student_adresse']?></p><input class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="student_adresse" value="<?=$profil['student_adresse']?>"><br>
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Ext</label>
				<p class="showContact">-- </p><br>
				
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 bg-slate-700 hover:bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos d'étude</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitEtd rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulEtd rounded-md px-2 bg-slate-500 text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editEtd" class="text-right"><i class="bi-pencil-square"></i></a>
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Mention</label>
				<p class=""><i class="bi-lock-fill"></i> <?=$etude_envisage?></p><br>
				<label class="text-sm text-slate-400">Niveau</label>
				<p class="showEtd">--<?php if ($profil['annee_etude']<=3) {
						echo " Licence ".$profil['annee_etude'];
					}else{
						echo " Master ".$profil['annee_etude']-3;
					} ?>	
				</p>
				<select class="editEtd p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="annee_etude">
					<option class="bg-slate-800" value="1" <?php if($profil['annee_etude'] == 1){echo 'selected';}?>>Licence 1</option>
					<option class="bg-slate-800" value="2" <?php if($profil['annee_etude'] == 2){echo 'selected';}?>>Licence 2</option>
					<option class="bg-slate-800" value="3" <?php if($profil['annee_etude'] == 3){echo 'selected';}?>>Licence 3</option>
					<option class="bg-slate-800" value="4" <?php if($profil['annee_etude'] == 4){echo 'selected';}?>>Master 1</option>
					<option class="bg-slate-800" value="5" <?php if($profil['annee_etude'] == 5){echo 'selected';}?>>Master 2</option>
				</select>
				<br>
				<label class="text-sm text-slate-400">Matricule</label>
				<p class=""><i class="bi-lock-fill"></i> <?=$profil['student_id']?></p><br>
				<label class="text-sm text-slate-400">Status</label>
				<p class="showEtd">-- <?=$profil['status']?></p>
				<select class="editEtd p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="status">
					<option class="bg-slate-800" <?php if($profil['status'] == 'Externe'){echo 'selected';}?>>Externe</option>
					<option class="bg-slate-800" <?php if($profil['status'] == 'Interne'){echo 'selected';}?>>Interne</option>
				</select>
				<br>
				
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Parcours</label>
				<p class="showEtd">-- <?=$etude_option?></p>
				<select id="firstEtd" class="editEtd p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="etude_option">
					<option class="bg-slate-800" ></option>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description ="'.$etude_envisage.'"');

	$showSignMention = $findSignMention->fetch();
	$SignMention = $showSignMention['filiere_sigle'];


$findOption = $dtb->query('SELECT * FROM filiere_parcours WHERE departement ="'.$SignMention.'" ORDER BY description');
while ($showO = $findOption->fetch()) {
 ?>	
 					<option class="bg-slate-800" <?php if($profil['etude_option'] == $showO['description']){echo 'selected';}?>><?=$showO['description']?></option>
 <?php 
}
 ?>		
 				</select>
				<br>
				<label class="text-sm text-slate-400">Année universitaire</label>
				<p class="showEtd">-- <?=$profil['annee_scolaire']?></p>
				<select class="editEtd p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="annee_scolaire">
<?php
$y = date('Y');
for ($i=0; $i <= 8; $i++) { 
	
	$as = $y." - ".($y+1);
	?>
		<option class="bg-slate-800" <?php if($profil['annee_scolaire'] == $as){echo 'selected';}?>><?=$as?></option>
<?php
$y = $y - 1;
}
 ?>
				</select>
				<br>
				<label class="text-sm text-slate-400">Ancien étudiant</label>
				<p class="showEtd">-- <?php if($profil['new_student'] == 1){echo 'Non';}else{echo 'Oui';}?></p>
				<select class="editEtd p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="new_student">
					<option class="bg-slate-800" <?php if($profil['new_student'] == 1){echo 'selected';}?> value="0">Non</option>
					<option class="bg-slate-800" <?php if($profil['new_student'] == 0){echo 'selected';}?> value="1">Oui</option>
				</select>
				<br>
				
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 bg-slate-700 hover:bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos parentale</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitParent rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulParent rounded-md px-2 bg-slate-500 text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editParent" class="text-right"><i class="bi-pencil-square"></i></a>
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Nom du père</label>
				<p class="showParent">-- <?=$profil['father_name']?></p><input id="firstParent" class="editParent p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="father_name" value="<?=$profil['father_name']?>"><br>
				<label class="text-sm text-slate-400">Nom de la mère</label>
				<p class="showParent">-- <?=$profil['mother_name']?></p><input class="editParent p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="mother_name" value="<?=$profil['mother_name']?>"><br>
				<label class="text-sm text-slate-400">Téléphone</label>
				<p class="showParent">-- <?=$profil['parent_tel']?></p><input class="editParent p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="parent_tel" value="<?=$profil['parent_tel']?>"><br>
				<label class="text-sm text-slate-400">Adresse</label>
				<p class="showParent">-- <?=$profil['parent_adresse']?></p><input class="editParent p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="parent_adresse" value="<?=$profil['parent_adresse']?>"><br>
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Sa profession</label>
				<p class="showParent">-- <?=$profil['father_prof']?></p><input class="editParent p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="father_prof" value="<?=$profil['father_prof']?>"><br>
				<label class="text-sm text-slate-400">Sa profession</label>
				<p class="showParent">-- <?=$profil['mother_prof']?></p><input class="editParent p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="mother_prof" value="<?=$profil['mother_prof']?>"><br>
				
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 bg-slate-700 hover:bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos du sponsor</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitSpons rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulSpons rounded-md px-2 bg-slate-500 text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editSpons" class="text-right"><i class="bi-pencil-square"></i></a>
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Nom du sponsor</label>
				<p class="showSpons">-- <?=$profil['sponsor_nom']?></p><input id="firstSpons" class="editSpons p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="sponsor_nom" value="<?=$profil['sponsor_nom']?>"><br>
				<label class="text-sm text-slate-400">Téléphone</label>
				<p class="showSpons">-- <?=$profil['sponsor_tel']?></p><input class="editSpons p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="sponsor_tel" value="<?=$profil['sponsor_tel']?>"><br>
				<label class="text-sm text-slate-400">Adresse</label>
				<p class="showSpons">-- <?=$profil['sponsor_adresse']?></p><input class="editSpons p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="sponsor_adresse" value="<?=$profil['sponsor_adresse']?>"><br>
				
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Prénom</label>
				<p class="showSpons">-- <?=$profil['sponsor_prenom']?></p><input class="editSpons p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="sponsor_prenom" value="<?=$profil['sponsor_prenom']?>"><br>
				
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 bg-slate-700 hover:bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Autres...</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitAutr rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulAutr rounded-md px-2 bg-slate-500 text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editAutr" class="text-right"><i class="bi-pencil-square"></i></a>
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">État civil</label>
				<p class="showAutr">-- <?=$profil['situationf']?></p>
				<select id="firstAutr" class="editAutr p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="">
					<option class="bg-slate-800" <?php if($profil['situationf'] == 'Célibataire'){echo 'selected';}?>>Célibataire</option>
					<option class="bg-slate-800" <?php if($profil['situationf'] == 'Marié'){echo 'selected';}?>>Marié</option>
				</select>
					<br>
				<label class="text-sm text-slate-400">Nombre d'enfant</label>
				<p class="showAutr">-- <?=$profil['nb_enfant']?></p><input class="editAutr p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="nb_enfant" value="<?=$profil['nb_enfant']?>"><br>
				<label class="text-sm text-slate-400">Numéro visa</label>
				<p class="showAutr">-- <?=$profil['num_visa']?></p><input class="editAutr p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="num_visa" value="<?=$profil['num_visa']?>"><br>
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Nom du/de conjoint(e)</label>
				<p class="showAutr">-- <?=$profil['nom_conjoint']?></p><input class="editAutr p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="nom_conjoint" value="<?=$profil['nom_conjoint']?>"><br>
				<label class="text-sm text-slate-400">Réligion</label>
				<p class="showAutr">-- <?=$profil['religion']?></p>
				<select id="firstAutr" class="editAutr p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="religion">
					<option class="bg-slate-800" <?php if($profil['religion'] == 'Adventiste' OR $profil['religion'] == 'Adventiste du Septieme-jour'){echo 'selected';}?>>Adventiste</option>
					<option class="bg-slate-800" <?php if($profil['religion'] != 'Adventiste' AND $profil['religion'] != 'Adventiste du Septieme-jour'){echo 'selected';}?>>non Adventiste</option>
				</select><br>
			</div>
		</div>

	</div>



</div>
</form>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script type="text/javascript">
	$(document).ready(function(){

		$('.form-no-refrech').on('submit',function (e) {
			e.preventDefault();

			var url = '../app/.student/updateStd.php?id=<?=$id?>&rg_id=<?=$rg_id?>';
			var data = $(this).serialize();

			$.post(url,data,function(response){
				
				$('.submitPers').css({'display':'none'});
				$('.submitContact').css({'display':'none'});
				$('.submitEtd').css({'display':'none'});
				$('.submitParent').css({'display':'none'});
				$('.submitSpons').css({'display':'none'});
				$('.submitAutr').css({'display':'none'});

				$('.annulPers').css({'display':'none'});
				$('.annulContact').css({'display':'none'});
				$('.annulEtd').css({'display':'none'});
				$('.annulParent').css({'display':'none'});
				$('.annulSpons').css({'display':'none'});
				$('.annulAutr').css({'display':'none'});

				$('#editPers').css({'display':'block'});
				$('#editContact').css({'display':'block'});
				$('#editEtd').css({'display':'block'});
				$('#editParent').css({'display':'block'});
				$('#editSpons').css({'display':'block'});
				$('#editAutr').css({'display':'block'});
/*
				$('.editPers').css({'display':'none'});
				$('.showPers').css({'display':'block'});
				$('.editContact').css({'display':'none'});
				$('.showContact').css({'display':'block'});
				$('.editEtd').css({'display':'none'});
				$('.showEtd').css({'display':'block'});
				$('.editParent').css({'display':'none'});
				$('.showParent').css({'display':'block'});
				$('.editSpons').css({'display':'none'});
				$('.showSpons').css({'display':'block'});
				$('.editAutr').css({'display':'none'});
				$('.showAutr').css({'display':'block'});
				*/
			});
		});
		
		$('#editPers').click(function(){
			$(this).css({'display':'none'});
			$('.annulPers').css({'display':'block'});
			$('.editPers').css({'display':'block'});
			$('#firstPers').focus();
			$('.showPers').css({'display':'none'});
		});
		$('.annulPers').click(function(){
			$(this).css({'display':'none'});
			$('#editPers').css({'display':'block'});
			$('.editPers').css({'display':'none'});
			$('.showPers').css({'display':'block'});
			$('.submitPers').css({'display':'none'});
		});

		$('.editPers').click(function(){
			$('.submitPers').css({'display':'block'});
		});


		$('#editContact').click(function(){
			$(this).css({'display':'none'});
			$('.annulContact').css({'display':'block'});
			$('.editContact').css({'display':'block'});
			$('#firstContact').focus();
			$('.showContact').css({'display':'none'});
		});
		$('.annulContact').click(function(){
			$(this).css({'display':'none'});
			$('#editContact').css({'display':'block'});
			$('.editContact').css({'display':'none'});
			$('.showContact').css({'display':'block'});
			$('.submitContact').css({'display':'none'});
		});

		$('.editContact').click(function(){
			$('.submitContact').css({'display':'block'});
		});


		$('#editEtd').click(function(){
			$(this).css({'display':'none'});
			$('.annulEtd').css({'display':'block'});
			$('.editEtd').css({'display':'block'});
			$('#firstEtd').focus();
			$('.showEtd').css({'display':'none'});
		});
		$('.annulEtd').click(function(){
			$(this).css({'display':'none'});
			$('#editEtd').css({'display':'block'});
			$('.editEtd').css({'display':'none'});
			$('.showEtd').css({'display':'block'});
			$('.submitEtd').css({'display':'none'});
		});

		$('.editEtd').click(function(){
			$('.submitEtd').css({'display':'block'});
		});


		$('#editParent').click(function(){
			$(this).css({'display':'none'});
			$('.annulParent').css({'display':'block'});
			$('.editParent').css({'display':'block'});
			$('#firstParent').focus();
			$('.showParent').css({'display':'none'});
		});
		$('.annulParent').click(function(){
			$(this).css({'display':'none'});
			$('#editParent').css({'display':'block'});
			$('.editParent').css({'display':'none'});
			$('.showParent').css({'display':'block'});
			$('.submitParent').css({'display':'none'});
		});

		$('.editParent').click(function(){
			$('.submitParent').css({'display':'block'});
		});


		$('#editSpons').click(function(){
			$(this).css({'display':'none'});
			$('.annulSpons').css({'display':'block'});
			$('.editSpons').css({'display':'block'});
			$('#firstSpons').focus();
			$('.showSpons').css({'display':'none'});
		});
		$('.annulSpons').click(function(){
			$(this).css({'display':'none'});
			$('#editSpons').css({'display':'block'});
			$('.editSpons').css({'display':'none'});
			$('.showSpons').css({'display':'block'});
			$('.submitSpons').css({'display':'none'});
		});

		$('.editSpons').click(function(){
			$('.submitSpons').css({'display':'block'});
		});


		$('#editAutr').click(function(){
			$(this).css({'display':'none'});
			$('.annulAutr').css({'display':'block'});
			$('.editAutr').css({'display':'block'});
			$('#firstAutr').focus();
			$('.showAutr').css({'display':'none'});
		});
		$('.annulAutr').click(function(){
			$(this).css({'display':'none'});
			$('#editAutr').css({'display':'block'});
			$('.editAutr').css({'display':'none'});
			$('.showAutr').css({'display':'block'});
			$('.submitAutr').css({'display':'none'});
		});

		$('.editAutr').click(function(){
			$('.submitAutr').css({'display':'block'});
		});
	});
</script>