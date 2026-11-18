<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Paramètres généraux</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require_once(__DIR__ . '/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
	
				<div class="back flex-1 overflow-y-auto">
					<div class="w-full grid gap-4 xl:grid-cols-4 sm:grid-cols-1 p-4" data-tuto="parametres-tuiles">
						<?php if ($rg_user['level'] <= 1): ?>
						<a href="<?=$app_base?>/accounts/create">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-person-fill"></i>&nbsp;&nbsp; Utilisateur</b><br><br>
							<p class="text-slate-500">Consulter la liste des utilisateurs. Créer un nouveau utilisateur.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php if ($rg_user['level'] <= 1): ?>
						<a href="<?=$app_base?>/finance">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-currency-exchange"></i>&nbsp;&nbsp; Finance</b><br><br>
							<p class="text-slate-500">Voir, Modifier les détails financiaires des étudiants.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php if ($rg_user['level'] <= 3): ?>
						<a href="<?=$app_base?>/finance/account-balance">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-wallet2"></i>&nbsp;&nbsp; Solde de compte</b><br><br>
							<p class="text-slate-500">Afficher le matricule, account code, nom complet, solde et etat debitaire ou creditaire.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php if ($rg_user['level'] <= 3): /* comme FinanceController::echeances */ ?>
						<a href="<?=$app_base?>/finance/echeances">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-calendar-check"></i>&nbsp;&nbsp; Échéances de paiement</b><br><br>
							<p class="text-slate-500">Les dates limites de chaque tranche, session par session : celles de la fiche d'inscription et du guichet.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php if ($rg_user['level'] <= 3): ?>
						<a href="./login-locations">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-geo-alt-fill"></i>&nbsp;&nbsp; Localisations GPS</b><br><br>
							<p class="text-slate-500">Voir les coordonnées GPS des connexions utilisateurs sur une carte.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php if ($rg_user['level'] <= 1): ?>
						<a href="<?=$app_base?>/sessions">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-calendar2-week-fill"></i>&nbsp;&nbsp; Session</b><br><br>
							<p class="text-slate-500">Gérer les sessions académiques (début et fin de semestre).</p>
							</div>
						</a>
						<?php endif; ?>
					<?php /* Superadmin et registraires. L'affichage suit le verrou du
					         controleur (AdminController::manageCoursesSessions) : une tuile
					         active qui mene a un 403 est pire qu'une tuile absente. */
					      $peutCoursSession = in_array((string)($rg_user['privilege'] ?? ''), ['superadmin', 'registrar'], true)
					                       || (int)($rg_user['level'] ?? 99) === 1; ?>
					<?php if ($peutCoursSession): ?>
					<a href="<?=$app_base?>/courses-sessions">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-book-fill"></i>&nbsp;&nbsp; Cours par session</b><br><br>
							<p class="text-slate-500">Gérer les cours offerts par semestre et année scolaire — affecte l'ajout de cours pour les étudiants.</p>
							</div>
						</a>
					<?php endif; ?>
						<?php /* Autorisation de réinscription : dans la barre latérale (section Étudiants). */ ?>
						<a href="<?=$app_base?>/reglement">
								<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
								<b class="text-lg"><i class="bi-journal-text"></i>&nbsp;&nbsp; Bulletin académique</b><br><br>
								<p class="text-slate-500">Le règlement des études en vigueur : notation, crédits, examens, diplomation. Consultable par tous.</p>
								</div>
							</a>
						<?php if ($rg_user['level'] <= 3): ?>
						<a href="<?=$app_base?>/settings/diplome">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-award-fill"></i>&nbsp;&nbsp; Diplôme</b><br><br>
							<p class="text-slate-500">Signatures, arrêtés ministériels et calage d'impression du nouveau modèle de diplôme.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php /* Sans cette tuile, la page des reglages de la vitrine n'etait
						         atteignable qu'en tapant son adresse : deployee et
						         introuvable. Meme niveau que le diplome — ce qu'on y change
						         s'affiche immediatement sur le site public. */ ?>
						<?php /* Superadmin uniquement : ce qui se regle ici s'affiche sur le
						         site public. Meme verrou que le controleur. */ ?>
						<?php if ((int)($rg_user['level'] ?? 99) === 1): ?>
						<a href="<?=$app_base?>/settings/vitrine">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-globe2"></i>&nbsp;&nbsp; Page vitrine</b><br><br>
							<p class="text-slate-500">Textes, dates de rentrée, coordonnées et visuels du site public. Les modifications s'appliquent dès l'enregistrement.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php /* Superadmin uniquement : le sigle d'une mention sert de cle
						         dans cinq tables et son libelle rattache les fiches
						         etudiantes. Meme verrou que le controleur — une tuile
						         active qui mene a un 403 est pire qu'une tuile absente. */ ?>
						<?php if ((int)($rg_user['level'] ?? 99) === 1): ?>
						<a href="<?=$app_base?>/settings/mentions">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-mortarboard-fill"></i>&nbsp;&nbsp; Mentions</b><br><br>
							<p class="text-slate-500">Filières de l'université : libellés, domaines, création. Le sigle sert de clé aux inscriptions, cours et barèmes.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php /* Superadmin uniquement, comme le contrôleur : publier les notes d'une
						         session avant l'aval du Sénat les divulgue à tous les étudiants. */ ?>
						<?php if ((int)($rg_user['level'] ?? 99) === 1): ?>
						<a href="<?=$app_base?>/settings/espace-etudiant">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-eye-fill"></i>&nbsp;&nbsp; Espace étudiant</b><br><br>
							<p class="text-slate-500">Publication des notes session par session après l'aval du Sénat, et rubriques visibles par les étudiants.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php /* Superadmin uniquement : le journal montre les données envoyées et les
						         valeurs modifiées de tout le projet. Même verrou que le
						         contrôleur et que app/audit.liste.php. */ ?>
						<?php if ((int)($rg_user['level'] ?? 99) === 1): ?>
						<a href="<?=$app_base?>/settings/logs">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white min-h-[160px] h-full'>
							<b class="text-lg"><i class="bi-activity"></i>&nbsp;&nbsp; Journal d'activité</b><br><br>
							<p class="text-slate-500">Connexions, pages ouvertes, modifications, exports et déconnexions de chaque utilisateur, avec les valeurs avant et après.</p>
							</div>
						</a>
						<?php endif; ?>
						<?php /* L'envoi de mails se fait depuis la Boite mail (menu Communication). */ ?>
					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>	
</body>
</html>