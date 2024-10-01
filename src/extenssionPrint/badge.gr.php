<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<?php
	$types = $_POST['types'];
	$yearworked = $_POST['yearBadgeGr'];

	$niveau	= $_POST['niveau'];

	$printName = "BADGE-GROUP_".$niveau."_".$types;


	if ($types == 'TOUT') {

		$voir = $dtb->query("SELECT * FROM filiere ORDER BY filiere_description");	

	}else{

		$voir = $dtb->query("SELECT * FROM filiere WHERE filiere_description = '".$types."' ORDER BY filiere_description");	

	}
	

	while ($affiche = $voir->fetch()) {

		if ($niveau == 'TOUT') {
			$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearworked.'" AND etude_envisage ="'.$types.'" AND new_student = 1 ORDER BY student_id');	
		}else{
			$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearworked.'" AND etude_envisage ="'.$types.'" AND annee_etude ="'.$niveau.'" AND new_student = 1 ORDER BY student_id');	
		}
		
	?>

<div class="grid-cols-3 grid">
	<?php
$nbr = 1;
		while($stdA = $searchStd->fetch()) {
		$image_student = $stdA['image_student'];	

	?>

<div style="page-break-inside: avoid;" class="m-[3px]">
	<div class="border p-3" style="page-break-inside: avoid; height: 325px; background-image : url('../file/fond-badge.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative; font-family: 'arial';">

		<img src="../file/UAZ Official.png" style="height: 35px; position: absolute;">
		<center style='page-break-inside: avoid;'>
			<b style="color: white; font-size: 8px;">Université Adventiste Zurcher</b><br>
			<b style="color: white; font-size: 7px;">BP.325 Antsirabe (110)<br>Tel : 034 08 722 95 / 034 08 722 95</b>
		</center>

		<?php 
		if(strlen($image_student)> 7){	
		 ?>
			<div style="border: 2px solid white; margin: auto; height: 120px; width: 50%; background-image: url('../app/photosetudiants/<?=$image_student?>'); background-position: center; background-size: cover; border-radius: 5px; margin-top: 10px;">	
			</div>
		<?php 
		}else{
		?>	
		 	<div style="border: 2px solid black; margin: auto; height: 120px; width: 50%; background: white;margin-top: 10px;">	
			</div>
		<?php
		}
		?>	

		<center>
			<b style="font-size: 12px; color: #07325a; line-height: 12px; margin-top: 4px;"><?=strtoupper($stdA['student_nom'])?><br><?=$stdA['student_prenom']?></b><br>
			
			<b style="font-size: 12px; position: absolute; transform: rotate(-90deg);text-align: left; left:140px; bottom: 182px; width: 100px;">
			
				<?php if ($stdA['abonment'] == 1) { echo $stdA['status'].' & Abonné';}else{ echo $stdA['status'];} ?>
				
			</b>
			<b style="font-size: 20px; color: black;"><?=$stdA['student_id']?></b>
			<div style="color: white; background:#0054a5; border-radius: 3px; width: 80%; font-size: 12px"><b><?=$stdA['etude_envisage']?></b></div>
		
				 	<div style="width: 100%;height: 60px; position: absolute; left: 0px;bottom: 0px; overflow : auto; overflow: hidden;">
				 		<div id="barcodeDiv<?=$nbr?>"></div>
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
</div>
<script type="text/javascript">
	
	var text = '<?=$stdA['lookup_code']?>';
	
	var nbr = '<?=$nbr?>';

	var box = document.getElementById('barcodeDiv'+nbr);
	
	box.innerHTML = "<svg id='barcode"+nbr+"' style='width: 80px; height: auto;'></svg>";
	
	JsBarcode("#barcode"+nbr, text);
</script>

	<?php
	$nbr++;
		}
	?>

</div>


	<?php

	}

	?>
