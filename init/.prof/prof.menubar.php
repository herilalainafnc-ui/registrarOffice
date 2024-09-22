<div class="my-1 px-2 mx-0.5 lg:w-4/12 xl:w-3/12 bg-slate-300 overflow-auto" style="height:calc(100vh - 160px);">
						
						<div class="flex my-2 relative">

							<a href="#" data-bs-toggle="dropdown" aria-expanded="false">
							<div class="w-[75px] <?=$bg_one_color?>">
								<?php
								if (!empty($profil['teacher_image'])) {
								
									$imangeLen = strlen($profil['teacher_image']);
									
									if ($profil['teacher_image'] !="" OR $imangeLen >=10) { ?>

									<img src="../app/photosenseignants/<?=$teacher_image?>" class="border-1 border-black w-full">

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
									if(!empty($profil['teacher_image'])) {
										if ($profil['teacher_image'] !="" OR $imangeLen >=10) {
								?>
								<a href="#" id="listOpt1Teacher"><p class="px-2 py-1 hover:bg-cyan-500">Agrandir</p></a>

								<?php
										} 
									}
								?>

								<a href="#" id="listOpt2Teacher"><p class="px-2 py-1 hover:bg-cyan-500">Modifier</p></a>
							</ul>

							<div class="w-9/12 text-left pl-3">
								
								<div class="w-full bg-gradient-to-r from-cyan-500 px-2 text-white">
									<b>
<?=$position?></b>
								</div>
								
								<b class="text-1xl"><?=$uid?></b><br>
								<p><?=$phone?></p>
								<p><a href="https://mail.google.com/mail/u/0/#inbox?compose=<?=$email?>" target="_blank"><?=$email?></a></p>
							</div>
							
						</div>
						<hr>
						<div class="w-full py-2 text-sm">
								<b><?=strtoupper($name) ?> <?=$lastName ?></b><br>
								<b>Réligion : <?=$religion?></b><br>
                                <b>Diplôme : <?=$diplome?></b><br>
                                <em>Sexe : <?php if($sex == 0) {echo "Feminin";}elseif($sex == 1){ echo "Masculin";} ?></em>
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
								
								<a href="?id=<?=$id;?>&page=cours">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "newCours") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-folder-plus"></i>
												Cours
									</div>
								</a>
								<hr>
								<a href="#" class="toolInactive">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md">
										<i class="bi-archive-fill"></i>
												Archive
									</div>
								</a>

								<a href="#" id="prof-suppr">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md">
										
										<i class="bi-trash3"></i>
												Supprimer
									</div>
								</a>

							</div>
						</div>


							<!-- MODIF IMAGE -->

						<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifModifIMGTeacher" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.prof/updateImgProf.php?id=<?=$id?>&user_id=<?=$rg_id?>" enctype="multipart/form-data">
							<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
								<div class="p-2">
									<p>Modifier l'image d'enseignant</p>
								</div>
								<div class="p-2">
									
									<div class="rounded-md <?=$bg_six_color?> h-20 text-center relative active hover:<?=$bg_three_color?> hover:text-white">
										<label for="teacher_image" class="text-lg mt-4"><i class="bi-image"></i></label>
										<p id="imgNoteTeacher">Choisir une image sur votre PC</p>
										<input type="file" accept=".jpg, .png" name="teacher_image" id="teacher_image" class="w-full h-20 absolute z-40 top-0 left-0" style="opacity: 0;">
									</div>
									

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelModifIMGTeacher" class="px-2 <?=$bg_six_color?> rounded-md py-1 mx-1">Annuler</a>
									<button type="submit" class="px-2 rounded-md py-1 text-white mx-1 btnInactive" id="btnModifyTeacher">Modifier</button>
								</div>
							</div>
</form>
						</div>

<!-- AFFICHE IMAGE -->

						<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifAffichIMGTeacher" style="backdrop-filter: blur(30px);">

							<div class="w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
								
								<div class="p-2">
									<div class="w-full h-[400px]" style="background-image: url('../app/photosenseignants/<?=$profil['teacher_image']?>');background-position: center; background-size: cover;background-repeat: no-repeat;">
										
									</div>

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelAffichIMGTeacher" class="px-2 <?=$bg_six_color?> rounded-md py-1 mx-1">Retour</a>
								</div>
							</div>

						</div>








<script type="text/javascript">
	$(document).ready(function() {
		$('#listOpt1Teacher').click(function() {
			$('#notifAffichIMGTeacher').css({'display':'block'});
		});
		$('#listOpt2Teacher').click(function() {
			$('#notifModifIMGTeacher').css({'display':'block'});
		});
		$('#cancelModifIMGTeacher').click(function() {
			$('#notifModifIMGTeacher').css({'display':'none'});
		});
		$('#cancelAffichIMG').click(function() {
			$('#notifAffichIMG').css({'display':'none'});
		});
		$('#teacher_image').on('change',function() {
			var teacher_image = $(this).val();
			alert (teacher_image);
			if(teacher_image !="") {
				$('#imgNoteTeacher').text('Image bien ajouté.');
				$('#btnModifyTeacher').attr('class','px-2 rounded-md py-1 text-white mx-1 bg-cyan-700');
			}
		});
	});
</script>