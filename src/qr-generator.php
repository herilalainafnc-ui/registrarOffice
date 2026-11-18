<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Générateur de QR Code</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/qr-generator.css?v=<?=@filemtime(__DIR__ . '/css/qr-generator.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Generateur de QR Code (Application)

   QR Codes personnalises (couleurs, logo, cadre avec texte) pour les badges,
   affiches et liens, exportes en PNG, JPG ou SVG. Tout se passe dans le
   navigateur, avec qr-code-styling (copie locale : src/js/qr-code-styling.min.js) :
   ni le contenu ni le logo ne sont envoyes au serveur.

   Acces : le personnel, comme le reste de la section « Application » du menu
   (DashboardController::qrGenerator). Script : src/js/qr-generator.js.
   ========================================================================== */

/* Couleurs proposees en un clic : celles du sceau (src/css/tokens.css). */
$qgNuancier = [
	'#222e65' => 'Bleu Zurcher',
	'#88724b' => 'Or Zurcher',
	'#000000' => 'Noir',
];
$qgPastilles = function (string $cible) use ($qgNuancier) {
	foreach ($qgNuancier as $hex => $nom) {
		echo '<button type="button" class="qg-pastille" data-cible="' . $cible . '" data-couleur="' . $hex . '"'
			. ' style="--qg-pastille: ' . $hex . '" title="' . $nom . '" aria-label="' . $nom . '"></button>';
	}
};
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<div class="qg-page">

						<header class="qg-entete">
							<div class="qg-entete__icone" aria-hidden="true"><i class="bi bi-qr-code"></i></div>
							<div>
								<h1 class="qg-titre">Générateur de QR Code</h1>
								<p class="qg-sous">Un lien ou un texte en QR Code à vos couleurs, avec logo et cadre, pour les badges et les affiches.</p>
							</div>
						</header>

						<div class="qg-grille">

							<!-- ============ RÉGLAGES ============ -->
							<div class="qg-reglages">

								<section class="qg-section" aria-labelledby="qgT1">
									<h2 class="qg-section__titre" id="qgT1">Contenu</h2>
									<label class="qg-champ" for="qgDonnees">
										<span class="qg-libelle">Lien, texte, adresse e-mail ou numéro de téléphone</span>
										<textarea id="qgDonnees" rows="3" spellcheck="false" placeholder="https://www.zurcher.edu.mg">https://www.zurcher.edu.mg</textarea>
									</label>
								</section>

								<section class="qg-section" aria-labelledby="qgT2">
									<h2 class="qg-section__titre" id="qgT2">Couleurs</h2>
									<div class="qg-rangee">
										<div class="qg-champ">
											<label class="qg-libelle" for="qgPoints">Points</label>
											<div class="qg-couleur">
												<input type="color" id="qgPoints" value="#222e65">
												<output for="qgPoints" data-hex="qgPoints">#222e65</output>
												<span class="qg-pastilles"><?php $qgPastilles('qgPoints'); ?></span>
											</div>
										</div>
										<div class="qg-champ">
											<label class="qg-libelle" for="qgFond">Fond</label>
											<div class="qg-couleur">
												<input type="color" id="qgFond" value="#ffffff">
												<output for="qgFond" data-hex="qgFond">#ffffff</output>
											</div>
										</div>
									</div>
									<label class="qg-interrupteur" for="qgTransparent">
										<input type="checkbox" role="switch" id="qgTransparent">
										<span>Fond transparent</span>
									</label>
									<label class="qg-interrupteur" for="qgDegrade">
										<input type="checkbox" role="switch" id="qgDegrade" aria-controls="qgDegradeBloc">
										<span>Dégradé sur les points</span>
									</label>
									<div class="qg-sous-bloc" id="qgDegradeBloc" hidden>
										<div class="qg-rangee">
											<div class="qg-champ">
												<label class="qg-libelle" for="qgPoints2">Seconde couleur</label>
												<div class="qg-couleur">
													<input type="color" id="qgPoints2" value="#88724b">
													<output for="qgPoints2" data-hex="qgPoints2">#88724b</output>
												</div>
											</div>
											<label class="qg-champ" for="qgDegradeType">
												<span class="qg-libelle">Type</span>
												<select id="qgDegradeType">
													<option value="linear">Linéaire</option>
													<option value="radial">Radial</option>
												</select>
											</label>
										</div>
										<div class="qg-champ">
											<label class="qg-libelle" for="qgRotation">Orientation <output for="qgRotation" id="qgRotationV">0°</output></label>
											<input type="range" id="qgRotation" min="0" max="360" step="5" value="0">
										</div>
									</div>
								</section>

								<section class="qg-section" aria-labelledby="qgT3">
									<h2 class="qg-section__titre" id="qgT3">Forme</h2>
									<div class="qg-rangee qg-rangee--3">
										<label class="qg-champ" for="qgFormePoints">
											<span class="qg-libelle">Points</span>
											<select id="qgFormePoints">
												<option value="square">Carrés</option>
												<option value="rounded">Arrondis</option>
												<option value="dots" selected>Ronds</option>
												<option value="extra-rounded">Très arrondis</option>
												<option value="classy">Classy</option>
												<option value="classy-rounded">Classy arrondi</option>
											</select>
										</label>
										<label class="qg-champ" for="qgFormeCoins">
											<span class="qg-libelle">Cadre des coins</span>
											<select id="qgFormeCoins">
												<option value="square">Carré</option>
												<option value="extra-rounded" selected>Arrondi</option>
												<option value="dot">Rond</option>
											</select>
										</label>
										<label class="qg-champ" for="qgFormeCentres">
											<span class="qg-libelle">Centre des coins</span>
											<select id="qgFormeCentres">
												<option value="square">Carré</option>
												<option value="dot" selected>Rond</option>
											</select>
										</label>
									</div>
								</section>

								<section class="qg-section" aria-labelledby="qgT4">
									<h2 class="qg-section__titre" id="qgT4">Logo au centre</h2>
									<button type="button" class="qg-depot" id="qgLogoDepot" aria-describedby="qgLogoAide">
										<i class="bi bi-image" aria-hidden="true"></i>
										<span><b>Choisissez un logo</b> ou glissez-le ici</span>
										<small id="qgLogoAide">PNG, JPG, SVG ou WebP, 5 Mo au plus</small>
									</button>
									<input type="file" id="qgLogoFichier" accept="image/png,image/jpeg,image/svg+xml,image/webp" hidden>
									<p class="qg-erreur" id="qgLogoErreur" role="alert"></p>
									<div class="qg-sous-bloc" id="qgLogoBloc" hidden>
										<div class="qg-logo">
											<img id="qgLogoImage" alt="Logo choisi">
											<span class="qg-logo__nom" id="qgLogoNom"></span>
											<button type="button" class="qg-btn qg-btn--discret" id="qgLogoRetirer">
												<i class="bi bi-x-lg" aria-hidden="true"></i> Retirer
											</button>
										</div>
										<div class="qg-champ">
											<label class="qg-libelle" for="qgLogoTaille">Taille du logo <output for="qgLogoTaille" id="qgLogoTailleV">40 %</output></label>
											<input type="range" id="qgLogoTaille" min="15" max="60" value="40">
										</div>
										<label class="qg-interrupteur" for="qgLogoMasque">
											<input type="checkbox" role="switch" id="qgLogoMasque" checked>
											<span>Masquer les points derrière le logo</span>
										</label>
									</div>
								</section>

								<section class="qg-section" aria-labelledby="qgT5">
									<h2 class="qg-section__titre" id="qgT5">Cadre et texte</h2>
									<label class="qg-interrupteur" for="qgCadreActif">
										<input type="checkbox" role="switch" id="qgCadreActif" aria-controls="qgCadreBloc" checked>
										<span>Encadrer le QR Code et ajouter un texte</span>
									</label>
									<div class="qg-sous-bloc" id="qgCadreBloc">
										<label class="qg-champ" for="qgTexte">
											<span class="qg-libelle">Texte sous le QR Code</span>
											<input type="text" id="qgTexte" value="Scannez-moi" maxlength="26" autocomplete="off">
										</label>
										<div class="qg-rangee">
											<div class="qg-champ">
												<label class="qg-libelle" for="qgCadreCouleur">Cadre et bandeau</label>
												<div class="qg-couleur">
													<input type="color" id="qgCadreCouleur" value="#222e65">
													<output for="qgCadreCouleur" data-hex="qgCadreCouleur">#222e65</output>
													<span class="qg-pastilles"><?php $qgPastilles('qgCadreCouleur'); ?></span>
												</div>
											</div>
											<div class="qg-champ">
												<label class="qg-libelle" for="qgTexteCouleur">Texte</label>
												<div class="qg-couleur">
													<input type="color" id="qgTexteCouleur" value="#ffffff">
													<output for="qgTexteCouleur" data-hex="qgTexteCouleur">#ffffff</output>
												</div>
											</div>
										</div>
									</div>
								</section>

								<section class="qg-section" aria-labelledby="qgT6">
									<h2 class="qg-section__titre" id="qgT6">Export</h2>
									<div class="qg-champ">
										<label class="qg-libelle" for="qgTaille">Taille du QR Code <output for="qgTaille" id="qgTailleV">600 px</output></label>
										<input type="range" id="qgTaille" min="200" max="1600" step="20" value="600">
									</div>
									<label class="qg-champ" for="qgCorrection">
										<span class="qg-libelle">Correction d'erreur</span>
										<select id="qgCorrection">
											<option value="L">Faible (L) : le plus de contenu</option>
											<option value="M">Moyenne (M)</option>
											<option value="Q" selected>Élevée (Q) : conseillée avec un logo</option>
											<option value="H">Maximale (H)</option>
										</select>
									</label>
								</section>

							</div>

							<!-- ============ APERÇU ============ -->
							<aside class="qg-apercu" aria-labelledby="qgApercuTitre">
								<h2 class="qg-visuel-masque" id="qgApercuTitre">Aperçu</h2>
								<div class="qg-scene" id="qgScene">
									<div class="qg-cadre" id="qgCadre">
										<div class="qg-qr" id="qgQr" role="img" aria-label="Aperçu du QR Code"></div>
										<div class="qg-cta" id="qgCta"></div>
									</div>
									<p class="qg-vide" id="qgVide" hidden>
										<i class="bi bi-qr-code-scan" aria-hidden="true"></i>
										Saisissez un lien ou un texte pour voir le QR Code.
									</p>
								</div>

								<ul class="qg-alertes" id="qgAlertes" role="status" aria-live="polite"></ul>

								<label class="qg-champ qg-champ--nom" for="qgNom">
									<span class="qg-libelle">Nom du fichier</span>
									<input type="text" id="qgNom" value="qr-code" maxlength="120" autocomplete="off" spellcheck="false">
								</label>
								<div class="qg-export">
									<button type="button" class="qg-btn qg-btn--primaire" id="qgPng">
										<i class="bi bi-download" aria-hidden="true"></i> PNG
									</button>
									<button type="button" class="qg-btn qg-btn--second" id="qgSvg">
										<i class="bi bi-download" aria-hidden="true"></i> SVG
									</button>
									<button type="button" class="qg-btn qg-btn--second" id="qgJpg">
										<i class="bi bi-download" aria-hidden="true"></i> JPG
									</button>
								</div>
								<p class="qg-dimensions" id="qgDimensions"></p>
							</aside>
						</div>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

	<script src="<?=$app_base?>/src/js/qr-code-styling.min.js?v=<?=@filemtime(__DIR__ . '/js/qr-code-styling.min.js')?>"></script>
	<script src="<?=$app_base?>/src/js/qr-generator.js?v=<?=@filemtime(__DIR__ . '/js/qr-generator.js')?>"></script>
</body>
</html>
