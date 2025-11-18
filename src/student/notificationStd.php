

<!-- ///////////////////////////////////////////////////////// STUDENT TOOLBAR //////////////////////////////////////////////////////////////////////// -->

<!-- FOR BULLETIN -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifBulletin" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Afficher le Bulletin de</b>
			</div>
			<div class="p-2 text-black flex">
				
				<div class="w-6/12">
					<label>Niveau</label>
					<select name="level" id="levelBulletin">
						<option value="all">Tout</option>
						<?php
						for ($a=1; $a <= $level; $a++) {
						?>
						<option value="<?=$a?>"><?php 
						if($a<=3) {
							echo "NIVEAU Licence ".$a;
						}else{
							echo "NIVEAU Master ".($a-3);
						}
						?></option>
						<?php
						}
						?>
					</select>
				</div>

				<div class="w-6/12">
					<label>Semestre</label>	
					<select name="semester" id="semesterBulletin">
						<option value="all">les Deux</option>
						<option value="1">Semestre 1</option>
						<option value="2">Semestre 2</option>
					</select>
				</div>

			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifBulletin" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Bulletin&level=all&semester=all" id="showBulletin" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>	
				</center>
			</div>
		</div>

	</div>

<!-- FOR FICHE INSCRIPTION -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifFichInsc" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
<form method="post" action="./data.topdf.php?ptype=Fiche_inscription
	&id=<?=$id?>
	&student_id=<?=$student_id?>
	&student_nom=<?=$student_nom?>
	&student_prenom=<?=$student_prenom?>
	&etude_envisage=<?=$etude_envisage?>
	&level=<?=$level?>
	&student_tel=<?=$student_tel?>
	&image_student=<?=$image_student?>" target="_blank">
			<div class="p-2 text-black">
				<b>Sélection de session.</b>
			</div>
			<div class="p-2 text-black flex">

				<div class="w-6/12">
					<label>Semestre</label><br>
					<select name="semester" id="typeSemestre">
						<option <?php 
if (date('m')>7) {
	echo "selected";	
}else{

}
						 ?> value="1">Premier semestre</option>
						<option value="3">Semestre d'été</option>
						<option <?php 
