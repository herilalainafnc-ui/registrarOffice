<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Créer un étudiant</title>
<?php
/* ===================== PONT CANDIDATURE -> ETUDIANT =====================
   Ouverte depuis une candidature acceptee (/students/create?candidat=ID),
   cette page pre-remplit ce qui a deja ete saisi par le candidat. Les
   valeurs restent modifiables : c'est un brouillon soumis a l'agent, pas
   une saisie automatique.

   Seule une candidature ACCEPTEE peut etre convertie : on ne cree pas un
   etudiant a partir d'un dossier refuse ou non instruit.
   ====================================================================== */
$pontCandidat   = null;   // candidature source, si le pont est actif
$preNom         = '';
$prePrenom      = '';
$preTel         = '';
$preMentionSigle = '';
$preDateNaiss   = '';
$preLieuNaiss   = '';
$preSexe        = '';
$preSituation   = '';
$preNationalite = '';
$prePays        = '';
$preAdresse     = '';
$prePere        = '';
$preMere        = '';
$preTelParent   = '';
$preTuteur      = '';
$preTelTuteur   = '';
$preParcours    = '';

/* Candidature non convertible : on n'ouvre pas le formulaire, on explique.

   Avant, la requete filtrait sur « statut = accepte » et un dossier deja
   converti renvoyait simplement null : la page s'ouvrait donc sur un
   formulaire VIDE, sans un mot. L'agent pouvait le remplir de bout en bout et
   creer un second etudiant pour le meme candidat. C'est le doublon qu'on
   cherche a eviter — la garde de app/.student/addStd.php le refuse cote
   serveur, mais il faut aussi le dire ici, avant la saisie. */
$pontBloque = null;   // ['titre' => …, 'texte' => …, 'lien' => …, 'lienTexte' => …]

if (!empty($_GET['candidat'])) {
	$idCand = (int) $_GET['candidat'];
	if ($idCand > 0) {
		try {
			// Lue quel que soit son statut : c'est le statut qui decide de la suite.
			$req = $dtb->prepare('SELECT * FROM candidat WHERE id = ? LIMIT 1');
			$req->execute([$idCand]);
			$dossier = $req->fetch(PDO::FETCH_ASSOC) ?: null;

			if (!$dossier) {
				$pontBloque = [
					'titre' => 'Candidature introuvable',
					'texte' => 'Ce dossier n’existe plus. Il a peut-être été supprimé.',
				];
			} elseif ($dossier['statut'] === 'converti') {
				/* On cherche la fiche de l'etudiant pour pouvoir y renvoyer :
				   la reponse utile a « et maintenant ? » est un lien, pas un
				   simple refus. */
				$ficheId = null;
				if (!empty($dossier['student_id'])) {
					$f = $dtb->prepare('SELECT id FROM tbl_2024_etudiant WHERE student_id = ? ORDER BY remove ASC, id ASC LIMIT 1');
					$f->execute([$dossier['student_id']]);
					$ficheId = $f->fetchColumn() ?: null;
				}

				$pontBloque = [
					'titre' => 'Cet étudiant est déjà créé',
					'texte' => trim($dossier['nom_complet']) . ' a déjà été inscrit'
						. (!empty($dossier['student_id']) ? ' sous le matricule ' . $dossier['student_id'] : '')
						. ($dossier['date_traitement']
							? ', le ' . date('d/m/Y à H:i', strtotime($dossier['date_traitement']))
							: '')
						. '. Créer une seconde fiche produirait un doublon.',
					'lien' => $ficheId
						? (($app_base ?? '') . '/student?id=' . (int) $ficheId . '&page=information')
						: null,
					'lienTexte' => 'Ouvrir la fiche de l’étudiant',
				];
			} elseif ($dossier['statut'] !== 'accepte') {
				/* Ce cas donnait aussi un formulaire vide et muet. */
				$libelles = [
					'nouveau'  => 'n’a pas encore été instruite',
					'en_cours' => 'est encore en cours d’instruction',
					'refuse'   => 'a été refusée',
				];
				$pontBloque = [
					'titre' => 'Candidature non acceptée',
					'texte' => 'La candidature de ' . trim($dossier['nom_complet']) . ' '
						. ($libelles[$dossier['statut']] ?? 'n’est pas au statut « accepté »')
						. '. Seule une candidature acceptée peut donner lieu à une inscription.',
					// Candidatures : superadmin et registrariat seulement (AdmissionController).
					'lien' => hasAnyPrivilege(['superadmin', 'registrar'])
						? ($app_base ?? '') . '/candidatures?statut=' . rawurlencode($dossier['statut'])
						: null,
					'lienTexte' => 'Revenir aux candidatures',
				];
			} else {
				$pontCandidat = $dossier;
			}
		} catch (Throwable $e) {
			error_log('Pont candidature: ' . $e->getMessage());
		}
	}
}

if ($pontCandidat) {
	/* Depuis l'elargissement du formulaire public, la candidature porte des
	   colonnes `nom` et `prenom` distinctes. Les candidatures anterieures n'ont
	   que `nom_complet` : on retombe alors sur la coupure au premier espace
	   (convention locale : le nom de famille vient en premier), a charge pour
	   l'agent de corriger. */
	if (!empty($pontCandidat['nom']) || !empty($pontCandidat['prenom'])) {
		$preNom    = trim((string) $pontCandidat['nom']);
		$prePrenom = trim((string) $pontCandidat['prenom']);
	} else {
		$nomComplet = trim($pontCandidat['nom_complet'] ?? '');
		$morceaux   = preg_split('/\s+/', $nomComplet, 2);
		$preNom     = $morceaux[0] ?? '';
		$prePrenom  = $morceaux[1] ?? '';
	}

	/* L'email n'est VOLONTAIREMENT pas repris de la candidature. Celui du
	   dossier est l'adresse personnelle qui a servi a joindre le candidat
	   avant son inscription ; l'adresse etudiante, elle, est derivee du nom
	   par le script en bas de page (nom.pre@zurcher.edu.mg). Pre-remplir ici
	   ferait enregistrer l'adresse personnelle comme adresse institutionnelle. */
	$preTel     = $pontCandidat['telephone'] ?? '';

	/* Etat civil et filiation, repris tels quels. */
	$preDateNaiss  = $pontCandidat['date_naissance'] ?? '';
	$preLieuNaiss  = $pontCandidat['lieu_naissance'] ?? '';
	$preNationalite = $pontCandidat['nationalite'] ?? '';
	$prePays       = $pontCandidat['pays_origine'] ?? '';
	$preAdresse    = $pontCandidat['adresse'] ?? '';
	$prePere       = $pontCandidat['nom_pere'] ?? '';
	$preMere       = $pontCandidat['nom_mere'] ?? '';
	$preTelParent  = $pontCandidat['contact_parent'] ?? '';
	$preTuteur     = $pontCandidat['tuteur'] ?? '';
	$preTelTuteur  = $pontCandidat['contact_tuteur'] ?? '';
	$preParcours   = $pontCandidat['parcours'] ?? '';

	/* Le champ `sex` attend 1 (masculin) ou 0 (feminin), la candidature
	   stocke M ou F. */
	if (($pontCandidat['genre'] ?? '') === 'M')      { $preSexe = '1'; }
	elseif (($pontCandidat['genre'] ?? '') === 'F')  { $preSexe = '0'; }

	/* La liste « situation familiale » ne propose que deux entrees, dont la
	   valeur est le libelle lui-meme. Les autres etats civils du formulaire
	   public (divorce, veuf) n'y ont pas d'equivalent : on laisse l'agent
	   choisir plutot que d'inventer une correspondance. */
	$correspondanceEtatCivil = ['celibataire' => 'Célibataire', 'marie' => 'Marié'];
	$preSituation = $correspondanceEtatCivil[$pontCandidat['etat_civil'] ?? ''] ?? '';

	/* La mention est stockee sous la cle du formulaire public ; le champ
	   etude_envisage attend un sigle de la table filiere.

	   La correspondance etait recopiee ici — un quatrieme exemplaire de la
	   meme table, qui ignorait toute mention creee depuis. Elle vient
	   maintenant de data/parcours.php, comme le formulaire public lui-meme. */
	require_once(__DIR__ . '/../data/parcours.php');
	$sigle = parcoursSigleDeCle((string) ($pontCandidat['mention'] ?? ''));
	/* On ne pre-selectionne que si le sigle existe reellement : sinon l'agent
	   choisit lui-meme, plutot que d'obtenir une valeur silencieusement fausse. */
	if ($sigle !== '') {
		$v = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_sigle = ? LIMIT 1');
		$v->execute([$sigle]);
		$preMentionSigle = $v->fetchColumn() ?: '';
	}
}
?>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

