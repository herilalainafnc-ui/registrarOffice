<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // classement de toute l'université : réservé au personnel
/*
 * Choix de l'année universitaire des classements (Dean's List, Top Students),
 * entre l'en-tête et la zone qui défile.
 *
 * Les flèches sont de vrais liens et la liste un formulaire GET : sans
 * JavaScript, tout marche en rechargeant la page. Avec, src/js/annees-ajax.js
 * change d'année sans recharger et tient la barre à jour.
 *
 * Attend $choixAnnees (voir src/student/annees-classement.php).
 */
$listeAnnees = $choixAnnees['annees'];
$pageAnnee = $choixAnnees['page'];
$nbAnnees = count($listeAnnees);
if ($nbAnnees > 1):
	$libelleAnnee = function ($n) use ($listeAnnees) {
		return htmlspecialchars((string) $listeAnnees[$n - 1], ENT_QUOTES, 'UTF-8');
	};
	/* Une flèche sans année au-delà reste en place, éteinte et hors du
	   parcours au clavier (un lien sans href n'est pas focalisable). */
	$fleche = function ($sens, $cible, $icone, $titre) use ($nbAnnees, $libelleAnnee) {
		$attributs = ($cible >= 1 && $cible <= $nbAnnees)
			? 'href="?page_annee=' . $cible . '" data-page-annee="' . $cible . '" data-pt-skip="1" aria-label="' . $titre . ' : ' . $libelleAnnee($cible) . '"'
			: 'aria-disabled="true" aria-label="' . $titre . '"';
		return '<a class="annees-fleche annees-fleche--' . $sens . '" title="' . $titre . '" ' . $attributs . '><i class="bi ' . $icone . '" aria-hidden="true"></i></a>';
	};
?>
<nav class="annees-nav" aria-label="Année universitaire" data-annees-nav data-annees-total="<?= $nbAnnees ?>">
    <label for="annees-choix" class="annees-nav__titre">Année universitaire</label>
    <div class="annees-nav__commande">
        <?= $fleche('recente', $pageAnnee - 1, 'bi-chevron-left', 'Année plus récente') ?>
        <form class="annees-choix" method="get" action="" data-pt-skip="1">
            <select id="annees-choix" name="page_annee" data-annees-choix>
                <?php for ($n = 1; $n <= $nbAnnees; $n++): ?>
                <option value="<?= $n ?>"<?= $n === $pageAnnee ? ' selected' : '' ?>><?= $libelleAnnee($n) ?></option>
                <?php endfor; ?>
            </select>
            <noscript><button type="submit" class="annees-voir">Afficher</button></noscript>
        </form>
        <?= $fleche('ancienne', $pageAnnee + 1, 'bi-chevron-right', 'Année plus ancienne') ?>
    </div>
</nav>
<?php endif; ?>
