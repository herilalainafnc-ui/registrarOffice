<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
require_once(__DIR__ . '/../../data/cours.sigle.php');
exigerConnexion();
exigerPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès à la liste des cours.");
	require('../../data/backdb.php');
	require_once('../../data/middleware.php');
	require_once(__DIR__ . '/../../data/perimetre.php'); // l'autre cycle reste invisible
	initMiddleware($dtb);
	$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_app_root, strlen($_doc_root));
	if ($app_base === false) $app_base = '';

	// Vérifier si c'est un professeur (pour filtrer ses cours uniquement)
	$isTeacherView = isTeacher() && !isAdmin() && !isRegistrar();
	$teacherUid = $isTeacherView ? getTeacherUid() : null;
 ?>
<table class="simpleTbl stack-cards">
	<thead class="bg-slate-500 text-white">
		<tr>
			<th>Sigle</th>
			<th>Cours</th>
			<th>Mention</th>
			<th>Parcours</th>
			<th>Crédit</th>
			<th>Catégorie</th>
			<th>Niveau</th>
			<th>Semestre</th>
			<th>Enseignant</th>
		</tr>
	</thead>
	<tbody>


 <?php 
if (isset($_POST['trie'])) {
		
	// Colonne de tri non liable en paramètre : liste blanche stricte
	$allowedSort = ['Sigle', 'title', 'dep_desc', 'nb_crd', 'category', 'yearlevel', 'semester', 'id_teacher'];
	$trie = in_array($_POST['trie'], $allowedSort, true) ? $_POST['trie'] : 'title';

	/* Trier par « Sigle » doit suivre le code affiche, pas la cle technique. */
	coursSigleAliasInstaller($dtb);
	$trie = $trie === 'Sigle' ? "COALESCE(NULLIF(TRIM(sigle_affiche), ''), Sigle)" : $trie;
	
	// Filtrer par professeur si nécessaire
	if ($isTeacherView && $teacherUid) {
		$recupcours = $dtb->prepare('SELECT * FROM t_2023_cours WHERE remove != 1 AND id_teacher = :teacher_uid' . perimetreSqlNiveau('yearlevel') . ' ORDER BY '.$trie.' LIMIT 800');
		$recupcours->execute(['teacher_uid' => $teacherUid]);
	} else {
		$recupcours = $dtb->query('SELECT * FROM t_2023_cours WHERE remove != 1' . perimetreSqlNiveau('yearlevel') . ' ORDER BY '.$trie.' limit 800');
	}
}
	$cours_nb = 1;
	while ($cours_list = $recupcours->fetch()) {
 ?>
 	<tr id="cours_<?=$cours_nb?>" class="hover:bg-slate-300 hover:bg-slate-600 text-slate-100">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?= htmlspecialchars(coursSigleAffiche($cours_list), ENT_QUOTES, 'UTF-8') ?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['title']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['dep_desc']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
if($cours_list['parcours'] == "all") { echo "Tronc comun";}else{
	$findParcours = $dtb->query('SELECT * FROM filiere_parcours WHERE shortcode = "'.$cours_list['parcours'].'"');
	$showParcours = $findParcours->fetch();
	if(!empty($showParcours)) {
		echo $showParcours['description'];
	}
}
								?></div></a></td>
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
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php
										if ($cours_list['yearlevel']==0) {
											echo "Remise à niveau";
										}elseif($cours_list['yearlevel']>0 AND $cours_list['yearlevel']<=3) {
											echo "Licence ".$cours_list['yearlevel'];
										}else{
											echo "Master ".($cours_list['yearlevel']-3);
										} ?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['semester']?></div></a></td>
									<td><a href="<?=$app_base?>/cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
$findTeach = $dtb->query('SELECT * FROM teacher WHERE uid = "'.$cours_list['id_teacher'].'"');
$showTeach = $findTeach->fetch();
if(!empty($showTeach)) {
	echo strtoupper($showTeach['name'])." ".$showTeach['lastName'];
}
?></div></a></td>
								</tr>

 <?php
	$cours_nb++;
	}
 ?>

	</tbody>
</table>		
