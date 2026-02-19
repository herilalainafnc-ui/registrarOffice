<?php 
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require '../../data/backdb.php';
	
	$last_change_user_id = $_GET['rg_id'];
	$id = $_GET['id'];
	$retrait_date = date('Y-m-d');
	$type_retrait = 5;
	$remove = 1;


	$delete = $dtb->prepare("UPDATE tbl_2024_etudiant SET
		type_retrait=:type_retrait,
		remove=:remove,
		retrait_date=:retrait_date,
		last_change_user_id=:last_change_user_id
		WHERE id=:id");
	$delete->bindParam(':type_retrait',$type_retrait,PDO::PARAM_INT);
	$delete->bindParam(':remove',$remove,PDO::PARAM_INT);
	$delete->bindParam(':retrait_date',$retrait_date,PDO::PARAM_STR);
	$delete->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_STR);
	$delete->bindParam(':id',$id,PDO::PARAM_INT);

	$delete->execute();

	header('location:' . $app_base . '/dashboard');

?>