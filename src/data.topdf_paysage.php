<?php 
	require('../data/session.php');
	/*require('../data/connectdb.php');*/
	require('../data/backdb.php');
	require('../init/head.php');
	$ptype = $_GET['ptype'];
	$date = date('Y-m-d');

 ?>

<body class="bg-slate-600 h-screen" style="background-color: #475469;">

	<div class="bg-slate-400 flex h-10 p-1">

		<div class="sm:w-5/12 sm:text-left lg:w-3/12 lg:text-right p-1">
		<a href="./accueil.php" class="px-2 py-1 rounded-md border border-black"><span class="bi-arrow-return-left"></span> Retour</a>
		</div>
		
		<div class="sm:w-2/12 lg:w-6/12 text-center">
			<p class="b-title center"><b><?=$ptype?></b></p>
		</div>
		<div class="sm:w-5/12 sm:text-right lg:text-left lg:w-3/12">
			<button type="button" onclick="printThisContent()" class="px-2 py-1 rounded-md text-white bg-slate-900"><span class="bi-download"></span> Version PDF</button>
			<!-- <button type="button" onclick="printThisContentExcel()" class="px-2 py-1 rounded-md text-white bg-green-700"><span class="bi-download"></span> Version EXCEL</button> -->
		</div>
	</div>
	<div style="overflow: auto; width: 100%; height: calc(100% - 43px);">

		<div style="border: 1px solid #475460; box-shadow: 0px 0px 20px #475469; padding: 35px;background: white; width: 800px;margin: auto; margin-top: 20px; margin-bottom: 20px;">
			
			
			<div id="printThisContent" class="b-black relative" style="min-height: 1020px; width: 100%;">
				
				<?php if($ptype != "Badge" AND $ptype != "Abonnement Caf") { require('../init/top.forPrint.php'); }
				
				if($ptype == "Statistique") {
					require ('./extenssionPrint/statistic-religion.php');
					require ('./extenssionPrint/statistic-sexe.php');
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
				}

			if($ptype != "Badge" AND $ptype != "Abonnement Caf" AND $ptype != "Worked_point") {
			
				?>
				
				<div class="bottom-0 w-full text-xs mt-10">
					<div class="text-sm">
						<p>Sambaina, <?php
									 echo date('d')." ";
									 $volana = date('m');
									 if($volana == '01'){echo('Janvier ');}
									 else if($volana == '02'){echo('Fevrier ');}
									 else if($volana == '03'){echo('Mars ');}
									 else if($volana == '04'){echo('Avril ');}
									 else if($volana == '05'){echo('Mai ');}
									 else if($volana == '06'){echo('Juin ');}
									 else if($volana == '07'){echo('Juillet ');}
									 else if($volana == '08'){echo('Aout ');}
									 else if($volana == '09'){echo('Septembre ');}
									 else if($volana == '10'){echo('Octobre ');}
									 else if($volana == '11'){echo('Novembre ');}
									 else if($volana == '12'){echo('Decembre ');}
									 echo date('Y')
									 ?></p>

						<br><br><br>

						<em>La registraire - Mme. Daniella MALALANIRINA</em>

					</div>
					<div class="border-t border-black flex">
						<div class="w-4/12">Université Adventiste Zurcher</div>
						<div class="w-4/12 text-center">--<?=$ptype?>--</div>
						<div class="w-4/12"></div>
					</div>
				</div>
			
			<?php
				}
			?>

			</div>

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
			  margin:       0.40,
			  filename:     '<?=$printName?>_<?=$date?>.pdf',
			  image:        { type: 'jpeg', quality: 2 },
			  html2canvas:  { scale: 10 },
			  jsPDF:        { unit: 'in', format: 'a4', orientation: 'paysage' }
			};

			// New Promise-based usage:
			html2pdf().set(opt).from(printConge).save();

			// Old monolithic-style usage:
			html2pdf(printConge, opt);
}
</script>
<style type="text/css">
	table tr,table td{
		border-collapse: collapse;
	}
	.tbl{
		border-collapse: collapse;
		font-size: 11px;
	}
	.tbl thead tr th{
		border: 1px solid #9d9d9d;
		padding-left: 4px;
		padding-right: 4px;
		height: 10px;
	}
	.tbl tbody tr td{
		border: 1px solid #9d9d9d;
		padding-left: 4px;
		padding-right: 4px;
		height: 10px;
	}
	.tbl tfoot tr th{
		border: 1px solid #9d9d9d;
		padding-left: 4px;
		padding-right: 4px;
		height: 10px;
	}
	.tbl tfoot tr td{
		border: 1px solid #9d9d9d;
		padding-left: 4px;
		padding-right: 4px;
		height: 10px;
	}
</style>