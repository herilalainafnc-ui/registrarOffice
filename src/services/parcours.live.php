<select id="firstEtd" class="inscInput h-5 text-sm w-full requierd-9 mb-3" name="etude_option" >
<?php
	require('../../data/backdb.php');

	$mention = $_POST['mention'];
	$findOption = $dtb->query('SELECT * FROM filiere_parcours WHERE departement = "'.$mention.'"');
	while ($showO = $findOption->fetch()) {
 ?>
		<option class="bg-slate-200"><?=$showO['description']?></option>
<?php
	}
 ?>
</select>
