<?php
try {
	$dtb = new PDO('mysql:host=localhost;dbname=student_db','herilalaina','J8wFF(FOy1KI(nay');
	$dtb -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
/*echo "Connexion correct ♥";*/
}catch(PDOException $e) {
	die(header('location:../data/creatdatabase.php'). $e->getMessage());
}
?>