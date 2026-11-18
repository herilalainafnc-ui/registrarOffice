<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul (un compte etudiant les lancait)
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require ('../../data/backdb.php');
require_once(__DIR__ . "/../../data/finance.bareme.php"); // bareme par session

	$date_entry = date('Y-m-d');
	$sigle = $_POST['sigle'];
	$title = $_POST['title'];
	$title_english = $_POST['title_english'];
	$nb_credit = max(0, min(20, (int) ($_POST['nb_credit'] ?? 0)));   // 0 : cours sans credit, rien a payer
	$category = $_POST['category'];
	$dep_desc = $_POST['dep_desc'];
	$parcours = $_POST['parcours'];
	$teacher_id = $_POST['teacher_id'];;
	$semester = $_POST['semester'];
	$lab = $_POST['lab'];
	$yearlevel = $_POST['yearlevel'];
	$description = $_POST['description'];
	$remark = $_POST['remark'];


	/* Bareme de la session en cours pour ce semestre, avec repli sur le bareme
	   de base. Ce point d'entree definit un cours, il n'a donc pas de session
	   d'inscription sous la main : c'est le tarif de l'annee en cours qui
	   s'applique. La requete precedente interpolait en plus $dep_desc,
	   $yearlevel et $semester directement dans le SQL. */
	$result_finance = baremeLigne($dtb, baremeSessionCourante($dtb, (int) $semester), [
		'std_mention' => $dep_desc,
		'level'       => $yearlevel,
		'semester'    => $semester,
	]);
	if (!$result_finance) {
		// Sans bareme, le cout calcule serait 0 sans que personne ne le sache.
		$result_finance = ['ecolage' => 0, 'laboratory_info' => 0, 'laboratory_lang' => 0];
	}

	$cout = $result_finance['ecolage'] * $nb_credit;

	if ($result_finance['laboratory_info'] == 0) {
		$cout_lab = $result_finance['laboratory_lang'];
	}else{
		$cout_lab = $result_finance['laboratory_info'];
	}
	
	$ajout = 1;



	$active = 1;

	/* Sigle unique (data/cours.sigle.php). La modale prévient pendant la
	   frappe, mais une requête peut se passer d'elle : la règle est tenue ici. */
	require_once(__DIR__ . '/../../data/cours.sigle.php');
	$ajax = function_exists('estRequeteAjax') && estRequeteAjax();
	$sigle = trim((string) preg_replace('~\s+~u', ' ', (string) $sigle));
	$reponseCours = function (int $code, array $charge) use ($ajax, $app_base) {
		if ($ajax) {
			http_response_code($code);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($charge, JSON_UNESCAPED_UNICODE);
		} else {
			echo htmlspecialchars($charge['message'], ENT_QUOTES, 'UTF-8') . ' <a href="' . $app_base . '/courses">Retour</a>';
		}
		exit;
	};
	/* Un cours neuf ne doit heurter ni une cle technique ni un code affiche. */
	$doublons = $sigle !== '' ? coursSigleCreationDoublons($dtb, $sigle) : [];
	if ($doublons) {
		$reponseCours(409, ['ok' => false, 'message' => coursSigleMessage($doublons) . ' Le cours n\'a pas été ajouté.', 'doublons' => $doublons]);
	}

if($sigle !="" AND $title!="") {
	$insertteacher = $dtb->prepare("INSERT INTO t_2023_cours(
				Sigle,
				title,
				title_english,
				Description,
				nb_crd,
				Remark,
				dep_desc,
				category,
				parcours,
				id_teacher,
				semester,
				lab,
				cout,
				cout_lab,
				ajout,
				yearlevel,
				active,
				date_entry
	) VALUES (
				:Sigle,
				:title,
				:title_english,
				:Description,
				:nb_crd,
				:Remark,
				:dep_desc,
				:category,
				:parcours,
				:id_teacher,
				:semester,
				:lab,
				:cout,
				:cout_lab,
				:ajout,
				:yearlevel,
				:active,
				:date_entry
	)");$insertteacher->execute(array(
				'Sigle' => $sigle,
				'title' => $title,
				'title_english' => $title_english,
				'Description' => $description,
				'nb_crd' => $nb_credit,
				'Remark' => $remark,
				'dep_desc' => $dep_desc,
				'category' => $category,
				'parcours' => $parcours,
				'id_teacher' => $teacher_id,
				'semester' => $semester,
				'lab' => $lab,
				'cout' => $cout,
				'cout_lab' => $cout_lab,
				'ajout' => $ajout,
				'yearlevel' => $yearlevel,
				'active' => $active,
				'date_entry' => $date_entry 
	));

	if ($ajax) {
		$reponseCours(200, ['ok' => true, 'id' => (int) $dtb->lastInsertId(), 'message' => 'Cours « ' . $sigle . ' » ajouté.']);
	}
	header('location:' . $app_base . '/courses');

}else{
	if ($ajax) {
		$reponseCours(422, ['ok' => false, 'message' => 'Le sigle et le titre du cours sont obligatoires.']);
	}
	echo "Opération non effectué!!!";
 ?>
 	<a href="../../src/accueil.cours.php">Retour</a>
<?php 
}
 ?>
