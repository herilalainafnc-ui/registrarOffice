<?php
use Dompdf\Dompdf;
use Dompdf\Options;
require 'dompdf/vendor/autoload.php';
require_once 'dompdf/autoload.inc.php';

ob_start();
require '../extenssionPrint/tamplate_mail_csv.php';
$html = ob_get_contents();
ob_end_clean();

$options = new Options();
$options->set('isRemoteEnabled', TRUE);
$options->set('chroot', [__DIR__.'/Librairie', __DIR__.'/files']);
$options->set('defaultFont', 'arial');

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A1','paysage');
$dompdf->render();
$dompdf->stream('user_'.$types.'_'.date('Y').' - '.(date('Y')+1).'.pdf');
 ?>