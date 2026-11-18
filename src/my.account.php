<?php
/* =============================================================================
   Mon compte

   Refondue sur l'idiome des reglages de compte de Meta : des RANGEES qui
   montrent le libelle, la valeur actuelle et un bouton « Modifier », et dont le
   formulaire s'ouvre sur place.

   La page precedente affichait six champs en LECTURE SEULE et un formulaire de
   mot de passe : on ne pouvait ni corriger son nom, ni son adresse email, ni
   deposer une photo. Tout est modifiable maintenant, sauf le role — reserve au
   superadmin, verrouille cote serveur dans app/.user/mon-compte.save.php.

   Aucun traitement de formulaire ici : tout passe par ce point d'entree, en
   arriere-plan. La page ne fait que rendre l'etat courant.
   ========================================================================== */

require('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$app_base = defined('APP_BASE') ? APP_BASE : '';
$user = currentUser();

if (!$user || empty($user['id'])) {
    if (function_exists('memoriserUrlDemandee')) { memoriserUrlDemandee(); }
    header('Location: ' . $app_base . '/login', true, 302);
    exit;
}

$niveau    = (int) ($user['level'] ?? 0);
$estSuper  = $niveau === 1 || (string) ($user['privilege'] ?? '') === 'superadmin';
$roleLabel = getRoleLabel($niveau);

$nomComplet = trim(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? ''));
if ($nomComplet === '') { $nomComplet = (string) ($user['pseudo'] ?? 'Utilisateur'); }


/* L'URL de l'avatar est calculee par init/topbar.php, requis plus bas mais
   AVANT ce fragment de corps : $rg_avatar_url et $rg_initiales sont donc
   disponibles. On les reutilise plutot que de refaire le calcul.

   Ce n'est pas de l'economie : ma premiere version le refaisait ici avec un
   simple is_file(), et ignorait donc les comptes dont la photo est une URL
   Google. La barre du haut montrait le portrait, cette page les initiales —
   pour le meme compte, a deux centimetres d'ecart. */

$derniereConnexion = !empty($_SESSION['login_time'])
    ? date('d/m/Y à H:i', (int) $_SESSION['login_time']) : null;
$mdpChangeLe = !empty($_SESSION['password_updated_at'])
    ? date('d/m/Y à H:i', (int) $_SESSION['password_updated_at']) : null;

/* Les neuf roles, dans l'ordre des niveaux. Meme table que celle du point
   d'entree — les deux doivent proposer exactement les memes choix. */
$roles = [
    1 => 'Superadmin', 2 => 'Administrateur', 3 => 'Registraire', 4 => 'Comptabilité',
    5 => 'Média', 6 => 'Chef de mention', 7 => 'Professeur', 8 => 'Étudiant',
    9 => 'Work Education',
];

/* Rangee d'identite : libelle, valeur, aide. Une boucle plutot que cinq blocs
   copies — les cinq se comportent exactement pareil. */
