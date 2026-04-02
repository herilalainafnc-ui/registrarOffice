<?php 
require '../data/backdb.php';

$id = $_GET['id'];
$student_id = $_GET['student_id'];
$student_nom = $_GET['student_nom'];
$student_prenom = $_GET['student_prenom'];
$etude_envisage = $_GET['etude_envisage'];
$etude_option = $_GET['etude_option'];
$student_tel = $_GET['student_tel'];
$image_student = $_GET['image_student'];
$lookup_code = $_GET['lookup_code'];
$status = $_GET['status'];
$abonment = $_GET['abonment'];
$date_entry = $_GET['date_entry'];
$year_entry = substr($date_entry, 0, 4);

$printName = $student_id."-BADGE-ETUDIANT";

 ?>


<div style="height: 840px;width: 100%; background-image : url('../file/badge_2025_2.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative;font-family: 'arial';">
<img src="../file/UAZ Official.png" style="height: 100px; position: absolute; top: 40px; left: 40px;">
<center>
	<br><br>

	<b style="color: white; font-size: 27px;">Université Adventiste Zurcher</b><br>
	<b style="color: white; font-size: 20px;">BP.325 Antsirabe (110)<br>Tel : 034 46 000 08 / 034 76 076 98</b><br><br>
</center>

<?php 
if(strlen($image_student)> 7){	
 ?>
	<div style="border: 6px solid white; margin: auto; height: 350px; width: 46%; background-image: url('../app/photosetudiants/<?=$image_student?>'); background-position: center; background-size: cover; border-radius: 25px;">	
	</div>
<?php 
}else{
?>	
 	<div style="border: 4px solid grey; margin: auto; height: 350px; width: 46%; background: white;">	
	</div>
<?php
}
?>
<center>
	<a style="font-size: 35px; color: white;"><?=strtoupper($student_nom)?><br><?=$student_prenom?></a>
	<b style="font-size: 22px; color: white; position: absolute; transform: rotate(-90deg);text-align: left; left:570px; bottom: 515px; width: 100px;">
		<?php if ($abonment == 1) { echo $status.' & Abonné';}else{ echo $status;} ?> 
		<br>
	</b>
	
	<b style="font-size: 70px; color: white; position: absolute; transform: rotate(-90deg);top: 290px; left:15px;"><?=$student_id?></b>
	<div style="color: white; width: 70%; font-size: 25px; border-top: 2px solid white; margin-top: 15px;"><b><?=strtoupper($etude_envisage)?></b></div>
	

		<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


		 	<div style="width: 100%; height: 150px; position: absolute; bottom: 0px; overflow : auto; overflow: hidden;">
		 		
		 		<div id="barcodeDiv"></div>
		 	</div>
		
		<style type="text/css">
			#barcode{
				width: 70%;
				height: 180%;
			}
		</style>
<!-- BARRE CODE -->

</center>

</div>
<script type="text/javascript">
			var text = '<?=$lookup_code;?>';
			var print = document.getElementById('print');
			var box = document.getElementById('barcodeDiv');
			box.innerHTML = "<svg id='barcode'></svg>";
			JsBarcode("#barcode", text);
</script>