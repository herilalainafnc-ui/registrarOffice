<?php 
	require('../../../data/backdb.php');

	$id = $_GET['id'];

	$findStd = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE id = "'.$id.'"');

	$showStd = $findStd->fetch();
		$student_id = $showStd['student_id'];
		$student_nom = $showStd['student_nom'];
		$student_prenom = $showStd['student_prenom'];
		$etude_envisage = $showStd['etude_envisage'];
		$etude_option = $showStd['etude_option'];
		$annee_etude = $showStd['annee_etude'];
		$student_tel = $showStd['student_tel'];
		$student_email = $showStd['student_email'];
		$dateNaissance = $showStd['dateNaissance'];
		$lieuNaissance = $showStd['lieuNaissance'];
		$nationalite = $showStd['nationalite'];
		$student_adresse = $showStd['student_adresse'];
	
	$printName = "FICHE D'INSCRIPTION_".$student_id;

	$i = 1;
	$sigle = "sigle";
	$titrecour = "titrecours";
	$nb_crd = "nb_crd";
	$category = "category";
	$note = "note";
	$notecredit = "notecredit";
	$etat = "etat";
	
	//$tnote = $_GET['tnote'];
	//$tnotecredit = $_GET['tnotecredit'];
	//$moyennesem = $_GET['moyennesem'];
	//$moyennemaj = $_GET['moyennemaj'];
	//$gencumul = $_GET['gencumul'];
	//$majcumul = $_GET['majcumul'];
	//$worked = $_GET['worked'];
	//$chapl = $_GET['chapl'];
	//$rmrq = $_GET['rmrq'];
	//$code = $_GET['code'];

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title></title>
</head>
<body>
<div class="col-lg-8">

	<div class="head">
		<div style="position: absolute;">
			<img src="files/UAZ Official Black Logo.jpg" style="width: 100px">
		</div>
	<center>
		<div>
			<?php require '../../../init/.forprint/top.forPrint.php'; ?>
		
		<h2>Fiche d'inscription <?php 
if ($code == 0){
echo "de tous les semestres";
	}elseif($code == 1){
echo "1ère Année - 1er Semestre";
	}elseif($code == 2){
echo "1ère Année - 2ème Semestre";
	}elseif($code == 3){
echo "2e Année - 1er Semestre";
	}elseif($code == 4){
echo "2e Année - 2ème Semestre";
	}elseif($code == 5){
echo "3e Année - 1er Semestre";
	}elseif($code == 6){
echo "3e Année - 2ème Semestre";
	}
echo " ".(date('Y')-1)." - ".date('Y');?></h2>	
		</div>
		
	</center>
	</div>
	<div class="inline">
		<div class="infotitre">
				<b>INFORMATIONS</b>	
		</div>
		<div class="informationbox">
			<div class="flex">
				<div style="width: 90%;">
						<table class="tbl">
							<tr>
								<td id="chfr3">Date :</td>
								<td><b><?php
					 echo date('d')." ";
					 $volana = date('m');
					 if($volana == '01'){echo('Janvier ');}
					 else if($volana == '02'){echo('Fevrier ');}
					 else if($volana == '03'){echo('Mars ');}
					 else if($volana == '04'){echo('Avril ');}
					 else if($volana == '05'){echo('Mai ');}
					 else if($volana == '06'){echo('Juin ');}
					 else if($volana == '07'){echo('Juillet ');}
					 else if($volana == '08'){echo('Aout ');}
					 else if($volana == '09'){echo('Septembre ');}
					 else if($volana == '10'){echo('Octobre ');}
					 else if($volana == '11'){echo('Novembre ');}
					 else if($volana == '12'){echo('Decembre ');}
					 echo date('Y')
					 ?></b></td>
					 			<td id="chfr3">Mention :</td>
								<td><b><?php echo $etude_envisage; ?></b></td>
							</tr>
							<tr>
								<td id="chfr3">Numéro :</td>
								<td><b><?php echo $student_id; ?></b></td>
								<td id="chfr3">Parcours :</td>
								<td><b><?php echo $etude_option; ?></b></td>
							</tr>
							<tr>
								<td id="chfr3">Nom :</td>
								<td><b><?php echo $student_nom; ?></b></td>
								<td id="chfr3">Année d'étude :</td>
								<td><b><?php echo $annee_etude; ?></b></td>
							</tr>
							<tr>
								<td id="chfr3">Prénoms :</td>
								<td><b><?php echo $student_prenom; ?></b></td>
								<td id="chfr3">Résidence :</td>
								<td><b><?php echo $residence; ?></b></td>
							</tr>
							<tr>
								<td id="chfr3">Adresse :</td>
								<td><b><?php echo $student_adresse; ?></b></td>
								<td id="chfr3">Nationalité :</td>
								<td><b><?php echo $nationalite; ?></b></td>
							</tr>
						</table>		
				</div>
			</div>
		</div>
		<center>
				<h3>LISTE DES COURS</h3>
		</center>
