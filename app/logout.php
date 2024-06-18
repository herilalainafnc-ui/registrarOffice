<?php
session_start();
setcookie('pseudo','',time()-3600,null,null,false,true);
setcookie('password','',time()-3600,null,null,false,true);

	$_SESSION['infinit_pseudo'] = null;
	$_SESSION['infinit_password'] = null;

header('location:../src/index.php');
?>