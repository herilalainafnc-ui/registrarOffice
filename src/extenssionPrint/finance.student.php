<?php

	$yearFinance = $_POST['yearFinance'];
	$semestreFinance = $_POST['semestreFinance'];
	$types = $_POST['types'];
	$level = $_POST['level'];
	
	if (!empty($_POST['new_student'])) {
		$new_student = 1;	
	}else{
		$new_student = 0;
	}

	$printName = "FINANCE_ETUDIANT".$yearFinance."_Sem".$semestreFinance;


	$verifySession = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester ="'.$semestreFinance.'" AND session_year="'.$yearFinance.'"');
	$showSession = $verifySession->fetch();

	echo $session_id = $showSession['session_id'];

	
 ?>