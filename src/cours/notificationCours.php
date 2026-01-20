<!-- FOR REMISE NOTE -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifRemiseNotes" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
		
			<div class="p-2 text-black">
				<b>Exporter la remise des notes.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2 text-black">
					      	<label for="yearRemiseNotes">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearRemiseNotes" id="yearRemiseNotes" class="input w-full text-black">
								<option></option>
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifRemiseNotes" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<a href="#" id="btnRemiseNotes" target="_blank" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive">Afficher</a>
				
				</center>
			</div>
	
		</div>

	</div>

<!-- FOR STUDENT LIST -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListStdInThisCours" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
		
			<div class="p-2 text-black">
				<b>Liste des étudiants dans ce cours.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2 text-black">
					      	<label for="yearListStdInThisCours">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearListStdInThisCours" id="yearListStdInThisCours" class="input w-full text-black">
								<option></option>
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListStdInThisCours" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<a href="#" id="btnListStdInThisCoursGenPDF" target="_blank" class="bg-slate-400 p-2 rounded-md ml-1 toolInactive"><span class="bi-download"></span><i class="text-[10px]"> (Texte)</i>.Pdf</a>
				<a href="#" id="btnListStdInThisCours" target="_blank" class="bg-slate-400 p-2 rounded-md ml-1 toolInactive">Afficher</a>
				
				</center>
			</div>
	
		</div>

	</div>


<!-- FOR SUPPRESSION Cours -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifCours-suppr" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			
			<div class="p-2 text-black">
				<b>Alert.</b>
			</div>
			<div class="p-2 text-black">
				<p>Tentative de suppression d'un cours. Voulez-vous continuer?</p>
				<div class="p-2">
					<input type="checkbox" name="definitive" id="definitive">
					<label for="definitive"> Effacer définitivement.</label>
				</div>
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifCours-suppr" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="../app/.cours/del.cours.php?id=<?=$id?>&rg_id=<?=$rg_id?>" id="btnCours-suppr" class="bg-red-600 p-2 text-white rounded-md mx-1">Supprimer</a>
				</center>
			</div>
			
		</div>

	</div>
<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->

<script>
	$(document).ready(function(){

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		$('#exportRemiseNotes').click(function(){
			$('#notifRemiseNotes').css({'display':'block'});
		});

		$('#yearRemiseNotes').on('change',function() {
			var yearRemiseNotes = $(this).val();
			
			$('#btnRemiseNotes').attr('class','bg-cyan-700 p-2 rounded-md mx-1');
			$('#btnRemiseNotes').attr('href','./data.topdf.php?cours_id=<?=$id?>&yearRemiseNotes='+yearRemiseNotes+'&ptype=RemiseNotes');

		});

		$('#cancelnotifRemiseNotes').click(function(){
			$('#notifRemiseNotes').css({'display':'none'});
		});
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
		
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		$('#exportListStdInThisCours').click(function(){
			$('#notifListStdInThisCours').css({'display':'block'});
		});

		$('#yearListStdInThisCours').on('change',function() {
			var yearForCours = $(this).val();
			
			$('#btnListStdInThisCours').attr('class','bg-cyan-700 p-2 rounded-md ml-1');
			$('#btnListStdInThisCours').attr('href','./data.topdf.php?cours_id=<?=$id?>&yearForCours='+yearForCours+'&ptype=ListStdInThisCours');

			$('#btnListStdInThisCoursGenPDF').attr('class','bg-red-700 p-2 rounded-md ml-1');
			$('#btnListStdInThisCoursGenPDF').attr('href','./gen.pdf.php?cours_id=<?=$id?>&yearForCours='+yearForCours+'&ptype=ListStdInThisCours');
		});

		$('#cancelnotifListStdInThisCours').click(function(){
			$('#notifListStdInThisCours').css({'display':'none'});
		});
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		$('#cours-suppr').click(function(){
			$('#notifCours-suppr').css({'display':'block'});
		});

		$('#definitive').on('change',function() {
			
			$.each($("#definitive:checked"),function() {

				$('#btnCours-suppr').attr('href','../app/.cours/del.cours.definitive.php?id=<?=$id?>');

			});

		});

		$('#cancelnotifCours-suppr').click(function(){
			$('#notifCours-suppr').css({'display':'none'});
		});
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
	});	
</script>