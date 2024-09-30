<!-- ////////////////////// GENERAL TOOLBAR //////////////////////////////////////////// -->

<!-- FOR LIST STUDENT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListStd" style="backdrop-filter: blur(3px);">
		
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl text-xs">
			<div class="p-2 text-black">
				<b>Exporter la liste d'étudiant.</b>
			</div>
			<form method="post" action="./data.topdf.php?ptype=listeStd" target="_blank">
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
									for ($i=0; $i < 5; $i++) { 
										$a = date('Y')+1;
										$annee = ($a-$i-1)." - ".($a-$i);
									?>
										<option value="<?=$annee?>"><?=$annee?></option>	

									<?php
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
				      				<option value="tout">Tout</option>
				      				<option value="1">Semestre 1</option>
				      				<option value="2">Semestre 2</option>
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
			<form method="post" action="./data.topdf.php?ptype=listeCours" target="_blank">
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
				      				<option value="tout">Tout</option>
				      				<option value="1">Semestre 1</option>
				      				<option value="2">Semestre 2</option>
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
			<form method="post" action="./data.topdf_paysage.php?ptype=foplist" target="_blank">
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
			<form method="post" action="./data.topdf_paysage.php?ptype=mesupres" target="_blank">
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
			
			<form method="post" action="./genPDF/gen.mail_csv.php" target="_blank">
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
			<form method="post" action="./data.topdf.php?ptype=Statistique" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la statistique générale.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearStatistic">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearStatistic" id="yearStatistic" class="input w-full">
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
				<a href="#" id="cancelnotifStatistic" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnStatistic" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR TICKET MAIL -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTicketMail" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="./data.topdf.php?ptype=ticketMail" target="_blank">
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

<!-- FOR WORKED SLIP -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifworkedSlip" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="./data.topdf.php?ptype=workedSlip" target="_blank">
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
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifworkedSlip" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnworkedSlip" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR WORKED -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifworked" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="./data.topdf.php?ptype=worked" target="_blank">
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
			<form method="post" action="./data.topdf.php?ptype=BadgeGr" target="_blank">
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
										
								<option <?php if ($affiche['filiere_description'] == "Théologie") {
									echo "selected";
								} ?>><?=$affiche['filiere_description'];?></option>

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

<!-- LOG OUT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifLogOut" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="./data.topdf.php?ptype=ticketMail" target="_blank">
			<div class="p-2 text-black">
				<b>Alert.</b>
			</div>
			<div class="p-2">
				<p>Voulez-vous vraiment déconnecter?</p>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifLogOut" class="<?=$bg_five_color?> p-2 rounded-md">Annuler</a>
				<a href="../app/logout.php" id="btnnotifLogOut" class="bg-red-600 p-2 text-white rounded-md mx-1">Ce déconnecter</a>
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- ************************ -->


	

<!-- ************************ -->

<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->
<script type="text/javascript">
	$(document).ready(function(){
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