$champsIdentite = [
    ['nom',    'Nom',                     $user['nom'] ?? '',    'text',  'family-name'],
    ['prenom', 'Prénom',                  $user['prenom'] ?? '', 'text',  'given-name'],
    ['post',   'Fonction',                $user['post'] ?? '',   'text',  'organization-title'],
    ['pseudo', 'Identifiant de connexion',$user['pseudo'] ?? '', 'text',  'username'],
    ['mail',   'Adresse email',           $user['mail'] ?? '',   'email', 'email'],
];
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Mon compte</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/my-account.css?v=<?=@filemtime(__DIR__.'/css/my-account.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- BIG NOTIFICATION MANAGER --><?php require_once(__DIR__ . '/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<div class="back flex-1 overflow-y-auto p-4 lg:p-6">

				<div class="mc" id="mon-compte">

					<?php /* ===== Entete : photo, nom, role ===== */ ?>
					<div class="mc-tete">
						<div class="mc-avatar">
<?php if (($rg_avatar_url ?? '') !== ''): ?>
							<img data-avatar src="<?= e($rg_avatar_url) ?>" alt="Votre photo de profil">
<?php else: ?>
							<?php /* Initiales plutot qu'une silhouette grise : elles
							         identifient le compte, et le bouton d'appareil dit
							         qu'on peut deposer une photo. */ ?>
							<span class="mc-avatar__initiales" data-avatar aria-hidden="true"><?= e($rg_initiales ?? '?') ?></span>
<?php endif; ?>

							<?php /* Un LABEL, pas un bouton : il declenche l'input fichier
							         nativement, le clavier l'atteint, et il n'y a rien a
							         synchroniser. */ ?>
							<label class="mc-avatar__bouton" for="mc-photo" title="Changer la photo de profil">
								<i class="bi-camera-fill" aria-hidden="true"></i>
								<span class="sr-only">Changer la photo de profil</span>
							</label>
							<input type="file" id="mc-photo" name="photo" class="sr-only"
							       accept="image/jpeg,image/png,image/webp"
							       data-jeton="<?= e(csrf_token()) ?>">
						</div>

						<div>
							<h1 class="mc-tete__nom" id="mc-nom-affiche"><?= e($nomComplet) ?></h1>
							<p class="mc-tete__sous"><?= e($user['mail'] ?? '') ?></p>
							<div class="mc-tete__etiquettes">
								<span class="mc-etiquette">
									<i class="bi-shield-check" aria-hidden="true"></i> <?= e($roleLabel) ?>
								</span>
<?php if ($derniereConnexion): ?>
								<span class="mc-etiquette mc-etiquette--neutre">
									<i class="bi-clock-history" aria-hidden="true"></i>
									Connecté le <?= e($derniereConnexion) ?>
								</span>
<?php endif; ?>
							</div>
						</div>
					</div>

					<?php /* ===== Informations personnelles ===== */ ?>
					<section class="mc-section">
						<div class="mc-section__tete">
							<h2>Informations personnelles</h2>
							<p>Votre nom et votre adresse email servent à vous identifier dans l’application et sur les documents que vous éditez.</p>
						</div>

						<?php /* Un seul formulaire pour les cinq champs : le serveur les
						         valide ensemble (l'identifiant et l'email doivent rester
						         uniques). Chaque champ garde sa propre rangee, et
						         l'enregistrement met les cinq resumes a jour. */ ?>
						<form id="mc-form-identite" novalidate>
							<input type="hidden" name="action" value="identite">
							<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<?php foreach ($champsIdentite as [$nom, $libelle, $valeur, $type, $autofill]): ?>
							<div class="mc-rangee" data-champ="<?= e($nom) ?>">
								<button type="button" class="mc-rangee__resume" aria-expanded="false">
									<span class="mc-rangee__texte">
										<span class="mc-rangee__libelle"><?= e($libelle) ?></span>
										<span class="mc-rangee__valeur<?= trim((string)$valeur) === '' ? ' mc-rangee__valeur--vide' : '' ?>">
											<?= trim((string)$valeur) !== '' ? e($valeur) : 'Non renseigné' ?>
										</span>
									</span>
									<span class="mc-rangee__action">Modifier</span>
									<span class="mc-rangee__chevron" aria-hidden="true"><i class="bi-chevron-down"></i></span>
								</button>

								<div class="mc-rangee__volet"><div>
									<div class="mc-rangee__contenu">
										<div class="mc-champ">
											<label for="mc-<?= e($nom) ?>"><?= e($libelle) ?></label>
											<input type="<?= e($type) ?>" id="mc-<?= e($nom) ?>" name="<?= e($nom) ?>"
											       value="<?= e($valeur) ?>" autocomplete="<?= e($autofill) ?>">
<?php if ($nom === 'pseudo'): ?>
											<p class="mc-aide">Ce que vous saisissez pour vous connecter. Lettres, chiffres, point, tiret et souligné.</p>
<?php elseif ($nom === 'post'): ?>
											<p class="mc-aide">Votre intitulé de poste, tel qu’il apparaît sur les documents que vous signez.</p>
<?php endif; ?>
										</div>
										<div class="mc-actions">
											<button type="submit" class="mc-btn mc-btn--plein">Enregistrer</button>
											<button type="button" class="mc-btn mc-btn--vide" data-annuler>Annuler</button>
										</div>
									</div>
								</div></div>
							</div>
<?php endforeach; ?>
						</form>
					</section>

					<?php /* ===== Sécurité ===== */ ?>
					<section class="mc-section">
						<div class="mc-section__tete">
							<h2>Sécurité</h2>
							<p>Un mot de passe long et propre à cette application vaut mieux qu’un mot de passe compliqué réutilisé ailleurs.</p>
						</div>

						<div class="mc-rangee">
							<button type="button" class="mc-rangee__resume" aria-expanded="false">
								<span class="mc-rangee__texte">
									<span class="mc-rangee__libelle">Mot de passe</span>
									<span class="mc-rangee__valeur" id="mc-mdp-date">
										<?= $mdpChangeLe ? 'Modifié le ' . e($mdpChangeLe) : 'Jamais modifié depuis cette page' ?>
									</span>
								</span>
								<span class="mc-rangee__action">Modifier</span>
								<span class="mc-rangee__chevron" aria-hidden="true"><i class="bi-chevron-down"></i></span>
							</button>

							<div class="mc-rangee__volet"><div>
								<div class="mc-rangee__contenu">
									<form id="mc-form-motdepasse" novalidate>
										<input type="hidden" name="action" value="motdepasse">
										<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

										<div class="mc-champs">
											<div class="mc-champ">
												<label for="mc-actuel">Mot de passe actuel</label>
												<div class="mc-secret">
													<input type="password" id="mc-actuel" name="actuel" autocomplete="current-password">
													<button type="button" class="mc-secret__oeil" aria-label="Afficher le mot de passe">
														<i class="bi-eye" aria-hidden="true"></i>
													</button>
												</div>
											</div>
										</div>

										<div class="mc-champs mc-champs--deux" style="margin-top:14px;">
											<div class="mc-champ">
												<label for="mc-nouveau">Nouveau mot de passe</label>
												<div class="mc-secret">
													<input type="password" id="mc-nouveau" name="nouveau" autocomplete="new-password">
													<button type="button" class="mc-secret__oeil" aria-label="Afficher le mot de passe">
														<i class="bi-eye" aria-hidden="true"></i>
													</button>
												</div>
												<?php /* La jauge explique la regle des huit caracteres
												         avant le refus, pas apres. */ ?>
												<div class="mc-force">
													<div class="mc-force__piste"><div class="mc-force__jauge" id="mc-force-jauge"></div></div>
													<span class="mc-force__mot" id="mc-force-mot" aria-live="polite">Au moins 8 caractères.</span>
												</div>
											</div>
											<div class="mc-champ">
												<label for="mc-confirmation">Confirmer le nouveau mot de passe</label>
												<div class="mc-secret">
													<input type="password" id="mc-confirmation" name="confirmation" autocomplete="new-password">
													<button type="button" class="mc-secret__oeil" aria-label="Afficher le mot de passe">
														<i class="bi-eye" aria-hidden="true"></i>
													</button>
												</div>
											</div>
										</div>

										<div class="mc-actions">
											<button type="submit" class="mc-btn mc-btn--plein">Modifier le mot de passe</button>
											<button type="button" class="mc-btn mc-btn--vide" data-annuler>Annuler</button>
										</div>
									</form>
								</div>
							</div></div>
						</div>
					</section>

					<?php /* ===== Rôle et accès ===== */ ?>
					<section class="mc-section">
						<div class="mc-section__tete">
							<h2>Rôle et accès</h2>
							<p>Le rôle détermine les pages auxquelles vous avez accès.
<?php if ($estSuper): ?>
								Vous pouvez le modifier parce que vous êtes superadmin.
<?php else: ?>
								Seul un superadmin peut le modifier.
<?php endif; ?>
							</p>
						</div>

<?php if ($estSuper): ?>
						<div class="mc-rangee">
							<button type="button" class="mc-rangee__resume" aria-expanded="false">
								<span class="mc-rangee__texte">
									<span class="mc-rangee__libelle">Rôle</span>
									<span class="mc-rangee__valeur"><?= e($roleLabel) ?></span>
								</span>
								<span class="mc-rangee__action">Modifier</span>
								<span class="mc-rangee__chevron" aria-hidden="true"><i class="bi-chevron-down"></i></span>
							</button>

							<div class="mc-rangee__volet"><div>
								<div class="mc-rangee__contenu">
									<div class="mc-encart">
										<i class="bi-exclamation-triangle-fill" aria-hidden="true"></i>
										<span>Changer votre rôle recalcule vos accès immédiatement. Si vous quittez le rôle
										de superadmin, vous ne pourrez plus le reprendre vous-même — il faudra qu’un autre
										superadmin vous le rende.</span>
									</div>

									<form id="mc-form-role">
										<input type="hidden" name="action" value="role">
										<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

										<div class="mc-champ">
											<label for="mc-role">Rôle</label>
											<select id="mc-role" name="level" data-actuel="<?= $niveau ?>">
<?php foreach ($roles as $lvl => $lbl): ?>
												<option value="<?= (int) $lvl ?>"<?= $lvl === $niveau ? ' selected' : '' ?>><?= e($lbl) ?></option>
<?php endforeach; ?>
											</select>
											<?php /* Le privilege et user_type ne sont pas offerts
											         separement : les regler a la main est ce qui a
											         produit l'incoherence deja en base — un compte de
											         niveau 3 portant le privilege « administrator ».
											         Le serveur ecrit les trois ensemble. */ ?>
											<p class="mc-aide">Le privilège et le type de compte suivent automatiquement le rôle choisi.</p>
										</div>

										<div class="mc-actions">
											<button type="submit" class="mc-btn mc-btn--plein">Changer le rôle</button>
											<button type="button" class="mc-btn mc-btn--vide" data-annuler>Annuler</button>
										</div>
									</form>
								</div>
							</div></div>
						</div>
<?php else: ?>
						<?php /* Rangee verrouillee : ce n'est pas un bouton — rien ne
						         suggere un clic qui ne mene nulle part. Le cadenas et la
						         phrase disent pourquoi, sans dependre de la couleur. */ ?>
						<div class="mc-rangee">
							<div class="mc-rangee__resume mc-rangee__resume--verrouille">
								<span class="mc-rangee__texte">
									<span class="mc-rangee__libelle">Rôle</span>
									<span class="mc-rangee__valeur"><?= e($roleLabel) ?></span>
								</span>
								<span class="mc-rangee__chevron" aria-hidden="true" title="Modifiable par un superadmin seulement">
									<i class="bi-lock-fill"></i>
								</span>
							</div>
						</div>
						<div class="mc-rangee">
							<div class="mc-rangee__resume mc-rangee__resume--verrouille">
								<span class="mc-rangee__texte">
									<span class="mc-rangee__libelle">Privilège</span>
									<span class="mc-rangee__valeur"><?= e($user['privilege'] ?? '—') ?></span>
								</span>
								<span class="mc-rangee__chevron" aria-hidden="true" title="Modifiable par un superadmin seulement">
									<i class="bi-lock-fill"></i>
								</span>
							</div>
						</div>
<?php endif; ?>
					</section>

				</div>
			</div>

			<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>

	<script src="<?=$app_base?>/src/js/my-account.js?v=<?=@filemtime(__DIR__.'/js/my-account.js')?>"></script>
</body>
</html>
