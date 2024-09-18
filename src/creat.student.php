<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Ajout étudiant</title>
</head>
<body class="<?=$bg_three_color?> text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>

<form id="form-inscription" enctype="multipart/form-data">
			
				<div class="<?=$bg_one_color?> w-all mx-1 px-2 py-1 text-slate-100 flex">
					<div class="w-2/12">
						<p><b>Inscription</b></p>	
					</div>	
					<div class="w-8/12">
						<p><em class="text-red-500" id="alert"></em></p>
					</div>
					<div class="w-2/12 text-right">
						<button class="px-5 py-0 bg-cyan-700 rounded-md" id="btn-inscription" type="button">Enregistrer</button>
					</div>
				</div>

				<div class="w-full px-0.5 flex" style="height: calc(100vh - 180px);">
						
						<div class="<?=$bg_one_color?> my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>

						<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto grid gap-2 lg:grid-cols-2 xl:grid-cols-3">
							
							
<!-- //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
							<div class="m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
										<div class="w-full flex mb-4">
											<div class="w-8/12">
												<b>Infos personnelle</b>	
											</div>
										</div>
										<div class="flex gap-2 mb-3">
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Nom</label>
												<input class="inscInput h-6 text-sm w-full requierd-1" type="text" name="student_nom" id="student_nom" placeholder="--">
											</div>
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Prénom</label>
												<input class="inscInput h-6 text-sm w-full" type="text" name="student_prenom" id="student_prenom" placeholder="--">
											</div>
										</div>
										<div class="flex gap-2 mb-3">
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Date de naissance</label>
												<input class="inscInput h-6 text-sm w-full requierd-2" type="date" name="dateNaissance">
											</div>
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Lieu de naissance</label>
												<input class="inscInput h-6 text-sm w-full requierd-3" type="text" name="lieuNaissance" placeholder="--">
											</div>
										</div>
										<div class="flex gap-2 mb-3">
											<div class="w-6/12">
												<label class="text-sm text-slate-400">CIN</label>
												<input class="inscInput h-6 text-sm w-full" type="text" name="num_cin" placeholder="--">
											</div>
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Date de délivrance</label>
												<input class="inscInput h-6 text-sm w-full" type="date" name="cin_date_delivre">
											</div>
										</div>
										<div class="flex gap-2 mb-3">
											<div class="w-6/12">
												<label class="text-sm text-slate-400">CIN région</label>
												<select class="inscInput h-6 text-sm w-full" name="cin_region">
													<option class="<?=$bg_seven_color?>"></option>
								<?php 
								$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
								while ($showR = $findRegion->fetch()) {
								 ?>	
								 					<option class="<?=$bg_seven_color?>" value="<?=$showR['id']?>"><?=$showR['region']?></option>
								 <?php 
								}
								 ?>
													
												</select>
											</div>
											<div class="w-6/12">
												
											</div>
										</div>
										<div class="flex gap-2 mb-3">
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Genre</label>
												<select class="inscInput h-6 text-sm w-full" name="sex">
													<option class="<?=$bg_seven_color?>" value="1">Masculin</option>
													<option class="<?=$bg_seven_color?>" value="0">Feminin</option>
												</select>
											</div>
											<div class="w-6/12">
												<label class="text-sm text-slate-400">Nationalité</label>
												<input class="inscInput h-6 text-sm w-full requierd-4" type="text" name="nationalite" placeholder="--">
											</div>
										</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
							<div class="m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos du contact, photos</b>	
									</div>
								</div>

								<div class="w-full flex gap-2">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Téléphone</label>
										<input class="inscInput h-6 text-sm w-full mb-3" type="text" name="student_tel" placeholder="--">
										<label class="text-sm text-slate-400">Adresse mail</label>
										<input class="inscInput h-6 text-sm w-full mb-3" type="text" name="student_email" id="student_email" placeholder="--">
										<label class="text-sm text-slate-400">Pays d'origine</label>
										<input class="inscInput h-6 text-sm w-full requierd-5 mb-3" type="text" name="pays_origine" placeholder="--">
										<label class="text-sm text-slate-400">Région</label>
										<select class="inscInput h-6 text-sm w-full requierd-6 mb-3" name="student_region">
											<option class="<?=$bg_seven_color?>"></option>
						<?php 
						$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
						while ($showR = $findRegion->fetch()) {
						 ?>	
						 					<option class="<?=$bg_seven_color?>" value="<?=$showR['id']?>"><?=$showR['region']?></option>
						 <?php 
						}
						 ?>
											
										</select>
											
										<label class="text-sm text-slate-400">Adresse actuel</label>
										<input class="inscInput h-6 text-sm w-full requierd-7 mb-3" type="text" name="student_adresse" placeholder="--">
									</div>
									<div class="w-6/12 pl-5">
										<label class="text-sm text-slate-400" for="student_images">Photos</label><br>

										<label for="student_images">
											<div class="<?=$bg_one_color?> rounded-md h-20 w-20 text-center py-3">
												<i class="bi-image text-4xl text-black"></i>
											</div>
										</label>
										<input type="file" name="image_student" id="student_images" class="hidden">
										
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->			
							<div class="m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos d'étude</b>	
									</div>
								</div>
								

								<div class="w-full flex gap-2">
									

									<div class="w-6/12">
										<label class="text-sm text-slate-400">Matricule</label>
										<p class="mb-1" id="student_id"><i class="bi-lock-fill"></i>00000</p>
										<input type="text" name="student_id" id="student_id_form" class="inscInput hidden">

										<label class="text-sm text-slate-400 mt-3">Mention</label>
										
										<select class="inscInput h-6 text-sm w-full requierd-8 mb-3" name="etude_envisage" id="etude_envisage">
											<option class="<?=$bg_seven_color?>"></option>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>	
											<option class="<?=$bg_seven_color?>" value="<?=$showSignMention['filiere_sigle']?>"><?=$showSignMention['filiere_description']?></option>
 <?php 
}
 ?>												
										</select>

										<label class="text-sm text-slate-400">Niveau</label>
										
										<select class="inscInput h-6 text-sm w-full mb-3" name="annee_etude" id="annee_etude">
											<option class="<?=$bg_seven_color?>" value="0">Remise à niveau</option>
											<option class="<?=$bg_seven_color?>" value="1" selected>Licence 1</option>
											<option class="<?=$bg_seven_color?>" value="2">Licence 2</option>
											<option class="<?=$bg_seven_color?>" value="3">Licence 3</option>
											<option class="<?=$bg_seven_color?>" value="4">Master 1</option>
											<option class="<?=$bg_seven_color?>" value="5">Master 2</option>
										</select>
												
										<label class="text-sm text-slate-400">Status</label>
										
										<select class="inscInput h-6 text-sm w-full mb-3" name="status">
											<option class="<?=$bg_seven_color?>">Externe</option>
											<option class="<?=$bg_seven_color?>">Interne</option>
											<option class="<?=$bg_seven_color?>">Bungalow</option>
										</select>
										
										<div class="obtention_Bacc">
											<label class="text-sm text-slate-400">Année d'obtention Bacc</label>
											<input class="inscInput h-6 text-sm w-full mb-3" type="date" name="obtention_bacc">
										</div>
										
										<div class="diplome_preced hidden">
											<label class="text-sm text-slate-400">Diplôme précédent</label>
											<input class="inscInput h-6 text-sm w-full mb-3" type="text" name="diplome_preced">
										</div>
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Entrée du</label>
										<select class="inscInput h-6 text-sm w-full mb-3" name="semestre">
											<option class="<?=$bg_seven_color?>" value="1">Premier semestre</option>
											<option class="<?=$bg_seven_color?>" value="2">Deuxieme semestre</option>
										</select>

										<label class="text-sm text-slate-400">Parcours</label>
										
										<div id="etude_option">
											<select id="firstEtd" class="inscInput h-6 text-sm w-full requierd-9 mb-3" name="etude_option">
												<option class="<?=$bg_seven_color?>"></option>
											</select>
										</div>
										<label class="text-sm text-slate-400">Année universitaire</label>
										
										<select class="inscInput h-6 text-sm w-full requierd-10 mb-3" name="annee_scolaire">
											
						<?php
						$y = date('Y');
						for ($i=0; $i <= 7; $i++) { 
							
							$as = $y." - ".($y+1);
							?>
											<option class="<?=$bg_seven_color?>"><?=$as?></option>
						<?php
						$y = $y - 1;
						}
						 ?>
										</select>
										
										<label class="text-sm text-slate-400">Ancien étudiant</label>
									
										<select class="inscInput h-6 text-sm w-full mb-3" name="new_student">
											<option class="<?=$bg_seven_color?>" value="0">Non</option>
											<option class="<?=$bg_seven_color?>" value="1">Oui</option>
										</select>
									
										<div class="obtention_Bacc">
											<label class="text-sm text-slate-400">Série du Bacc</label>
											<select class="inscInput h-6 text-sm w-full mb-3" name="serie_bacc">
												<option class="<?=$bg_seven_color?>">A1</option>
												<option class="<?=$bg_seven_color?>">A2</option>
												<option class="<?=$bg_seven_color?>">BTP</option>
												<option class="<?=$bg_seven_color?>">C</option>
												<option class="<?=$bg_seven_color?>">D</option>
												<option class="<?=$bg_seven_color?>">Electronique</option>
												<option class="<?=$bg_seven_color?>">G1</option>
												<option class="<?=$bg_seven_color?>">G2</option>
												<option class="<?=$bg_seven_color?>">G3</option>
												<option class="<?=$bg_seven_color?>">L</option>
												<option class="<?=$bg_seven_color?>">S</option>
												<option class="<?=$bg_seven_color?>">OSE</option>
												<option class="<?=$bg_seven_color?>">Technique Ouvrage Bois</option>
												<option class="<?=$bg_seven_color?>">Technique Ouvrage Métalique</option>
												<option class="<?=$bg_seven_color?>">Technique Maintenance Automobile</option>
											</select>
										</div>
										
										<div class="diplome_preced hidden">
											<label class="text-sm text-slate-400">Date d'obtention</label>
											<input class="inscInput h-6 text-sm w-full mb-3" type="date" name="date_obtent_diplome_preced">
										</div>

					
									</div>
								</div>	
							</div>
