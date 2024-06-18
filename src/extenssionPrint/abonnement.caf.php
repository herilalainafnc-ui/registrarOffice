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
$date_entry = $_GET['date_entry'];
$year_entry = substr($date_entry, 0, 4);

$printName = "Badge-".$student_id;

 ?>


<div style="height: 999px;width: 100%; background-image : url('../file/fond abonnement.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative;font-family: 'arial'; padding-left: 70px; padding-top: 320px;">

<?php 
if(strlen($image_student)> 7){	
 ?>
 
	<div style="border: 2px solid black; height:300px; width: 300px; background-image: url('../app/photosetudiants/<?=$image_student?>'); background-position: center; background-size: cover; border-radius: 200px;">	
	</div>
<?php 
}else{
?>	
 	<div style="border: 4px solid black; margin: auto; height: 350px; width: 46%; background: white;">	
	</div>
<?php
}
?>

	
	
	<br>
	<div style="color: black; background:white; border-top-left-radius: 10px; border-bottom-left-radius: 10px; width: 78%; padding-left: 10px;">
		<b style="font-size: 25px;"><?=strtoupper($student_nom)?><br><?=$student_prenom?></b>
	</div>
	<b style="font-size: 20px; color: white;">Matricule : <?=$student_id?></b><br>
	<b style="font-size: 20px; color: white;">Status : <?=$status?></b><br>
	<b style="font-size: 20px; color: white;">Mention : <?=$etude_envisage?></b>
	
	

		<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


		 	<div style="width: 100%; height: 190px; position: absolute; bottom: 0px; overflow : auto; overflow: hidden;">
		 		
		 		<div id="barcodeDiv"></div>
		 	</div>
		
		<style type="text/css">
			#barcode{
				width: 70%;
				height: 180%;
			}
		</style>
<!-- BARRE CODE -->


</div>
<script type="text/javascript">
			var text = '<?=$lookup_code;?>';
			var print = document.getElementById('print');
			var box = document.getElementById('barcodeDiv');
			box.innerHTML = "<svg id='barcode'></svg>";
			JsBarcode("#barcode", text);
</script>