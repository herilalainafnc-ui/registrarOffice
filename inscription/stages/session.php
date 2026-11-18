<?php 
	// Fragment charge en AJAX : il rend des donnees de l'etudiant, il doit
	// donc verifier l'acces. _guard.php fournit aussi $dtb et $app_base.
	require_once(__DIR__ . '/../app/_guard.php');
	inscriptionGuardFragment();
	
	// Vérifier si student_id existe dans POST
	if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
		echo '<div class="insc-session insc-session--vide"><span class="insc-surtitre">Session</span><b class="insc-session-nom">Non attribuée</b></div>';
		exit;
	}
	
	$student_id = $_POST['student_id'];
	$passed_session_id = isset($_POST['session_id']) ? $_POST['session_id'] : '';
	$passed_semester = isset($_POST['semester']) ? $_POST['semester'] : '';
	$passed_year = isset($_POST['year']) ? $_POST['year'] : '';

	/* Rendu : un bloc .insc-session (src/css/inscription.css), dont l'etat
	   colore la note — ok, hors (autre annee), attente (pas enregistree), vide. */
	$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	$bloc = static function (string $etat, string $nom, string $annee = '', string $note = '', string $icone = '', string $suite = '') use ($h): void {
		echo '<div class="insc-session insc-session--' . $etat . '">'
			. '<span class="insc-surtitre">Session</span>'
			. '<b class="insc-session-nom">' . $h($nom) . '</b>'
			. ($annee !== '' ? '<span class="insc-session-annee">' . $h($annee) . '</span>' : '')
			. ($note !== '' ? '<span class="insc-session-note">' . ($icone !== '' ? '<i class="bi ' . $icone . '" aria-hidden="true"></i>' : '') . $h($note) . '</span>' : '')
			. $suite
			. '</div>';
	};

	// Session de l'annee en cours — la selection par « id le plus grand »
	// affichait celle d'une autre annee, et c'est cet identifiant que le JS
	// propage ensuite aux etapes, aux frais et a la fiche.
	$showStudent_Session = inscSessionCourante($dtb, $student_id, $passed_session_id);

	if ($showStudent_Session && $showStudent_Session['session_id']) {
		// annee_effective vient du catalogue, avec repli sur la copie locale :
		// la jointure est deja faite par le resolveur, plus de seconde requete.
		$sessionName = $showStudent_Session['session_name'];
		$sessionYear = $showStudent_Session['annee_effective'];

		if (empty($sessionName)) {
			$sessionName = 'Session #' . $showStudent_Session['session_id'];
		}

		// Une session hors annee en cours se signale : c'est la situation qui
		// faussait silencieusement les etapes et les frais.
		$horsAnnee = !inscAnneeEstCourante($sessionYear);
		$bloc(
			$horsAnnee ? 'hors' : 'ok',
			(string) $sessionName,
			(string) $sessionYear,
			$horsAnnee ? 'Hors année en cours (' . inscAnneeCourante() . ')' : 'Année en cours',
			$horsAnnee ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill',
			'<p id="session_id" style="display:none">' . (int) $showStudent_Session['session_id'] . '</p>'
		);

	} elseif (!empty($passed_session_id) && !empty($passed_semester)) {
		// Session passée en paramètre mais pas encore dans DB
		$bloc('passee', (string) $passed_semester, (string) $passed_year, '', '',
			'<p id="session_id" style="display:none">' . $h($passed_session_id) . '</p>');

	} elseif (!empty($passed_semester)) {
		// Session choisie mais pas encore enregistrée
		$bloc('attente', (string) $passed_semester, (string) $passed_year, 'Pas encore enregistrée', 'bi-hourglass-split');

	} else {
		$bloc('vide', 'Non attribuée');
	}
?>