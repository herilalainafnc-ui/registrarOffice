<!DOCTYPE html>
<html>
<head>
	<?php require('../init/head.php');?>
	<link rel="stylesheet" type="text/css" href="../src/css/style.css">
	<title>Inscription</title>
</head>
<body>
	<!-- TOP BAR --><?php require('../init/topbar.php');?>

	<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
	
	<div class="w-full bg-slate-700 py-6" style="height: calc(100vh - 48px);">

		<div class="xl:w-11/12 sm:w-11/12 sm:rounded-xl p-3 bg-slate-800 mx-auto shadow-sm" style="height: calc(100vh - 110px);">
			<div id="header" class="flex w-full h-[70px]">
				<div class="text-white w-3/12">
					<b>Inscription et réinscription</b>
					<form method="post" action="#">
					<input id="std-search" type="text" name="student_id" placeholder="Matricule..." class="h-8 px-2 text-sm rounded-md mt-2 <?=$bg_two_color?> lg:w-6/12 sm:w-full">
						<button type="submit" style="display: none" onclick="surligne();"></button>
					</form>

				</div>
				
<?php
if (isset($_POST['student_id']) OR isset($_GET['student_id'])) {
		
		if (isset($_GET['student_id'])) {
			
			$search = $_GET['student_id'];
		
		}else{
		
			$search = $_POST['student_id'];
		
		}
		
		// Utiliser des requêtes préparées pour éviter l'injection SQL
		$searchParam = '%' . $search . '%';
		$stmt = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE (student_id LIKE :search1 OR student_nom LIKE :search2 OR student_prenom LIKE :search3) AND remove != 1 LIMIT 1');
		$stmt->execute(['search1' => $searchParam, 'search2' => $searchParam, 'search3' => $searchParam]);
		$recupsdt = $stmt;

?>

				<div id="five-stages" class="w-7/12 gap-2 grid grid-cols-7 text-center text-white"></div>
				
				<div id="session" class="text-white text-right w-2/12"></div>

			</div>
			
			<div id="body" class="w-full flex gap-2">
<?php 

	if($recupsdt->rowCount() > 0) {
		$profil = $recupsdt->fetch();
		$id = $profil['id'];
		$student_id = $profil['student_id'];
		$student_nom = $profil['student_nom'];
		$student_prenom = $profil['student_prenom'];
		$level = $profil['annee_etude'];
		$annee_scolaire = $profil['annee_scolaire'];
		$etude_envisage = $profil['etude_envisage'];
		$etude_option = $profil['etude_option'];
		$student_tel = $profil['student_tel'];
		$image_student = $profil['image_student'];
		$lookup_code = $profil['lookup_code'];
		$status = $profil['status'];
		$graduated = $profil['graduated'];
		$date_entry = $profil['date_entry'];


 ?>

				<div class="my-3 px-2 mx-0.5 sm:w-4/12 xl:w-4/12 border-slate-600 border-1 rounded-lg overflow-auto relative" style="height:calc(100vh - 230px);">
						
						<div class="flex my-2 relative text-white">

							<a href="#" data-bs-toggle="dropdown" aria-expanded="false">
							<div class="w-[75px] <?=$bg_one_color?>">
								<?php
								if (!empty($profil['image_student'])) {
								
									$imangeLen = strlen($profil['image_student']);
									
									if ($profil['image_student'] !="" OR $imangeLen >=10) { ?>

									<img src="../app/photosetudiants/<?=$profil['image_student'] ?>" class="border-1 border-black w-full">

								<?php 
									}else{
								?>
									
									<img src="../app/photosetudiants/10054.jpg" class="border-1 border-black w-full">

								<?php
									}
								}else{ ?>
									
									<img src="../app/photosetudiants/10054.jpg" class="border-1 border-black w-full">

								<?php }	?>
								
							</div></a>

							<ul class="dropdown-menu border <?=$bg_five_color?> text-black p-0 rounded-0 text-xs" style="max-height:400px;">
								<?php 
									if(!empty($profil['image_student'])) {
										if ($profil['image_student'] !="" OR $imangeLen >=10) {
								?>
								<a href="#" id="listOpt1"><p class="px-2 py-1 hover:bg-cyan-500">Agrandir</p></a>
								<?php
										} 
									}
								?>
		
							</ul>

							<div class="w-9/12 text-left pl-3">
								
								<div class="w-full bg-gradient-to-r from-cyan-500 px-2 text-white">
									<b>
<?php
	if ($profil['new_student'] == 1) {
	echo "Nouveau";
	}elseif ($profil['new_student'] == 10) {
		echo "Spécial";
	}else{
		echo "Ancien";
	}
?>	</b>
								</div>
								
								<b class="text-1xl"><?=$profil['student_id'] ?></b>
								<p><?php
if($profil['annee_etude'] == 0) {
	echo "Remise à niveau";
}elseif ($profil['annee_etude']>0 AND $profil['annee_etude']<=3) {
	echo "Licence ".$profil['annee_etude'];
}else{
	echo "Master ".$profil['annee_etude']-3;
}
								?></p>
								<p><a href="https://mail.google.com/mail/u/0/#inbox?compose=<?=$profil['student_email']?>" target="_blank"><?=$profil['student_email']?></a></p>
							</div>
							
						</div>
						<hr>
						<?php 
						// Vérification si l'étudiant est suspendu
						$isSuspended = false;
						if (isset($profil['suspended']) && $profil['suspended'] == 1) {
							$dateFin = $profil['date_fin_suspension'];
							if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
								$isSuspended = true;
							}
						}
						
						if ($isSuspended) {
						?>
						<div class="w-full py-2 px-3 my-2 bg-orange-500 text-white rounded-lg text-center">
							<i class="bi bi-exclamation-triangle-fill"></i>
							<b class="text-sm">ÉTUDIANT SUSPENDU</b>
							<p class="text-xs mt-1">L'inscription et la prise de cours sont bloquées.</p>
							<?php if (!empty($profil['date_fin_suspension'])) { ?>
								<p class="text-xs">Fin: <?= date('d/m/Y', strtotime($profil['date_fin_suspension'])) ?></p>
							<?php } ?>
						</div>
						<?php } ?>
						<div class="w-full py-2 text-sm  text-white">
								<b><?=strtoupper($profil['student_nom']) ?> <?=$profil['student_prenom'] ?></b><br>
								<em><?=$profil['etude_envisage']." - ".$profil['etude_option'] ?></em><br>
								<b>Année <?=$profil['annee_scolaire']?></b><br>
								<p class="text-[11px] text-green-600" style="line-height: 12px;">Modifié par <?php 
								if (!empty($profil['last_change_user_id'])) {
									$user_modif_id  = $profil['last_change_user_id'];
									$findUser = $dtb->query('SELECT * FROM compt_utilisateur WHERE id = "'.$user_modif_id.'"');
$showUser = $findUser->fetch();
echo "<b>[".$showUser['prenom']."]</b><br>".$profil['last_change_datetime'];
								}
								 ?></p>
						</div><hr>
						<div class="w-full text-md">

								<div id="intInformation" class="w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200">
									<i class="bi-info-square"></i>
											Information
								</div>
							
								<div id="intNewcours" class="w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 <?php if($isSuspended) echo 'opacity-50 pointer-events-none'; ?>">
									
									<i class="bi-folder-plus"></i>
											Cours offert
								</div>

								<div id="intModepayement" class="w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-2001 <?php if($isSuspended) echo 'opacity-50 pointer-events-none'; ?>">
									
									<i class="bi-cash-coin"></i>
											Mode de payement
								</div>

								<div id="intFicheinscription" class="w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-2001">
									
									<i class="bi-file-text-fill"></i>
											Fiche d'inscription 
								</div>

							</div>

							<div class="absolute bottom-0 w-11/12 m-2 text-center">
								<div class="gap-2 grid grid-cols-2 text-white">
									<a href="#" id="downStage" class="px-5 py-2 bg-slate-700 rounded-md toolInactive">Retour</a>
									<a href="#" id="upStage" data-stage="1" class="px-5 py-2 rounded-md bg-slate-700 toolInactive">Suivant</a>
								</div>
							</div>
<?php 
	// Récupérer les infos de session pour les autres usages dans la page
	$findStudent_Session = $dtb->query('SELECT * FROM t_2024_inscription_session WHERE student_id="'.$student_id.'" ORDER BY id DESC');
	$showStudent_Session = $findStudent_Session->fetch();
	$session_id = isset($showStudent_Session['session_id']) ? $showStudent_Session['session_id'] : '';
	$y = date('Y');
	$aSem = $y." - ".($y+1);
	$aSem_ = ($y-1)." - ".$y;
?>
						</div>

<!-- ::::::::::::::::::::::::::::::: MAIN CONTENT :::::::::::::::::::::::::::::::: -->

						<div id="contentMain" class="my-3 px-2 sm:w-8/12 xl:w-9/12 border-1 border-slate-600 rounded-lg text-sm text-white" style="height:calc(100vh - 230px);">

							<div id="contentGenerateStudet" class="stage_0">
								<div class="w-full text-center pt-20">
									<a class="text-[30px] text-slate-600">Cela est indispensable.</a>
									<div class="w-5/12 m-auto mt-4 p-3 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-lg border-2 <?=$br_two_color?> hover:border-cyan-500 transition-all">
										<a class="text-white">Veuillez préciser la session à laquelle cet étudiant doit s’inscrire.</a>

					<form method="post" action="./app/generate.student.php?student_id=<?=$student_id?>&graduated=<?=$graduated?>" class="session-no-refrech">

										<select name="semesterSession" id="semesterSelect" class="w-full bg-slate-800 rounded-lg my-2">
											<option <?php if (date('m') >= 7) { echo "selected"; } ?>>Premier semestre</option>
											<option>Semestre d'été</option>
											<option <?php if (date('m') < 7) { echo "selected"; } ?>>Deuxième semestre</option>
											<option>Semestre d'hiver</option>
										</select>


										<select class="w-full bg-slate-800 rounded-lg my-2" name="annee_scolaire" id="yearSelect">
						<?php
						
						for ($i=0; $i <= 8; $i++) { 
							if(date('m') >= 7) {
								$as = $y." - ".($y+1);
							}elseif(date('m') < 7) {
								$as = ($y-1)." - ".$y;
							}
							
							?>
											<option><?=$as?></option>
						<?php
						$y = $y - 1;
						}
						 ?>
										</select>
										<button id="submitSession" type="submit" class="my-2 px-5 py-2 bg-cyan-700 rounded-md">Enregistrer</button><br>
										<em id="sessionMessage" class="text-slate-500">Vérification...</em>
					</form>

									</div>

								</div>
							</div>

<script>
// Vérification dynamique de la session
function checkSessionExists() {
	const semester = document.getElementById('semesterSelect').value;
	const year = document.getElementById('yearSelect').value;
	const studentId = '<?=$student_id?>';
	
	const submitBtn = document.getElementById('submitSession');
	const message = document.getElementById('sessionMessage');
	const upStageBtn = document.getElementById('upStage');
	
	// État de chargement
	message.textContent = 'Vérification...';
	message.className = 'text-slate-400';
	
	$.ajax({
		url: './app/check.session.php',
		type: 'POST',
		data: {
			student_id: studentId,
			semester: semester,
			year: year
		},
		success: function(response) {
			// Mettre à jour currentSessionId si disponible
			if (response.session_id) {
				currentSessionId = response.session_id;
			}
			
			if (response.exists) {
				// Session existe déjà - désactiver Enregistrer, activer Suivant
				submitBtn.className = 'my-2 px-5 py-2 bg-slate-800 toolInactive rounded-md';
				submitBtn.disabled = true;
				upStageBtn.className = 'px-5 py-2 rounded-md bg-cyan-700';
				upStageBtn.classList.remove('toolInactive');
				message.textContent = 'La session a déjà été créée. Vous pouvez passer à l\'étape suivante.';
				message.className = 'text-green-500';
			} else if (response.will_replace) {
				// Une autre session existe pour cette année - avertir et permettre le remplacement
				submitBtn.className = 'my-2 px-5 py-2 bg-orange-600 rounded-md';
				submitBtn.disabled = false;
				upStageBtn.className = 'px-5 py-2 rounded-md bg-slate-700 toolInactive';
				message.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + response.message;
				message.className = 'text-orange-400';
			} else {
				// Session n'existe pas - activer Enregistrer, désactiver Suivant
				submitBtn.className = 'my-2 px-5 py-2 bg-cyan-700 rounded-md';
				submitBtn.disabled = false;
				upStageBtn.className = 'px-5 py-2 rounded-md bg-slate-700 toolInactive';
				message.textContent = response.message || 'Veuillez enregistrer la session avant de passer à l\'étape suivante.';
				message.className = 'text-slate-500';
			}
		},
		error: function() {
			message.textContent = 'Erreur de vérification. Veuillez réessayer.';
			message.className = 'text-red-500';
		}
	});
}

// Écouter les changements sur les selects
$(document).ready(function() {
	checkSessionExists(); // Vérification initiale
	
	$('#semesterSelect, #yearSelect').on('change', function() {
		checkSessionExists();
		// Mettre à jour l'affichage de la session dans la sidebar
		updateSessionDisplay();
	});
});

// Mettre à jour l'affichage de la session dans la sidebar
function updateSessionDisplay() {
	$.ajax({
		url: "./stages/session.php",
		method: "POST",
		data: {
			student_id: '<?=$student_id?>',
			session_id: currentSessionId,
			semester: $('#semesterSelect').val(),
			year: $('#yearSelect').val()
		},
		success: function(data) {
			$("#session").html(data);
		}
	});
}
</script>

							<div id="contentInformation" class="stage_1 hidden">
								<?php require ('../src/student/information.php'); ?>
							</div>
							
							<div id="contentNewcours" class="stage_2 hidden">
								<?php require ('../src/student/new.cours.php'); ?>
							</div>
							
							<div id="contentModepayement" class="stage_3 hidden">
								
							</div>
							
							<div id="contentFicheinscription" class="stage_4 hidden">
								<div class="w-full text-center pt-20">
									<a target="_blank" href="#" id="linkFicheInscription"
										data-base-url="../src/data.topdf.php?ptype=Fiche_inscription&id=<?=$id?>&student_id=<?=$student_id?>&student_nom=<?=$student_nom?>&student_prenom=<?=$student_prenom?>&etude_envisage=<?=$etude_envisage?>&level=<?=$level?>&student_tel=<?=$student_tel?>&image_student=<?=$image_student?>"
											class="text-[40px] leading-tight active:bg-cyan-700 p-1">
											<div class="w-5/12 m-auto p-3 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-lg border-2 <?=$br_two_color?> hover:border-cyan-500 transition-all">
											<i class="bi-file-text-fill text-[180px] text-green-300"></i><br>
													Voir le fiche d'inscription
											</center>
										</div>
									</a>
								</div>
							</div>
							<div id="contentImpression" class="stage_5 hidden">
								<div class="w-full text-center pt-40">
									<i class="bi-pencil-fill text-[150px] text-yellow-300"></i>
									<i class="bi-file-text-fill text-[150px] text-yellow-300"></i><br><br><br>
									
									<a class="text-[30px] text-slate-600">Finalisation des signatures.<br><br>Dépôt de la liste au registraire.</a>	
								</div>
								
							</div>
							<div id="contentInscrit" class="stage_6 hidden">
								<div class="w-full text-center pt-40">
									<i class="bi-emoji-smile-fill text-[180px] text-yellow-300"></i><br><br><br>
									<a class="text-[30px] text-slate-600 mt-4">Inscription terminée.</a>
								</div>
								
							</div>

						</div>

<!-- ////////////////////////////////////////////////////////////////////////////// -->

<!-- AFFICHE IMAGE -->

						<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifAffichIMG" style="backdrop-filter: blur(30px);">

							<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
								
								<div class="p-2">
									<div class="w-full h-[400px]" style="background-image: url('../app/photosetudiants/<?=$profil['image_student']?>');background-position: center; background-size: cover;background-repeat: no-repeat;">
										
									</div>

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelAffichIMG" class="px-2 <?=$bg_six_color?> rounded-md py-1 mx-1">Retour</a>
								</div>
							</div>

						</div>
<?php 
		}
	}
