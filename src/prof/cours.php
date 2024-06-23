<div class='p-1 bg-slate-700 hover:bg-slate-600 mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>

<table class="simpleTbl">
							<thead class="bg-slate-500 text-white">
								<tr>
									<th>Sigle</th>
									<th>Cours</th>
									<th>Mention</th>
									<th>Crédit</th>
									<th>Catégorie</th>
									<th>Niveau</th>
									<th>Semestre</th>
								</tr>
							</thead>
							<tbody>
<?php
	$recupcours = $dtb->query('SELECT * FROM t_2023_cours WHERE id_teacher="'.$uid.'" ORDER BY title limit 200');	
	
	$cours_nb = 1;
	while ($cours_list = $recupcours->fetch()) {
 ?>								
								<tr id="cours_<?=$cours_nb?>" class="hover:bg-slate-300 hover:text-slate-800">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['Sigle']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['title']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['dep_desc']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['nb_crd']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
if ($cours_list['category'] == 0){
	echo "Général";
}elseif ($cours_list['category'] == 1) {
	echo "Majeur";
}elseif ($cours_list['category'] == -1 OR $cours_list['category'] == 2) {
	echo "Selective";
}elseif ($cours_list['category'] == 3) {
	echo "Additionnel";
}elseif ($cours_list['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
								?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['yearlevel']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['semester']?></div></a></td>
								</tr>

<?php
	$cours_nb++;
	}
 ?>								

							</tbody>
						</table>


</div>