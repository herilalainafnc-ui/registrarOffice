<!-- ////////////////////// GENERAL TOOLBAR //////////////////////////////////////////// -->

<!-- FOR LIST STUDENT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListStd" style="backdrop-filter: blur(3px);">
		
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl text-xs">
			<div class="p-2 text-black">
				<b>Exporter la liste d'étudiant.</b>
			</div>
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=listeStd" target="_blank">
			<div class="p-2">
					<b class="toolInactive">Types d'exoprtation.</b>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="types">Types</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="types" name="exportation" class="input w-full">
				      				<option value="general">Liste d'étudiant générale</option>
				      				<option value="internat">Liste d'étudiant interne</option>
				      				<option value="abnment">Liste d'étudiant abonnée</option>
				      				<option value="adventiste">Liste d'étudiant Adventiste</option>
				      				<option value="non_adventiste">Liste d'étudiant Non-Adventiste</option>

				      			</select>	
				      		</div>
				      	</div>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="signature" type="checkbox" name="new_student">
				      			<label for="signature"> Les nouveaux seulement.</label>
				      		</div>
				      	</div>
				      	<hr>

	      			<b class="toolInactive">Listes.</b>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="types">Mention</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="types" name="types" class="input w-full">
				      				<option value="TOUT">Tout</option>
			<?php 
						$voir = $dtb->query("SELECT * FROM filiere");
						while ($affiche = $voir->fetch()) {?>
											
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

			<?php	
				}
			 ?>	
				      			</select>	
				      		</div>
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="anneescolaire">Année</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="anneescolaire" name="anneescolaire" class="input w-full">
				      				 <?php
										$y = date('Y');
										for ($i=0; $i <= 3; $i++) { 
											
											if (date('m')>7) {
												$annee = $y." - ".($y+1);	
											}else{
												$annee = ($y-1)." - ".$y;
											}
											
										?>
										<option value="<?=$annee?>"><?=$annee?></option>
										<?php
										$y = $y - 1;
										}
									?>
				      			</select>	
				      		</div>
				      	</div>

				      	<hr>
				      	
				      	<b class="toolInactive">Cours.</b>
				    
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="cours" type="checkbox" name="cours">
				      			<label for="cours">Afficher la liste des cours à chaque étudiant.</label>	
				      		</div>	
				      	</div>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="notes" type="checkbox" name="notes">
				      			<label for="notes">Y-compris les nôtes.</label>	
				      		</div>	
				      	</div>
				      	
				      	<hr>
				      	
				      	<b class="toolInactive">Niveau/Semestre.</b>
				      	
				      	<div class="flex mb-3">

				      		<div class="w-3/12 text-right pr-2">
				      			<label for="annee">Niveau : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="annee" name="annee_etude" class="input w-full">
				      				<option value="tout">Tout</option>
				      				<option value="1">Licence 1</option>
				      				<option value="2">Licence 2</option>
				      				<option value="3">Licence 3</option>
				      				<option value="4">Master 1</option>
				      				<option value="5">Master 2</option>
				      				<option value="10">Classe spéciale</option>
				      			</select>	
				      		</div>
				      	</div>
				      	
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2"></div>
				      		<div class="w-9/12">
				      			<input id="master" type="checkbox" name="master">
				      			<label for="master">Avec MASTER</label>	
				      		</div>
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="semestre">Semestre : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="semestre" name="semestre" class="input w-full">
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
				      	</div>
				      	<hr>

				      	<b class="toolInactive">Colonne.</b>
				      	
				      	<div class="flex mb-3">
				      		
				      		<div class="w-6/12 p-2">
				      			<input id="signature" type="checkbox" name="signature">
				      			<label for="signature">Colonne de signature.</label>	
				      		</div>
				      		<div class="w-6/12 p-2">
				      			<input id="remarque" type="checkbox" name="remarque">
				      			<label for="remarque">Colonne de remarque.</label>	
				      		</div>	

				      	</div>
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListStd" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnStudentList" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR LIST COURS -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListCours" style="backdrop-filter: blur(3px);">
		
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Exporter la liste de cours.</b>
			</div>
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=listeCours" target="_blank">
			<div class="p-2">
	      			<b class="toolInactive">Listes.</b>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="types">Mention</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="types" name="types" class="input w-full">
				      				<option value="TOUT">Tout</option>
			<?php 
						$voir = $dtb->query("SELECT * FROM filiere");
						while ($affiche = $voir->fetch()) {?>
											
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

			<?php	
				}
			 ?>	
				      			</select>	
				      		</div>
				      	</div>

				      	<hr>
				      	
				      	<b class="toolInactive">Niveau/Semestre.</b>
				      	
				      	<div class="flex mb-3">

				      		<div class="w-3/12 text-right pr-2">
				      			<label for="annee">Niveau : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="annee" name="yearlevel" class="input w-full">
				      				<option value="tout">Tout</option>
				      				<option value="1">Licence 1</option>
				      				<option value="2">Licence 2</option>
				      				<option value="3">Licence 3</option>
				      				<option value="4">Master 1</option>
				      				<option value="5">Master 2</option>
				      			</select>	
				      		</div>
				      	</div>
				      	
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2"></div>
				      		<div class="w-9/12">
				      			<input id="master" type="checkbox" name="master">
				      			<label for="master">Avec MASTER</label>	
				      		</div>
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="semestre">Semestre : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="semestre" name="semester" class="input w-full">
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
				      	</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListCours" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnCoursList" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR FOP LIST -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListFOP" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf_paysage?ptype=foplist" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la requête FOP.</b>
			</div>
			<div class="p-2">
				<div class="w-all">
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearFOP">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearFOP" id="yearFOP" class="input w-full">
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
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListFOP" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnFOP" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR MESUPRES -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifMesupres" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf_paysage?ptype=mesupres" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la requête MESUPRES.</b>
			</div>
			<div class="p-2">
				<div class="w-all">
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearMesupres">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearMesupres" id="yearMesupres" class="input w-full">
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
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifMesupres" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnMesupres" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- MAIL CSV -->

	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListCSV" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			
			<form method="post" action="<?=$app_base?>/src/genPDF/gen.mail_csv" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les adresses mail (CSV).</b>
			</div>
			<div class="p-2">
				<div class="w-all">
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_description'];?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearListCSV">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearListCSV" id="yearListCSV" class="input w-full">
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

				     <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="typesCSV">Types de donnée</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="typesCSV" id="typesCSV" class="input w-full">
								<option value="csv">CSV (séparé par de virgule)</option>
								<option value="table">Table</option>
							</select>
					    </div>
				    </div>
					
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListCSV" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnListCSV" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>

		</div>

	</div>

