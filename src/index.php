<?php

	session_start();
	
	/*require('../data/connectdb.php');*/
	require('../data/backdb.php');

	/* SESSION LOG */

	/*:::::::::::::::::::::::: SESSION COLORS ::::::::::::::::::::::::*/

	$_SESSION['bg_one_color'] = 'bg-slate-800';
	$_SESSION['bg_two_color'] = 'bg-slate-700';
	$_SESSION['bg_three_color'] = 'bg-slate-600';
	$_SESSION['bg_four_color'] = 'bg-slate-500';
	$_SESSION['bg_five_color'] = 'bg-slate-400';
	$_SESSION['bg_six_color'] = 'bg-slate-300';
	$_SESSION['bg_seven_color'] = 'bg-slate-200';
	$_SESSION['bg_eight_color'] = 'bg-slate-100';

	$_SESSION['txt_one_color'] = 'text-slate-100';
	$_SESSION['txt_two_color'] = 'text-slate-400';
	$_SESSION['txt_three_color'] = 'text-black';

	/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/

	if ((isset($_COOKIE['infinit_pseudo']) and isset($_COOKIE['infinit_password'])) AND (!empty($_COOKIE['infinit_pseudo']) and !empty($_COOKIE['infinit_password']))) {
		$_SESSION['infinit_pseudo'] = $_COOKIE['infinit_pseudo'];
		$_SESSION['infinit_password'] = $_COOKIE['infinit_password'];
				
	}
	
	if( isset($_SESSION['infinit_pseudo']) AND isset($_SESSION['infinit_password']) ) {

		if( !empty($_SESSION['infinit_pseudo']) AND !empty($_SESSION['infinit_password']) ) {

			header('location:./accueil.php');

		}
	}

		if(!empty($_POST)) {

			if (isset($_POST['infinit_pseudo']) and isset($_POST['infinit_password'])) {
				
				$infinit_pseudo = $_POST['infinit_pseudo'];
				$infinit_password = $_POST['infinit_password'];

				if (isset($_POST['infinit_souvenir']) and !empty($_POST['infinit_souvenir'])) {
					setcookie('infinit_pseudo',$user_pseudo,time()+20,null,null,false,true);
					setcookie('infinit_password',$user_password,time()+20,null,null,false,true);
				}

			/*$req = $dtb->query("SELECT * FROM rg_user WHERE user_pseudo='".$infinit_pseudo."' AND user_password='".$infinit_password."' limit 1");*/

			$req = $dtb->query("SELECT * FROM compt_utilisateur WHERE  pseudo='".$infinit_pseudo."' AND password='".$infinit_password."' limit 1");

				if($req->rowCount() > 0){
					
					$req = $req->fetch();
					$_SESSION['infinit_pseudo'] = $infinit_pseudo;
					$_SESSION['infinit_password'] = $infinit_password;

				header('location: ./accueil.php');

				} else {
					header('location: ./index.php');
				}
			}
		}
	
?>
<!DOCTYPE html>
<html>
<head>
	<?php require ('../init/head.php');?>
	<title>Log-in</title>
</head>
<body>

	<div class="main">
		<!-- <a href="#" class="b-white" data-bs-toggle="modal" data-bs-target="#notification-download" style="position: absolute; top: 10px; right: 10px" title="Assurez-vous de télécharger la version application de ce programme pour qu'il devienne indépendant du navigateur.">Télécharger l'app</a> -->
		
		<div style="height: 20%;">
			
		</div>
		<div class="rounded-md p-4" id="windowLog">
			
			<center>
				<img src="../file/logo-coldbloud.png" style="width: 40px; height: 40px;">
				<b style="font-size: 30px; color: #e6e9f0"> Infinit Registrar</b><br>
				<p style="font-size: 17px; color: #e6e9f0">Veuillez connecter pour continuer.</p>
			</center>
			

<form method="post" action="">
				
				<div class="flex mt-10">
					
					<input class="inputLog" type="text" name="infinit_pseudo" placeholder="Utilisateur">
				</div>
				
				<div class="flex mt-4">
					
					<input class="inputLog" id="emp_password" type="password" name="infinit_password" placeholder="Mot de passe">
					
				</div>

				<div style="text-align:right; width: 100%;">
					<i class="eye bi-eye-slash text-slate-100 pointer-events-auto" style="display: block;"><a href="#"> Afficher le mot de passe</a></i>
					<i class="eye bi-eye-fill text-slate-100 pointer-events-auto" style="display: none"><a href="#"> Cacher le mot de passe</a></i>

				</div>
				
				<div class="w-full mt-4">
					<button type="submit" class="text-slate-100 bg-cyan-700 p-2 rounded-md w-full">Connecter</button>	
				</div><br>
				
</form>

		</div>
	</div>

