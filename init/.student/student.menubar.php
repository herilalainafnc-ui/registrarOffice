<div class="my-1 px-2 mx-0.5 lg:w-4/12 xl:w-3/12 bg-slate-300 overflow-auto" style="height:calc(100vh - 160px);">
						
						<div class="flex my-2 relative">

							<a href="#" data-bs-toggle="dropdown" aria-expanded="false">
							<div class="w-[75px] <?=$bg_one_color?>">
								<?php
								
								$extentionImage = substr($profil['image_student'], -4);

								if ($extentionImage == '.jpg' OR $extentionImage == '.JPG') {
								
								?>
									<img src="../app/photosetudiants/<?=$profil['image_student']?>" class="border-1 border-black w-full">

								<?php	
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
						<div class="w-full py-2 text-sm">
								<b><?=strtoupper($profil['student_nom']) ?> <?=$profil['student_prenom'] ?></b><br>
								<em><?=$profil['etude_envisage']." - ".$profil['etude_option'] ?></em><br>
								<b>Année <?=$profil['annee_scolaire']?></b><br>
								<p class="text-[11px] text-green-600" style="line-height: 12px;">Modifié par <?php 
								$user_modif_id  = $profil['last_change_user_id'];
$findUser = $dtb->query('SELECT * FROM compt_utilisateur WHERE id = "'.$user_modif_id.'"');
$showUser = $findUser->fetch();
if (!empty($showUser)) {
	echo "<b>[".$showUser['prenom']."]</b><br>".$profil['last_change_datetime'];	
}
								 ?></p>
						</div><hr>
						<div class="w-full text-md">
								<a href="?id=<?=$id;?>&page=information">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "information") {
	echo "bg-cyan-700 text-white";
} ?>">
										<i class="bi-info-square"></i>
												Information
									</div>
								</a>
		
								<a href="?id=<?=$id;?>&page=transcriptSS">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "transcriptSS") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-newspaper"></i>
												Transcript
									</div>
								</a>
								
								<a href="?id=<?=$id;?>&page=newCours">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "newCours") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-folder-plus"></i>
												Ajout de cours
									</div>
								</a>
		
								<a href="?id=<?=$id;?>&page=bulletin">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "bulletin") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-journal-album"></i>
												Bulletin
									</div>
								</a>
								<hr>
								<a href="?id=<?=$id;?>&page=diplome&langue=FR">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "diplome") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-box-seam-fill"></i>
												Diplôme
									</div>
								</a>
								<hr>
								<a href="#" class="toolInactive">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md">
										<i class="bi-archive-fill"></i>
												Archive
									</div>
								</a>

								<a href="#" id="linkSupprStd">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 text-red-600 rounded-md">
										
										<i class="bi-trash3"></i>
												Supprimer
									</div>
								</a>

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
										<input type="file" accept=".jpg, .png" name="image_student" id="image_student" class="w-full h-20 absolute z-40 top-0 left-0" style="opacity: 0;">
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
	});
</script>