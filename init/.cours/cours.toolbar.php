<div class="w-2/12 flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {echo "toolInactive";}?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-sort-alpha-up-alt text-2xl"></i><br>
						Trier par
				</center>
			
		</a>
				<ul class="dropdown-menu border <?=$bg_six_color?> text-black p-0 rounded-0 text-xs">

					<li><a href="?id=<?=$id;?>&page=transcriptSS"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Classement en session</p>
					</a></li>
					<li><a href="?id=<?=$id;?>&page=transcript"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Classement en semestre</p>
					</a></li>
					

				</ul>

		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {echo "toolInactive";}?>">
				<center>
				<i class="bi-funnel text-2xl"></i><br>
						Filter
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {echo "toolInactive";}?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-flag text-2xl"></i><br>
						Langues
				</center>
		</a>
				<ul class="dropdown-menu border <?=$bg_six_color?> text-black p-0 rounded-0 text-xs">

					<li><a href="?id=<?=$id;?>&page=<?=$_GET['page']?>&langue=FR"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Français</p>
					</a></li>
					<li><a href="?id=<?=$id;?>&page=<?=$_GET['page']?>&langue=ANG"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Anglais</p>
					</a></li>

				</ul>
	</div>
	<div class="w-2/12 flex px-1">
		<a href="?id=<?=$id?>&page=newStd" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {echo "toolInactive";}?>">
				<center>
				<i class="bi-person-add text-2xl text-cyan-500"></i><br>
						Ajout étudiant
				</center>
			
		</a>
		
		
		<a href="?id=<?=$id;?>&page=stdsupprim" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {echo "toolInactive";}?>">
				<center>
				<i class="bi-trash2 text-2xl text-red-500"></i><br>
						Etudiant supprimé
				</center>
			
		</a>
		<a href="#" id="<?php if (!empty($_GET['page']) AND $_GET['page'] == 'notes'){echo 'exportRemiseNote';}
							elseif(!empty($_GET['page']) AND $_GET['page'] == 'etudiants'){echo 'exportListStdInThisCours';}
						?>" 

			class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php if (isset($_GET['page']) and ($_GET['page'] == 'information') or empty($_GET['page'])) {echo "toolInactive";}?>">
		
				<center>
				<i class="bi-filetype-pdf text-2xl text-blue-400"></i><br>
				<?php if (isset($_GET['page']) and ($_GET['page'] == 'notes')) {echo "Remise de notes";}else{echo "Exporter";}?>
				</center>
			
		</a>
	</div>