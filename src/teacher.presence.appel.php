<?php
/* L'appel d'une seance (src/teacher.presence.contenu.php) : entete, heures, liste des etudiants.
   Variables : $seance (ou null), $lignes, $bilans, $feuille, $e, $app_base.
   Inclus deux fois : pour la seance ouverte, et, quand aucune ne l'est, dans un <template id="prModeleAppel"> qui
   permet d'ouvrir une seance sans reseau (src/js/teacher-presence.js : la date et les heures y sont posees par le
   script, la liste des etudiants est celle du cours, sans pointage). */
?>
		<!-- Appel de la seance choisie -->
		<section class="pr-carte overflow-hidden" id="appel" aria-label="Appel">
<?php if (!$seance): ?>
			<div class="p-6 pr-sous text-center">Choisissez une séance, ou ouvrez-en une nouvelle.</div>
<?php else:
			$retardEns = presenceRetardEnseignant($seance);
			$sansPenalite = presenceSeanceSansPenalite($seance);
?>
			<div class="pr-entete flex-wrap">
				<span><i class="bi bi-list-check mr-1"></i>Appel du <span data-seance-date><?= $e(presenceDateLongue($seance['date_seance'])) ?></span></span>
				<span class="flex gap-2 flex-wrap">
					<button type="button" class="pr-bouton pr-secondaire" id="btnTousPresents"><i class="bi bi-check2-all"></i>Non pointés présents</button>
					<button type="button" class="pr-bouton pr-danger" id="btnSupprimer" title="Supprimer cette séance et ses pointages"><i class="bi bi-trash"></i></button>
				</span>
			</div>
			<form id="formEnseignant" class="px-4 py-3 flex flex-wrap items-end gap-3" style="border-bottom:1px solid rgb(var(--c-line))">
				<div>
					<label class="pr-label" for="sHeurePrevue">Début prévu</label>
					<input id="sHeurePrevue" name="heure_prevue" type="time" class="pr-champ" value="<?= $e(substr((string)$seance['heure_prevue'], 0, 5)) ?>">
				</div>
				<div>
					<label class="pr-label" for="sArrivee">Arrivée de l'enseignant</label>
					<input id="sArrivee" name="arrivee_enseignant" type="time" class="pr-champ" value="<?= $e(substr((string)$seance['arrivee_enseignant'], 0, 5)) ?>">
				</div>
				<label class="flex items-center gap-2 pr-sous" style="min-height:36px">
					<input type="checkbox" name="message_retard" value="1" <?= $seance['message_retard'] ? 'checked' : '' ?>> Retard annoncé aux étudiants
				</label>
				<p class="pr-sous" id="etatEnseignant" style="flex-basis:100%;margin:0">
					<?php if ($sansPenalite): ?>
						<i class="bi bi-hourglass-split" style="color:#fbbf24"></i>
						Arrivée <?= (int)$retardEns ?> min après l'heure, sans message : les étudiants pouvaient partir sans pénalité.
						Les absences et retards de cette séance ne comptent pas (<a href="<?= $e($app_base) ?>/reglement#p474" style="color:rgb(var(--c-accent-text))">§474</a>).
					<?php elseif ($retardEns !== null && $retardEns > 0): ?>
						Arrivée <?= (int)$retardEns ?> min après l'heure<?= $seance['message_retard'] ? ', retard annoncé' : '' ?>.
					<?php endif; ?>
				</p>
			</form>

			<div id="listeAppel">
<?php if (!$lignes): ?>
				<p class="p-6 pr-sous text-center">Aucun étudiant inscrit à ce cours pour ce semestre.</p>
<?php else: $n = 1; foreach ($lignes as $l):
			$categorie = presenceCategorie($l['statut'], $l['minutes_retard']);
			$b = $bilans[$l['student_id']] ?? presenceBilan([], $feuille['seances_prevues']);
			$info = presenceEtatInfo($b['etat']);
			$nom = trim(($l['student_nom'] ?? '') . ' ' . ($l['student_prenom'] ?? ''));
?>
				<div class="pr-appel <?= $categorie ? 'est-pointe-' . $categorie : '' ?>" data-student="<?= $e($l['student_id']) ?>">
					<span class="pr-sous pr-num"><?= $n++ ?></span>
					<div style="min-width:0">
						<div class="pr-appel__nom"><?= $nom !== '' ? $e($nom) : '<em>Nom non défini</em>' ?></div>
						<div class="pr-appel__mat"><?= $e($l['student_id']) ?></div>
					</div>
					<div class="pr-choix" role="group" aria-label="Présence de <?= $e($nom) ?>">
						<?php foreach (['present' => 'Présent', 'retard' => 'Retard', 'absent' => 'Absent'] as $st => $lib): ?>
							<button type="button" data-statut="<?= $st ?>" aria-pressed="<?= $l['statut'] === $st ? 'true' : 'false' ?>"><?= $lib ?></button>
						<?php endforeach; ?>
					</div>
					<label class="pr-minutes" <?= $l['statut'] === 'retard' ? '' : 'hidden' ?>>
						<input type="number" min="1" max="240" class="pr-champ js-minutes" value="<?= $l['statut'] === 'retard' ? (int)$l['minutes_retard'] : PRESENCE_RETARD_TOLERE ?>" aria-label="Minutes de retard"> min
					</label>
					<div class="flex items-center gap-2 flex-wrap">
						<select class="pr-champ pr-justif js-justif" aria-label="Justificatif présenté" title="Justificatif présenté au retour (§476) : il n'excuse pas l'absence, un certificat médical permet de refaire tests et examens">
							<option value="">Sans justificatif</option>
							<option value="medical" <?= $l['justificatif'] === 'medical' ? 'selected' : '' ?>>Certificat médical</option>
							<option value="autre" <?= $l['justificatif'] === 'autre' ? 'selected' : '' ?>>Autre justificatif</option>
						</select>
						<span class="pr-bilan-mini js-bilan" style="color:<?= $info['couleur'] ?>" title="<?= $e($info['libelle']) ?>">
							<?= (int)$b['comptees'] ?><?= $b['permises'] !== null ? ' / ' . (int)$b['permises'] : '' ?> abs.
						</span>
					</div>
				</div>
<?php endforeach; endif; ?>
			</div>
<?php endif; ?>
		</section>
