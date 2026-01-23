<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php 
	// SÉCURITÉ: Cette page est réservée aux administrateurs et registraires
	requireLevel(ROLE_REGISTRAR, './accueil.php');
	?>
	<title>Utilisateur</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12">
	
			
				<div class="back" style="height: calc(100vh - 152px);">
					<div class="w-full h-23 <?=$txt_one_color?> py-1 flex shadow-md">
						<div class="sm:w-4/12 lg:w-3/12 lg:border-r flex px-1">
		
							<a href="#" id="addUser" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1">
									<center>
									<i class="bi-person-fill-add text-2xl"></i><br>
											Ajouter
									</center>
								
							</a>
						</div>
					</div>
					
					<div class="w-full gap-3 flex p-2">
						
<!-- ----------------------------------------------------------------- -->						
						
						<div class="w-full m-0 p-3 <?=$bg_one_color?> rounded-md border-2 border-slate-800 transition-all text-white overflow-auto" style="height: calc(100vh - 150px);">
							
							<table class="simpleTbl mb-1">
								<thead>
									<tr class="text-center bg-gradient-to-r from-cyan-500">
										<th colspan="10">List des Utilisateurs Infinit</th>
									</tr>
								</thead>
								<thead class="bg-slate-500 <?=$txt_one_color?>">
									<tr>
										<th></th>
										<th>Nom</th>
										<th>Prenom</th>
										<th>Pseudo</th>
										<th>Status</th>
										<th>Position</th>
										<th>Etat</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
									
	<?php 
		$utilisateur = $dtb->query("SELECT * FROM compt_utilisateur ORDER BY level");

	while($user = $utilisateur->fetch()){
		$user_id = $user['id'];
	 ?>								
	 								<tr class="h-[50px] hover:bg-slate-600">
										<td style="width : 30px">
											<?php 
												if (!empty($user['photos'])) {
											?>
											<center>
												<img src="../app/photosuser/<?php echo $user['photos'];?>" style="border-radius: 40px;">
											</center>
											<?php		
												}
											 ?>
											
										</td>
										<td><?php echo $user['nom']; ?></td>
										<td><?php echo $user['prenom']; ?></td>
										<td><?php echo $user['pseudo']; ?></td>
										<td><?php echo $user['privilege']; ?></td>
										<td><?php echo $user['post']; ?></td>
										<td><?php 
											if($user['etat'] == 1){
												echo "<em style='color:#02d788; font-wheight:bold'><span class='bi-check2-circle'> Activé</em>";
											}else{
												echo "<em style='color:grey; font-wheight:bold'><span class='bi-slash-circle'> Désactivé</em>";
											}
										 ?></td>
										 <td class=" hover:<?=$bg_six_color?>">
										 	<a href="#" id="updateUser_<?=$user_id;?>" class="text-[10px] w-4/12 active:bg-cyan-700 p-1">
												<center>
												<i class="bi-pencil-fill"></i>
												</center>
								
											</a>
										 </td>
									</tr>
<script>
	$(document).ready(function(){
		$('#updateUser_<?=$user_id;?>').click(function(){
			$('#bigNotifUpdateUser<?=$user_id;?>').css({'display': 'block'});
		});
		$('#cancelnotifUpdateUser<?=$user_id;?>').click(function(){
			$('#bigNotifUpdateUser<?=$user_id;?>').css({'display': 'none'});
		});
		
		$('#etat<?=$user_id;?>').on('change',function(){

			var etat = $('#etat<?=$user_id;?>').is(':checked');

			if(etat) {
				$('label[for="etat<?=$user_id;?>"]').text('Activé');
			}else {
				$('label[for="etat<?=$user_id;?>"]').text('Désactivé');
			}

		})
	});
</script>

<!-- -------------------------------------------------------------------------------------- -->

<!-- MISE A JOUR D'UTILISATEUR -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 text-black hidden" id="bigNotifUpdateUser<?=$user['id'];?>" style="backdrop-filter: blur(3px);">

		<div class="w-[700px] bg-slate-300 border-2 border-slate-700 mx-auto my-[1%] opacity-100 drop-shadow-2xl">
<?php 
	// Requête sécurisée
	$user = DB::find('compt_utilisateur', $user_id);
 ?>
<form method="post" action="../app/.user/updateUser?rg_id=<?=$rg_id;?>&id=<?=$user_id?>" enctype="multipart/form-data">
			<?= csrf_field() ?>
			<div class="p-2 text-black flex">
				<div class="w-7/12">
					<b>Mettre à jour <?=$user['nom']?> <?=$user['prenom']?>.</b>	
				</div>
				<div class="w-5/12 text-right">
					<p><em class="text-red-500" id="alert-prof"></em></p>	
				</div>
			</div>
			<div class="p-2 flex gap-2">
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
			
			<div class="w-5/12 mb-2">
			  		
						<input id="etat<?=$user_id;?>" type="checkbox" name="etat<?=$user_id?>" <?php if ($user['etat'] == 1) { echo 'checked';} ?>>
						<label for="etat<?=$user_id;?>" style="margin-left: 10px;"><?php if ($user['etat'] == 1) { echo 'Compte activé';}else{ echo 'Compte désactivé';} ?></label>
			  			<br><br>

				<?php 
						if (!empty($user['photos'])) {
				?>
						<label for="photosuser">
							<img src="../app/photosuser/<?=$user['photos'];?>" style="border-radius: 10px; width: 100%;">
						</label>
				<?php		
						}else{
				?>
						<label class="text-sm" for="photosuser">Photos</label><br>
						<label for="photosuser" class="w-full">
							<div class="bg-slate-600 rounded-md w-full text-center py-3">
								<i class="bi-image text-4xl text-white"></i>
							</div>
						</label>
				<?php
						}
				?>
															
						<input type="text" name="oldPhotos<?=$user_id?>" value="<?=$user['photos'];?>" style="display: none;">
						

						<!-- photos à exporter -->
						<input type="file" accept=".jpg, .png, .JPG, .PNG" name="photos<?=$user_id;?>" class="hidden">
						

			  

			</div>

<div class="w-7/12">
	<div class="w-full/12 mb-2">
		<label>Nom</label><br>
		<input class="input w-full requierd-prof-1" type="text" name="nom<?=$user_id?>" value="<?=$user['nom']?>">
	</div>
	<div class="w-full/12 mb-2">
		<label>Prénom</label><br>
		<input class="input w-full" type="text" name="prenom<?=$user_id?>" value="<?=$user['prenom']?>">
	</div>
	<div class="w-full gap-1 mb-2">
		<label>Poste</label><br>
		<input class="input w-full" type="text" name="post<?=$user_id?>" value="<?=$user['post']?>">
	</div>

	<div class="w-full/12 mb-2">
		<label>Adresse mail</label><br>
		<input class="input w-full requierd-prof-2" type="text" name="mail<?=$user_id?>" value="<?=$user['mail']?>">
	</div><br>
	<hr><br>
	<div class="w-full/12 mb-2">
		<label>Privilège</label><br>
		<select class="input w-full" type="number" name="level<?=$user_id?>">
			<option value="1" <?php if($user['level'] == 1 ) { echo 'selected';}?>>Administrateur</option>
			<option value="2" <?php if($user['level'] == 2 ) { echo 'selected';}?>>Registraire</option>
			<option value="3" <?php if($user['level'] == 3 ) { echo 'selected';}?>>Utilisateur</option>
			<option value="4" <?php if($user['level'] == 4 ) { echo 'selected';}?>>Visiteur</option>
		</select>
	</div>
	<div class="w-full/12 mb-2">
		<label>Pseudo</label><br>
		<input class="input w-full requierd-prof-2" type="text" name="pseudo<?=$user_id?>" value="<?=$user['pseudo']?>">
	</div>

	<div class="w-full/12 mb-2">
		<label>Nouveau mot de passe</label><br>
		<input class="input w-full requierd-prof-2" type="password" name="password<?=$user_id?>">
	</div>

</div>
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
			</div>
			<div class="p-3">
				<center>
					<a href="#" id="cancelnotifUpdateUser<?=$user_id;?>" class="bg-slate-400 p-2 rounded-md">Annuler</a>
					<input id="btnUpdateUser<?=$user_id;?>" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Enregistrer">
				</center>
			</div>
</form>
		</div>

	</div>

<!-- -------------------------------------------------------------------------------------- -->
	<?php 
	}
	 ?>
								</tbody>
							</table>



						</div>


					</div>
					
					<?php require('../init/footer.php'); ?>
				</div>


			</div>

		</div>

	</div>

