<?php require '../../data/backdb.php'; 
 
	$types = $_POST['types'];
	$yearListCSV = $_POST['yearListCSV'];
	$typesCSV = $_POST['typesCSV'];

	function remplaceCS($texte) {
	
	    $caracteresSpeciaux = ['é', 'à', 'è', 'ë', 'ê', 'ô',' ',',','-'];
	    $remplacements = ['e', 'a', 'e', 'e', 'e', 'o','','',''];

	    return str_replace($caracteresSpeciaux, $remplacements, $texte);
	}

	function remplaceNum($numero) {
	
	    return preg_replace('/^0/', '+261', $numero);
	
	}

	function captTen($numberNum) {

		return substr($numberNum,0,10);

	}

if ($typesCSV == 'csv') {
	
?>
<table style="border-collapse: collapse;">
	<thead>
		<tr>
			<th style="border: 1px solid black; text-align: left">
				First Name [Required],Last Name [Required],Email Address [Required],Password [Required],Password Hash Function [UPLOAD ONLY],Org Unit Path [Required],New Primary Email [UPLOAD ONLY],Status [READ ONLY],Last Sign In [READ ONLY],Recovery Email,Home Secondary Email,Work Secondary Email,Recovery Phone [MUST BE IN THE E.164 FORMAT],Work Phone,Home Phone,Mobile Phone,Work Address,Home Address,Employee ID,Employee Type,Employee Title,Manager Email,Department,Cost Center,2sv Enrolled [READ ONLY],2sv Enforced [READ ONLY],Building ID,Floor Name,Floor Section,Email Usage [READ ONLY],Drive Usage [READ ONLY],Photos Usage [READ ONLY],Storage limit [READ ONLY],Storage Used [READ ONLY],Change Password at Next Sign-In,New Status [UPLOAD ONLY],Advanced Protection Program enrollment,Gemini Limit Status [READ ONLY]
			</th>
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
			<td style="border: 1px solid black; text-align: left">
				<?=$afficher['student_nom']?>,<?=$afficher['student_prenom']?>,<?=remplaceCS($afficher['student_email'])?>,<?=$afficher['password']?>,,/,,Activate,,,,,,<?php 
if ($afficher['student_tel']) {
					// code...
				}				
	remplaceNum(remplaceCS(captTen($afficher['student_tel'])));

				?>,,<?php 
if ($afficher['student_tel']) {
					// code...
				}				
	remplaceNum(remplaceCS(captTen($afficher['student_tel'])));

			?>,Adventist Univerity Zurcher,<?=remplaceCS($afficher['student_adresse'])?>,<?=$afficher['student_id']?>,Student,Student,,<?= $afficher['etude_envisage']?>,<?=remplaceCS($afficher['etude_option'])?>,,,,,,,,,,,FALSE,,FALSE,-
			</td>
		</tr>
	<?php
		}
	 ?>
	 </tbody>
</table>
<?php 
}elseif ($typesCSV == 'table') {
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
			<th>Status [READ ONLY]</th>
			<th>Last Sign In [READ ONLY]</th>
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
			<th>2sv Enrolled [READ ONLY]</th>
			<th>2sv Enforced [READ ONLY]</th>
			<th>Building ID</th>
			<th>Floor Name</th>
			<th>Floor Section</th>
			<th>Email Usage [READ ONLY]</th>
			<th>Drive Usage [READ ONLY]</th>
			<th>Photos Usage [READ ONLY]</th>
			<th>Storage limit [READ ONLY]</th>
			<th>Storage Used [READ ONLY]</th>
			<th>Change Password at Next Sign-In</th>
			<th>New Status [UPLOAD ONLY]</th>
			<th>Advanced Protection Program enrollment</th>
			<th>Gemini Limit Status [READ ONLY]</th>
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
			<td><?=$afficher['student_nom']?></td>
			<td><?=$afficher['student_prenom']?></td>
			<td><?=remplaceCS($afficher['student_email'])?></td>
			<td><?=$afficher['password']?></td>
			<td></td>
			<td>/</td>
			<td></td>
			<td>Activate</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td><?=remplaceNum(remplaceCS($afficher['student_tel']))?></td>
			<td></td>
			<td><?=remplaceNum(remplaceCS($afficher['student_tel']))?></td>
			<td>Adventist Univerity Zurcher</td>
			<td><?=remplaceCS($afficher['student_adresse'])?></td>
			<td><?=$afficher['student_id']?></td>
			<td>Student</td>
			<td>Student</td>
			<td></td>
			<td><?= $afficher['etude_envisage']?></td>
			<td><?=remplaceCS($afficher['etude_option'])?></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td>FALSE</td>
			<td></td>
			<td>FALSE</td>
			<td>-</td>

		</tr>

<?php
	}
 ?>
	</tbody>

</table>
<?php 
}
 ?>
<style type="text/css">
	.tbl{
		border-collapse: collapse;
		text-align: center;
	}

	.tbl th, td{
		border: 1px solid grey;
	}
</style>