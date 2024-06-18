<?php 
	session_start();
	$path = $_SERVER['PHP_SELF'];
	$page = basename($path);

	$infinit_pseudo = $_SESSION['infinit_pseudo'] ;
	$infinit_password = $_SESSION['infinit_password'] ;

	if($infinit_pseudo !='' && $infinit_password !=''){
		/*$rg_utilisateur = $dtb->query("SELECT * FROM rg_user WHERE user_pseudo='".$infinit_pseudo."' AND user_password='".$infinit_password."' limit 1");
		$rg_user = $rg_utilisateur->fetch();
		$rg_name = $rg_user['user_name'];
		$rg_last_name = $rg_user['user_last_name'];
		$rg_userId = $rg_user['id'];
		$rg_photos = $rg_user['user_photos'];*/

		$rg_utilisateur = $dtb->query("SELECT * FROM compt_utilisateur WHERE pseudo='".$infinit_pseudo."' AND password='".$infinit_password."' limit 1");
		$rg_user = $rg_utilisateur->fetch();

		$rg_id = $rg_user['id'];
		$rg_name = $rg_user['nom'];
		$rg_last_name = $rg_user['prenom'];
		$rg_userId = $rg_user['id'];
		$rg_photos = $rg_user['photos'];

	}else{
		header('location:./index.php');
	}

 ?>

<div class="flex w-full h-12 shadow-sm p-1 bg-slate-800 text-slate-100">
	<div class="w-2/12 px-2 mt-2 flex">
		<img src="../file/logo-coldbloud.png" class="w-6 h-6 mx-2">
		<b> Infinit Registrar</b>
	</div>
	<div class="w-7/12 mt-1">
		
		<div class="searchStudent w-full hidden text-right">
			<form method="post" action="accueil.php">
					<input id="std-search" type="text" name="search" autocomplete="off" placeholder="Rechercher un étudiant" class="h-8 px-2 text-sm border bg-slate-700 w-4/12">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
		</div>

	</div>
	<div class="w-1/12">
		
	</div>
	<div class="w-2/12 px-2">
		
		<a href="#" class="flex relative text-right" type="button" role="button" data-bs-toggle="dropdown" aria-expanded="false"><b class="pt-2 absolute right-14"><?=$rg_last_name;?></b>
		<div class="rounded-full bg-slate-100 w-9 h-9 mx-2 absolute top-0.5 right-0 p-0.5">
			<img src="../app/photosuser/<?=$rg_photos?>" class="rounded-full w-full h-full">
		</div></a>

				<ul class="dropdown-menu border bg-slate-300 text-black right-[15px] p-0 rounded-0 text-xs" style="left: 0px; top: 20px">
		            <li><a class="dropdown-item">
		            	<span class="bi-person-badge-fill"></span>&nbsp;&nbsp; <?=$rg_user['privilege'];?></a></li>
		            <li><a href="" class="dropdown-item" role="link" disabled>
		            <span class="bi-gear"></span>&nbsp;&nbsp; Paramètres de compte</a></li>
<?php 		            
if(($rg_user['privilege'] == 'administrator') OR ($rg_user['privilege_2'] == 'administrator') OR ($rg_user['privilege_3'] == 'administrator') OR ($rg_user['privilege_4'] == 'administrator')) {
?>
								<li><a href="../wordpress/wp-login.php" class="dropdown-item" role="link" target="_blank">
		            <span class="bi-wordpress"></span>&nbsp;&nbsp; Wordpress</a></li>
		            <li><a href="../../phpmyadmin/index.php?route=/database/structure&db=registrar_db" class="dropdown-item" role="link" target="_blank">
		            <span class="bi-database-exclamation"></span>&nbsp;&nbsp; Base de donnée MySQL</a></li>
		            <li><a href="https://getbootstrap.com/docs/5.3/getting-started/introduction/" class="dropdown-item" role="link" target="_blank">
		            <span class="bi-bootstrap-fill"></span>&nbsp;&nbsp; Bootstrap Site</a></li>

		            <li><a href="https://tailwindcss.com/docs/installation"  class="dropdown-item" role="link" target="_blank"><i class="bi-filetype-css"></i>&nbsp;&nbsp; Tailwind CSS</a></li>

		            <li><a href="../../rhm/" class="dropdown-item" role="link" target="_blank">
		            <span class="bi-app-indicator"></span>&nbsp;&nbsp; Resource Humaine</a></li>


		            <li><a href="../../uaz-site/" class="dropdown-item" role="link" target="_blank">
		            <span class="bi-award-fill"></span>&nbsp;&nbsp; UAZ SITE</a></li>

		            <li><a href="../../" class="dropdown-item" role="link" target="_blank">
		            <span class="bi-alexa"></span>&nbsp;&nbsp; Registrar</a></li>
		            
<?php
}else{
	echo "";
}
 ?>
		            <li><hr class="dropdown-divider"></li>
		            <li><a class="dropdown-item" href="../app/logout.php"><span class="bi-door-open-fill"></span> Deconnexion</a></li>
          		</ul>
	</div>
				
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#std-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#stdSearch-result').css({'display':'block'});
				$('#all-std').css({'display':'none'});

				$.ajax({
					url:"../init/std-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#stdSearch-result").html(data);
					}
				});

			}else{
				$('#stdSearch-result').css({'display':'none'});
				$('#all-std').css({'display':'block'});
			}
			

		});

	});

</script>