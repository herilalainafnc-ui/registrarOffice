<?php 

$mention = $_POST['types'];
$annee_scolaire = $_POST['yearworkedSlip'];
$date_begin = $_POST['date_begin'];
$date_end = $_POST['date_end'];


$printName = "WORKED_SLIP_ETUDIANT";
	
	if ($mention == "TOUT") {
		$student = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$annee_scolaire.'" ORDER BY student_id');
	}else{
		$student = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$annee_scolaire.'" AND etude_envisage="'.$mention.'" ORDER BY student_id');
	}
?>
<div class="w-full grid gap-2 grid-cols-2">
<?php 

	while ($afficher = $student->fetch()){
?>
	
	<div class="w-full border-1 border-black">
		
			<center><b style="font-size: 12px;">UNIVERSITE ADVENTISTE ZURCHER<br>Departement Work Education Clearance Slip</b></center>	
			<div style="font-size: 10px; height: 86.5px" class="px-2">
				<em>Matricule : </em><b> <?=$afficher['student_id']?></b><br>
				<em>Nom et prénoms : </em><b> <?=strtoupper($afficher['student_nom'])." ".$afficher['student_prenom']?></b><br>
				<em>Mention : </em><b><?=$afficher['etude_envisage']." - ".$afficher['etude_option']?></b><br>
				<em>Niveau :</em><b> Licence <?=$afficher['annee_etude']?></b><br>
				<em>Année académique :</em><b> <?=$afficher['annee_scolaire']?></b><br>
			</div>
			<div style="text-align: center;font-size: 12px">
				Cet(te) étudiant(e) peut écrire ses examens parce qu'il/elle a terminé toutes les heures requises de son Work Education.		

			</div>
			<hr style="margin: 0px;">
		<div class="h-[40px]">
			<table style="width:100%; font-size: 10px;">
				<tr>
					<td> 
						<em>Date du début : </em> <b><?=$date_begin?></b><br>
						<em>Date d'expiration : </em> <b><?=$date_end?></b><br>
					</td>
					<td>
						<em>Signature</em><br><br>
					</td>
				</tr>
			</table>
		</div>

		


	</div>

<?php 
	}
?>	
</div>