<div class="my-1 px-2 mx-0.5 lg:w-4/12 xl:w-3/12 bg-slate-300 overflow-auto" style="height:calc(100vh - 160px);">
						
						<div class="flex my-2 relative">

							<div class="w-28 bg-slate-800" id="imgStd">
								<img src="../app/photosendrignants/<?=$teacher_image?>" class="border-1 border-black w-full">	
							</div>

							<div class="absolute border bg-slate-500 text-white bottom-[-15px] left-[60px] hidden" id="imgOpt" style="z-index: 9;">
								<a href="#" id="listOpt1"><p class="px-2 py-1 hover:bg-cyan-500">Agrandir</p></a>
								<a href="#" id="listOpt2"><p class="px-2 py-1 hover:bg-cyan-500">Modifier</p></a>
							</div>

							<div class="w-9/12 text-left pl-3">
								
								<div class="w-full bg-gradient-to-r from-cyan-500 px-2 text-white">
									<b>
<?=$position?></b>
								</div>
								
								<b class="text-1xl"><?=$uid?></b><br>
								<p><?=$phone?></p><br>
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
												Informations
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
								<a href="#">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md">
										<i class="bi-archive-fill"></i>
												Archive
									</div>
								</a>

								<a href="#">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md">
										
										<i class="bi-trash3"></i>
												Supprimer
									</div>
								</a>

							</div>
						</div>


							<!-- MODIF IMAGE -->

						<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifModifIMG" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/updtadeImgStd.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>" enctype="multipart/form-data" class="form-no-refrech">
							<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
								<div class="p-2">
									<p>Modifier l'image</p>
								</div>
								<div class="p-2">
									
									<div class="rounded-md bg-slate-300 h-20 text-center relative active hover:bg-slate-600 hover:text-white">
										<label for="image_student" class="text-lg mt-4"><i class="bi-image"></i></label>
										<p>Choisir une image sur votre PC</p>
										<input type="file" name="image_student" id="image_student" class="w-full h-20 absolute z-40 top-0 left-0" style="opacity: 0;">
									</div>
									

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelModifIMG" class="px-2 bg-slate-300 rounded-md py-1 mx-1">Annuler</a>
									<button type="submit" class="px-2 rounded-md py-1 text-white mx-1 btnInactive" id="btnModify">Modifier</button>
								</div>
							</div>
</form>
						</div>

<!-- AFFICHE IMAGE -->

						<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifAffichIMG" style="backdrop-filter: blur(30px);">

							<div class="w-3/12 bg-slate-100 border-2 border-slate-700 mx-auto my-[12%] opacity-100 drop-shadow-2xl">
								
								<div class="p-2">
									<div class="w-full h-[400px]" style="background-image: url('../app/photosetudiants/<?=$profil['image_student']?>');background-position: center; background-size: cover;background-repeat: no-repeat;">
										
									</div>

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelAffichIMG" class="px-2 bg-slate-300 rounded-md py-1 mx-1">Retour</a>
								</div>
							</div>

						</div>








<script type="text/javascript">
	$(document).ready(function(){
		$('#imgStd').click(function(){
			$('#imgOpt').css({'display':'block'});
		});
		$('#listOpt1').click(function(){
			$('#imgOpt').css({'display':'none'});
			$('#notifAffichIMG').css({'display':'block'});
		});
		$('#listOpt2').click(function(){
			$('#imgOpt').css({'display':'none'});
			$('#notifModifIMG').css({'display':'block'});
		});
		$('#cancelModifIMG').click(function(){
			$('#notifModifIMG').css({'display':'none'});
		});
		$('#cancelAffichIMG').click(function(){
			$('#notifAffichIMG').css({'display':'none'});
		});
		$('#image_student').on('change',function(){
			image_student = $(this).val();
			if(image_student!="") {
				$('#btnModify').attr('class','px-2 rounded-md py-1 text-white mx-1 bg-cyan-700');
			}
		});
	});
</script>