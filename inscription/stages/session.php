<?php 
	require('../../data/backdb.php');
	
	// Vérifier si student_id existe dans POST
	if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
		echo '<b>Session</b><br><a class="text-sm text-red-400">Non attribuée</a>';
		exit;
	}
	
	$student_id = $_POST['student_id'];
	$passed_session_id = isset($_POST['session_id']) ? $_POST['session_id'] : '';
	$passed_semester = isset($_POST['semester']) ? $_POST['semester'] : '';
	$passed_year = isset($_POST['year']) ? $_POST['year'] : '';

?>
<b>Session</b>

<?php
	// D'abord, essayer de récupérer la session depuis la base de données
	$stmt = $dtb->prepare('SELECT ins.session_id, ins.annee_scolaire, ins.nbr_semester, sess.session_name, sess.session_year 
		FROM t_2024_inscription_session ins 
		LEFT JOIN t_2023_session sess ON ins.session_id = sess.session_id 
		WHERE ins.student_id = ? 
		ORDER BY ins.id DESC 
		LIMIT 1');
	$stmt->execute([$student_id]);
	$showStudent_Session = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($showStudent_Session && $showStudent_Session['session_id']) {
		// Session trouvée dans la DB - l'afficher
		$sessionName = $showStudent_Session['session_name'];
		$sessionYear = $showStudent_Session['session_year'];
		
		// Si le JOIN n'a pas retourné les infos de session, essayer autrement
		if (empty($sessionName)) {
			$stmtSess = $dtb->prepare('SELECT session_name, session_year FROM t_2023_session WHERE session_id = ?');
			$stmtSess->execute([$showStudent_Session['session_id']]);
			$sessInfo = $stmtSess->fetch(PDO::FETCH_ASSOC);
			if ($sessInfo) {
				$sessionName = $sessInfo['session_name'];
				$sessionYear = $sessInfo['session_year'];
			}
		}
		
		// Fallback sur les données de inscription_session si toujours vide
		if (empty($sessionName)) {
			$sessionName = 'Session #' . $showStudent_Session['session_id'];
		}
		if (empty($sessionYear)) {
			$sessionYear = $showStudent_Session['annee_scolaire'];
		}
		
		echo '<p class="text-sm text-green-400">'.htmlspecialchars($sessionName).'</p>';
		echo '<p class="text-sm text-slate-400">'.htmlspecialchars($sessionYear).'</p>';
		echo '<p id="session_id" style="display:none">'.$showStudent_Session['session_id'].'</p>';
		
	} elseif (!empty($passed_session_id) && !empty($passed_semester)) {
		// Session passée en paramètre mais pas encore dans DB
		echo '<p class="text-sm text-cyan-400">'.htmlspecialchars($passed_semester).'</p>';
		echo '<p class="text-sm text-slate-400">'.htmlspecialchars($passed_year).'</p>';
		echo '<p id="session_id" style="display:none">'.htmlspecialchars($passed_session_id).'</p>';
		
	} elseif (!empty($passed_semester)) {
		// Session choisie mais pas encore enregistrée
		echo '<p class="text-sm text-yellow-400">'.htmlspecialchars($passed_semester).'</p>';
		echo '<p class="text-sm text-slate-400">'.htmlspecialchars($passed_year).'</p>';
		echo '<p class="text-xs text-yellow-500">(en attente)</p>';
		
	} else {
		echo '<br><a class="text-sm text-red-400">Non attribuée</a>';
	}
?>