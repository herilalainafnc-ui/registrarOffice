<?php
/* =============================================================================
   Partie dynamique de la barre de session — fragment partage.

   Sort du corps de la page pour etre reutilise a l'identique par
   app/finance-bareme.grille.php, qui la renvoie lors d'un changement d'annee
   ou de semestre. Le selecteur d'annee lui-meme reste dans la page : sa liste
   ne change pas, et le remplacer ferait perdre le focus au clavier.

   Attend, fournis par l'appelant :
     $anneeChoisie      annee scolaire affichee
     $sessionsAnnee     sessions de l'annee, indexees par semestre
     $semestreChoisi    numero de semestre
     $sessionCourante   ligne du catalogue, ou null
     $editeBase         true si l'on edite le bareme de base
     $sessionId         identifiant de la session affichee
     $mentionsAjusteesN nombre de filieres ayant un tarif propre
     $nbMentions        nombre total de filieres
   ========================================================================== */
?>
<div class="bareme-onglets" role="tablist">
	<?php foreach ($sessionsAnnee as $sem => $s) { ?>
		<a class="bareme-onglet js-bareme-onglet" role="tab"
		   data-pt-skip="1"
		   data-annee="<?= e($anneeChoisie) ?>"
		   data-semestre="<?= (int) $sem ?>"
		   data-base="0"
		   aria-selected="<?= (!$editeBase && (int) $sem === $semestreChoisi) ? 'true' : 'false' ?>"
		   href="<?= e(baremeLien($anneeChoisie, (int) $sem)) ?>"><?= e($s['session_name']) ?></a>
	<?php } ?>
	<a class="bareme-onglet js-bareme-onglet" role="tab"
	   data-pt-skip="1"
	   data-annee="<?= e($anneeChoisie) ?>"
	   data-semestre="<?= (int) $semestreChoisi ?>"
	   data-base="1"
	   aria-selected="<?= $editeBase ? 'true' : 'false' ?>"
	   href="<?= e(baremeLien($anneeChoisie, $semestreChoisi, true)) ?>"
	   title="Tarifs appliqués à toute session qui n'a pas de barème propre">Barème de base</a>
</div>

<div class="ml-auto flex items-center gap-2">
	<?php if ($editeBase) { ?>
		<span class="bareme-etat bareme-etat-base">
			<i class="bi bi-diagram-3"></i> Sert de repli à toutes les sessions
		</span>
	<?php } elseif ($sessionCourante) { ?>
		<span class="bareme-etat <?= $mentionsAjusteesN > 0 ? 'bareme-etat-propre' : 'bareme-etat-base' ?>">
			<i class="bi bi-<?= $mentionsAjusteesN > 0 ? 'check-circle-fill' : 'arrow-down-left' ?>"></i>
			<?= $mentionsAjusteesN > 0
				? $mentionsAjusteesN . ' / ' . $nbMentions . ' filière(s) ajustée(s)'
				: 'Aucun ajustement — hérite du barème de base' ?>
		</span>
		<span class="text-slate-500" style="font-size:.7rem">session <?= (int) $sessionId ?></span>
	<?php } ?>
</div>
