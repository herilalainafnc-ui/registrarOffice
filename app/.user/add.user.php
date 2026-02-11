<?php 
/**
 * Ajout d'un nouvel utilisateur
 * SÉCURISÉ: Vérification des privilèges + CSRF
 */

// Activer l'affichage des erreurs pour le debug (à retirer en production stable)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

// Log de l'action (non-bloquant)
try {
    Middleware::logSecurityEvent('user_create_attempt', [
        'by_user' => $_SESSION['user_id'] ?? null
    ]);
} catch (Exception $e) {
    // Ignorer silencieusement les erreurs de log
}

try {
	$nom = trim($_POST['nom'] ?? '');
	$prenom = trim($_POST['prenom'] ?? '');
	$mail = trim($_POST['mail'] ?? '');
	$pseudo = trim($_POST['pseudo'] ?? '');
	$post = trim($_POST['post'] ?? '');
	
	$passwordBrut = $_POST['password'] ?? '';
	$salt = 'fixing_password';
	$password = hash('sha256', $passwordBrut. $salt);

	$confirmPass = $_POST['confirmpass'] ?? '';
	
	// Gestion de la photo
	$photos = isset($_FILES['photos']) && $_FILES['photos']['error'] === UPLOAD_ERR_OK 
	         ? $_FILES['photos']['name'] : '';
	$photos_tmp = isset($_FILES['photos']) ? $_FILES['photos']['tmp_name'] : '';
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG');
	$extension_photos = $photos ? strrchr($photos,".") : '.jpg';
	$photos_dest = __DIR__ . '/../photosuser/';
	$etat = 1;
	$photosname = $prenom.$extension_photos;
	
	$level = (int)($_POST['level'] ?? 3);

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
	
	// Upload photo (créer le dossier si nécessaire)
	if (!is_dir($photos_dest)) {
		mkdir($photos_dest, 0755, true);
	}
	if ($photos && $photos_tmp && in_array($extension_photos, $extension)) {
		move_uploaded_file($photos_tmp, $photos_dest.$photosname);
	}

	// Vérifier si les colonnes existent avant d'insérer
	// Tester la structure de la table
	$columns = [];
	try {
		$stmt = $dtb->query("SHOW COLUMNS FROM compt_utilisateur");
		while ($row = $stmt->fetch()) {
			$columns[] = $row['Field'];
		}
	} catch (PDOException $e) {
		throw new Exception("Impossible de lire la structure de la table compt_utilisateur: " . $e->getMessage());
	}

	// Construire la requête dynamiquement selon les colonnes disponibles
	$fields = ['nom', 'prenom', 'post', 'pseudo', 'mail', 'password', 'privilege', 'photos', 'etat', 'theme', 'level'];
	$params = [
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
	];

	// Ajouter les colonnes optionnelles si elles existent dans la table
	if (in_array('user_type', $columns)) {
		$fields[] = 'user_type';
		$params['user_type'] = $user_type;
	}
	if (in_array('teacher_uid', $columns)) {
		$fields[] = 'teacher_uid';
		$params['teacher_uid'] = $teacher_uid;
	}
	if (in_array('student_id', $columns)) {
		$fields[] = 'student_id';
		$params['student_id'] = $student_id;
	}

	$fieldList = implode(', ', $fields);
	$placeholders = implode(', ', array_map(fn($f) => ':' . $f, $fields));

	$sql = "INSERT INTO compt_utilisateur($fieldList) VALUES($placeholders)";
	$insertuser = $dtb->prepare($sql);
	$insertuser->execute($params);

	header('Location: ../../src/creat.account.php');
	exit();

} catch (PDOException $e) {
	error_log("ERREUR add.user.php [PDO]: " . $e->getMessage());
	http_response_code(500);
	die("Erreur base de données lors de la création de l'utilisateur. Détail: " . $e->getMessage());
} catch (Exception $e) {
	error_log("ERREUR add.user.php [General]: " . $e->getMessage());
	http_response_code(500);
	die("Erreur lors de la création de l'utilisateur. Détail: " . $e->getMessage());
}

?>
