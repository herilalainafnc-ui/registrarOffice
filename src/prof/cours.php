<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>

<table class="simpleTbl">
							<thead class="<?=$bg_four_color?> text-white">
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
	require_once(__DIR__ . '/../../data/cours.sigle.php');
	require_once(__DIR__ . '/../../data/perimetre.php'); // ses cours de l'autre cycle restent invisibles
	$recupcours = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id_teacher = :uid' . perimetreSqlNiveau('yearlevel') . ' ORDER BY title LIMIT 200');
	$recupcours->execute(['uid' => $uid]);	
	
	$cours_nb = 1;
	while ($cours_list = $recupcours->fetch()) {
 ?>								
								<tr id="cours_<?=$cours_nb?>" class="hover:<?=$bg_six_color?> hover:text-slate-800">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?= htmlspecialchars(coursSigleAffiche($cours_list), ENT_QUOTES, 'UTF-8') ?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['title']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['dep_desc']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['nb_crd']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
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
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['yearlevel']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['semester']?></div></a></td>
<?php
	$cours_nb++;
	}
 ?>								

							</tbody>
						</table>


</div>
