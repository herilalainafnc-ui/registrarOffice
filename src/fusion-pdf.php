<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Fusion de PDF</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/fusion-pdf.css?v=<?=@filemtime(__DIR__ . '/css/fusion-pdf.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Fusion de PDF (Application)

   Combine plusieurs PDF en un seul, dans l'ordre choisi. Tout se passe dans le
   navigateur, avec pdf-lib : aucun fichier n'est envoye au serveur, ce qui
   compte pour des releves de notes ou des pieces d'identite d'etudiants.

   Acces : le personnel, comme le reste de la section « Application » du menu
   (DashboardController::fusionPdf). Script : src/js/fusion-pdf.js.
   ========================================================================== */
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<div class="fp-page">

						<header class="fp-entete">
							<div class="fp-entete__icone" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></div>
							<div>
								<h1 class="fp-titre">Fusion de PDF</h1>
								<p class="fp-sous">Combinez plusieurs fichiers PDF en un seul, dans l'ordre de votre choix.</p>
							</div>
						</header>

						<p class="fp-confidentiel">
							<i class="bi bi-shield-lock" aria-hidden="true"></i>
							Tout se fait sur cet ordinateur : aucun fichier n'est envoyé au serveur.
						</p>

						<button type="button" class="fp-depot" id="fpDepot" aria-describedby="fpDepotAide">
							<i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
							<span class="fp-depot__titre"><b>Choisissez vos PDF</b> ou glissez-les ici</span>
							<span class="fp-depot__aide" id="fpDepotAide">Plusieurs fichiers à la fois ; vous pourrez ensuite changer leur ordre.</span>
						</button>
						<input type="file" id="fpFichier" accept="application/pdf,.pdf" multiple hidden>

						<section class="fp-liste-bloc" id="fpBloc" aria-labelledby="fpListeTitre" hidden>
							<div class="fp-liste-entete">
								<h2 id="fpListeTitre">Ordre de fusion</h2>
								<span class="fp-total" id="fpTotal"></span>
							</div>
							<p class="fp-astuce">Glissez une ligne, ou utilisez les flèches, pour changer l'ordre.</p>
							<ol class="fp-liste" id="fpListe"></ol>

							<div class="fp-actions">
								<label class="fp-nom">
									<span>Nom du fichier</span>
									<input type="text" id="fpNom" value="fusion.pdf" maxlength="120" autocomplete="off" spellcheck="false">
								</label>
								<div class="fp-actions__boutons">
									<button type="button" class="fp-btn fp-btn--second" id="fpVider">
										<i class="bi bi-trash3" aria-hidden="true"></i> Tout retirer
									</button>
									<button type="button" class="fp-btn fp-btn--primaire" id="fpFusionner">
										<i class="bi bi-files" aria-hidden="true"></i> Fusionner et télécharger
									</button>
								</div>
							</div>
						</section>

						<p class="fp-etat" id="fpEtat" role="status" aria-live="polite"></p>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

	<?php /* pdf-lib 1.17.1 depuis cdnjs, comme les autres bibliotheques du projet
	         (jsPDF, ExcelJS…) ; l'empreinte est celle que publie cdnjs : un
	         fichier modifie serait refuse par le navigateur. */ ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"
	        integrity="sha512-z8IYLHO8bTgFqj+yrPyIJnzBDf7DDhWwiEsk4sY+Oe6J2M+WQequeGS7qioI5vT6rXgVRb4K1UVQC5ER7MKzKQ=="
	        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="<?=$app_base?>/src/js/fusion-pdf.js?v=<?=@filemtime(__DIR__ . '/js/fusion-pdf.js')?>"></script>
</body>
</html>
