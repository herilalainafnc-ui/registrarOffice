
<form method="post" action="<?=$app_base?>/app/.cours/updateCours?id=<?=$id?>&rg_id=<?=$rg_id?>" class="form-no-refrech">
<div class="w-full grid gap-2 sm:grid-cols-1 lg:grid-cols-2 overflow-auto mt-3">

	<div class='m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos générale</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitPers rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulPers rounded-md px-2 <?=$bg_four_color?> text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editPers" class="text-right"><i class="bi-pencil-square"></i></a>	
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Sigle</label>
				<p class="showPers">-- <?=$sigle?></p><input id="firstPers"  class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="sigle" value="<?=$sigle?>"><br>

				<label class="text-sm text-slate-400">Mention</label>
				<p class="showPers">-- <?=$dep_desc?></p>
				<select name="dep_desc" class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden">
					<option class="<?=$bg_one_color?>" ></option>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_sigle ASC');
while ($showMent = $findSignMention->fetch()) {
	?>
					<option class="<?=$bg_one_color?>" <?php if($profil['dep_desc'] == $showMent['filiere_sigle']){echo 'selected';}?>><?=$showMent['filiere_sigle']?></option>
 <?php 
	}  					
 ?> 					
				</select><br>
				<label class="text-sm text-slate-400">Enseignant</label>
				<p class="showPers">-- 
<?php 
$findTeach = $dtb->query('SELECT * FROM teacher WHERE uid = "'.$profil['id_teacher'].'"');
$showTeach = $findTeach->fetch();
if(!empty($showTeach)) {
	echo strtoupper($showTeach['name'])." ".$showTeach['lastName'];
}
 ?>			
			</p><select name="id_teacher" class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden">
