<?php 
	require('../data/session.php');
	/*require('../data/connectdb.php');*/
	require('../data/backdb.php');
	require('../init/head.noTem.php');

	// Pages d'export (sortie destinée à l'impression / PDF) : on masque les
	// avertissements "clé de tableau indéfinie" (PHP 8+) qui polluent le rendu
	// quand un champ POST est absent (accès direct en GET, rechargement...).
	error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

	$ptype = $_GET['ptype'] ?? '';

	// Un export a besoin de paramètres : formulaire POST ("en masse") ou
	// paramètres GET (liens individuels). On ne bloque que les accès réellement
	// vides (aucune donnée hormis ptype/export_format), qui ne génèrent rien.
	$getParams = $_GET;
	unset($getParams['ptype'], $getParams['export_format']);
	if (empty($_POST) && empty($getParams)) {
		echo '<div style="max-width:520px;margin:12vh auto;padding:28px;border-radius:14px;background:#0f172a;border:1px solid #334155;color:#e2e8f0;font-family:Inter,Arial,sans-serif;text-align:center;box-shadow:0 24px 60px -12px rgba(0,0,0,.5);">'
			. '<div style="font-size:42px;margin-bottom:10px;">&#128196;</div>'
			. '<h2 style="margin:0 0 8px;font-size:18px;font-weight:600;">Export indisponible en acc&egrave;s direct</h2>'
			. '<p style="color:#94a3b8;font-size:14px;line-height:1.5;margin:0 0 20px;">Cet export doit &ecirc;tre lanc&eacute; depuis son bouton dans l\'application&nbsp;: les param&egrave;tres sont transmis par le formulaire.</p>'
			. '<a href="' . $app_base . '/tableau-de-bord" style="display:inline-block;padding:9px 20px;border-radius:8px;background:#0891b2;color:#fff;text-decoration:none;font-size:14px;font-weight:500;">Retour au tableau de bord</a>'
			. '</div>';
		exit;
	}
	$h = (date('H')+3);
	$date = 'heure_'.date($h.'-i-s').' date_'.date('d-m-Y');

 ?>
<?php $ongletTitre = ["foplist" => "Liste pour la FOP", "mesupres" => "Données MESUPRES", "Finance" => "Finance"][$ptype] ?? "Exportation"; ?>
<title><?= htmlspecialchars($ongletTitre, ENT_QUOTES, "UTF-8") ?></title>

<body class="h-screen" style="background-color:#63748b;">

	<div class="bg-slate-800 flex h-10 p-1">

		<div class="sm:w-5/12 py-1 text-left lg:w-4/12">
        <a href="<?=$app_base?>/tableau-de-bord" class="flex text-white">
            <img src="../file/logo-coldbloud.png" class="w-6 h-6 mx-3 mx-2">
            <b> Infinit Registrar</b>
        </a>
        </div>
		 <div class="sm:w-2/12 lg:w-4/12 text-center">
            <p class="b-title center"><b><?=$ptype?></b></p>
        </div>
        <div class="sm:w-5/12 text-right lg:w-4/12 pr-4">
            
            <form action="<?=$app_base?>/src/data.toxlsx" method="post" id="exportForm">
                
                <input type="hidden" name="htmlContent" id="htmlContent" value="">
                
                <button type="submit" class="px-2 py-1 rounded-md text-white bg-green-700 opacity-0" id="btnToExcel"><span class="bi-file-earmark-spreadsheet"></span> Excel</button>
                
                <!-- <a href="./gen.pdf.php?ptype=<?=$ptype?>" target="_blank" class="px-2 py-[5.5px] rounded-md text-white bg-red-800"><span class="bi-filetype-pdf"></span><i class="text-[10px]">(Text)</i>.Pdf</a> -->

                <a href="#" onclick="printThisContent()" class="px-2 py-[5.5px] rounded-md text-white bg-blue-800"><span class="bi-file-earmark-image-fill"></span><i class="text-[10px]">(Image)</i>.Pdf</a>
                
                <!-- <a class="ml-2 text-sm"><b><i class="bi-arrow-repeat"></i></b> portrait</a> -->
            </form>
            
        </div>
	</div>
<div style="overflow: auto; width: 100%; height: calc(100% - 43px);">

	<div style="border: 1px solid #475460; box-shadow: 0px 0px 20px #475469; padding: 35px; background: white; width: 1020px; margin: auto; margin-top: 20px; margin-bottom: 20px;">
			
<!-- ::::::::::::::::::::::::::::: CONTENTS PRINT ::::::::::::::::::::::::::::::::: -->

		<div id="printThisContent" class="b-black relative" style="min-height: 800px; width: 100%;">
				
			<?php
				
				if($ptype == "foplist") {
					require ('./extenssionPrint/fop.list.php');
				}elseif($ptype == "mesupres") {
					require ('./extenssionPrint/mesupres.php');
				}elseif($ptype == "Finance") {
					require ('./extenssionPrint/finance.student.php');
				}

			?>

		</div>

<!-- ::::::::::::::::::::::::::::: --- ::::::::::::::::::::::::::::::::: -->

	</div>
</div>
</body>


<!-- ------------------------------------------------------------------------------------------------- -->

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

			<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script type="text/javascript">
			
			var printConge = document.getElementById('printThisContent');

function printThisContent(){
			if (window.Toast) { Toast.info('Génération du PDF en cours…'); }
			var element = document.getElementById('element-to-print');
			var opt = {
			  margin:       0.5,
			  filename:     '<?=$printName?>_<?=$date?>.pdf',
			  image:        { type: 'jpeg', quality: 4 },
			  html2canvas:  { scale: 4 },
              jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
			};

			// New Promise-based usage:
			html2pdf().set(opt).from(printConge).save();
}
</script>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script type="text/javascript">
	$(document).ready(function() {
	//::::::::::::::::::::::::::::::::::::::::::::::::
		$('#btnToExcel').click(function() {
			
			if (window.Toast) { Toast.info('Export Excel en cours…'); }

			var htmlContent = $('#printThisContent').html();
                
                $.ajax({
                    type: 'POST',
                    url: '',
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
/*    require '../data/vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\IOFactoey;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['html'])) {
        
        $html = $_POST['html'];
        exportToExcel($html);
    }

    function exportToExcel($html) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
        $spreadsheet = $reader->loadFromString($html);

        $writer = new Xlsx($spreadsheet);
        $fileName = $printName.'_'.$date.'.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }*/
?>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
<?php require('../init/toast.php'); ?>
