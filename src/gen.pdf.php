<?php
use Dompdf\Dompdf;
use Dompdf\Options;
require 'dompdf/vendor/autoload.php';
require 'dompdf/autoload.inc.php';
require '../data/backdb.php';
$ptype = $_GET['ptype'];
ob_start();

		if($ptype == "Statistique") {
        
            $printName = "STATISTIQUE";
            require('../init/.forPrint/top.forPrint.php');

            echo "<br>";
            require ('./extenssionPrint/statistic-general.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-internat.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-abonment.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-sexe.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-religion.php');
            echo "<br>";
 
            require('../init/.forPrint/foot.forPrint.php');

            
        }elseif($ptype == "Badge"){
            require ('./extenssionPrint/badge.php');

        }elseif($ptype == "BadgeGr"){
            require ('./extenssionPrint/badge.gr.php');
            
        }elseif($ptype == "Bulletin"){
            require ('./extenssionPrint/bulletin.php');

        }elseif($ptype == "Transcript"){
            require ('./extenssionPrint/transcript.php');

        }elseif($ptype == "Diplôme"){
            require ('./extenssionPrint/diplome.php');

        }elseif($ptype == "Checklist"){
            require ('./extenssionPrint/vrai.cheklist.php');

        }elseif($ptype == "Certificat de scolarité"){
            require ('./extenssionPrint/certScolarity.php');

        }elseif($ptype == "Abonnement Caf"){
            require ('./extenssionPrint/abonnement.caf.php');

        }elseif($ptype == "TranscriptSS"){
            require ('./extenssionPrint/transcriptSS.php');

        }elseif($ptype == "Worked_point"){
            require ('./extenssionPrint/worked_point.php');

        }elseif($ptype == "listeStd"){
            require ('./extenssionPrint/std.list.php');

        }elseif($ptype == "listeCours"){
            require ('./extenssionPrint/cours.list.php');

        }elseif($ptype == "ticketMail"){
            require ('./extenssionPrint/ticket.mail.php');

        }elseif($ptype == "workedSlip"){
            require ('./extenssionPrint/worked.Slip.php');

        }elseif($ptype == "worked"){
            require ('./extenssionPrint/worked.php');

        }elseif($ptype == "Fiche_inscription"){
            require ('./extenssionPrint/fiche_inscription.php');

        }elseif($ptype == "ListStdInThisCours"){
            require ('./extenssionPrint/listStdInThisCours.php');

        }elseif($ptype == "RemiseNotes"){
            require ('./extenssionPrint/remiseNotes.php');

        }

	$html = ob_get_contents();

ob_end_clean();

$options = new Options();
$options->set('isRemoteEnabled', TRUE);

$options->set('chroot', [realpath(__DIR__.'/../file'), realpath(__DIR__.'/../app/photosetudiants'), realpath(__DIR__.'/../app/photosenseignants'), realpath(__DIR__.'/../app/photosuser'), realpath(__DIR__.'/css/style.css')]);


$options->set('defaultFont', 'arial');

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A4','portrait');
$dompdf->render();
$dompdf->stream($printName.'-text_'.date('Y').' - '.(date('Y')+1).'.pdf');

?>