?>

			</div>
		</div>
		
	</div>

</body>
</html>

<script type="text/javascript">
	// Variable globale pour stocker le session_id actuel (mis à jour dynamiquement)
	var currentSessionId = '<?=$session_id?>';
	
	$(document).ready(function(){

		$('.session-no-refrech').on('submit',function(submitSS){
			submitSS.preventDefault();

			var url = './app/generate.student.php?student_id=<?=$student_id?>&graduated=<?=$graduated ?? 0?>';
			var data = $(this).serialize();
			
			var submitBtn = $('#submitSession');
			submitBtn.prop('disabled', true).text('Enregistrement...');

			$.post(url, data, function(response){
				// Parser la réponse JSON si possible
				var result = response;
				if (typeof response === 'string') {
					try { result = JSON.parse(response); } catch(e) { result = { success: true }; }
				}
				
				// IMPORTANT: Mettre à jour la variable globale currentSessionId
				if (result.session_id) {
					currentSessionId = result.session_id;
					console.log('Session ID mis à jour:', currentSessionId);
				}

				$('#upStage').attr('class','px-5 py-2 bg-cyan-700 rounded-md');
				$('#upStage').removeClass('toolInactive');
				$('#submitSession').attr('class','my-2 px-5 py-2 bg-slate-800 rounded-md toolInactive');
				$('#submitSession').prop('disabled', true).text('Enregistrer');
				$('#sessionMessage').text('La session a déjà été créée. Vous pouvez passer à l\'étape suivante.').attr('class', 'text-green-500');
				
				var	student_id = '<?=$student_id?>';

				$.ajax({
						url:"./stages/top.stages.php",
						method:"POST",
						data:{student_id:student_id},

						success:function(data){

							$("#five-stages").html(data);
						}
					});

				$.ajax({
						url:"./stages/session.php",
						method:"POST",
						data:{
							student_id: student_id,
							session_id: currentSessionId,
							semester: $('#semesterSelect').val(),
							year: $('#yearSelect').val()
						},

						success:function(data){
							
							$("#session").html(data);
							// Afficher un message de succès
							Toast.success('Session enregistrée avec succès!');
						}
					});

			}).fail(function(){
				submitBtn.prop('disabled', false).text('Enregistrer');
				$('#sessionMessage').text('Erreur lors de l\'enregistrement').attr('class', 'text-red-500');
				Toast.error('Erreur lors de l\'enregistrement de la session');
			});

		});

		var	student_id = '<?=$student_id?>';

		$.ajax({
				url:"./stages/top.stages.php",
				method:"POST",
				data:{student_id:student_id},

				success:function(data){

					$("#five-stages").html(data);
				}
			});

		$.ajax({
				url:"./stages/session.php",
				method:"POST",
				data:{
					student_id: student_id,
					session_id: currentSessionId,
					semester: $('#semesterSelect').val(),
					year: $('#yearSelect').val()
				},

				success:function(data){
					
					$("#session").html(data);
				}
			});


        function updateContent() {
        
            var params = new URLSearchParams(window.location.search);
        
            var stage = params.get('stage');
            
            if (stage == 1 ) {
            	
            	$('.stage_0').css({'display':'block'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
            	$('.stage_5').css({'display':'none'});
            	$('.stage_6').css({'display':'none'});


            	$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');


				var url = './app/generate.stage.php?student_id=<?=$student_id?>&stage=1';

				$.post(url,function(response){});
			

            }else if (stage == 2 ) {
            	
            	$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'block'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
            	$('.stage_5').css({'display':'none'});
            	$('.stage_6').css({'display':'none'});
				
				$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 bg-cyan-500');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');

				
				var url = './app/generate.stage.php?student_id=<?=$student_id?>&stage=2';

				$.post(url,function(response){});

				// Utiliser la variable globale currentSessionId (toujours à jour)
				var session_id = currentSessionId || $("#session_id").text();

				$("input[name='session_id']").attr('value', session_id);

            }else if (stage == 3 ) {
            	
            	$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'block'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
            	$('.stage_5').css({'display':'none'});	
				$('.stage_6').css({'display':'none'});

            	$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 bg-cyan-500');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');

				$('#stageMark_2').attr('class','text-xs p-1');

				// Utiliser la variable globale currentSessionId (toujours à jour)
				var session_id = currentSessionId || $("#session_id").text();
				
				var url = './app/generate.stage.php?student_id=<?=$student_id?>&session_id='+session_id+'&stage=3';

				$.post(url,function(response){});

				// Rafraîchir la liste des cours pour refléter les changements (suppression à l'étape 4)
				$('#contentNewcours').css('opacity', '0.5');
				$.ajax({
					url: '../src/student/new.cours.php',
					method: 'GET',
					cache: false,
					data: {
						student_id: '<?=$student_id?>',
						id: '<?=$id?>',
						session_id: session_id,
						_t: Date.now() // Éviter le cache
					},
					success: function(data) {
						$('#contentNewcours').html(data).css('opacity', '1');
						// Réattacher les événements si nécessaire
						if (typeof bindFormEvents === 'function') {
							bindFormEvents();
						}
					},
					error: function() {
						$('#contentNewcours').css('opacity', '1');
						Toast.error('Erreur lors du rafraîchissement de la liste des cours');
					}
				});

            }else if (stage == 4 ) {
				
				$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
				$('.stage_5').css({'display':'none'});
				$('.stage_6').css({'display':'none'});

            	$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 bg-cyan-500');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');

				$('#upStage').attr('class','px-5 py-2 bg-slate-700 rounded-md toolInactive');
            	
            	$('#stageMark_3').attr('class','text-xs p-1');
            	
            	var url = './app/generate.stage.php?student_id=<?=$student_id?>&stage=4';

				$.post(url,function(response){});
            

            	var student_id = '<?=$student_id?>';
            	// Utiliser la variable globale currentSessionId (toujours à jour)
            	var session_id = currentSessionId || $("#session_id").text();
            	
            	$.ajax({
					url:"./stages/mode.payement.php",
					method:"POST",
					data:{student_id:student_id,session_id:session_id},

					success:function(data){
						$('.stage_3').css({'display':'block'});
						$("#contentModepayement").html(data);
					}
				});

				$('#downStage').click(function(){
					$('#upStage').attr('class','px-5 py-2 bg-cyan-700 rounded-md');
				});

            }else if (stage == 5 ) {
				
				$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'block'});
				$('.stage_5').css({'display':'none'});
				$('.stage_6').css({'display':'none'});

            	$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 bg-cyan-500');	

            	$('#stageMark_4').attr('class','text-xs p-1');
            	
            	// Mettre à jour le lien de la fiche d'inscription avec le bon session_id
            	var linkFiche = $('#linkFicheInscription');
            	var baseUrl = linkFiche.data('base-url');
            	var session_id = currentSessionId || $("#session_id").text();
            	linkFiche.attr('href', baseUrl + '&session_id=' + session_id);
            	
            	var url = './app/generate.stage.php?student_id=<?=$student_id?>&stage=5';

				$.post(url,function(response){});


            }else if (stage == 6 ) {
				
				$('.stage_0').css({'display':'none'});
  				$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
				$('.stage_5').css({'display':'block'});
				$('.stage_6').css({'display':'none'});

            	$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200 ');

				$('#stageMark_5').attr('class','text-xs p-1');
				
				var url = './app/generate.stage.php?student_id=<?=$student_id?>&stage=6';

				$.post(url,function(response){});

            }else if (stage == 7 ) {
            	
            	$('.stage_0').css({'display':'none'});  				
  				$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
				$('.stage_5').css({'display':'none'});           	     		
				$('.stage_6').css({'display':'block'});

            	$('#stageMark_6').attr('class','text-xs p-1');
            	
            	var url = './app/generate.stage.php?student_id=<?=$student_id?>&stage=7';

				$.post(url,function(response){});
            }
            
            //$('#contentMain').html(contentHtml);
            
        }
        
        //updateContent();

		$('#upStage').on('click', function(event) {

            event.preventDefault(); 

            //var student_id = $(this).data('student_id');
            var stage = $(this).data('stage');

            var currentUrl = new URL(window.location.href);
            
       		

           // currentUrl.searchParams.set('student_id', student_id);
            
            if (stage > 0 && stage <= 6) {
            	
            	currentUrl.searchParams.set('stage', stage+1);
            	$(this).data('stage', stage+1);
            	$('#downStage').attr('class', 'px-5 py-2  rounded-md');

            }else if(stage == 7) {

	       		Toast.success('Inscription terminée avec succès! L\'étudiant est maintenant inscrit.');
	       		$(this).attr('class', 'px-5 py-2 bg-slate-700 rounded-md toolInactive');
	       		$('#downStage').attr('class', 'px-5 py-2 bg-slate-700 rounded-md toolInactive');

	       		$('#intInformation').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200  toolInactive');
				$('#intNewcours').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200  toolInactive');
				$('#intModepayement').attr('class','w-full text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200  toolInactive');
				$('#intFicheinscription').attr('class','w-full  text-slate-100 p-2 my-2 rounded-md transition delay-100 duration-200  toolInactive');
           
           	}
            
            window.history.pushState({}, '', currentUrl);
            
            updateContent();
        });
        




        $('#downStage').on('click', function(event) {
            event.preventDefault();
            //var student_id = $(this).data('student_id');
            var stage = $('#upStage').data('stage');
            
            var currentUrl = new URL(window.location.href);
            

            //currentUrl.searchParams.set('student_id', student_id);
           	
           	if (stage > 1) {
           		
           		currentUrl.searchParams.set('stage', stage-1);

            	$('#upStage').data('stage', stage - 1);

           	}else {
           		$(this).attr('class', 'px-5 py-2 bg-slate-700 rounded-md toolInactive');
           	}
            
            window.history.pushState({}, '', currentUrl);
            
            updateContent();
        });

		$('#listOpt1').click(function(){
			$('#notifAffichIMG').css({'display':'block'});
		});
		$('#listOpt2').click(function(){
			$('#notifModifIMG').css({'display':'block'});
		});
		$('#cancelModifIMG').click(function(){
			$('#notifModifIMG').css({'display':'none'});
		});
		$('#cancelAffichIMG').click(function(){
			$('#notifAffichIMG').css({'display':'none'});
		});
		$('#image_student').on('change',function(){
			var image_student = $(this).val();
			if(image_student!="") {
				$('#imgNote').text('Image bien ajouté.');
				$('#btnModify').attr('class','px-2 rounded-md py-1 text-white mx-1 bg-cyan-700');
			}
		});
		$('#linkSupprStd').click(function(){
			$('#notifSupprStd').css({'display':'block'});
		});

	});
</script>
<?php require('../init/toast.php'); ?>
</body>
</html>