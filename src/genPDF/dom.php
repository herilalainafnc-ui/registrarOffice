<?php
	use Dompdf\Dompdf;
	use Dompdf\Options;

	$page = $_GET['page'];
	$ptype = $_GET['ficheInscription'];
	$date = 'heure_'.date($h.'-i-s').' date_'.date('d-m-Y');

	require 'dompdf/vendor/autoload.php';
	require_once 'dompdf/autoload.inc.php';

	ob_start();
		require './print/fiche.inscription.php';
	/*if ($ptype == "ficheInscription") {
		
	}*/
	
	$html = ob_get_contents();
	ob_end_clean();

	$options = new Options();
	$options->set('isRemoteEnabled', TRUE);
	$options->set('chroot', [__DIR__.'/Librairie', __DIR__.'/files']);
	$options->set('defaultFont', 'arial');
	$options->set('isRemoteEnabled', true);

	$dompdf = new Dompdf($options);

	$dompdf->loadHtml($html);

	$dompdf->setPaper('A4','portrait');

	$dompdf->render();
	$dompdf->stream($printName.' '.$date.'.pdf');

	header('location:../student.php?id='.$id.'&page='.$page);
 ?>