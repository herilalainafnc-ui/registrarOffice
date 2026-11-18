<?php
	// Fragment charge en AJAX : il rend des donnees de l'etudiant, il doit
	// donc verifier l'acces. _guard.php fournit aussi $dtb, $app_base et le
	// socle data/inscription.session.php.
	require_once(__DIR__ . '/../app/_guard.php');
	inscriptionGuardFragment();

	$student_id = $_POST['student_id'] ?? '';

	// Session de l'annee en cours, pas « l'id le plus grand » : un dossier
	// d'annee anterieure cree plus tard prenait sinon le dessus, et la barre
	// se vidait sans explication.
	$dossier = inscSessionCourante($dtb, $student_id, $_POST['session_id'] ?? null);

/* =============================================================================
   L'ETAT REEL, PAS SEULEMENT LES CASES COCHEES

   Les drapeaux de t_2024_inscription_session sont poses au clic, sans jamais
   verifier que l'etape a eu lieu. Releve en production le 16/09/2026 :
     380 dossiers portaient « mode de paiement » sans aucun mode enregistre ;
     517 portaient « informations enregistrees » sans ligne de frais ;
       5 portaient « cours ajoute » sans un seul cours.
   La barre lisait ces drapeaux : elle annoncait donc des etapes qui n'avaient
   pas eu lieu, et le registraire decouvrait le manque a l'impression.

   Trois etapes se lisent maintenant dans les donnees — les frais calcules, les
   cours pris, le mode enregistre. Les trois autres sont des gestes humains
   (verification, signatures, depot) : leur drapeau fait foi, faute de trace
   ailleurs. Quand un drapeau annonce une etape que les donnees dementent, la
   barre le signale au lieu de le croire.
   ========================================================================== */

if (!$dossier) {
	// Aucune inscription : la barre restait muette, le registraire ne savait
	// pas s'il devait attendre ou agir.
	echo '<div class="insc-etapes-vide">Aucune session enregistrée pour cet étudiant.</div>';
	return;
}

if (!inscAnneeEstCourante($dossier['annee_effective'])) {
	// Dossier hors annee en cours : on le dit, au lieu de ne rien afficher.
	echo '<div class="insc-etapes-vide insc-etapes-alerte">'
		. '<i class="bi bi-exclamation-triangle-fill"></i> Dernière inscription : '
		. htmlspecialchars((string) $dossier['annee_effective'], ENT_QUOTES, 'UTF-8')
		. '. Année en cours : ' . htmlspecialchars(inscAnneeCourante(), ENT_QUOTES, 'UTF-8')
		. '. Créez la session de l\'année en cours.</div>';
	return;
}

$sessionId = (int) $dossier['session_id'];

/* --- Ce que les donnees disent du dossier ------------------------------- */
$req = $dtb->prepare(
	'SELECT COUNT(*) FROM t_2023_notes
	  WHERE student_id = :student_id AND session_id = :session_id AND `remove` != 1'
);
$req->execute(['student_id' => $student_id, 'session_id' => $sessionId]);
$nbCours = (int) $req->fetchColumn();

$finance = inscFinanceSession($dtb, (string) $student_id, $sessionId);
$mode = $finance ? strtoupper(trim((string) $finance['mode_payement'])) : '';

/* --- Les six etapes ------------------------------------------------------ */
$etapes = [
	[
		'nom'     => 'Informations enregistrées',
		'faite'   => (int) $dossier['data_completion'] === 1,
		'note'    => $finance ? '' : 'frais non calculés',
		'alerte'  => !$finance,
		'apercu'  => $finance ? 'Frais calculés pour cette session.' : "Aucune ligne de frais : la fiche d'inscription ne pourra pas servir de justificatif.",
	],
	[
		'nom'     => 'Informations vérifiées',
		'faite'   => (int) $dossier['data_verification'] === 1,
		'apercu'  => 'Contrôle des informations par le registrariat.',
	],
	[
		'nom'     => 'Cours ajoutés',
		'faite'   => $nbCours > 0,
		'note'    => $nbCours > 0 ? $nbCours . ' cours' : '',
		'alerte'  => (int) $dossier['cours_selected'] === 1 && $nbCours === 0,
		'apercu'  => $nbCours > 0
			? $nbCours . ' cours pris sur cette session.'
			: ((int) $dossier['cours_selected'] === 1
				? 'Étape cochée, mais aucun cours enregistré sur cette session.'
				: 'Aucun cours pris pour le moment.'),
	],
	[
		'nom'     => 'Mode de paiement',
		'faite'   => $mode !== '',
		'note'    => $mode !== '' ? 'tranche ' . $mode : '',
		'alerte'  => (int) $dossier['mode_payement'] === 1 && $mode === '',
		'apercu'  => $mode !== ''
			? 'Tranche ' . $mode . ' enregistrée.'
			: ((int) $dossier['mode_payement'] === 1
				? 'Étape cochée, mais aucun mode de paiement enregistré.'
				: 'Mode de paiement à choisir.'),
	],
	[
		'nom'     => 'Impression, signatures',
		'faite'   => (int) $dossier['verification_signatures'] === 1,
		'apercu'  => 'Fiche imprimée et signée.',
	],
	[
		'nom'     => 'Dépôt de la liste',
		'faite'   => (int) $dossier['depot_list'] === 1,
		'apercu'  => 'Liste déposée au registrariat.',
	],
];

/* Etape courante : la premiere qui reste a faire. C'est elle que la barre met
   en avant, pour que le registraire sache ou reprendre. */
$courante = null;
foreach ($etapes as $rang => $etape) {
	if (!$etape['faite']) {
		$courante = $rang;
		break;
	}
}
$faites = count(array_filter($etapes, static fn (array $e): bool => $e['faite']));

/* --- Rendu --------------------------------------------------------------- */
echo '<ol class="insc-etapes" aria-label="Progression de l\'inscription">';

foreach ($etapes as $rang => $etape) {
	$etat = $etape['faite'] ? 'faite' : ($rang === $courante ? 'courante' : 'avenir');
	$alerte = !empty($etape['alerte']);

	$classes = 'insc-etape insc-etape--' . $etat . ($alerte ? ' insc-etape--alerte' : '');
	$note = $alerte
		? ($etape['note'] !== '' ? $etape['note'] : 'à vérifier')
		: ($etape['note'] ?? '');

	echo '<li id="stageMark_' . ($rang + 1) . '" class="' . $classes . '"'
		. ' title="' . htmlspecialchars((string) $etape['apercu'], ENT_QUOTES, 'UTF-8') . '"'
		. ($rang === $courante ? ' aria-current="step"' : '') . '>'
		. '<span class="insc-etape-puce">'
		. ($alerte
			? '<i class="bi bi-exclamation-triangle-fill"></i>'
			: ($etape['faite'] ? '<i class="bi bi-check-lg"></i>' : (string) ($rang + 1)))
		. '</span>'
		. '<span class="insc-etape-nom">' . htmlspecialchars($etape['nom'], ENT_QUOTES, 'UTF-8') . '</span>'
		. ($note !== ''
			? '<span class="insc-etape-note">' . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . '</span>'
			: '')
		. '</li>';
}

echo '<li class="insc-etapes-compte" title="Étapes franchies, d\'après les données du dossier">'
	. '<b>' . $faites . '</b> / ' . count($etapes)
	. '<span>' . ($courante === null ? 'dossier complet' : 'à l\'étape ' . ($courante + 1)) . '</span>'
	. '</li>';

echo '</ol>';
