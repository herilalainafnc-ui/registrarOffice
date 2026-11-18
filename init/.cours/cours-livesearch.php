<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
require_once(__DIR__ . '/../../data/cours.sigle.php');
exigerConnexion();
exigerPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès à la liste des cours.");
	require('../../data/backdb.php');
	require_once('../../data/middleware.php');
	require_once(__DIR__ . '/../../data/perimetre.php');
	initMiddleware($dtb);
	$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_app_root, strlen($_doc_root));
	if ($app_base === false) $app_base = '';

	// Vérifier si c'est un professeur (pour filtrer ses cours uniquement)
	$isTeacherView = isTeacher() && !isAdmin() && !isRegistrar();
	$teacherUid = $isTeacherView ? getTeacherUid() : null;
	$teacherFilter = ($isTeacherView && $teacherUid) ? ' AND id_teacher = ' . (int) $teacherUid : '';
	$perimetre = perimetreSqlNiveau('yearlevel'); // l'autre cycle reste invisible
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
	if (isset($_POST['input'])) {
		
		$input = $_POST['input'];

		$like = '%'.$input.'%';
		/* On cherche aussi sur le code affiche : apres le renommage, le
		   personnel tape le nouveau sigle, pas la cle technique. */
		coursSigleAliasInstaller($dtb);
		$stmt = $dtb->prepare('SELECT * FROM t_2023_cours WHERE (Sigle LIKE ? OR sigle_affiche LIKE ? OR title LIKE ? OR dep_desc LIKE ? OR nb_crd LIKE ? OR title_english LIKE ?) AND remove != 1'.$teacherFilter.$perimetre.' ORDER BY title LIMIT 200');
		$stmt->execute([$like, $like, $like, $like, $like, $like]);
		$recupcours = $stmt;
 
	}elseif (isset($_POST['filter']) AND isset($_POST['channel'])) {
			// Nom de colonne non liable en paramètre : liste blanche stricte
			$allowedFilters = ['dep_desc', 'category', 'yearlevel'];
			$filter = in_array($_POST['filter'], $allowedFilters, true) ? $_POST['filter'] : 'dep_desc';
			$channel = $_POST['channel'];
		
		$stmt = $dtb->prepare('SELECT * FROM t_2023_cours WHERE '.$filter.' LIKE ? AND remove != 1'.$teacherFilter.$perimetre.' ORDER BY title LIMIT 800');
		$stmt->execute(['%'.$channel.'%']);
		$recupcours = $stmt;
	}

	$cours_nb = 1;
	while ($cours_list = $recupcours->fetch()) {
 ?>								
								<tr id="cours_<?=$cours_nb?>" class="hover:bg-slate-600 text-slate-100">	
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

