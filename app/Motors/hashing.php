<?php 
	require ('../../data/backdb.php');

// HASH PASSWORD FOR USER
	
	$stmt = $dtb->query('SELECT * FROM compt_utilisateur');
	
	$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$salt = 'fixing_password';

	foreach ($users as $user) {
		$id = $user['id'];
		$nom = $user['nom'];
		$prenom = $user['prenom'];
		$post = $user['post'];
		$pseudo = $user['pseudo'];
		$hashedPassword = hash('sha256', $user['password'] . $salt);
		$privilege = $user['privilege'];
		$photos = $user['photos'];
		$etat = $user['etat'];
		$theme = $user['theme'];

		

		echo '<br>'. $user['prenom'] . " : " . $hashedPassword;

	    $creatStmt = $dtb->prepare("INSERT INTO compt_user (
			id,
			nom,
			prenom,
			post,
			pseudo,
			password,
			privilege,
			photos,
			etat,
			theme
	    )VALUES(
	    	:id,
			:nom,
			:prenom,
			:post,
			:pseudo,
			:password,
			:privilege,
			:photos,
			:etat,
			:theme

	    )");/*$creatStmt->execute(array(
	    	'id' => $id,
			'nom' => $nom,
			'prenom' => $prenom,
			'post' => $post,
			'pseudo' => $pseudo,
			'password' => $hashedPassword,
			'privilege' => $privilege,
			'photos' => $photos,
			'etat' => $etat,
			'theme' => $theme

	    ));*/

	}
 ?>