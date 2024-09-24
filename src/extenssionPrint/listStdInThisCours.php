<?php 

	echo $cours_id = $_POST['cours_id'];
	echo $yearForCours = $_POST['yearForCours'];

	$findCours = $dtb->query('SELECT * FROM t_2023_cours WHERE id = "'.$cours_id.'"');

	$showCours = $findCours->fetch();

	echo $showCours['title'];

 ?>