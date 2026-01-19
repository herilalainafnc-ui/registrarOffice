<?php 
/**
 * Ajout d'un nouvel utilisateur
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
Middleware::logSecurityEvent('user_create_attempt', [
    'by_user' => $_SESSION['user_id'] ?? null
]);

	$nom = trim($_POST['nom']);
	$prenom = trim($_POST['prenom']);
	$mail = trim($_POST['mail']);
	$pseudo = trim($_POST['pseudo']);
	$post = trim($_POST['post'] ?? '');
	
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
	
	$level = (int)$_POST['level'];


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
		$level = 4;
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
