<?php
/**
 * Mise à jour d'un utilisateur
 * SÉCURISÉ: Vérification des privilèges + CSRF
 *
 * Appelé en AJAX par /accounts/create (src/creat.account.php) : réponse JSON
 * { ok, message }, la page met à jour son tableau sans se recharger. Sans
 * JavaScript, le comportement d'origine est conservé (redirection).
 */

// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require('../../data/backdb.php');
require('../../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

$ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

/** Réponse JSON en AJAX ; redirection (succès) ou message (échec) sinon. */
function compteRepondre(bool $ok, string $message, int $code = 200): void
{
    global $ajax, $app_base;
    if ($ajax) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'success' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        header('location:' . $app_base . '/accounts/create');
        exit;
    }
    http_response_code($code);
    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

// SÉCURITÉ: Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    compteRepondre(false, 'Méthode non autorisée. Ce formulaire doit être soumis via POST.', 405);
}

// SÉCURITÉ: Vérifier que l'utilisateur est admin ou registrar
if (!isRegistrar()) {
    compteRepondre(false, 'Accès refusé : privilèges insuffisants.', $ajax ? 422 : 403);
}

// SÉCURITÉ: Vérifier le token CSRF. En AJAX, 400 et non 403 : src/js/session.js
// traite un 403 comme un refus de privilèges et redirige.
if ($ajax && !verify_csrf()) {
    compteRepondre(false, 'Jeton de sécurité expiré. Rechargez la page et réessayez.', 400);
}
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

	if ($id <= 0) {
		compteRepondre(false, 'Utilisateur inconnu.', 422);
	}
	if ($nom === '' || $pseudo === '') {
		compteRepondre(false, 'Le nom et le pseudo sont obligatoires.', 422);
	}

	/* Aucune contrainte d'unicité en base sur le pseudo. On ne refuse que s'il
	   CHANGE vers un pseudo déjà pris : un doublon ancien reste modifiable. */
	$lecture = $dtb->prepare('SELECT pseudo, photos FROM compt_utilisateur WHERE id = :id');
	$lecture->execute(['id' => $id]);
	$actuel = $lecture->fetch(PDO::FETCH_ASSOC);
	if ($actuel === false) {
		compteRepondre(false, 'Utilisateur inconnu.', 422);
	}
	$pseudoActuel = $actuel['pseudo'];
	if ($pseudo !== (string) $pseudoActuel) {
		$doublon = $dtb->prepare('SELECT COUNT(*) FROM compt_utilisateur WHERE pseudo = :p AND id <> :id');
		$doublon->execute(['p' => $pseudo, 'id' => $id]);
		if ((int) $doublon->fetchColumn() > 0) {
			compteRepondre(false, 'Ce pseudo est déjà utilisé par un autre compte.', 422);
		}
	}

	if (isset($_POST['etat'.$id])) {
		$etat = 1;
	}else{
		$etat = 0;
	}

	$passwordBrut = $_POST['password'.$id] ?? '';
	$password = password_hash($passwordBrut, PASSWORD_DEFAULT);


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
	}elseif($level == 9) {
		$privilege = "work_education";
	} else {
		$privilege = "registrar";
	}

	$updateUser_user = (int) ($_SESSION['user_id'] ?? 0); // auteur : le compte connecte, pas l'adresse
	$date_entry = date('Y-m-d');

	/* Photo : contenu verifie et reencode, nom fabrique par le serveur
	   (data/photo.php). Le controle d'extension n'etait pas lu — tout fichier
	   passait, un script PHP compris — et le nom venait du prenom saisi. La
	   photo actuelle est relue en base, et non plus prise du formulaire. */
	require_once __DIR__ . '/../../data/photo.php';
	$photosname = (string) ($actuel['photos'] ?? '');
	$photo = photoEnregistrer($_FILES['photos'.$id] ?? null, __DIR__ . '/../photosuser', 'u' . $id, 512);
	if ($photo['erreur'] !== null) {
		compteRepondre(false, $photo['erreur'], 422);
	}
	if ($photo['nom'] !== null) {
		$photosname = $photo['nom'];
	}


	// Récupérer student_id et teacher_uid du formulaire
	$student_id_post = trim($_POST['student_id'.$id] ?? '');
	$teacher_uid_post = trim($_POST['teacher_uid'.$id] ?? '');

	// Déterminer user_type
	$user_type_map = [
		1 => 'superadmin', 2 => 'admin', 3 => 'registrar', 4 => 'comptabilite',
		5 => 'media', 6 => 'chef_mention', 7 => 'teacher', 8 => 'student', 9 => 'work_education'
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
	/* Chef de mention : la mention de sa liste des etudiants (data/perimetre.php).
	   Un autre role n'en a pas. */
	if (in_array('perimetre_mention', $columns)) {
		require_once __DIR__ . '/../../data/perimetre.php';
		$setClauses[] = 'perimetre_mention=:perimetre_mention';
		$params['perimetre_mention'] = $level == 6 ? perimetreMentionValide($dtb, $_POST['perimetre_mention'.$id] ?? '') : null;
	}

	try {
		$sql = "UPDATE compt_utilisateur SET " . implode(', ', $setClauses) . " WHERE id=:id";
		$stmt = $dtb->prepare($sql);
		$stmt->execute($params);
	} catch (PDOException $e) {
		error_log('updateUser.php : ' . $e->getMessage());
		// Message générique : le détail PDO peut révéler la structure de la base.
		compteRepondre(false, $e->getCode() === '23000'
			? 'Ce pseudo est déjà utilisé par un autre compte.'
			: "La mise à jour de l'utilisateur a échoué.", 422);
	}

	// Invalider le cache session de student_id si c'est l'utilisateur courant
	if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
		unset($_SESSION['cached_student_id']);
	}

	compteRepondre(true, 'Compte de ' . trim($nom . ' ' . $prenom) . ' mis à jour.');
