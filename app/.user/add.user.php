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

	// Récupérer les liaisons étudiant/professeur
	$teacher_uid = !empty($_POST['teacher_uid']) ? (int)$_POST['teacher_uid'] : null;
	$student_id = !empty($_POST['student_id']) ? trim($_POST['student_id']) : null;
	$user_type = 'staff';

	if ($level == 1) {
		$privilege = "superadmin";
		$user_type = 'superadmin';
	}elseif($level == 2) {
		$privilege = "administrator";
		$user_type = 'admin';
	}elseif($level == 3) {
		$privilege = "registrar";
		$user_type = 'registrar';
	}elseif($level == 4) {
		$privilege = "comptabilite";
		$user_type = 'comptabilite';
	}elseif($level == 5) {
		$privilege = "media";
		$user_type = 'media';
	}elseif($level == 6) {
		$privilege = "chef_mention";
		$user_type = 'chef_mention';
	}elseif($level == 7) {
		$privilege = "teacher";
		$user_type = 'teacher';
	}elseif($level == 8) {
		$privilege = "student";
		$user_type = 'student';
	} else {
		$privilege = "registrar";
		$level = 3;
		$user_type = 'registrar';
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
			theme,
			level,
			user_type,
			teacher_uid,
			student_id
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
			:theme,
			:level,
			:user_type,
			:teacher_uid,
			:student_id
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
			'theme' => $theme,
			'level' => $level,
			'user_type' => $user_type,
			'teacher_uid' => $teacher_uid,
			'student_id' => $student_id
));
	


 header('location:../../src/creat.account.php');

?>
