<?php 
	require ('../../data/backdb.php');

	// Utiliser $_REQUEST pour supporter GET et POST
	$id = $_REQUEST['id'] ?? $_GET['id'] ?? null;
	$student_id = $_REQUEST['student_id'] ?? $_GET['student_id'] ?? null;

	// Vérification si l'étudiant est suspendu
	$checkSuspension = $dtb->prepare('SELECT suspended, date_fin_suspension FROM tbl_2024_etudiant WHERE student_id = :student_id');
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
	$user_id_entry = $_REQUEST['user_id'] ?? $_GET['user_id'] ?? null;
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

/*$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$*/

	$searchSs = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_name = :session_name AND session_year = :session_year');
	$searchSs->execute(['session_name' => $semesterSession, 'session_year' => $annee_scolaire]);

	$showSs = $searchSs->fetch();
	$session_id = $showSs['session_id'];


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

				$updateSession->execute();

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
				'niveau_std' => $annee_etude,
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

			// Créer aussi la ligne finance si elle n'existe pas
			$checkFinanceExists = $dtb->prepare('SELECT id FROM t_2024_etudiant_finace WHERE student_id = :student_id AND session_id = :session_id');
			$checkFinanceExists->execute(['student_id' => $student_id, 'session_id' => $session_id]);
			if (!$checkFinanceExists->fetch()) {
				$financeConfig = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence WHERE std_status = :status AND std_mention = :mention AND level = :level AND semester = :semester');
				$financeConfig->execute(['status' => $status, 'mention' => $etude_envisage_sign, 'level' => $annee_etude, 'semester' => $nbr_semester]);
				$fConfig = $financeConfig->fetch();
				if ($fConfig) {
					$fc_nbr_day = intval($fConfig['nb_jours_semestre']);
					$fc_logement = floatval($fConfig['dortoir']) * $fc_nbr_day;
					$fc_fraix = floatval($fConfig['frais_generaux']);
					$fc_abonment = ($abonment == 1) ? floatval($fConfig['cafeteria']) * $fc_nbr_day : 0;
					$fc_voyage = floatval($fConfig['frais_voyage']);
					$fc_graduation = ($graduated == 1) ? floatval($fConfig['frais_graduation']) : 0;
					$fc_costume = ($annee_etude == 1 && $new_student == 1) ? floatval($fConfig['frais_costume']) : 0;
					$fc_depot = ($annee_etude == 1 && $status == 'Interne') ? floatval($fConfig['fond_depot']) : 0;

					$createFinance = $dtb->prepare('INSERT INTO t_2024_etudiant_finace (
						student_id, session_id, mention, level, status,
						cout_logement, cout_fraix_generaux, cout_fondDepot_dortoir,
						cout_abonment, cout_frais_graduation, cout_costume, cout_voyage, date_entry
					) VALUES (
						:student_id, :session_id, :mention, :level, :status,
						:cout_logement, :cout_fraix_generaux, :cout_fondDepot_dortoir,
						:cout_abonment, :cout_frais_graduation, :cout_costume, :cout_voyage, :date_entry
					)');
					$createFinance->execute([
						'student_id' => $student_id,
						'session_id' => $session_id,
						'mention' => $etude_envisage_sign,
						'level' => $annee_etude,
						'status' => $status,
						'cout_logement' => $fc_logement,
						'cout_fraix_generaux' => $fc_fraix,
						'cout_fondDepot_dortoir' => $fc_depot,
						'cout_abonment' => $fc_abonment,
						'cout_frais_graduation' => $fc_graduation,
						'cout_costume' => $fc_costume,
						'cout_voyage' => $fc_voyage,
						'date_entry' => $date_entry
					]);
				}
			}
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
			
			$grade = 0;
				$recherche = $dtb->prepare("SELECT * FROM t_2023_cours WHERE id = :id");
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
				
				$verification_finance_licence = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence WHERE std_mention = :mention AND level = :level AND semester = :semester');
				$verification_finance_licence->execute(['mention' => $etude_envisage_sign, 'level' => $yearlevel, 'semester' => $semester]);

				$result_finance = $verification_finance_licence->fetch();

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