<?php 
	$path = $_SERVER['PHP_SELF'];
	$page = basename($path);
	
	$infinit_pseudo = $_SESSION['infinit_pseudo'] ;
	$infinit_password = $_SESSION['infinit_password'];


	if($infinit_pseudo !='' && $infinit_password !=''){

		$rg_utilisateur = $dtb->query("SELECT * FROM compt_utilisateur WHERE pseudo='".$infinit_pseudo."' AND password='".$infinit_password."' limit 1");
		$rg_user = $rg_utilisateur->fetch();

		$rg_id = $rg_user['id'];
		$rg_name = $rg_user['nom'];
		$rg_last_name = $rg_user['prenom'];
		$rg_userId = $rg_user['id'];
		$rg_photos = $rg_user['photos'];
		$privilege = $rg_user['privilege'];

		if ($page != 'inscription.php' AND $privilege == 'visitor') {
			header('location:../inscription/inscription.php');
		}

	}else{
		header('location:./index.php');
	}
 ?>

<div class="flex w-full h-12 shadow-sm p-1 <?=$bg_one_color?> <?=$txt_one_color?>">
	
	<div class="sm:w-3/12 lg:w-2/12 px-2 mt-2">
		<a href="../src/" class="flex">
			<img src="../file/logo-coldbloud.png" class="w-6 h-6 mx-3 mx-2">
			<b> Infinit Registrar</b>
		</a>
	</div>
	
	<div class="sm:w-5/12 lg:w-7/12 mt-1">
		
		<div class="search w-full hidden text-right">
			<?php 
			if($page == "accueil.php" OR $page == "student.php") {
			 ?>
			<form method="post" action="accueil.php">
					<input id="std-search" type="text" name="search" placeholder="Search student..." class="h-8 px-2 text-sm border <?=$bg_two_color?> sm:w-full lg:w-4/12">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}elseif($page == "accueil.cours.php" OR $page == "cours.php") {
			?>
			<form method="post" action="accueil.cours.php">
					<input id="cours-search" type="text" name="search" placeholder="Search course..." class="h-8 px-2 text-sm border <?=$bg_two_color?> sm:w-full lg:w-4/12">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}elseif($page == "accueil.prof.php" OR $page == "prof.php") {
			?>
			<form method="post" action="accueil.cours.php">
					<input id="prof-search" type="text" name="search" placeholder="Search teacher..." class="h-8 px-2 text-sm border <?=$bg_two_color?> sm:w-full lg:w-4/12">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}
			?>
		</div>

	</div>
	<div class="sm:w-4/12 lg:w-3/12 flex text-right">
		<div class="w-4/12">
			
		</div>
		<div class="w-1/12">
			<!-- <div class="p-1 text-lg">
				<a href="#" id="light"><span class="bi-sun-fill"></span></a>
				<a href="#" id="dark" class="hidden text-slate-800"><span class="bi-moon-stars-fill"></span></a>
			</div> -->
		</div>
		<div class="px-2 w-7/12 relative text-right">

			<a href="#" class="" data-bs-toggle="dropdown" aria-expanded="false">
				<b class="pt-2 absolute right-14 text-xs"><?=$rg_last_name;?></b>
				<div class="rounded-full <?=$bg_eight_color?> w-9 h-9 mx-2 absolute top-0.5 right-0 p-0.5">
					<img src="../app/photosuser/<?=$rg_photos?>" class="rounded-full w-full h-full">
				</div>		
			</a>
			<ul class="dropdown-menu border bg-slate-300 text-black p-0 rounded-0 text-xs" style="max-height:400px; min-width: 200px; position: absolute; right: 20px;">
		            
		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white toolInactive"><?=$rg_user['privilege'];?></li>
		        
		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="./my.account.php">
		        <span class="bi-gear"></span>&nbsp;&nbsp; Mon compte</a></li>
		<?php 		            
		if(($rg_user['privilege'] == 'administrator') OR ($rg_user['privilege_2'] == 'administrator') OR ($rg_user['privilege_3'] == 'administrator') OR ($rg_user['privilege_4'] == 'administrator')) {
		?>
				<li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="../app/Motors/" target="_blank">
		        <span class="bi-code-slash"></span>&nbsp;&nbsp; Lanceur de code</a></li>

				<li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="../wordpress/wp-login.php" target="_blank">
		        <span class="bi-wordpress"></span>&nbsp;&nbsp; Wordpress</a></li>
		        
		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="../../phpmyadmin/index.php?route=/database/structure&db=registrar_db" target="_blank">
		        <span class="bi-database-exclamation"></span>&nbsp;&nbsp; Base de donnée MySQL</a></li>
		        
		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="https://icons.getbootstrap.com/" target="_blank">
		        <span class="bi-bootstrap-fill"></span>&nbsp;&nbsp; Bootstrap Icon</a></li>

		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="https://tailwindcss.com/docs/installation"  target="_blank"><i class="bi-filetype-css"></i>&nbsp;&nbsp; Tailwind CSS</a></li>

		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="../../rhm/" target="_blank">
		        <span class="bi-app-indicator"></span>&nbsp;&nbsp; Resource Humaine</a></li>


		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="../../uaz-site/" target="_blank">
		        <span class="bi-award-fill"></span>&nbsp;&nbsp; UAZ SITE</a></li>

		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="../../" target="_blank">
		        <span class="bi-alexa"></span>&nbsp;&nbsp; Registrar</a></li>
		        
		<?php
		}else{
		echo "";
		}
		?>
		        <li><hr class="dropdown-divider"></li>
		        <li class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><a href="#" class="logOut"><span class="bi-door-open-fill"></span> Déconnecter</a></li>
			</ul>
		</div>
	</div>
	

	
				
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#std-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#stdTriage-result').css({'display':'none'});
				$('#stdSearch-result').css({'display':'block'});
				$('#all-std').css({'display':'none'});

				$.ajax({
					url:"../init/.student/std-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#stdSearch-result").html(data);
					}
				});

			}else{
				$('#stdTriage-result').css({'display':'none'});
				$('#stdSearch-result').css({'display':'none'});
				$('#all-std').css({'display':'block'});
			}
		});