<?php if ($pontBloque): ?>
			<?php /* Candidature non convertible : on s'arrete ici. Le formulaire
			         n'est pas seulement masque, il n'est pas emis du tout — et
			         les scripts qui le pilotent non plus, puisqu'on sort avant
			         eux. Un formulaire cache mais present resterait soumettable,
			         ce qui est exactement ce qu'on veut empecher. */ ?>
			<div class="back flex-1 overflow-y-auto">
				<div style="max-width:640px; margin:48px auto; padding:0 20px;">
					<div style="padding:26px 28px; border-radius:12px;
					            background:rgb(var(--c-canvas) / .72);
					            border:1px solid rgb(var(--c-line-strong) / .22);
					            border-left:4px solid #fbbf24;">
						<h1 style="margin:0 0 10px; font-size:19px; font-weight:700; color:#fbbf24;">
							<i class="bi-exclamation-triangle-fill" aria-hidden="true"></i>
							<?= htmlspecialchars($pontBloque['titre'], ENT_QUOTES, 'UTF-8') ?>
						</h1>
						<p style="margin:0; font-size:14px; line-height:1.65; color:rgb(var(--c-text-2));">
							<?= htmlspecialchars($pontBloque['texte'], ENT_QUOTES, 'UTF-8') ?>
						</p>

						<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:22px;">
<?php if (!empty($pontBloque['lien'])): ?>
							<a href="<?= htmlspecialchars($pontBloque['lien'], ENT_QUOTES, 'UTF-8') ?>"
							   style="display:inline-flex; align-items:center; gap:7px; min-height:40px;
							          padding:9px 18px; border-radius:9px; background:rgb(var(--c-accent-strong)); color:#fff;
							          font-size:13.5px; font-weight:600; text-decoration:none;">
								<i class="bi-box-arrow-up-right" aria-hidden="true"></i>
								<?= htmlspecialchars($pontBloque['lienTexte'] ?? 'Ouvrir', ENT_QUOTES, 'UTF-8') ?>
							</a>
<?php endif; ?>
							<?php if (hasAnyPrivilege(['superadmin', 'registrar'])): ?>
							<a href="<?= htmlspecialchars(($app_base ?? '') . '/candidatures', ENT_QUOTES, 'UTF-8') ?>"
							   style="display:inline-flex; align-items:center; gap:7px; min-height:40px;
							          padding:9px 18px; border-radius:9px; color:rgb(var(--c-text-2));
							          border:1px solid rgb(var(--c-line-strong) / .28);
							          font-size:13.5px; font-weight:600; text-decoration:none;">
								<i class="bi-arrow-left" aria-hidden="true"></i> Liste des candidatures
							</a>
							<?php endif; ?>
							<a href="<?= htmlspecialchars(($app_base ?? '') . '/students/create', ENT_QUOTES, 'UTF-8') ?>"
							   style="display:inline-flex; align-items:center; gap:7px; min-height:40px;
							          padding:9px 18px; border-radius:9px; color:rgb(var(--c-text-3));
							          font-size:13.5px; text-decoration:none;">
								Créer un étudiant sans candidature
							</a>
						</div>
					</div>
				</div>
				<?php require('../init/footer.php'); ?>
			</div>
			</div>
		</div>
	</div>
</body>
</html>
<?php
	exit;
endif;
?>
<form id="form-inscription" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
<?php /* Un jeton par formulaire affiche : si le meme formulaire arrive deux fois
         (clics repetes, reseau lent), app/.student/addStd.php ne cree qu'un
         etudiant et renvoie le second envoi vers sa fiche. */ ?>
	<input type="hidden" name="jeton_creation" value="<?= bin2hex(random_bytes(16)) ?>">
<?php if ($pontCandidat): ?>
	<!-- Origine de la creation : permet a addStd.php de boucler la conversion. -->
	<input type="hidden" name="candidat_id" value="<?=(int)$pontCandidat['id']?>">
	<!-- Le rappel de l'origine s'affiche en toast au chargement (voir plus bas) :
	     il n'occupe plus de place au-dessus d'un formulaire deja dense. -->
