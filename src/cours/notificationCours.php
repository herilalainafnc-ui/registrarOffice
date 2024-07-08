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