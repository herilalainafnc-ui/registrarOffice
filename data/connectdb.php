<?php
try {
	$dtb = new PDO('mysql:host=localhost;dbname=student_db','herilalaina','J8wFF(FOy1KI(nay');
	$dtb -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
/*echo "Connexion correct ♥";*/

	// Lever automatiquement les suspensions expirées
	$dtb->exec("UPDATE tbl_2024_etudiant SET suspended = 0 WHERE suspended = 1 AND date_fin_suspension IS NOT NULL AND date_fin_suspension < CURDATE()");

}catch(PDOException $e) {
	die(header('location:../data/creatdatabase.php'). $e->getMessage());
}
?>