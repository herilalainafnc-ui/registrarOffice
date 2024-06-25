<div style="width: 100%;display: grid;grid-template-columns: repeat(2, 1fr);grid-gap: 10px; font-family: 'arial';">
<?php 
	$student = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire = "2023 - 2024" AND annee_etude = 1 ORDER BY student_id');

	while ($afficher = $student->fetch()){
?>
	
	<div style="border: 1px solid black; width: 95%;">
		<div style="padding-left: 5px;padding-right: 5px; background: #eeedef; height: 60px;">
			<b style="font-size: 18px;"> <?=$afficher['student_id']?></b><br>
			<b style="font-size: 13px;"> <?=strtoupper($afficher['student_nom'])." ".$afficher['student_prenom']?></b>
		</div><hr style="margin: 0px;">

		<div style="padding-left: 5px;padding-right: 5px; font-size: 13px;">
			<b><?=$afficher['etude_envisage']." - ".$afficher['etude_option']?></b>
		</div><hr style="margin: 0px;">

		<div style="text-align: center">
				Adresse Mail Zurcher
		</div>

		<div style="padding-left: 5px;padding-right: 5px; text-align: center; font-size:13px; height: 40px;">	
			<label></label><b> <?=$afficher['student_email']?></b><br>
			<label>Password : </label><b> <?=$afficher['password']?></b>
		</div><hr style="margin: 0px;">

		<div style="padding-left: 5px;padding-right: 5px;">
			<em>S'il vous plaît, Veuillez garder ce ticket !!!</em>
		</div>


	</div>

<?php 
	}
?>	
</div>