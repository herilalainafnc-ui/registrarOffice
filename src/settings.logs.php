<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Journal d'activité</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/settings.logs.css?v=<?=@filemtime(__DIR__ . '/css/settings.logs.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Journal d'activité (Réglages)

   Chaque action des utilisateurs : connexion, échec de connexion, consultation
   de page, modification, action, export, déconnexion — avec l'utilisateur,
   l'heure à la milliseconde, l'IP, l'appareil, les données envoyées et, pour
   chaque écriture en base, les valeurs avant et après.

   La capture est faite par data/audit.php ; cette page ne fait que lire.
   Superadmin uniquement (même verrou dans AdminController et app/audit.*.php).
   ========================================================================== */

require_once(__DIR__ . '/../data/audit.lecture.php');

$journalPret  = journalDisponible($dtb);
$types        = journalTypes();
$utilisateurs = journalUtilisateurs($dtb);
$tables       = $journalPret ? journalTablesConnues($dtb) : [];
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

				<div class="back flex-1 overflow-y-auto" id="jlDefilement">
					<div class="jl-page">

						<header class="jl-entete">
							<div class="jl-entete__icone" aria-hidden="true"><i class="bi bi-activity"></i></div>
							<div class="jl-entete__texte">
								<h1 class="jl-titre">Journal d'activité</h1>
								<p class="jl-sous">
									Chaque connexion, consultation, modification, export et déconnexion,
									avec l'utilisateur, l'heure, l'appareil et les valeurs avant et après.
								</p>
							</div>
							<?php /* Le journal suit en direct : les nouvelles actions arrivent seules
							         (src/js/settings.logs.js). Un clic met en pause, un autre reprend. */ ?>
							<div class="jl-entete__actions">
								<button type="button" class="jl-direct" id="jlDirect" aria-pressed="true" data-etat="direct"
								        title="Mise à jour automatique : cliquez pour la mettre en pause">
									<span class="jl-direct__point" aria-hidden="true"></span>
									<span class="jl-direct__texte">
										<b id="jlDirectTitre">En direct</b>
										<small id="jlDirectMaj">connexion…</small>
									</span>
									<i class="bi bi-pause-fill jl-direct__icone" id="jlDirectIcone" aria-hidden="true"></i>
								</button>
							</div>
						</header>

						<?php /* Les chiffres du jour suivent le direct ; un clic filtre la liste
						         (aujourd'hui, ou le type d'action correspondant). */ ?>
						<section class="jl-stats" id="jlStats" aria-label="Activité d'aujourd'hui">
							<button type="button" class="jl-stat" data-filtre="aujourdhui" aria-pressed="false" title="Afficher les actions d'aujourd'hui">
								<i class="bi bi-lightning-charge jl-stat__icone" aria-hidden="true"></i>
								<span class="jl-stat__valeur" data-stat="actions">–</span><span class="jl-stat__libelle">actions aujourd'hui</span></button>
							<div class="jl-stat jl-stat--fixe">
								<i class="bi bi-people jl-stat__icone" aria-hidden="true"></i>
								<span class="jl-stat__valeur" data-stat="utilisateurs">–</span><span class="jl-stat__libelle">utilisateurs actifs</span></div>
							<button type="button" class="jl-stat jl-stat--connexion" data-filtre="connexion" aria-pressed="false" title="N'afficher que les connexions">
								<i class="bi bi-box-arrow-in-right jl-stat__icone" aria-hidden="true"></i>
								<span class="jl-stat__valeur" data-stat="connexions">–</span><span class="jl-stat__libelle">connexions</span></button>
							<button type="button" class="jl-stat jl-stat--echec" data-filtre="echec_connexion" aria-pressed="false" title="N'afficher que les échecs de connexion">
								<i class="bi bi-shield-exclamation jl-stat__icone" aria-hidden="true"></i>
								<span class="jl-stat__valeur" data-stat="echecs">–</span><span class="jl-stat__libelle">échecs de connexion</span></button>
							<button type="button" class="jl-stat jl-stat--modification" data-filtre="modification" aria-pressed="false" title="N'afficher que les modifications">
								<i class="bi bi-database-gear jl-stat__icone" aria-hidden="true"></i>
								<span class="jl-stat__valeur" data-stat="modifications">–</span><span class="jl-stat__libelle">écritures en base</span></button>
						</section>

						<form class="jl-filtres" id="jlFiltres" role="search" autocomplete="off" onsubmit="return false">
							<div class="jl-filtres__ligne">
								<label class="jl-champ jl-champ--large">
									<span class="sr-only">Rechercher</span>
									<i class="bi bi-search" aria-hidden="true"></i>
									<input type="search" name="q" placeholder="Page, utilisateur, table, IP, matricule envoyé…">
								</label>
								<label class="jl-champ">
									<span class="jl-champ__nom">Utilisateur</span>
									<select name="user">
										<option value="">Tous</option>
										<option value="0">Visiteur non connecté</option>