<div class="modal notif-fade" id="notification-download" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered notif-centered">
		<div class="modal-content notif-content">
			<div class="notif-head">
				Téléchargement de l'application RHM
			</div>
			<div class="notif-body">
				
				<div class="styl-details flex" id="macbook">
					<div class="col-lg-4 center">
						<img src="./files/Apple_logo_grey.svg.png" style="width: 100px">
						<b class="b-black pointer-none">Version MacOS</b>
					</div>
					<div class="col-lg-8">
						<div class="flex">
							<img src="./files/logo-coldbloud.png" style="width: 30px; height: 30px;">
							<b class="b-blue pointer-none">&nbsp;&nbsp;RHM</b>	
						</div>
						<div>
							<p class="b-grey pointer-none" style="font-size: 11px"><br>Mode d'installation : Après avoir télécharger le fichier "zip", décompressez-le dans votre dossier <b class="b-blue">Documents</b> ou dans un emplacement sûr. Ouvrir le dossier décompressé et cliquez sur l'application <b class="b-blue">RHM</b>.<br>Vous pouvez garder l'application dans le Dock pour faciliter l'accès au lancement prochain.</p>	
						</div>
					</div>
				</div>
				<div class="styl-details flex" id="windows">
					<div class="col-lg-4 center">
						<img src="./files/Windows_logo_-_2012.png" style="width: 100px">
						<b class="b-black pointer-none">Version Windows</b>
					</div>
					<div class="col-lg-8">
						<div class="flex">
							<img src="./files/logo-coldbloud.png" style="width: 30px; height: 30px;">
							<b class="b-blue pointer-none">&nbsp;&nbsp;RHM</b>	
						</div>
						<div>
							<p class="b-grey pointer-none" style="font-size: 11px"><br>Mode d'installation : Après avoir télécharger le fichier "zip", décompressez-le dans votre dossier <b class="b-blue">Documents</b> ou dans un emplacement sûr. Ouvrir le dossier décompressé et cliquez sur l'application <b class="b-blue">RHM.exe</b>.<br>Vous pouvez épingler l'application sur la barre des tâches ou créez un raccourci sur le bureau pour faciliter l'accès au lancement prochain.</p>	
						</div>
					</div>
					
				</div>

			</div>
			<div class="notif-foot">
				<a href="#" id="annulate" class="btn btn-simple" data-bs-dismiss="modal">Annuler</a>&nbsp;&nbsp;
				<a href="#" id="btn-download" class="btn btn-submit link-disabled" onclick="success()">Télécharger</a>
			</div>
		</div>
	</div>
</div>

</body>
</html>


<script type="text/javascript">
	function success() {
		alert ('Téléchargement effectué.');
	};
	$(document).ready(function(){
		
		$(window).ready(function() {
			$('.user_pseudo').focus();		
		});

		$('#emp_password').keyup(function(){
			var pass = $(this).val();
			
			if(pass != "") {

				$(".bi-eye-slash").click(function(){
					$(this).css("display", "none");
					$(".bi-eye-fill").css("display", "block");
					$('#emp_password').prop('type','text');
				});
				$(".bi-eye-fill").click(function(){
					$(this).css("display", "none");
					$(".bi-eye-slash").css("display", "block");
					$('#emp_password').prop('type','password');
				});

			}else{
				$(".bi-eye-slash").css("display", "block");
				$(".bi-eye-fill").css("display", "none");
				$('#emp_password').prop('type','password');
			}
		});

		$('#windows').click(function() {
			$(this).css({
				'background-color':'#d0ecf8'
			});
			$('#macbook').css({
				'background-color':'#f9f9f9',
				'border':'0px'
			});
			$('#btn-download').css({
			        	'cursor':'pointer',
			        	'pointer-events':'auto',
			        	'text-decoration' : 'none',
			        	'opacity':'1'
	        });
			$('#btn-download').attr('href','./files/RHM-win32-x64.zip');

		});
		$('#macbook').click(function() {
			$(this).css({
				'background-color':'#d0ecf8'
			});
			$('#windows').css({
				'background-color':'#f9f9f9',
				'border':'0px'
			});
			$('#btn-download').css({
			        	'cursor':'pointer',
			        	'pointer-events':'auto',
			        	'text-decoration' : 'none',
			        	'opacity':'1'
	        });
			$('#btn-download').attr('href','./files/RHM-darwin-x64.zip');
		});


	});

</script>
<style type="text/css">
	#windows, #macbook{
		margin-bottom: 12px;
	}
	#windowLog{
		border: 1px solid #1f2633;
		backdrop-filter: blur(50px);
		margin: auto;
		width: 450px;
	}
	.inputLog{
		padding: 1px 4px 1px 4px;
		height: 40px;
		width: 100%;
		border: none;
		border-radius: 8px;
		background-color: #e6e9f0;
	}
	.main{
		background-image: url('./css/téléchargement.jpg');
		background-size: cover;
		background-position: center;
		background-repeat: no-repeat;
		width: 100%;
		height: 100vh;
	}
</style>