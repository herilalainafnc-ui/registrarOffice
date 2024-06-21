<?php 
	require('../data/backdb.php');	
	
	if (isset($_POST['input'])) {
		
		$input = $_POST['input'];


 ?>
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
									<th>Enseignant</th>
								</tr>
							</thead>
							<tbody>
<?php
	$recupcours = $dtb->query('SELECT * FROM t_2023_cours WHERE Sigle LIKE "%'.$input.'%" OR title LIKE "%'.$input.'%" OR dep_desc LIKE "%'.$input.'%" OR nb_crd LIKE "%'.$input.'%" OR title_english LIKE "%'.$input.'%" ORDER BY title limit 200');

	$cours_nb = 1;
	while ($cours_list = $recupcours->fetch()) {
 ?>								
								<tr id="cours_<?=$cours_nb?>" class="hover:bg-slate-300 hover:text-slate-800">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['Sigle']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['title']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['dep_desc']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['nb_crd']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['category']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['yearlevel']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['semester']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
$findTeach = $dtb->query('SELECT * FROM teacher WHERE uid = "'.$cours_list['id_teacher'].'"');
$showTeach = $findTeach->fetch();
echo strtoupper($showTeach['name'])." ".$showTeach['lastName'];
 ?></div></a></td>
								</tr>

<?php
	$cours_nb++;
	}
 ?>								

							</tbody>
						</table>
<?php 
	}
 ?>				

