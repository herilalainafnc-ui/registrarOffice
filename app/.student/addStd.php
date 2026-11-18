<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(3); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul, niveaux 1 a 3 comme la page de creation
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require ('../../data/backdb.php');
require_once(__DIR__ . "/../../data/finance.bareme.php"); // bareme par session

	/* ============ GARDE ANTI-DOUBLON DU PONT CANDIDATURE ============
	   Une candidature deja convertie ne doit pas produire un second
	   etudiant. Le marquage « converti » en fin de script portait bien la
	   condition « AND statut = accepte », ce qui empechait de reecrire le
	   lien — mais l'etudiant, lui, etait quand meme cree : le doublon
	   arrivait en base sans que rien ne le signale.

	   Trois chemins y menaient : le bouton « Créer l'étudiant » clique deux
	   fois, un renvoi du formulaire par le bouton « precedent », et deux
	   agents ouvrant la meme candidature.

	   On renvoie vers la page de creation, qui explique la situation et
	   pointe vers l'etudiant existant (voir src/creat.student.php). */
	if (!empty($_POST['candidat_id'])) {
		$idCandidature = (int) $_POST['candidat_id'];
		try {
			$dejaConverti = $dtb->prepare(
				'SELECT student_id FROM candidat
				  WHERE id = ? AND statut = "converti" AND student_id IS NOT NULL
				  LIMIT 1'
			);
			$dejaConverti->execute([$idCandidature]);
			if ($dejaConverti->fetchColumn() !== false) {
				header('location:' . $app_base . '/students/create?candidat=' . $idCandidature);
				exit;
			}
		} catch (Throwable $e) {
			/* La verification a echoue : on laisse la creation se poursuivre.
			   Refuser sur une erreur de lecture bloquerait une inscription
			   legitime, ce qui est plus grave qu'un doublon rattrapable. */
			error_log('Pont candidature: verification du doublon impossible: ' . $e->getMessage());
		}
	}

	/* ============ GARDE ANTI-DOUBLE ENVOI ============
	   Chaque formulaire affiche porte un jeton (src/creat.student.php). Le
	   meme formulaire recu une seconde fois — clics repetes sur un reseau
	   lent, bouton « precedent » — ne cree pas un deuxieme etudiant : on
	   renvoie vers la fiche deja creee. Le 06/10/2026, huit clics en deux
	   secondes avaient donne huit fiches identiques.

	   Les envois d'une meme session passent un par un (verrou de session de
	   PHP) : le second voit forcement le jeton note par le premier. */
	$jetonCreation = (string) ($_POST['jeton_creation'] ?? '');
	if (!preg_match('~^[a-f0-9]{32}$~', $jetonCreation)) { $jetonCreation = ''; }
	if ($jetonCreation !== '' && !empty($_SESSION['creations_etudiant'][$jetonCreation])) {
		header('location:' . $app_base . '/student?id=' . (int) $_SESSION['creations_etudiant'][$jetonCreation] . '&page=information&cree=deja');
		exit;
	}

	$student_nom = trim($_POST['student_nom']);
	$student_prenom = trim($_POST['student_prenom']);
	$dateNaissance = $_POST['dateNaissance'];
	$lieuNaissance = $_POST['lieuNaissance'];
	$num_cin = $_POST['num_cin'];
	
	if (!empty($_POST['cin_date_delivre'])) {
		$cin_date_delivre = $_POST['cin_date_delivre'];
	}else{
		$cin_date_delivre = null;
	}
	$cin_region = $_POST['cin_region'];
	$sex = $_POST['sex'];
	$nationalite = $_POST['nationalite'];
	$student_tel = $_POST['student_tel'];
	$student_email = strtolower(str_replace(' ', '', $_POST['student_email']));

	/* Adresse universitaire UNIQUE (data/adresse.etudiant.php). Celle du
	   formulaire est gardee si elle est libre ; deja prise — par qui que ce
	   soit, la meme personne passee de Licence en Master comprise — ou vide,
	   la premiere libre est attribuee, et la fiche le dit a l'agent. Le
	   formulaire la propose deja libre : ceci couvre une saisie a la main, ou
	   une autre creation enregistree entre-temps. */
	require_once __DIR__ . '/../../data/adresse.etudiant.php';
	$adresseAvis = '';
	if ($student_email === '' || adresseEtudiantPrise($dtb, $student_email)) {
		$adresseLibre = adresseEtudiantLibre($dtb, $student_nom, $student_prenom);
		if ($adresseLibre['adresse'] !== '') {
			if ($student_email !== '') {
				$adresseAvis = "L'adresse {$student_email} était déjà prise : {$adresseLibre['adresse']} a été attribuée.";
			}
			$student_email = $adresseLibre['adresse'];
		}
	}
	$pays_origine = $_POST['pays_origine'];
	$student_region = $_POST['student_region'];
	$student_adresse = $_POST['student_adresse'];
	

	$init_id = $_POST['student_id'];

	/* ==================== ATTRIBUTION DU MATRICULE ====================
	   Le matricule propose par le formulaire (src/services/matricule.live)
	   peut deja etre pris : la proposition repose sur un tri de colonne
	   VARCHAR, qui n'ordonne pas les nombres comme des nombres. On ne fait
	   donc jamais confiance a la valeur recue et on verifie ici, au moment
	   d'ecrire.

	   Cette verification existait mais ne s'est jamais declenchee : elle
	   lisait $showId alors que le fetch alimentait $showID. Des etudiants
	   distincts ont ainsi recu le meme matricule (constate en base).

	   Deux formats coexistent et doivent etre preserves :
	     - licence : purement numerique          (ex. 20477)
	     - master  : numero + M + initiale       (ex. 20481MG)
	   On incremente donc la seule partie numerique, en conservant le
	   suffixe, jusqu'a trouver un matricule reellement libre. L'ancienne
	   version faisait « $init_id + 1 », ce qui perdait le suffixe et
	   pouvait retomber sur un autre matricule deja attribue. */
	if (!function_exists('matriculeDisponible')) {
		function matriculeDisponible(PDO $dtb, $propose) {
			$propose = trim((string) $propose);
			if ($propose === '') { return $propose; }

			// Separe la partie numerique de tete du reste (suffixe eventuel).
			if (!preg_match('/^(\d+)(.*)$/', $propose, $m)) {
				return $propose; // format inattendu : on ne touche a rien
			}
			$numero  = (int) $m[1];
			$suffixe = $m[2];

			$test = $dtb->prepare('SELECT 1 FROM tbl_2024_etudiant WHERE student_id = ? LIMIT 1');

			// Borne de securite : evite toute boucle sans fin si la table
			// contenait une plage entierement occupee.
			for ($i = 0; $i < 1000; $i++) {
				$candidat = $numero . $suffixe;
				$test->execute([$candidat]);
				if ($test->fetchColumn() === false) {
					return $candidat; // libre
				}
				$numero++;
			}
			// Aucun matricule libre trouve : on renvoie la proposition telle
			// quelle plutot que d'inventer une valeur silencieusement.
			error_log('addStd: aucun matricule libre a partir de ' . $propose);
			return $propose;
		}
	}

	$student_id = matriculeDisponible($dtb, $init_id);

	$mention = $_POST['etude_envisage'];
	$findMention = $dtb->prepare('SELECT * FROM filiere WHERE filiere_sigle = ?');
	$findMention->execute([$mention]);
	$showM = $findMention->fetch();
	$etude_envisage = $showM['filiere_description'];

	$graduated = 0;
	$abonment = $_POST['abonment'];
	$annee_etude = $_POST['annee_etude'];
	$status = $_POST['status'];
	$etude_option = $_POST['etude_option'];
	$taille = $_POST['taille'] ?? '';
	$semester = $_POST['semestre'];
	$annee_scolaire = $_POST['annee_scolaire'];
	$new_student = $_POST['new_student'];
	$father_name = $_POST['father_name'];
	$father_prof = $_POST['father_prof'];
	$mother_name = $_POST['mother_name'];
	$mother_prof = $_POST['mother_prof'];
	$parent_tel = $_POST['parent_tel'];
	$parent_adresse = $_POST['parent_adresse'];
	$sponsor_nom = $_POST['sponsor_nom'];
	$sponsor_prenom = $_POST['sponsor_prenom'];

	/* Nouvel etudiant : le « Type etudiant » choisi, sauf qu'un etudiant qui a deja
	   suivi une session (inscriptions et notes) est ancien (data/inscription.session.php). */
	require_once __DIR__ . '/../../data/inscription.session.php';
	$new_student = inscNouvelEtudiant($dtb, (string) $student_id, 0, $new_student);
	$sponsor_tel = $_POST['sponsor_tel'];
	$sponsor_adresse = $_POST['sponsor_adresse'];
	$situationf = $_POST['situationf'];
	$nom_conjoint = $_POST['nom_conjoint'];
	$nb_enfant = $_POST['nb_enfant'];
	$religion = $_POST['religion'];
	$num_visa = $_POST['num_visa'];
	$last_change_user_id = (int) ($_SESSION['user_id'] ?? 0); // auteur : le compte connecte, pas l'adresse
	$date_entry = date("Y-m-d");

	/*:::::::::::::::::::: VERIFICATION SESSION ::::::::::::::::::::*/

	$find_session = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_semester = ? AND session_year = ? LIMIT 1');
	$find_session->execute([$semester, $annee_scolaire]);

	$showSession = $find_session->fetch();

	$session_id = $showSession['session_id'];

	/*:::::::::::::::::::: PASSWORD MAIL GENERATE ::::::::::::::::::::*/

	$a = rand(1000,9999);
	
	$mois = date('m');
	if ($mois < 7){
		$y = date('Y');
	}else{
		$y = date('Y')+1;
	}
	
	$password = $a."Student".$y;

	/*:::::::::::::::::::: CODE-BAR GENERATE ::::::::::::::::::::*/

	$lookup_code = $y.$student_id;


	/*:::::::::::::::::::: IMAGE GENERATE ::::::::::::::::::::*/

	/* Photo : contenu verifie et reencode, nom fabrique par le serveur
	   (data/photo.php). L'ancien code gardait tel quel un fichier que GD ne
	   savait pas lire. Une photo refusee n'empeche pas l'inscription : la
	   fiche est creee sans photo, comme avant. */
	require_once __DIR__ . '/../../data/photo.php';
	$photo = photoEnregistrer($_FILES['image_student'] ?? null, __DIR__ . '/../photosetudiants', (string) $student_id);
	$dbimage = $photo['nom'] ?? '';

	/*:::::::::::::::::::: FINANCE ::::::::::::::::::::*/

	/* Bareme de la session de creation, avec repli sur le bareme de base.
	   Les criteres restent ceux de la version d'origine — statut et mention
	   seulement, sans niveau ni semestre — pour ne pas changer ici une regle
	   metier au passage. Seule la dimension session est ajoutee. */
	$result_finance = baremeLigne($dtb, $session_id, [
		'std_status'  => $status,
		'std_mention' => $mention,
	]);


	/*============== DETERMINATION OF DAYS NUMBER ==============*/
	
	if ($annee_etude == 1) {

		$nbr_day = $result_finance['nb_jours_semestre'];

		$frais_costume = $result_finance['frais_costume'];
	
	}elseif ($annee_etude == 2) {
	
		$nbr_day = $result_finance['nb_jours_semestre_L2'];

		$frais_costume = 0;
	
	}elseif ($annee_etude == 3) {
	
		$nbr_day = $result_finance['nb_jours_semestre_L3'];
		$frais_costume = 0;
	}

	/*============== FRAIS GENEREAUX ==============*/

	$cout_fraix_generaux = $result_finance['frais_generaux'];

	/*============== LOGMENT ==============*/

	$cout_logement = $result_finance['dortoir'] * $nbr_day;


	/*============== ABONEMENT CAF ==============*/

	if($abonment == 1) {

		$cout_abonment = $result_finance['cafeteria'] * $nbr_day;

	}else{
		
		$cout_abonment = 0;

	}


	/*============== FOND DE DEPOT ==============*/
	if ($annee_etude == 1 AND $new_student == 1) {

		$fond_depot = $result_finance['fond_depot'];

	}else{

		$fond_depot = 0;

	}
	


	/*============== FRAIS DE COSTUME ==============*/
	
	$frais_costume = $result_finance['frais_costume'];

	if ($semester == 1) {

		$frais_voyage = $result_finance['frais_voyage'];

	}else{

		$frais_voyage = 0;
		
	}

	/*:::::::::::::::::::: DIPLÔME PRECEDENT ::::::::::::::::::::*/

	if($annee_etude <= 3 ) {
	
		$obtention_bacc	= $_POST['obtention_bacc'];
		$serie_bacc= $_POST['serie_bacc'];

		$insertBacc = $dtb->prepare('INSERT INTO t_2024_bacc(
				student_id,
				date_obtent,
				bacc_serie,
				user_id,
				date_entry
			)VALUES(
				:student_id,
				:date_obtent,
				:bacc_serie,
				:user_id,
				:date_entry
			)');$insertBacc->execute(array(
				'student_id'  => $student_id,
				'date_obtent' => $obtention_bacc,
				'bacc_serie' => $serie_bacc,
				'user_id' => $last_change_user_id,
				'date_entry' => $date_entry
			));


	}elseif($annee_etude > 3 ) {

		$diplome_preced = $_POST['diplome_preced'];
		$date_obtent_diplome_preced = $_POST['date_obtent_diplome_preced'];

		$insertBacc = $dtb->prepare('INSERT INTO t_2024_diplome_preced(
				student_id,
				diplome_name,
				date_obtent,
				user_id,
				date_entry
			)VALUES(
				:student_id,
				:diplome_name,
				:date_obtent,
				:user_id,
				:date_entry
			)');$insertBacc->execute(array(
				'student_id' => $student_id,
				'diplome_name' => $diplome_preced,
				'date_obtent' => $date_obtent_diplome_preced,
				'user_id' => $last_change_user_id,
				'date_entry' => $date_entry
			));
	
	}

	/*:::::::::::::::::::: INFORMATION ::::::::::::::::::::*/

	$insertStd = $dtb->prepare('INSERT INTO tbl_2024_etudiant(
		student_nom,
		student_prenom,
		dateNaissance,
		lieuNaissance,
		num_cin,
		cin_date_delivre,
		cin_region,
		sex,
		nationalite,
		student_tel,
		student_email,
		password,
		lookup_code,
		pays_origine,
		student_region,
		student_adresse,
		image_student,
		student_id,
		etude_envisage,
		annee_etude,
		status,
		etude_option,
		annee_scolaire,
		new_student,
		father_name,
		father_prof,
		mother_name,
		mother_prof,
		parent_tel,
		parent_adresse,
		sponsor_nom,
		sponsor_prenom,
		sponsor_tel,
		sponsor_adresse,
		situationf,
		nom_conjoint,
		nb_enfant,
		religion,
		num_visa,
		abonment,
		last_change_user_id,
		date_entry,
		taille

	)VALUES(
		:student_nom,
		:student_prenom,
		:dateNaissance,
		:lieuNaissance,
		:num_cin,
		:cin_date_delivre,
		:cin_region,
		:sex,
		:nationalite,
		:student_tel,
		:student_email,
		:password,
		:lookup_code,
		:pays_origine,
		:student_region,
		:student_adresse,
		:image_student,
		:student_id,
		:etude_envisage,
		:annee_etude,
		:status,
		:etude_option,
		:annee_scolaire,
		:new_student,
		:father_name,
		:father_prof,
		:mother_name,
		:mother_prof,
		:parent_tel,
		:parent_adresse,
		:sponsor_nom,
		:sponsor_prenom,
		:sponsor_tel,
		:sponsor_adresse,
		:situationf,
		:nom_conjoint,
		:nb_enfant,
		:religion,
		:num_visa,
		:abonment,
		:last_change_user_id,
		:date_entry,
		:taille

	)');$insertStd->execute(array(
		'student_nom' => $student_nom,
		'student_prenom' => $student_prenom,
		'dateNaissance' => $dateNaissance,
		'lieuNaissance' => $lieuNaissance,
		'num_cin' => $num_cin,
		'cin_date_delivre' => $cin_date_delivre,
		'cin_region' => $cin_region,
		'sex' => $sex,
		'nationalite' => $nationalite,
		'student_tel' => $student_tel,
		'student_email' => $student_email,
		'password' => $password,
		'lookup_code' => $lookup_code,
		'pays_origine' => $pays_origine,
		'student_region' => $student_region,
		'student_adresse' => $student_adresse,
		'image_student' => $dbimage,
		'student_id' => $student_id,
		'etude_envisage' => $etude_envisage,
		'annee_etude' => $annee_etude,
		'status' => $status,
		'etude_option' => $etude_option,
		'annee_scolaire' => $annee_scolaire,
		'new_student' => $new_student,
		'father_name' => $father_name,
		'father_prof' => $father_prof,
		'mother_name' => $mother_name,
		'mother_prof' => $mother_prof,
		'parent_tel' => $parent_tel,
		'parent_adresse' => $parent_adresse,
		'sponsor_nom' => $sponsor_nom,
		'sponsor_prenom' => $sponsor_prenom,
		'sponsor_tel' => $sponsor_tel,
		'sponsor_adresse' => $sponsor_adresse,
		'situationf' => $situationf,
		'nom_conjoint' => $nom_conjoint,
		'nb_enfant' => $nb_enfant,
		'religion' => $religion,
		'num_visa' => $num_visa,
		'abonment' => $abonment,
		'last_change_user_id' => $last_change_user_id,
		'date_entry' => $date_entry,
		'taille' => $taille
	));



	$findId = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = ? ORDER BY id DESC LIMIT 1');
	$findId->execute([$student_id]);
	$showId = $findId->fetch();
	$id = $showId['id'];
	if ($adresseAvis !== '') { $_SESSION['adresse_creation'][(int) $id] = $adresseAvis; } // montre sur la fiche (src/student.php)

	/* Compte Google de l'adresse universitaire, cree tout de suite par l'API
	   Google Admin (data/google.admin.php) : il marche des que le mail
	   d'acceptation part, sans import CSV. Ne fait jamais echouer la creation
	   de l'etudiant, deja enregistree ; l'issue est montree sur la fiche, et un
	   compte manquant se cree ensuite depuis /settings/adresses-google. */
	$compteGoogle = null;
	if (googleAdminConfiguree() && $student_email !== '') {
		$compteGoogle = googleAdminCreerEtudiant([
			'adresse' => $student_email, 'nom' => $student_nom, 'prenom' => $student_prenom, 'mot_de_passe' => $password,
			'matricule' => (string) $student_id, 'mention' => (string) $etude_envisage, 'parcours' => (string) $etude_option,
			'telephone' => (string) $student_tel, 'domicile' => (string) $student_adresse,
		]);
		$_SESSION['google_creation'][(int) $id] = $compteGoogle;
	}

	/* ================= PONT CANDIDATURE -> ETUDIANT =================
	   Creation lancee depuis une candidature acceptee : on ferme la boucle
	   en marquant celle-ci « convertie » et en conservant le matricule
	   attribue. La candidature garde ainsi la trace de l'etudiant issu
	   d'elle, et disparait des dossiers a instruire.

	   L'ARCHIVAGE EST FAIT ICI, dans la meme requete. Une candidature
	   convertie est un dossier clos : il n'y a plus rien a en faire, et le
	   laisser dans la liste active obligeait l'agent a l'archiver a la main
	   apres chaque creation — un geste oublie une fois sur deux, qui laissait
	   des dossiers deja traites au milieu de ceux qui attendent.

	   L'archivage ne change pas le statut : « converti » reste « converti »,
	   et le dossier se retrouve dans la vue Archives. Il peut en etre
	   ressorti par « Restaurer » si l'on veut le reconsulter.

	   Ce raccordement ne doit jamais faire echouer la creation de
	   l'etudiant, qui est deja enregistree : toute erreur est journalisee
	   sans interrompre la redirection.

	   La condition sur le statut evite d'ecraser une conversion deja faite
	   (double soumission, retour navigateur) ; la garde en tete de fichier
	   arrete ce cas bien plus tot. */
	if (!empty($_POST['candidat_id'])) {
		try {
			/* Qui archive : la meme trace que l'archivage manuel, pour que la
			   colonne archive_par reste lisible d'une origine a l'autre. */
			$nomAgent = trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''));
			if ($nomAgent === '') { $nomAgent = $_SESSION['user_pseudo'] ?? ''; }

			$majCandidat = $dtb->prepare(
				'UPDATE candidat
				    SET statut = "converti", student_id = ?, date_traitement = NOW(),
				        archive_le = COALESCE(archive_le, NOW()),
				        archive_par = COALESCE(archive_par, ?)
				  WHERE id = ? AND statut = "accepte"'
			);
			/* COALESCE plutot qu'une affectation seche : si l'agent avait deja
			   archive le dossier avant de creer l'etudiant, sa trace et sa date
			   sont celles qui comptent — on ne les remplace pas par les
			   notres. */
			$majCandidat->execute([
				$student_id,
				$nomAgent !== '' ? $nomAgent : null,
				(int) $_POST['candidat_id'],
			]);

			/* Master : c'est maintenant, avec l'adresse universitaire et le mot
			   de passe que cette creation vient de produire, que part le mail
			   d'acceptation (data/admissions.mail.php). « Accepter » n'a rien
			   envoye pour le Master. La Licence a recu le sien a l'acceptation.
			   L'issue est montree a l'agent sur la fiche (src/student.php). */
			if ($majCandidat->rowCount() === 1) {
				$lireCandidat = $dtb->prepare('SELECT nom_complet, email, mention, mention_libelle, diplome_vise FROM candidat WHERE id = ?');
				$lireCandidat->execute([(int) $_POST['candidat_id']]);
				$candidature = $lireCandidat->fetch(PDO::FETCH_ASSOC);
				require_once __DIR__ . '/../../data/admissions.mail.php';
				if ($candidature && admissionEstMaster($candidature)) {
					$envoi = admissionNotifierIdentifiants($candidature, $student_email, $password, $compteGoogle !== null ? !empty($compteGoogle['cree']) : null);
					if ($envoi['envoye']) {
						$dtb->prepare('UPDATE candidat SET notifie_le = NOW() WHERE id = ?')->execute([(int) $_POST['candidat_id']]);
					}
					$_SESSION['mail_creation'][(int) $id] = $envoi;
				}
			}
		} catch (Throwable $e) {
			error_log('Pont candidature: conversion non enregistree: ' . $e->getMessage());
		}
	}
	
	/* Jeton consomme : un second envoi du meme formulaire menera ici. On garde
	   les vingt derniers, de quoi couvrir une matinee d'inscriptions. */
	if ($jetonCreation !== '') {
		$_SESSION['creations_etudiant'][$jetonCreation] = (int) $id;
		$_SESSION['creations_etudiant'] = array_slice($_SESSION['creations_etudiant'], -20, null, true);
	}

	/* « cree » : la fiche confirme la creation (src/student.php). */
	header('location:' . $app_base . '/student?id='.$id.'&page=information&cree=1')
 ?>
