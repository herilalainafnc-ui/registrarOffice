<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // notes : réservées au personnel ; l'enseignant passe par une remise
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require '../../data/backdb.php';
require_once(__DIR__ . "/../../data/finance.bareme.php"); // bareme par session


	$id = $_GET['id'];
	$last_change_user_id = (int) ($_SESSION['user_id'] ?? 0); // auteur : le compte connecte, pas l'adresse

	$sigleAffiche = $_POST['sigle'];
	$dep_desc = $_POST['dep_desc'];
	/* « Aucun enseignant » (src/cours/information.php) : NULL, comme les cours
	   qui n'en ont jamais eu, et non 0. */
	$id_teacher = trim((string) ($_POST['id_teacher'] ?? '')) === '' ? null : (int) $_POST['id_teacher'];
	$title = $_POST['title'];
	$title_english = $_POST['title_english'];
	$yearlevel = $_POST['yearlevel'];
	$nb_crd = max(0, min(20, (int) ($_POST['nb_crd'] ?? 0)));   // 0 : cours sans credit, rien a payer (fiche du cours : 0 a 20)
	$semester = $_POST['semester'];
	$category = $_POST['category'];
	$parcours = $_POST['parcours'];
	$lab = $_POST['lab'];

	/* Le formulaire modifie le sigle AFFICHÉ, jamais la clé technique.

	   t_2023_cours.Sigle reste figé : 41 412 notes historiques n'ont pas
	   d'id_cours et ne retrouvent leur cours que par ce sigle (17 260 y
	   parviennent aujourd'hui). L'alias vit dans sigle_affiche, posé par
	   data/cours.sigle.php — voir data/migration_sigle_affiche.sql.

	   Comme avant, seul un code MODIFIÉ vers un code déjà pris est refusé :
	   un cours qui partage déjà le sien (doublons historiques) doit rester
	   modifiable pour pouvoir être corrigé. */
	require_once(__DIR__ . '/../../data/cours.sigle.php');
	coursSigleAliasInstaller($dtb);
	$ajax = function_exists('estRequeteAjax') && estRequeteAjax();
	$sigleAffiche = trim((string) preg_replace('~\s+~u', ' ', (string) $sigleAffiche));
	$lectureSigle = $dtb->prepare('SELECT Sigle, sigle_affiche FROM t_2023_cours WHERE id = ?');
	$lectureSigle->execute([(int) $id]);
	$ligneSigle = $lectureSigle->fetch(PDO::FETCH_ASSOC) ?: [];
	$sigleActuel = (string) ($ligneSigle['Sigle'] ?? '');   // clé technique, non modifiable
	$afficheActuel = coursSigleAffiche($ligneSigle);        // ce que le personnel voyait

	/* Revenir au sigle technique vide l'alias : pas d'alias redondant. */
	if ($sigleAffiche !== '' && coursSigleNormalise($sigleAffiche) === coursSigleNormalise($sigleActuel)) {
		$sigleAffiche = null;
	}

	if (coursSigleNormalise((string) $sigleAffiche) !== coursSigleNormalise($afficheActuel)) {
		$doublons = ($sigleAffiche !== null && $sigleAffiche !== '')
			? coursSigleAliasDoublons($dtb, $sigleAffiche, (int) $id)
			: [];
		if ($sigleAffiche === '' || $doublons) {
			$message = $sigleAffiche === ''
				? 'Le sigle est obligatoire. Le cours n\'a pas été modifié.'
				: coursSigleMessage($doublons) . ' Le cours n\'a pas été modifié.';
			if ($ajax) {
				http_response_code($sigleAffiche === '' ? 422 : 409);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['ok' => false, 'message' => $message, 'doublons' => $doublons], JSON_UNESCAPED_UNICODE);
			} else {
				echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
			}
			exit;
		}
	}


	/* Meme regle que addCours.php : tarif de la session en cours pour ce
	   semestre, avec repli sur le bareme de base. Requete preparee au passage,
	   les trois valeurs etaient interpolees dans le SQL. */
	$result_finance = baremeLigne($dtb, baremeSessionCourante($dtb, (int) $semester), [
		'std_mention' => $dep_desc,
		'level'       => $yearlevel,
		'semester'    => $semester,
	]);
	if (!$result_finance) {
		$result_finance = ['ecolage' => 0, 'laboratory_info' => 0, 'laboratory_lang' => 0];
	}

	// $nb_credit n'existait pas ici : chaque modification remettait le coût du cours à 0.
	$total_cout_cours = $result_finance['ecolage'] * (int) $nb_crd;

	if($lab == "0" OR $lab == "") {
		$cout_lab = 0;
	}else{
		if ($result_finance['laboratory_info'] == 0) {
			$cout_lab = $result_finance['laboratory_lang'];
		}else{
			$cout_lab = $result_finance['laboratory_info'];
		}
	}


	$last_change_datetime = date('Y-m-d');

	$update = $dtb->prepare("UPDATE t_2023_cours SET
		sigle_affiche=:sigle_affiche,
		dep_desc=:dep_desc,
		id_teacher=:id_teacher,
		title=:title,
		title_english=:title_english,
		yearlevel=:yearlevel,
		nb_crd=:nb_crd,
		lab=:lab,
		cout=:cout,
		cout_lab=:cout_lab,
		semester=:semester,
		category=:category,
		parcours=:parcours,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	
	WHERE id=:id");

		$update->bindParam(':sigle_affiche',$sigleAffiche,PDO::PARAM_STR);
		$update->bindParam(':dep_desc',$dep_desc,PDO::PARAM_STR);
		$update->bindParam(':id_teacher',$id_teacher,PDO::PARAM_INT);
		$update->bindParam(':title',$title,PDO::PARAM_STR);
		$update->bindParam(':title_english',$title_english,PDO::PARAM_STR);
		$update->bindParam(':yearlevel',$yearlevel,PDO::PARAM_INT);
		$update->bindParam(':nb_crd',$nb_crd,PDO::PARAM_INT);
		$update->bindParam(':lab',$lab,PDO::PARAM_INT);
		$update->bindParam(':cout',$total_cout_cours,PDO::PARAM_STR);
		$update->bindParam(':cout_lab',$cout_lab,PDO::PARAM_STR);
		$update->bindParam(':semester',$semester,PDO::PARAM_INT);
		$update->bindParam(':category',$category,PDO::PARAM_INT);
		$update->bindParam(':parcours',$parcours,PDO::PARAM_STR);
		$update->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		$update->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
		$update->bindParam(':id',$id,PDO::PARAM_INT);

		$update->execute();


	/* Les notes qui suivent la fiche : celles de l'annee scolaire en cours qui
	   portent encore le sigle de la fiche (data/cours.sigle.php,
	   coursNotesSuivantFiche). Les annees passees gardent le cours tel que
	   l'etudiant l'a suivi : credits, intitule, enseignant... (13/10/2026 ;
	   avant, toutes les annees suivaient et leurs moyennes changeaient).
	   id_cours seul ne suffit pas : des fiches ont été réutilisées pour un
	   autre cours (921 notes relevées le 15/09/2026, ex. « Anatomie I » sur la
	   fiche « Anatomie II et Génétique »). */
	[$notesSuivies, $notesParams] = coursNotesSuivantFiche((int) $id, $sigleActuel, inscAnneeCourante());
	$modify = $dtb->prepare('UPDATE t_2023_notes SET
		title_cours=:title, 
		title_english=:title_english,
		credit=:nb_crd,
		teacher_id=:id_teacher,
		semester=:semester,
		cours_category=:category,
		lab=:lab,
		yearlevel=:yearlevel,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	
	WHERE ' . $notesSuivies);

	$modify->bindParam(':title',$title,PDO::PARAM_STR);
	$modify->bindParam(':title_english',$title_english,PDO::PARAM_STR);
	$modify->bindParam(':nb_crd',$nb_crd,PDO::PARAM_INT);
	$modify->bindParam(':id_teacher',$id_teacher,PDO::PARAM_STR);
	$modify->bindParam(':semester',$semester,PDO::PARAM_INT);
	$modify->bindParam(':category',$category,PDO::PARAM_INT);
	$modify->bindParam(':lab',$lab,PDO::PARAM_INT);
	$modify->bindParam(':yearlevel',$yearlevel,PDO::PARAM_INT);
	$modify->bindParam('last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$modify->bindParam('last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	foreach ($notesParams as $nom => $valeur) {
		$modify->bindValue(':' . $nom, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
	}

	$modify->execute();

	if ($ajax) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['ok' => true, 'message' => 'Cours « ' . coursSigleAffiche(['Sigle' => $sigleActuel, 'sigle_affiche' => $sigleAffiche]) . ' » modifié.'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	header('location:' . $app_base . '/course?id='.$id.'&page=information');

 ?>