<div style="min-height: 570px;">
	<div class="informationbox col-lg-12" style="border-radius: 0px;">
			<table class="tbl">
				<thead>
					<tr id="trchfr">
						<td id="chfr" class="cl1">SIGLE</td>
						<td id="chfr" class="cl2">TITRE DU COURS</td>
						<td id="chfr" class="cl3">CREDITS</td>
						<td id="chfr" class="cl4">Categorie</td>
					</tr>
				</thead>
				<tbody>
<?php 
if ($etude_envisage == "Théologie") {
	$student_cours = 'THEO';
}elseif ($etude_envisage == "Gestion") {
	$student_cours = 'GEST';
}elseif ($etude_envisage == "Informatique") {
	$student_cours = 'INFO';
}elseif ($etude_envisage == "Sciences Infirmières") {
	$student_cours = 'NURS';
}elseif ($etude_envisage == "Education") {
	$student_cours = 'EDUC';
}elseif ($etude_envisage == "Communication") {
	$student_cours = 'COMM';
}elseif ($etude_envisage == "Etudes anglophones") {
	$student_cours = 'ENGL';
}elseif ($etude_envisage == "Cours Préparatoire") {
	$student_cours = 'CPRE';
}
	$sutdent_option = $etude_option;
	$annee = $annee_etude;
	$date_entry = date('Y-m-d');
$course = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout = 1 AND date_entry = '".$date_entry."' ORDER BY title_cours");

	if($code == 0){
		$year = 0;
		$sem = 0;
	}elseif($code == 1){
		$year = 1;
		$sem = 1;
	}elseif($code == 2){
		$year = 1;
		$sem = 2;
	}elseif($code == 3){
		$year = 2;
		$sem = 1;
	}elseif($code == 4){
		$year = 2;
		$sem = 2;
	}elseif($code == 5){
		$year = 3;
		$sem = 1;
	}elseif($code == 6){
		$year = 3;
		$sem = 2;
	}

if($year != 0 AND $sem != 0){
		$course = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id ="'.$student_id.'" AND yearlevel LIKE "%'.$year.'%" AND semester LIKE "%'.$sem.'%" AND ajout = 1 ORDER BY title_cours');
	}


$nbr = 1;
	if($course->rowCount() > 0) {
while ($cours_table = $course->fetch()) {
	
 ?>
					<tr>
						<td class="cl1"><?php echo $cours_table['Sigle']; ?></td>
						<td class="cl2"><?php echo $cours_table['title_cours']; ?></td>
						<td class="cl3" id="chfr"><?php echo $cours_table['credit']; ?></td>
						<td class="cl4" id="chfr">
<?php 
$voir = $dtb->query("SELECT * FROM t_2023_cours WHERE Sigle ='".$cours_table['Sigle']."'");
$afficher = $voir->fetch();

if($afficher){
	if(is_null($afficher['category'])){
		echo '';
	}

$category = $afficher['category'];
							if (($category == 0) OR ($category == 'Général')){
								echo "Général";
							}elseif (($category == 1) OR ($category == 'Majeur')) {
								echo "Majeur";
							}elseif (($category == 2) OR ($category == 'Selective')) {
								echo "Selective";
							}else{
								echo "-";
							}
}
						 ?></rd>

<?php 
	if ($cours_table['cours_category'] == 1) {
		$valmajeur = $cours_table['grade'];
		$ident = 1;
	}else {
		$valmajeur = 0;
		$ident = 0;
	}
 ?>
							 
					</tr>
<?php
$tcredit = 0;
$crd[$nbr] = $cours_table['credit'];
foreach ($crd as $valeur) {
$tcredit += $valeur;
}


$nbr++;
	}
}
 ?>
					<tr>
						<input type="number" class="nombre" name="nombre" value="<?php echo $nbr -1; ?>" style="display: none;">
						<td class="marck cl1">Nb Cours <?php echo $nbr -1;?></td>
<?php 
$nombre = $nbr -1;
if(($nbr -1) < 1){	 
}
 ?>
						<td class="mute 2"></td>
						<td id="chfr" class="marck cl3"><?php
