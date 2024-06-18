<?php 
require ('../data/backdb.php');

echo "<br>".$student_id = $_GET['student_id'];
echo "<br>".$id = $_GET['id'];
echo "<br>".$as = $_GET['as'];
echo "<br>".$idSupprCours = $_GET['idSupprCours'];
$ajout = 1;
$retrait_date = date('Y-m-d')." ".date('h:i:s');
$last_change_datetime = date('Y-m-d')." ".date('h:i:s');
echo "<br>".$last_change_user_id = $_GET['user_id'];


	$updatenote = $dtb->prepare("UPDATE t_2023_notes SET 
		retrait_date=:retrait_date,
		ajout=:ajout,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime 
		WHERE id=:id");
	
	$updatenote->bindParam(':retrait_date',$retrait_date,PDO::PARAM_STR);
	$updatenote->bindParam(':ajout',$ajout,PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$updatenote->bindParam(':id',$idSupprCours,PDO::PARAM_INT);

	$updatenote->execute();

header('location:../src/student.php?id='.$id.'&page=transcript#semestre'.$as);

 ?>