<?php endif; ?>
			
				<!-- Header bar with title and save button -->
				<div class="inscription-header mx-1 px-4 py-2 flex items-center justify-between flex-shrink-0">
					<div class="flex items-center gap-3">
						<div class="icon-wrapper">
							<i class="bi bi-person-plus-fill"></i>
						</div>
						<div>
							<h2 class="text-base font-semibold text-slate-100">Nouvelle inscription</h2>
							<p class="text-xs text-slate-400">Remplissez les informations de l'étudiant</p>
						</div>
					</div>
					<div class="flex items-center gap-3">
						<p><em class="text-red-400 text-sm" id="alert"></em></p>
					</div>
				</div>

				<!-- ===== MULTI-STEP PROGRESS BAR ===== -->
				<div class="msf-progress-wrapper mx-1 flex-shrink-0">
					<ul id="msf-progressbar">
						<li class="active" id="step-personal">
							<div class="step-icon"><i class="bi bi-person-badge"></i></div>
							<strong>Identité</strong>
						</li>
						<li id="step-contact">
							<div class="step-icon"><i class="bi bi-telephone"></i></div>
							<strong>Contact</strong>
						</li>
						<li id="step-study">
							<div class="step-icon"><i class="bi bi-mortarboard"></i></div>
							<strong>Études</strong>
						</li>
						<li id="step-family">
							<div class="step-icon"><i class="bi bi-people"></i></div>
							<strong>Famille</strong>
						</li>
						<li id="step-sponsor">
							<div class="step-icon"><i class="bi bi-person-heart"></i></div>
							<strong>Sponsor</strong>
						</li>
						<li id="step-finish">
							<div class="step-icon"><i class="bi bi-check2-circle"></i></div>
							<strong>Confirmer</strong>
						</li>
					</ul>
					<div class="msf-progress-track">
						<div class="msf-progress-fill" id="msf-progress-fill"></div>
					</div>
				</div>

				<div class="w-full px-1 flex-1 overflow-y-auto overflow-x-hidden py-2" style="scrollbar-width: thin; scrollbar-color: rgba(78, 158, 222, 0.3) transparent;">
						
						<div class="<?=$bg_one_color?> my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>

						<div class="w-full flex justify-center px-1 pb-4">
						<div class="w-full max-w-2xl">

							<!-- ===== STEP 1: Identité ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-person-badge"></i>
										<span>Infos personnelle</span>
									</div>
									<div class="step-counter">Étape 1 / 6</div>
								</div>
								<div class="card-body">
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom <span class="text-red-400">*</span></label>
											<input class="field-input requierd-1" type="text" name="student_nom" id="student_nom" placeholder="Entrez le nom" value="<?=htmlspecialchars($preNom, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Prénom</label>
											<input class="field-input" type="text" name="student_prenom" id="student_prenom" placeholder="Entrez le prénom" value="<?=htmlspecialchars($prePrenom, ENT_QUOTES, 'UTF-8')?>">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Date de naissance <span class="text-red-400">*</span></label>
											<input class="field-input requierd-2" type="date" name="dateNaissance" value="<?=htmlspecialchars($preDateNaiss, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Lieu de naissance <span class="text-red-400">*</span></label>
											<input class="field-input requierd-3" type="text" name="lieuNaissance" placeholder="Lieu de naissance" value="<?=htmlspecialchars($preLieuNaiss, ENT_QUOTES, 'UTF-8')?>">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">CIN</label>
											<input class="field-input" type="text" name="num_cin" placeholder="Numéro CIN">
										</div>
										<div class="field-group">
											<label class="field-label">Date de délivrance</label>
											<input class="field-input" type="date" name="cin_date_delivre">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">CIN région</label>
											<select class="field-input" name="cin_region">
												<option value="">Sélectionner...</option>
								<?php 
								$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
								while ($showR = $findRegion->fetch()) {
								 ?>	
								 				<option value="<?=$showR['id']?>"><?=$showR['region']?></option>
								 <?php 
								}
								 ?>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Genre</label>
											<select class="field-input" name="sex">
												<option value="1"<?=$preSexe === '1' ? ' selected' : ''?>>Masculin</option>
												<option value="0"<?=$preSexe === '0' ? ' selected' : ''?>>Féminin</option>
											</select>
										</div>
									</div>
									<div class="field-group">
										<label class="field-label">Nationalité <span class="text-red-400">*</span></label>
										<input class="field-input requierd-4" type="text" name="nationalite" placeholder="Nationalité" value="<?=htmlspecialchars($preNationalite, ENT_QUOTES, 'UTF-8')?>">
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<div></div>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 2: Contact & Photo ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-telephone"></i>
										<span>Infos du contact, photos</span>
									</div>
									<div class="step-counter">Étape 2 / 6</div>
								</div>
								<div class="card-body">
									<div class="flex gap-4">
										<div class="flex-1">
											<div class="field-group">
												<label class="field-label">Téléphone</label>
												<input class="field-input" type="text" name="student_tel" placeholder="034 00 000 00" value="<?=htmlspecialchars($preTel, ENT_QUOTES, 'UTF-8')?>">
											</div>
											<div class="field-group">
												<label class="field-label">Adresse mail</label>
												<input class="field-input" type="email" name="student_email" id="student_email" placeholder="email@example.com">
												<span class="field-note" id="student_email_note" aria-live="polite" hidden></span>
											</div>
											<div class="field-group">
												<label class="field-label">Adresse d'origine <span class="text-red-400">*</span></label>
												<input class="field-input requierd-5" type="text" name="pays_origine" placeholder="Madagascar" value="<?=htmlspecialchars($prePays, ENT_QUOTES, 'UTF-8')?>">
											</div>
											<div class="field-group">
												<label class="field-label">Région <span class="text-red-400">*</span></label>
												<select class="field-input requierd-6" name="student_region">
													<option value="">Sélectionner...</option>
						<?php 
						$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
						while ($showR = $findRegion->fetch()) {
						 ?>	
						 					<option value="<?=$showR['id']?>"><?=$showR['region']?></option>
						 <?php 
						}
						 ?>
												</select>
											</div>
											<div class="field-group">
												<label class="field-label">Adresse actuel <span class="text-red-400">*</span></label>
												<input class="field-input requierd-7" type="text" name="student_adresse" placeholder="Adresse complète" value="<?=htmlspecialchars($preAdresse, ENT_QUOTES, 'UTF-8')?>">
											</div>
										</div>
										<div class="w-28">
											<label class="field-label">Photo</label>
											<label for="student_images" class="photo-upload-box">
												<i class="bi bi-camera text-3xl text-slate-500"></i>
												<span class="text-xs text-slate-500 mt-1" id="photoNote">Cliquer pour ajouter</span>
											</label>
											<input type="file" accept="image/*" name="image_student" id="student_images" class="hidden">
											<canvas id="convertCanvasCreate" style="display:none;"></canvas>
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 3: Études ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-mortarboard"></i>
										<span>Infos d'étude</span>
									</div>
									<div class="step-counter">Étape 3 / 6</div>
								</div>
								<div class="card-body">
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Matricule</label>
											<div class="matricule-display" id="student_id">
												<i class="bi bi-lock-fill text-cyan-400"></i>
												<span>00000</span>
											</div>
											<input type="text" name="student_id" id="student_id_form" class="hidden">
										</div>
										<div class="field-group">
											<label class="field-label">Entrée du</label>
											<select class="field-input" name="semestre">
												<option value="1">Premier semestre</option>
												<option value="2">Deuxième semestre</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Mention <span class="text-red-400">*</span></label>
											<select class="field-input requierd-8" name="etude_envisage" id="etude_envisage">
												<option value="">Sélectionner...</option>
<?php 
/* Mentions FERMEES exclues : c'est une liste de choix pour un etudiant qui
   n'existe pas encore. Une mention fermee ne recrute plus, la proposer ici
   serait un piege. Les donnees existantes ne sont pas concernees — voir
   data/mentions.php.

   Exception : la mention issue d'une candidature (preMentionSigle) reste
   proposee meme fermee, sinon le formulaire perdrait silencieusement le choix
   du candidat. Elle est alors suffixee « fermee ». */
require_once(__DIR__ . '/../data/mentions.php');
mentionsOuvertureDisponible($dtb);
$sqlMentions = 'SELECT * FROM filiere WHERE (' . mentionsFiltreOuvertes() . ')'
    . ($preMentionSigle !== '' ? ' OR filiere_sigle = :pre' : '')
    . ' ORDER BY filiere_description';
