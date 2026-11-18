<?php
/**
 * Roadmap du cursus, une par mention.
 *
 * Le candidat voit d'un coup d'oeil comment se deroulent les trois annees :
 * les cours de chaque niveau, leurs credits, et le volume total. Les donnees
 * viennent du bulletin academique (data/cursus.php, genere) — pas de saisie
 * manuelle qui divergerait du texte officiel.
 *
 * Divulgation progressive : certains niveaux comptent plus de cinquante cours.
 * Les afficher tous d'emblee donnerait un mur illisible, alors que le visiteur
 * cherche d'abord a se faire une idee. Huit sont montres, le reste se deplie.
 */

$cursus = @include __DIR__ . '/../../data/cursus.php';
if (!is_array($cursus)) { $cursus = []; }

$libelles = [
    'theologie'            => 'Théologie',
    'gestion'              => 'Gestion',
    'informatique'         => 'Informatique',
    'sciences-infirmieres' => 'Sciences Infirmières',
    'communication'        => 'Communication',
    'etudes-anglophones'   => 'Études Anglophones',
    'droit'                => 'Droit',
];
$niveaux = [2 => 'Première année', 3 => 'Deuxième année', 4 => 'Troisième année'];
$courts  = [2 => 'L1', 3 => 'L2', 4 => 'L3'];

// On n'affiche que les mentions qui ont reellement un cursus renseigne.
$aAfficher = array_filter($libelles, fn($c) => !empty($cursus[$c]), ARRAY_FILTER_USE_KEY);
if ($aAfficher):
    $premiere = array_key_first($aAfficher);
?>
<section class="section" id="cursus">
    <div class="section-header">
        <span class="section-label">Cursus</span>
        <h2 class="section-title">Le Parcours, Année par Année</h2>
        <p class="section-subtitle">
            Choisissez une mention pour découvrir les cours de chaque niveau,
            leurs crédits et la progression sur les trois années de Licence.
        </p>
    </div>

    <!-- Selecteur de mention. Un vrai tablist : les fleches du clavier
         naviguent entre les onglets, comme l'attend un lecteur d'ecran. -->
    <div class="cursus-onglets" role="tablist" aria-label="Mentions">
<?php foreach ($aAfficher as $cle => $nom): $actif = ($cle === $premiere); ?>
        <button type="button" role="tab"
                id="onglet-<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"
                class="cursus-onglet<?= $actif ? ' est-actif' : '' ?>"
                aria-selected="<?= $actif ? 'true' : 'false' ?>"
                aria-controls="cursus-<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"
                tabindex="<?= $actif ? '0' : '-1' ?>"
                data-mention="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>
        </button>
<?php endforeach; ?>
    </div>

<?php foreach ($aAfficher as $cle => $nom):
        $par = $cursus[$cle];
        $totalCours = 0; $totalCredits = 0;
        foreach ($par as $lst) { $totalCours += count($lst); foreach ($lst as $c) { $totalCredits += (int)$c['credits']; } }
?>
    <div class="cursus-panneau<?= $cle === $premiere ? ' est-actif' : '' ?>"
         id="cursus-<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"
         role="tabpanel"
         aria-labelledby="onglet-<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"
         <?= $cle === $premiere ? '' : 'hidden' ?>>

        <!-- Le total des credits proposes (souvent 200 et plus) n'est PAS le
             nombre a valider : le bulletin liste les cours de tous les parcours
             d'une mention, alors qu'un etudiant n'en suit qu'une partie. Les
             afficher cote a cote sans le dire laisserait croire qu'il faut tout
             prendre. On annonce donc la condition de diplome separement. -->
        <p class="cursus-resume">
            <strong><?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?></strong> —
            <?= (int)$totalCours ?> cours proposés sur les trois années.
        </p>

        <p class="cursus-diplome">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            <span><strong>180 crédits</strong> à valider pour obtenir la Licence,
            soit environ 60 par année.</span>
        </p>

        <div class="cursus-voie">
<?php foreach ($niveaux as $n => $titreNiveau):
            $liste = $par[$n] ?? [];
            if (!$liste) { continue; }
            $credits = array_sum(array_column($liste, 'credits'));
            $visibles = 8;
?>
            <div class="cursus-etape">
                <div class="cursus-etape__marque" aria-hidden="true"><?= $courts[$n] ?></div>

                <div class="cursus-etape__corps">
                    <h3 class="cursus-etape__titre"><?= $titreNiveau ?></h3>
                    <p class="cursus-etape__chiffres">
                        <?= count($liste) ?> cours · <?= (int)$credits ?> crédits
                    </p>

                    <ul class="cursus-cours">
<?php foreach ($liste as $i => $c):
                        $aDesc = trim((string)($c['desc'] ?? '')) !== '';
                        $idC   = 'c-' . $cle . '-' . $n . '-' . $i;
?>
                        <li class="cursus-cours__item<?= $i >= $visibles ? ' est-replie' : '' ?><?= $aDesc ? ' a-detail' : '' ?>">
