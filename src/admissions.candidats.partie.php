<?php
/**
 * Partie echangeable de la page des candidatures : filtres + tableau.
 *
 * Rendue de deux facons :
 *   - incluse par src/admissions.candidats.php (chargement normal de la page) ;
 *   - seule, via /candidatures?fragment=1, quand le navigateur change de
 *     filtre — le contenu est alors remplace sans recharger la page.
 *
 * Elle doit donc etre autonome : si $dtb n'est pas deja fourni (cas du
 * fragment, ou init/head.php n'est pas charge), on ouvre la connexion ici.
 */

if (!isset($dtb)) {
    // On ouvre la connexion nous-memes plutot que d'inclure connectdb.php :
    // ce fichier a pu etre charge plus tot dans la requete (c'est le cas
    // quand une action d'instruction renvoie la liste rafraichie), auquel cas
    // un require_once ne le rejouerait pas — et $dtb, variable locale du
    // controleur, resterait absente de cette portee.
    require_once(__DIR__ . '/../data/config.php');
    require_once(__DIR__ . '/../data/audit.php');
    $dtb = auditConnexion(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
}

$libelleStatut = [
    'nouveau'  => 'Nouveau',
    'en_cours' => 'En cours',
    'accepte'  => 'Accepté',
    'refuse'   => 'Refusé',
    'converti' => 'Converti',
];

// Filtre demande. On le valide contre la liste connue : une valeur
// arbitraire ne doit pas atteindre la requete.
/* « archive » n'est pas un statut mais une mise de cote : elle se combine a
   n'importe quelle decision. Elle partage neanmoins le parametre d'URL des
   filtres, puisqu'elle occupe la meme place dans l'interface. */
$filtre = isset($_GET['statut']) ? (string) $_GET['statut'] : '';
if ($filtre !== 'archive' && !isset($libelleStatut[$filtre])) { $filtre = ''; }
$vueArchives = ($filtre === 'archive');

$candidats = [];
$parStatut = [];
$total = 0;

try {
    // Compteurs par statut (pour les filtres), independants du filtre actif.
    // Les archives en sont exclues : c'est tout l'interet du rangement, et un
    // compteur « 42 nouvelles » dont 30 archivees serait trompeur.
    foreach ($dtb->query('SELECT statut, COUNT(*) AS n FROM candidat WHERE archive_le IS NULL GROUP BY statut') as $r) {
        $parStatut[$r['statut']] = (int) $r['n'];
        $total += (int) $r['n'];
    }
    $nbArchives = (int) $dtb->query('SELECT COUNT(*) FROM candidat WHERE archive_le IS NOT NULL')->fetchColumn();

    /* La jointure sur l'etudiant rapporte l'identifiant de sa fiche : un
       dossier converti affiche ainsi un matricule CLIQUABLE, et non plus un
       simple texte. C'est ce qui permet de verifier qu'un etudiant existe
       vraiment avant d'envisager de le recreer.

       LEFT JOIN, et non JOIN : si la fiche a ete supprimee entre-temps, la
       candidature doit rester visible dans la liste — le lien manquera, pas
       la ligne.

       Le COLLATE n'est pas decoratif : les deux colonnes student_id n'ont pas
       la meme collation — utf8mb4_unicode_ci pour `candidat`,
       utf8mb4_general_ci pour `tbl_2024_etudiant`. Sans lui, MySQL refuse la
       comparaison (erreur 1267, « illegal mix of collations ») et la page
       entiere tombe. Verifie avant d'ecrire cette requete.

       La conversion porte sur le cote `candidat` et non sur `e.student_id` :
       coercer la colonne indexee (idx_student_id) empecherait d'utiliser son
       index. Ici cinq candidatures contre deux mille etudiants, l'ecart est
       theorique — mais le sens de la conversion, lui, ne coute rien. */
    /* c.* : la fiche complete s'ouvre en fenetre au clic sur la ligne, et
       affiche tout ce que le candidat a saisi — filiation, tuteur, adresse,
       message entier. Selectionner une vingtaine de colonnes nommees pour les
       oublier une a une au fil des ajouts au formulaire public serait le
       meilleur moyen d'afficher une fiche incomplete. */
    $sql = 'SELECT c.*, e.id AS fiche_etudiant
            FROM candidat c
            LEFT JOIN tbl_2024_etudiant e
                   ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = c.student_id COLLATE utf8mb4_general_ci ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)';

    if ($vueArchives) {
        // Les archives se lisent par date de rangement : le dernier range est
        // celui qu'on vient de sortir de la liste, donc le plus recherche.
        $req = $dtb->query($sql . ' WHERE c.archive_le IS NOT NULL ORDER BY c.archive_le DESC');
    } elseif ($filtre !== '') {
        $req = $dtb->prepare($sql . ' WHERE c.archive_le IS NULL AND c.statut = ? ORDER BY c.date_creation DESC');
        $req->execute([$filtre]);
    } else {
        $req = $dtb->query($sql . ' WHERE c.archive_le IS NULL ORDER BY c.date_creation DESC');
    }
    $candidats = $req->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<p style="color:#f87171">Erreur de lecture des candidatures : '
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
}