$findSignMention = $dtb->prepare($sqlMentions);
$findSignMention->execute($preMentionSigle !== '' ? ['pre' => $preMentionSigle] : []);
while ($showSignMention = $findSignMention->fetch()) {
	$mentionFermee = isset($showSignMention['ouverte']) && !$showSignMention['ouverte'];
 ?>	
												<option value="<?=$showSignMention['filiere_sigle']?>"<?=($preMentionSigle !== '' && $preMentionSigle === $showSignMention['filiere_sigle']) ? ' selected' : ''?>><?=$showSignMention['filiere_description']?><?= $mentionFermee ? ' — fermée' : '' ?></option>
 <?php 
}
 ?>												
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Parcours <span class="text-red-400">*</span></label>
											<div id="etude_option">
												<select id="firstEtd" class="field-input requierd-9" name="etude_option">
													<option value="">Sélectionner...</option>
												</select>
											</div>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Niveau</label>
											<select class="field-input" name="annee_etude" id="annee_etude">
												<option value="0">Remise à niveau</option>
												<option value="1" selected>Licence 1</option>
												<option value="2">Licence 2</option>
												<option value="3">Licence 3</option>
												<option value="4">Master 1</option>
												<option value="5">Master 2</option>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Année universitaire <span class="text-red-400">*</span></label>
											<select class="field-input requierd-10" name="annee_scolaire">
						<?php
						$y = date('Y');
						for ($i=0; $i <= 7; $i++) { 
							$as = $y." - ".($y+1);
							?>
												<option><?=$as?></option>
						<?php
						$y = $y - 1;
						}
						 ?>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Status</label>
											<select class="field-input" name="status">
												<option>Externe</option>
												<option>Interne</option>
												<option>Bungalow</option>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Ancien étudiant</label>
											<select class="field-input" name="new_student">
												<option value="1">Non</option>
												<option value="0">Oui</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Taille (T-shirt)</label>
											<select class="field-input" name="taille">
												<option value="">Sélectionner...</option>
												<option value="S">S</option>
												<option value="M">M</option>
												<option value="L">L</option>
												<option value="XL">XL</option>
												<option value="XXL">XXL</option>
												<option value="XXXL">XXXL</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group obtention_Bacc">
											<label class="field-label">Année d'obtention Bacc</label>
											<input class="field-input" type="date" name="obtention_bacc">
										</div>
										<div class="field-group obtention_Bacc">
											<label class="field-label">Série du Bacc</label>
											<select class="field-input" name="serie_bacc">
												<option>A1</option>
												<option>A2</option>
												<option>BTP</option>
												<option>C</option>
												<option>D</option>
												<option>Electronique</option>
												<option>G1</option>
												<option>G2</option>
												<option>G3</option>
												<option>L</option>
												<option>S</option>
												<option>OSE</option>
												<option>Technique Ouvrage Bois</option>
												<option>Technique Ouvrage Métalique</option>
												<option>Technique Maintenance Automobile</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3 diplome_preced hidden">
										<div class="field-group">
											<label class="field-label">Diplôme précédent</label>
											<input class="field-input" type="text" name="diplome_preced">
										</div>
										<div class="field-group">
											<label class="field-label">Date d'obtention</label>
											<input class="field-input" type="date" name="date_obtent_diplome_preced">
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 4: Parents ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-people"></i>
										<span>Infos parentale</span>
									</div>
									<div class="step-counter">Étape 4 / 6</div>
								</div>
								<div class="card-body">
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom du père</label>
											<input class="field-input" type="text" name="father_name" placeholder="Nom complet" value="<?=htmlspecialchars($prePere, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Sa profession</label>
											<input class="field-input" type="text" name="father_prof" placeholder="Profession">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom de la mère</label>
											<input class="field-input" type="text" name="mother_name" placeholder="Nom complet" value="<?=htmlspecialchars($preMere, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Sa profession</label>
											<input class="field-input" type="text" name="mother_prof" placeholder="Profession">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Téléphone</label>
											<input class="field-input" type="text" name="parent_tel" placeholder="034 00 000 00" value="<?=htmlspecialchars($preTelParent, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Adresse</label>
											<input class="field-input" type="text" name="parent_adresse" placeholder="Adresse des parents">
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 5: Sponsor & Situation familiale ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-person-heart"></i>
										<span>Sponsor & Situation familiale</span>
									</div>
									<div class="step-counter">Étape 5 / 6</div>
								</div>
								<div class="card-body">
									<div class="msf-section-label"><i class="bi bi-shield-check"></i> Garant financier</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom du sponsor</label>
											<input class="field-input" type="text" name="sponsor_nom" placeholder="Nom" value="<?=htmlspecialchars($preTuteur, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Prénom</label>
											<input class="field-input" type="text" name="sponsor_prenom" placeholder="Prénom">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Téléphone</label>
											<input class="field-input" type="text" name="sponsor_tel" placeholder="034 00 000 00" value="<?=htmlspecialchars($preTelTuteur, ENT_QUOTES, 'UTF-8')?>">
										</div>
										<div class="field-group">
											<label class="field-label">Adresse</label>
											<input class="field-input" type="text" name="sponsor_adresse" placeholder="Adresse du sponsor">
										</div>
									</div>

									<div class="msf-divider"></div>

									<div class="msf-section-label"><i class="bi bi-house-heart"></i> Situation familiale & autres</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">État civil</label>
											<select class="field-input" name="situationf">
												<option<?=$preSituation === 'Célibataire' ? ' selected' : ''?>>Célibataire</option>
												<option<?=$preSituation === 'Marié' ? ' selected' : ''?>>Marié</option>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Nom du/de conjoint(e)</label>
											<input class="field-input" type="text" name="nom_conjoint" placeholder="Nom du conjoint">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nombre d'enfant</label>
											<input class="field-input" type="number" name="nb_enfant" value="0" min="0">
										</div>
										<div class="field-group">
											<label class="field-label">Réligion</label>
											<select class="field-input" name="religion">
												<option>Adventiste</option>
												<option>FLM</option>
												<option>Apokalypsy</option>
												<option>Catholique</option>
												<option>FJKM</option>
												<option>Lutherien</option>
												<option>Musulman</option>
												<option>Shyn</option>
												<option>Jesosy Mamonjy</option>
												<option>Autre...</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Numéro visa</label>
											<input class="field-input" type="text" name="num_visa" placeholder="Numéro visa">
										</div>
										<div class="field-group">
											<label class="field-label">Abonné au CAF</label>
											<select class="field-input" name="abonment">
												<option value="0">Non</option>
												<option value="1">Oui</option>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 6: Confirmation ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-check2-circle"></i>
										<span>Confirmation</span>
									</div>
									<div class="step-counter">Étape 6 / 6</div>
								</div>
								<div class="card-body">
									<div class="msf-summary">
										<div class="msf-summary-icon">
											<i class="bi bi-clipboard-check"></i>
										</div>
										<h3>Vérifiez les informations</h3>
										<p>Relisez les données saisies avant de valider l'inscription.</p>

										<div class="msf-recap" id="msf-recap">
											<!-- Filled by JS -->
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button class="msf-btn msf-btn-submit" id="btn-inscription" type="button">
									<i class="bi bi-check2-circle"></i> Enregistrer l'étudiant
								</button>
							</div>
							</fieldset>

						</div>
						</div>

				</div>