<?php 
$findTeach = $dtb->query('SELECT * FROM teacher ORDER BY name ASC');
while ($showTeach = $findTeach->fetch()) {
	?>
					<option class="<?=$bg_one_color?>" value="<?=$showTeach['uid']?>" <?php if($profil['id_teacher'] == $showTeach['uid']){echo 'selected';}?>><?=strtoupper($showTeach['name'])." ".$showTeach['lastName']?></option>
 <?php 
	}  					
 ?> 					
				</select><br>
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Titre</label>
				<p class="showPers">-- <?=$title?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="title" value="<?=$title?>"><br>

				<label class="text-sm text-slate-400">Titre en anglais</label>
				<p class="showPers">-- <?=$title_english?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="title_english" value="<?=$title_english?>"><br>

				<label class="text-sm text-slate-400">Parcours</label>
				
				<p class="showPers">-- <?php if($profil['parcours'] =='all') { 
					echo "TRONC COMUN";
				}else{
					$prcrs = $dtb->query('SELECT * FROM filiere_parcours WHERE shortcode = "'.$profil['parcours'].'"'); 
					$showprcrs = $prcrs->fetch();
					echo $showprcrs['description'];
				}?></p>
				<select class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="parcours">
				<?php 
					$prcrs = $dtb->query('SELECT * FROM filiere_parcours WHERE departement = "'.$profil['dep_desc'].'"'); 
				 ?>	
					<option class="<?=$bg_one_color?>" value="all">TRONC COMMUN</option>
					<?php
						while ($pr = $prcrs->fetch()) {
					?>
							<option class="<?=$bg_one_color?>" value="<?=$pr['shortcode'];?>" <?php if($profil['parcours'] == $pr['shortcode']){echo 'selected';}?>><?=$pr['description'];?></option>
					<?php		
					}
					?>	
				</select><br>
				
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Détail...</b>	
			</div>
			<div class="w-4/12 text-right">
				<div class="w-full flex gap-1 relative">
					<input type="submit" class="submitContact rounded-md px-2 bg-cyan-700 text-center hidden absolute right-[70px]" value="Modifier">
					<a href="#" class="annulContact rounded-md px-2 <?=$bg_four_color?> text-center hidden absolute right-0">Annuler</a>
				</div>
				<a href="#" id="editContact" class="text-right"><i class="bi-pencil-square"></i></a>
			</div>
		</div>

		<div class="w-full flex">
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Niveau</label>
				<p class="showContact">--<?php if ($profil['yearlevel']==0) {
											echo "Remise à niveau";
										}elseif($profil['yearlevel']>0 AND $profil['yearlevel']<=3) {
											echo "Licence ".$profil['yearlevel'];
										}else{
											echo "Master ".($profil['yearlevel']-3);
										} ?></p>
				<select class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="yearlevel">
					<option class="<?=$bg_one_color?>" value="1" <?php if($profil['yearlevel'] == 1){echo 'selected';}?>>Licence 1</option>
					<option class="<?=$bg_one_color?>" value="2" <?php if($profil['yearlevel'] == 2){echo 'selected';}?>>Licence 2</option>
					<option class="<?=$bg_one_color?>" value="3" <?php if($profil['yearlevel'] == 3){echo 'selected';}?>>Licence 3</option>
					<option class="<?=$bg_one_color?>" value="4" <?php if($profil['yearlevel'] == 4){echo 'selected';}?>>Master 1</option>
					<option class="<?=$bg_one_color?>" value="5" <?php if($profil['yearlevel'] == 5){echo 'selected';}?>>Master 2</option>
				</select><br>

				<label class="text-sm text-slate-400">Crédit</label>
				<p class="showContact">-- <?=$profil['nb_crd']?> Crédits</p>
				<select class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="nb_crd">
					<option class="<?=$bg_one_color?>" value="1" <?php if($profil['nb_crd'] == 1){echo 'selected';}?>>1 Crédits</option>
					<option class="<?=$bg_one_color?>" value="2" <?php if($profil['nb_crd'] == 2){echo 'selected';}?>>2 Crédits</option>
					<option class="<?=$bg_one_color?>" value="3" <?php if($profil['nb_crd'] == 3){echo 'selected';}?>>3 Crédits</option>
					<option class="<?=$bg_one_color?>" value="4" <?php if($profil['nb_crd'] == 4){echo 'selected';}?>>4 Crédits</option>
					<option class="<?=$bg_one_color?>" value="5" <?php if($profil['nb_crd'] == 5){echo 'selected';}?>>5 Crédits</option>
					<option class="<?=$bg_one_color?>" value="6" <?php if($profil['nb_crd'] == 6){echo 'selected';}?>>6 Crédits</option>
					<option class="<?=$bg_one_color?>" value="7" <?php if($profil['nb_crd'] == 7){echo 'selected';}?>>7 Crédits</option>
					<option class="<?=$bg_one_color?>" value="8" <?php if($profil['nb_crd'] == 8){echo 'selected';}?>>8 Crédits</option>
					<option class="<?=$bg_one_color?>" value="9" <?php if($profil['nb_crd'] == 9){echo 'selected';}?>>9 Crédits</option>
					<option class="<?=$bg_one_color?>" value="10" <?php if($profil['nb_crd'] == 10){echo 'selected';}?>>10 Crédits</option>
					<option class="<?=$bg_one_color?>" value="11" <?php if($profil['nb_crd'] == 11){echo 'selected';}?>>11 Crédits</option>
					<option class="<?=$bg_one_color?>" value="12" <?php if($profil['nb_crd'] == 12){echo 'selected';}?>>12 Crédits</option>
					<option class="<?=$bg_one_color?>" value="13" <?php if($profil['nb_crd'] == 13){echo 'selected';}?>>13 Crédits</option>
					<option class="<?=$bg_one_color?>" value="14" <?php if($profil['nb_crd'] == 14){echo 'selected';}?>>14 Crédits</option>
					<option class="<?=$bg_one_color?>" value="15" <?php if($profil['nb_crd'] == 15){echo 'selected';}?>>15 Crédits</option>
					<option class="<?=$bg_one_color?>" value="16" <?php if($profil['nb_crd'] == 16){echo 'selected';}?>>16 Crédits</option>
					<option class="<?=$bg_one_color?>" value="17" <?php if($profil['nb_crd'] == 17){echo 'selected';}?>>17 Crédits</option>
					<option class="<?=$bg_one_color?>" value="18" <?php if($profil['nb_crd'] == 18){echo 'selected';}?>>18 Crédits</option>
					<option class="<?=$bg_one_color?>" value="19" <?php if($profil['nb_crd'] == 19){echo 'selected';}?>>19 Crédits</option>
					<option class="<?=$bg_one_color?>" value="20" <?php if($profil['nb_crd'] == 20){echo 'selected';}?>>20 Crédits</option>
				</select><br>

				<label class="text-sm text-slate-400">Coût</label>
				
				<p class="showContact">-- <?=$profil['cout'];?> Ar</p>
				<input class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="cout" value="<?=$profil['cout'];?>"><br>
				<br>
				
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Semestre</label>
				<p class="showContact">-- Semestre <?=$profil['semester']?></p>
				<select class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="semester">
					<option class="<?=$bg_one_color?>" value="1" <?php if($profil['semester'] == 1){echo 'selected';}?>>Semestre 1</option>
					<option class="<?=$bg_one_color?>" value="2" <?php if($profil['semester'] == 2){echo 'selected';}?>>Semestre 2</option>
				</select><br>

				<label class="text-sm text-slate-400">Catégorie</label>
				<p class="showContact">-- <?php 