<?php foreach ($utilisateurs as $u) { ?>
										<option value="<?=(int) $u['id']?>"><?=$h(($u['nom'] !== '' ? $u['nom'] : $u['pseudo']) . ' — ' . $u['pseudo'])?></option>
<?php } ?>
									</select>
								</label>
								<label class="jl-champ">
									<span class="jl-champ__nom">Du</span>
									<input type="date" name="du">
								</label>
								<label class="jl-champ">
									<span class="jl-champ__nom">Au</span>
									<input type="date" name="au">
								</label>
								<label class="jl-champ">
									<span class="jl-champ__nom">Table modifiée</span>
									<select name="table">
										<option value="">Toutes</option>
<?php foreach ($tables as $t => $libelle) { ?>
										<option value="<?=$h($t)?>"><?=$h($libelle === $t ? $t : $libelle . ' (' . $t . ')')?></option>
<?php } ?>
									</select>
								</label>
								<label class="jl-champ jl-champ--etroit">
									<span class="jl-champ__nom">IP</span>
									<input type="text" name="ip" inputmode="decimal" placeholder="102.16.">
								</label>
							</div>
							<div class="jl-filtres__ligne jl-filtres__ligne--types">
								<div class="jl-puces" role="group" aria-label="Types d'action">
									<button type="button" class="jl-puce" id="jlTout" aria-pressed="true">Tout</button>
<?php foreach ($types as $cle => $t) { ?>
									<button type="button" class="jl-puce jl-puce--<?=$h($cle)?>" data-type="<?=$h($cle)?>" aria-pressed="false">
										<i class="bi <?=$h($t['icone'])?>" aria-hidden="true"></i> <?=$h($t['libelle'])?>
									</button>
<?php } ?>
								</div>
								<button type="button" class="jl-lien" id="jlEffacer">Effacer les filtres</button>
							</div>
						</form>

						<?php /* Plus bas dans la liste, les nouvelles actions n'y font pas sauter
						         la lecture : cette bulle les annonce et y ramene. */ ?>
						<div class="jl-nouveau" id="jlNouveau" hidden>
							<button type="button" class="jl-nouveau__bulle" id="jlNouveauBouton"></button>
						</div>

						<p class="jl-resume" id="jlResume" aria-live="polite"></p>

<?php if (!$journalPret) { ?>
						<div class="jl-vide">
							<i class="bi bi-hourglass-split" aria-hidden="true"></i>
							<p><b>Le journal démarre à la première action enregistrée.</b></p>
							<p>Les tables sont créées automatiquement : rechargez la page dans un instant.</p>
						</div>
<?php } ?>

						<div class="jl-liste" id="jlListe"></div>

						<div class="jl-bas">
							<button type="button" class="jl-bouton" id="jlPlus" hidden>Charger les actions précédentes</button>
						</div>

						<details class="jl-note">
							<summary>Ce qui est enregistré, et ce qui ne l'est pas</summary>
							<ul>
								<li><b>Enregistré :</b> connexions (mot de passe, Google, « se souvenir de moi »), échecs de connexion, déconnexions,
									pages ouvertes, formulaires envoyés, exports PDF et Excel, et chaque écriture en base avec les valeurs avant et après.</li>
								<li><b>Masqué :</b> mots de passe, jetons et clés de sécurité, partout où ils apparaissent.</li>
								<li><b>Non enregistré :</b> les recherches et chargements automatiques qui ne modifient rien, les pages du site public
									vues par des visiteurs, et le journal lui-même.</li>
								<li>Pour une requête qui touche plus de 50 lignes, seules les 50 premières sont détaillées.</li>
							</ul>
						</details>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

	<div class="jl-voile" id="jlVoile" hidden></div>
	<aside class="jl-tiroir" id="jlTiroir" role="dialog" aria-modal="true" aria-labelledby="jlTiroirTitre" hidden>
		<header class="jl-tiroir__entete">
			<div>
				<p class="jl-tiroir__sur" id="jlTiroirSur"></p>
				<h2 class="jl-tiroir__titre" id="jlTiroirTitre">Détail de l'action</h2>
			</div>
			<button type="button" class="jl-fermer" id="jlFermer" aria-label="Fermer le détail"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
		</header>
		<div class="jl-tiroir__corps" id="jlDetail"></div>
	</aside>

	<script>
		window.JOURNAL = {
			types: <?=json_encode(array_map(fn($t) => $t['libelle'], $types), JSON_UNESCAPED_UNICODE)?>,
			icones: <?=json_encode(array_map(fn($t) => $t['icone'], $types), JSON_UNESCAPED_UNICODE)?>,
			pret: <?=$journalPret ? 'true' : 'false'?>
		};
	</script>
	<script src="<?=$app_base?>/src/js/settings.logs.js?v=<?=@filemtime(__DIR__ . '/js/settings.logs.js')?>"></script>
</body>
</html>