if (date('m')>7) {
	
}else{
	echo "selected";
}
						 ?> value="2">Deuxième semestre</option>
						<option value="4">Semestre d'hiver</option>
					</select>
				</div>

				<div class="w-6/12">
					<label>Année scolaire</label><br>
					<select name="annee_scolaire" id="semesterTranscript">
						<?php
								$y = date('Y');
								for ($i=0; $i <= 3; $i++) { 
									
									if (date('m')>7) {
										$as = $y." - ".($y+1);	
									}else{
										$as = ($y-1)." - ".$y;
									}
									
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
						?>
					</select>
				</div>

			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifFichInsc" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<button id="ficheInscription" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</button>
				</center>	
			</div>
	</form>			
		</div>

	</div>

<!-- FOR TRANSCRIPT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTranscript" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Afficher le Transcript Semestriel de</b>
			</div>
			<div class="p-2 text-black flex">
				
				<div class="w-6/12">
					<label>Niveau</label>
					<select name="level" id="levelTranscript">
						<option value="all">Tout</option>
						<?php
						for ($a=1; $a <= $level; $a++) {
						?>
						<option value="<?=$a?>"><?php 
						if($a<=3) {
							echo "NIVEAU Licence ".$a;
						}else{
							echo "NIVEAU Master ".($a-3);
						}
						?></option>
						<?php
						}
						?>
					</select>
				</div>

				<div class="w-6/12">
					<label>Semestre</label>	
					<select name="semester" id="semesterTranscript">
						<option value="all">les Deux</option>
						<option value="1">Semestre 1</option>
						<option value="2">Semestre 2</option>
					</select>
				</div>

			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifTranscript" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Transcript&level=all&semester=all" id="showTranscript" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>
				</center>	
			</div>
		</div>

	</div>

<!-- FOR TRANSCRIPT SESSION -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTranscriptSS" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Afficher le Transcript par Session de</b>
			</div>
			<div class="p-2 text-black flex">
				
				<div class="w-6/12">
					<label>Niveau</label>
					<select name="level" id="levelTranscriptSS">
						<option value="all">Tout</option>
						<?php
						for ($a=1; $a <= $level; $a++) {
						?>
						<option value="<?=$a?>"><?php 
						if($a<=3) {
							echo "NIVEAU Licence ".$a;
						}else{
							echo "NIVEAU Master ".($a-3);
						}
						?></option>
						<?php
						}
						?>
					</select>
				</div>

				<div class="w-6/12">
					<label>Semestre</label>	
					<select name="semester" id="semesterTranscriptSS">
						<option value="all">les Deux</option>
						<option value="1">Semestre 1</option>
						<option value="2">Semestre 2</option>
					</select>
				</div>

			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifTranscriptSS" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="./data.topdf.php?student_id=<?=$student_id?>&annee_scolaire=<?=$annee_scolaire?>&std_niveau=<?=$level?>&ptype=TranscriptSS&level=all&semester=all" id="showTranscriptSS" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>
				</center>	
			</div>
		</div>

	</div>



<!-- FOR DIPLOME -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifDiplome" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Exporter ce Diplôme</b>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifDiplome" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<!-- <a href="#" id="showDiplome" onclick="printThisDiplome()" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Continuer</a> -->
				<button type="button" onclick="printThisDiplome()" class="bg-cyan-800 p-2 rounded-md text-white mx-1"><span class="bi-download"></span> Obtenir PDF</button>
				</center>
			</div>
		</div>

	</div>

<!-- SUPPRESION ETUDIANT-->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifSupprStd" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="./data.topdf.php?ptype=ticketMail" target="_blank">
			<div class="p-2 text-black">
				<b>Alert.</b>
			</div>
			<div class="p-2 text-black">
				<p>Voulez-vous vraiment supprimer cet étudiant ?</p>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifSupprStd" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="../app/.student/delStd.php?id=<?=$id?>&rg_id=<?=$rg_id?>" id="btnnotifSupprStd" class="bg-red-600 p-2 text-white rounded-md mx-1">Supprimer</a>
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->

<script>
	$(document).ready(function(){

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		$('#exportBulletin').click(function(){
			$('#notifBulletin').css({'display':'block'});
		});
		$('#exportTranscript').click(function(){
			$('#notifTranscript').css({'display':'block'});
		});
		$('#exportTranscriptSS').click(function(){
			$('#notifTranscriptSS').css({'display':'block'});
		});
		$('#exportDiplome').click(function(){
			$('#notifDiplome').css({'display':'block'});
		});
		$('#exportFichInsc').click(function(){
			$('#notifFichInsc').css({'display':'block'});
		});

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		$('#cancelnotifBulletin').click(function(){
			$('#notifBulletin').css({'display':'none'});
		});
		$('#cancelnotifTranscript').click(function(){
			$('#notifTranscript').css({'display':'none'});
		});
		$('#cancelnotifTranscriptSS').click(function(){
			$('#notifTranscriptSS').css({'display':'none'});
		});
		$('#cancelnotifDiplome').click(function(){
			$('#notifDiplome').css({'display':'none'});
		});
		$('#cancelnotifSupprStd').click(function(){
			$('#notifSupprStd').css({'display':'none'});
		});
		$('#cancelnotifFichInsc').click(function(){
			$('#notifFichInsc').css({'display':'none'});
		});

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		$('#levelBulletin').on('change',function(){
			level = $(this).val();
			semester = $('#semesterBulletin').val();
			$('#showBulletin').attr('href','./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Bulletin&level='+level+'&semester='+semester);
		});

		$('#semesterBulletin').on('change',function(){
			semester = $(this).val();
			level = $('#levelBulletin').val();
			$('#showBulletin').attr('href','./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Bulletin&level='+level+'&semester='+semester);
		});

		$('#levelTranscript').on('change',function(){
			level = $(this).val();
			semester = $('#semesterTranscript').val();
			$('#showTranscript').attr('href','./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Transcript&level='+level+'&semester='+semester);
		});

		$('#levelTranscriptSS').on('change',function(){
			level = $(this).val();
			semester = $('#semesterTranscriptSS').val();
			$('#showTranscriptSS').attr('href','./data.topdf.php?student_id=<?=$student_id?>&annee_scolaire=<?=$annee_scolaire?>&std_niveau=<?=$level?>&ptype=TranscriptSS&level='+level+'&semester='+semester);
		});

		$('#semesterTranscript').on('change',function(){
			semester = $(this).val();
			level = $('#levelTranscript').val();
			$('#showTranscript').attr('href','./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Transcript&level='+level+'&semester='+semester);
		});
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
		
	});	
</script>