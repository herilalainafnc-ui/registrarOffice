<!-- FOR SUPPRESSION PROF -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifProf-suppr" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			
			<div class="p-2 text-black">
				<b>Alert.</b>
			</div>
			<div class="p-2 text-black">
				<p>Tentative de suppression d'un professeur. Voulez-vous continuer?</p>
				<div class="p-2">
					<input type="checkbox" name="definitive" id="definitive">
					<label for="definitive"> Effacer définitivement.</label>
				</div>
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifProf-suppr" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="<?=$app_base?>/app/.prof/del.prof?teacher_id=<?=$teacher_id?>&rg_id=<?=$rg_id?>" id="btnProf-suppr" class="bg-red-600 p-2 text-white rounded-md mx-1">Supprimer</a>
				</center>
			</div>
			
		</div>

	</div>

<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->

<script>
	$(document).ready(function(){

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#prof-suppr').click(function(){
			$('#notifProf-suppr').css({'display':'block'});
		});

		$('#definitive').on('change',function() {
			
			$.each($("#definitive:checked"),function() {

				$('#btnProf-suppr').attr('href','<?=$app_base?>/app/.prof/del.prof.definitive?teacher_id=<?=$teacher_id?>');

			});

		});

		$('#cancelnotifProf-suppr').click(function(){
			$('#notifProf-suppr').css({'display':'none'});
		});

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	

	});	
</script>