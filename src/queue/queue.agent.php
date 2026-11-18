<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php
	// Vérification d'accès - niveau Registraire minimum
	requireLevel(ROLE_REGISTRAR, $app_base . '/etudiants');
	?>
	<title>File d'attente</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/queue.css?v=<?=@filemtime(__DIR__ . '/../css/queue.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   File d'attente — ecran agent (Application)

   Distribuer les tickets, appeler les numeros au guichet, et piloter l'ecran
   public (/queue/display) : annonces vocales et videos. Les donnees passent
   par /queue/api ; un seul appel (agent_state) rafraichit toute la page.

   Acces : QueueController (niveau 1). Script : src/js/queue.js.
   ========================================================================== */

// Mentions proposees pour un ticket
$fqMentions = [];
try {
	$fqMentions = $dtb->query("SELECT DISTINCT mention FROM t_mention WHERE etat = 1 ORDER BY mention ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
	$fqMentions = ['Communication', 'Éducation', 'Gestion', 'Informatique', 'Sciences Infirmières', 'Théologie'];
}

// Annonces toutes pretes, dites telles quelles par l'ecran public
$fqAnnonces = [
	'Problème technique' => "Votre attention s'il vous plaît. Un problème technique est en cours de résolution. Merci de votre patience.",
	'Patience'           => 'Merci de votre patience. Le service reprendra dans quelques instants.',
	'Suspension'         => "L'inscription est temporairement suspendue. Veuillez patienter.",
	'Documents'          => 'Votre attention. Veuillez préparer vos documents avant de vous présenter au guichet.',
	'Pause déjeuner'     => 'La pause déjeuner commence. Le service reprendra à 14 heures. Merci.',
];
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<div class="fq-page" id="fqPage" data-api="<?=$app_base?>/queue/api">

						<header class="fq-entete">
							<div class="fq-entete__icone" aria-hidden="true"><i class="bi bi-people-fill"></i></div>
							<div class="fq-entete__texte">
								<h1 class="fq-titre">File d'attente</h1>
								<p class="fq-sous">Tickets, appels au guichet et écran public des inscriptions.</p>
							</div>
							<?php /* Montre ou retire le plan du bureau sur l'ecran public (set_plan_affiche) */ ?>
							<button type="button" class="fq-btn fq-btn--second fq-plan" id="fqPlan" aria-pressed="false" disabled>
								<i class="bi bi-map" aria-hidden="true"></i> <span>Afficher le plan</span>
							</button>
							<a class="fq-btn fq-btn--discret" href="<?=$app_base?>/queue/plan" data-zone-ignorer>
								<i class="bi bi-pencil-square" aria-hidden="true"></i> Modifier le plan
							</a>
							<a class="fq-btn fq-btn--second" href="<?=$app_base?>/queue/display" target="_blank" rel="noopener" data-zone-ignorer>
								<i class="bi bi-display" aria-hidden="true"></i> Écran public
								<i class="bi bi-box-arrow-up-right fq-icone-petite" aria-hidden="true"></i>
							</a>
						</header>

						<!-- ============ SESSION ============ -->
						<section class="fq-session" id="fqSession" aria-labelledby="fqSessionNom" aria-busy="true">
							<div class="fq-session__info">
								<span class="fq-pastille-etat" id="fqEtat"><span aria-hidden="true"></span><b>Chargement…</b></span>
								<h2 class="fq-session__nom" id="fqSessionNom">File d'attente</h2>
								<p class="fq-session__meta" id="fqSessionMeta"></p>
							</div>
							<div class="fq-session__actions">
								<button type="button" class="fq-btn fq-btn--second" id="fqNouvelle" aria-controls="fqCreation" aria-expanded="false">
									<i class="bi bi-plus-lg" aria-hidden="true"></i> <span>Nouvelle session</span>
								</button>
								<button type="button" class="fq-btn fq-btn--danger" id="fqFermer" hidden>
									<i class="bi bi-door-closed" aria-hidden="true"></i> Fermer la session
								</button>
							</div>

							<form class="fq-creation" id="fqCreation" method="post" data-zone-ignorer hidden>
								<label class="fq-champ fq-creation__nom" for="fqNomSession">
									<span class="fq-libelle">Nom de la session</span>
									<input type="text" id="fqNomSession" maxlength="100" autocomplete="off" placeholder="Ex. : Réinscription premier semestre 2026-2027" required>
								</label>
								<label class="fq-champ" for="fqDateSession">
									<span class="fq-libelle">Date</span>
									<input type="date" id="fqDateSession" value="<?=date('Y-m-d')?>" required>
								</label>
								<div class="fq-creation__boutons">
									<button type="submit" class="fq-btn fq-btn--primaire"><i class="bi bi-check-lg" aria-hidden="true"></i> Ouvrir la session</button>
									<button type="button" class="fq-btn fq-btn--discret" id="fqAnnulerCreation">Annuler</button>
								</div>
								<p class="fq-aide" id="fqCreationAide" hidden>
									<i class="bi bi-info-circle" aria-hidden="true"></i>
									La session en cours sera fermée : ses tickets en attente ne seront plus appelés.
								</p>
							</form>
						</section>

						<!-- ============ ESPACE DE TRAVAIL (session ouverte) ============ -->
						<div class="fq-grille" id="fqTravail" hidden>
							<div class="fq-gauche">

								<section class="fq-indicateurs" aria-label="Indicateurs de la session">
									<div class="fq-indicateur fq-indicateur--attente">
										<span class="fq-indicateur__libelle">En attente</span>
										<b class="fq-indicateur__valeur" id="fqIndAttente">0</b>
										<span class="fq-indicateur__detail" id="fqIndAttenteDetail"></span>
									</div>
									<div class="fq-indicateur fq-indicateur--appel">
										<span class="fq-indicateur__libelle">Appelés</span>
										<b class="fq-indicateur__valeur" id="fqIndAppeles">0</b>
										<span class="fq-indicateur__detail" id="fqIndAppelesDetail"></span>
									</div>
									<div class="fq-indicateur fq-indicateur--servi">
										<span class="fq-indicateur__libelle">Servis</span>
										<b class="fq-indicateur__valeur" id="fqIndServis">0</b>
										<span class="fq-indicateur__detail" id="fqIndServisDetail"></span>
									</div>
									<div class="fq-indicateur">
										<span class="fq-indicateur__libelle">Attente moyenne</span>
										<b class="fq-indicateur__valeur" id="fqIndMoyenne">—</b>
										<span class="fq-indicateur__detail">sur la dernière heure</span>
									</div>
								</section>

								<!-- Appel -->
								<section class="fq-carte fq-appel" aria-labelledby="fqAppelTitre">
									<div class="fq-carte__entete">
										<h2 class="fq-carte__titre" id="fqAppelTitre"><i class="bi bi-megaphone" aria-hidden="true"></i> Appel</h2>
										<span class="fq-prochain" id="fqProchain"></span>
									</div>
									<div class="fq-appel__commande">
										<div class="fq-pas" role="group" aria-label="Nombre de numéros à appeler">
											<button type="button" class="fq-pas__btn" id="fqMoins" aria-label="Un numéro de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
											<input type="number" id="fqNombre" min="1" max="10" value="1" inputmode="numeric" aria-label="Nombre de numéros à appeler">
											<button type="button" class="fq-pas__btn" id="fqPlus" aria-label="Un numéro de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
										</div>
										<button type="button" class="fq-btn fq-btn--primaire fq-btn--grand" id="fqAppeler">
											<i class="bi bi-megaphone-fill" aria-hidden="true"></i> <span id="fqAppelerTexte">Appeler le suivant</span>
										</button>
									</div>
									<div class="fq-appeles">
										<div class="fq-appeles__entete">
											<h3 class="fq-sous-titre">Appelés en ce moment</h3>
											<button type="button" class="fq-btn fq-btn--discret" id="fqRappelerTous" hidden>
												<i class="bi bi-arrow-repeat" aria-hidden="true"></i> Rappeler tous
											</button>
										</div>
										<ul class="fq-appeles__liste" id="fqAppeles"></ul>
										<p class="fq-vide-court" id="fqAucunAppel">Aucun numéro appelé pour l'instant.</p>
									</div>
								</section>

								<!-- Nouveau ticket -->
								<section class="fq-carte fq-nouveau" aria-labelledby="fqNouveauTitre">
									<h2 class="fq-carte__titre" id="fqNouveauTitre"><i class="bi bi-ticket-perforated" aria-hidden="true"></i> Nouveau ticket</h2>
									<form class="fq-nouveau__form" id="fqTicketForm" method="post" data-zone-ignorer>
										<label class="fq-champ" for="fqTicketNom">
											<span class="fq-libelle">Nom <small>facultatif</small></span>
											<input type="text" id="fqTicketNom" maxlength="100" autocomplete="off" spellcheck="false">
										</label>
										<label class="fq-champ" for="fqTicketMention">
											<span class="fq-libelle">Mention <small>facultatif</small></span>
											<select id="fqTicketMention">
												<option value="">Aucune</option>
												<?php foreach ($fqMentions as $m): ?>
												<option value="<?=htmlspecialchars($m)?>"><?=htmlspecialchars($m)?></option>
												<?php endforeach; ?>
											</select>
										</label>
										<button type="submit" class="fq-btn fq-btn--primaire" id="fqCreer">
											<i class="bi bi-plus-circle" aria-hidden="true"></i> Créer le ticket
										</button>
									</form>
									<div class="fq-dernier" id="fqDernier" role="status" aria-live="polite" hidden>
										<span class="fq-libelle">Ticket créé</span>
										<b class="fq-num fq-num--geant" id="fqDernierNum"></b>
										<span class="fq-dernier__qui" id="fqDernierQui"></span>
									</div>
								</section>

								<!-- Annonce vocale -->
								<section class="fq-carte fq-annonce" aria-labelledby="fqAnnonceTitre">
									<h2 class="fq-carte__titre" id="fqAnnonceTitre"><i class="bi bi-chat-square-quote" aria-hidden="true"></i> Annonce vocale</h2>
									<p class="fq-aide">L'écran public la lit à voix haute, après un carillon.</p>
									<div class="fq-modeles" role="group" aria-label="Annonces toutes prêtes">
										<?php foreach ($fqAnnonces as $titre => $texte): ?>
										<button type="button" class="fq-modele" data-texte="<?=htmlspecialchars($texte)?>"><?=htmlspecialchars($titre)?></button>
										<?php endforeach; ?>
									</div>
									<label class="fq-champ" for="fqAnnonceTexte">
										<span class="fq-libelle">Message <output id="fqAnnonceCompte" for="fqAnnonceTexte">0 / 500</output></span>
										<textarea id="fqAnnonceTexte" rows="3" maxlength="500"></textarea>
									</label>
									<div class="fq-rangee-boutons">
										<button type="button" class="fq-btn fq-btn--primaire" id="fqDiffuser"><i class="bi bi-broadcast" aria-hidden="true"></i> Diffuser</button>
										<button type="button" class="fq-btn fq-btn--second" id="fqEcouter"><i class="bi bi-volume-up" aria-hidden="true"></i> Écouter ici</button>
										<button type="button" class="fq-btn fq-btn--discret" id="fqEffacerAnnonce"><i class="bi bi-x-lg" aria-hidden="true"></i> Effacer</button>
									</div>
									<p class="fq-etat-ligne" id="fqAnnonceEtat">Aucune annonce diffusée.</p>
								</section>

								<!-- Videos de l'ecran public -->
								<section class="fq-carte fq-videos" aria-labelledby="fqVideosTitre">
									<div class="fq-carte__entete">
										<h2 class="fq-carte__titre" id="fqVideosTitre"><i class="bi bi-collection-play" aria-hidden="true"></i> Vidéos</h2>
										<div class="fq-carte__outils">
											<button type="button" class="fq-icone" id="fqTitres" title="Mettre à jour les titres" aria-label="Mettre à jour les titres des vidéos"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
											<button type="button" class="fq-icone fq-icone--danger" id="fqVider" title="Vider la liste" aria-label="Vider la liste de lecture"><i class="bi bi-trash3" aria-hidden="true"></i></button>
										</div>
									</div>
									<form class="fq-ajout" id="fqAjoutForm" method="post" data-zone-ignorer>
										<label class="fq-visuel-masque" for="fqAjoutUrl">Lien YouTube</label>
										<input type="url" id="fqAjoutUrl" placeholder="Lien YouTube (vidéo ou Shorts)" autocomplete="off">
										<button type="submit" class="fq-btn fq-btn--second"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</button>
									</form>
									<ol class="fq-videos__liste" id="fqVideos"></ol>
									<p class="fq-vide-court" id="fqAucuneVideo">Aucune vidéo : ajoutez un lien YouTube.</p>
									<div class="fq-lecteur">
										<div class="fq-lecteur__boutons">
											<button type="button" class="fq-icone" id="fqPrec" aria-label="Vidéo précédente"><i class="bi bi-skip-start-fill" aria-hidden="true"></i></button>
											<button type="button" class="fq-btn fq-btn--second" id="fqLecture"><i class="bi bi-play-fill" aria-hidden="true"></i> <span>Lecture</span></button>
											<button type="button" class="fq-icone" id="fqSuiv" aria-label="Vidéo suivante"><i class="bi bi-skip-end-fill" aria-hidden="true"></i></button>
										</div>
										<label class="fq-volume" for="fqVolume">
											<i class="bi bi-volume-down" aria-hidden="true"></i>
											<span class="fq-visuel-masque">Volume</span>
											<input type="range" id="fqVolume" min="0" max="100" value="50">
											<output id="fqVolumeV" for="fqVolume">50 %</output>
										</label>
									</div>
									<label class="fq-interrupteur" for="fqPleinEcran">
										<input type="checkbox" role="switch" id="fqPleinEcran">
										<span>Vidéo en plein écran sur l'écran public</span>
									</label>
									<p class="fq-etat-ligne" id="fqVideoEtat"></p>
								</section>
							</div>

							<!-- Tickets -->
							<div class="fq-droite">
								<section class="fq-carte fq-tickets" aria-labelledby="fqTicketsTitre">
									<div class="fq-carte__entete">
										<h2 class="fq-carte__titre" id="fqTicketsTitre"><i class="bi bi-list-ol" aria-hidden="true"></i> Tickets</h2>
										<span class="fq-total" id="fqTotal"></span>
									</div>
									<div class="fq-onglets" role="tablist" aria-label="Filtrer les tickets par état" id="fqOnglets">
										<button type="button" role="tab" data-filtre="waiting" aria-selected="true">En attente <span data-compte="waiting">0</span></button>
										<button type="button" role="tab" data-filtre="called" aria-selected="false">Appelés <span data-compte="called">0</span></button>
										<button type="button" role="tab" data-filtre="serving" aria-selected="false">Au guichet <span data-compte="serving">0</span></button>
										<button type="button" role="tab" data-filtre="done" aria-selected="false">Terminés <span data-compte="done">0</span></button>
										<button type="button" role="tab" data-filtre="skipped" aria-selected="false">Passés <span data-compte="skipped">0</span></button>
										<button type="button" role="tab" data-filtre="absent" aria-selected="false">Absents <span data-compte="absent">0</span></button>
										<button type="button" role="tab" data-filtre="all" aria-selected="false">Tous <span data-compte="total">0</span></button>
									</div>
									<label class="fq-recherche" for="fqRecherche">
										<i class="bi bi-search" aria-hidden="true"></i>
										<span class="fq-visuel-masque">Chercher un ticket</span>
										<input type="search" id="fqRecherche" placeholder="Numéro ou nom" autocomplete="off" spellcheck="false">
									</label>
									<ul class="fq-liste" id="fqListe" role="tabpanel" aria-labelledby="fqTicketsTitre"></ul>
									<p class="fq-vide-court" id="fqListeVide" hidden></p>
									<p class="fq-aide" id="fqListeTronque" hidden>Seuls les 150 premiers sont affichés : cherchez un numéro ou un nom.</p>
								</section>
							</div>
						</div>

						<!-- ============ SESSIONS PRECEDENTES ============ -->
						<details class="fq-carte fq-historique" id="fqHistorique">
							<summary class="fq-historique__titre">
								<i class="bi bi-clock-history" aria-hidden="true"></i> Sessions précédentes
								<i class="bi bi-chevron-down fq-historique__chevron" aria-hidden="true"></i>
							</summary>
							<p class="fq-aide">Rouvrir une session ferme celle en cours. Supprimer efface aussi ses tickets.</p>
							<ul class="fq-historique__liste" id="fqHistoriqueListe"></ul>
						</details>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

	<script src="<?=$app_base?>/src/js/queue.js?v=<?=@filemtime(__DIR__ . '/../js/queue.js')?>"></script>
</body>
</html>
