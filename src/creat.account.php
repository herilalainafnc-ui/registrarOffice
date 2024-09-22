<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
	
			
				<div class="back" style="height: calc(100vh - 152px);">
					<div class="w-full gap-4 flex p-4">
						
							<div class='w-3/12 m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white'>
								<b class="text-lg"><i class="bi-person-fill-add"></i>&nbsp;&nbsp; Nouveau compte</b><br><br>
								<div class="w-full">
									<?php 		            
if(($rg_user['privilege'] == 'administrator') OR ($rg_user['privilege_2'] == 'administrator') OR ($rg_user['privilege_3'] == 'administrator') OR ($rg_user['privilege_4'] == 'administrator') OR ($rg_user['privilege'] == 'registrar') OR ($rg_user['privilege_2'] == 'registrar') OR ($rg_user['privilege_3'] == 'registrar') OR ($rg_user['privilege_4'] == 'registrar')) {
?>
						<form  method="post" action="../app/.utilisateur/add.user.php" enctype="multipart/form-data">
<?php
}else{
	echo "";
}
 ?>							
 						<div class="w-full gap-3">
 							
 								<div class="w-6/12 my-2">
									<label class="text-sm text-slate-400" for="image_user">Photos</label><br>

									<label for="image_user">
										<div class="bg-slate-400 rounded-md h-20 w-20 text-center py-3">
											<i class="bi-image text-4xl text-black"></i>
										</div>
									</label>
									<input type="file" accept=".jpg, .png" name="photos" id="image_user" class="hidden">
								</div>

								<div class="w-full mb-2">
									<label>Nom</label><br>
									<input type="text" name="nom" class="inscInput h-6 text-sm w-full requierd-1" placeholder="Obligatoire">
								</div>
								<div class="w-full mb-2">
									<label>Prénom</label><br>
									<input type="text" name="prenom" class="inscInput h-6 text-sm w-full requierd-1" placeholder="Obligatoire">
								</div>
								<br><hr><br>
 								<div class="w-full mb-2">
 									<label>Nom d'utilisateur</label><br>
									<input type="text" name="pseudo" class="inscInput h-6 text-sm w-full requierd-1" placeholder="pseudo">
								</div>
								<div class="w-full mb-2">
									<label>Mot de passe</label><br>
									<input type="password" name="password" class="inscInput h-6 text-sm w-full requierd-1" placeholder="Obligatoire">
								</div>
								<div class="w-full mb-2">
									<label>Confirmation</label><br>	
									<input type="password" name="confirmpass" class="inscInput h-6 text-sm w-full requierd-1" placeholder="Confirmez le mot de passe">
								</div>
								<br><hr><br>
								<div class="w-full mb-2 mt-4">
									<label>Status</label><br>
									<select class="inscInput h-6 text-sm w-full requierd-1" name="privilege">
										<option value="administrator">Administrateur</option>
										<option value="registrar">Registraire</option>
										<option value="secretary">Secretaire</option>
										<option value="user">Utilisateur</option>
										<option value="visitor">Visiteur</option>
									</select>
								</div>
								<br><hr><br>
 								<div class="w-full grid grid-cols-2 gap-2 mt-2">
		 							<a href="./settings.php" class="px-5 py-2 bg-slate-500 rounded-md">Annuler</a>
									<button type="submit" class="px-5 py-2 bg-cyan-700 rounded-md">Enregistrer</button>
								</div>
 						</div>	
 						
						</form>
								</div>

							</div>
						

						<div class="w-9/12 m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white overflow-auto" style="height: calc(100vh - 119px);">
							
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
										<th>Etat</th>
									</tr>
								</thead>
								<tbody>
									
	<?php 
		$utilisateur = $dtb->query("SELECT * FROM compt_utilisateur ORDER BY privilege");

	while($user = $utilisateur->fetch()){
	 ?>								
	 								<tr class="h-[50px]">
										<td style="width : 50px"><center><img src="../app/photosuser/<?php echo $user['photos']; ?>" class="smimg"></center></td>
										<td><?php echo $user['nom']; ?></td>
										<td><?php echo $user['prenom']; ?></td>
										<td><?php echo $user['pseudo']; ?></td>
										<td><?php echo $user['privilege']; ?></td>
										<td><?php 
											if($user['etat'] == 1){
												echo "<em style='color:#02d788; font-wheight:bold'><span class='bi-check2-circle'> Activé</em>";
											}else{
												echo "<em style='color:grey; font-wheight:bold'><span class='bi-slash-circle'> Désactivé</em>";
											}
										 ?></td>
									</tr>
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