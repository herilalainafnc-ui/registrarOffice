<!DOCTYPE html>
<html>
<head>
	<?php require('../init/head.php');?>
	<link rel="stylesheet" type="text/css" href="../src/css/style.css">
	<title>Inscrition</title>
</head>
<body>
	<!-- TOP BAR --><?php require('../init/topbar.php');?>

	<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
	
	<div class="w-full bg-slate-700 py-6" style="height: calc(100vh - 48px);">

		<div class="xl:w-10/12 sm:w-11/12 sm:rounded-xl p-3 bg-slate-800 mx-auto shadow-sm" style="height: calc(100vh - 110px);">
			<div id="header" class="flex w-full">
				<div class="text-white w-3/12">
					<b>Inscription</b>
					<form method="post" action="#">
					<input id="std-search" type="text" name="student_id" placeholder="Matricule..." class="h-8 px-2 text-sm rounded-md mt-2 <?=$bg_two_color?> lg:w-6/12 sm:w-full">
						<button type="submit" style="display: none" onclick="surligne();"></button>
					</form>

				</div>
				<div class="w-7/12 gap-2 flex text-white">
					
					<span target="_blank" class="text-xs w-2/12 p-1 toolInactive">
						<center>
							<i class="bi-1-circle-fill text-2xl"></i><br>
								Donnée Enrégistrée
						</center>
					</span>
					<span target="_blank" class="text-xs w-2/12 p-1 toolInactive">
						<center>
							<i class="bi-2-circle-fill text-2xl"></i><br>
								Information vérifié
						</center>
					</span>
					<span target="_blank" class="text-xs w-2/12 p-1 toolInactive">
						<center>
							<i class="bi-3-circle-fill text-2xl"></i><br>
								Ajout de cours
						</center>
					</span>
					<span target="_blank" class="text-xs w-2/12 p-1 toolInactive">
						<center>
							<i class="bi-4-circle-fill text-2xl"></i><br>
								Mode de payement
						</center>
					</span>
					<span target="_blank" class="text-xs w-2/12 p-1 toolInactive">
						<center>
							<i class="bi-5-circle-fill text-2xl"></i><br>
								Impression et signature
						</center>
					</span>

				</div>
				<div class="text-white text-right w-2/12">
					<b>Session</b>
					<p class="text-sm text-slate-400">Premier semestre<br>2024 - 2025</p>
				</div>

			</div>
			
			<div id="body" class="w-full flex gap-2">
<?php 

if (isset($_POST['student_id'])) {
		
		$search = $_POST['student_id'];

		$recupsdt = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id LIKE "%'.$search.'%" limit 1'); 

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
		$date_entry = $profil['date_entry'];

 ?>

				<div class="my-3 px-2 mx-0.5 sm:w-4/12 xl:w-3/12 bg-slate-500 rounded-lg overflow-auto" style="height:calc(100vh - 230px);">
						
						<div class="flex my-2 relative">

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
								<a href="#" id="listOpt2"><p class="px-2 py-1 hover:bg-cyan-500">Modifier</p></a>
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
if ($profil['annee_etude']<=3) {
	echo "Licence ".$profil['annee_etude'];
}else{
	echo "Master ".$profil['annee_etude']-3;
}
								?></p>
								<p><a href="https://mail.google.com/mail/u/0/#inbox?compose=<?=$profil['student_email']?>" target="_blank"><?=$profil['student_email']?></a></p>
							</div>
							
						</div>
						<hr>
						<div class="w-full py-2 text-sm">
								<b><?=strtoupper($profil['student_nom']) ?> <?=$profil['student_prenom'] ?></b><br>
								<em><?=$profil['etude_envisage']." - ".$profil['etude_option'] ?></em><br>
								<b>A.U <?=$profil['annee_scolaire']?></b>
								<a></a><br>
						</div><hr>
						<div class="w-full text-md">
								<a href="#" id="btnInformation">
									<div id="intInformation" class="w-full bg-slate-400 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-200">
										<i class="bi-info-square"></i>
												Information
									</div>
								</a>
								
								<a href="#" id="btnNewcours">
									<div id="intNewcours" class="w-full bg-slate-400 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-200">
										
										<i class="bi-folder-plus"></i>
												Cours offert
									</div>
								</a>

								<a href="#" id="btnModepayement">
									<div id="intModepayement" class="w-full bg-slate-400 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-2001">
										
										<i class="bi-folder-plus"></i>
												Mode de payement
									</div>
								</a>

								<a href="#" id="btnModepayement">
									<div id="intModepayement" class="w-full bg-slate-400 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-2001">
										
										<i class="bi-folder-plus"></i>
												Fiche d'inscription 
									</div>
								</a>

							</div>
						</div>


						<div id="contentMain" class="my-3 px-2 sm:w-8/12 xl:w-9/12 border-2 border-slate-700 rounded-lg text-sm text-white" style="height:calc(100vh - 230px);">
							
							<div id="contentInformation" class="hidden">
								<?php require ('../src/student/information.php'); ?>
							</div>
							<div id="contentNewcours" class="hidden">
								<?php require ('../src/student/new.cours.php'); ?>
							</div>

						</div>


							<!-- MODIF IMAGE -->

						<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifModifIMG" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.student/updtateImgStd.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>" enctype="multipart/form-data">
							<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
								<div class="p-2">
									<p>Modifier l'image d'étudiant</p>
								</div>
								<div class="p-2">
									
									<div class="rounded-md <?=$bg_six_color?> h-20 text-center relative active hover:<?=$bg_three_color?> hover:text-white">
										<label for="image_student" class="text-lg mt-4"><i class="bi-image"></i></label>
										<p id="imgNote">Choisir une image sur votre PC</p>
										<input type="file" name="image_student" id="image_student" class="w-full h-20 absolute z-40 top-0 left-0" style="opacity: 0;">
									</div>
									

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelModifIMG" class="px-2 <?=$bg_six_color?> rounded-md py-1 mx-1">Annuler</a>
									<button type="submit" class="px-2 rounded-md py-1 text-white mx-1 btnInactive" id="btnModify">Modifier</button>
								</div>
							</div>
</form>
						</div>

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
	$(document).ready(function(){
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

		$('#btnInformation').click(function(){
			$('#intInformation').attr('class','w-full bg-cyan-500 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-200');
			$('#intNewcours').attr('class','w-full bg-slate-400 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-200');
		    $('#contentInformation').css({'display':'block'});
		    $('#contentNewcours').css({'display':'none'});
		});

		$('#btnNewcours').click(function(){
			$('#intInformation').attr('class','w-full bg-slate-400 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-200');
			$('#intNewcours').attr('class','w-full bg-cyan-500 text-slate-100 p-2 my-2 rounded-md hover:bg-slate-600 transition delay-100 duration-200');
		    $('#contentInformation').css({'display':'none'});
		    $('#contentNewcours').css({'display':'block'});
		});
	});
</script>