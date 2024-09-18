<?php
try {
	$dtb = new PDO('mysql:host=localhost;dbname=registrar_db','herilalaina','J8wFF(FOy1KI(nay');
	//$dtb = new PDO('mysql:host=localhost;dbname=registrar_db','root','');
	$dtb -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
/*echo "Connexion correct ♥";*/
}catch(PDOException $e) {
	echo "Connexion DB incorrect ☻ : " . $e->getMessage();
	exit();
}
?>