<!-- ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->							
							<div class="m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos parentale</b>	
									</div>
								</div>

								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom du père</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="father_name" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Sa profession</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="father_prof" placeholder="--">
									</div>
								</div>
								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom de la mère</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="mother_name" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Sa profession</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="mother_prof" placeholder="--">
									</div>
								</div>
								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Téléphone</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="parent_tel" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Adresse</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="parent_adresse" placeholder="--">
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->							
							<div class="m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Infos du sponsor</b>	
									</div>
								</div>

								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom du sponsor</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="sponsor_nom" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Prénom</label>
										<input class="inscInput  h-6 text-sm w-full" type="text" name="sponsor_prenom" placeholder="--">
									</div>
								</div>
								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Téléphone</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="sponsor_tel" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Adresse</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="sponsor_adresse" placeholder="--">
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->							
							<div class="m-0 p-2 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
								<div class="w-full flex mb-4">
									<div class="w-8/12">
										<b>Situation familiale, autres...</b>	
									</div>
								</div>

								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">État civil</label>
										<select class="inscInput h-6 text-sm w-full" name="situationf" placeholder="--">
											<option class="<?=$bg_seven_color?>">Célibataire</option>
											<option class="<?=$bg_seven_color?>">Marié</option>
										</select>
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nom du/de conjoint(e)</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="nom_conjoint" placeholder="--">
									</div>
								</div>
								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Nombre d'enfant</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="nb_enfant" value="0">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Réligion</label>
										<select id="firstAutr" class="inscInput h-6 text-sm w-full" name="religion">
											<option class="<?=$bg_seven_color?>">Adventiste</option>
											<option class="<?=$bg_seven_color?>">FLM</option>
											<option class="<?=$bg_seven_color?>">Apokalypsy</option>
											<option class="<?=$bg_seven_color?>">Catholique</option>
											<option class="<?=$bg_seven_color?>">FJKM</option>
											<option class="<?=$bg_seven_color?>">Lutherien</option>
											<option class="<?=$bg_seven_color?>">Musulman</option>
											<option class="<?=$bg_seven_color?>">Shyn</option>
											<option class="<?=$bg_seven_color?>">Jesosy Mamonjy</option>
											<option class="<?=$bg_seven_color?>">Autre...</option>
										</select>
									</div>
								</div>
								<div class="flex gap-2 mb-3">
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Numéro visa</label>
										<input class="inscInput h-6 text-sm w-full" type="text" name="num_visa" placeholder="--">
									</div>
									<div class="w-6/12">
										<label class="text-sm text-slate-400">Abonné au CAF</label>
										<select id="firstAutr" class="inscInput h-6 text-sm w-full" name="abonment">
											<option class="<?=$bg_seven_color?>" value="0">Non</option>
											<option class="<?=$bg_seven_color?>" value="1">Oui</option>
										</select>
									</div>
								</div>
							</div>
