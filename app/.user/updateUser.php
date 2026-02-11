<?php 
/**
 * Mise à jour d'un utilisateur
 * SÉCURISÉ: Vérification des privilèges + CSRF
 */

require('../../data/backdb.php');
require('../../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// SÉCURITÉ: Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Méthode non autorisée. Ce formulaire doit être soumis via POST.');
}

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
		$privilege = "superadmin";
	}elseif($level == 2) {
		$privilege = "administrator";
	}elseif($level == 3) {
		$privilege = "registrar";
	}elseif($level == 4) {
		$privilege = "comptabilite";
	}elseif($level == 5) {
		$privilege = "media";
	}elseif($level == 6) {
		$privilege = "chef_mention";
	}elseif($level == 7) {
		$privilege = "teacher";
	}elseif($level == 8) {
		$privilege = "student";
	} else {
		$privilege = "registrar";
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
	

	// Récupérer student_id et teacher_uid du formulaire
	$student_id_post = trim($_POST['student_id'.$id] ?? '');
	$teacher_uid_post = trim($_POST['teacher_uid'.$id] ?? '');

	// Déterminer user_type
	$user_type_map = [
		1 => 'superadmin', 2 => 'admin', 3 => 'registrar', 4 => 'comptabilite',
		5 => 'media', 6 => 'chef_mention', 7 => 'teacher', 8 => 'student'
	];
	$user_type = $user_type_map[$level] ?? 'admin';

	// Détecter les colonnes disponibles
	$columns = [];
	try {
		$colStmt = $dtb->query("SHOW COLUMNS FROM compt_utilisateur");
		while ($col = $colStmt->fetch()) { $columns[] = $col['Field']; }
	} catch (PDOException $e) { /* ignorer */ }

	// Construire dynamiquement le SET
	$setClauses = [
		'nom=:nom', 'prenom=:prenom', 'post=:post', 'mail=:mail',
		'level=:level', 'privilege=:privilege', 'pseudo=:pseudo',
		'photos=:photos', 'etat=:etat', 'update_user=:update_user', 'date_entry=:date_entry'
	];
	$params = [
		'nom' => $nom, 'prenom' => $prenom, 'post' => $post, 'mail' => $mail,
		'level' => $level, 'privilege' => $privilege, 'pseudo' => $pseudo,
		'photos' => $photosname, 'etat' => $etat, 'update_user' => $updateUser_user,
		'date_entry' => $date_entry, 'id' => $id
	];

	if ($passwordBrut != "") {
		$setClauses[] = 'password=:password';
		$params['password'] = $password;
	}

	if (in_array('user_type', $columns)) {
		$setClauses[] = 'user_type=:user_type';
		$params['user_type'] = $user_type;
	}
	if (in_array('student_id', $columns) && !empty($student_id_post)) {
		$setClauses[] = 'student_id=:student_id';
		$params['student_id'] = $student_id_post;
	}
	if (in_array('teacher_uid', $columns) && !empty($teacher_uid_post)) {
		$setClauses[] = 'teacher_uid=:teacher_uid';
		$params['teacher_uid'] = $teacher_uid_post;
	}

	$sql = "UPDATE compt_utilisateur SET " . implode(', ', $setClauses) . " WHERE id=:id";
	$stmt = $dtb->prepare($sql);
	$stmt->execute($params);

	// Invalider le cache session de student_id si c'est l'utilisateur courant
	if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
		unset($_SESSION['cached_student_id']);
	}

	header('location:../../src/creat.account');
 ?>