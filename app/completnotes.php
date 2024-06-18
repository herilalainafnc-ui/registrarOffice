<?php 
	require ('../data/backdb.php');
	
	$repid = $_POST['repid'];
	$id = $_POST['id'];
	$grade = $_POST['note'];
	$date_entry = date('Y-m-d');

	$update = $dtb->prepare("UPDATE t_2023_notes SET grade=:grade WHERE id=:id");
	$update->bindParam(':grade',$grade,PDO::PARAM_STR);
	$update->bindParam(':id',$id,PDO::PARAM_INT);
	$update->execute();

	//header('location:../internotes.php?id='.$repid);

 ?>