<!-- ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

						</div>

				</div>
</form>					
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>
<style type="text/css">
	.inscInput{
		border: none;
		background-color: #e0e0e0;
		padding-top: 0px;
		padding-bottom: 0px;
		border-radius: 2px;
		color: black;
	}
</style>
<script type="text/javascript">
	$(document).ready(function(){
		$('#etude_envisage').on('change', function(){
			var mention = $(this).val();
			var annee_etude = $('#annee_etude').val();

			$.ajax({
				url:"./services/parcours.live.php",
				method:"POST",
				data:{mention:mention},

				success:function(data){
					$("#etude_option").html(data);
				}
			});

			$.ajax({
				url:"./services/matricule.live.php",
				method:"POST",
				data:{mention:mention, annee_etude:annee_etude},

				success:function(data){
					$("#student_id").html('<i class="bi-lock-fill"></i>'+data);
					$("#student_id_form").val(data);
				}
			});
		});
		$('#annee_etude').on('change', function(){
			var mention = $('#etude_envisage').val();
			var annee_etude = $(this).val();

			$.ajax({
				url:"./services/matricule.live.php",
				method:"POST",
				data:{mention:mention, annee_etude:annee_etude},

				success:function(data){
					$("#student_id").html('<i class="bi-lock-fill"></i>'+data);
					$("#student_id_form").val(data);
				}
			});
			
			if(annee_etude <= 3) {
				$('.obtention_Bacc').css({'display':'block'});
				$('.diplome_preced').css({'display':'none'});
			}else if(annee_etude > 3) {
				$('.obtention_Bacc').css({'display':'none'});
				$('.diplome_preced').css({'display':'block'});
			}

		});

		$('#btn-inscription').click(function() {
			
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
				
				$('#alert').text('Ces zones sont obligatoires !');

				$('.requierd-1').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-2').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-3').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-4').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-5').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-6').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-7').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-8').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-9').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});			
				$('.requierd-10').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
			
			}else{

				$('#form-inscription').attr('action','../app/.student/addStd.php?rg_id=<?=$rg_id?>');
				$('#form-inscription').attr('method','post');
				$(this).attr('types','submit');

			}
		});

		$('#btn-inscription').hover(function() {

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
			
			if(r_1 !="" && r_2 !="" && r_3 !="" && r_4 !="" && r_5 !="" && r_6 !="" && r_7 !="" && r_8 !="" && r_9 !="" && r_10 !="") {			
				$(this).attr('type','submit');
				$('#form-inscription').attr('action','../app/.student/addStd.php?rg_id=<?=$rg_id?>');
				$('#form-inscription').attr('method','post');
			}
		});

		$("#student_nom").keyup(function() {
			var name = $(this).val().toLowerCase();

			if($("#student_prenom").val() == ""){
				$("#student_email").val(name+'.uaz@zurcher.edu.mg');
			}else{
				var lastname = $('#student_prenom').val().substr(0,3).toLowerCase();
				$("#student_email").val(name+'.'+lastname+'@zurcher.edu.mg');
			}
		});

		$('#student_prenom').keyup(function() {
			var name = $('#student_nom').val().toLowerCase();
			
			if($(this).val() == ""){
				$("#student_email").val(name+'.uaz@zurcher.edu.mg');
			}else{
				var lastname = $(this).val().substr(0,3).toLowerCase();
				$("#student_email").val(name+'.'+lastname+'@zurcher.edu.mg');
			}
		});
	});
</script>