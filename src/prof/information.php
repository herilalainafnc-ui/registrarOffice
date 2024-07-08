
<form method="post" action="../app/.prof/updateProf.php?id=<?=$teacher_id?>&rg_id=<?=$rg_id?>" class="form-no-refrech">
<div class="w-full grid gap-2 grid-cols-2">

	<div class='m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos personnelle</b>	
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
				<label class="text-sm text-slate-400">Nom</label>
				<p class="showPers">-- <?=strtoupper($name)?></p><input id="firstPers"  class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="name" value="<?=$name?>"><br>

				<label class="text-sm text-slate-400">Date de naissance</label>
				<p class="showPers">-- <?=$profil['birthday']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="date" name="birthday" value="<?=$profil['birthday']?>"><br>

				<label class="text-sm text-slate-400">Genre</label>
				<p class="showPers">-- <?php if($profil['sex'] == '0'){echo 'Feminin';}else{echo 'Masculin';}?></p>
				<select class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="sex" default>
					<option class="<?=$bg_one_color?>" value="1" <?php if($profil['sex'] == '1'){echo 'selected';}?>>Masculin</option>
					<option class="<?=$bg_one_color?>" value="0" <?php if($profil['sex'] == '0'){echo 'selected';}?>>Feminin</option>
				</select><br>
                
                <label class="text-sm text-slate-400">Diplôme</label>
				<p class="showPers">-- <?=$profil['diplome']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="diplome" value="<?=$profil['diplome']?>"><br>

    		</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Prénom</label>
				<p class="showPers">-- <?=$lastName?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="lastName" value="<?=$lastName?>"><br>

				<label class="text-sm text-slate-400">Lieu de naissance</label>
				<p class="showPers">-- <?=$profil['lieuN']?></p><input class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="lieuN" value="<?=$profil['lieuN']?>"><br>
                <label class="text-sm text-slate-400">Réligion</label>
				<p class="showPers">-- <?=$profil['religion']?></p>
                <select id="firstAutr" class="editPers p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" name="religion">
					<option class="<?=$bg_one_color?>" <?php if($religion == 'Adventiste' OR $religion == 'Adventiste du Septieme-jour'){echo 'selected';}?>>Adventiste</option>
					<option class="<?=$bg_one_color?>" <?php if($religion != 'Adventiste' AND $religion != 'Adventiste du Septieme-jour'){echo 'selected';}?>>non Adventiste</option>
				</select><br>
			</div>
		</div>

	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<div class="w-full flex mb-4">
			<div class="w-8/12">
				<b>Infos du contact</b>	
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
				<label class="text-sm text-slate-400">Téléphone</label>
				<p class="showContact">-- <?=$profil['phone']?></p><input id="firstContact"  class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="phone" value="<?=$profil['phone']?>"><br>
				<label class="text-sm text-slate-400">Adresse mail</label>
				<p class="showContact">-- <?=$profil['email']?></p><input class="editContact p-0 bg-transparent h-5 text-sm border-0 w-11/12 hidden" type="text" name="email" value="<?=$profil['email']?>"><br>
				
			</div>
			<div class="w-6/12">
				<label class="text-sm text-slate-400">Ext</label>
				<p class="showContact">-- </p><br>
				
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

			var url = '../app/.prof/updateProf.php?id=<?=$teacher_id?>&rg_id=<?=$rg_id?>';
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