<?php 
require ('../../data/backdb.php');

	$student_id = $_GET['student_id'];
	$id = $_GET['id'];
	$as = $_GET['as'];
	$idSupprCours = $_GET['idSupprCours'];
	$id_cours = $_GET['id_cours'];
	$ajout = 0;
	$remove = 1;
	$retrait_date = date('Y-m-d')." ".date('h:i:s');
	$last_change_datetime = date('Y-m-d')." ".date('h:i:s');
	$last_change_user_id = $_GET['user_id'];

	
	$updatenote = $dtb->prepare("UPDATE t_2023_notes SET 
		retrait_date=:retrait_date,
		ajout=:ajout,
		remove=:remove,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime 
		WHERE id=:id");
	
	$updatenote->bindParam(':retrait_date',$retrait_date,PDO::PARAM_STR);
	$updatenote->bindParam(':ajout',$ajout,PDO::PARAM_INT);
	$updatenote->bindParam(':remove',$remove,PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$updatenote->bindParam(':id',$idSupprCours,PDO::PARAM_INT);

	$updatenote->execute();

	$updatCoursFinance = $dtb->prepare("UPDATE t_2024_cours_finance SET 
		remove=:remove,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	
	WHERE cours_id=:id");
	
	$updatCoursFinance->bindParam(':remove',$remove,PDO::PARAM_INT);
	$updatCoursFinance->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updatCoursFinance->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$updatCoursFinance->bindParam(':id',$id_cours,PDO::PARAM_INT);

	$updatCoursFinance->execute();

	$findCoutFromNote = $dtb->query("SELECT * FROM t_2023_cours WHERE id='".$id_cours."'");
	$showCFromNote = $findCoutFromNote->fetch();
	
		
		$vCout = $showCFromNote['cout'];
		$vCout_lab = $showCFromNote['cout_lab'];


	/*$findCoutFromFinance = $dtb->query("SELECT * FROM t_2024_etudiant_finace WHERE student_id='".$student_id."'");
	$showCFromFinance = $findCoutFromFinance->fetch();
	
		$fCout = $showCFromFinance['cout_totalCours'];
		$fCout_lab = $showCFromFinance['cout_totalLab'];

		$restCout = $fCout - $vCout;
		$restCoutLab = $fCout_lab - $vCout_lab;

		$updateReste = $dtb->prepare("UPDATE t_2024_etudiant_finace SET cout_totalCours=:cout_totalCours,cout_totalLab=:cout_totalLab WHERE student_id=:student_id");
		$updateReste->bindParam(':cout_totalCours',$restCout,PDO::PARAM_STR);
		$updateReste->bindParam(':cout_totalLab',$restCoutLab,PDO::PARAM_STR);
		$updateReste->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$updateReste->execute();*/




	header('location:../../src/student.php?id='.$id.'&page=transcript#semestre'.$as);

 ?>