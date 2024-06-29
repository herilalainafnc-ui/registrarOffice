
<div>	
<?php 
	for ($a=1; $a <= $level; $a++) { 
?>
	<div class='p-1 bg-slate-700 hover:bg-slate-600 mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
<b>
		<?php 
		if($a<=3) {
			echo "NIVEAU Licence ".$a;
		}else{
			echo "NIVEAU Master ".($a-3);
		}
		?>		
</b>	

<?php	
		for ($s=1; $s <=2 ; $s++) { 
			
		
 ?>
		<table class="simpleTbl mb-1">
			<thead>
				<tr class="text-center bg-gradient-to-r from-red-500">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="bg-slate-800 text-white">
				<tr>
					<th class="w-20">SIGLE</th>
					<th class="">TITRE DU COURS</th>
					<th class="w-20">CREDITS</th>
					<th class="w-20">Categorie</th>
					<th class="w-20">Notes/20</th>
					<th class="w-[13%]">Supprimé par</th>
					<th class="w-[13%]">Date de suppression</th>
					<th colspan="2" class="w-[29px]"></th>
				</tr>	
			</thead>
			<tbody class="bg-slate-500">
	<?php
	$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout = 0 AND yearlevel='".$a."' AND semester='".$s."' ORDER BY id");
	
	$nbr = 0;
	$nbrMaj = 0;
	$credit = 0;
	$note = 0;
	$notecredit = 0;

	$tMaj = 0;
	$tTMaj = 0;
	$tcredit = 0;
	$tnote = 0;
	$tnotecredit = 0;
	
	if($cours->rowCount() > 0) {
		while ($crs = $cours->fetch()) {
			$note_id = $crs['id'];
			$session_id = $crs['session_id'];
			$annee_scolaire = $crs['annee_scolaire'];
	 ?>
				<tr class="hover:transition-all duration-75 hover:bg-slate-400 hover:text-black">
					<td class="bg-gradient-to-r from-orange-800 to-orange-400"><?=$crs['Sigle']?></td>
					<td><?=$crs['title_cours']?></td>
					<td><?=$crs['credit']?></td>
					<td><?php 
if ($crs['cours_category'] == 0){
	echo "Général";
}elseif ($crs['cours_category'] == 1) {
	echo "Majeur";
}elseif ($crs['cours_category'] == 2) {
	echo "Selective";
}elseif ($crs['cours_category'] == 3) {
	echo "Additionnel";
}else{
	echo "-";
}
						 ?></td>
					<td><?=$crs['grade']?></td>
					<td><?php
					$findUser = $dtb->query('SELECT * FROM compt_utilisateur WHERE id = "'.$crs['last_change_user_id'].'"');
					$showUser = $findUser->fetch(); echo $showUser['prenom'];
				?></td>
					<td><?=$crs['last_change_datetime']?></td>
					<td class="p-0 text-center bg-green-700 w-[60px]"><a href="../app/.student/recup-cours.php?student_id=<?=$student_id?>&id=<?=$id?>&as=<?=$a.$s?>&idSupprCours=<?=$note_id?>&user_id=<?=$rg_id?>">Restaurer</a></td>
					<td class="p-0 text-center bg-red-700 w-[60px]"><a href="../app/.student/del-cours.definitive.php?student_id=<?=$student_id?>&id=<?=$id?>&as=<?=$a.$s?>&idSupprCours=<?=$note_id?>&user_id=<?=$rg_id?>">Effacer</a></td>

				</tr>

	<?php

		$nbr++;
		}
	}
	 ?>			
			</tbody>

		</table>
<?php
		}
	echo "</div>";
	}
?>
</div>