</form>					
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>
<style type="text/css">
	/* ===== SHADCN-INSPIRED FORM STYLES ===== */
	.inscription-header {
		background: rgb(var(--c-canvas) / 0.8);
		backdrop-filter: blur(8px);
		border-bottom: 1px solid rgb(var(--c-line) / 0.5);
	}
	
	.inscription-header .icon-wrapper {
		width: 40px;
		height: 40px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: linear-gradient(135deg, rgb(var(--c-accent) / 0.2), rgb(var(--c-accent) / 0.1));
		border-radius: 10px;
		border: 1px solid rgb(var(--c-accent) / 0.3);
	}
	
	.inscription-header .icon-wrapper i {
		font-size: 18px;
		color: rgb(var(--c-accent-text));
	}
	
	.form-card {
		background: rgb(var(--c-canvas) / 0.6);
		backdrop-filter: blur(12px);
		border: 1px solid rgb(var(--c-line) / 0.5);
		border-radius: 12px;
		overflow: hidden;
		transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	.form-card:hover {
		border-color: rgb(var(--c-accent) / 0.4);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(56, 189, 248, 0.1);
	}
	
	.form-card .card-header {
		padding: 14px 16px;
		border-bottom: 1px solid rgb(var(--c-line) / 0.5);
		background: rgb(var(--c-canvas) / 0.4);
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	
	.form-card .card-title {
		display: flex;
		align-items: center;
		gap: 10px;
		font-size: 14px;
		font-weight: 600;
		color: rgb(var(--c-text));
	}
	
	.form-card .card-title i {
		color: rgb(var(--c-accent-text));
		font-size: 16px;
	}
	
	.form-card .card-body {
		padding: 16px;
	}

	.step-counter {
		font-size: 12px;
		color: rgb(var(--c-text-3));
		font-weight: 500;
	}
	
	.field-group {
		margin-bottom: 12px;
	}
	
	.field-label {
		display: block;
		font-size: 11px;
		font-weight: 500;
		color: rgb(var(--c-text-3));
		text-transform: uppercase;
		letter-spacing: 0.03em;
		margin-bottom: 6px;
	}
	
	.field-input {
		width: 100%;
		padding: 8px 12px;
		background: rgb(var(--c-canvas) / 0.8);
		border: 1px solid rgb(var(--c-line) / 0.6);
		border-radius: 8px;
		color: rgb(var(--c-text));
		font-size: 13px;
		transition: all 0.15s ease;
	}
	
	.field-input:focus {
		outline: none;
		border-color: rgb(var(--c-accent));
		box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
	}
	
	.field-input::placeholder {
		color: rgb(var(--c-text-3));
	}

	/* Adresse universitaire : d'ou elle vient, et si l'habituelle etait prise. */
	.field-note {
		display: block;
		margin-top: 5px;
		font-size: 11.5px;
		line-height: 1.45;
		color: rgb(var(--c-text-3));
	}
	.field-note--prise { color: rgb(var(--c-warning)); }
	.field-note[hidden] { display: none; }
	
	select.field-input {
		cursor: pointer;
	}
	
	select.field-input option {
		background: rgb(var(--c-canvas));
		color: rgb(var(--c-text));
	}
	
	.matricule-display {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		background: rgb(var(--c-canvas) / 0.8);
		border: 1px solid rgb(var(--c-line) / 0.6);
		border-radius: 8px;
		color: rgb(var(--c-text));
		font-size: 14px;
		font-weight: 500;
	}
	
	.photo-upload-box {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 100px;
		background: rgb(var(--c-canvas) / 0.8);
		border: 2px dashed rgb(var(--c-line) / 0.6);
		border-radius: 10px;
		cursor: pointer;
		transition: all 0.2s ease;
		margin-top: 4px;
	}
	
	.photo-upload-box:hover {
		border-color: rgb(var(--c-accent));
		background: rgb(var(--c-accent) / 0.05);
	}
	
	/* Error states */
	.field-input.error {
		border-color: #ef4444;
		background: rgba(239, 68, 68, 0.1);
	}

	/* ===== MULTI-STEP PROGRESS BAR ===== */
	.msf-progress-wrapper {
		padding: 20px 24px 12px;
		background: rgb(var(--c-canvas) / 0.5);
		border-bottom: 1px solid rgb(var(--c-line) / 0.3);
	}

	#msf-progressbar {
		display: flex;
		justify-content: space-between;
		margin: 0 0 16px 0;
		padding: 0;
		position: relative;
		z-index: 1;
		list-style: none;
	}

	#msf-progressbar li {
		display: flex;
		flex-direction: column;
		align-items: center;
		flex: 1;
		position: relative;
		font-size: 11px;
		font-weight: 500;
		color: rgb(var(--c-text-2));
		transition: color 0.4s ease;
	}

	#msf-progressbar li strong {
		margin-top: 8px;
		white-space: nowrap;
	}

	#msf-progressbar li .step-icon {
		width: 42px;
		height: 42px;
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 18px;
		background: rgb(var(--c-canvas) / 0.8);
		border: 2px solid rgb(var(--c-line) / 0.5);
		color: rgb(var(--c-text-2));
		transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
		z-index: 2;
	}

	/* Trait de liaison entre deux etapes.
	   Il ne couvre QUE l'espace libre entre deux icones. Auparavant il partait
	   du centre de l'icone (left:50%, width:100%) et passait donc dessous :
	   le fond de l'icone n'etant opaque qu'a 80%, le trait vert transparaissait
	   au travers. Le decalage de 25px correspond au rayon de l'icone (21px)
	   plus 4px de degagement. */
	#msf-progressbar li::after,
	#msf-progressbar li::before {
		content: '';
		position: absolute;
		top: 21px;
		left: calc(50% + 25px);
		width: calc(100% - 50px);
		height: 2px;
		border-radius: 2px;
	}

	/* Rail gris, toujours visible. */
	#msf-progressbar li::after {
		background: rgb(var(--c-raised) / 0.4);
		z-index: 0;
	}

	/* Remplissage vert, revele par mise a l'echelle horizontale plutot que par
	   un changement de couleur instantane : le trait se trace d'une etape a la
	   suivante, ce qui rend visible le sens de la progression. */
	#msf-progressbar li::before {
		background: linear-gradient(90deg, #10b981, #34d399);
		z-index: 1;
		transform: scaleX(0);
		transform-origin: left center;
		transition: transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
	}

	#msf-progressbar li.done::before {
		transform: scaleX(1);
	}

	#msf-progressbar li:last-child::after,
	#msf-progressbar li:last-child::before {
		display: none;
	}

	/* Le trace est un repere de progression, pas une decoration : sous
	   reduction de mouvement on garde l'etat final, sans l'animer. */
	@media (prefers-reduced-motion: reduce) {
		#msf-progressbar li::before {
			transition: none;
		}
	}

	#msf-progressbar li.active {
		color: rgb(var(--c-accent-text));
	}

	#msf-progressbar li.active .step-icon {
		background: linear-gradient(135deg, rgb(var(--c-accent) / 0.2), rgb(var(--c-accent) / 0.1));
		border-color: rgb(var(--c-accent));
		color: rgb(var(--c-accent-text));
		box-shadow: 0 0 20px rgba(14, 165, 233, 0.25);
	}

	#msf-progressbar li.done {
		color: #34d399;
	}

	#msf-progressbar li.done .step-icon {
		background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(52, 211, 153, 0.1));
		border-color: #10b981;
		color: #34d399;
	}


	.msf-progress-track {
		width: 100%;
		height: 4px;
		background: rgb(var(--c-raised) / 0.4);
		border-radius: 4px;
		overflow: hidden;
	}

	.msf-progress-fill {
		height: 100%;
		width: 16.66%;
		background: linear-gradient(90deg, rgb(var(--c-accent)), rgb(var(--c-accent)));
		border-radius: 4px;
		transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 0 0 8px rgba(14, 165, 233, 0.4);
	}

	/* ===== FIELDSET STEPS ===== */
	.msf-fieldset {
		border: none;
		padding: 0;
		margin: 0;
		min-width: 0;
	}

	.msf-fieldset:not(:first-of-type) {
		display: none;
	}

	/* ===== NAVIGATION BUTTONS ===== */
	.msf-nav {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-top: 16px;
		padding: 0 4px;
	}

	.msf-btn {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 10px 24px;
		font-size: 13px;
		font-weight: 600;
		border-radius: 10px;
		border: none;
		cursor: pointer;
		transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
	}

	.msf-btn-next {
		background: linear-gradient(135deg, rgb(var(--c-accent)), rgb(var(--c-accent-strong)));
		color: white;
		box-shadow: 0 2px 10px rgba(14, 165, 233, 0.3);
	}

	.msf-btn-next:hover {
		background: linear-gradient(135deg, rgb(var(--c-accent)), rgb(var(--c-accent)));
		transform: translateY(-1px);
		box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4);
	}

	.msf-btn-prev {
		background: rgb(var(--c-raised) / 0.5);
		color: rgb(var(--c-text-3));
		border: 1px solid rgb(var(--c-line) / 0.6);
	}

	.msf-btn-prev:hover {
		background: rgb(var(--c-raised) / 0.8);
		color: rgb(var(--c-text));
		transform: translateY(-1px);
	}

	.msf-btn-submit {
		background: linear-gradient(135deg, #10b981, #059669);
		color: white;
		box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);
		padding: 12px 32px;
		font-size: 14px;
	}

	.msf-btn-submit:hover {
		background: linear-gradient(135deg, #34d399, #10b981);
		transform: translateY(-1px);
		box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
	}

	/* Pendant l'enregistrement : le bouton dit ce qui se passe et ne
	   reagit plus aux clics. */
	.msf-btn-submit:disabled,
	.msf-btn-submit:disabled:hover {
		background: linear-gradient(135deg, #10b981, #059669);
		opacity: .8;
		transform: none;
		cursor: progress;
	}

	/* ===== SECTION LABELS & DIVIDERS ===== */
	.msf-section-label {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 13px;
		font-weight: 600;
		color: rgb(var(--c-accent-text));
		margin-bottom: 12px;
		padding-bottom: 8px;
		border-bottom: 1px solid rgb(var(--c-accent) / 0.15);
	}

	.msf-section-label i {
		font-size: 15px;
	}

	.msf-divider {
		height: 1px;
		background: linear-gradient(90deg, transparent, rgb(var(--c-raised) / 0.5), transparent);
		margin: 20px 0;
	}

	/* ===== SUMMARY / CONFIRMATION STEP ===== */
	.msf-summary {
		text-align: center;
		padding: 16px 0;
	}

	.msf-summary-icon {
		width: 64px;
		height: 64px;
		border-radius: 16px;
		background: linear-gradient(135deg, rgb(var(--c-accent) / 0.15), rgb(var(--c-accent) / 0.08));
		border: 2px solid rgb(var(--c-accent) / 0.3);
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 auto 16px;
		font-size: 28px;
		color: rgb(var(--c-accent-text));
	}

	.msf-summary h3 {
		font-size: 18px;
		font-weight: 700;
		color: rgb(var(--c-text));
		margin: 0 0 6px;
	}

	.msf-summary p {
		font-size: 13px;
		color: rgb(var(--c-text-3));
		margin: 0 0 24px;
	}

	.msf-recap {
		text-align: left;
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 8px;
	}

	.msf-recap-item {
		padding: 8px 12px;
		background: rgb(var(--c-canvas) / 0.6);
		border: 1px solid rgb(var(--c-line) / 0.4);
		border-radius: 8px;
	}

	.msf-recap-item .recap-label {
		font-size: 10px;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: rgb(var(--c-text-3));
		margin-bottom: 2px;
	}

	.msf-recap-item .recap-value {
		font-size: 13px;
		color: rgb(var(--c-text));
		font-weight: 500;
		word-break: break-word;
	}

	.msf-recap-item .recap-value.highlight {
		color: rgb(var(--c-accent-text));
	}

	/* ===== ANIMATION ===== */
	.msf-fieldset {
		animation: msfSlideIn 0.4s ease;
	}

	@keyframes msfSlideIn {
		from { opacity: 0; transform: translateX(30px); }
		to { opacity: 1; transform: translateX(0); }
	}

	@keyframes msfSlideOut {
		from { opacity: 1; transform: translateX(0); }
		to { opacity: 0; transform: translateX(-30px); }
	}

	/* ===== LIGHT MODE ===== */
	[data-theme="light"] .inscription-header {
		background: rgb(var(--c-surface) / 0.9);
		border-bottom-color: rgb(var(--c-line-strong) / 0.8);
	}
	
	[data-theme="light"] .inscription-header h2 {
		color: rgb(var(--c-text)) !important;
	}
	
	[data-theme="light"] .inscription-header p {
		color: rgb(var(--c-text-3)) !important;
	}
	
	[data-theme="light"] .form-card {
		background: rgb(var(--c-surface) / 0.9);
		border-color: rgb(var(--c-line-strong) / 0.8);
	}
	
	[data-theme="light"] .form-card:hover {
		border-color: rgb(var(--c-accent) / 0.5);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
	}
	
	[data-theme="light"] .form-card .card-header {
		background: rgb(var(--c-surface-2) / 0.8);
		border-bottom-color: rgb(var(--c-line-strong) / 0.6);
	}
	
	[data-theme="light"] .form-card .card-title {
		color: rgb(var(--c-text));
	}
	
	[data-theme="light"] .field-label {
		color: rgb(var(--c-text-3));
	}
	
	[data-theme="light"] .field-input {
		background: rgb(var(--c-surface));
		border-color: rgb(var(--c-line));
		color: rgb(var(--c-text));
	}
	
	[data-theme="light"] .field-input::placeholder {
		color: rgb(var(--c-text-3));
	}
	
	[data-theme="light"] .matricule-display {
		background: rgb(var(--c-surface-2));
		border-color: rgb(var(--c-line));
		color: rgb(var(--c-text));
	}
	
	[data-theme="light"] .photo-upload-box {
		background: rgb(var(--c-surface-2));
		border-color: rgb(var(--c-line-strong));
	}
	
	[data-theme="light"] .photo-upload-box:hover {
		border-color: rgb(var(--c-accent));
		background: rgb(var(--c-accent) / 0.05);
	}

	[data-theme="light"] .msf-progress-wrapper {
		background: rgb(var(--c-surface-2) / 0.8);
		border-bottom-color: rgb(var(--c-line-strong) / 0.6);
	}

	[data-theme="light"] #msf-progressbar li .step-icon {
		background: rgb(var(--c-surface-2));
		border-color: rgb(var(--c-line));
	}

	[data-theme="light"] .msf-btn-prev {
		background: rgb(var(--c-surface-2));
		border-color: rgb(var(--c-line));
		color: rgb(var(--c-text-2));
	}

	[data-theme="light"] .msf-btn-prev:hover {
		background: rgb(var(--c-raised));
		color: rgb(var(--c-text));
	}

	[data-theme="light"] .msf-recap-item {
		background: rgb(var(--c-surface-2));
		border-color: rgb(var(--c-line));
	}

	[data-theme="light"] .msf-recap-item .recap-value {
		color: rgb(var(--c-text));
	}

	[data-theme="light"] .step-counter {
		color: rgb(var(--c-text-3));
	}

	/* Scrollbar styling */
	.overflow-y-auto::-webkit-scrollbar {
		width: 6px;
	}
	
	.overflow-y-auto::-webkit-scrollbar-track {
		background: transparent;
	}
	
	.overflow-y-auto::-webkit-scrollbar-thumb {
		background: rgb(var(--c-accent) / 0.3);
		border-radius: 3px;
	}
	
	.overflow-y-auto::-webkit-scrollbar-thumb:hover {
		background: rgb(var(--c-accent) / 0.5);
	}

	/* ===== RESPONSIVE ===== */
	@media (max-width: 640px) {
		#msf-progressbar li strong { display: none; }
		#msf-progressbar li .step-icon { width: 36px; height: 36px; font-size: 15px; border-radius: 10px; }
		/* Icone reduite a 36px : le degagement suit (18px de rayon + 4px).
		   Le rail et son remplissage doivent bouger ensemble, sinon les deux
		   traits se decalent l'un par rapport a l'autre. */
		#msf-progressbar li::after,
		#msf-progressbar li::before {
			top: 18px;
			left: calc(50% + 22px);
			width: calc(100% - 44px);
		}
		.msf-progress-wrapper { padding: 14px 12px 8px; }
		.msf-recap { grid-template-columns: 1fr; }
		.msf-btn { padding: 9px 16px; font-size: 12px; }
		.msf-btn-submit { padding: 10px 20px; font-size: 13px; }
	}