$base = ($app_base ?? '') . '/candidatures';
$lienFiltre = function ($statut) use ($base) {
    return $statut === '' ? $base : $base . '?statut=' . urlencode($statut);
};
?>
						<div style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:14px; gap:12px; flex-wrap:wrap;">
							<div>
								<h1 style="font-size:20px; font-weight:700; margin:0;">Candidatures de pré-admission</h1>
								<p style="color:rgb(var(--c-text-3)); margin:4px 0 0; font-size:13px;">
									<?= (int)$total ?> au total ·
									<span style="color:rgb(var(--c-accent-text));"><?= (int)($parStatut['nouveau'] ?? 0) ?> nouvelle(s)</span>
								</p>
							</div>
						</div>

						<div class="cand-filtres" style="margin-bottom:14px;">
							<a class="cand-filtre <?= $filtre === '' ? 'actif' : '' ?>" href="<?= htmlspecialchars($lienFiltre(''), ENT_QUOTES, 'UTF-8') ?>">
								Toutes (<?= (int)$total ?>)
							</a>
<?php foreach ($libelleStatut as $cle => $lib): ?>
							<a class="cand-filtre <?= $filtre === $cle ? 'actif' : '' ?>" href="<?= htmlspecialchars($lienFiltre($cle), ENT_QUOTES, 'UTF-8') ?>">
								<?= htmlspecialchars($lib, ENT_QUOTES, 'UTF-8') ?> (<?= (int)($parStatut[$cle] ?? 0) ?>)
							</a>
<?php endforeach; ?>
							<!-- Ecarte des autres par une marge : ce n'est pas un
							     statut de plus, c'est un autre rayon. -->
							<a class="cand-filtre cand-filtre--archive <?= $vueArchives ? 'actif' : '' ?>"
							   href="<?= htmlspecialchars($lienFiltre('archive'), ENT_QUOTES, 'UTF-8') ?>">
								<i class="bi-archive" aria-hidden="true"></i>
								Archivées (<?= (int)($nbArchives ?? 0) ?>)
							</a>
						</div>

<?php if (empty($candidats)): ?>
						<div class="cand-vide">
							<i class="bi-inbox" style="font-size:32px; display:block; margin-bottom:8px; opacity:.5;"></i>
							<?= $filtre === '' ? "Aucune candidature pour l'instant." : 'Aucune candidature avec ce statut.' ?>
						</div>
<?php else: ?>
						<div style="overflow-x:auto;">
							<table class="cand-table">
								<thead>
									<tr>
										<?php /* La colonne « Message » a disparu. Un candidat qui
										         redige une lettre de motivation de vingt lignes
										         faisait une rangee de cinq cents pixels, et la liste
										         devenait illisible des la deuxieme candidature. Le
										         message entier s'affiche dans la fiche, au clic sur
										         la ligne ; une pastille signale ici qu'il y en a un. */ ?>
										<th>Reçu le</th>
										<th>Candidat</th>
										<th>Contact</th>
										<th>Mention</th>
										<th>Bac</th>
										<th>Statut</th>
										<th>Actions</th>
									</tr>
								</thead>
								<tbody>
<?php foreach ($candidats as $c):
	$statut = $c['statut'];
	$classeBadge = 'cand-' . preg_replace('/[^a-z_]/', '', $statut);
	$dateAff = $c['date_creation'] ? date('d/m/Y H:i', strtotime($c['date_creation'])) : '—';
	$dateTrait = $c['date_traitement'] ? date('d/m/Y H:i', strtotime($c['date_traitement'])) : null;
	// « converti » est terminal : plus d'action possible depuis l'interface.
	$verrouille = ($statut === 'converti');
?>
									<?php /* La rangee entiere ouvre la fiche. tabindex et role pour
									         que le clavier y accede : c'est le seul chemin vers le
									         message complet et vers la filiation. */ ?>
									<tr class="cand-ligne" data-fiche="<?= (int) $c['id'] ?>"
									    tabindex="0" role="button"
									    aria-label="Voir la fiche de <?= htmlspecialchars($c['nom_complet'], ENT_QUOTES, 'UTF-8') ?>">
										<td style="white-space:nowrap; color:rgb(var(--c-text-3));"><?= htmlspecialchars($dateAff, ENT_QUOTES, 'UTF-8') ?></td>
										<td style="font-weight:600;">
											<?= htmlspecialchars($c['nom_complet'], ENT_QUOTES, 'UTF-8') ?>
