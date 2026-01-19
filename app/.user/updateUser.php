<?php 
/**
 * Mise à jour d'un utilisateur
 * SÉCURISÉ: Vérification des privilèges + CSRF
 */

require('../../data/backdb.php');
require('../../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// SÉCURITÉ: Vérifier que l'utilisateur est admin ou registrar
if (!isRegistrar()) {
    http_response_code(403);
    die('Accès refusé: privilèges insuffisants');
}

// SÉCURITÉ: Vérifier le token CSRF
require_csrf();

// Log de l'action
Middleware::logSecurityEvent('user_update_attempt', [
    'by_user' => $_SESSION['user_id'] ?? null,
    'target_user' => $_GET['id'] ?? null
]);

	$id = (int)$_GET['id'];
	$nom = trim($_POST['nom'.$id] ?? '');
	$prenom = trim($_POST['prenom'.$id] ?? '');
	$post = trim($_POST['post'.$id] ?? '');
	$mail = trim($_POST['mail'.$id] ?? '');
	$level = (int)($_POST['level'.$id] ?? 4);
	$pseudo = trim($_POST['pseudo'.$id] ?? '');
	
	if (isset($_POST['etat'.$id])) {
		$etat = 1;
	}else{
		$etat = 0;
	}

	$passwordBrut = $_POST['password'.$id] ?? '';
	$salt = 'fixing_password';
	
	$password = hash('sha256', $passwordBrut. $salt);


	if ($level == 1) {
		$privilege = "administrator";
	}elseif($level == 2) {
		$privilege = "registrar";
	}elseif($level == 3) {
		$privilege = "user";
	}elseif($level == 4) {
		$privilege = "visitor";
	} else {
		$privilege = "visitor";
	}

	$updateUser_user = (int)$_GET['rg_id'];
	$date_entry = date('Y-m-d');
	
	$oldPhotos = $_POST['oldPhotos'.$id] ?? '';

	$inputPhotos = 'photos'.$id;

	$photos = $_FILES[$inputPhotos]['name'];
	$photos_tmp = $_FILES[$inputPhotos]['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG');
	$extension_photos = strrchr($photos,".");
	$photos_dest = '../photosuser/';

	if (isset($photos) AND !empty($photos)) {	

		$photosname = $prenom.$extension_photos;
		in_array($extension_photos, $extension);
		move_uploaded_file($photos_tmp, $photos_dest.$photosname);
	
	}else{

		$photosname = $oldPhotos;
	
	}
	

	if ($passwordBrut == "") {
		$updateUserNotPwd = $dtb->prepare("UPDATE compt_utilisateur SET
		nom=:nom,
		prenom=:prenom,
		post=:post,
		mail=:mail,
		level=:level,
		privilege=:privilege,
		pseudo=:pseudo,
		photos=:photos,
		etat=:etat,
		update_user=:update_user,
		date_entry=:date_entry

		WHERE id=:id");

		$updateUserNotPwd->bindParam(':nom', $nom, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':prenom', $prenom, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':post', $post, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':mail', $mail, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':level', $level, PDO::PARAM_INT);
		$updateUserNotPwd->bindParam(':privilege', $privilege, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':photos', $photosname, PDO::PARAM_STR);
		$updateUserNotPwd->bindParam(':etat', $etat, PDO::PARAM_INT);
		$updateUserNotPwd->bindParam(':update_user', $updateUser_user, PDO::PARAM_INT);
		$updateUserNotPwd->bindParam(':date_entry', $date_entry, PDO::PARAM_STR);
			
		$updateUserNotPwd->bindParam(':id',$id,PDO::PARAM_INT);

		$updateUserNotPwd->execute();

	}else{

		$updateUser = $dtb->prepare("UPDATE compt_utilisateur SET
		nom=:nom,
		prenom=:prenom,
		post=:post,
		mail=:mail,
		level=:level,
		privilege=:privilege,
		pseudo=:pseudo,
		photos=:photos,
		etat=:etat,
		password=:password,
		update_user=:update_user,
		date_entry=:date_entry

		WHERE id=:id");

		$updateUser->bindParam(':nom', $nom, PDO::PARAM_STR);
		$updateUser->bindParam(':prenom', $prenom, PDO::PARAM_STR);
		$updateUser->bindParam(':post', $post, PDO::PARAM_STR);
		$updateUser->bindParam(':mail', $mail, PDO::PARAM_STR);
		$updateUser->bindParam(':level', $level, PDO::PARAM_INT);
		$updateUser->bindParam(':privilege', $privilege, PDO::PARAM_STR);
		$updateUser->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
		$updateUser->bindParam(':photos', $photosname, PDO::PARAM_STR);
		$updateUser->bindParam(':etat', $etat, PDO::PARAM_INT);
		$updateUser->bindParam(':password', $password, PDO::PARAM_STR);
		$updateUser->bindParam(':update_user', $updateUser_user, PDO::PARAM_INT);
		$updateUser->bindParam(':date_entry', $date_entry, PDO::PARAM_STR);
			
		$updateUser->bindParam(':id',$id,PDO::PARAM_INT);

		$updateUser->execute();
	}
	header('location:../../src/creat.account.php');
 ?>