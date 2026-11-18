<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
/* Ajout d'un cours : niveaux 1 a 6, comme le guichet d'inscription dont c'est l'etape 3
   (inscription/app/_guard.php, INSCRIPTION_NIVEAU_REQUIS) ; le chef de mention (niveau 6)
   inscrit ses etudiants et etait refuse ici. */
exigerConnexion(6); refuserEtudiant(); refuserEnseignant();
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	require ('../../data/backdb.php');
	// Bareme financier par session, avec repli sur le bareme de base.
	require_once(__DIR__ . '/../../data/finance.bareme.php');
	require_once(__DIR__ . '/../../data/finance.frais.php'); // frais supplementaires du bareme
	require_once(__DIR__ . '/../../data/inscription.frais.php'); // ligne de frais et totaux des cours

	// Utiliser $_REQUEST pour supporter GET et POST
	$id = $_REQUEST['id'] ?? $_GET['id'] ?? null;
	$student_id = $_REQUEST['student_id'] ?? $_GET['student_id'] ?? null;

	// Vérification si l'étudiant est suspendu
	$checkSuspension = $dtb->prepare('SELECT suspended, date_fin_suspension FROM tbl_2024_etudiant WHERE student_id = :student_id ORDER BY remove ASC, id ASC LIMIT 1');
	$checkSuspension->execute(['student_id' => $student_id]);
	$suspensionData = $checkSuspension->fetch();
	
	if ($suspensionData && $suspensionData['suspended'] == 1) {
		// Vérifier si la suspension est toujours active
		$dateFin = $suspensionData['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			echo '<div class="bg-red-500 text-white p-4 rounded-lg text-center">
				<i class="bi bi-exclamation-triangle-fill text-2xl"></i><br>
				<b>ACCÈS REFUSÉ</b><br>
				Cet étudiant est actuellement suspendu et ne peut pas prendre de cours.
			</div>';
			exit;
		}
	}

	$semesterSession = $_POST['semesterSession'];

	if ($semesterSession == "Premier semestre") {
		$nbr_semester =1;
	}elseif ($semesterSession == "Semestre d'été") {
		$nbr_semester =3;
	}elseif ($semesterSession == "Deuxième semestre") {
		$nbr_semester =2;
	}elseif ($semesterSession == "Semestre d'hiver") {
		$nbr_semester =4;
	}

	$annee_scolaire = $_POST['annee_scolaire'];
	$page = $_REQUEST['page'] ?? $_GET['page'] ?? null;
	$user_id_entry = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null; // auteur : le compte connecte, pas l'adresse
	$date_entry = date('Y-m-d');
	$ajout = '1';

/*$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$*/
	$etude_envisage = $_REQUEST['etude_envisage'] ?? $_GET['etude_envisage'] ?? null;

	$findInfiliere = $dtb->prepare('SELECT * FROM filiere WHERE filiere_description = :etude_envisage');
	$findInfiliere->execute(['etude_envisage' => $etude_envisage]);
	$showInfiliere = $findInfiliere->fetch();
	$etude_envisage_sign = $showInfiliere['filiere_sigle'];

	$status = $_REQUEST['status'] ?? $_GET['status'] ?? null;
	$new_student = $_REQUEST['new_student'] ?? $_GET['new_student'] ?? null;
	$graduated = $_REQUEST['graduated'] ?? $_GET['graduated'] ?? null;
	$student_adresse = $_REQUEST['student_adresse'] ?? $_GET['student_adresse'] ?? null;
	$etude_option = $_REQUEST['etude_option'] ?? $_GET['etude_option'] ?? null;
	$annee_etude = $_REQUEST['annee_etude'] ?? $_GET['annee_etude'] ?? null;
	$sponsor_nom = $_REQUEST['sponsor_nom'] ?? $_GET['sponsor_nom'] ?? null;
	$sponsor_prenom = $_REQUEST['sponsor_prenom'] ?? $_GET['sponsor_prenom'] ?? null;
	$sponsor_tel = $_REQUEST['sponsor_tel'] ?? $_GET['sponsor_tel'] ?? null;
	$sponsor_adresse = $_REQUEST['sponsor_adresse'] ?? $_GET['sponsor_adresse'] ?? null;
	$situationf = $_REQUEST['situationf'] ?? $_GET['situationf'] ?? null;
	$nom_conjoint = $_REQUEST['nom_conjoint'] ?? $_GET['nom_conjoint'] ?? null;
	$nb_enfant = $_REQUEST['nb_enfant'] ?? $_GET['nb_enfant'] ?? 0;
	$abonment = $_REQUEST['abonment'] ?? $_GET['abonment'] ?? 0;

	// new_student : une fois la session connue (plus bas, inscNouvelEtudiant)