</style>
<script type="text/javascript">
	$(document).ready(function(){

		// ============================================================
		// Multi-Step Form Logic
		// ============================================================
		var currentStep = 1;
		var totalSteps = $(".msf-fieldset").length;
		var progressItems = $("#msf-progressbar li");

		updateProgressBar(currentStep);

		// NEXT button
		$(".msf-btn-next").click(function(){
			var currentFieldset = $(this).closest('.msf-fieldset');
			var nextFieldset = currentFieldset.next('.msf-fieldset');

			if (nextFieldset.length === 0) return;

			// Mark current step as done
			progressItems.eq(currentStep - 1).removeClass('active').addClass('done');
			// Mark next step as active
			progressItems.eq(currentStep).addClass('active');

			// Animate transition
			currentFieldset.css('animation', 'msfSlideOut 0.3s ease forwards');
			setTimeout(function(){
				currentFieldset.hide().css('animation', '');
				nextFieldset.show().css('animation', 'msfSlideIn 0.4s ease');
				
				// Scroll to top of form area
				currentFieldset.closest('.overflow-y-auto').scrollTop(0);

				// If last step, build recap
				if (currentStep + 1 === totalSteps) {
					buildRecap();
				}
			}, 280);

			currentStep++;
			updateProgressBar(currentStep);
		});

		// PREVIOUS button
		$(".msf-btn-prev").click(function(){
			var currentFieldset = $(this).closest('.msf-fieldset');
			var prevFieldset = currentFieldset.prev('.msf-fieldset');

			if (prevFieldset.length === 0) return;

			// Remove active/done from current, restore active on previous
			progressItems.eq(currentStep - 1).removeClass('active done');
			progressItems.eq(currentStep - 2).removeClass('done').addClass('active');

			currentFieldset.css('animation', 'msfSlideOut 0.3s ease forwards');
			setTimeout(function(){
				currentFieldset.hide().css('animation', '');
				prevFieldset.show().css('animation', 'msfSlideIn 0.4s ease');
				currentFieldset.closest('.overflow-y-auto').scrollTop(0);
			}, 280);

			currentStep--;
			updateProgressBar(currentStep);
		});

		function updateProgressBar(step) {
			var percent = (step / totalSteps) * 100;
			$("#msf-progress-fill").css("width", percent + "%");
		}

		function buildRecap() {
			var recapFields = [
				{ label: 'Nom', name: 'student_nom', hl: true },
				{ label: 'Prénom', name: 'student_prenom', hl: true },
				{ label: 'Date de naissance', name: 'dateNaissance' },
				{ label: 'Lieu de naissance', name: 'lieuNaissance' },
				{ label: 'Nationalité', name: 'nationalite' },
				{ label: 'Téléphone', name: 'student_tel' },
				{ label: 'Email', name: 'student_email' },
				{ label: 'Adresse', name: 'student_adresse' },
				{ label: 'Matricule', name: 'student_id', fromId: 'student_id_form', hl: true },
				{ label: 'Mention', name: 'etude_envisage', selectText: true, hl: true },
				{ label: 'Parcours', name: 'etude_option', selectText: true },
				{ label: 'Niveau', name: 'annee_etude', selectText: true },
				{ label: 'Année scolaire', name: 'annee_scolaire', selectText: true },
				{ label: 'Père', name: 'father_name' },
				{ label: 'Mère', name: 'mother_name' },
				{ label: 'Sponsor', name: 'sponsor_nom' },
			];

			var html = '';
			recapFields.forEach(function(f) {
				var val = '';
				if (f.fromId) {
					val = $('#' + f.fromId).val();
				} else if (f.selectText) {
					var sel = $('[name="' + f.name + '"]');
					val = sel.is('select') ? sel.find('option:selected').text() : sel.val();
				} else {
					val = $('[name="' + f.name + '"]').val();
				}
				val = val || '—';
				var cls = f.hl ? ' highlight' : '';
				html += '<div class="msf-recap-item"><div class="recap-label">' + f.label + '</div><div class="recap-value' + cls + '">' + $('<span>').text(val).html() + '</div></div>';
			});

			$('#msf-recap').html(html);
		}

		// ============================================================
		// Existing Logic — Mention / Parcours / Matricule
		// ============================================================
		$('#etude_envisage').on('change', function(){
			var mention = $(this).val();
			var annee_etude = $('#annee_etude').val();

			$.ajax({
				url:APP_BASE+"/src/services/parcours.live",
				method:"POST",
				data:{mention:mention},

				success:function(data){
					$("#etude_option").html(data);
				}
			});

			$.ajax({
				url:APP_BASE+"/src/services/matricule.live",
				method:"POST",
				data:{mention:mention, annee_etude:annee_etude},

				success:function(data){
					$("#student_id").html('<i class="bi-lock-fill"></i>'+data);
					$("#student_id_form").val(data);
				}
			});
		});
		$('#annee_etude').on('change', function(){
			var mention = $('#etude_envisage').val();
			var annee_etude = $(this).val();

			$.ajax({
				url:APP_BASE+"/src/services/matricule.live",
				method:"POST",
				data:{mention:mention, annee_etude:annee_etude},

				success:function(data){
					$("#student_id").html('<i class="bi-lock-fill"></i>'+data);
					$("#student_id_form").val(data);
				}
			});
			
			if(annee_etude <= 3) {
				$('.obtention_Bacc').css({'display':'block'});
				$('.diplome_preced').css({'display':'none'});
			}else if(annee_etude > 3) {
				$('.obtention_Bacc').css({'display':'none'});
				$('.diplome_preced').css({'display':'block'});
			}

		});

		// ============================================================
		// Image conversion
		// ============================================================
		$('#student_images').on('change', function(){
			var fileInput = this;
			var file = fileInput.files[0];
			if(!file) return;

			var reader = new FileReader();
			reader.onload = function(e) {
				var img = new Image();
				img.onload = function() {
					var canvas = document.getElementById('convertCanvasCreate');
					var maxSize = 1200;
					var w = img.width, h = img.height;
					if (w > maxSize || h > maxSize) {
						if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
						else { w = Math.round(w * maxSize / h); h = maxSize; }
					}
					canvas.width = w;
					canvas.height = h;
					var ctx = canvas.getContext('2d');
					ctx.drawImage(img, 0, 0, w, h);
					canvas.toBlob(function(blob) {
						var baseName = file.name.replace(/\.[^.]+$/, '');
						var convertedFile = new File([blob], baseName + '.jpg', { type: 'image/jpeg' });
						var dataTransfer = new DataTransfer();
						dataTransfer.items.add(convertedFile);
						fileInput.files = dataTransfer.files;
						$('#photoNote').text('Image prête (' + w + 'x' + h + ')');
					}, 'image/jpeg', 0.9);
				};
				img.onerror = function() {
					$('#photoNote').text('Format non supporté');
					fileInput.value = '';
				};
				img.src = e.target.result;
			};
			reader.readAsDataURL(file);
		});

		// ============================================================
		// Validation & Submit
		// ============================================================
		$('#btn-inscription').click(function() {
			
			var r_1 = $('.requierd-1').val();
			var r_2 = $('.requierd-2').val();
			var r_3 = $('.requierd-3').val();
			var r_4 = $('.requierd-4').val();
			var r_5 = $('.requierd-5').val();
			var r_6 = $('.requierd-6').val();
			var r_7 = $('.requierd-7').val();
			var r_8 = $('.requierd-8').val();
			var r_9 = $('.requierd-9').val();
			var r_10 = $('.requierd-10').val();
				
			if(r_1 =="" || r_2 =="" || r_3 =="" || r_4 =="" || r_5 =="" || r_6 =="" || r_7 =="" || r_8 =="" || r_9 =="" || r_10 =="") {			
				
				$('#alert').text('Ces zones sont obligatoires !');

				// Reset error styles
				$('.requierd-1,.requierd-2,.requierd-3,.requierd-4,.requierd-5,.requierd-6,.requierd-7,.requierd-8,.requierd-9,.requierd-10').each(function(){
					if($(this).val() === '') {
						$(this).css({'border':'1px solid #ef4444','background':'rgba(239,68,68,0.15)'});
					}
				});

				// Find which step has the first missing required field and go there
				var requiredFields = [
					{ el: '.requierd-1', step: 1 }, { el: '.requierd-2', step: 1 },
					{ el: '.requierd-3', step: 1 }, { el: '.requierd-4', step: 1 },
					{ el: '.requierd-5', step: 2 }, { el: '.requierd-6', step: 2 },
					{ el: '.requierd-7', step: 2 }, { el: '.requierd-8', step: 3 },
					{ el: '.requierd-9', step: 3 }, { el: '.requierd-10', step: 3 }
				];

				for (var i = 0; i < requiredFields.length; i++) {
					if ($(requiredFields[i].el).val() === '') {
						goToStep(requiredFields[i].step);
						break;
					}
				}
			
			}else{

				/* Un seul envoi, au premier clic. Avant, le bouton ne devenait
				   « envoyer » qu'au survol de la souris et rien ne l'arretait
				   ensuite : sur une connexion lente, la page restait affichee
				   quelques secondes sans aucun signe, l'agent recliquait, et
				   chaque clic creait un etudiant (huit fiches identiques le
				   06/10/2026). Le serveur refuse aussi un second envoi du meme
				   formulaire (jeton_creation, app/.student/addStd.php). */
				if (envoiEnCours) { return; }
				envoiEnCours = true;
				$(this).prop('disabled', true).attr('aria-busy', 'true')
					.html('<i class="bi bi-arrow-repeat animate-spin" style="display:inline-block" aria-hidden="true"></i> Enregistrement…');
				var formulaire = document.getElementById('form-inscription');
				formulaire.action = APP_BASE + '/app/.student/addStd?rg_id=<?=$rg_id?>';
				formulaire.method = 'post';
				formulaire.submit();

			}
		});

		/* Retour vers ce formulaire par « Precedent » du navigateur : la page
		   revient telle quelle, bouton compris ; il redevient utilisable. */
		var envoiEnCours = false;
		var texteBoutonEnvoi = $('#btn-inscription').html();
		window.addEventListener('pageshow', function (e) {
			if (!e.persisted) { return; }
			envoiEnCours = false;
			$('#btn-inscription').prop('disabled', false).removeAttr('aria-busy').html(texteBoutonEnvoi);
		});

		// ============================================================
		// Go to specific step (for validation redirect)
		// ============================================================
		function goToStep(targetStep) {
			if (targetStep === currentStep) return;

			// Reset all progress
			progressItems.removeClass('active done');
			for (var i = 0; i < targetStep - 1; i++) {
				progressItems.eq(i).addClass('done');
			}
			progressItems.eq(targetStep - 1).addClass('active');

			// Show target fieldset
			$('.msf-fieldset').hide();
			$('.msf-fieldset').eq(targetStep - 1).show().css('animation', 'msfSlideIn 0.4s ease');

			currentStep = targetStep;
			updateProgressBar(currentStep);
		}

		// ============================================================
		// Auto-generate email
		//
		// L'adresse etudiante se derive du nom : elle n'est jamais saisie, et
		// surtout jamais reprise de la candidature (celle du dossier est
		// l'adresse personnelle du candidat, pas son adresse institutionnelle).
		// ============================================================
		/* Elle doit etre UNIQUE : le serveur rend la premiere libre
		   (data/adresse.etudiant.php) — nom.pre, sinon nom.pren, nom.preno...,
		   sinon nom.prenom2 — sans accents ni espaces, et dit qui porte deja
		   l'adresse habituelle. Une adresse deja prise n'est jamais reprise,
		   meme par la meme personne passee de Licence en Master (regle du
		   08/10/2026). addStd.php la revalide a l'enregistrement. */
		var adresseMinuterie = null;
		var adresseNumero = 0;
		function genererEmailEtudiant() {
			clearTimeout(adresseMinuterie);
			adresseMinuterie = setTimeout(function () {
				var nom = $('#student_nom').val() || '';
				var prenom = $('#student_prenom').val() || '';
				var note = $('#student_email_note');
				if ($.trim(nom) === '') { $('#student_email').val(''); note.prop('hidden', true); return; }
				var n = ++adresseNumero;
				$.getJSON(APP_BASE + '/students/adresse-libre', { nom: nom, prenom: prenom })
					.done(function (r) {
						if (n !== adresseNumero || !r) { return; }
						$('#student_email').val(r.adresse || '');
						if (r.adresse && r.prise_par) {
							note.text(r.base + ' est déjà prise par ' + r.prise_par + ' : ' + r.adresse + ' lui est attribuée.')
								.removeClass().addClass('field-note field-note--prise').prop('hidden', false);
						} else if (r.adresse) {
							note.text('Adresse libre, générée d’après le nom et le prénom.')
								.removeClass().addClass('field-note').prop('hidden', false);
						} else {
							note.prop('hidden', true);
						}
					})
					.fail(function () { if (n === adresseNumero) { $('#student_email_note').prop('hidden', true); } });
			}, 350);
		}

		$('#student_nom, #student_prenom').on('input', genererEmailEtudiant);

		// ============================================================
		// Amorçage depuis une candidature acceptée
		//
		// Les champs sont pré-remplis côté serveur, donc aucun événement
		// utilisateur ne se déclenche : ni la génération d'email (liée au
		// keyup), ni le chargement des parcours (lié au change de la mention).
		// On les provoque ici, une seule fois, au chargement.
		// ============================================================
<?php if ($pontCandidat): ?>
		(function amorcerDepuisCandidature() {
			/* Rappel de l'origine du dossier, en toast plutot qu'en bandeau :
			   le formulaire est deja dense, l'information est ponctuelle.
			   Duree allongee (8 s) : c'est une phrase a lire, pas un simple
			   accuse de reception.

			   Le nom vient du formulaire PUBLIC, donc d'une source non
			   maitrisee, et Toast.show l'insere via innerHTML : il est
			   echappe cote PHP avant d'arriver ici. */
<?php
			/* Double protection, car les deux contextes sont traverses :
			   htmlspecialchars parce que Toast.show insere via innerHTML,
			   json_encode parce que le resultat devient un litteral JS — un
			   antislash ou un retour a la ligne dans le nom romprait sinon la
			   chaine. htmlspecialchars seul ne traite ni l'un ni l'autre. */
			$nomEchappe     = htmlspecialchars($pontCandidat['nom_complet'], ENT_QUOTES, 'UTF-8');
			$mentionEchappee = htmlspecialchars($pontCandidat['mention_libelle'] ?: $pontCandidat['mention'], ENT_QUOTES, 'UTF-8');
			$messageToast   = 'Création à partir de la candidature de <strong>' . $nomEchappe . '</strong> ('
			                . $mentionEchappee . '). Les champs pré-remplis restent modifiables.';
			?>
			if (typeof Toast !== 'undefined') {
				Toast.show(
					<?=json_encode($messageToast, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>,
					'info',
					{ title: 'Dossier repris', duration: 8000 }
				);
			}

			if ($('#student_nom').val()) { genererEmailEtudiant(); }

			var mention  = $('#etude_envisage').val();
			var parcours = <?=json_encode($preParcours, JSON_UNESCAPED_UNICODE)?>;
			if (!mention) { return; }

			// La liste des parcours arrive par requête ; on ne peut sélectionner
			// qu'une fois le fragment inséré, d'où le rappel dans « success »
			// plutôt qu'un délai arbitraire.
			$.ajax({
				url: APP_BASE + "/src/services/parcours.live",
				method: "POST",
				data: { mention: mention },
				success: function (data) {
					$("#etude_option").html(data);
					if (!parcours) { return; }

					// Le libellé du parcours vient du formulaire public ; celui
					// de la table filière peut différer légèrement. On tente la
					// correspondance exacte, puis une approche tolérante.
					var $sel = $('#etude_option select');
					var trouve = $sel.find('option').filter(function () {
						return $.trim($(this).text()) === $.trim(parcours);
					}).first();

					if (!trouve.length) {
						var cible = parcours.toLowerCase();
						trouve = $sel.find('option').filter(function () {
							var t = $.trim($(this).text()).toLowerCase();
							return t !== '' && (t.indexOf(cible) !== -1 || cible.indexOf(t) !== -1);
						}).first();
					}

					// Sans correspondance, on laisse le choix à l'agent plutôt
					// que d'enregistrer un parcours faux.
					if (trouve.length) { $sel.val(trouve.val()); }
				}
			});
		})();
<?php endif; ?>

		// Clear error style on focus
		$('.field-input').on('focus', function(){
			$(this).css({'border':'','background':''});
			$('#alert').text('');
		});
	});
</script>