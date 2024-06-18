<!-- ///////////////////////////////////////////////////////// GENERAL TOOLBAR //////////////////////////////////////////////////////////////////////// -->

<!-- FOR LIST -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListStd" style="backdrop-filter: blur(3px);">

		<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Exporter la liste d'étudiant.</b>
			</div>
			<div class="p-2">
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListStd" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<a href="#" id="showDiplome" target="_blank" class="bg-cyan-800 p-2 rounded-md text-white mx-1">Afficher</a>
				</center>
			</div>
		</div>

	</div>


<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->
<script type="text/javascript">
	$(document).ready(function(){
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListStd').click(function(){
			$('#notifListStd').css({'display':'block'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#cancelnotifListStd').click(function(){
			$('#notifListStd').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
	});

</script>