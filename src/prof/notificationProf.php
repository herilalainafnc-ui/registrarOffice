

<!-- ///////////////////////////////////////////////////////// STUDENT TOOLBAR //////////////////////////////////////////////////////////////////////// -->

<!-- FOR BULLETIN -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifBulletin" style="backdrop-filter: blur(3px);">

		<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
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
						<option value="<?=$a?>">Licence <?=$a?></option>
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
				<a href="#" id="cancelnotifBulletin" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<a href="./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Bulletin&level=all&semester=all" id="showBulletin" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>	
				</center>
			</div>
		</div>

	</div>


<!-- FOR TRANSCRIPT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTranscript" style="backdrop-filter: blur(3px);">

		<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
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
						<option value="<?=$a?>">Licence <?=$a?></option>
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
				<a href="#" id="cancelnotifTranscript" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<a href="./data.topdf.php?student_id=<?=$student_id?>&std_niveau=<?=$level?>&ptype=Transcript&level=all&semester=all" id="showTranscript" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>
				</center>	
			</div>
		</div>

	</div>

<!-- FOR TRANSCRIPT SESSION -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTranscriptSS" style="backdrop-filter: blur(3px);">

		<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
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
						<option value="<?=$a?>">Licence <?=$a?></option>
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
				<a href="#" id="cancelnotifTranscriptSS" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<a href="./data.topdf.php?student_id=<?=$student_id?>&annee_scolaire=<?=$annee_scolaire?>&std_niveau=<?=$level?>&ptype=TranscriptSS&level=all&semester=all" id="showTranscriptSS" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>
				</center>	
			</div>
		</div>

	</div>



<!-- FOR DIPLOME -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifDiplome" style="backdrop-filter: blur(3px);">

		<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Exporter ce Diplôme</b>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifDiplome" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<!-- <a href="#" id="showDiplome" onclick="printThisDiplome()" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Continuer</a> -->
				<button type="button" onclick="printThisDiplome()" class="bg-cyan-800 p-2 rounded-md text-white mx-1"><span class="bi-download"></span> Obtenir PDF</button>
				</center>
			</div>
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