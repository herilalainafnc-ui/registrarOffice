<!DOCTYPE html>
<html lang="fr">
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php
	// SÉCURITÉ: Cette page est réservée aux administrateurs et registraires
	requireLevel(ROLE_REGISTRAR, './accueil');
	?>
	<title>Utilisateurs</title>
	<?php /* Styles de la page : src/css/comptes.css, sur les jetons du theme. */ ?>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/comptes.css?v=<?= @filemtime(__DIR__ . '/css/comptes.css') ?>">
</head>
<?php
/* ----------------------------------------------------------------------------
   Les comptes, en UNE requete : la page en faisait une par compte pour
   remplir sa fenetre d'edition (DB::find), plus une par etudiant lie. Et elle
   ecrivait les 150 fenetres d'edition d'un coup (1 Mo, 13 000 noeuds) : il n'y
   en a plus qu'une, remplie a l'ouverture depuis les donnees en bas de page.
   ------------------------------------------------------------------------- */
/* Mention d'un chef de mention (data/perimetre.php) : lue et proposee une fois
   la colonne creee (data/migration_perimetre_mention.sql). */
require_once __DIR__ . '/../data/perimetre.php';
$avecMention = (bool) $dtb->query("SHOW COLUMNS FROM compt_utilisateur LIKE 'perimetre_mention'")->fetch();
$mentionsChef = $avecMention ? perimetreMentionsPossibles($dtb) : [];