if (!empty($tcredit)) {
	echo $tcredit;
}else{
	echo "";
}						 ?></td>
						<td id="chfr" class="mute cl7"></td>
					</tr>
				</tbody>
			</table>
	</div>

<br>
	<h4>WORK EDUCATION CHOISI :</h4>
	
	<h4>ENGAGEMENT</h4>
	<b>Je sousigné(e) <?php echo $student_nom." ".$student_prenom ?></b>
	<p>m'engage, durant mon séjour à l'Université Adventiste Zurcher, à maintenir en tout temps une conduite et attitude exemplaire, et en harmonie avec la philosophie chrétienne de cette institution qui m'acceuille; à contribuer positivement à la vie de l'université et à vivre en tout temps en conformité avec ses principes et règlements. Le non-respect de cet engagement pourrait entrainer une sanction ou même un renvoi temporaire.</p>
	<hr>


</div>


	
		<div>
			<p>Sambaina, le <?php
				 echo date('d')." ";
				 $volana = date('m');
				 if($volana == '01'){echo('Janvier ');}
				 else if($volana == '02'){echo('Fevrier ');}
				 else if($volana == '03'){echo('Mars ');}
				 else if($volana == '04'){echo('Avril ');}
				 else if($volana == '05'){echo('Mai ');}
				 else if($volana == '06'){echo('Juin ');}
				 else if($volana == '07'){echo('Juillet ');}
				 else if($volana == '08'){echo('Aout ');}
				 else if($volana == '09'){echo('Septembre ');}
				 else if($volana == '10'){echo('Octobre ');}
				 else if($volana == '11'){echo('Novembre ');}
				 else if($volana == '12'){echo('Decembre ');}
				 echo date('Y')
				 ?></p>	
		</div>
		<p>Approuvée par :</p>
		<table style="width: 100%">
			<tbody>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td style="height: 50px;border"></td>
				</tr>
				<tr>
					<td style="border-top: 1px solid black; text-align: center;">Etudiant(e)</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Chef de département</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>financière</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>académique</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>aux affaire Estudiantine</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Registraire</td>
				</tr>
			</tbody>
		</table><br>
<div style="border-top:1px solid black; width: 100%;">
	<table class="tbl">
		<tr>
			<td><em>Université Adventiste Zurcher</em></td>
			<td><em>-Inscription-</em></td>
		</tr>
	</table>
</div>
</div>

<div style="width: 700px;">
<?php 
$course = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout = 1  AND date_entry = '".$date_entry."' ORDER BY title_cours");

	if($code == 0){
		$year = 0;
		$sem = 0;
	}elseif($code == 1){
		$year = 1;
		$sem = 1;
	}elseif($code == 2){
		$year = 1;
		$sem = 2;
	}elseif($code == 3){
		$year = 2;
		$sem = 1;
	}elseif($code == 4){
		$year = 2;
		$sem = 2;
	}elseif($code == 5){
		$year = 3;
		$sem = 1;
	}elseif($code == 6){
		$year = 3;
		$sem = 2;
	}