<?php if (trim((string) ($c['message'] ?? '')) !== ''): ?>
											<?php /* Pastille : elle dit qu'un message existe sans en
											         montrer un mot. La longueur donne l'ordre de
											         grandeur, ce qui aide a decider si on l'ouvre. */ ?>
											<i class="bi-chat-left-text cand-puce-msg"
											   title="<?= (int) mb_strlen(trim((string) $c['message'])) ?> caractères de message — cliquez pour lire"
											   aria-hidden="true"></i>
<?php endif; ?>
<?php
	/* Etat civil resume sous le nom. Les candidatures anterieures a
	   l'elargissement du formulaire n'ont aucun de ces champs : on
	   n'affiche donc la ligne que si quelque chose la remplit. */
	$naiss = '';
	if (!empty($c['date_naissance'])) {
		$naiss = date('d/m/Y', strtotime($c['date_naissance']));
	} elseif (!empty($c['annee_naissance'])) {
		$naiss = $c['annee_naissance'];
	}
	$details = array_filter([
		!empty($c['genre']) ? ($c['genre'] === 'F' ? 'F' : 'M') : '',
		$naiss !== '' ? 'né(e) ' . $naiss : '',
		$c['ville'] ?? '',
	]);
	if ($details):
?>
											<div style="font-weight:400; font-size:12px; color:rgb(var(--c-text-3)); margin-top:3px;">
												<?= htmlspecialchars(implode(' · ', $details), ENT_QUOTES, 'UTF-8') ?>
											</div>
<?php endif; ?>
										</td>
										<td style="white-space:nowrap;">
											<div><i class="bi-telephone" style="opacity:.6;"></i> <?= htmlspecialchars($c['telephone'], ENT_QUOTES, 'UTF-8') ?></div>
<?php if (!empty($c['email'])): ?>
											<div style="color:rgb(var(--c-text-3));"><i class="bi-envelope" style="opacity:.6;"></i> <?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
										</td>
										<td>
											<?= htmlspecialchars($c['mention_libelle'] ?: $c['mention'], ENT_QUOTES, 'UTF-8') ?>
<?php if (!empty($c['parcours']) || !empty($c['diplome_vise'])): ?>
											<div style="font-size:12px; color:rgb(var(--c-text-3)); margin-top:3px;">
<?php if (!empty($c['diplome_vise'])): ?>
												<span style="text-transform:capitalize;"><?= htmlspecialchars($c['diplome_vise'], ENT_QUOTES, 'UTF-8') ?></span><?php if (!empty($c['parcours'])): ?> · <?php endif; ?>
<?php endif; ?>
<?= htmlspecialchars($c['parcours'] ?? '', ENT_QUOTES, 'UTF-8') ?>
											</div>
