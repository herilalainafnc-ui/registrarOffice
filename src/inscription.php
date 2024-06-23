<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Home</title>
</head>
<body class="bg-slate-600 text-sm">
	<div class="h-screen w-full bg-slate-600">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>

<form id="form-inscription">
			
				<div class="bg-slate-800 w-all mx-1 px-2 py-1 text-slate-100 flex">
					<div class="w-10/12">
						<p><b>Inscription</b></p>	
					</div>	
					
					<div class="w-2/12 text-right">
						<button class="px-5 py-0 bg-cyan-700 rounded-md" id="btn-inscription" type="button">Enregistrer</button>
					</div>
				</div>

				<div class="w-full px-0.5 flex" style="height: calc(100vh - 180px);">
						
						<div class="bg-slate-800 my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>

						<div class="bg-slate-800 my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto grid gap-2 grid-cols-2">
							
							
<!-- //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
							<div class="m-0 p-2 bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
										<div class="w-full flex mb-4">
											<div class="w-8/12">
												<b>Infos personnelle</b>	
											</div>
										</div>

										<div class="flex gap-2">
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Nom</label>
												<input class="inscInput p-0  h-5 text-sm w-full requierd-1" type="text" name="student_nom" placeholder="--"><br><br>

												<label class="text-sm text-slate-400">Date de naissance</label>
												<input class="inscInput p-0  h-5 text-sm w-full requierd-2" type="date" name="dateNaissance" placeholder="--"><br><br>

												<label class="text-sm text-slate-400">Genre</label>
												<select class="inscInput p-0  h-5 text-sm w-full" name="sex" default>
													<option class="bg-slate-800" value="1">Masculin</option>
													<option class="bg-slate-800" value="0">Feminin</option>
												</select><br><br>

												<label class="text-sm text-slate-400">CIN</label>
												<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="num_cin" placeholder="--"><br><br>

												<label class="text-sm text-slate-400">CIN région</label>
												<select class="inscInput p-0  h-5 text-sm w-full" name="cin_region">
													<option class="bg-slate-800"></option>
								<?php 
								$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
								while ($showR = $findRegion->fetch()) {
								 ?>	
								 					<option class="bg-slate-800" value="<?=$showR['id']?>"><?=$showR['region']?></option>
								 <?php 
								}
								 ?>
													
												</select>			
											</div>
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Prénom</label>
												<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="student_prenom" placeholder="--"><br><br>

												<label class="text-sm text-slate-400">Lieu de naissance</label>
												<input class="inscInput p-0  h-5 text-sm w-full requierd-3" type="text" name="lieuNaissance" placeholder="--"><br><br>

												<label class="text-sm text-slate-400">Nationalité</label>
												<input class="inscInput p-0  h-5 text-sm w-full requierd-4" type="text" name="nationalite" placeholder="--"><br><br>

												<label class="text-sm text-slate-400">Date de délivrance</label>
												<input class="inscInput p-0  h-5 text-sm w-full" type="date" name="cin_date_delivre" placeholder="--">
											</div>
										</div>	
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
							<div class="m-0 p-2 bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos du contact</b>	
									</div>
								</div>

								<div class="w-full flex gap-2">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Téléphone</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="student_tel" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Adresse mail</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="student_email" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Pays d'origine</label>
										<input class="inscInput p-0  h-5 text-sm w-full requierd-5" type="text" name="pays_origine" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Région</label>
										<select class="inscInput p-0  h-5 text-sm w-full requierd-6" name="student_region">
											<option class="bg-slate-800"></option>
						<?php 
						$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
						while ($showR = $findRegion->fetch()) {
						 ?>	
						 					<option class="bg-slate-800" value="<?=$showR['id']?>"><?=$showR['region']?></option>
						 <?php 
						}
						 ?>
											
										</select>
											<br><br>
										<label class="text-sm text-slate-400">Adresse actuel</label>
										<input class="inscInput p-0  h-5 text-sm w-full requierd-7" type="text" name="student_adresse" placeholder="--"><br><br>
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400" for="student_images">Photos</label><br>

										<label for="student_images">
											<div class="bg-slate-800 rounded-md h-20 w-20 text-center py-3">
												<i class="bi-image text-4xl text-black"></i>
											</div>
										</label>
										<input type="file" name="student_images" id="student_images" class="hidden">
										
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->			
							<div class="m-0 p-2 bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos d'étude</b>	
									</div>
								</div>

								<div class="w-full flex gap-2">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Mention</label>
										
										<select class="inscInput p-0  h-5 text-sm w-full requierd-8" name="etude_envisage">
											<option class="bg-slate-800"></option>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>	
											<option class="bg-slate-800"><?=$showSignMention['filiere_description']?></option>
 <?php 
}
 ?>												
										</select><br><br>

										<label class="text-sm text-slate-400">Niveau</label>
										
										<select class="inscInput p-0  h-5 text-sm w-full" name="annee_etude">
											<option class="bg-slate-800" value="1">Licence 1</option>
											<option class="bg-slate-800" value="2">Licence 2</option>
											<option class="bg-slate-800" value="3">Licence 3</option>
										</select>
										<br><br>
										
										<label class="text-sm text-slate-400">Matricule</label>
										<p class=""><i class="bi-lock-fill"></i>00000</p><br>
										
										<label class="text-sm text-slate-400">Status</label>
										
										<select class="inscInput p-0  h-5 text-sm w-full" name="status">
											<option class="bg-slate-800">Externe</option>
											<option class="bg-slate-800">Interne</option>
										</select>
										
										
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Parcours</label>
										
										<select id="firstEtd" class="inscInput p-0  h-5 text-sm w-full requierd-9" name="etude_option">

											<option class="bg-slate-800"></option>
