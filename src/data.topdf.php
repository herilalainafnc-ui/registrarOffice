<?php 
	require('../data/session.php');
	/*require('../data/connectdb.php');*/
	require('../data/backdb.php');
	require('../init/head.php');
	$ptype = $_GET['ptype'];
	$h = (date('H')+3);
	$date = 'heure_'.date($h.'-i-s').' date_'.date('d-m-Y');
 ?>

<body class="bg-slate-600 h-screen" style="background-color: #475469;">

	<div class="bg-slate-400 flex h-10 p-1">

		<div class="sm:w-5/12 sm:text-left lg:w-3/12 lg:text-right p-1">
		<a href="./accueil.php" class="px-2 py-1 rounded-md border border-black"><span class="bi-house-door-fill"></span> Accueil</a>
		</div>
		
		<div class="sm:w-2/12 lg:w-6/12 text-center">
			<p class="b-title center"><b><?=$ptype?></b></p>
		</div>
		<div class="sm:w-5/12 sm:text-right lg:text-left lg:w-3/12 py-1">
			
			<a href="#" class="px-2 py-1 rounded-md text-white bg-green-700" id="btnToExcel"><span class="bi-file-earmark-spreadsheet"></span> Excel</a>
			
			<a href="#" onclick="printThisContent()" class="px-2 py-1 rounded-md text-white bg-red-800"><span class="bi-filetype-pdf"></span> Pdf</a>

			<a class="ml-2 text-sm"><b><i class="bi-arrow-repeat"></i></b> portrait</a>

		</div>
	</div>
	<div style="overflow: auto; width: 100%; height: calc(100% - 43px);">

		<div style="border: 1px solid #475460; box-shadow: 0px 0px 20px #475469; padding: 35px;background: white; width: 800px;margin: auto; margin-top: 20px; margin-bottom: 20px;">
			
<!-- ::::::::::::::::::::::::::::: CONTENTS PRINT ::::::::::::::::::::::::::::::::: -->			

	<div id="printThisContent" class="b-black relative" style="min-height: 1020px; width: 100%;">
		
	<?php

		if($ptype != "Badge" AND $ptype != "Abonnement Caf" AND $ptype !="ticketMail") { 
		
			require('../init/.forPrint/top.forPrint.php'); 
		
		}
		
		if($ptype == "Statistique") {
			require ('./extenssionPrint/statistic-sexe.php');
			require ('./extenssionPrint/statistic-religion.php');
			
		}elseif($ptype == "Badge"){
			require ('./extenssionPrint/badge.php');
		}elseif($ptype == "Bulletin"){
			require ('./extenssionPrint/bulletin.php');
		}elseif($ptype == "Transcript"){
			require ('./extenssionPrint/transcript.php');
		}elseif($ptype == "Diplôme"){
			require ('./extenssionPrint/diplome.php');
		}elseif($ptype == "Checklist"){
			require ('./extenssionPrint/cheklist.php');
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
		}


		if($ptype != "Badge" AND $ptype != "Abonnement Caf" AND $ptype != "Worked_point" AND $ptype !="ticketMail") {
		
			require('../init/.forPrint/foot.forPrint.php');

		}
	?>

	</div>

<!-- ::::::::::::::::::::::::::::: --- ::::::::::::::::::::::::::::::::: -->

		</div>
	</div>
</body>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript">
			
var printConge = document.getElementById('printThisContent');

function printThisContent(){

	alert('Download PDF processing !');

	var opt = {
	  margin:       0.40,
	  filename:     '<?=$printName?> <?=$date?>.pdf',
	  image:        { type: 'jpeg', quality: 2 },
	  html2canvas:  { scale: 10 },
	  jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
	};

	// New Promise-based usage:
	html2pdf().set(opt).from(printConge).save();

	// Old monolithic-style usage:
	html2pdf(printConge, opt);
}
</script>
<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script type="text/javascript">
	$(document).ready(function() {
	//::::::::::::::::::::::::::::::::::::::::::::::::
		$('#btnToExcel').click(function() {
			
			alert('Download EXCEL processing !');

			var htmlContent = $('#printThisContent').html();
                
                $.ajax({
                    type: 'POST',
                    url: 'data.topdf.php',
                    data: { html: htmlContent },
                    success: function(response) {
                        
                        var blob = new Blob([response], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
                        
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = '<?=$printName?> <?=$date?>.xlsx';
                        link.click();
                    }
                });
			
		});
	//::::::::::::::::::::::::::::::::::::::::::::::::
	});
</script>

<?php
    require '../data/vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\IOFactoey;
	use PhpOffice\PhpSpreadsheet\Style\Alignment;
	use PhpOffice\PhpSpreadsheet\Style\Fill;

    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Générer le contenu HTML dynamique
    ob_start();
    
    // Inclure ou générer votre contenu dynamique ici
    // Par exemple, include 'votre_fichier_dynamique.php';
    include 'data.topdf.php#printThisContent';
    
    $htmlContent = ob_get_clean();

    // Appeler la fonction pour exporter en Excel
    exportToExcel($htmlContent);
	}

    function exportToExcel($html) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
        $spreadsheet = $reader->loadFromString($html);

        $writer = new Xlsx($spreadsheet);
        $fileName = $printName.' '.$date.'.xlsx';

        // Envoyer les en-têtes pour télécharger le fichier Excel
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
?>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<style type="text/css">
	table tr,table td{
		border-collapse: collapse;
	}
	.tbl{
		border-collapse: collapse;
		font-size: 11px;
	}
	.tbl thead tr th{
		border: 1px solid black;
		padding-left: 4px;
		padding-right: 4px;
		margin: none;
		height: 10px;
	}
	.tbl tbody tr td{
		border: 1px solid black;
		padding-left: 4px;
		padding-right: 4px;
		margin: none;
		height: 10px;
	}
	.tbl tfoot tr th{
		border: 1px solid black;
		padding-left: 4px;
		padding-right: 4px;
		margin: none;
		height: 10px;
	}
	.tbl tfoot tr td{
		border: 1px solid black;
		padding-left: 4px;
		padding-right: 4px;
		margin: none;
		height: 10px;
	}
</style>