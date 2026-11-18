<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();
exigerPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès à la liste des enseignants.");
	require('../../data/backdb.php');
	$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_app_root, strlen($_doc_root));
	if ($app_base === false) $app_base = '';
 ?>
<table class="simpleTbl stack-cards">
	<thead class="bg-slate-500 text-white">
		<tr>
			<th>ID</th>
			<th>Nom</th>
			<th>Prénom</th>
			<th>address</th>
			<th>Téléphone</th>
			<th>Email</th>
			
		</tr>
	</thead>
	<tbody>
<?php

	if (isset($_POST['input'])) {
		
		$input = $_POST['input'];

		$like = '%'.$input.'%';
		$stmt = $dtb->prepare('SELECT * FROM teacher WHERE (teacher_id LIKE ? OR name LIKE ? OR lastName LIKE ? OR email LIKE ? OR phone LIKE ?) AND remove != 1 ORDER BY teacher_id DESC LIMIT 200');
		$stmt->execute([$like, $like, $like, $like, $like]);
		$recupprof = $stmt;

	}elseif (isset($_POST['filter']) AND isset($_POST['channel'])) {
			// Nom de colonne non liable en paramètre : liste blanche stricte
			$allowedFilters = ['address', 'religion'];
			$filter = in_array($_POST['filter'], $allowedFilters, true) ? $_POST['filter'] : 'address';
			$channel = $_POST['channel'];
		
		$stmt = $dtb->prepare('SELECT * FROM teacher WHERE '.$filter.' LIKE ? AND remove != 1 ORDER BY teacher_id DESC LIMIT 800');
		$stmt->execute(['%'.$channel.'%']);
		$recupprof = $stmt;
	}

	$prof_nb = 1;
	while ($prof_list = $recupprof->fetch()) {
 ?>								
	<tr id="prof_<?=$prof_nb?>" class="hover:bg-slate-600 text-slate-100">	
		<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="<?=$app_base?>/professor?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['teacher_id']?></div></a></td>
		<td><a href="<?=$app_base?>/professor?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=strtoupper($prof_list['name'])?></div></a></td>
		<td><a href="<?=$app_base?>/professor?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['lastName']?></div></a></td>
		<td><a href="<?=$app_base?>/professor?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['address']?></div></a></td>
		<td><a href="<?=$app_base?>/professor?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['phone']?></div></a></td>
		<td><a href="<?=$app_base?>/professor?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['email']?></div></a></td>
		
	</tr>

<?php
	$prof_nb++;
	}
 ?>								

	</tbody>
</table>