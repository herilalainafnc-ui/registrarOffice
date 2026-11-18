<?php
/**
 * Resultat du controle de coherence d'une remise.
 *
 * Partage par la page enseignant et la page du registrariat : les deux doivent
 * lire exactement les memes reserves, sinon l'enseignant transmettrait une
 * feuille que le registraire refuse pour un motif qu'il n'avait pas vu.
 *
 * Attend $controle, tel que le renvoie remiseControler().
 */
if (!isset($controle)) { return; }
?>
<?php if ($controle['bloquants']): ?>
	<div class="bandeau bandeau-erreur mb-3">
		<i class="bi bi-x-octagon-fill"></i>
		<div>
			<b>A corriger avant de transmettre :</b>
			<ul class="mt-1 ml-4 list-disc">
				<?php foreach ($controle['bloquants'] as $b): ?>
					<li><?= htmlspecialchars($b) ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
<?php endif; ?>

<?php if ($controle['avertissements']): ?>
	<div class="bandeau bandeau-alerte mb-3">
		<i class="bi bi-exclamation-triangle-fill"></i>
		<div>
			<b>A verifier, sans empecher la transmission :</b>
			<ul class="mt-1 ml-4 list-disc">
				<?php foreach ($controle['avertissements'] as $a): ?>
					<li><?= htmlspecialchars($a) ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
<?php endif; ?>

<?php if (!$controle['bloquants'] && !$controle['avertissements']): ?>
	<div class="bandeau bandeau-succes">
		<i class="bi bi-check-circle-fill"></i>
		<div>
			Feuille coherente : <?= $controle['pretes'] ?> note(s) sur <?= $controle['total'] ?> pretes a etre reportees.
		</div>
	</div>
<?php else: ?>
	<p class="text-slate-400 text-xs">
		<?= $controle['pretes'] ?> note(s) sur <?= $controle['total'] ?> sont pretes a etre reportees.
	</p>
<?php endif; ?>
