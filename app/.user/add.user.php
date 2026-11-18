<?php
/**
 * Ajout d'un nouvel utilisateur
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

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
        header('Location: ' . $app_base . '/accounts/create');
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
	$confirmPass = $_POST['confirmpass'] ?? '';

	/* Vérifiés côté client seulement jusqu'ici : une requête directe créait un
	   compte sans mot de passe ou avec une confirmation différente. */
	if ($nom === '' || $prenom === '' || $pseudo === '') {
		compteRepondre(false, 'Le nom, le prénom et le pseudo sont obligatoires.', 422);
	}
	if ($passwordBrut === '' || $passwordBrut !== $confirmPass) {
		compteRepondre(false, 'Les mots de passe ne correspondent pas.', 422);
	}

	/* Aucune contrainte d'unicité en base sur le pseudo : deux comptes au même
	   pseudo rendaient la connexion ambiguë. */
	$doublon = $dtb->prepare('SELECT COUNT(*) FROM compt_utilisateur WHERE pseudo = :p');
	$doublon->execute(['p' => $pseudo]);
	if ((int) $doublon->fetchColumn() > 0) {
		compteRepondre(false, 'Ce pseudo est déjà utilisé par un autre compte.', 422);
	}

	$password = password_hash($passwordBrut, PASSWORD_DEFAULT);

	/* Photo : contenu verifie et reencode, nom fabrique par le serveur
	   (data/photo.php). Le nom venait du prenom saisi : un prenom contenant
	   « ../ » ecrivait hors du dossier, et deux homonymes partageaient la meme
	   photo. Sans photo, la colonne reste vide (elle recevait « prenom.jpg »,
	   un fichier qui n'existait pas). */
	require_once __DIR__ . '/../../data/photo.php';
	$photo = photoEnregistrer($_FILES['photos'] ?? null, __DIR__ . '/../photosuser', 'u', 512);
	if ($photo['erreur'] !== null) {
		compteRepondre(false, $photo['erreur'], 422);
	}
	$photosname = $photo['nom'] ?? '';
	$etat = 1;

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
	}elseif($level == 9) {
		$privilege = "work_education";
		$user_type = 'work_education';
	} else {
		$privilege = "registrar";
		$level = 3;
		$user_type = 'registrar';
	}

	$theme = 'Blue';

	// Vérifier si les colonnes existent avant d'insérer
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
	/* Chef de mention : la mention de sa liste des etudiants (data/perimetre.php). */
	if (in_array('perimetre_mention', $columns)) {
		require_once __DIR__ . '/../../data/perimetre.php';
		$fields[] = 'perimetre_mention';
		$params['perimetre_mention'] = $level == 6 ? perimetreMentionValide($dtb, $_POST['perimetre_mention'] ?? '') : null;
	}

	$fieldList = implode(', ', $fields);
	$placeholders = implode(', ', array_map(fn($f) => ':' . $f, $fields));

	$sql = "INSERT INTO compt_utilisateur($fieldList) VALUES($placeholders)";
	$insertuser = $dtb->prepare($sql);
	$insertuser->execute($params);

	compteRepondre(true, 'Compte de ' . trim($nom . ' ' . $prenom) . ' créé.');

} catch (PDOException $e) {
	error_log("ERREUR add.user.php [PDO]: " . $e->getMessage());
	// Message générique : le détail PDO peut révéler la structure de la base.
	compteRepondre(false, $e->getCode() === '23000'
		? 'Ce pseudo est déjà utilisé par un autre compte.'
		: "Erreur de base de données lors de la création de l'utilisateur.", 500);
} catch (Exception $e) {
	error_log("ERREUR add.user.php [General]: " . $e->getMessage());
	compteRepondre(false, "Erreur lors de la création de l'utilisateur.", 500);
}