<!-- -------------------------------------------------------------------------------------- -->

<!-- AJOUT D'UTILISATEUR -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 text-black hidden" id="bigNotifAddUser" style="backdrop-filter: blur(3px);">

		<div class="w-[700px] bg-slate-300 border-2 border-slate-700 mx-auto my-[1%] opacity-100 drop-shadow-2xl">

<form id="formToAddUser" method="post" action="../app/.user/add.user?id=<?=$rg_id?>" enctype="multipart/form-data">
			<?= csrf_field() ?>
			<div class="p-2 text-black flex">
				<div class="w-7/12">
					<b>Ajouter un utilisateur.</b>	
				</div>
				<div class="w-5/12 text-right">
					<p><em class="text-red-500" id="alert-prof"></em></p>	
				</div>
			</div>
			<div class="p-2 flex gap-2">
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
			
			<div class="w-5/12 mb-2">
				
						<label class="text-sm" for="photosuser">Photos</label><br>
						<label for="photosuser" class="w-full">
							<div class="bg-slate-600 rounded-md w-full text-center py-3">
								<i class="bi-image text-4xl text-white"></i>
							</div>
						</label>
															
						
						<input type="file" accept=".jpg, .png" name="photos" id="photosuser" class="hidden">
						
			</div>

<div class="w-7/12">
	<div class="w-full/12 mb-2">
		<label>Nom</label><br>
		<input id="nom" class="input w-full requierd-prof-1" type="text" name="nom">
	</div>
	<div class="w-full/12 mb-2">
		<label>Prénom</label><br>
		<input id="prenom" class="input w-full" type="text" name="prenom">
	</div>
	<div class="w-full gap-1 mb-2">
		<label>Poste</label><br>
		<input id="post" class="input w-full" type="text" name="post">
	</div>

	<div class="w-full/12 mb-2">
		<label>Adresse mail</label><br>
		<input id="mail" class="input w-full requierd-prof-2" type="text" name="mail">
	</div><br>
	<hr><br>
	<div class="w-full/12 mb-2">
		<label>Privilège</label><br>
		<select class="input w-full" type="number" name="level">
			<option value="1">Administrateur</option>
			<option value="2">Registraire</option>
			<option value="3" selected>Utilisateur</option>
			<option value="4">Visiteur</option>
		</select>
	</div>
	<div class="w-full/12 mb-2">
		<label>Pseudo</label><br>
		<input id="pseudo" class="input w-full requierd-prof-2" type="text" name="pseudo">
	</div>

	<div class="w-full/12 mb-2">
		<label>Nouveau mot de passe</label><br>
		<input id="password" class="input w-full requierd-prof-2" type="password" name="password">
	</div>
	<div class="w-full mb-2">
	<label>Confirmation</label><br>	
		<input id="confirmpass" type="password" name="confirmpass" class="input w-full requierd-prof-2">
	</div>

