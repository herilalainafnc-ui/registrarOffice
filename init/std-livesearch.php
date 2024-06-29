<?php 
	require('../data/backdb.php');
 ?>
<table class="simpleTbl">
	<thead class="bg-slate-500 text-white">
		<tr>
			<th>ID</th>
			<th>Nom</th>
			<th>Prénom</th>
			<th>Mention</th>
			<th>Parcours</th>
			<th>Année</th>
			<th>Niveau</th>
		</tr>
	</thead>
	<tbody>
<?php
	
	if (isset($_POST['input'])) {
		
		$input = $_POST['input'];

	$recupsdt = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE student_id LIKE "%'.$input.'%" OR student_nom LIKE "%'.$input.'%" OR student_prenom LIKE "%'.$input.'%" OR sex LIKE "%'.$input.'%" OR student_email LIKE "%'.$input.'%" OR student_tel LIKE "%'.$input.'%" OR religion LIKE "%'.$input.'%" ORDER BY student_id DESC limit 100');
	
	}elseif (isset($_POST['filter']) AND isset($_POST['channel'])) {
			$filter = $_POST['filter'];
			$channel = $_POST['channel'];

	$recupsdt = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE '.$filter.' LIKE "%'.$channel.'%" ORDER BY student_id DESC limit 800');

	}

	$sdt_nbLivesearch = 1;
	while ($sdt_list = $recupsdt->fetch()) {
 ?>								
		<tr id="std_<?=$sdt_nb?>" class="hover:bg-slate-300 hover:text-slate-800">	
			<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_id']?></div></a></td>
			<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=strtoupper($sdt_list['student_nom'])?></div></a></td>
			<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_prenom']?></div></a></td>
			<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_envisage']?></div></a></td>
			<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_option']?></div></a></td>
			<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['annee_scolaire']?></div></a></td>
			<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?php
				if($sdt_list['annee_etude']<=3) {
					echo "Licence ".$sdt_list['annee_etude'];
				}else{
					echo "Master ".($sdt_list['annee_etude']-3);
				} ?></div></a>
			</td>
		</tr>

<?php
	$sdt_nbLivesearch++;
	}
 ?>								

	</tbody>
</table>