/*/////////////////////////////////////////////////////////////////*/
		$('#cours-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#coursTriage-result').css({'display':'none'});
				$('#coursSearch-result').css({'display':'block'});
				$('#all-cours').css({'display':'none'});

				$.ajax({
					url:"../init/.cours/cours-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#coursSearch-result").html(data);
					}
				});

			}else{
				$('#coursTriage-result').css({'display':'none'});
				$('#coursSearch-result').css({'display':'none'});
				$('#all-cours').css({'display':'block'});
			}
		});
/*/////////////////////////////////////////////////////////////////*/
		$('#prof-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#profTriage-result').css({'display':'none'});
				$('#profSearch-result').css({'display':'block'});
				$('#all-prof').css({'display':'none'});

				$.ajax({
					url:"../init/.prof/prof-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#profSearch-result").html(data);
					}
				});

			}else{
				$('#profTriage-result').css({'display':'none'});
				$('#profSearch-result').css({'display':'none'});
				$('#all-prof').css({'display':'block'});
			}
		});
/*/////////////////////////////////////////////////////////////////*/
		$('#light').click(function(){
			
			var bg_one_color = '<?=$bg_one_color?>';
			var bg_two_color = '<?=$bg_two_color?>';
			var bg_three_color = '<?=$bg_three_color?>';
			var bg_four_color = '<?=$bg_four_color?>';
			var bg_five_color = '<?=$bg_five_color?>';
			var bg_six_color = '<?=$bg_six_color?>';
			var bg_seven_color = '<?=$bg_seven_color?>';
			var bg_eight_color = '<?=$bg_eight_color?>';
	
			var br_two_color = '<?=$br_two_color?>';
			var br_three_color = '<?=$br_three_color?>';

			var txt_one_color = '<?=$txt_one_color?>';
			var txt_two_color = '<?=$txt_two_color?>';
			var txt_three_color = '<?=$txt_three_color?>';

			$(this).css({'display':'none'});
			$('#dark').css({'display':'block'});

		});

		$('#dark').click(function(){
			
			$(this).css({'display':'none'});
			$('#light').css({'display':'block'});


		});
	});

</script>
