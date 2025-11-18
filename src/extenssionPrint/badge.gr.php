<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<?php
	$student_id = $_POST['student_id'];
	$types = $_POST['types'];
	$semester = $_POST['semesterBadgeGr'];
	$yearworked = $_POST['yearBadgeGr'];

	$level	= $_POST['level'];

	$printName = $level."_".$types;

	
	$sub_session = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester="'.$semester.'" AND session_year ="'.$yearworked.'"');

	$getSession = $sub_session->fetch();

	$session_id = $getSession['session_id'];


	$voir = $dtb->query("SELECT * FROM filiere WHERE filiere_sigle = '".$types."' ORDER BY filiere_description");


	while ($filiere = $voir->fetch()) {

		if (!empty($student_id)) {
					
			$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id="'.$student_id.'"');

		}else{
			
			//$searchStd = $dtb->query('SELECT e.* FROM tbl_2024_etudiant AS e INNER JOIN t_2024_inscription_session AS i ON e.student_id = i.student_id  WHERE i.etude_mention = "'.$types.'" AND i.session_id = "'.$session_id.'" AND i.new_student = 1');

			$searchStd = $dtb->query('
			    SELECT DISTINCT e.*
			    FROM tbl_2024_etudiant AS e
			    INNER JOIN t_2024_inscription_session AS i 
			        ON e.student_id = i.student_id
			    WHERE i.etude_mention = "'.$types.'" 
			      AND i.session_id = "'.$session_id.'" 
			      AND i.new_student = 1
			');

		}
		
	?>


<div class="grid-cols-2 grid gape-2 px-[52px]" style="padding: 2px">

	<?php

// DOS BADGE ----------------------------------------
if(!empty($_POST['back'])) {
		for ($i=0; $i < 6; $i++) { 
		?>
 		<div style="page-break-inside: avoid; border: 1px dashed grey; grey; padding-top: 7.5px; padding-bottom: 7.5px; margin: 1.85599999999px" class="opacity-[1]">
			<div class="border p-[15px] m-auto" style="height: 8.5cm; width:7.5cm; background-image : url('../file/dos-badge_2025_2.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative; font-family: 'arial';">
			</div>
		</div>

		<?php		
		}
}
// ---------------------------------------------------
		$nbr = 1;

		while($stdA = $searchStd->fetch()) {

		$image_student = $stdA['image_student'];	

	?>

<div style="page-break-inside: avoid; border: 1px dashed grey; grey; padding-top: 7.5px; padding-bottom: 7.5px; margin: 1.85599999999px" class="opacity-[1]">

	<div class="border p-[15px] m-auto" style="height: 8.5cm; width:7.5cm; background-image : url('../file/badge_2025_2.jpg');background-position: center; background-size: cover; font-family: corbel, sans-serif; position: relative; font-family: 'arial';">

		<img src="../file/UAZ Official.png" style="height: 30px; position: absolute;">
		<center>
			<b style="color: white; font-size: 10px;">Université Adventiste Zurcher</b><br>
			<b style="color: white; font-size: 7px;">BP.325 Antsirabe (110)<br>Tel : 034 46 000 08 / 034 76 076 98</b>
		</center>

		<?php 
		if(strlen($image_student)> 7){	
		 ?>
			<div style="border: 3px solid white; margin: auto; height: 120px; width: 50%; background-image: url('../app/photosetudiants/<?=$image_student?>'); background-position: center; background-size: cover; border-radius: 10px; margin-top: 10px; margin-bottom: 5px;">	
			</div>
		<?php 
		}else{
		?>	
		 	<div style="border: 3px solid white; margin: auto; height: 120px; width: 50%; background: #dadadc;margin-top: 10px; margin-top: 10px; margin-bottom: 5px; border-radius: 10px;">	
			</div>
		<?php
		}
		?>	

		<center>
			<a style="font-size: 12px; color: white; line-height: 15px; margin-top: 4px;"><?=strtoupper($stdA['student_nom'])?><br><?php 
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
		?></a><br>
			<b style="font-size: 20px; color: white; position: absolute; transform: rotate(-90deg);text-align: left; left:-16px; bottom: 200px; width: 100px;">		
				<?=$stdA['student_id']?>	
			</b>
			<b style="font-size: 10px; color: white; position: absolute; transform: rotate(-90deg);text-align: left; left:180px; bottom: 200px; width: 100px;">		
				<?php echo $stdA['status']."<br>"; if ($stdA['abonment'] == 1) { echo 'Abonné(e)';}?>	
			</b>
			
			<div style="color: white; border-radius: 3px; width: 80%; font-size: 12px; border-top: 1px solid white; margin-top: 5px;"><b><?=strtoupper($stdA['etude_envisage'])?></b></div>
		
				 	<div style="width: 100%;height: 60px; position: absolute; left: 0px;bottom: 0px; overflow : auto; overflow: hidden;">
				 		<div id="barcodeDiv<?=$nbr?>"></div>
				 	</div>
				
				<style type="text/css">
					#barcode<?=$nbr?>{
						width: 55%;
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
