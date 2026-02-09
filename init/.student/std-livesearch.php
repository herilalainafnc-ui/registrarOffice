<?php 
	require('../../data/backdb.php');
	// Calcul du chemin de base pour les liens absolus
	$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_app_root, strlen($_doc_root));
	if ($app_base === false) $app_base = '';
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

	$recupsdt = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id LIKE "%'.$input.'%" OR student_nom LIKE "%'.$input.'%" OR student_prenom LIKE "%'.$input.'%" OR sex LIKE "%'.$input.'%" OR student_email LIKE "%'.$input.'%" OR student_tel LIKE "%'.$input.'%" OR religion LIKE "%'.$input.'%" AND remove != 1 ORDER BY student_id DESC limit 100');
	
	}elseif (isset($_POST['filter']) AND isset($_POST['channel'])) {
			$filter = $_POST['filter'];
			$channel = $_POST['channel'];

	$recupsdt = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE '.$filter.' LIKE "%'.$channel.'%" AND remove != 1 ORDER BY student_id DESC limit 800');

	}

	$sdt_nbLivesearch = 1;
	while ($sdt_list = $recupsdt->fetch()) {
 ?>								
		<tr id="std_<?=$sdt_nb?>" class="hover:bg-slate-600 text-slate-100">	
			<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_id']?></div></a></td>
			<td class="relative"><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=strtoupper($sdt_list['student_nom'])?>
			<?php if($sdt_list['new_student'] == 1){ echo "&nbsp;&nbsp;&nbsp;<span class='badge bg-slate-900 border-1 border-slate-700 text-slate-400 absolute top-[1px]'>Nouveau</span>";} ?>
			</div></a></td>
			<td><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_prenom']?></div></a></td>
			<td><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_envisage']?></div></a></td>
			<td><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_option']?></div></a></td>
			<td><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['annee_scolaire']?></div></a></td>
			<td><a href="<?=$app_base?>/src/student?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?php
				if ($sdt_list['annee_etude']==0) {
					echo "Remise à niveau";
				}elseif($sdt_list['annee_etude'] > 0 AND $sdt_list['annee_etude'] < 4) {
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