<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-6/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information' or $_GET['page'] == 'diplome') or empty($_GET['page'])) {
																								echo "toolInactive";
																							}?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-sort-alpha-up-alt text-2xl"></i><br>
						Trier par
				</center>
			
		</a>
				<ul class="dropdown-menu border bg-slate-300 text-black p-0 rounded-0 text-xs">

					<li><a href="?id=<?=$id;?>&page=transcriptSS"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Classement en session</p>
					</a></li>
					<li><a href="?id=<?=$id;?>&page=transcript"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Classement en semestre</p>
					</a></li>
					

				</ul>

		<!-- <a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information' or $_GET['page'] == 'diplome') or empty($_GET['page'])) {
																								echo "toolInactive";
																							}?>">
				<center>
				<i class="bi-funnel text-2xl"></i><br>
						Filter
				</center>
			
		</a> -->
		<a href="#" class="text-xs w-6/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {
																								echo "toolInactive";
																							}?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-flag text-2xl"></i><br>
						Langues
				</center>
		</a>
				<ul class="dropdown-menu border bg-slate-300 text-black p-0 rounded-0 text-xs">

					<li><a href="?id=<?=$id;?>&page=<?=$_GET['page']?>&langue=FR"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Français</p>
					</a></li>
					<li><a href="?id=<?=$id;?>&page=<?=$_GET['page']?>&langue=ANG"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Anglais</p>
					</a></li>

				</ul>
	</div>
	<div class="w-3/12 flex px-1">
		<a href="?id=<?=$id?>&page=newCours" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-folder-plus text-2xl"></i><br>
						Nouveau cours
				</center>
			
		</a>
		
		
		<a href="?id=<?=$id;?>&page=courssupprim" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-trash2 text-2xl"></i><br>
						Cours supprimé
				</center>
			
		</a>
		<a href="#" id="<?php if (!empty($_GET['page']) AND $_GET['page'] == 'bulletin'){echo 'exportBulletin';}
							elseif(!empty($_GET['page']) AND $_GET['page'] == 'transcript'){echo 'exportTranscript';}
							elseif(!empty($_GET['page']) AND $_GET['page'] == 'transcriptSS'){echo 'exportTranscriptSS';}
						?>" 

			class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information' or $_GET['page'] == 'newCours' or $_GET['page'] == 'diplome') or empty($_GET['page'])) {
																								echo "toolInactive";
																							}?>">
		
				<center>
				<i class="bi-filetype-pdf text-2xl"></i><br>
						Exporter
				</center>
			
		</a>
	</div>