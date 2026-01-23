<?php 
	require('../data/session.php');
	/*require('../data/connectdb.php');*/
	require('../data/backdb.php');
	require('../init/head.noTem.php');
	$ptype = $_GET['ptype'];
	$h = (date('H')+3);
	$date = 'heure_'.date($h.'-i-s').' date_'.date('d-m-Y');

 ?>
<title>Exportation (paysage)</title>

<body class="h-screen" style="background-color:#63748b;">

	<div class="bg-slate-800 flex h-10 p-1">

		<div class="sm:w-5/12 py-1 text-left lg:w-4/12">
        <a href="./" class="flex text-white">
            <img src="../file/logo-coldbloud.png" class="w-6 h-6 mx-3 mx-2">
            <b> Infinit Registrar</b>
        </a>
        </div>
		 <div class="sm:w-2/12 lg:w-4/12 text-center">
            <p class="b-title center"><b><?=$ptype?></b></p>
        </div>
        <div class="sm:w-5/12 text-right lg:w-4/12 pr-4">
            
            <form action="data.toxlsx" method="post" id="exportForm">
                
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
			alert('Succès.');
			var element = document.getElementById('element-to-print');
			var opt = {
			  margin:       0.5,
			  filename:     '<?=$printName?>_<?=$date?>.pdf',
			  image:        { type: 'jpeg', quality: 4 },
			  html2canvas:  { scale: 4 },
			  jsPDF:        { unit: 'in', format: 'a4', orientation: 'paysage' }
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