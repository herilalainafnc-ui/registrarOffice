<?php 

require ('../../data/backdb.php');

	$nom = $_POST['nom'];
	$prenom = $_POST['prenom'];
	$mail = $_POST['mail'];
	$pseudo = $_POST['pseudo'];
	$post = $_POST['post'];
	
	$passwordBrut = $_POST['password'];
	$salt = 'fixing_password';
	$password = hash('sha256', $passwordBrut. $salt);

	$confirmPass = $_POST['confirmpass'];
	$photos = $_FILES['photos']['name'];
	$photos_tmp = $_FILES['photos']['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG');
	$extension_photos = strrchr($photos,".");
	$photos_dest = '../photosuser/';
	$etat = 1;
	$photosname = $prenom.$extension_photos;
	
	$level = $_POST['level'];


	if ($level == 1) {
		$privilege = "administrator";
	}elseif($level == 2) {
		$privilege = "registrar";
	}elseif($level == 3) {
		$privilege = "user";
	}elseif($level == 4) {
		$privilege = "visitor";
	}

	$theme = 'Blue';
	
	
	in_array($extension_photos, $extension);
	move_uploaded_file($photos_tmp, $photos_dest.$photosname);

	$insertuser = $dtb->prepare("INSERT INTO compt_utilisateur(
			nom,
			prenom,
			post,
			pseudo,
			mail,
			password,
			privilege,
			photos,
			etat,
			theme
		) VALUES(
			:nom,
			:prenom,
			:post,
			:pseudo,
			:mail,
			:password,
			:privilege,
			:photos,
			:etat,
			:theme
)");$insertuser->execute(array(
			'nom' => $nom,
			'prenom' => $prenom,
			'post' => $post,
			'pseudo' => $pseudo,
			'mail' => $mail,
			'password' => $password,
			'privilege' => $privilege,
			'photos' => $photosname,
			'etat' => $etat,
			'theme' => $theme
));
	


 header('location:../../src/creat.account.php');

?>