$comptes = $dtb->query("
	SELECT u.id, u.nom, u.prenom, u.pseudo, u.mail, u.post, u.level, u.etat, u.photos, u.student_id,
	       " . ($avecMention ? 'u.perimetre_mention,' : '') . "
	       (SELECT TRIM(CONCAT(e.student_nom, ' ', e.student_prenom)) FROM tbl_2024_etudiant e
	         WHERE e.student_id = NULLIF(u.student_id, '') LIMIT 1) AS etudiant
	  FROM compt_utilisateur u
	 ORDER BY u.level, u.nom
")->fetchAll(PDO::FETCH_ASSOC);

$roles = [
	1 => ['superadmin',   'Superadmin'],
	2 => ['admin',        'Administrateur'],
	3 => ['registraire',  'Registraire'],
	4 => ['comptabilite', 'Comptabilité'],
	5 => ['media',        'Média'],
	6 => ['chef',         'Chef de mention'],
	7 => ['professeur',   'Professeur'],
	8 => ['etudiant',     'Étudiant'],
	9 => ['work-education', 'Work Education'], /* attribue le Work Education : sa seule page */
];

$stats = ['total' => count($comptes), 'actifs' => 0, 'acces' => 0];
foreach ($comptes as $u) {
	if ((int) $u['etat'] === 1) { $stats['actifs']++; }
	if (in_array((int) $u['level'], [1, 2, 3], true)) { $stats['acces']++; }
}
$stats['inactifs'] = $stats['total'] - $stats['actifs'];

$dossierPhotos = dirname(__DIR__) . '/app/photosuser/';
/** Adresse de la photo du compte, ou null si le fichier manque (image cassee sinon). */
$photoDe = static function (array $u) use ($dossierPhotos, $app_base): ?string {
	$f = trim((string) ($u['photos'] ?? ''));
	if ($f === '' || basename($f) !== $f || !is_file($dossierPhotos . $f)) { return null; }
	return $app_base . '/app/photosuser/' . rawurlencode($f);
};

/* Donnees de la fenetre d'edition, par compte. */
$donnees = [];
foreach ($comptes as $u) {
	$donnees[(int) $u['id']] = [
		'nom' => (string) $u['nom'], 'prenom' => (string) $u['prenom'], 'post' => (string) $u['post'],
		'mail' => (string) $u['mail'], 'level' => (int) $u['level'], 'pseudo' => (string) $u['pseudo'],
		'etat' => (int) $u['etat'] === 1, 'photos' => (string) $u['photos'], 'photo' => $photoDe($u),
		'student_id' => (string) ($u['student_id'] ?? ''), 'etudiant' => (string) ($u['etudiant'] ?? ''),
		'perimetre_mention' => (string) ($u['perimetre_mention'] ?? ''),
	];
}
?>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="min-h-screen w-full <?=$bg_three_color?>">

	<!-- TOP BAR --><?php require('../init/topbar.php');?>
	<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
	<!-- NOTIFICATION MANAGER --><?php require_once(__DIR__ . '/main/bigNotif.php');?>

	<div class="w-full flex flex-col lg:flex-row">
		<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

		<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
		<main class="cpt-page flex-1 overflow-y-auto" id="cptPage">

			<header class="cpt-tete">
				<div>
					<h1>Utilisateurs</h1>
					<p>Les comptes qui ouvrent l'application : leur rôle, leur poste et leur état.</p>
				</div>
				<button type="button" id="addUserBtn" class="cpt-btn cpt-btn--primaire">
					<i class="bi bi-person-plus-fill" aria-hidden="true"></i> Nouvel utilisateur
				</button>
			</header>

			<div class="cpt-stats" id="cptStats">
				<div class="cpt-stat cpt-ton--accent"><span class="cpt-stat__icone"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
					<div><span class="cpt-stat__chiffre"><?= $stats['total'] ?></span><span class="cpt-stat__libelle">Comptes</span></div></div>
				<div class="cpt-stat cpt-ton--succes"><span class="cpt-stat__icone"><i class="bi bi-person-check-fill" aria-hidden="true"></i></span>
					<div><span class="cpt-stat__chiffre"><?= $stats['actifs'] ?></span><span class="cpt-stat__libelle">Actifs</span></div></div>
				<div class="cpt-stat cpt-ton--or"><span class="cpt-stat__icone"><i class="bi bi-shield-fill-check" aria-hidden="true"></i></span>
					<div><span class="cpt-stat__chiffre"><?= $stats['acces'] ?></span><span class="cpt-stat__libelle">Superadmins, admins et registraires</span></div></div>
				<div class="cpt-stat cpt-ton--danger"><span class="cpt-stat__icone"><i class="bi bi-person-x-fill" aria-hidden="true"></i></span>
					<div><span class="cpt-stat__chiffre"><?= $stats['inactifs'] ?></span><span class="cpt-stat__libelle">Inactifs</span></div></div>
			</div>

			<div class="cpt-outils">
				<div class="cpt-recherche">
					<label for="searchUsers" class="sr-only">Rechercher un utilisateur</label>
					<i class="bi bi-search" aria-hidden="true"></i>
					<input type="search" id="searchUsers" class="cpt-champ" placeholder="Nom, identifiant, poste ou e-mail" autocomplete="off">
				</div>
				<span class="cpt-compte" id="cptCompte" aria-live="polite"><?= $stats['total'] ?> comptes</span>
			</div>

			<div class="cpt-cadre">
				<table class="cpt-table" id="usersTable">
					<thead>
						<tr>
							<th scope="col">Utilisateur</th>
							<th scope="col">Identifiant</th>
							<th scope="col">Rôle</th>
							<th scope="col">Poste</th>
							<th scope="col">Statut</th>
							<th scope="col"><span class="sr-only">Actions</span></th>
						</tr>
					</thead>
					<tbody>
<?php foreach ($comptes as $u):
	$id = (int) $u['id'];
	[$roleCle, $roleLibelle] = $roles[(int) $u['level']] ?? ['etudiant', (string) $u['level']];
	$nomComplet = trim($u['nom'] . ' ' . $u['prenom']);
	$initiales = mb_strtoupper(mb_substr((string) $u['nom'], 0, 1) . mb_substr((string) $u['prenom'], 0, 1)) ?: 'U';
	$photo = $photoDe($u);
	$cle = mb_strtolower($nomComplet . ' ' . $u['pseudo'] . ' ' . $u['post'] . ' ' . $u['mail']);
?>
						<tr data-user-id="<?= $id ?>" data-name="<?= e($cle) ?>">
							<td>
								<div class="cpt-personne">
									<span class="cpt-avatar"><?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" width="38" height="38" loading="lazy" decoding="async"><?php else: ?><?= e($initiales) ?><?php endif; ?></span>
									<span><span class="cpt-nom"><?= e($nomComplet) ?></span><span class="cpt-mail"><?= e($u['mail'] ?: 'Pas d\'e-mail') ?></span></span>
								</div>
							</td>
							<td><span class="cpt-pseudo"><?= e($u['pseudo']) ?></span></td>
							<td><span class="cpt-badge cpt-role--<?= $roleCle ?>"><?= e($roleLibelle) ?></span></td>
							<td class="cpt-poste"><?= e($u['post'] ?: '—') ?></td>
							<td><?php if ((int) $u['etat'] === 1): ?><span class="cpt-badge cpt-etat--actif"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Actif</span><?php else: ?><span class="cpt-badge cpt-etat--inactif"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Inactif</span><?php endif; ?></td>
							<td><button type="button" class="cpt-btn cpt-btn--icone updateUserBtn" data-user-id="<?= $id ?>" aria-label="Modifier <?= e($nomComplet) ?>" title="Modifier"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
						</tr>
<?php endforeach; ?>
					</tbody>
				</table>
				<div class="cpt-vide" id="cptVide" hidden>Aucun compte ne correspond à cette recherche.</div>
			</div>

		</main>
		<?php require('../init/footer.php'); ?>
		</div>
	</div>
</div>

<!-- Fenetre d'edition : une seule, remplie a l'ouverture (src/js/comptes.js). Les
     champs prennent alors le nom attendu par app/.user/updateUser (nom44, level44...). -->
<div class="cpt-voile" id="dialogueEdition" hidden>
	<div class="cpt-dialogue" role="dialog" aria-modal="true" aria-labelledby="titreEdition">
		<form id="formEdition" method="post" enctype="multipart/form-data" novalidate>
			<?= csrf_field() ?>
			<div class="cpt-dialogue__tete">
				<h2 id="titreEdition"><i class="bi bi-person-gear" aria-hidden="true"></i>Modifier l'utilisateur</h2>
				<button type="button" class="cpt-btn cpt-btn--icone js-fermer" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
			</div>
			<div class="cpt-dialogue__corps">
				<div class="cpt-formulaire">
					<div>
						<label class="cpt-photo" id="editionPhotoCadre">
							<span class="js-photo-vide"><i class="bi bi-camera" aria-hidden="true"></i><br>Choisir une photo</span>
							<img alt="" hidden>
							<input type="file" accept=".jpg,.jpeg,.png,.webp" data-champ="photos" aria-label="Photo du compte">
						</label>
						<input type="hidden" data-champ="oldPhotos">
						<label class="cpt-interrupteur">
							<input type="checkbox" data-champ="etat">
							<span class="cpt-interrupteur__piste" aria-hidden="true"></span>
							<span class="js-etat-libelle">Actif</span>
						</label>
					</div>
					<div class="cpt-champs">
						<div class="cpt-deux">
							<div><label class="cpt-libelle" for="editionNom">Nom <span class="cpt-requis">*</span></label><input class="cpt-champ" type="text" id="editionNom" data-champ="nom" required></div>
							<div><label class="cpt-libelle" for="editionPrenom">Prénom</label><input class="cpt-champ" type="text" id="editionPrenom" data-champ="prenom"></div>
						</div>
						<div><label class="cpt-libelle" for="editionPoste">Poste</label><input class="cpt-champ" type="text" id="editionPoste" data-champ="post"></div>
						<div><label class="cpt-libelle" for="editionMail">E-mail</label><input class="cpt-champ" type="email" id="editionMail" data-champ="mail"></div>
						<hr class="cpt-separe">
						<div><label class="cpt-libelle" for="editionNiveau">Rôle</label>
							<select class="cpt-champ" id="editionNiveau" data-champ="level">
								<?php foreach ($roles as $n => [, $libelle]): ?><option value="<?= $n ?>"><?= e($libelle) ?></option><?php endforeach; ?>
							</select>
						</div>
						<?php if ($avecMention): ?>
						<div id="editionMention" hidden>
							<label class="cpt-libelle" for="editionMentionChoix">Mention</label>
							<select class="cpt-champ" id="editionMentionChoix" data-champ="perimetre_mention">
								<option value="">— Choisir la mention —</option>
								<?php foreach ($mentionsChef as $m): ?><option value="<?= e($m) ?>"><?= e($m) ?></option><?php endforeach; ?>
							</select>
							<p class="cpt-aide">Sa liste des étudiants ne montre que cette mention.</p>
						</div>
						<?php endif; ?>
						<div id="editionLienEtudiant" hidden>
							<label class="cpt-libelle" for="editionRechercheEtudiant">Étudiant lié</label>
							<input type="search" class="cpt-champ" id="editionRechercheEtudiant" placeholder="Rechercher par nom ou matricule" autocomplete="off">
							<div class="cpt-resultats" id="editionResultats" hidden></div>
							<input type="hidden" data-champ="student_id">
							<div class="cpt-lien" id="editionLien"><span></span></div>
						</div>
						<div><label class="cpt-libelle" for="editionPseudo">Identifiant <span class="cpt-requis">*</span></label><input class="cpt-champ" type="text" id="editionPseudo" data-champ="pseudo" required autocomplete="off"></div>
						<div><label class="cpt-libelle" for="editionMdp">Nouveau mot de passe</label><input class="cpt-champ" type="password" id="editionMdp" data-champ="password" placeholder="Laisser vide pour le conserver" autocomplete="new-password"></div>
					</div>
				</div>
			</div>
			<div class="cpt-dialogue__pied">
				<button type="button" class="cpt-btn cpt-btn--second js-fermer">Annuler</button>
				<button type="submit" class="cpt-btn cpt-btn--primaire"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer</button>
			</div>
		</form>
	</div>
</div>

<!-- Fenetre de creation -->
<div class="cpt-voile" id="addUserDialog" hidden>
	<div class="cpt-dialogue" role="dialog" aria-modal="true" aria-labelledby="titreCreation">
		<form id="formToAddUser" method="post" action="<?=$app_base?>/app/.user/add.user?id=<?= (int) $rg_id ?>" enctype="multipart/form-data">
			<?= csrf_field() ?>
			<div class="cpt-dialogue__tete">
				<h2 id="titreCreation"><i class="bi bi-person-plus-fill" aria-hidden="true"></i>Créer un utilisateur</h2>
				<button type="button" class="cpt-btn cpt-btn--icone js-fermer" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
			</div>
			<div class="cpt-dialogue__corps">
				<div class="cpt-formulaire">
					<div>
						<label class="cpt-photo">
							<span class="js-photo-vide"><i class="bi bi-camera" aria-hidden="true"></i><br>Ajouter une photo</span>
							<img alt="" hidden>
							<input type="file" accept=".jpg,.jpeg,.png,.webp" name="photos" id="photosuser" aria-label="Photo du compte">
						</label>
					</div>
					<div class="cpt-champs">
						<div class="cpt-deux">
							<div><label class="cpt-libelle" for="nom">Nom <span class="cpt-requis">*</span></label><input class="cpt-champ" type="text" name="nom" id="nom" required></div>
							<div><label class="cpt-libelle" for="prenom">Prénom <span class="cpt-requis">*</span></label><input class="cpt-champ" type="text" name="prenom" id="prenom" required></div>
						</div>
						<div><label class="cpt-libelle" for="post">Poste</label><input class="cpt-champ" type="text" name="post" id="post"></div>
						<div><label class="cpt-libelle" for="mail">E-mail</label><input class="cpt-champ" type="email" name="mail" id="mail"></div>
						<hr class="cpt-separe">
						<div><label class="cpt-libelle" for="levelSelect">Rôle</label>
							<select class="cpt-champ" name="level" id="levelSelect">
								<?php foreach ($roles as $n => [, $libelle]): ?><option value="<?= $n ?>"<?= $n === 3 ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach; ?>
							</select>
						</div>
						<?php if ($avecMention): ?>
						<div id="mentionSection" hidden>
							<label class="cpt-libelle" for="mentionChoix">Mention</label>
							<select class="cpt-champ" name="perimetre_mention" id="mentionChoix">
								<option value="">— Choisir la mention —</option>
								<?php foreach ($mentionsChef as $m): ?><option value="<?= e($m) ?>"><?= e($m) ?></option><?php endforeach; ?>
							</select>
							<p class="cpt-aide">Sa liste des étudiants ne montre que cette mention.</p>
						</div>
						<?php endif; ?>
						<div id="teacherLinkSection" hidden>
							<label class="cpt-libelle" for="teacherUidSelect">Enseignant lié</label>
							<select class="cpt-champ" name="teacher_uid" id="teacherUidSelect">
								<option value="">— Choisir l'enseignant —</option>
								<?php
								/* L'adresse de la fiche est portée par l'option : choisir
								   l'enseignant remplit le champ e-mail, car c'est par elle
								   qu'il se connectera (bouton Google, ou e-mail + mot de
								   passe). uid = 0 n'est pas un enseignant mais le marqueur
								   « aucun » porté par certains cours. */
								$teachers = $dtb->query("SELECT uid, name, lastName, email FROM teacher WHERE remove != 1 AND uid <> 0 ORDER BY lastName, name");
								while ($teacher = $teachers->fetch()) {
									$courriel = trim((string) ($teacher['email'] ?? ''));
									if (strcasecmp($courriel, 'none') === 0) { $courriel = ''; }
									echo '<option value="' . (int) $teacher['uid'] . '" data-mail="' . e($courriel) . '">'
									   . e($teacher['lastName'] . ' ' . $teacher['name'])
									   . ($courriel !== '' ? ' — ' . e($courriel) : ' — (sans adresse)')
									   . '</option>';
								}
								?>
							</select>
							<p class="cpt-aide" id="teacherMailNote"></p>
						</div>
						<div id="studentLinkSection" hidden>
							<label class="cpt-libelle" for="studentSearchAdd">Étudiant lié</label>
							<input type="search" class="cpt-champ" id="studentSearchAdd" placeholder="Rechercher par nom ou matricule" autocomplete="off">
							<div class="cpt-resultats" id="studentSearchResults" hidden></div>
							<input type="hidden" name="student_id" id="selectedStudentId">
							<div class="cpt-lien" id="selectedStudentBadge" hidden>
								<span id="selectedStudentText"></span>
								<button type="button" id="clearStudentSelection" aria-label="Retirer l'étudiant lié"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
							</div>
						</div>
						<div><label class="cpt-libelle" for="pseudo">Identifiant <span class="cpt-requis">*</span></label><input class="cpt-champ" type="text" name="pseudo" id="pseudo" required autocomplete="off"></div>
						<div class="cpt-deux">
							<div><label class="cpt-libelle" for="password">Mot de passe <span class="cpt-requis">*</span></label><input class="cpt-champ" type="password" name="password" id="password" required autocomplete="new-password"></div>
							<div><label class="cpt-libelle" for="confirmpass">Confirmation <span class="cpt-requis">*</span></label><input class="cpt-champ" type="password" name="confirmpass" id="confirmpass" required autocomplete="new-password"></div>
						</div>
						<p id="passwordMatch" class="cpt-aide" hidden></p>
					</div>
				</div>
			</div>
			<div class="cpt-dialogue__pied">
				<button type="button" class="cpt-btn cpt-btn--second js-fermer">Annuler</button>
				<button type="submit" id="btnAddUser" class="cpt-btn cpt-btn--primaire" disabled><i class="bi bi-person-plus" aria-hidden="true"></i> Créer</button>
			</div>
		</form>
	</div>
</div>

<script type="application/json" id="cptDonnees"><?= json_encode(
	$donnees,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
) ?></script>
<script type="application/json" id="cptConfig"><?= json_encode(
	['rgId' => (int) $rg_id],
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<script src="<?=$app_base?>/src/js/comptes.js?v=<?= @filemtime(__DIR__ . '/js/comptes.js') ?>" defer></script>
</body>
</html>