/*$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$*/

	/* Annee passee, choisie dans le volet « Ajout de cours » : on complete un
	   cursus deja clos. L'inscription de cette session decrit l'etudiant tel
	   qu'il ETAIT : on ne la reecrit pas avec son profil d'aujourd'hui (un
	   etudiant de Master 2 completant son Master 1 y serait passe en Master
	   2). Si elle manque, elle est creee au niveau des cours ajoutes. */
	require_once __DIR__ . '/../../data/cours.offerts.php';
	$sessionPassee = coursAnneePassee((string) $annee_scolaire);
	$niveauSession = $annee_etude;
	if ($sessionPassee && preg_match('~^[0-5]$~', (string) ($_POST['niveau_bloc'] ?? ''))) {
		$niveauSession = (int) $_POST['niveau_bloc'];
	}

	$searchSs = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_name = :session_name AND session_year = :session_year');
	$searchSs->execute(['session_name' => $semesterSession, 'session_year' => $annee_scolaire]);

	$showSs = $searchSs->fetch();
	/* Pas de session pour cette annee (non creee sur la page « Sessions ») :
	   on refuse, plutot que d'ecrire des notes rattachees a aucune session.
	   Le volet affiche alors « Erreur lors de l'ajout du cours ». */
	if (!$showSs) {
		http_response_code(400);
		echo 'Aucune session « ' . htmlspecialchars((string) $semesterSession, ENT_QUOTES, 'UTF-8') . ' » en ' . htmlspecialchars((string) $annee_scolaire, ENT_QUOTES, 'UTF-8') . '.';
		exit;
	}
	$session_id = $showSs['session_id'];

	/* Nouvel etudiant : aucune autre session suivie (inscriptions et notes), et pas
	   marque « ancien » — sur son inscription a cette session, sinon sur sa fiche. */
	require_once __DIR__ . '/../../data/inscription.session.php';
	$drapeau = $dtb->prepare('SELECT new_student FROM t_2024_inscription_session WHERE student_id = :s AND session_id = :ses ORDER BY id LIMIT 1');
	$drapeau->execute(['s' => $student_id, 'ses' => $session_id]);
	$drapeauSession = $drapeau->fetchColumn();
	if ($drapeauSession === false) {
		$drapeau = $dtb->prepare('SELECT new_student FROM tbl_2024_etudiant WHERE student_id = :s ORDER BY `remove` ASC, id ASC LIMIT 1');
		$drapeau->execute(['s' => $student_id]);
		$drapeauSession = $drapeau->fetchColumn();
	}
	$new_student = inscNouvelEtudiant($dtb, (string) $student_id, (int) $session_id, $drapeauSession === false ? null : $drapeauSession);


	if(isset($_POST['checklist'])) {
		/*========================================== UPDATE STUDENT ON SESSION ==========================*/	

		$verification = $dtb->prepare('SELECT * FROM t_2024_inscription_session WHERE student_id = :student_id AND session_id = :session_id');
		$verification->execute(['student_id' => $student_id, 'session_id' => $session_id]);

		$answering = $verification->fetch();

		if (!empty($answering)) {
			$idForSession = $answering['id'];
			
			$updateSession = $dtb->prepare("UPDATE t_2024_inscription_session SET 	
				status=:status,
				new_student=:new_student,
				graduated=:graduated,
				nbr_semester=:nbr_semester,
				adresse_actuel_std=:adresse_actuel_std,
				parcours_std=:parcours_std,
				niveau_std=:niveau_std,
				sponsor_name=:sponsor_name,
				sponsor_lastName=:sponsor_lastName,
				sponsor_contact=:sponsor_contact,
				sponsor_address=:sponsor_address,
				etat_civil_std=:etat_civil_std,
				conjoint_name=:conjoint_name,
				nb_enfant=:nb_enfant,
				abonment_std=:abonment_std,
				annee_scolaire=:annee_scolaire,
				date_entry=:date_entry

			WHERE id=:idForSession");

				$updateSession->bindParam(':status',$status,PDO::PARAM_STR);
				$updateSession->bindParam(':new_student',$new_student,PDO::PARAM_INT);
				$updateSession->bindParam(':graduated',$graduated,PDO::PARAM_INT);
				$updateSession->bindParam(':nbr_semester',$nbr_semester,PDO::PARAM_INT);
				$updateSession->bindParam(':adresse_actuel_std',$student_adresse,PDO::PARAM_STR);
				$updateSession->bindParam(':parcours_std',$etude_option,PDO::PARAM_STR);
				$updateSession->bindParam(':niveau_std',$annee_etude,PDO::PARAM_INT);
				
				$updateSession->bindParam(':sponsor_name',$sponsor_nom,PDO::PARAM_STR);
				$updateSession->bindParam(':sponsor_lastName',$sponsor_prenom,PDO::PARAM_STR);
				$updateSession->bindParam(':sponsor_contact',$sponsor_tel,PDO::PARAM_STR);
				$updateSession->bindParam(':sponsor_address',$sponsor_adresse,PDO::PARAM_STR);
				$updateSession->bindParam(':etat_civil_std',$situationf,PDO::PARAM_STR);
				$updateSession->bindParam(':conjoint_name',$nom_conjoint,PDO::PARAM_STR);
				$updateSession->bindParam(':nb_enfant',$nb_enfant,PDO::PARAM_INT);
				$updateSession->bindParam(':abonment_std',$abonment,PDO::PARAM_INT);
				
				$updateSession->bindParam(':annee_scolaire',$annee_scolaire,PDO::PARAM_STR);
				$updateSession->bindParam(':date_entry',$date_entry,PDO::PARAM_STR);

				$updateSession->bindParam(':idForSession',$idForSession,PDO::PARAM_INT);

				// Annee passee : l'inscription d'alors reste telle quelle (voir plus haut).
				if (!$sessionPassee) {
					$updateSession->execute();
				}

		}else{

			$insertSession = $dtb->prepare('INSERT INTO t_2024_inscription_session (
				student_id,
				etude_mention,
				status,
				new_student,
				graduated,
				session_id,
				nbr_semester,
				adresse_actuel_std,
				parcours_std,
				niveau_std,
				sponsor_name,
				sponsor_lastName,
				sponsor_contact,
				sponsor_address,
				etat_civil_std,
				conjoint_name,
				nb_enfant,
				abonment_std,
				annee_scolaire,
				date_entry
				
			) VALUES (
				:student_id,
				:etude_mention,
				:status,
				:new_student,
				:graduated,
				:session_id,
				:nbr_semester,
				:adresse_actuel_std,
				:parcours_std,
				:niveau_std,
				:sponsor_name,
				:sponsor_lastName,
				:sponsor_contact,
				:sponsor_address,
				:etat_civil_std,
				:conjoint_name,
				:nb_enfant,
				:abonment_std,
				:annee_scolaire,
				:date_entry

			)');$insertSession->execute(array(
				'student_id' => $student_id,
				'etude_mention' => $etude_envisage_sign,
				'status' => $status,
				'new_student' => $new_student,
				'graduated' => $graduated,
				'session_id' => $session_id,
				'nbr_semester' => $nbr_semester,
				'adresse_actuel_std' => $student_adresse,
				'parcours_std' => $etude_option,
				'niveau_std' => $niveauSession,
				'sponsor_name' => $sponsor_nom,
				'sponsor_lastName' => $sponsor_prenom,
				'sponsor_contact' => $sponsor_tel,
				'sponsor_address' => $sponsor_adresse,
				'etat_civil_std' => $situationf,
				'conjoint_name' => $nom_conjoint,
				'nb_enfant' => $nb_enfant,
				'abonment_std' => $abonment,
				'annee_scolaire' => $annee_scolaire,
				'date_entry' => $date_entry

			));

		}

		/* La ligne de frais de la session, si elle manque : a la creation de
		   l'inscription, et pour une inscription qui n'en avait pas (675 au
		   07/10/2026 : l'ajout de cours ne la creait qu'avec l'inscription).
		   Calcul commun du guichet (data/inscription.frais.php), au tarif de
		   l'annee pour une session passee. Cette copie-ci appliquait ses
		   propres regles de costume et de fond de depot. */
		$ctxFrais = inscriptionFraisContexte($dtb, (string) $student_id, (int) $session_id);
		$prevusFrais = $ctxFrais ? inscriptionFraisPrevus($dtb, $ctxFrais) : null;
		if ($prevusFrais) {
			inscriptionFraisCreer($dtb, $ctxFrais, $prevusFrais, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
		}
/*====================================================================*/
		
		$tCout = 0;
		$tCout_lab = 0;
		$n_lab = 0;
		$somm_lab = 0;

		foreach($_POST['checklist'] as $i){
			
			// Vérifier si le cours existe déjà pour cet étudiant
			$checkExisting = $dtb->prepare('SELECT id, grade FROM t_2023_notes WHERE id_cours = :id_cours AND student_id = :student_id AND ajout = 1 AND remove = 0');
			$checkExisting->execute(['id_cours' => $i, 'student_id' => $student_id]);
			$existingNote = $checkExisting->fetch();
			
			if ($existingNote) {
				// Si le cours est en échec (note > 0 et < 10), autoriser la reprise dans une nouvelle session
				if ($existingNote['grade'] > 0 && $existingNote['grade'] < 10) {
					// Marquer l'ancien cours comme retiré (historique) avant d'ajouter la reprise
					$archiveOld = $dtb->prepare('UPDATE t_2023_notes SET remove = 1, ajout = 0, retrait_date = :retrait_date, last_change_user_id = :user_id, last_change_datetime = :datetime WHERE id = :id');
					$archiveOld->execute([
						'retrait_date' => date('Y-m-d H:i:s'),
						'user_id' => $user_id_entry,
						'datetime' => date('Y-m-d H:i:s'),
						'id' => $existingNote['id']
					]);
					
					// Enregistrer dans l'historique des modifications de notes (reprise)
					try {
						$getCourseInfo = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :id');
						$getCourseInfo->execute(['id' => $existingNote['id']]);
						$courseInfo = $getCourseInfo->fetch();
						if ($courseInfo) {
							$insertHistory = $dtb->prepare('INSERT INTO t_notes_modification_history (
								note_id, student_id, session_id, cours_sigle, cours_titre,
								old_grade, new_grade, action_type, action_by, action_date, ip_address, commentaire
							) VALUES (
								:note_id, :student_id, :session_id, :cours_sigle, :cours_titre,
								:old_grade, :new_grade, :action_type, :action_by, :action_date, :ip_address, :commentaire
							)');
							$insertHistory->execute([
								'note_id' => $existingNote['id'],
								'student_id' => $student_id,
								'session_id' => $courseInfo['session_id'],
								'cours_sigle' => $courseInfo['Sigle'],
								'cours_titre' => $courseInfo['title_cours'],
								'old_grade' => $existingNote['grade'],
								'new_grade' => null,
								'action_type' => 'suppression',
								'action_by' => $user_id_entry,
								'action_date' => date('Y-m-d H:i:s'),
								'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
								'commentaire' => 'Reprise du cours suite à échec (note: ' . $existingNote['grade'] . '/20)'
							]);
						}
					} catch (PDOException $e) {
						// Ignorer l'erreur d'historique
					}
					// Continuer pour créer le nouveau cours dans la nouvelle session
				} else {
					// Le cours est réussi ou en attente, ne pas ajouter de doublon
					continue;
				}
			}
			
			/* Un cours ajoute n'est pas encore note : -4, affiche NN et hors
			   moyenne (data/notes.statut.php). Un 0 est une vraie note : il
			   s'affichait en echec (E), jusqu'a ce qu'on le repasse a la main en
			   NN, ou que la remise de l'enseignant le remplace. */
			$grade = '-4';
				$recherche =$dtb->prepare("SELECT * FROM t_2023_cours WHERE id = :id");
				$recherche->execute(['id' => $i]);
				
				$affiche = $recherche->fetch();
					$cours_id = $affiche['id'];
		      		$sigle = $affiche['Sigle'];
				    $id_cours = $affiche['id'];
					$title_cours = $affiche['title'];
					$credit = $affiche['nb_crd'];
					$lab = $affiche['lab'];

					$cours_category = $affiche['category'];
					$semester = $affiche['semester'];
					$yearlevel = $affiche['yearlevel'];
					$teacher_id = $affiche['id_teacher'];
				
				// Idem : le tarif suit la session. Le statut n'entre pas dans
				// la cle ici, comme dans la version d'origine — l'ecolage et
				// les frais de labo ne dependent pas de la residence.
				$result_finance = baremeLigne($dtb, $session_id, [
					'std_mention' => $etude_envisage_sign,
					'level'       => $yearlevel,
					'semester'    => $semester,
				]);

				/* Annee passee sans bareme de session : l'ecolage est celui de
				   CETTE annee (regle du registrariat), lu dans ce qui a ete
				   facture alors (baremeEcolageHistorique), et non le tarif
				   d'aujourd'hui du bareme de base. Un ecolage a 0 (mention sans
				   Master payant) reste a 0. */
				if ($sessionPassee && $result_finance && empty($result_finance['bareme_surcharge'])
					&& (float) $result_finance['ecolage'] > 0) {
					$ecolageAnnee = baremeEcolageHistorique($dtb, (string) $annee_scolaire, (int) $yearlevel);
					if ($ecolageAnnee !== null) {
						$result_finance['ecolage'] = $ecolageAnnee;
					}
				}

				$cout = $result_finance['ecolage'] * $credit;

				if ($result_finance['laboratory_info'] == 0) {
					$cout_lab = $result_finance['laboratory_lang'];
				}else{
					$cout_lab = $result_finance['laboratory_info'];
				}

					/*$cout = $affiche['cout'];
					$cout_lab = $affiche['cout_lab'];*/

					if ($lab != 0) {
						$nLab = 1;
					}else{
						$nLab = 0;
					}
					

			$insert = $dtb->prepare("INSERT INTO t_2023_notes(
				id_cours,
				Sigle,
				title_cours,
				student_id,
				teacher_id,
				session_id,
				semester,
				yearlevel,
				annee_scolaire,
				credit,
				lab,
				grade,
				ajout,
				cours_category,
				user_id_entry,
				date_entry
				) VALUES (
				:id_cours,
				:Sigle,
				:title_cours,
				:student_id,
				:teacher_id,
				:session_id,
				:semester,
				:yearlevel,
				:annee_scolaire,
				:credit,
				:lab,
				:grade,
				:ajout,
				:cours_category,
				:user_id_entry,
				:date_entry
			)");
			$insert->execute(array(
					'id_cours' => $id_cours,
					'Sigle' => $sigle,
					'title_cours' => $title_cours,
					'student_id' => $student_id,
					'teacher_id' => $teacher_id,
					'session_id' => $session_id,
					'semester' => $semester,
					'yearlevel' => $yearlevel,
					'annee_scolaire' => $annee_scolaire,
					'credit' => $credit,
					'lab' => $lab,
					'grade' => $grade,
					'ajout' => $ajout,
					'cours_category' => $cours_category,
					'user_id_entry' => $user_id_entry,
					'date_entry' => $date_entry
			));

			if ($lab != 0) {						
					
				$n_lab =+ $n_lab + 1;
				
				if ($n_lab <= 2) {
					$somm_lab =+ $somm_lab + $lab;
				}

			}

			$insert_Finance = $dtb->prepare("INSERT INTO t_2024_cours_finance(
				student_id,
				session_id,
				cours_id,
				cours_sigle,
				cours_title,
				cours_credit,
				cours_cout,
				lab_cout,
				date_entry,
				last_change_user_id
			) VALUES (
				:student_id,
				:session_id,
				:cours_id,
				:cours_sigle,
				:cours_title,
				:cours_credit,
				:cours_cout,
				:lab_cout,
				:date_entry,
				:last_change_user_id
			)");
			$insert_Finance->execute(array(
				'student_id' => $student_id,
				'session_id' => $session_id,
				'cours_id' => $cours_id,
				'cours_sigle' => $sigle,
				'cours_title' => $title_cours,
				'cours_credit' => $credit,
				'cours_cout' => $cout,
				'lab_cout' => $somm_lab,
				'date_entry' => $date_entry,
				'last_change_user_id' => $user_id_entry
			));

		/*===================== FIANCES =====================*/
		
		}
		
		$tCout;
		$tCout_lab;
		
		/* Totaux de TOUS les cours de la session, comme la fiche d'inscription
		   (inscriptionFraisCours) : $tCout ne comptait que les cours de cet
		   ajout, et un second ajout effacait le total du premier. */
		$totauxCours = inscriptionFraisCours($dtb, (string) $student_id, (int) $session_id);
		$tCout = $totauxCours['cout_totalCours'];
		$tCout_lab = $totauxCours['cout_totalLab'];
		$updateTotality = $dtb->prepare("UPDATE t_2024_etudiant_finace SET cout_totalCours=:cout_totalCours,cout_totalLab=:cout_totalLab WHERE student_id=:student_id AND session_id=:session_id");

		$updateTotality->bindParam(':cout_totalCours',$tCout,PDO::PARAM_STR);
		$updateTotality->bindParam(':cout_totalLab',$tCout_lab,PDO::PARAM_STR);
		$updateTotality->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$updateTotality->bindParam(':session_id',$session_id,PDO::PARAM_INT);
		$updateTotality->execute();
	}
	
	//header('location:../../src/student.php?id='.$id.'&page=transcript');
	
	//header('location:../../src/genPDF/dom.php?id='.$id.'&page=transcript&ptype=ficheInscription');
 ?>