<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<?php
	$student_id = $_POST['student_id'];
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
			$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearworked.'" AND etude_envisage ="'.$types.'" ORDER BY student_id');	
		}elseif (!empty($student_id)) {
			$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');
		}else{
			$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearworked.'" AND etude_envisage ="'.$types.'" AND annee_etude ="'.$niveau.'" AND new_student = 1 ORDER BY student_id');	
		}
		
	?>
<!--  -->
<div class="grid-cols-3 grid gape-2 px-[52px]" style="page-break-inside: avoid; padding: 2px">

	<?php

//		for ($i=0; $i < 7; $i++) { 
		?>
<!-- 		<div style="page-break-inside: avoid; border: 1px dashed grey; grey; padding-top: 7.5px; padding-bottom: 7.5px; margin: 2px" class="opacity-[1]">
			<div class="border p-[15px] m-auto" style="height: 8.5cm; width:5.4cm; background-image : url('../file/badge-dos_2025.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative; font-family: 'arial';">
			<div style="height: 20px"></div>

			<center style='page-break-inside: avoid;'>
				<b style="font-size: 8px;">Université Adventiste Zurcher</b><br>
				<b style="font-size: 7px;">BP.325 Antsirabe (110)<br>Tel : 034 08 722 95 / 034 08 722 95</b><br><br>

				<b style="color: #145995; font-size: 9px">DÉCLARATION DE MISSION</b>
				<p style="font-size: 8px;">L'UAZ est une institution internationale dont la mission est d'offrir une éducation supérieure, dans un environnement permettant le développement harmonieux des énergies physiques, mentales et spirituelles, conformément aux valeurs chrétiennes prônées par l'Eglise adventiste du septième jour, en vue de restaurer en l'homme l'image de Dieu.</p>

				<b style="color: #145995; font-size: 9px">NOTRE VISION</b>
				<p style="font-size: 8px;">Préparer aujourd'hui les leaders de demain</p>

				<b style="color: #145995; font-size: 9px">NOS VALEURS</b>
				<p style="font-size: 8px;">L'amour de Dieu et du prochain<br>Le service dans l'excellence<br>Le respect des humains et de la création</p>
			</center>
			</div>
		</div>
 -->
		<?php		
//		}

		$nbr = 1;
		while($stdA = $searchStd->fetch()) {
		$image_student = $stdA['image_student'];	

	?>

<div style="page-break-inside: avoid; border: 1px dashed grey; grey; padding-top: 7.5px; padding-bottom: 7.5px; margin: 2px" class="opacity-[1]">

	<div class="border p-[15px] m-auto" style="height: 8.5cm; width:5.4cm; background-image : url('../file/badge-face_2025.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative; font-family: 'arial';">

		<img src="../file/UAZ Official.png" style="height: 30px; position: absolute;">
		<div style="height: 30px"></div>

		<?php 
		if(strlen($image_student)> 7){	
		 ?>
			<div style="border: 2px solid #001330; margin: auto; height: 120px; width: 60%; background-image: url('../app/photosetudiants/<?=$image_student?>'); background-position: center; background-size: cover; border-radius: 5px; margin-top: 10px; margin-bottom: 5px;">	
			</div>
		<?php 
		}else{
		?>	
		 	<div style="border: 2px solid #001330; margin: auto; height: 120px; width: 60%; background: #dadadc;margin-top: 10px; margin-top: 10px; margin-bottom: 5px; border-radius: 5px;">	
			</div>
		<?php
		}
		?>	

		<center>
			<b style="font-size: 12px; color: #001330; line-height: 12px; margin-top: 4px;"><?=strtoupper($stdA['student_nom'])?><br><?php 
$spcOne = strpos($stdA['student_prenom'],' ');

if ($spcOne !== false) {
    
    $spcTwoo = strpos($stdA['student_prenom'], ' ', $spcOne + 1);
    
    if ($spcTwoo !== false) {
    	echo substr($stdA['student_prenom'],0,($spcTwoo));
    } else {
        echo $stdA['student_prenom'];
    }
} else {
    echo $stdA['student_prenom'];
}		
		?></b><br>
			<b style="font-size: 12px; position: absolute; transform: rotate(-90deg);text-align: left; left:-16px; bottom: 190px; width: 100px;">		
				<?php if ($stdA['abonment'] == 1) { echo 'Abonné(e)';}?>	
			</b>
			<b style="font-size: 12px; position: absolute; transform: rotate(-90deg);text-align: left; left:118px; bottom: 190px; width: 100px;">
				<?=$stdA['status']?>	
			</b>
			<b style="font-size: 20px; color: black;"><?=$stdA['student_id']?></b>
			<div style="color: white; background:#04254e; border-radius: 3px; width: 80%; font-size: 12px"><b><?=$stdA['etude_envisage']?></b></div>
		
				 	<div style="width: 100%;height: 60px; position: absolute; left: 0px;bottom: 0px; overflow : auto; overflow: hidden;">
				 		<div id="barcodeDiv<?=$nbr?>"></div>
				 	</div>
				
				<style type="text/css">
					#barcode<?=$nbr?>{
						width: 100%;
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
