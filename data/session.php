<?php 
	// Durée de vie de la session : 10 heures
	ini_set('session.gc_maxlifetime', 36000);
	ini_set('session.cookie_lifetime', 36000);
	session_start();

	if(isset($_SESSION['infinit_pseudo']) AND isset($_SESSION['infinit_password'])) {

		if(!empty($_SESSION['infinit_pseudo']) AND !empty($_SESSION['infinit_password'])) {

			$infinit_pseudo = $_SESSION['infinit_pseudo'];
			$infinit_password = $_SESSION['infinit_password'];

		}
	}elseif(empty($infinit_pseudo) AND empty($infinit_password)){

			header('location:./index.php');
	}

 ?>