<?php 
	require('../init/.forPrint/top.forPrint.php');
require_once(__DIR__ . '/../../data/cours.sigle.php'); 
	$cours_id = $_GET['cours_id'];
	$yearRemiseNotes = $_GET['yearRemiseNotes'];
	$semesterRemiseNotes = isset($_GET['semesterRemiseNotes']) ? trim((string)$_GET['semesterRemiseNotes']) : '';

	$findCours = $dtb->query('SELECT * FROM t_2023_cours WHERE id = '.$dtb->quote($cours_id).'');

	$showCours = $findCours->fetch();

	$printName = "NOTES-STD-IN_".$showCours['title'];
	if ($semesterRemiseNotes !== '') {
		$printName .= "_S".$semesterRemiseNotes;
	}
 ?>

<div class="text-xs text-center my-2" style="border: 1px solid #8e9bb2;">
	<p class="text-[17px] bg-blue-200 h-[20px]">Remise de notes du cours - [ <b><?=$showCours['title']?></b> ]</p>
	<div class="w-full p-2" style="display: flex;">

		<div class="text-right w-3/12">
			<label>Sigle - </label><br>
			<label>Catégorie - </label><br>
			<label>Enseignant(e) - </label><br>
			<label>Année - </label><br>			
		</div>
		<div class="w-4/12 text-left pl-1">
			
			<b><?= htmlspecialchars(coursSigleAffiche($showCours), ENT_QUOTES, 'UTF-8') ?></b><br>
			<b><?php
if ($showCours['category'] == 0){
	echo "Général";
}elseif ($showCours['category'] == 1) {
	echo "Majeur";
}elseif ($showCours['category'] == -1 OR $showCours['category'] == 2) {
	echo "Selective";
}elseif ($showCours['category'] == 3) {
	echo "Additionnel";
}elseif ($showCours['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
			?></b><br>
			<b><?php 
	
	$findTeacher = $dtb->query('SELECT * FROM teacher WHERE uid ='.$dtb->quote($showCours['id_teacher']).'');
	$showTeacher = $findTeacher->fetch();
	
	echo $showTeacher['name']." ".$showTeacher['lastName'];
			?></b><br>
			<b><?=$yearRemiseNotes?></b><br>		
		</div>
		<div class="text-right w-3/12">
			<label>Crédit - </label><br>
			<label>Niveau - </label><br>
			<label>Semestre - </label><br>		
		</div>
		<div class="w-4/12 text-left pl-1">
			<b><?=$showCours['nb_crd']?></b><br>
			<b><?php if($showCours['yearlevel'] <=3 ){
				echo "Licence ".$showCours['yearlevel'];
			}elseif ($showCours['yearlevel'] > 3){
				echo "Master ".($showCours['yearlevel'] - 3);
			}
				?></b><br>
			<b><?php
				$semesterValue = $semesterRemiseNotes !== '' ? $semesterRemiseNotes : (string)($showCours['semester'] ?? '');
				if ($semesterValue === '3') {
					echo "Semestre d'été";
				} elseif ($semesterValue === '4') {
					echo "Semestre d'hiver";
				} elseif ($semesterValue !== '') {
					echo "Semestre ".htmlspecialchars($semesterValue);
				}
			?></b><br>
		</div>
	</div>
</div>

<b>Liste des étudiants</b>
 <table class="tbl">
 	<thead>
 		<tr>
 			<th class="py-1 border-l border-slate-400 w-[30px]">No</th>
 			<th class="py-1 border-l border-slate-400 w-[65px]">Matricule</th>
 			<th class="py-1 border-l border-slate-400">Noms</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Niveau</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Quiz<br>__%</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Recherche<br>__%</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Autres<br>__%</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Examen<br>Mi sem__%</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Examen<br>Final__%</th>
 			<th class="py-1 border-l border-slate-400 w-[55px]">Notes<br>Final/20</th>
 		</tr>
 	</thead>
 	<tbody>
 <?php 
 $semesterClause = '';
 if ($semesterRemiseNotes !== '') {
	$semesterClause = ' AND ((s.session_semester IS NOT NULL AND TRIM(s.session_semester) <> "" AND REPLACE(LOWER(TRIM(s.session_semester)), "s", "") = '.$dtb->quote($semesterRemiseNotes).') OR ((s.session_semester IS NULL OR TRIM(s.session_semester) = "") AND n.semester = '.$dtb->quote($semesterRemiseNotes).'))';
 }
 $findStdInCours = $dtb->query('SELECT n.*, s.session_name, s.session_semester, s.session_year FROM t_2023_notes n LEFT JOIN t_2023_session s ON s.session_id = n.session_id WHERE n.id_cours = '.$dtb->quote($cours_id).' AND n.annee_scolaire = '.$dtb->quote($yearRemiseNotes).''.$semesterClause.' AND n.remove = 0 ORDER BY n.session_id ASC, n.student_id ASC');
 $nbr = 1;
 while($showStdInCours = $findStdInCours->fetch()) {
	$jer = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE student_id=".$dtb->quote($showStdInCours['student_id'])." ORDER BY remove ASC, id ASC LIMIT 1");
	$apotr = $jer->fetch();

	// Exclure les etudiants suspendus et retires de l'universite.
	if (
		$apotr
		&& (
			(isset($apotr['suspended']) && (int)$apotr['suspended'] === 1)
			|| (isset($apotr['retrait_universite']) && (int)$apotr['retrait_universite'] !== 0)
		)
	) {
		continue;
	}
  ?>
 		<tr class="text-[11px]" style="page-break-inside: avoid;">
 			<td class="py-1 border-l border-t border-slate-400"><?=$nbr?></td>
 			<td class="py-1 border-l border-t border-slate-400"><?=$showStdInCours['student_id']?></td>
 			<td class="py-1 border-l border-t border-slate-400"><?php
if($apotr){
	if(is_null($apotr['student_nom']) AND is_null($apotr['student_prenom'])){
		echo "<em style='color:red'>Non défini</em>";
	}else{
		echo $apotr['student_nom']." ".$apotr['student_prenom'];
	}
}else{
	echo "<em style='color:red'>Etudiant non inscrit dans la base!!</em>";
}
					?></td>
 			<td class="py-1 border-l border-t border-slate-400">
<?php 
if($apotr){
	if($apotr['annee_etude'] <=3 ){
		echo "Licence ".$apotr['annee_etude'];
	}elseif ($apotr['annee_etude'] > 3){
		echo "Master ".($apotr['annee_etude'] - 3);
	}
}else{
	echo "-";
}
				?></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<?php require_once __DIR__ . '/../../data/notes.statut.php'; $stNote = noteStatut($showStdInCours['grade'], noteEstCoursValidation($showStdInCours)); ?><td class="py-1 border-l border-t border-slate-400 text-bold text-center" style="color: <?= $stNote['code'] === 'NN' ? '#475569' : (in_array($stNote['code'], ['S', 'V'], true) ? 'blue' : 'red') ?>"><?= $stNote['libelle'] ?></td>
 		</tr>
 <?php
 $nbr++;
	}
  ?>
 	</tbody>
 </table>

 <b><?php if ($nbr > 2) { echo ($nbr-1)." étudiants"; }else{ echo ($nbr-1)." étudiant"; } ?></b>


 <div class="flex w-full text-right">
 	<div class="w-9/12">
 		
 	</div>
 	<div class="w-3/12 pb-16 border-b border-slate-600">
 		<p class="text-xs">Signature est Noms de l'enseignant(e).</p>

 	</div>
 </div><?php require('../init/.forPrint/foot.forPrint.php'); ?>