if($year != 0 AND $sem != 0){
		$course = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id ="'.$student_id.'" AND yearlevel LIKE "%'.$year.'%" AND semester LIKE "%'.$sem.'%" AND ajout = 1');
	}

	while ($crs_tbl = $course->fetch()) {
 ?>

		<div style="border: 1px solid black; width: 700px; margin-top: 3px;min-height: 122px;">
			<center>
				<b>Billet d’entrée en classe</b>
			</center>
			<hr>
			<table>
				<tr>
					<td style="padding-left: 5px; padding-right: 5px; "><b>Fait le <?php
				 echo date('d')." ";
				 $volana = date('m');
				 if($volana == '01'){echo('Janvier ');}
				 else if($volana == '02'){echo('Fevrier ');}
				 else if($volana == '03'){echo('Mars ');}
				 else if($volana == '04'){echo('Avril ');}
				 else if($volana == '05'){echo('Mai ');}
				 else if($volana == '06'){echo('Juin ');}
				 else if($volana == '07'){echo('Juillet ');}
				 else if($volana == '08'){echo('Aout ');}
				 else if($volana == '09'){echo('Septembre ');}
				 else if($volana == '10'){echo('Octobre ');}
				 else if($volana == '11'){echo('Novembre ');}
				 else if($volana == '12'){echo('Decembre ');}
				 echo date('Y')
				 ?></b></td>
					<td style="padding-left: 5px; padding-right: 5px; "></td>
				</tr>
				<tr>
					<td style="padding-left: 5px; padding-right: 5px; "><label><b>Matricule :</b> <?= $student_id;?></label></td>
					<td style="width:50px"></td>
					<td style="padding-left: 5px; padding-right: 5px; "><label><b>Cours :</b> <?=$crs_tbl['title_cours'];?></label></td>
				</tr>
				<tr>
					<td style="padding-left: 5px; padding-right: 5px; "><label><b>Nom complet :</b> <?= $student_nom." ".$student_prenom;?></label></td>
					<td style="width:50px"></td>
					<td style="padding-left: 5px; padding-right: 5px; "><label><b>Sigle :</b> <?=$crs_tbl['Sigle'];?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=$crs_tbl['credit'];?> Crédit.</label></td>
				</tr>
				<tr>
					<td style="padding-left: 5px; padding-right: 5px; "><label><b>Faculté :</b> <?= $etude_envisage;?></label></td>
					<td style="width:50px"></td>
					<td style="padding-left: 5px; padding-right: 5px; "><label><b>Nom du professeur :</b> 
<?php 
$uid = $crs_tbl['teacher_id'];
if ($crs_tbl['teacher_id'] != "") {
	$teach = $dtb->query('SELECT * FROM teacher WHERE uid = "'.$uid.'" LIMIT 1');
	$affiche_teach = $teach->fetch();
	echo $affiche_teach['name']." ".$affiche_teach['lastName'];
}

 ?>						
				
					</label></td>
				</tr>
			</table>
			<hr>
			<table>
				<tr>
					<td style="padding-left: 10px;"><em><b>Veuillez recevoir cet(te) étudiant(e) dans votre cours. Merci!</b></em></td>
					<td style="width:150px"></td>
					<td><em>La Registraire :</em></td>
				</tr>
			</table>				
		</div>

<?php 
}
 ?>
</div>

</body>
</html>

<style type="text/css">
	tr td{
		min-height: 17px;
	}
.cl1{width: 10%}
.cl2{width: 45%}
.cl3{width: 10%}
.cl4{width: 10%}
.cl5{width: 10%}
.cl6{width: 10%}
.cl7{width: 5%}

@font-face {
    font-family: 'corbel';
    src: url('mycss/polices/corbel.ttf');
}
body {
    font-family: corbel, sans-serif;
  }
.infotitre{
	background-color: #8b2da1;
	color: white;
	border-top-left-radius: 8px;
	border-top-right-radius: 8px;
	padding-left: 15px;
	height: 20px;
	padding-top: 5px;
	
}
.informationbox{
	border: 1px solid #8b2da1;
	margin: 0px;
}
.flex{
	display: flex;
	padding-left: 10px;
	padding-right: 10px;
	padding-top: 5px;
	padding-bottom: 5px;
	width: 90%;
}
.inline{
	
	display: inline-grid;
	width: 100%;
}
.head{
	border-bottom: 2px solid #6b6969;
	margin-bottom: 10px;
}
thead #trchfr{
	font-weight: bold;
	height: 10px;
	padding: 0px;
	margin: 0px;
	background-color: #dfe0ef;
}
#chfr{
	text-align: center;
	padding-left: 5px;	
	padding-right: 5px;
	border: 1px solid #c8cae4;
}
#chfr2{
	text-align: right;
	padding-left: 5px;	
	padding-right: 5px;
	border: 1px solid #c8cae4;
}
#chfr3{
	text-align: right;
	padding-right: 12px;
}
#chfrnb{
	padding: 0px;
	text-align: center;
	margin: 0px;
	background-color: #e8e6e6;
	border: 1px solid #c8cae4;
}
.tbl{
	width: 100%;
}
.marck{
	background-color: #e8e6e6;
	font-weight: bold;
}
.mute{
	border: none;
}
.indic{
	width:50%;
}
.infos{
	width: 400px;
}
.text{
	text-align: right;
	padding-right: 20px;
	border: 1px solid #d6d6d6;
	background: #e8e6e6;
	padding-top: 5px;
}
.footer{
	border-top: 1px solid black;
	height: 200px;
}
.center{
	text-align: center;
}
.center em{
	font-size: 13px;
}
.celule{
	background-color: #e8e6e6;
	width: 100px;
	outline: none;
	text-align: center;
	border: 0px;	
	margin: 0px;
}
.celuletotal{
	font-weight: bold;
}
input::-webkit-inner-spin-button,
input::-webkit-outer-spin-button { 
	-webkit-appearance: none;
	margin:0;
}
m{}
table {
  border-collapse: collapse;
}
input{
	border: 0px;
	width: 100%;
}
</style>