</div>
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
			</div>
			<div class="p-3">
				<center>
					<a href="#" id="cancelnotifAddUser" class="bg-slate-400 p-2 rounded-md">Annuler</a>
					<input id="btnAddUser" type="submit" class="bg-slate-400 p-2 rounded-md text-black mx-1 toolInactive" value="Enregistrer">
				</center>
			</div>
</form>
		</div>

	</div>

<!-- -------------------------------------------------------------------------------------- -->


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

<script>
	$(document).ready(function(){
		$('#addUser').click(function(){
			$('#bigNotifAddUser').css({'display': 'block'});
		});
		$('#cancelnotifAddUser').click(function(){
			$('#bigNotifAddUser').css({'display': 'none'});
		});
		
		$('input[name="password"]').on('input', function(){
			
			var password = $('#password').val();
			var confirmpass = $('#confirmpass').val();
			
if ($('#nom').val() != "" && $('#prenom').val() != "" && $('#pseudo').val() != "") {			
			
				if (password == confirmpass) {
					$('#btnAddUser').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
					$('input[name="confirmpass"]').attr('class','input w-full requierd-prof-2 border-1 bg-green-200');
					$('input[name="password"]').attr('class','input w-full requierd-prof-2 border-1 bg-green-200');	
				}else if(password =! confirmpass) {
					$('input[name="password"]').attr('class','input w-full requierd-prof-2 border-1 bg-red-200');
					$('input[name="confirmpass"]').attr('class','input w-full requierd-prof-2 border-1 bg-red-200');
				}else if(password == ''){
					$('input[name="password"]').attr('class','input w-full requierd-prof-2 border-1');
				}
}						
		});

		$('input[name="confirmpass"]').on('input',function(){
			var password = $('#password').val();
			var confirmpass = $('#confirmpass').val();
			
if ($('#nom').val() != "" && $('#prenom').val() != "" && $('#pseudo').val() != "") {			
			
				if (password == confirmpass) {
					$('#btnAddUser').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
					$('input[name="confirmpass"]').attr('class','input w-full requierd-prof-2 border-1 bg-green-200');
					$('input[name="password"]').attr('class','input w-full requierd-prof-2 border-1 bg-green-200');
				}else if(password =! confirmpass) {
					$('input[name="password"]').attr('class','input w-full requierd-prof-2 border-1 bg-red-200');
					$('input[name="confirmpass"]').attr('class','input w-full requierd-prof-2 border-1 bg-red-200');				
			
				}else if(confirmpass == ''){
					$('input[name="confirmpass"]').attr('class','input w-full requierd-prof-2 border-1');
				}
}						
		});
		

	});

</script>