<?php
$findOption = $dtb->query('SELECT * FROM filiere_parcours ORDER BY description');
while ($showO = $findOption->fetch()) {
 ?>	
 											<option class="bg-slate-800"><?=$showO['description']?></option>
 <?php 
}
 ?>	
						 				</select>
										<br><br>
										<label class="text-sm text-slate-400">Année universitaire</label>
										
										<select class="inscInput p-0  h-5 text-sm w-full requierd-10" name="annee_scolaire">
											<option class="bg-slate-800"></option>
						<?php
						$y = date('Y');
						for ($i=0; $i <= 8; $i++) { 
							
							$as = $y." - ".($y+1);
							?>
											<option class="bg-slate-800"><?=$as?></option>
						<?php
						$y = $y - 1;
						}
						 ?>
										</select>
										<br><br>
										<label class="text-sm text-slate-400">Ancien étudiant</label>
									
										<select class="inscInput p-0  h-5 text-sm w-full" name="new_student">
											<option class="bg-slate-800">Non</option>
											<option class="bg-slate-800">Oui</option>
										</select>
										
										
									</div>
								</div>	
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->							
							<div class="m-0 p-2 bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos parentale</b>	
									</div>
								</div>

								<div class="w-full flex gap-2">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom du père</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="father_name" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Nom de la mère</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="mother_name" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Téléphone</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="parent_tel" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Adresse</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="parent_adresse" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Sa profession</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="father_prof" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Sa profession</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="mother_prof" placeholder="--">
										
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->							
							<div class="m-0 p-2 bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos du sponsor</b>	
									</div>
								</div>

								<div class="w-full flex gap-2">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom du sponsor</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="sponsor_nom" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Téléphone</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="sponsor_tel" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Adresse</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="sponsor_adresse" placeholder="--">
										
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Prénom</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="sponsor_prenom" placeholder="--">
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->							
							<div class="m-0 p-2 bg-slate-600 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Autres...</b>	
									</div>
								</div>

								<div class="w-full flex">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">État civil</label>
										<select class="inscInput p-0  h-5 text-sm w-full" name="" placeholder="--">
											<option class="bg-slate-800">Célibataire</option>
											<option class="bg-slate-800">Marié</option>
										</select>
											<br><br>
										<label class="text-sm text-slate-400">Nombre d'enfant</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="nb_enfant" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Numéro visa</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="num_visa" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom du/de conjoint(e)</label>
										<input class="inscInput p-0  h-5 text-sm w-full" type="text" name="nom_conjoint" placeholder="--"><br><br>
										<label class="text-sm text-slate-400">Réligion</label>
										<select id="firstAutr" class="inscInput p-0  h-5 text-sm w-full" name="religion">
											<option class="bg-slate-800">Adventiste</option>
											<option class="bg-slate-800">non Adventiste</option>
										</select>
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

						</div>

				</div>
</form>					
				<div class="w-10/12 h-6 bg-slate-500 mt-1 absolute bottom-0">
						
				</div>
			</div>


		</div>

	</div>
</body>
</html>
<style type="text/css">
	.inscInput{
		border: none;
		padding: 0px 2px 0px 2px;
	}
</style>
<script type="text/javascript">
	$(document).ready(function(){
		$('#btn-inscription').Click(function() {
			
			var r_1 = $('.requierd-1').val();
			var r_2 = $('.requierd-2').val();
			var r_3 = $('.requierd-3').val();
			var r_4 = $('.requierd-4').val();
			var r_5 = $('.requierd-5').val();
			var r_6 = $('.requierd-6').val();
			var r_7 = $('.requierd-7').val();
			var r_8 = $('.requierd-8').val();
			var r_9 = $('.requierd-9').val();
			var r_10 = $('.requierd-10').val();
				
				if(r_1 =="" || r_2 =="" || r_3 =="" || r_4 =="" || r_5 =="" || r_6 =="" || r_7 =="" || r_8 =="" || r_9 =="" || r_10 =="") {			
					
					$('.requierd-1').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-2').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-3').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-4').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-5').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-6').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-7').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-8').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
					$('.requierd-9').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});			
					$('.requierd-10').css({'border':'1px solid #FF6E6E','border-radius':'5px','background':'#FF8484'});
				
				}else{
				
					$('#form-inscription').attr('action','../app/inscription.php');
					$('#btn-inscription').attr('types','submit');
				
				}
		});
	});
</script>
<style type="text/css">
	.inscInput{
		background: none;
	}
</style>