<?php 
$id = $_GET['id'];
$student_id = $_GET['student_id'];
$student_nom = $_GET['student_nom'];
$student_prenom = $_GET['student_prenom'];
$etude_envisage = $_GET['etude_envisage'];
$etude_option = $_GET['etude_option'];

$student_tel = $_GET['student_tel'];
$image_student = $_GET['image_student'];
$printName = $student_id."-CERTIFICAT_SCOLARITE";
 
$searchStd = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE student_id = "'.$student_id.'"');

$stdA = $searchStd->fetch();
 ?>


<div>
	<br><center>
		<b class="text-2xl">CERTIFICAT DE SCOLARITE</b>
	</center><br><br>
	<div style="text-align: justify;font-size: 14px">
		<p style="text-indent: 40px;">Je, soussignée, Registraire de l’Université Adventiste Zurcher, certifie que :</p><br>
		<em>Nom et prénoms : </em><b><em><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></em></b><br>
		<em>Date de naissance : </em><b><em><?=$stdA['dateNaissance']?></em></b><br>
		<em>Lieu de naissance : </em><b><em><?=$stdA['lieuNaissance']?></em></b><br>
		<em>Fils/fille de : </em><b><em><?=$stdA['father_name']?></em></b><br>
		<em>Et de : </em><b><em><?=$stdA['mother_name']?></em></b><br>
		<em>Adresse habituelle :</em><b><em><?=$stdA['student_adresse']?></em></b><br><br>
		<p style="text-indent: 40px;">Est inscrit(e) dans la FACULTE de <b><?= $stdA['etude_envisage']?></b> de notre Institution, comme étudiant(e) régulièr(e), en <b><?= $etude_option?></b> pour cette année universitaire <?=$stdA['annee_scolaire']?>.</p><br>
		<em>Numéro d’immatriculation : </em><b><em><?=$student_id?></em></b><br>
		<p style="text-indent: 40px;">Ce certificat lui est délivré pour valoir et servir ce que de droit.</p>
	</div><br>
</div>