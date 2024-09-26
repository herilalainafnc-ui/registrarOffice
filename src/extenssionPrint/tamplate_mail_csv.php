<?php require '../../data/backdb.php'; 
 
	$types = $_POST['types'];
	$yearListCSV = $_POST['yearListCSV'];

?>

<table class="tbl">
	<thead>
		<tr>
			<th>First Name [Required]</th>
			<th>Last Name [Required]</th>
			<th>Email Address [Required]</th>
			<th>Password [Required]</th>
			<th>Password Hash Function [UPLOAD ONLY]</th>
			<th>Org Unit Path [Required]</th>
			<th>New Primary Email [UPLOAD ONLY]</th>
			<th>Recovery Email</th>
			<th>Home Secondary Email</th>
			<th>Work Secondary Email</th>
			<th>Recovery Phone [MUST BE IN THE E.164 FORMAT]</th>
			<th>Work Phone</th>
			<th>Home Phone</th>
			<th>Mobile Phone</th>
			<th>Work Address</th>
			<th>Home Address</th>
			<th>Employee ID</th>
			<th>Employee Type</th>
			<th>Employee Title</th>
			<th>Manager Email</th>
			<th>Department</th>
			<th>Cost Center</th>
			<th>Building ID</th>
			<th>Floor Name</th>
			<th>Floor Section</th>
			<th>Change Password at Next Sign-In</th>
			<th>New Status [UPLOAD ONLY]</th>
			<th>Advanced Protection Program enrollment</th>
		</tr>
	</thead>
	<tbody>
<?php 
	if ($types == 'TOUT') {

		$student = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearListCSV.'" AND new_student = 1 ORDER BY annee_etude');	

	}else{

		$student = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearListCSV.'" AND etude_envisage = "'.$types.'" AND new_student = 1 ORDER BY annee_etude');	
	}
	

	while ($afficher = $student->fetch()){
 ?>
		<tr>
			<td><?= $afficher['student_nom']?></td>
			<td><?= $afficher['student_prenom']?></td>
			<td><?= $afficher['student_email']?></td>
			<td><?= $afficher['password']?></td>
			<td><?= $afficher['password']?></td>
			<td>/</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td><?= $afficher['student_tel']?></td>
			<td></td>
			<td></td>
			<td><?= $afficher['student_tel']?></td>
			<td>Adventist Univerity Zurcher</td>
			<td><?= $afficher['status']?></td>
			<td><?= $student_id = $afficher['student_id']?></td>
			<td>Student</td>
			<td>Student</td>
			<td><?= $afficher['etude_envisage']?></td>
			<td><?= $afficher['etude_option']?></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td>False</td>
			<td></td>
			<td>False</td>
			
		</tr>

<?php
	}
 ?>
	</tbody>

</table>

<style type="text/css">
	.tbl{
		border-collapse: collapse;
		text-align: center;
	}

	.tbl th, td{
		border: 1px solid grey;
	}
</style>