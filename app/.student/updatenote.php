<?php 
	require('../../data/backdb.php');

	$id = $_GET['id'];
	$as = $_GET['as'];
	$nbr = $_GET['nbr'];
	$note_id = $_GET['note_id'];
	 

	if ($_POST['nb_crd'.$nbr] == "ok" OR $_POST['nb_crd'.$nbr] == "Ok" OR  $_POST['nb_crd'.$nbr] == "OK") {
		$note = -2;	
	}else{
		$note = $_POST['nb_crd'.$nbr];
		//str_replace(',', '.', $_POST['note']);
	}
	
	$last_change_user_id = $_GET['user_id'];
	$date = date('Y-m-d');


	$updateNote = $dtb->prepare('UPDATE t_2023_notes SET grade=:note,last_change_user_id=:last_change_user_id,last_change_datetime=:last_change_datetime WHERE id=:note_id');

	$updateNote->bindParam(':note',$note,PDO::PARAM_STR);
	$updateNote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updateNote->bindParam(':last_change_datetime',$date,PDO::PARAM_STR);
	$updateNote->bindParam(':note_id',$note_id,PDO::PARAM_INT);

	$updateNote->execute();

	header('location:../../src/student.php?id='.$id.'&page=transcriptSS#semestre'.$as);
 ?>