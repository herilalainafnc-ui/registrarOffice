<!DOCTYPE html>
<html lang="fr">
<head>
	<?php require('../init/head.php');?>
	<title>Inscription</title>
	<?php /* La page et les fragments d'etapes (inscription/stages/) se stylent
	         dans src/css/inscription.css, sur les jetons du theme. */ ?>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/inscription.css?v=<?=@filemtime(__DIR__ . '/../src/css/inscription.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* ----------------------------------------------------------------------------
   Le dossier recherche : correspondance exacte sur le matricule d'abord, puis
   matricule, nom ou prenom. Une recherche vide n'ouvre plus rien : le
   LIKE '%%' qui suivait renvoyait le premier etudiant venu.
   ------------------------------------------------------------------------- */
$recherche = trim((string) ($_GET['student_id'] ?? $_POST['student_id'] ?? ''));
$profil = null;
if ($recherche !== '') {
	$search = $recherche;
	$stmt = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :exact AND remove != 1 LIMIT 1');
	$stmt->execute(['exact' => $search]);
	if ($stmt->rowCount() === 0) {
		$searchParam = '%' . $search . '%';
		$stmt = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE (student_id LIKE :search1 OR student_nom LIKE :search2 OR student_prenom LIKE :search3) AND remove != 1 ORDER BY (student_id = :exact_order) DESC LIMIT 1');
		$stmt->execute(['search1' => $searchParam, 'search2' => $searchParam, 'search3' => $searchParam, 'exact_order' => $search]);
	}
	$recupsdt = $stmt;
	$profil = $recupsdt->rowCount() > 0 ? $recupsdt->fetch() : null;
}

if ($profil) {
	/* Les variables du dossier : les fichiers inclus plus bas (fiche
	   d'information, cours offerts) et le script de la page les lisent. */
	$id = $profil['id'];
	$student_id = $profil['student_id'];
	$student_nom = $profil['student_nom'];
	$student_prenom = $profil['student_prenom'];
	$level = $profil['annee_etude'];
	$annee_scolaire = $profil['annee_scolaire'];
	$etude_envisage = $profil['etude_envisage'];
	$etude_option = $profil['etude_option'];
	$student_tel = $profil['student_tel'];
	$image_student = $profil['image_student'];
	$lookup_code = $profil['lookup_code'];
	$status = $profil['status'];
	$graduated = $profil['graduated'];
	$date_entry = $profil['date_entry'];
	$imangeLen = strlen((string) $profil['image_student']);

	$nomComplet = trim(mb_strtoupper((string) $student_nom) . ' ' . $student_prenom);
	$photo = $image_student !== null && trim((string) $image_student) !== '' ? (string) $image_student : null;

	if ((int) $profil['new_student'] === 1) {
		$typeEtudiant = ['nouveau', 'Nouveau'];
	} elseif ((int) $profil['new_student'] === 10) {
		$typeEtudiant = ['special', 'Spécial'];
	} else {
		$typeEtudiant = ['ancien', 'Ancien'];
	}

	$anneeEtude = (int) $profil['annee_etude'];
	if ($anneeEtude === 0) {
		$niveauLibelle = 'Remise à niveau';
	} elseif ($anneeEtude <= 3) {
		$niveauLibelle = 'Licence ' . $anneeEtude;
	} else {
		$niveauLibelle = 'Master ' . ($anneeEtude - 3);
	}

	// Suspendu : l'inscription et la prise de cours sont bloquees.
	$isSuspended = false;
	if (isset($profil['suspended']) && $profil['suspended'] == 1) {
		$dateFin = $profil['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			$isSuspended = true;
		}
	}
	// Autorise a la reinscription (caisse) et dossier en ordre (registraire).
	$isAuthorizedReinscription = isset($profil['authorized_reinscription']) && $profil['authorized_reinscription'] == 1;
	$isDossierOk = isset($profil['dossier_ok']) && $profil['dossier_ok'] == 1;

	// Derniere modification du dossier : qui, et quand.
	$modifiePar = null;
	if (!empty($profil['last_change_user_id'])) {
		$findUser = $dtb->prepare('SELECT prenom FROM compt_utilisateur WHERE id = :id');
		$findUser->execute(['id' => $profil['last_change_user_id']]);
		$modifiePar = $findUser->fetchColumn() ?: null;
	}
	$modifieLe = !empty($profil['last_change_datetime']) && strtotime($profil['last_change_datetime'])
		? date('d/m/Y', strtotime($profil['last_change_datetime']))
		: null;

	// Session de reference de la page : celle de l'annee scolaire en cours.
	// La requete precedente prenait « l'id le plus grand », sans filtre
	// d'annee : un dossier d'annee anterieure cree plus tard l'emportait, et
	// cet identifiant est ensuite propage au JS, aux etapes, aux frais et a la
	// fiche d'inscription. Voir data/inscription.session.php.
	require_once(__DIR__ . '/../data/inscription.session.php');
	$showStudent_Session = inscSessionCourante($dtb, (string) $student_id);
	$session_id = $showStudent_Session ? (string) $showStudent_Session['session_id'] : '';
	$y = date('Y');

	/* Le selecteur d'annee generait neuf annees en dur, dont beaucoup n'ont
	   aucune session au catalogue : le registraire ne l'apprenait qu'apres
	   avoir clique, par « Session non configurée ». On ne propose donc que
	   ce qui existe dans t_2023_session, annee par annee. */
	$catalogueSessions = [];
	foreach ($dtb->query('SELECT session_year, session_name FROM t_2023_session
	                        WHERE session_year <> ""
	                        ORDER BY session_year DESC, session_semester') as $ligneSession) {
		$catalogueSessions[$ligneSession['session_year']][] = $ligneSession['session_name'];
	}
	$anneesCatalogue = array_keys($catalogueSessions);
	$anneeDefaut = in_array(inscAnneeCourante(), $anneesCatalogue, true)
		? inscAnneeCourante()
		: ($anneesCatalogue[0] ?? inscAnneeCourante());
	$semestresAnnee = $catalogueSessions[$anneeDefaut] ?? [];
	/* Semestre de la periode, s'il existe cette annee-la. */
	$semestreDefaut = (date('m') >= 7) ? 'Premier semestre' : 'Deuxième semestre';
	if (!in_array($semestreDefaut, $semestresAnnee, true)) {
		$semestreDefaut = $semestresAnnee[0] ?? '';
	}
}

/* Les sept etapes du guichet, dans l'ordre ou le script de la page les
   parcourt (?stage=1 a 7). */
$etapesGuichet = [
	1 => ['Session', "Semestre et année scolaire de l'inscription."],
	2 => ['Informations', "Identité, contacts et filière de l'étudiant."],
	3 => ['Cours offerts', 'Les cours suivis pendant la session.'],
	4 => ['Mode de paiement', 'La tranche choisie ; les frais sont calculés.'],
	5 => ["Fiche d'inscription", 'Imprimée, puis signée.'],
	6 => ['Signatures et dépôt', 'La liste signée remise au registraire.'],
	7 => ['Terminé', "L'étudiant est inscrit."],
];
?>

<div class="min-h-screen w-full <?=$bg_three_color?>">
	<!-- TOP BAR --><?php require('../init/topbar.php');?>

	<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

	<div class="w-full flex flex-col lg:flex-row">
		<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>
		<?php /* Au bureau la page ne defile pas (init/head.php) : c'est la zone ci-dessous
		         qui defile, entre la barre du haut et le pied (src/css/inscription.css). */ ?>
		<div class="insc-colonne w-full lg:w-10/12 flex flex-col" style="min-height: calc(100vh - 56px);">
		<?php /* La zone : recherche, dossier, etapes et blocages y sont remplaces sans
		         recharger la page (src/js/zone-ajax.js). Le script du dossier et les
		         modales sont donc a l'interieur, rejoues a chaque dossier ouvert. */ ?>
		<main class="insc-page flex-1" id="inscPage" data-zone-ajax>

			<header class="insc-tete" id="header">
				<div>
					<h1>Inscription et réinscription</h1>
					<p>Ouvrez le dossier d'un étudiant, puis suivez les étapes du guichet.</p>
				</div>
				<?php /* GET plutot que POST : l'URL du dossier devient partageable et
				         rechargeable, sans avertissement de re-soumission. */ ?>
				<form method="get" action="<?=$app_base?>/inscription/inscription" id="form-recherche" class="insc-recherche" role="search">
					<label for="insc-recherche" class="sr-only">Matricule ou nom de l'étudiant</label>
					<i class="bi bi-search" aria-hidden="true"></i>
					<input id="insc-recherche" type="search" name="student_id" value="<?= e($recherche) ?>"
					       placeholder="Matricule ou nom" autocomplete="off" spellcheck="false" enterkeyhint="search"<?= $recherche === '' ? ' autofocus' : '' ?>>
					<button type="submit" class="insc-btn insc-btn--primaire">Ouvrir</button>
				</form>
			</header>

<?php if (!$profil): ?>
<?php if ($recherche !== ''): ?>
			<div class="insc-introuvable" role="status">
				<i class="bi bi-person-x" aria-hidden="true"></i>
				<div>
					<h2>Aucun étudiant ne correspond à « <?= e($recherche) ?> »</h2>
					<p>Vérifiez le matricule, ou cherchez par nom ou prénom. Les dossiers supprimés n'apparaissent pas.</p>
				</div>
			</div>
<?php endif; ?>
			<section class="insc-parcours" aria-labelledby="inscParcoursTitre">
				<h2 id="inscParcoursTitre">Les étapes du guichet</h2>
				<p>Chaque dossier passe par ces étapes, dans cet ordre. Une fois le dossier ouvert, son état s'affiche en haut de la page.</p>
				<ol>
<?php foreach ($etapesGuichet as $rang => [$nomEtape, $aideEtape]): ?>
					<li><span class="insc-parcours-puce"><?= $rang === 7 ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : $rang ?></span><b><?= e($nomEtape) ?></b><span><?= e($aideEtape) ?></span></li>
<?php endforeach; ?>
				</ol>
			</section>
<?php else: ?>

			<?php /* Le dossier a gauche, de haut en bas ; a droite, l'etat du
			         dossier puis l'etape en cours. */ ?>
			<div id="body" class="insc-grille">

				<aside class="insc-dossier" aria-label="Dossier de l'étudiant">
					<div class="insc-dossier-tete">
						<button type="button" class="insc-photo" id="inscPhoto"<?= $photo ? ' aria-label="Agrandir la photo"' : ' disabled aria-label="Pas de photo"' ?>>
							<img src="<?=$app_base?>/app/photosetudiants/<?= e($photo ?? '10054.jpg') ?>" alt="" width="76" height="96">
						</button>
						<div class="insc-dossier-id">
							<span class="insc-type insc-type--<?= $typeEtudiant[0] ?>"><?= $typeEtudiant[1] ?></span>
							<span class="code-tag"><?= e($student_id) ?></span>
							<p class="insc-niveau"><?= e($niveauLibelle) ?></p>
						</div>
					</div>

					<h2 class="insc-nom"><?= e(mb_strtoupper((string) $student_nom)) ?> <span><?= e($student_prenom) ?></span></h2>
					<p class="insc-filiere"><?= e($etude_envisage) ?><?= $etude_option !== '' && $etude_option !== null ? ' — ' . e($etude_option) : '' ?></p>

					<dl class="insc-faits">
						<div><dt>Année</dt><dd><?= e($annee_scolaire) ?></dd></div>
<?php if (!empty($profil['student_email'])): ?>
						<div><dt>Courriel</dt><dd><a href="https://mail.google.com/mail/?view=cm&amp;to=<?= rawurlencode((string) $profil['student_email']) ?>" target="_blank" rel="noopener"><?= e($profil['student_email']) ?></a></dd></div>
<?php endif; ?>
					</dl>
<?php if ($modifiePar || $modifieLe): ?>
					<p class="insc-modifie">Modifié<?= $modifiePar ? ' par <b>' . e($modifiePar) . '</b>' : '' ?><?= $modifieLe ? ' le ' . $modifieLe : '' ?></p>
<?php endif; ?>

<?php if ($isSuspended): ?>
					<div class="insc-alerte insc-alerte--danger" role="alert">
						<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
						<div>
							<b>Étudiant suspendu</b>
							<p>L'inscription et la prise de cours sont bloquées<?= !empty($profil['date_fin_suspension']) ? " jusqu'au " . date('d/m/Y', strtotime($profil['date_fin_suspension'])) : '' ?>.</p>
						</div>
					</div>
<?php endif; ?>

					<hr class="insc-separe">

					<p class="insc-surtitre" id="inscGuichetTitre">Guichet</p>
					<?php /* Ou l'on en est dans la saisie : le script de la page deplace
					         l'etape courante (guichet()). Les etapes 3 a 5 sont
					         grisees pour un etudiant suspendu. */ ?>
					<ol class="insc-guichet" id="inscGuichet" aria-labelledby="inscGuichetTitre">
<?php
	$idsGuichet = [2 => 'intInformation', 3 => 'intNewcours', 4 => 'intModepayement', 5 => 'intFicheinscription'];
	foreach ($etapesGuichet as $rang => [$nomEtape]):
		$classes = [];
		if ($rang === 1) { $classes[] = 'est-courante'; }
		if ($isSuspended && $rang >= 3 && $rang <= 5) { $classes[] = 'est-bloquee'; }
?>
						<li data-etape="<?= $rang ?>" title="<?= e($nomEtape) ?>"<?= isset($idsGuichet[$rang]) ? ' id="' . $idsGuichet[$rang] . '"' : '' ?><?= $classes ? ' class="' . implode(' ', $classes) . '"' : '' ?><?= $rang === 1 ? ' aria-current="step"' : '' ?>><span class="insc-guichet-puce"><?= $rang ?></span><?= e($nomEtape) ?></li>
<?php endforeach; ?>
					</ol>

					<div class="insc-actions">
						<button type="button" id="downStage" class="insc-btn insc-btn--second toolInactive" disabled><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</button>
						<button type="button" id="upStage" data-stage="1" class="insc-btn insc-btn--primaire toolInactive" disabled>Suivant <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
					</div>
				</aside>

				<div class="insc-travail">

				<section class="insc-bande" aria-label="État du dossier">
					<div class="insc-bande-tete">
						<p class="insc-surtitre">État du dossier</p>
						<div id="session" class="insc-bande-session" aria-live="polite"></div>
					</div>
					<div id="five-stages" class="insc-bande-barre"><div class="insc-etapes-vide">Lecture du dossier…</div></div>
				</section>

<!-- ::::::::::::::::::::::::::::::: MAIN CONTENT :::::::::::::::::::::::::::::::: -->

				<div id="contentMain" class="insc-panneau">

					<section id="contentGenerateStudet" class="stage_0 insc-etape-vue" aria-labelledby="inscVue1">
						<header class="insc-vue-tete">
							<p class="insc-surtitre">Étape 1 sur 7</p>
							<h2 id="inscVue1">Session d'inscription</h2>
							<p>Choisissez la session à laquelle l'étudiant s'inscrit. Les frais, les cours et la fiche en dépendent : rien ne peut se faire sans elle.</p>
						</header>

						<form method="post" action="<?=$app_base?>/inscription/app/generate.student.php?student_id=<?=urlencode((string) $student_id)?>&graduated=<?=urlencode((string) $graduated)?>" class="session-no-refrech insc-session-form">
							<?= csrf_field() ?>
							<div class="insc-champs">
								<label class="insc-champ-bloc">
									<span class="insc-libelle">Semestre</span>
									<select name="semesterSession" id="semesterSelect" class="insc-champ">
										<?php foreach ($semestresAnnee as $nomSession) { ?>
											<option<?= $nomSession === $semestreDefaut ? ' selected' : '' ?>><?= e($nomSession) ?></option>
										<?php } ?>
										<?php if ($semestresAnnee === []) { ?>
											<option disabled>Aucune session au catalogue</option>
										<?php } ?>
									</select>
								</label>
								<label class="insc-champ-bloc">
									<span class="insc-libelle">Année scolaire</span>
									<select name="annee_scolaire" id="yearSelect" class="insc-champ">
										<?php foreach ($anneesCatalogue as $anneeSession) { ?>
											<option<?= $anneeSession === $anneeDefaut ? ' selected' : '' ?>><?= e($anneeSession) ?></option>
										<?php } ?>
									</select>
								</label>
							</div>
							<div class="insc-session-pied">
								<button id="submitSession" type="submit" class="insc-btn insc-btn--primaire toolInactive" disabled>Enregistrer la session</button>
								<p id="sessionMessage" class="insc-statut insc-statut--attente" role="status" aria-live="polite">Vérification…</p>
							</div>
						</form>
					</section>

<script>
// ---------------------------------------------------------------------------
// Utilitaires partagés : jeton CSRF et signalement d'erreur.
// Les appels AJAX de ce module modifient des données (inscriptions, montants,
// notes) : ils doivent tous porter le jeton et rendre compte d'un échec.
// ---------------------------------------------------------------------------

/* Le dossier affiche. On passe d'un dossier a l'autre sans recharger
   (src/js/zone-ajax.js) : une reponse arrivee pour le dossier precedent ne
   doit rien ecrire dans celui-ci. */
window.inscDossier = <?= json_encode((string) $student_id) ?>;
function dossierAffiche(sid) { return sid === window.inscDossier; }

/** Active ou desactive un bouton du guichet. toolInactive bloque le
 *  pointeur (src/css/style.css), disabled bloque le clavier : un lien
 *  seulement grise se suivait encore a la touche Entree. */
function activerBouton(bouton, actif) {
	$(bouton).toggleClass('toolInactive', !actif).prop('disabled', !actif);
}

/** Message d'etat de la session : ok, info, attente ou erreur. */
function statutSession(texte, etat) {
	var zone = document.getElementById('sessionMessage');
	if (!zone) { return; }
	zone.textContent = texte;
	zone.className = 'insc-statut insc-statut--' + etat;
}

/** Le retard sur le calendrier des inscriptions de la session (§ 334 du
 *  bulletin academique ; data/inscriptions.tardives.php), avant ou apres
 *  l'enregistrement. null si l'inscription est dans les temps. */
function jourRetard(ymd) {
	var p = String(ymd || '').split('-');
	return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) : '';
}
function messageRetard(r, enregistree) {
	if (!r || (r.etat !== 'tardive' && r.etat !== 'hors-delai')) { return null; }
	var jour = jourRetard;
	var ar = function (n) { return Number(n || 0).toLocaleString('fr-FR') + ' Ar'; };
	var jours = r.jours + ' jour' + (r.jours > 1 ? 's' : '') + ' ouvrable' + (r.jours > 1 ? 's' : '');
	// dates propres a sa mention, son niveau ou a lui (calendrier particulier)
	var cas = r.cas ? ' Calendrier particulier : ' + r.cas.libelle + '.' : '';
	if (r.etat === 'hors-delai') {
		return 'Hors délai : les inscriptions tardives ont pris fin le ' + jour(r.limite_tardive) + '. '
			+ (enregistree ? 'Inscription enregistrée avec ' : 'L\'inscription reste possible, avec ') + jours + ' de retard : '
			+ ar(r.penalite) + ' de pénalité ' + (enregistree ? 'ajoutés' : 's\'ajouteront') + ' à ses frais divers, et elle est marquée hors délai.' + cas;
	}
	return 'Inscription tardive : ' + jours + ' de retard (inscriptions closes le ' + jour(r.fin_normale) + '). '
		+ (enregistree ? 'Pénalité de ' + ar(r.penalite) + ' ajoutée à ses frais divers.' : 'Une pénalité de ' + ar(r.penalite) + ' s\'ajoutera à ses frais divers.') + cas;
}

/** Sans penalite alors que les inscriptions de la session sont closes : un
 *  nouvel etudiant (jamais penalise), ou un calendrier particulier (stage...).
 *  On le dit, pour que le guichet sache pourquoi. null sinon. */
function messageSansPenalite(r, enregistree) {
	if (!r || !r.session_fin_normale || (r.etat !== 'nouveau' && !(r.etat === 'a-l-heure' && r.cas))) { return null; }
	var auj = new Date();
	var ymd = auj.getFullYear() + '-' + String(auj.getMonth() + 1).padStart(2, '0') + '-' + String(auj.getDate()).padStart(2, '0');
	if (ymd <= r.session_fin_normale) { return null; }
	var suite = enregistree ? ' Session enregistrée : vous pouvez passer à l\'étape suivante.' : '';
	if (r.etat === 'nouveau') {
		return 'Pas de pénalité : nouvel étudiant (les nouveaux étudiants ne sont pas pénalisés pour une inscription tardive).' + suite;
	}
	return 'Pas de pénalité : calendrier particulier « ' + r.cas.libelle + ' »' + (r.cas.motif ? ' (' + r.cas.motif + ')' : '')
		+ ', inscription sans pénalité jusqu\'au ' + jourRetard(r.fin_normale) + '.' + suite;
}

/** Met en avant l'etape n du guichet ; les precedentes sont franchies.
 *  Au-dela de 7, tout le parcours l'est. */
function guichet(n) {
	$('#inscGuichet li').each(function () {
		var rang = parseInt(this.getAttribute('data-etape'), 10);
		this.classList.toggle('est-courante', rang === n);
		this.classList.toggle('est-passee', rang < n);
		if (rang === n) { this.setAttribute('aria-current', 'step'); } else { this.removeAttribute('aria-current'); }
	});
}

/** Signale un problème à l'utilisateur au lieu de l'ignorer silencieusement. */
function inscriptionErreur(message) {
	var zone = document.getElementById('sessionMessage');
	if (zone) {
		zone.textContent = message;
		zone.className = 'insc-statut insc-statut--erreur';
	} else {
		if (window.Toast) { Toast.error(message); } else if (window.console) { console.error(message); }
	}
}

/** Message lisible à partir d'une réponse jQuery en échec. */
function inscriptionMessageErreur(xhr, repli) {
	if (xhr && xhr.responseJSON && xhr.responseJSON.message) { return xhr.responseJSON.message; }
	if (xhr && xhr.status === 401) { return 'Session expirée. Reconnectez-vous.'; }
	if (xhr && xhr.status === 403) { return 'Action refusée. Rafraîchissez la page et réessayez.'; }
	return repli || 'Une erreur est survenue.';
}

/**
 * Marque une étape franchie côté serveur.
 * Remplace sept appels $.post(url, function(response){}) dont le callback vide
 * jetait la réponse — y compris le refus « étudiant suspendu ».
 */
/** Relit la barre d'étapes depuis le serveur.
 *  Les classes etaient repeintes ici a chaque etape franchie, ce qui
 *  affichait l'etape comme faite sans rien verifier. Le serveur la
 *  deduit maintenant des donnees (inscription/stages/top.stages.php). */
function rafraichirEtapes() {
	var sid = '<?=$student_id?>';
	return $.post(APP_BASE + '/inscription/stages/top.stages.php', {
		student_id: sid,
		session_id: currentSessionId
	}).done(function (html) {
		if (dossierAffiche(sid)) { $('#five-stages').html(html); }
	});
}

function marquerEtape(stage, sessionId) {
	var url = APP_BASE + '/inscription/app/generate.stage.php'
		+ '?student_id=<?=$student_id?>&stage=' + encodeURIComponent(stage)
		+ (sessionId ? '&session_id=' + encodeURIComponent(sessionId) : '');

	return $.post(url, { csrf_token: CSRF_TOKEN })
		.done(function (reponse) {
			if (reponse && reponse.success === false) {
				inscriptionErreur(reponse.message || "L'étape n'a pas pu être enregistrée.");
			}
		})
		.fail(function (xhr) {
			inscriptionErreur(inscriptionMessageErreur(xhr, "L'étape n'a pas pu être enregistrée."));
		});
}

// Vérification dynamique de la session
function checkSessionExists() {
	const semester = document.getElementById('semesterSelect').value;
	const year = document.getElementById('yearSelect').value;
	const studentId = '<?=$student_id?>';
	
	const submitBtn = document.getElementById('submitSession');
	const message = document.getElementById('sessionMessage');
	const upStageBtn = document.getElementById('upStage');
	
	// État de chargement
	statutSession('Vérification…', 'attente');
	activerBouton(submitBtn, false);
	
	$.ajax({
		url: APP_BASE+'/inscription/app/check.session.php',
		type: 'POST',
		data: {
			student_id: studentId,
			semester: semester,
			year: year,
			csrf_token: CSRF_TOKEN
		},
		success: function(response) {
			if (!dossierAffiche(studentId)) { return; }
			// Mettre à jour currentSessionId si disponible
			if (response.session_id) {
				currentSessionId = response.session_id;
			}
			
			if (response.exists) {
				// Session existe déjà - désactiver Enregistrer, activer Suivant
				activerBouton(submitBtn, false);
				activerBouton(upStageBtn, true);
				statutSession('La session est déjà enregistrée. Vous pouvez passer à l\'étape suivante.', 'ok');
			} else {
				// Session n'existe pas - activer Enregistrer, désactiver Suivant
				activerBouton(submitBtn, true);
				activerBouton(upStageBtn, false);
				var retard = messageRetard(response.retard, false);
				if (retard) {
					statutSession(retard, 'alerte');
				} else {
					statutSession(messageSansPenalite(response.retard, false) || response.message || 'Enregistrez la session pour passer à l\'étape suivante.', 'info');
				}
			}
		},
		error: function() {
			if (!dossierAffiche(studentId)) { return; }
			statutSession('La vérification de la session a échoué. Réessayez.', 'erreur');
		}
	});
}

// Écouter les changements sur les selects
$(document).ready(function() {
	checkSessionExists(); // Vérification initiale
	
	$('#semesterSelect, #yearSelect').on('change', function() {
		if (this.id === 'yearSelect') { majSemestres(); }
		checkSessionExists();
		// Mettre à jour l'affichage de la session dans la sidebar
		updateSessionDisplay();
	});
});

// Mettre à jour l'affichage de la session dans la sidebar
function updateSessionDisplay() {
	var sid = '<?=$student_id?>';
	$.ajax({
		url: APP_BASE+'/inscription/stages/session.php',
		method: "POST",
		data: {
			student_id: sid,
			session_id: currentSessionId,
			semester: $('#semesterSelect').val(),
			year: $('#yearSelect').val()
		},
		success: function(data) {
			if (dossierAffiche(sid)) { $('#session').html(data); }
		}
	});
}
</script>

					<section id="contentInformation" class="stage_1 hidden insc-etape-vue" aria-labelledby="inscVue2">
						<header class="insc-vue-tete">
							<p class="insc-surtitre">Étape 2 sur 7</p>
							<h2 id="inscVue2">Informations de l'étudiant</h2>
							<p>Vérifiez l'identité, les contacts et la filière avant de passer aux cours.</p>
						</header>
						<?php require ('../src/student/information.php'); ?>
					</section>

					<?php /* Le script recharge #contentNewcours et #contentModepayement :
					         leur en-tete reste donc a l'exterieur. */ ?>
					<section class="stage_2 hidden insc-etape-vue" aria-labelledby="inscVue3">
						<header class="insc-vue-tete">
							<p class="insc-surtitre">Étape 3 sur 7</p>
							<h2 id="inscVue3">Cours offerts</h2>
							<p>Ajoutez les cours que l'étudiant suivra pendant la session.</p>
						</header>
						<div id="contentNewcours">
							<?php require ('../src/student/new.cours.php'); ?>
						</div>
					</section>

					<section class="stage_3 hidden insc-etape-vue" aria-labelledby="inscVue4">
						<header class="insc-vue-tete">
							<p class="insc-surtitre">Étape 4 sur 7</p>
							<h2 id="inscVue4">Mode de paiement</h2>
							<p>Contrôlez les cours et les frais de la session, puis enregistrez la tranche choisie par l'étudiant.</p>
						</header>
						<div id="contentModepayement"></div>
					</section>

					<section id="contentFicheinscription" class="stage_4 hidden insc-etape-vue" aria-labelledby="inscVue5">
						<header class="insc-vue-tete">
							<p class="insc-surtitre">Étape 5 sur 7</p>
							<h2 id="inscVue5">Fiche d'inscription</h2>
							<p>Ouvrez la fiche de la session, imprimez-la et faites-la signer.</p>
						</header>
						<?php /* La fiche relit tout depuis la base (fiche_inscription.php) :
						         nom, prenom, filiere, tel et photo n'ont jamais ete lus depuis l'URL.
						         Les transmettre exposait des donnees non encodees dans un attribut HTML. */ ?>
						<a target="_blank" rel="noopener" href="#" id="linkFicheInscription" class="insc-document"
						   data-base-url="<?=$app_base?>/src/data.topdf.php?ptype=Fiche_inscription&id=<?=urlencode((string) $id)?>&student_id=<?=urlencode((string) $student_id)?>&level=<?=urlencode((string) $level)?>">
							<span class="insc-document-icone"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
							<span class="insc-document-texte">
								<b>Ouvrir la fiche d'inscription</b>
								<span>S'ouvre dans un nouvel onglet, prête à imprimer.</span>
							</span>
							<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
						</a>
					</section>

					<section id="contentImpression" class="stage_5 hidden insc-etape-vue" aria-labelledby="inscVue6">
						<header class="insc-vue-tete">
							<p class="insc-surtitre">Étape 6 sur 7</p>
							<h2 id="inscVue6">Signatures et dépôt</h2>
							<p>Le dossier se termine hors de l'écran : passez à l'étape suivante une fois la liste déposée.</p>
						</header>
						<ol class="insc-consignes">
							<li><i class="bi bi-pen" aria-hidden="true"></i><div><b>Finaliser les signatures</b><p>Sur la fiche d'inscription imprimée.</p></div></li>
							<li><i class="bi bi-inbox" aria-hidden="true"></i><div><b>Déposer la liste au registraire</b><p>La liste signée rejoint le dossier de l'étudiant.</p></div></li>
						</ol>
					</section>

					<section id="contentInscrit" class="stage_6 hidden insc-etape-vue insc-fin" aria-labelledby="inscVue7">
						<span class="insc-fin-sceau" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
						<h2 id="inscVue7">Inscription terminée</h2>
						<p>Le dossier de <?= e($nomComplet) ?> est complet pour cette session.</p>
						<a href="<?=$app_base?>/inscription/inscription" class="insc-btn insc-btn--second"><i class="bi bi-search" aria-hidden="true"></i> Ouvrir un autre dossier</a>
					</section>

				</div>

				</div><!-- /insc-travail -->

			</div>

			<?php /* La photo agrandie : bouton de la photo pour l'ouvrir, Fermer,
			         Echap ou un clic a cote pour la refermer. */ ?>
			<div class="insc-visionneuse" id="inscVisionneuse" role="dialog" aria-modal="true" aria-label="Photo de l'étudiant" hidden>
<?php if ($photo): ?>
				<figure>
					<img src="<?=$app_base?>/app/photosetudiants/<?= e($photo) ?>" alt="Photo de <?= e($nomComplet) ?>">
					<figcaption><?= e($nomComplet) ?> · <?= e($student_id) ?></figcaption>
				</figure>
<?php endif; ?>
				<button type="button" id="inscVisionneuseFermer" class="insc-btn insc-btn--second">Fermer</button>
			</div>
<?php endif; /* fin du dossier ouvert */ ?>

<?php /* Tout ce bloc pilote le dossier affiche : etapes, session, frais, fiche.
         Sans etudiant charge il n'a rien a piloter, et il interpolait pourtant
         $session_id, $student_id, $id et $graduated — non definis dans ce cas.
         Chaque « Undefined variable » de PHP 8 s'imprimait alors au milieu du
         JavaScript, ce qui suffit a en casser l'analyse et a rendre la page
         entierement inerte. Il n'est donc emis que lorsqu'un dossier existe. */
if (isset($profil)) { ?>
<script type="text/javascript">
	/* Semestres existants par annee : proposer autre chose mene a « Session non
   configurée » (voir $catalogueSessions plus haut). */
var SESSIONS_CATALOGUE = <?= json_encode($catalogueSessions ?? [], JSON_UNESCAPED_UNICODE) ?>;

/** Aligne la liste des semestres sur l'annee choisie. */
function majSemestres() {
	var annee = document.getElementById('yearSelect');
	var select = document.getElementById('semesterSelect');
	if (!annee || !select) { return; }

	var noms = SESSIONS_CATALOGUE[annee.value] || [];
	var courant = select.value;
	select.innerHTML = '';
	noms.forEach(function (nom) {
		var option = document.createElement('option');
		option.textContent = nom;
		if (nom === courant) { option.selected = true; }
		select.appendChild(option);
	});
	if (noms.length === 0) {
		var vide = document.createElement('option');
		vide.textContent = 'Aucune session au catalogue';
		vide.disabled = true;
		select.appendChild(vide);
	}
}

/** Recalcul des frais de la session déjà enregistrée. */
function recalculerFrais(bouton) {
	var libelle = bouton.innerHTML;
	bouton.disabled = true;
	bouton.textContent = 'Recalcul…';

	$.post(APP_BASE + '/inscription/app/recalculer-frais.php', {
		student_id: '<?=$student_id?>',
		session_id: currentSessionId,
		csrf_token: CSRF_TOKEN
	}).done(function (reponse) {
		if (!reponse || reponse.success === false) {
			inscriptionErreur((reponse && reponse.message) || 'Le recalcul a échoué.');
			return;
		}
		/* Le detail ligne par ligne : le registraire doit voir ce qui a bouge
		   avant de reimprimer la fiche. */
		var detail = (reponse.lignes || []).map(function (l) {
			return l.libelle + ' : ' + l.avant + ' → ' + l.apres;
		}).join('\n');
		if (window.Toast) {
			window.Toast.success(reponse.message + (detail ? '\n' + detail : ''));
		}
		updateSessionDisplay();
		// Les montants affichés et la barre datent d'avant le recalcul.
		if (typeof loadModePayement === 'function') { loadModePayement(currentSessionId); }
		rafraichirEtapes();
	}).fail(function (xhr) {
		inscriptionErreur(inscriptionMessageErreur(xhr, 'Le recalcul a échoué.'));
	}).always(function () {
		bouton.disabled = false;
		bouton.innerHTML = libelle;
	});
}

/* Rejoue a chaque dossier ouvert : l'ecoute porte un nom et remplace la
   precedente, pour qu'un clic ne recalcule qu'une fois. */
$(document).off('click.inscFrais').on('click.inscFrais', '#recalculerFrais', function () { recalculerFrais(this); });

// Variable globale pour stocker le session_id actuel (mis à jour dynamiquement)
	var currentSessionId = '<?= e($session_id) ?>';

	$(document).ready(function(){

		$('.session-no-refrech').on('submit',function(submitSS){
			submitSS.preventDefault();

			var url = APP_BASE+'/inscription/app/generate.student.php?student_id=<?=$student_id?>&graduated=<?=$graduated ?? 0?>';
			var data = $(this).serialize();
			
			var submitBtn = $('#submitSession');
			submitBtn.prop('disabled', true).text('Enregistrement...');

			$.post(url, data, function(response){
				// Parser la réponse JSON si possible
				var result = response;
				if (typeof response === 'string') {
					try { result = JSON.parse(response); } catch(e) { result = { success: true }; }
				}

				// Vérifier si le serveur a renvoyé une erreur
				if (result.success === false) {
					activerBouton(submitBtn, true);
					submitBtn.text('Enregistrer la session');
					statutSession(result.message || 'Erreur lors de l\'enregistrement', 'erreur');
					Toast.error(result.message || 'Erreur lors de l\'enregistrement de la session');
					return;
				}
				
				// IMPORTANT: Mettre à jour la variable globale currentSessionId
				if (result.session_id) {
					currentSessionId = result.session_id;
					console.log('Session ID mis à jour:', currentSessionId);
				}

				activerBouton('#upStage', true);
				activerBouton('#submitSession', false);
				$('#submitSession').text('Enregistrer la session');
				var retardEnregistre = messageRetard(result.retard, true);
				if (retardEnregistre) {
					statutSession(retardEnregistre, 'alerte');
					if (window.Toast) { Toast.warning(retardEnregistre); }
				} else {
					statutSession(messageSansPenalite(result.retard, true) || 'Session enregistrée. Vous pouvez passer à l\'étape suivante.', 'ok');
				}
				
				var	student_id = '<?=$student_id?>';

				$.ajax({
						url: APP_BASE+'/inscription/stages/top.stages.php',
						method:"POST",
						data:{student_id:student_id},

						success:function(data){

							if (dossierAffiche(student_id)) { $("#five-stages").html(data); }
						}
					});

				$.ajax({
						url: APP_BASE+'/inscription/stages/session.php',
						method:"POST",
						data:{
							student_id: student_id,
							session_id: currentSessionId,
							semester: $('#semesterSelect').val(),
							year: $('#yearSelect').val()
						},

						success:function(data){
							
							if (dossierAffiche(student_id)) { $("#session").html(data); }
							// Afficher un message de succès
							Toast.success('Session enregistrée avec succès!');
						}
					});

			}).fail(function(xhr){
				// Remonter le motif exact (session expirée, droits, CSRF) plutôt
				// qu'un message générique qui laisse l'utilisateur sans recours.
				var motif = inscriptionMessageErreur(xhr, "Erreur lors de l'enregistrement");
				activerBouton(submitBtn, true);
				submitBtn.text('Enregistrer la session');
				statutSession(motif, 'erreur');
				Toast.error(motif);
			});

		});

		var	student_id = '<?=$student_id?>';

		$.ajax({
				url: APP_BASE+'/inscription/stages/top.stages.php',
				method:"POST",
				data:{student_id:student_id},

				success:function(data){

					if (dossierAffiche(student_id)) { $("#five-stages").html(data); }
				}
			});

		$.ajax({
				url: APP_BASE+'/inscription/stages/session.php',
				method:"POST",
				data:{
					student_id: student_id,
					session_id: currentSessionId,
					semester: $('#semesterSelect').val(),
					year: $('#yearSelect').val()
				},

				success:function(data){
					
					if (dossierAffiche(student_id)) { $("#session").html(data); }
				}
			});


        function updateContent() {
        
            var params = new URLSearchParams(window.location.search);
        
            var stage = params.get('stage');
            
            if (stage == 1 ) {
            	
            	$('.stage_0').css({'display':'block'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
            	$('.stage_5').css({'display':'none'});
            	$('.stage_6').css({'display':'none'});


            	guichet(1);


				marquerEtape(1);
			

            }else if (stage == 2 ) {
            	
            	$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'block'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
            	$('.stage_5').css({'display':'none'});
            	$('.stage_6').css({'display':'none'});
				
				guichet(2);

				
				marquerEtape(2);

				// Utiliser la variable globale currentSessionId (toujours à jour)
				var session_id = currentSessionId || $("#session_id").text();

				$("input[name='session_id']").attr('value', session_id);

            }else if (stage == 3 ) {
            	
            	$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'block'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
            	$('.stage_5').css({'display':'none'});	
				$('.stage_6').css({'display':'none'});

            	guichet(3);

				rafraichirEtapes();

				// Utiliser la variable globale currentSessionId (toujours à jour)
				var session_id = currentSessionId || $("#session_id").text();
				
				marquerEtape(3, session_id);

				// Rafraîchir la liste des cours pour refléter les changements (suppression à l'étape 4)
				$('#contentNewcours').css('opacity', '0.5');
				$.ajax({
					url: APP_BASE+'/src/student/new.cours.php',
					method: 'GET',
					cache: false,
					data: {
						student_id: '<?=$student_id?>',
						id: '<?=$id?>',
						session_id: session_id,
						_t: Date.now() // Éviter le cache
					},
					success: function(data) {
						$('#contentNewcours').html(data).css('opacity', '1');
						// Réattacher les événements si nécessaire
						if (typeof bindFormEvents === 'function') {
							bindFormEvents();
						}
					},
					error: function() {
						$('#contentNewcours').css('opacity', '1');
						Toast.error('Erreur lors du rafraîchissement de la liste des cours');
					}
				});

            }else if (stage == 4 ) {
				
				$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
				$('.stage_5').css({'display':'none'});
				$('.stage_6').css({'display':'none'});

            	guichet(4);

				activerBouton('#upStage', false);
            	
            	rafraichirEtapes();
            	
            	marquerEtape(4);
            

            	var student_id = '<?=$student_id?>';
            	// Utiliser la variable globale currentSessionId (toujours à jour)
            	var session_id = currentSessionId || $("#session_id").text();
            	
            	// Fonction pour charger le mode de paiement
            	function loadModePayement(sid) {
            		// Le panneau devient visible avant l'appel : en cas d'echec il
            		// ne restait sinon aucun contenu, et l'etape 4 s'affichait
            		// entierement vide, sans explication.
            		$('.stage_3').css({'display':'block'});
            		$("#contentModepayement").html(
            			'<div class="p-6 text-center text-slate-400">Chargement des frais…</div>');

            		$.ajax({
						url: APP_BASE+'/inscription/stages/mode.payement.php',
						method:"POST",
						data:{student_id:student_id, session_id:sid},
						success:function(data){
							if (!dossierAffiche(student_id)) { return; }
							$("#contentModepayement").html(data);
						},
						error:function(xhr){
							if (!dossierAffiche(student_id)) { return; }
							var motif = inscriptionMessageErreur(xhr, "Les frais n'ont pas pu être chargés.");
							// .text() puis .html() : le motif vient du serveur,
							// il est insere comme texte et non comme balisage.
							$("#contentModepayement").html(
								$('<div class="p-6 text-center text-red-400">').text(motif));
							Toast.error(motif);
						}
					});
            	}
            	
            	if (session_id) {
            		// session_id disponible, charger directement
            		loadModePayement(session_id);
            	} else {
            		// session_id pas encore disponible (race condition), attendre le chargement de session.php
            		var retries = 0;
            		var waitForSession = setInterval(function() {
            			var sid = currentSessionId || $("#session_id").text();
            			retries++;
            			if (sid) {
            				clearInterval(waitForSession);
            				currentSessionId = sid;
            				loadModePayement(sid);
            			} else if (retries >= 10) {
            				// Après 2 secondes, charger quand même (le fallback PHP récupérera le session_id)
            				clearInterval(waitForSession);
            				loadModePayement('');
            			}
            		}, 200);
            	}

				$('#downStage').click(function(){
					activerBouton('#upStage', true);
				});

            }else if (stage == 5 ) {
				
				$('.stage_0').css({'display':'none'});
            	$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'block'});
				$('.stage_5').css({'display':'none'});
				$('.stage_6').css({'display':'none'});

            	guichet(5);

            	rafraichirEtapes();
            	
            	// Mettre à jour le lien de la fiche d'inscription avec le bon session_id
            	var linkFiche = $('#linkFicheInscription');
            	var baseUrl = linkFiche.data('base-url');
            	var session_id = currentSessionId || $("#session_id").text();
            	linkFiche.attr('href', baseUrl + '&session_id=' + session_id);
            	
            	marquerEtape(5);


            }else if (stage == 6 ) {
				
				$('.stage_0').css({'display':'none'});
  				$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
				$('.stage_5').css({'display':'block'});
				$('.stage_6').css({'display':'none'});

            	guichet(6);

				rafraichirEtapes();
				
				marquerEtape(6);

            }else if (stage == 7 ) {
            	
            	$('.stage_0').css({'display':'none'});  				
  				$('.stage_1').css({'display':'none'});
            	$('.stage_2').css({'display':'none'});
            	$('.stage_3').css({'display':'none'});
            	$('.stage_4').css({'display':'none'});
				$('.stage_5').css({'display':'none'});           	     		
				$('.stage_6').css({'display':'block'});

            	rafraichirEtapes();
            	
            	guichet(7);

            	marquerEtape(7);
            }
            
            //$('#contentMain').html(contentHtml);
            
        }
        
        // ===================================================================
        // Transition stinger + navigation façon single page application
        // ===================================================================

        /** Étape courante lue dans l'URL (1 par défaut, bornée à 1-7). */
        function etapeCourante() {
            var v = parseInt(new URLSearchParams(window.location.search).get('stage'), 10);
            return (v >= 1 && v <= 7) ? v : 1;
        }

        // Seule étape dont l'arrivée déclenche le stinger. Ailleurs le
        // changement est instantané : la transition perdait son effet à être
        // rejouée à chaque pas du parcours.
        var ETAPE_AVEC_STINGER = 2;

        /**
         * Applique l'étape courante — derrière le rideau si c'est l'étape
         * concernée, immédiatement sinon.
         */
        function allerEtape() {
            var e = etapeCourante();
            // Le rideau vient du système partagé (src/js/transitions.js), celui
            // qui masque aussi les navigations : une seule superposition pour
            // toute l'application, au lieu d'une copie propre à cette page.
            // Repli immédiat si le script n'est pas là : la navigation entre
            // étapes ne doit jamais dépendre de l'animation.
            var partage = window.rgPageTransition;
            if (e === ETAPE_AVEC_STINGER && partage && partage.swap) {
                partage.swap(updateContent);
            } else {
                updateContent();
            }
        }

        // Navigation arrière / avant du navigateur : sans cela, les pushState
        // empilés laissaient l'URL changer sans que le contenu suive.
        // Une seule ecoute, celle du dossier affiche : une ecoute restee d'un
        // dossier precedent marquerait ses etapes a lui. Elle ne suit que les
        // entrees posees par Suivant et Retour (elles portent le dossier) ;
        // la recherche et les autres dossiers sont l'affaire de zone-ajax.
        if (window.inscPopstate) { window.removeEventListener('popstate', window.inscPopstate); }
        window.inscPopstate = function (e) {
            if (!e.state || !e.state.inscDossier) { return; }
            if (e.state.inscDossier === student_id && document.getElementById('upStage')) {
                $('#upStage').data('stage', etapeCourante());
                allerEtape();
                return;
            }
            // L'entree d'un dossier qui n'est plus affiche (recherche faite
            // depuis) : on le rouvre dans la zone, sans recharger la page.
            if (window.ZoneAjax) { ZoneAjax.aller(location.href); }
        };
        window.addEventListener('popstate', window.inscPopstate);

		$('#upStage').on('click', function(event) {

            event.preventDefault(); 

            //var student_id = $(this).data('student_id');
            var stage = $(this).data('stage');

            var currentUrl = new URL(window.location.href);
            
       		

           // currentUrl.searchParams.set('student_id', student_id);
            
            if (stage > 0 && stage <= 6) {
            	
            	currentUrl.searchParams.set('stage', stage+1);
            	$(this).data('stage', stage+1);
            	activerBouton('#downStage', true);

            }else if(stage == 7) {

	       		Toast.success('Inscription terminée avec succès! L\'étudiant est maintenant inscrit.');
	       		activerBouton(this, false);
	       		activerBouton('#downStage', false);

	       		guichet(8);
           
           	}

            window.history.pushState({ inscDossier: student_id }, '', currentUrl);

            // Le changement d'étape se joue pendant que le stinger masque l'écran.
            allerEtape();
        });
        




        $('#downStage').on('click', function(event) {
            event.preventDefault();
            //var student_id = $(this).data('student_id');
            var stage = $('#upStage').data('stage');
            
            var currentUrl = new URL(window.location.href);
            

            //currentUrl.searchParams.set('student_id', student_id);
           	
           	if (stage > 1) {
           		
           		currentUrl.searchParams.set('stage', stage-1);

            	$('#upStage').data('stage', stage - 1);

           	}else {
           		activerBouton(this, false);
           	}

            window.history.pushState({ inscDossier: student_id }, '', currentUrl);

            // Le changement d'étape se joue pendant que le stinger masque l'écran.
            allerEtape();
        });

		// La photo agrandie : Fermer, Echap ou un clic a cote la referment.
		$('#inscPhoto').on('click', function(){
			$('#inscVisionneuse').prop('hidden', false);
			$('#inscVisionneuseFermer').trigger('focus');
		});
		function fermerPhoto() {
			if ($('#inscVisionneuse').prop('hidden')) { return; }
			$('#inscVisionneuse').prop('hidden', true);
			$('#inscPhoto').trigger('focus');
		}
		$('#inscVisionneuseFermer').on('click', fermerPhoto);
		$('#inscVisionneuse').on('click', function (e) { if (e.target === this) { fermerPhoto(); } });
		$(document).off('keydown.inscPhoto').on('keydown.inscPhoto', function (e) { if (e.key === 'Escape') { fermerPhoto(); } });
		$('#listOpt2').click(function(){
			$('#notifModifIMG').css({'display':'block'});
		});
		$('#cancelModifIMG').click(function(){
			$('#notifModifIMG').css({'display':'none'});
		});
		$('#image_student').on('change',function(){
			var image_student = $(this).val();
			if(image_student!="") {
				$('#imgNote').text('Image bien ajouté.');
				$('#btnModify').attr('class','px-2 rounded-md py-1 text-white mx-1 bg-cyan-700');
			}
		});
		$('#linkSupprStd').click(function(){
			$('#notifSupprStd').css({'display':'block'});
		});

	});
</script>
<?php } /* fin du bloc reserve a un dossier charge */ ?>

<?php if ($profil && !$isAuthorizedReinscription) { ?>
<!-- MODAL BLOCAGE RÉINSCRIPTION -->
<div id="modalBlockReinscription" class="insc-blocage insc-blocage--danger" role="alertdialog" aria-modal="true" aria-labelledby="blocageReinsTitre" aria-describedby="blocageReinsTexte">
	<div class="insc-blocage-carte">
		<span class="insc-blocage-icone" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
		<h2 id="blocageReinsTitre">Réinscription non autorisée</h2>
		<p id="blocageReinsTexte">L'étudiant <strong><?= e($nomComplet) ?></strong> n'a pas encore été autorisé à faire la réinscription.</p>
		<p class="insc-blocage-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Contactez le <strong>service de caisse</strong> pour obtenir l'autorisation avant de poursuivre.</p>
		<a href="<?=$app_base?>/inscription/inscription" class="insc-btn insc-btn--second"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la recherche</a>
	</div>
</div>
<?php } ?>

<?php if ($profil && !$isDossierOk) { ?>
<!-- MODAL BLOCAGE DOSSIER REGISTRAIRE -->
<div id="modalBlockDossier" class="insc-blocage insc-blocage--warning" role="alertdialog" aria-modal="true" aria-labelledby="blocageDossierTitre" aria-describedby="blocageDossierTexte">
	<div class="insc-blocage-carte">
		<span class="insc-blocage-icone" aria-hidden="true"><i class="bi bi-folder-x"></i></span>
		<h2 id="blocageDossierTitre">Dossier non validé</h2>
		<p id="blocageDossierTexte">Le dossier de l'étudiant <strong><?= e($nomComplet) ?></strong> n'a pas encore été validé par le registraire.</p>
		<p class="insc-blocage-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Contactez le <strong>service du registraire</strong> pour la vérification du dossier avant de poursuivre.</p>
		<a href="<?=$app_base?>/inscription/inscription" class="insc-btn insc-btn--second"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la recherche</a>
	</div>
</div>
<?php } ?>

<?php if ($profil && (!$isAuthorizedReinscription || !$isDossierOk)) { ?>
<script>
	/* Un dossier bloque : le focus va a la sortie de la modale (apres celui
	   que zone-ajax pose sur le titre de la page). */
	setTimeout(function () {
		var sortie = document.querySelector('.insc-blocage a');
		if (sortie) { sortie.focus(); }
	}, 0);
</script>
<?php } ?>

		</main>
		<?php require('../init/footer.php'); ?>
		</div>
	</div>

</div>

</body>
</html>
