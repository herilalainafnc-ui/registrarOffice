<?php 
	require('../../data/backdb.php');	

 ?>
<table class="simpleTbl">
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

		$recupprof = $dtb->query('SELECT * FROM teacher WHERE teacher_id LIKE "%'.$input.'%" OR name LIKE "%'.$input.'%" OR lastName LIKE "%'.$input.'%" OR email LIKE "%'.$input.'%" OR phone LIKE "%'.$input.'%" ORDER BY teacher_id DESC limit 200');	

	}elseif (isset($_POST['filter']) AND isset($_POST['channel'])) {
			$filter = $_POST['filter'];
			$channel = $_POST['channel'];
		
		$recupprof = $dtb->query('SELECT * FROM teacher WHERE '.$filter.' LIKE "%'.$channel.'%" ORDER BY teacher_id DESC limit 800');
	}

	$prof_nb = 1;
	while ($prof_list = $recupprof->fetch()) {
 ?>								
	<tr id="prof_<?=$prof_nb?>" class="hover:bg-slate-300 hover:bg-slate-600 text-slate-100">	
		<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['teacher_id']?></div></a></td>
		<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=strtoupper($prof_list['name'])?></div></a></td>
		<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['lastName']?></div></a></td>
		<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['address']?></div></a></td>
		<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['phone']?></div></a></td>
		<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['email']?></div></a></td>
		
	</tr>

<?php
	$prof_nb++;
	}
 ?>								

	</tbody>
</table>