if ($profil['category'] == 0){
	echo "Général";
}elseif ($profil['category'] == 1) {
	echo "Majeur";
}elseif ($profil['category'] == -1 OR $profil['category'] == 2) {
	echo "Selective";
}elseif ($profil['category'] == 3) {
	echo "Additionnel";
}elseif ($profil['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
 ?></p>
				<select name="category" class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden">
					<option class="<?=$bg_one_color?>" ></option>
					<option class="<?=$bg_one_color?>" value="0" <?php if($profil['category'] == 0){echo 'selected';}?>>Général</option>
					<option class="<?=$bg_one_color?>" value="1" <?php if($profil['category'] == 1){echo 'selected';}?>>Mageur</option>
					<option class="<?=$bg_one_color?>" value="2" <?php if($profil['category'] == 2){echo 'selected';}?>>Selective</option>
					<option class="<?=$bg_one_color?>" value="3" <?php if($profil['category'] == 3){echo 'selected';}?>>Additionnel</option>
					
				</select><br>
				
				<label class="text-sm text-slate-400">Laboratoire</label>
				<p class="showContact">-- <?php
if($profil['lab'] == 0 OR $profil['lab'] == ''){echo 'Sans Laboratoire';}
elseif($profil['lab'] == 1){echo 'Lab 1';}
elseif($profil['lab'] == 2){echo 'Lab 2';}
elseif($profil['lab'] == 3){echo 'Lab 3';}
elseif($profil['lab'] == 4){echo 'Simulation Room';}
elseif($profil['lab'] == 5){echo 'Labo recherche';}

				?></p>
				<select class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="lab">
					<option class="<?=$bg_one_color?>" value="0" <?php if($profil['lab'] == 0){echo 'selected';}?>>Sans Laboratoire</option>
					<option class="<?=$bg_one_color?>" value="1" <?php if($profil['lab'] == 1){echo 'selected';}?>>Lab 1</option>
					<option class="<?=$bg_one_color?>" value="2" <?php if($profil['lab'] == 2){echo 'selected';}?>>Lab 2</option>
					<option class="<?=$bg_one_color?>" value="3" <?php if($profil['lab'] == 3){echo 'selected';}?>>Lab 3</option>
					<option class="<?=$bg_one_color?>" value="4" <?php if($profil['lab'] == 4){echo 'selected';}?>>Simulation Room</option>
					<option class="<?=$bg_one_color?>" value="5" <?php if($profil['lab'] == 5){echo 'selected';}?>>Labo recherche</option>
				</select><br>

			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
</div>
</form>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script type="text/javascript">
	$(document).ready(function(){

		$('.form-no-refrech').on('submit',function (e) {
			e.preventDefault();

			var url = '<?=$app_base?>/app/.cours/updateCours?id=<?=$id?>&rg_id=<?=$rg_id?>';
			var data = $(this).serialize();

			$.post(url,data,function(response){
				
				$('.submitPers').css({'display':'none'});
				$('.submitContact').css({'display':'none'});
				
				$('.annulPers').css({'display':'none'});
				$('.annulContact').css({'display':'none'});
				
				$('#editPers').css({'display':'block'});
				$('#editContact').css({'display':'block'});
				
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
	
	});
</script>