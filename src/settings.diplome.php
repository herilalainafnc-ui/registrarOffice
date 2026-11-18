<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Paramètres — Diplôme</title>
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
<?php
	require_once(__DIR__ . '/../data/diplome.config.php');
	$cfg = diplomeConfig($dtb);
	$sigDir = $app_base . '/file/signatures/';

	$flash = '';
	if (isset($_GET['ok'])) { $flash = 'Paramètres du diplôme enregistrés.'; }
	$flashErr = isset($_GET['error']) ? (string)$_GET['error'] : '';
?>
					<div class="p-4 max-w-5xl">

						<div class="flex items-center gap-2 mb-4">
							<a href="<?=$app_base?>/settings" class="text-slate-400 hover:text-cyan-400"><i class="bi-arrow-left"></i></a>
							<h1 class="text-xl font-semibold text-white m-0"><i class="bi-award-fill"></i> Paramètres du diplôme</h1>
						</div>

						<p class="text-slate-400 mb-4">
							Ces réglages s'appliquent au <b>nouveau modèle</b> (étudiants gradués à partir de 2025).
							L'ancien modèle n'est pas affecté.
						</p>

						<?php if ($flash || $flashErr): ?>
						<!-- Retour d'enregistrement en toast : le bandeau repoussait
						     le formulaire vers le bas a chaque envoi, et le champ
						     qu'on venait de corriger sortait de l'ecran. -->
						<script>
						document.addEventListener('DOMContentLoaded', function () {
							if (typeof Toast === 'undefined') { return; }
<?php if ($flash): ?>
							Toast.success(<?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
<?php endif; ?>
<?php if ($flashErr): ?>
							Toast.error(<?= json_encode($flashErr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, { duration: 8000 });
<?php endif; ?>
						});
						</script>
						<?php endif; ?>

						<form method="post" action="<?=$app_base?>/app/.settings/save.diplome.config.php" enctype="multipart/form-data">
							<?= csrf_field() ?>

							<!-- ============ SIGNATAIRES ============ -->
							<div data-tuto='diplome-signataires' class='p-4 <?=$bg_one_color?> rounded-md border-2 border-slate-800 mb-4'>
								<b class="text-white text-base"><i class="bi-pen-fill"></i>&nbsp; Signataires</b>
								<p class="text-slate-500 mb-3">Le titre, le nom et l'image de signature apparaissent au bas du diplôme. Laisser un champ image vide conserve la signature actuelle.</p>

								<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

									<!-- Recteur -->
									<div class="<?=$bg_two_color?> rounded-md p-3 border <?=$br_three_color?>">
										<p class="text-[11px] uppercase tracking-wide text-slate-400 mb-2">Signataire 1</p>

										<label class="block text-slate-300 mb-1">Titre</label>
										<input type="text" name="diplome_recteur_titre" value="<?=htmlspecialchars($cfg['diplome_recteur_titre'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full mb-3 rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">

										<label class="block text-slate-300 mb-1">Nom</label>
										<input type="text" name="diplome_recteur_nom" value="<?=htmlspecialchars($cfg['diplome_recteur_nom'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full mb-3 rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">

										<label class="block text-slate-300 mb-1">Signature (PNG à fond transparent conseillé)</label>
										<?php if (!empty($cfg['diplome_recteur_signature'])): ?>
											<div class="bg-white rounded p-2 mb-2 inline-block">
												<img src="<?=$sigDir.rawurlencode($cfg['diplome_recteur_signature'])?>?v=<?=time()?>" alt="Signature" style="height:38px;">
											</div>
										<?php endif; ?>
										<input type="file" name="signature_recteur" accept="image/png,image/jpeg,image/webp"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-300 file:mr-3 file:px-3 file:py-1 file:rounded file:border-0 file:bg-cyan-700 file:text-white">
									</div>

									<!-- President -->
									<div class="<?=$bg_two_color?> rounded-md p-3 border <?=$br_three_color?>">
										<p class="text-[11px] uppercase tracking-wide text-slate-400 mb-2">Signataire 2</p>

										<label class="block text-slate-300 mb-1">Titre</label>
										<input type="text" name="diplome_president_titre" value="<?=htmlspecialchars($cfg['diplome_president_titre'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full mb-3 rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">

										<label class="block text-slate-300 mb-1">Nom</label>
										<input type="text" name="diplome_president_nom" value="<?=htmlspecialchars($cfg['diplome_president_nom'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full mb-3 rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">

										<label class="block text-slate-300 mb-1">Signature (PNG à fond transparent conseillé)</label>
										<?php if (!empty($cfg['diplome_president_signature'])): ?>
											<div class="bg-white rounded p-2 mb-2 inline-block">
												<img src="<?=$sigDir.rawurlencode($cfg['diplome_president_signature'])?>?v=<?=time()?>" alt="Signature" style="height:38px;">
											</div>
										<?php endif; ?>
										<input type="file" name="signature_president" accept="image/png,image/jpeg,image/webp"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-300 file:mr-3 file:px-3 file:py-1 file:rounded file:border-0 file:bg-cyan-700 file:text-white">
									</div>
								</div>

								<div class="mt-4">
									<label class="block text-slate-300 mb-1">Libellé du 3<sup>e</sup> bloc (signature de l'étudiant)</label>
									<input type="text" name="diplome_etudiant_libelle" value="<?=htmlspecialchars($cfg['diplome_etudiant_libelle'], ENT_QUOTES, 'UTF-8')?>"
										class="w-full lg:w-1/2 rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
									<p class="text-[11px] text-slate-500 mt-1">Saisir la forme masculine : le « e » du féminin est ajouté automatiquement (L'étudiant → L'étudiante).</p>
								</div>
							</div>

							<!-- ============ MENTIONS OFFICIELLES ============ -->
							<div data-tuto='diplome-mentions' class='p-4 <?=$bg_one_color?> rounded-md border-2 border-slate-800 mb-4'>
								<b class="text-white text-base"><i class="bi-file-earmark-text-fill"></i>&nbsp; Mentions officielles</b>
								<p class="text-slate-500 mb-3">Arrêtés cités dans les considérants et lieu de remise.</p>

								<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
									<div>
										<label class="block text-slate-300 mb-1">Lieu de remise</label>
										<input type="text" name="diplome_lieu" value="<?=htmlspecialchars($cfg['diplome_lieu'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
									</div>
									<div>
										<label class="block text-slate-300 mb-1">Date de remise</label>
										<input type="text" name="diplome_date_remise" placeholder="02 août 2026" value="<?=htmlspecialchars($cfg['diplome_date_remise'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
										<p class="text-[11px] text-slate-500 mt-1">Laisser vide : la date de fin du semestre actif est utilisée automatiquement.</p>
									</div>
									<div>
										<label class="block text-slate-300 mb-1">Crédits requis — Licence</label>
										<input type="number" name="diplome_credits_licence" min="0" max="600" value="<?=htmlspecialchars($cfg['diplome_credits_licence'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
										<p class="text-[11px] text-slate-500 mt-1">Règlement §383 : 180 crédits.</p>
									</div>
									<div>
										<label class="block text-slate-300 mb-1">Crédits requis — Master</label>
										<input type="number" name="diplome_credits_master" min="0" max="600" value="<?=htmlspecialchars($cfg['diplome_credits_master'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
										<p class="text-[11px] text-slate-500 mt-1">À confirmer auprès du bureau académique.</p>
									</div>
									<div>
										<label class="block text-slate-300 mb-1">Nouveau modèle à partir de l'année</label>
										<input type="number" name="diplome_new_model_from_year" min="2000" max="2100" value="<?=htmlspecialchars($cfg['diplome_new_model_from_year'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
										<p class="text-[11px] text-slate-500 mt-1">Choix modifiable sur chaque diplôme.</p>
									</div>
								</div>

								<details class="mt-2">
									<summary class="cursor-pointer text-slate-400 hover:text-cyan-400">Valeurs de repli (si la filière n'est pas dans le tableau ci-dessous)</summary>
									<div class="mt-3">
										<label class="block text-slate-300 mb-1">Arrêté MESupRES par défaut</label>
										<input type="text" name="diplome_arrete_1" value="<?=htmlspecialchars($cfg['diplome_arrete_1'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full mb-3 rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
										<label class="block text-slate-300 mb-1">Arrêté CNEAT / FOP par défaut</label>
										<input type="text" name="diplome_arrete_2" value="<?=htmlspecialchars($cfg['diplome_arrete_2'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
									</div>
								</details>
							</div>

							<!-- ============ ARRETES PAR FILIERE ============ -->
							<div data-tuto='diplome-arretes' class='p-4 <?=$bg_one_color?> rounded-md border-2 border-slate-800 mb-4'>
								<b class="text-white text-base"><i class="bi-list-check"></i>&nbsp; Arrêtés par filière</b>
								<p class="text-slate-500 mb-3">
									Les arrêtés cités sur le diplôme dépendent de la filière et du niveau.
									L'acronyme sert à composer le numéro de série (année + acronyme filière + acronyme parcours + matricule).
								</p>
								<div class="overflow-x-auto">
									<table class="w-full text-left">
										<thead>
											<tr class="text-slate-400 border-b <?=$br_three_color?>">
												<th class="py-2 pr-2">Programme</th>
												<th class="py-2 pr-2 w-20">Acr.</th>
												<th class="py-2 pr-2">Arrêté MESupRES</th>
												<th class="py-2">Arrêté CNEAT</th>
											</tr>
										</thead>
										<tbody>
										<?php
											$arretes = [];
											try { $arretes = $dtb->query('SELECT * FROM diplome_arretes ORDER BY programme')->fetchAll(); } catch (Exception $ex) {}
											foreach ($arretes as $a):
										?>
											<tr class="border-b border-slate-800">
												<td class="py-1.5 pr-2 text-slate-200"><?=htmlspecialchars($a['programme'], ENT_QUOTES, 'UTF-8')?></td>
												<td class="py-1.5 pr-2">
													<input type="text" name="arretes[<?=(int)$a['id']?>][accronyme]" value="<?=htmlspecialchars((string)$a['accronyme'], ENT_QUOTES, 'UTF-8')?>"
														class="w-16 rounded border <?=$br_three_color?> <?=$bg_three_color?> px-2 py-1 text-slate-100">
												</td>
												<td class="py-1.5 pr-2">
													<input type="text" name="arretes[<?=(int)$a['id']?>][mesupres]" value="<?=htmlspecialchars((string)$a['arrete_mesupres'], ENT_QUOTES, 'UTF-8')?>"
														class="w-full rounded border <?=$br_three_color?> <?=$bg_three_color?> px-2 py-1 text-slate-100">
												</td>
												<td class="py-1.5">
													<input type="text" name="arretes[<?=(int)$a['id']?>][cneat]" value="<?=htmlspecialchars((string)$a['arrete_cneat'], ENT_QUOTES, 'UTF-8')?>"
														class="w-full rounded border <?=$br_three_color?> <?=$bg_three_color?> px-2 py-1 text-slate-100">
												</td>
											</tr>
										<?php endforeach; ?>
										<?php if (empty($arretes)): ?>
											<tr><td colspan="4" class="py-3 text-slate-500">Aucun arrêté enregistré.</td></tr>
										<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>

							<!-- ============ CALAGE ============ -->
							<div data-tuto='diplome-calage' class='p-4 <?=$bg_one_color?> rounded-md border-2 border-slate-800 mb-4'>
								<b class="text-white text-base"><i class="bi-arrows-move"></i>&nbsp; Calage d'impression</b>
								<p class="text-slate-500 mb-3">
									Le nouveau modèle s'imprime sur le papier pré-imprimé. Après une impression test, ajustez ici
									pour aligner le texte avec la matrice. Valeurs en millimètres, négatives autorisées
									(X positif = vers la droite, Y positif = vers le bas).
								</p>
								<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
									<div>
										<label class="block text-slate-300 mb-1">Décalage horizontal X (mm)</label>
										<input type="number" step="0.5" name="diplome_offset_x" value="<?=htmlspecialchars($cfg['diplome_offset_x'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
									</div>
									<div>
										<label class="block text-slate-300 mb-1">Décalage vertical Y (mm)</label>
										<input type="number" step="0.5" name="diplome_offset_y" value="<?=htmlspecialchars($cfg['diplome_offset_y'], ENT_QUOTES, 'UTF-8')?>"
											class="w-full rounded-lg border <?=$br_three_color?> <?=$bg_three_color?> px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
									</div>
								</div>
							</div>

							<div class="flex items-center gap-2 pb-6">
								<button type="submit" data-tuto="diplome-enregistrer" class="bg-cyan-700 hover:bg-cyan-600 text-white px-5 py-2 rounded-lg">
									<i class="bi-save"></i> Enregistrer
								</button>
								<a href="<?=$app_base?>/settings" class="px-5 py-2 rounded-lg border <?=$br_three_color?> <?=$bg_two_color?> text-slate-200 hover:bg-black/20">Annuler</a>
							</div>

						</form>
					</div>

					<?php require('../init/footer.php'); ?>

				</div>

			</div>

		</div>

	</div>
<script src="<?=$app_base?>/src/js/settings.diplome.js?v=20260915-1"></script>
</body>
</html>