<!-- FOR STATISTIC -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifStatistic" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=Statistique" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la statistique générale.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearStatistic">Semestre</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="semestre" id="semstre" class="input w-full">
								<option>Semestre d'été</option>
						<option <?php if (date('m')>=7) {echo "selected";} ?>>Premier semestre</option>
						<option>Semestre d'hiver</option>
						<option <?php if (date('m')<7) {echo "selected";} ?>>Deuxième semestre</option>
							</select>
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearStatistic">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearStatistic" id="yearStatistic" class="input w-full">
								 <?php
								$mois = date('m');
								if (intval($mois) < 7){
									$z = date('Y');
								}else{
									
									$z = date('Y') + 1;
								}
								$yn = 1;
				        			for ($i=1; $i < 10; $i++) { 
				        			?>
				        				<option><?=($z-1)." - ".$z?></option>
				        			<?php
				        			$z = $z-$yn;
				        		}
			        		 	?>
							</select>
					    </div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifStatistic" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnStatistic" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR TICKET MAIL -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTicketMail" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=ticketMail" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter le Ticket par Email.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>
					    </div>
					    
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
					      	<label for="level">Niveau</label>
					    </div>
					    <div class="w-9/12">
			      			<select id="level" name="level" class="input w-full">
			      				<option value="TOUT">Tout</option>
								<option value="1">Licence 1</option>
								<option value="2">Licence 2</option>
								<option value="3">Licence 3</option>
								<option value="4">Master 1</option>
								<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearTicket" id="yearTicket" class="input w-full">
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
				<a href="#" id="cancelnotifTicketMail" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnTicketMail" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- FOR FINANCE -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifFinance" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf_paysage?ptype=Finance" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter le financement lors de l'inscrition de...</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearFinance">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearFinance" id="yearFinance" class="input w-full">
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
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="semestreFinance">Semestre</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="semestreFinance" id="semestreFinance" class="input w-full">
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
				</div>
				<hr><br>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>
					    </div>
					    
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
					      	<label for="level">Niveau</label>
					    </div>
					    <div class="w-9/12">
			      			<select id="level" name="level" class="input w-full">
			      				<option value="TOUT">Tout</option>
								<option value="1">Licence 1</option>
								<option value="2">Licence 2</option>
								<option value="3">Licence 3</option>
								<option value="4">Master 1</option>
								<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<!-- <hr><br>
				<div class="flex mb-3">
		      		<div class="w-3/12 text-right pr-2">
		      			
		      		</div>
		      		<div class="w-9/12">
		      			<input id="signature" type="checkbox" name="new_student">
		      			<label for="signature"> Les nouveaux seulement.</label>
		      		</div>
		      	</div> -->
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifFinance" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnFinance" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- FOR WORKED SLIP -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifworkedSlip" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=workedSlip" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les (worked slip).</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearworkedSlip" class="input w-full">
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
				<hr><br>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="yearTicket">Début d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input id="yearworkedSlip" type="date" name="date_begin" class="input w-full">
				    </div>
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="yearTicket">Fin d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input type="date" name="date_end" class="input w-full">
				    </div>
				</div>
				<hr><br>
				<b class="text-slate-500">Champs à afficher sur le slip :</b>
				<div class="flex mb-3 flex-wrap">
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_matricule" id="show_matricule" checked>
						<label for="show_matricule">Matricule</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_nom" id="show_nom" checked>
						<label for="show_nom">Nom et prénoms</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_mention" id="show_mention" checked>
						<label for="show_mention">Mention</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_niveau" id="show_niveau" checked>
						<label for="show_niveau">Niveau</label>
					</div>
				</div>
				<hr>
				<b class="text-slate-500">Exporter pour un seul étudiant :</b>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="single_student_id">Matricule</label>
					</div>
					<div class="w-9/12">
						<input type="text" name="single_student_id" id="single_student_id" class="input w-full" placeholder="Laisser vide pour exporter tout">
					</div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifworkedSlip" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnworkedSlip" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR FILES CHECKING SLIP -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notiffilesSlip" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=filesSlip" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les (File's Checking Slip).</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="typesFiles">Mention</label>
					    </div>
					    <div class="w-9/12">
							<select id="typesFiles" name="types" class="input w-full">
					  			<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
				  			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearFilesSlip">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearFilesSlip" id="yearFilesSlip" class="input w-full">
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
				<hr><br>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="date_begin_files">Début d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input id="date_begin_files" type="date" name="date_begin" class="input w-full">
				    </div>
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="date_end_files">Fin d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input id="date_end_files" type="date" name="date_end" class="input w-full">
				    </div>
				</div>
				<hr><br>
				<b class="text-slate-500">Champs à afficher sur le slip :</b>
				<div class="flex mb-3 flex-wrap">
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_matricule" id="show_matricule_files" checked>
						<label for="show_matricule_files">Matricule</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_nom" id="show_nom_files" checked>
						<label for="show_nom_files">Nom et prénoms</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_mention" id="show_mention_files" checked>
						<label for="show_mention_files">Mention</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_niveau" id="show_niveau_files" checked>
						<label for="show_niveau_files">Niveau</label>
					</div>
				</div>
				<hr>
				<b class="text-slate-500">Exporter pour un seul étudiant :</b>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="single_student_id_files">Matricule</label>
					</div>
					<div class="w-9/12">
						<input type="text" name="single_student_id" id="single_student_id_files" class="input w-full" placeholder="Laisser vide pour exporter tout">
					</div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotiffilesSlip" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnfilesSlip" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR WORKED -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifworked" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=worked" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la liste (worked) de manière groupée.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="niveau">Niveau</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="niveau" name="niveau" class="input w-full">
			    				<option value="1">Licence 1</option>
			    				<option value="2">Licence 2</option>
			    				<option value="3">Licence 3</option>
			    				<option value="4">Master 1</option>
			    				<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearworked" id="yearworked" class="input w-full">
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
				<a href="#" id="cancelnotifworked" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnworked" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR BADGE EN GROUPE -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifBadgeGr" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=BadgeGr" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les badges en groupe.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<!-- <option value="TOUT">Tout</option> -->
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle']?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="niveau">Niveau</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="niveau" name="level" class="input w-full">
			    				<option value="TOUT">Tout</option>
			    				<option value="1" selected>Licence 1</option>
			    				<option value="2">Licence 2</option>
			    				<option value="3">Licence 3</option>
			    				<option value="4">Master 1</option>
			    				<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="niveau">Semestre</label>
					    </div>
					    <div class="w-9/12">
			    			<select name="semesterBadgeGr" class="input w-full">
								<option <?php if (date('m')>=7) {echo "selected";} ?> value="1">Premier semestre</option>
								<option value="3">Semestre d'été</option>
								<option <?php if (date('m')<7) {echo "selected";} ?> value="2">Deuxième semestre</option>
								<option value="4">Semestre d'hiver</option>
							</select>
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearBadgeGr" id="yearBadgeGr" class="input w-full">
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
				<hr><br>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	
					    </div>
					    <div class="w-9/12">
					    	<input type="checkbox" name="back" id="back" checked>
								<label for="back"> Imprimer avec verso.</label>
					    </div>
				</div>
				<hr>
				<b class="text-slate-500">Si vous voulez exporter pour un seul étudiant...</b><br>
				<div class="flex mb-3">
				    	<div class="w-6/12 text-right pr-2">
					      	<label for="yearTicket">Matricule</label>
					    </div>
					    <div class="w-6/12">
					    	<input type="text" name="student_id" class="input w-full">
					    </div>
				</div><hr>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifBadgeGr" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnBadgeGr" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- FOR EXCEL EXPORT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifExcel" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl rounded-lg overflow-hidden">
			<div class="p-3 bg-green-600 text-white flex items-center gap-2">
				<i class="bi bi-file-earmark-excel-fill text-lg"></i>
				<b>Exporter la liste d'étudiants (Excel)</b>
			</div>
			<form method="post" action="<?=$app_base?>/src/genPDF/gen.excel.php" target="_blank">
			<div class="p-4">
				<b class="text-slate-600 text-sm">Type d'exportation</b>
				<div class="flex mb-3 mt-2">
					<div class="w-3/12 text-right pr-2">
						<label for="excelExportType" class="text-sm">Type</label>
					</div>
					<div class="w-9/12">
						<select id="excelExportType" name="exportation" class="input w-full border rounded px-2 py-1">
							<option value="general">Liste d'étudiant générale</option>
							<option value="internat">Liste d'étudiant interne</option>
							<option value="abnment">Liste d'étudiant abonnée</option>
							<option value="adventiste">Liste d'étudiant Adventiste</option>
							<option value="non_adventiste">Liste d'étudiant Non-Adventiste</option>
							<option value="contacts">Liste avec contacts (email, téléphone)</option>
						</select>	
					</div>
				</div>
				
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2"></div>
					<div class="w-9/12">
						<input id="excelNewStudent" type="checkbox" name="new_student">
						<label for="excelNewStudent" class="text-sm"> Les nouveaux seulement</label>
					</div>
				</div>
				
				<hr class="my-3">
				
				<b class="text-slate-600 text-sm">Filtres</b>
				<div class="flex mb-3 mt-2">
					<div class="w-3/12 text-right pr-2">
						<label for="excelMention" class="text-sm">Mention</label>
					</div>
					<div class="w-9/12">
						<select id="excelMention" name="types" class="input w-full border rounded px-2 py-1">
							<option value="TOUT">Tout</option>
<?php 
	$voir = $dtb->query("SELECT * FROM filiere");
	while ($affiche = $voir->fetch()) {?>
							<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>
<?php } ?>	
						</select>	
					</div>
				</div>

				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="excelAnneeScolaire" class="text-sm">Année</label>
					</div>
					<div class="w-9/12">
						<select id="excelAnneeScolaire" name="anneescolaire" class="input w-full border rounded px-2 py-1">
<?php
	$y = date('Y');
	for ($i=0; $i <= 3; $i++) { 
		if (date('m')>7) {
			$annee = $y." - ".($y+1);	
		}else{
			$annee = ($y-1)." - ".$y;
		}
?>
							<option value="<?=$annee?>"><?=$annee?></option>
<?php
		$y = $y - 1;
	}
?>
						</select>	
					</div>
				</div>

				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="excelNiveau" class="text-sm">Niveau</label>
					</div>
					<div class="w-9/12">
						<select id="excelNiveau" name="annee_etude" class="input w-full border rounded px-2 py-1">
							<option value="tout">Tout</option>
							<option value="1">Licence 1</option>
							<option value="2">Licence 2</option>
							<option value="3">Licence 3</option>
							<option value="4">Master 1</option>
							<option value="5">Master 2</option>
							<option value="10">Classe spéciale</option>
						</select>	
					</div>
				</div>

				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="excelSemestre" class="text-sm">Semestre</label>
					</div>
					<div class="w-9/12">
						<select id="excelSemestre" name="semestre" class="input w-full border rounded px-2 py-1">
							<option <?php if (date('m')>7) { echo "selected"; } ?> value="1">Premier semestre</option>
							<option value="3">Semestre d'été</option>
							<option <?php if (date('m')<=7) { echo "selected"; } ?> value="2">Deuxième semestre</option>
							<option value="4">Semestre d'hiver</option>
						</select>	
					</div>
				</div>

				<hr class="my-3">

				<b class="text-slate-600 text-sm">Colonnes à inclure</b>
				<div class="flex flex-wrap mb-3 mt-2">
					<div class="w-4/12 p-1">
						<input id="excelMatricule" type="checkbox" name="col_matricule" checked>
						<label for="excelMatricule" class="text-sm">Matricule</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelNom" type="checkbox" name="col_nom" checked>
						<label for="excelNom" class="text-sm">Nom</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelPrenom" type="checkbox" name="col_prenom" checked>
						<label for="excelPrenom" class="text-sm">Prénom</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelSexe" type="checkbox" name="col_sexe" checked>
						<label for="excelSexe" class="text-sm">Sexe</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelDateNaissance" type="checkbox" name="col_datenaissance">
						<label for="excelDateNaissance" class="text-sm">Date naiss.</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelMentionCol" type="checkbox" name="col_mention" checked>
						<label for="excelMentionCol" class="text-sm">Mention</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelNiveauCol" type="checkbox" name="col_niveau" checked>
						<label for="excelNiveauCol" class="text-sm">Niveau</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelEmail" type="checkbox" name="col_email">
						<label for="excelEmail" class="text-sm">Email</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelTelephone" type="checkbox" name="col_telephone">
						<label for="excelTelephone" class="text-sm">Téléphone</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelAdresse" type="checkbox" name="col_adresse">
						<label for="excelAdresse" class="text-sm">Adresse</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelStatus" type="checkbox" name="col_status">
						<label for="excelStatus" class="text-sm">Statut</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelReligion" type="checkbox" name="col_religion">
						<label for="excelReligion" class="text-sm">Religion</label>
					</div>
				</div>
			</div>
			<div class="p-3 bg-slate-200 flex justify-center gap-3">
				<a href="#" id="cancelnotifExcel" class="bg-slate-400 hover:bg-slate-500 text-white px-4 py-2 rounded-md transition">Annuler</a>
				<input id="btnExcel" type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md cursor-pointer transition" value="Télécharger Excel">
			</div>
			</form>
		</div>

	</div>


<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->
<script type="text/javascript">
	$(document).ready(function(){
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportExcel').click(function(){
			$('#notifExcel').css({'display':'block'});
		});

		$('#cancelnotifExcel').click(function(){
			$('#notifExcel').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListStd').click(function(){
			$('#notifListStd').css({'display':'block'});
		});

		$('#cancelnotifListStd').click(function(){
			$('#notifListStd').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListCours').click(function(){
			$('#notifListCours').css({'display':'block'});
		});

		$('#cancelnotifListCours').click(function(){
			$('#notifListCours').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListFOP').click(function(){
			$('#notifListFOP').css({'display':'block'});
		});
		$('#yearFOP').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnFOP').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnFOP').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifListFOP').click(function(){
			$('#notifListFOP').css({'display':'none'});
		});


		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListCSV').click(function(){
			$('#notifListCSV').css({'display':'block'});
		});
		$('#yearListCSV').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnListCSV').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnListCSV').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifListCSV').click(function(){
			$('#notifListCSV').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportMesupres').click(function(){
			$('#notifMesupres').css({'display':'block'});
		});
		$('#yearMesupres').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnMesupres').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnMesupres').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifMesupres').click(function(){
			$('#notifMesupres').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('#exportTicketMail').click(function(){
			$('#notifTicketMail').css({'display':'block'});
		});
		$('#yearTicket').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnTicketMail').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnTicketMail').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifTicketMail').click(function(){
			$('#notifTicketMail').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('#finance').click(function(){
			$('#notifFinance').css({'display':'block'});
		});
		$('#yearFinance').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnFinance').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnFinance').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifFinance').click(function(){
			$('#notifFinance').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('#exportStatistic').click(function(){
			$('#notifStatistic').css({'display':'block'});
		});
		$('#yearStatistic').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnStatistic').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnStatistic').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifStatistic').click(function(){
			$('#notifStatistic').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('.logOut').click(function(){
			$('#notifLogOut').css({'display':'block'});
		});
		
		$('#cancelnotifLogOut').click(function(){
			$('#notifLogOut').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
		

		$('#workedSlip').click(function(){
			$('#notifworkedSlip').css({'display':'block'});
		});
		$('#yearworkedSlip').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnworkedSlip').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnworkedSlip').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifworkedSlip').click(function(){
			$('#notifworkedSlip').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

		$('#filesSlip').click(function(){
			$('#notiffilesSlip').css({'display':'block'});
		});
	
		$('#cancelnotiffilesSlip').click(function(){
			$('#notiffilesSlip').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

		$('#worked').click(function(){
			$('#notifworked').css({'display':'block'});
		});
	
		$('#cancelnotifworked').click(function(){
			$('#notifworked').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	

		$('#badgeGr').click(function(){
			$('#notifBadgeGr').css({'display':'block'});
		});
	
		$('#cancelnotifBadgeGr').click(function(){
			$('#notifBadgeGr').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
	});

</script>

<style type="text/css">
	.input{
		padding: 0px 2px 0px 2px;
		font-size: 13px;
	}
</style>