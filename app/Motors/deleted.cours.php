<?php 
	/*NEW BASE */ require('../../data/backdb.php');

	$delete = $dtb->prepare('DELETE FROM t_2023_notes WHERE transfert = 1 AND origine_transfert ="OLD"');

	$delete->execute();
	header('location: ./end.php');

 ?>