<?php endif; ?>
										</td>
										<td><?= htmlspecialchars($c['annee_bacc'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
										<td>
											<span class="cand-badge <?= htmlspecialchars($classeBadge, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($libelleStatut[$statut] ?? $statut, ENT_QUOTES, 'UTF-8') ?></span>
											<?php /* « traité par » et la date sont passes dans la fiche :
											         ils ajoutaient une troisieme ligne a la rangee, alors
											         qu'on ne les consulte qu'en instruisant un dossier. */ ?>
										</td>
										<td>
											<!-- Le conteneur enveloppe les deux cas : un dossier converti n'a
											     plus d'action d'instruction, mais il garde le bouton d'archivage. -->
											<div class="cand-actions">
<?php if ($verrouille): ?>
											<?php /* Dossier converti : plus rien a instruire, mais on dit
											         clairement que l'etudiant EXISTE et on y renvoie. Sans ce
											         lien, le seul moyen de verifier etait de le rechercher a la
											         main — et la tentation de recreer la fiche l'emportait. */ ?>
<?php if (!empty($c['fiche_etudiant'])): ?>
												<a class="cand-btn cand-btn--creer"
												   href="<?= htmlspecialchars(($app_base ?? '') . '/student?id=' . (int)$c['fiche_etudiant'] . '&page=information', ENT_QUOTES, 'UTF-8') ?>"
												   title="Étudiant déjà créé — ouvrir sa fiche">
													<i class="bi-person-check-fill"></i>
													Déjà créé<?= !empty($c['student_id']) ? ' · ' . htmlspecialchars($c['student_id'], ENT_QUOTES, 'UTF-8') : '' ?>
												</a>
<?php else: ?>
												<?php /* Matricule enregistre mais fiche introuvable : on ne
												         propose pas un lien mort, on signale l'incoherence. */ ?>
												<span class="cand-traite" title="Fiche étudiant introuvable pour ce matricule">
													Déjà créé<?= !empty($c['student_id']) ? ' · ' . htmlspecialchars($c['student_id'], ENT_QUOTES, 'UTF-8') : '' ?>
													<br><span style="color:#fbbf24;">fiche introuvable</span>
												</span>
<?php endif; ?>
<?php elseif (!$vueArchives): ?>
											<!-- Vue des archives : aucune action d'instruction. Un dossier
											     range ne s'instruit pas — on le restaure d'abord, ce qui rend
											     le geste deliberé et evite de statuer sur un dossier qu'on
											     croyait clos. Seul « Restaurer » subsiste, plus bas. -->
<?php if ($statut === 'accepte'): ?>
												<a class="cand-btn cand-btn--creer"
												   href="<?= htmlspecialchars(($app_base ?? '') . '/students/create?candidat=' . (int)$c['id'], ENT_QUOTES, 'UTF-8') ?>">
													<i class="bi-person-plus-fill"></i> Créer l'étudiant
												</a>
<?php endif; ?>
<?php
	// Boutons proposes : on n'affiche pas celui du statut deja en place.
	$actions = [
		'en_cours' => ['En cours', 'cand-btn--encours'],
		'accepte'  => ['Accepter', 'cand-btn--accept'],
		'refuse'   => ['Refuser',  'cand-btn--refus'],
	];
	foreach ($actions as $cle => [$libelle, $classe]):
		if ($statut === $cle) { continue; }
?>
												<form method="post" action="<?= htmlspecialchars(($app_base ?? '') . '/candidatures/statut', ENT_QUOTES, 'UTF-8') ?>">
													<?= csrf_field() ?>
													<input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
													<input type="hidden" name="statut" value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
													<!-- Retour sur le meme filtre apres l'action. -->
													<input type="hidden" name="retour" value="<?= htmlspecialchars($filtre, ENT_QUOTES, 'UTF-8') ?>">
													<button type="submit" class="cand-btn <?= $classe ?>"<?= $cle === 'refuse' ? ' data-confirmer="Refuser cette candidature ?"' : '' ?>><?= $libelle ?></button>
												</form>
<?php endforeach; ?>
<?php endif; ?>
											<!-- Archivage : disponible quel que soit le statut, « converti »
											     compris — c'est justement un dossier clos qu'on veut ranger.
											     Il est donc hors du bloc verrouille ci-dessus. -->
											<form method="post" action="<?= htmlspecialchars(($app_base ?? '') . '/candidatures/archiver', ENT_QUOTES, 'UTF-8') ?>">
												<?= csrf_field() ?>
												<input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
												<input type="hidden" name="archiver" value="<?= $vueArchives ? '0' : '1' ?>">
												<input type="hidden" name="retour" value="<?= htmlspecialchars($filtre, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($vueArchives): ?>
												<button type="submit" class="cand-btn cand-btn--desarchive">
													<i class="bi-arrow-counterclockwise" aria-hidden="true"></i> Restaurer
												</button>
<?php else: ?>
												<button type="submit" class="cand-btn cand-btn--archive"
												        title="Retirer de la liste sans changer la décision">
													<i class="bi-archive" aria-hidden="true"></i> Archiver
												</button>
<?php endif; ?>
											</form>
											</div>
										</td>
									</tr>
<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<?php /* Les fiches completes, en bloc de donnees.

						         DANS le fragment, et non dans la page qui l'enveloppe : la
						         zone est remplacee a chaque bascule de filtre, les donnees
						         suivent donc les lignes affichees et ne peuvent pas se
						         desynchroniser.

						         Ici plutot que par un appel dedie : il y a une poignee de
						         candidatures, et le message etait DEJA envoye au navigateur —
						         dans la colonne qu'on vient de retirer. Rien de nouveau ne
						         transite, et il n'y a pas de point d'entree supplementaire a
						         proteger.

						         Les options HEX ferment le contexte HTML : un message
						         contenant « </script> » ne peut pas sortir du bloc. */ ?>
						<script type="application/json" id="cand-fiches"><?= json_encode(
							$candidats,
							JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
							| JSON_HEX_APOS | JSON_HEX_QUOT
						) ?></script>
<?php endif; ?>
