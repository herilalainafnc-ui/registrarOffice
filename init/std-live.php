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
if (isset($_POST['trie'])) {
		
	$trie = $_POST['trie'];
	
	$recupsdt = $dtb->query('SELECT * FROM etudiant_second_semester_23 ORDER BY '.$trie.' limit 800');
}
	$sdt_nbLive = 1;
	while ($sdt_list = $recupsdt->fetch()) {
 ?>								
		<tr id="std_<?=$sdt_nb?>" class="hover:bg-slate-300 hover:text-slate-800">	
			<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_id']?></div></a></td>
			<td><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=strtoupper($sdt_list['student_nom'])?></div></a></td>
			<td><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_prenom']?></div></a></td>
			<td><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_envisage']?></div></a></td>
			<td><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_option']?></div></a></td>
			<td><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['annee_scolaire']?></div></a></td>
			<td><a target="_blank" href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full">L<?=$sdt_list['annee_etude']?></div></a></td>
		</tr>

<?php
	$sdt_nbLive++;
	}
 ?>								

	</tbody>
</table>