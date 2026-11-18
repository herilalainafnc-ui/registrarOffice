<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerNiveauNotes(); // ecriture de notes : registrariat seul, hors circuit de remise
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	/**
	 * Saisie de la note CUMULATIVE (Work Education / participation chapelle & semaine de priere)
	 * et repartition coherente sur chaque semestre de l'etudiant.
	 *
	 * Principe : l'utilisateur saisit une seule note cumulative en bas du transcript.
	 * On la repartit sur toutes les sessions de l'etudiant avec de legeres variations
	 * (valeurs non identiques mais coherentes) dont la MOYENNE reste egale a la note saisie
	 * (donc l'affichage cumulatif = valeur saisie). Les notes par-semestre restent modifiables.
	 */

	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');
	date_default_timezone_set('Indian/Antananarivo');

	$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

	function respond($success, $message, $isAjax, $app_base, $id) {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => $success, 'message' => $message]);
		} else {
			$flag = $success ? 'ok' : 'error';
			header('location:' . $app_base . '/student?id=' . $id . '&page=transcriptSS&' . $flag . '=cumul');
		}
		exit;
	}

	// --- Autorisation : seuls les niveaux <= 3 (superadmin, admin, registrar) ---
	if (session_status() === PHP_SESSION_NONE) { ini_set('session.gc_maxlifetime', 36000); ini_set('session.cookie_lifetime', 36000); session_start(); }
	$currentUserLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
	$id = $_GET['id'] ?? 0;
	if ($currentUserLevel > 3) {
		respond(false, "Acces refuse. Vous n'avez pas la permission de modifier les notes.", $isAjax, $app_base, $id);
	}

	$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
	$user_id    = (int) ($_SESSION['user_id'] ?? 0); // auteur : le compte connecte, pas l'adresse
	$yes        = 1;
	$date       = date('Y-m-d');

	if ($student_id <= 0) {
		respond(false, "Etudiant introuvable.", $isAjax, $app_base, $id);
	}

	// Valeurs cumulatives saisies (une seule peut etre fournie a la fois)
	$rawWork   = array_key_exists('cumul_work_educ', $_POST)   ? trim((string)$_POST['cumul_work_educ'])   : '';
	$rawChapel = array_key_exists('cumul_chapel_part', $_POST) ? trim((string)$_POST['cumul_chapel_part']) : '';

	$metrics = []; // colonne => valeur cumulative
	foreach ([['grade_work_educ', $rawWork, 'Note de Work Education'],
	          ['grade_chapel_part', $rawChapel, 'Note de participation chapelle']] as $m) {
		list($col, $raw, $label) = $m;
		if ($raw === '') { continue; } // champ non modifie / vide => on ne touche pas cette metrique
		$val = str_replace(',', '.', $raw);
		if (!is_numeric($val)) {
			respond(false, "$label : la note doit etre un nombre valide.", $isAjax, $app_base, $id);
		}
		$val = (float)$val;
		if ($val < 0 || $val > 20) {
			respond(false, "$label : la note doit etre comprise entre 0 et 20.", $isAjax, $app_base, $id);
		}
		$metrics[$col] = round($val, 2);
	}

	if (empty($metrics)) {
		respond(false, "Aucune note cumulative a enregistrer.", $isAjax, $app_base, $id);
	}

	// --- Recuperer toutes les sessions notees de l'etudiant (ordre chronologique) ---
	$sessQ = $dtb->prepare("SELECT DISTINCT n.session_id, s.session_semester, s.session_year
		FROM t_2023_notes n
		INNER JOIN t_2023_session s ON n.session_id = s.session_id
		WHERE n.student_id = :sid AND n.ajout = :yes
		ORDER BY s.session_year ASC, s.session_semester ASC");
	$sessQ->execute([':sid' => $student_id, ':yes' => $yes]);
	$sessions = $sessQ->fetchAll();

	if (empty($sessions)) {
		respond(false, "Aucune session avec des notes pour cet etudiant.", $isAjax, $app_base, $id);
	}

	$sessionIds = array_map(function ($s) { return (int)$s['session_id']; }, $sessions);

	/**
	 * Repartit une note cumulative $x sur les sessions avec de legeres variations
	 * (paires +d / -d) : valeurs non identiques mais moyenne EXACTEMENT egale a $x,
	 * chaque valeur restant dans [0,20]. Deterministe (memes valeurs a chaque saisie).
	 *
	 * @return array session_id => note repartie
	 */
	function distributeCoherent($x, array $sessionIds, $seedShift = 0) {
		$n = count($sessionIds);
		$out = [];
		$x = round((float)$x, 2);
		if ($n === 0) { return $out; }
		if ($n === 1) { $out[$sessionIds[0]] = $x; return $out; }

		// Amplitude de variation : coherente (<= 0.4 pt) et sans sortir de [0,20]
		$maxOff = min(0.4, $x, 20 - $x);
		if ($maxOff < 0) { $maxOff = 0; }

		$vals = array_fill(0, $n, $x);
		// Paires (+d, -d) : la somme reste n*x => moyenne exacte = x
		for ($i = 0; $i + 1 < $n; $i += 2) {
			$sid = (int)$sessionIds[$i] + $seedShift;
			$r = (($sid * 9301 + 49297) % 233280) / 233280.0; // pseudo-aleatoire deterministe [0,1)
			$d = $maxOff > 0 ? round(0.1 + $r * max($maxOff - 0.1, 0), 2) : 0.0;
			if ($d > $maxOff) { $d = $maxOff; }
			$vals[$i]     = round($x + $d, 2);
			$vals[$i + 1] = round($x - $d, 2);
		}
		// n impair : la derniere session garde exactement x
		foreach ($sessionIds as $idx => $sid) {
			$out[$sid] = $vals[$idx];
		}
		return $out;
	}

	// Distribution par metrique (decalage de graine different => work et chapel non correles)
	$dist = [];
	$shift = 0;
	foreach ($metrics as $col => $x) {
		$dist[$col] = distributeCoherent($x, $sessionIds, $shift);
		$shift += 7919; // decalage premier pour desynchroniser les deux repartitions
	}

	// --- Ecriture : UPDATE si la ligne existe, sinon INSERT ---
	$findRow  = $dtb->prepare('SELECT id FROM t_2023_promotion_notes WHERE student_id = :sid AND session_id = :ses LIMIT 1');
	$getYear  = $dtb->prepare('SELECT yearlevel FROM t_2023_notes WHERE student_id = :sid AND session_id = :ses AND ajout = :yes LIMIT 1');

	$dtb->beginTransaction();
	try {
		foreach ($sessions as $s) {
			$ses = (int)$s['session_id'];

			// Colonnes a ecrire pour cette session
			$set = [];
			$params = [':sid' => $student_id, ':ses' => $ses];
			foreach ($metrics as $col => $x) {
				$set[$col] = $dist[$col][$ses];
			}

			$findRow->execute([':sid' => $student_id, ':ses' => $ses]);
			$exists = $findRow->fetch();

			if ($exists) {
				$assign = [];
				foreach ($set as $col => $v) {
					$assign[] = "$col = :$col";
					$params[":$col"] = $v;
				}
				$params[':uid']  = $user_id;
				$params[':dt']   = $date;
				$sql = 'UPDATE t_2023_promotion_notes SET ' . implode(', ', $assign)
					. ', last_change_user_id = :uid, last_change_datetime = :dt
					   WHERE student_id = :sid AND session_id = :ses';
				$dtb->prepare($sql)->execute($params);
			} else {
				// Nouvelle ligne : renseigner les champs requis (semestre, niveau, annee)
				$getYear->execute([':sid' => $student_id, ':ses' => $ses, ':yes' => $yes]);
				$ylRow = $getYear->fetch();
				$yearlevel = $ylRow ? (int)$ylRow['yearlevel'] : 1;

				$work   = array_key_exists('grade_work_educ', $set)   ? $set['grade_work_educ']   : '';
				$chapel = array_key_exists('grade_chapel_part', $set) ? $set['grade_chapel_part'] : '';

				$ins = $dtb->prepare('INSERT INTO t_2023_promotion_notes(
						student_id, session_id, semester, yearlevel, annee_scolaire,
						grade_work_educ, grade_chapel_part, grade_remark_acad,
						date_entry, last_change_user_id)
					VALUES(:sid, :ses, :sem, :yl, :annee,
						:work, :chapel, :remark, :dt, :uid)');
				$ins->execute([
					':sid'    => $student_id,
					':ses'    => $ses,
					':sem'    => $s['session_semester'],
					':yl'     => $yearlevel,
					':annee'  => $s['session_year'],
					':work'   => $work,
					':chapel' => $chapel,
					':remark' => '',
					':dt'     => $date,
					':uid'    => $user_id,
				]);
			}
		}
		$dtb->commit();
	} catch (Exception $e) {
		$dtb->rollBack();
		respond(false, "Erreur lors de l'enregistrement : " . $e->getMessage(), $isAjax, $app_base, $id);
	}

	respond(true, "Note cumulative repartie sur chaque semestre avec succes !", $isAjax, $app_base, $id);
?>
