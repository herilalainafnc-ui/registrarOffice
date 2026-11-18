<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();
/**
 * Enregistrement des parametres du diplome (nouveau modele).
 * Signatures (images), arretes, lieu, annee de bascule et calage d'impression.
 */

	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');
	require_once('../../data/diplome.config.php');
	date_default_timezone_set('Indian/Antananarivo');

	/* Envoyé en AJAX par src/js/settings.diplome.js : réponse JSON { ok, message },
	   la page ne se recharge plus. Sans JavaScript, redirection comme avant. */
	function backToSettings($app_base, $params) {
		if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
			$erreur = isset($params['error']) ? (string) $params['error'] : '';
			// 422 et non 403 : src/js/session.js redirige sur un 403.
			http_response_code($erreur !== '' ? 422 : 200);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'ok'      => $erreur === '',
				'message' => $erreur !== '' ? $erreur : 'Paramètres du diplôme enregistrés.',
			], JSON_UNESCAPED_UNICODE);
			exit;
		}
		header('location:' . $app_base . '/settings/diplome?' . http_build_query($params));
		exit;
	}

	// --- Autorisation : niveaux <= 3 uniquement ---
	if (session_status() === PHP_SESSION_NONE) { ini_set('session.gc_maxlifetime', 36000); ini_set('session.cookie_lifetime', 36000); session_start(); }
	$level = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
	if ($level > 3) {
		backToSettings($app_base, ['error' => "Accès refusé : vous n'avez pas la permission de modifier ces paramètres."]);
	}
	$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		backToSettings($app_base, ['error' => 'Requête invalide.']);
	}

	// Jeton CSRF : ce formulaire n'en portait pas (src/settings.diplome.php).
	if (!function_exists('verify_csrf') || !verify_csrf()) {
		backToSettings($app_base, ['error' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.']);
	}

	// ------------------------------------------------------------ Champs texte
	$data = [];
	foreach ([
		'diplome_recteur_titre', 'diplome_recteur_nom',
		'diplome_president_titre', 'diplome_president_nom',
		'diplome_etudiant_libelle',
		'diplome_arrete_1', 'diplome_arrete_2', 'diplome_lieu', 'diplome_date_remise',
		'diplome_credits_licence', 'diplome_credits_master',
	] as $k) {
		if (isset($_POST[$k])) { $data[$k] = trim((string)$_POST[$k]); }
	}

	// Annee de bascule
	if (isset($_POST['diplome_new_model_from_year'])) {
		$y = (int)$_POST['diplome_new_model_from_year'];
		if ($y < 2000 || $y > 2100) {
			backToSettings($app_base, ['error' => "L'année de bascule doit être comprise entre 2000 et 2100."]);
		}
		$data['diplome_new_model_from_year'] = (string)$y;
	}

	// Calage (mm, negatif autorise, borne raisonnable)
	foreach (['diplome_offset_x', 'diplome_offset_y'] as $k) {
		if (!isset($_POST[$k]) || trim((string)$_POST[$k]) === '') { $data[$k] = '0'; continue; }
		$v = str_replace(',', '.', trim((string)$_POST[$k]));
		if (!is_numeric($v)) {
			backToSettings($app_base, ['error' => 'Le calage doit être un nombre (en mm).']);
		}
		$v = (float)$v;
		if ($v < -50 || $v > 50) {
			backToSettings($app_base, ['error' => 'Le calage doit rester entre -50 et 50 mm.']);
		}
		$data[$k] = (string)$v;
	}

	// ------------------------------------------------------- Images signatures
	$dir = dirname(dirname(__DIR__)) . '/file/signatures';
	if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

	$allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
	$uploads = [
		'signature_recteur'   => 'diplome_recteur_signature',
		'signature_president' => 'diplome_president_signature',
	];

	foreach ($uploads as $field => $cfgKey) {
		if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
			continue; // aucun fichier envoye : on garde la signature actuelle
		}
		$f = $_FILES[$field];

		if ($f['error'] !== UPLOAD_ERR_OK) {
			backToSettings($app_base, ['error' => "Échec du téléversement de la signature (code {$f['error']})."]);
		}
		if ($f['size'] > 2 * 1024 * 1024) {
			backToSettings($app_base, ['error' => 'Image de signature trop lourde (2 Mo maximum).']);
		}

		// Verification reelle du type (pas seulement l'extension annoncee)
		$info = @getimagesize($f['tmp_name']);
		if ($info === false || !isset($allowed[$info[2]])) {
			backToSettings($app_base, ['error' => 'Format de signature non supporté (PNG, JPEG ou WebP attendu).']);
		}

		$ext  = $allowed[$info[2]];
		$slug = ($field === 'signature_recteur') ? 'recteur' : 'president';
		$name = $slug . '-' . date('Ymd-His') . '.' . $ext;

		if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
			backToSettings($app_base, ['error' => "Impossible d'enregistrer l'image de signature."]);
		}
		@chmod($dir . '/' . $name, 0644);
		$data[$cfgKey] = $name;
	}

	// -------------------------------------------------- Arretes par filiere
	if (isset($_POST['arretes']) && is_array($_POST['arretes'])) {
		try {
			$upd = $dtb->prepare('UPDATE diplome_arretes
				SET accronyme = :acc, arrete_mesupres = :mes, arrete_cneat = :cne,
				    last_change_user_id = :uid, last_change_datetime = NOW()
				WHERE id = :id');
			foreach ($_POST['arretes'] as $rowId => $vals) {
				if (!is_array($vals)) { continue; }
				$upd->execute([
					':acc' => mb_substr(trim((string)($vals['accronyme'] ?? '')), 0, 10),
					':mes' => trim((string)($vals['mesupres'] ?? '')),
					':cne' => trim((string)($vals['cneat'] ?? '')),
					':uid' => $userId,
					':id'  => (int)$rowId,
				]);
			}
		} catch (Exception $e) {
			backToSettings($app_base, ['error' => 'Erreur sur les arrêtés par filière : ' . $e->getMessage()]);
		}
	}

	// ------------------------------------------------------------ Sauvegarde
	try {
		diplomeConfigSave($dtb, $data, $userId);
	} catch (Exception $e) {
		backToSettings($app_base, ['error' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()]);
	}

	backToSettings($app_base, ['ok' => 1]);
?>
