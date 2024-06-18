<?php 
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