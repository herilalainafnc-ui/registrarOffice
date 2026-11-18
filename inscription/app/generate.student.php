<?php
/**
 * Cree (ou met a jour) l'inscription d'un etudiant a une session, et la ligne
 * financiere correspondante.
 * SECURISE : authentification + niveau + CSRF (voir _guard.php), POST uniquement.
 */

require_once(__DIR__ . '/_guard.php');
require_once(__DIR__ . '/../../data/finance.frais.php'); // frais supplementaires du bareme
require_once(__DIR__ . '/../../data/inscriptions.tardives.php'); // trace et penalite d'une inscription tardive (§ 334)
inscriptionGuard('POST');

	header('Content-Type: application/json; charset=utf-8');

	try {
		$semesterSession = $_POST['semesterSession'] ?? '';
		$annee_scolaire  = $_POST['annee_scolaire'] ?? '';
		$student_id      = $_GET['student_id'] ?? '';
		$graduated       = $_GET['graduated'] ?? '';

		if ($semesterSession === '' || $annee_scolaire === '' || $student_id === '') {
			inscriptionRefus('Paramètres incomplets (session, année ou étudiant manquant).', 400);
		}

	if ($graduated == "") {
		$graduated = 0;
	}

	$remove = 0;

	// Auteur de l'ecriture, pour la piste d'audit. La colonne est NOT NULL :
	// 0 plutot que null si la session ne porte pas d'identifiant.
	$userId = (int)($_SESSION['user_id'] ?? 0);

	$findStudent = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id ORDER BY remove ASC, id ASC LIMIT 1');
	$findStudent->execute(['student_id' => $student_id]);
	$showStudent = $findStudent->fetch();

	// Etudiant introuvable : sans cette garde $showStudent['id'] valait null et
	// l'UPDATE plus bas s'executait sur WHERE id = NULL.
	if (!$showStudent) {
		inscriptionRefus('Étudiant introuvable.', 404);
	}

	$idS = $showStudent['id'];

	/* Verrous : ce que la page montre en modale, le serveur le refuse
	   desormais (inscriptionMotifDeRefus, data/inscription.session.php). */
	$motifDeRefus = inscriptionMotifDeRefus($showStudent);
	if ($motifDeRefus !== null) {
		inscriptionRefus($motifDeRefus, 403);
	}

	// new_student : une fois la session connue (plus bas, inscNouvelEtudiant)

	// Déterminer annee_etude
	if ($showStudent['annee_etude'] < 3 AND $showStudent['annee_scolaire'] != $annee_scolaire) {

		$annee_etude = $showStudent['annee_etude'] + 1;
	
	}elseif($showStudent['annee_scolaire'] == $annee_scolaire){
		
		$annee_etude = 1;
	
	}elseif($showStudent['annee_etude'] == 3 OR ($showStudent['annee_etude'] == 1 AND $showStudent['new_student'] == 1 )) {

		$annee_etude = $showStudent['annee_etude'];

	}
	
	/* L'annee scolaire de l'etudiant etait mise a jour ICI, avant
	   beginTransaction() : l'ecriture etait donc auto-validee. Quand la suite
	   echouait — typiquement « Configuration financière introuvable », qui
	   appelle rollBack() — l'appel repondait « echec » mais l'annee de
	   l'etudiant avait deja change, sans session correspondante. L'UPDATE est
	   deplace dans la transaction, apres la validation du bareme. */

	$findMention = $dtb->prepare('SELECT * FROM filiere WHERE filiere_description = :etude_envisage');
	$findMention->execute(['etude_envisage' => $showStudent['etude_envisage']]);
	$showMention = $findMention->fetch();
	
	$etude_mention = $showMention['filiere_sigle'];

	
	if ($showStudent['status'] == "" OR $showStudent['status'] == "Bungalow") {
		$status = 'Externe';
	}else{
		$status = $showStudent['status'];	
	}

	
	$level = $showStudent['annee_etude'];

	/* Ce que l'inscription garde de la session : le niveau et l'abonnement
	   factures ici, et le vrai statut. L'etape 1 ne les ecrivait pas : niveau
	   0 et abonnement 0 jusqu'a un ajout de cours (toute la session 2024-2025
	   S1 restee a 0), et un bungalow inscrit comme externe — le bareme les
	   facture pareil, mais la statistique Internat ne pouvait plus les compter. */
	$niveauInscription = (int) $level;
	$abonnementInscription = (int) ($showStudent['abonment'] ?? 0);
	$statutInscription = trim((string) $showStudent['status']) !== '' ? (string) $showStudent['status'] : 'Externe';

	$data_completion = 1;
	
	$date_entry = date('Y-m-d');
	

	$findSession = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_name = :session_name AND session_year = :session_year LIMIT 1');
	$findSession->execute(['session_name' => $semesterSession, 'session_year' => $annee_scolaire]);
	$showSession = $findSession->fetch();

	/* Session absente du catalogue t_2023_session : les trois lectures qui
	   suivent renvoyaient null, l'annee scolaire de l'etudiant etait ecrasee
	   avec null et l'INSERT echouait plus loin sur une colonne NOT NULL, avec
	   un message technique. Le motif reel est annonce ici. */
	if (!$showSession) {
		inscriptionRefus(
			'Session « ' . $semesterSession . ' » non configurée pour l\'année ' . $annee_scolaire . '.',
			422
		);
	}

	$session_id = $showSession['session_id'];
	$nbr_semester = $showSession['session_semester'];
	$annee_scolaire = $showSession['session_year'];

	$verification_Old_status = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id ORDER BY remove ASC, id ASC LIMIT 1');
	$verification_Old_status->execute(['student_id' => $student_id]);
	$show_Old_status = $verification_Old_status->fetch();

	$graduated = $show_Old_status['graduated'];

	// Variables de configuration (pas d'echo pour le JSON)
	$debug_info = [
		'status' => $status,
		'etude_mention' => $etude_mention,
		'level' => $level
	];

	// ============================================================
	// Vérifier si une inscription existe déjà pour ce même semestre
	// Si oui → UPDATE (pas de suppression, on préserve l'historique)
	// Si non → INSERT
	// Les cours (t_2024_cours_finance) et notes (t_2023_notes) ne sont JAMAIS touchés
	// ============================================================

	// Transaction démarrée AVANT les vérifications pour éviter les race conditions
	$dtb->beginTransaction();
	
	try {

	$checkExisting = $dtb->prepare('SELECT id, new_student FROM t_2024_inscription_session WHERE student_id = :student_id AND session_id = :session_id FOR UPDATE');
	$checkExisting->execute(['student_id' => $student_id, 'session_id' => $session_id]);
	$existingSession = $checkExisting->fetch();

	/* Nouvel etudiant : aucune autre session suivie (inscriptions et notes), et pas
	   marque « ancien » — ni sur son inscription a cette session, ni sur sa fiche. */
	$new_student = inscNouvelEtudiant($dtb, (string) $student_id, (int) $session_id,
		$existingSession ? $existingSession['new_student'] : ($show_Old_status['new_student'] ?? null));

	$checkExistingFinance = $dtb->prepare('SELECT id FROM t_2024_etudiant_finace WHERE student_id = :student_id AND session_id = :session_id FOR UPDATE');
	$checkExistingFinance->execute(['student_id' => $student_id, 'session_id' => $session_id]);
	$existingFinance = $checkExistingFinance->fetch();

	/* Bareme de la session inscrite, avec repli sur le bareme de base.
	   La requete precedente ignorait la session : un ajustement de tarif
	   s'appliquait donc retroactivement a toutes les annees. Voir
	   data/finance.bareme.php. */
	$result_finance = baremeLigne($dtb, $session_id, [
		'std_status'  => $status,
		'std_mention' => $etude_mention,
		'level'       => $level,
		'semester'    => $nbr_semester,
	]);

	if (!$result_finance) {
		$dtb->rollBack();
		echo json_encode([
			'success' => false,
			'message' => 'Configuration financière introuvable pour: ' . $status . ' / ' . $etude_mention . ' / L' . $level . ' / Semestre ' . $nbr_semester
		]);
		exit;
	}

	/* Le bareme est valide : l'annee scolaire de l'etudiant peut etre alignee
	   sur la session demandee. Dans la transaction, donc annulee avec le reste
	   si une ecriture suivante echoue. */
	$updateStd = $dtb->prepare('UPDATE tbl_2024_etudiant SET annee_scolaire = :annee_scolaire WHERE id = :id');
	$updateStd->execute(['annee_scolaire' => $annee_scolaire, 'id' => $idS]);

	/* Un seul calcul pour l'inscription et pour le recalcul : voir
	   data/inscription.frais.php. Deux copies auraient divergé, comme l'ont
	   fait les six variantes de la regle du frais de graduation. */
	$fraisDus = inscriptionFraisCalcules($dtb, $result_finance, [
		'student_id'     => $student_id,
		'annee_scolaire' => $annee_scolaire,
		'semestre'       => $nbr_semester,
		'statut_dossier' => $show_Old_status['status'] ?? '',
		'abonnement'     => $showStudent['abonment'] ?? 0,
		'diplomant'      => $graduated,
	]);

	$nbr_day                = (int) $result_finance['nb_jours_semestre'];
	$cout_fraix_generaux    = $fraisDus['cout_fraix_generaux'];
	$cout_logement          = $fraisDus['cout_logement'];
	$cout_abonment          = $fraisDus['cout_abonment'];
	$cout_fondDepot_dortoir = $fraisDus['cout_fondDepot_dortoir'];
	$cout_frais_graduation  = $fraisDus['cout_frais_graduation'];
	$cout_costume           = $fraisDus['cout_costume'];
	$cout_voyage            = $fraisDus['cout_voyage'];

	if ($existingFinance) {
		// UPDATE la ligne finance existante (pas de suppression)
		$updateFinance = $dtb->prepare('UPDATE t_2024_etudiant_finace SET
			mention = :mention,
			level = :level,
			status = :status,
			cout_logement = :cout_logement,
			cout_fraix_generaux = :cout_fraix_generaux,
			cout_fondDepot_dortoir = :cout_fondDepot_dortoir,
			cout_frais_graduation = :cout_frais_graduation,
			cout_costume = :cout_costume,
			cout_voyage = :cout_voyage,
			date_entry = :date_entry
		WHERE student_id = :student_id AND session_id = :session_id');

		$updateFinance->execute(array(
			'mention' => $etude_mention,
			'level' => $level,
			'status' => $status,
			'cout_logement' => $cout_logement,
			'cout_fraix_generaux' => $cout_fraix_generaux,
			'cout_fondDepot_dortoir' => $cout_fondDepot_dortoir,
			'cout_frais_graduation' => $cout_frais_graduation,
			'cout_costume' => $cout_costume,
			'cout_voyage' => $cout_voyage,
			'date_entry' => $date_entry,
			'student_id' => $student_id,
			'session_id' => $session_id
		));
	} else {
		/* INSERT nouvelle ligne finance.
		   Toutes les colonnes NOT NULL sans valeur par defaut sont fournies
		   explicitement. La version precedente en omettait sept : la production
		   tourne avec un sql_mode permissif qui les remplissait silencieusement,
		   mais sous STRICT_TRANS_TABLES (MySQL 8 par defaut, donc en local)
		   l'INSERT echouait sur « Field 'cout_abonment' doesn't have a default
		   value ». Les montants encore inconnus a cette etape valent 0 : les
		   cours ne sont pas choisis, le mode de paiement pas encore selectionne. */
		$creatLineStdToFinance = $dtb->prepare('INSERT INTO t_2024_etudiant_finace(
			student_id,
			session_id,
			mention,
			level,
			status,
			cout_logement,
			cout_fraix_generaux,
			cout_fondDepot_dortoir,
			cout_abonment,
			cout_frais_graduation,
			cout_costume,
			cout_voyage,
			cout_totalCours,
			cout_totalLab,
			mode_payement,
			`remove`,
			date_entry,
			last_change_user_id,
			last_change_datetime
		) VALUES (
			:student_id,
			:session_id,
			:mention,
			:level,
			:status,
			:cout_logement,
			:cout_fraix_generaux,
			:cout_fondDepot_dortoir,
			:cout_abonment,
			:cout_frais_graduation,
			:cout_costume,
			:cout_voyage,
			:cout_totalCours,
			:cout_totalLab,
			:mode_payement,
			:remove,
			:date_entry,
			:last_change_user_id,
			:last_change_datetime
		)');

		$creatLineStdToFinance->execute(array(
			'student_id' => $student_id,
			'session_id' => $session_id,
			'mention' => $etude_mention,
			'level' => $level,
			'status' => $status,
			'cout_logement' => $cout_logement,
			'cout_fraix_generaux' => $cout_fraix_generaux,
			'cout_fondDepot_dortoir' => $cout_fondDepot_dortoir,
			'cout_abonment' => $cout_abonment,
			'cout_frais_graduation' => $cout_frais_graduation,
			'cout_costume' => $cout_costume,
			'cout_voyage' => $cout_voyage,
			'cout_totalCours' => 0,   // renseigne au choix des cours (etape 3)
			'cout_totalLab' => 0,     // idem
			'mode_payement' => '',    // choisi a l'etape 4
			'remove' => 0,
			'date_entry' => $date_entry,
			'last_change_user_id' => $userId,
			'last_change_datetime' => $date_entry
		));
	}

	/* Frais supplementaires du bareme — « Alliance francaise » et compagnie.
	   Ce sont des lignes, pas des colonnes cout_* : voir data/finance.frais.php.
	   Applique dans la transaction, donc annule avec le reste si une ecriture
	   suivante echoue. */
	fraisPersoAppliquer($dtb, (string) $student_id, (int) $session_id, [
		'std_status'  => $status,
		'std_mention' => $etude_mention,
		'level'       => $level,
		'semester'    => $nbr_semester,
	], is_numeric($userId ?? null) ? (int) $userId : null, [
		/* Contexte : ce qui permet a un frais de ne viser qu'une partie des
		   etudiants du bareme — diplomants, nouveaux, abonnes, internes
		   (fraisPersoConditions, data/finance.frais.php). */
		'diplomant'       => $graduated,
		'nouvel_etudiant' => $new_student,
		'abonne'          => $showStudent['abonment'] ?? 0,
		'statut'          => $status,
	]);

	if ($existingSession) {
		// UPDATE l'inscription session existante (pas de suppression)
		$updateSession = $dtb->prepare('UPDATE t_2024_inscription_session SET
			etude_mention = :etude_mention,
			status = :status,
			niveau_std = :niveau_std,
			abonment_std = :abonment_std,
			new_student = :new_student,
			data_completion = :data_completion,
			nbr_semester = :nbr_semester,
			annee_scolaire = :annee_scolaire,
			date_entry = :date_entry
		WHERE student_id = :student_id AND session_id = :session_id');
		$updateSession->execute(array(
			'etude_mention' => $etude_mention,
			'status' => $statutInscription,
			'niveau_std' => $niveauInscription,
			'abonment_std' => $abonnementInscription,
			'new_student' => $new_student,
			'data_completion' => $data_completion,
			'nbr_semester' => $nbr_semester,
			'annee_scolaire' => $annee_scolaire,
			'date_entry' => $date_entry,
			'student_id' => $student_id,
			'session_id' => $session_id
		));
	} else {
		// INSERT nouvelle inscription session
		$creatLineStdToSession = $dtb->prepare('INSERT INTO t_2024_inscription_session (
		student_id,
		etude_mention,
		status,
		niveau_std,
		abonment_std,
		new_student,
		data_completion,
		session_id,
		nbr_semester,
		annee_scolaire,
		date_entry
	) VALUES (
		:student_id,
		:etude_mention,
		:status,
		:niveau_std,
		:abonment_std,
		:new_student,
		:data_completion,
		:session_id,
		:nbr_semester,
		:annee_scolaire,
		:date_entry
	)');
	$creatLineStdToSession->execute(array(
		'student_id' => $student_id,
		'etude_mention' => $etude_mention,
		'status' => $statutInscription,
		'niveau_std' => $niveauInscription,
		'abonment_std' => $abonnementInscription,
		'new_student' => $new_student,
		'data_completion' => $data_completion,
		'session_id' => $session_id,
		'nbr_semester' => $nbr_semester,
		'annee_scolaire' => $annee_scolaire,
		'date_entry' => $date_entry
	));

	/* Inscription nouvelle : sa date, son heure et son agent sont gardes une
	   fois pour toutes ; en retard sur le calendrier de la session, la
	   penalite s'ajoute a ses frais divers (data/inscriptions.tardives.php). */
	$retard = itTracer($dtb, (int) $session_id, (string) $student_id, $userId > 0 ? $userId : null);
	}

	$dtb->commit();

	echo json_encode([
		'success' => true,
		'message' => 'Session créée avec succès',
		'session_id' => $session_id,
		'retard' => $retard ?? null,
		'data' => $debug_info
	]);

	} catch (Exception $transactionError) {
		$dtb->rollBack();
		throw $transactionError;
	}

	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'message' => 'Erreur: ' . $e->getMessage()
		]);
	}
?>