<?php if ($aDesc): ?>
                            <!-- Depliant seulement quand il y a quelque chose a lire.
                                 Un accordeon vide serait une promesse non tenue. -->
                            <button type="button" class="cursus-cours__tete"
                                    aria-expanded="false" aria-controls="<?= $idC ?>">
                                <span class="cursus-cours__sigle"><?= htmlspecialchars($c['sigle'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cursus-cours__titre"><?= htmlspecialchars($c['titre'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cursus-cours__credits"><?= (int)$c['credits'] ?> cr</span>
                                <svg class="cursus-cours__chevron" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <!-- Deux niveaux : le volet porte l'animation de
                                 hauteur, le contenu porte le rognage. Un seul
                                 element ne peut pas faire les deux. -->
                            <div class="cursus-cours__volet" id="<?= $idC ?>" hidden>
                                <div class="cursus-cours__detail">
                                    <?= nl2br(htmlspecialchars($c['desc'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
<?php else: ?>
                            <div class="cursus-cours__tete cursus-cours__tete--fixe">
                                <span class="cursus-cours__sigle"><?= htmlspecialchars($c['sigle'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cursus-cours__titre"><?= htmlspecialchars($c['titre'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cursus-cours__credits"><?= (int)$c['credits'] ?> cr</span>
                            </div>
<?php endif; ?>
                        </li>
<?php endforeach; ?>
                    </ul>

<?php if (count($liste) > $visibles): ?>
                    <button type="button" class="cursus-plus"
                            aria-expanded="false"
                            data-plus="Voir les <?= count($liste) - $visibles ?> autres cours"
                            data-moins="Réduire la liste">
                        Voir les <?= count($liste) - $visibles ?> autres cours
                    </button>
<?php endif; ?>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

    <p class="cursus-note">
        Programme officiel issu du bulletin académique. Les cours peuvent varier
        selon le parcours choisi au sein de la mention.
    </p>
</section>

<script>
/* Onglets et depliage du cursus.
   Ecrit ici plutot que dans vitrine.js : la section est incluse aussi bien par
   la page unique que par la page Formations, qui ne charge pas ce script. */
(function () {
    var section = document.getElementById('cursus');
    if (!section) { return; }

    var onglets  = Array.prototype.slice.call(section.querySelectorAll('.cursus-onglet'));
    var panneaux = Array.prototype.slice.call(section.querySelectorAll('.cursus-panneau'));
    if (!onglets.length) { return; }

    function activer(cle, donnerLeFocus) {
        onglets.forEach(function (o) {
            var actif = o.dataset.mention === cle;
            o.classList.toggle('est-actif', actif);
            o.setAttribute('aria-selected', actif ? 'true' : 'false');
            o.tabIndex = actif ? 0 : -1;
            if (actif && donnerLeFocus) { o.focus(); }
        });
        panneaux.forEach(function (p) {
            var actif = p.id === 'cursus-' + cle;
            p.hidden = !actif;
            p.classList.toggle('est-actif', actif);
        });
    }

    onglets.forEach(function (o) {
        o.addEventListener('click', function () { activer(o.dataset.mention, false); });
    });

    /* Fleches, Origine et Fin : comportement attendu d'un tablist. Sans cela,
       la tabulation traverse les sept onglets un a un avant d'atteindre le
       contenu. */
    section.querySelector('.cursus-onglets').addEventListener('keydown', function (e) {
        var i = onglets.indexOf(document.activeElement);
        if (i < 0) { return; }
        var j = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { j = (i + 1) % onglets.length; }
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { j = (i - 1 + onglets.length) % onglets.length; }
        else if (e.key === 'Home') { j = 0; }
        else if (e.key === 'End') { j = onglets.length - 1; }
        if (j === null) { return; }
        e.preventDefault();
        activer(onglets[j].dataset.mention, true);
    });

    /* Accordeon : description d'un cours.

       [hidden] vaut « display:none », depuis lequel rien ne se transitionne.
       A l'ouverture on le retire donc d'abord, puis on declenche l'animation
       a l'image suivante ; a la fermeture on attend la fin de la transition
       pour le reposer, sinon le volet disparaitrait d'un coup et resterait
       accessible au clavier entre-temps. */
    var moinsDeMouvement = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    section.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('.cursus-cours__tete') : null;
        if (!t || t.tagName !== 'BUTTON') { return; }

        var volet = document.getElementById(t.getAttribute('aria-controls'));
        var item  = t.closest('.cursus-cours__item');
        var ouvert = t.getAttribute('aria-expanded') === 'true';
        t.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
        if (!volet) { return; }

        if (!ouvert) {
            volet.hidden = false;
            if (moinsDeMouvement) { item.classList.add('est-ouvert'); return; }
            requestAnimationFrame(function () { item.classList.add('est-ouvert'); });
        } else {
            item.classList.remove('est-ouvert');
            if (moinsDeMouvement) { volet.hidden = true; return; }
            var fini = function (ev) {
                if (ev.target !== volet) { return; }
                volet.hidden = true;
                volet.removeEventListener('transitionend', fini);
            };
            volet.addEventListener('transitionend', fini);
        }
    });

    // Depliage des cours au-dela des huit premiers.
    section.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('.cursus-plus') : null;
        if (!b) { return; }
        var ouvert = b.getAttribute('aria-expanded') === 'true';
        var corps  = b.closest('.cursus-etape__corps');
        corps.querySelectorAll('.cursus-cours__item').forEach(function (li, i) {
            if (i >= 8) { li.classList.toggle('est-replie', ouvert); }
        });
        b.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
        b.textContent = ouvert ? b.dataset.plus : b.dataset.moins;
    });
})();
</script>
<?php endif; ?>
