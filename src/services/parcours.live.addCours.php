<select class="input w-full" name="parcours" id="parcours">
	<option value="all">TRONC COMMUN</option>
<?php
	require('../../data/backdb.php');

	$mention = $_POST['mentionSelect'];

	$findOption = $dtb->query('SELECT * FROM filiere_parcours WHERE departement = "'.$mention.'"');
	while ($showO = $findOption->fetch()) {
 ?>
		<option value="<?=$showO['shortcode']?>"><?=$showO['description']?></option>
<?php
	}
 ?>
</select>
