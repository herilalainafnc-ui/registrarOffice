<!-- ////////////////////// GENERAL TOOLBAR //////////////////////////////////////////// -->

<!-- FOR LIST STUDENT -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListStd" style="backdrop-filter: blur(3px);">
		
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Exporter la liste d'étudiant.</b>
			</div>
			<form method="post" action="./data.topdf.php?ptype=listeStd" target="_blank">
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
				      	
				      	<b class="toolInactive">Année/Semestre.</b>
				      	
				      	<div class="flex mb-3">

				      		<div class="w-3/12 text-right pr-2">
				      			<label for="annee">Année : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="annee" name="annee_etude" class="input w-full">
				      				<option value="tout">Toutes les années</option>
				      				<option value="1">Première année</option>
				      				<option value="2">Deuxième année</option>
				      				<option value="3">Troisième année</option>
				      				<option value="4">Quatrième année</option>
				      				<option value="5">Cinquième année</option>
				      				<option value="10">Etudiant spécial</option>
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
				      				<option value="tout">Tous les semestres</option>
				      				<option value="1">Premier semestre</option>
				      				<option value="2">Deuxième semestre</option>
				      			</select>	
				      		</div>
				      	</div>
				      	<hr>

				      	<b class="toolInactive">Colonne.</b>
				      	
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="signature" type="checkbox" name="signature">
				      			<label for="signature">Colonne de signature.</label>	
				      		</div>	
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
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
				      	
				      	<b class="toolInactive">Année/Semestre.</b>
				      	
				      	<div class="flex mb-3">

				      		<div class="w-3/12 text-right pr-2">
				      			<label for="annee">Année : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="annee" name="yearlevel" class="input w-full">
				      				<option value="tout">Toutes les années</option>
				      				<option value="1">Première année</option>
				      				<option value="2">Deuxième année</option>
				      				<option value="3">Troisième année</option>
				      				<option value="4">Quatrième année</option>
				      				<option value="5">Cinquième année</option>
				      				<option value="10">Etudiant spécial</option>
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
				      				<option value="tout">Tous les semestres</option>
				      				<option value="1">Premier semestre</option>
				      				<option value="2">Deuxième semestre</option>
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
			
				$('#btnFOP').attr('class','bg-slate-400 p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifListFOP').click(function(){
			$('#notifListFOP').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('#exportTicketMail').click(function(){
			$('#notifTicketMail').css({'display':'block'});
		});
		$('#yearTicket').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnTicketMail').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnTicketMail').attr('class','bg-slate-400 p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifTicketMail').click(function(){
			$('#notifTicketMail').css({'display':'none'});
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