<?php
/**
 * Mes fiches d'inscription — espace etudiant.
 *
 * Une fiche par session ou l'etudiant est inscrit : celle que le guichet
 * imprime (src/extenssionPrint/fiche_inscription.php), avec ses cours, ses
 * frais et son mode de paiement. Elle s'ouvre et se telecharge en PDF depuis
 * src/student.fiche-inscription.php. Le registrariat peut masquer la rubrique
 * (Parametres > Espace etudiant).
 */
?>
<!DOCTYPE html>
<html>
<?php require('../init/head.php');?>
	<title>Mes fiches d'inscription - Espace Étudiant</title>
<?php
require_once('../data/espace.etudiant.php');
require_once('../data/finance.bareme.php');
require_once('../data/inscription.session.php');

if (!isStudent() && !isAdmin() && !isRegistrar()) {
	header('Location: ' . $app_base . '/login');
	exit;
}
$studentInfo = getStudentInfo();
if (!$studentInfo) {
	header('Location: ' . $app_base . '/student/home');
	exit;
}
$sid = (string)$studentInfo['student_id'];
$e = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$ouverte = espaceEtudiantRubriqueVisible($dtb, 'fiches_inscription');

/* Les inscriptions de l'etudiant, de la plus recente a la plus ancienne.
   L'annee est celle de la session (une inscription n'a pas d'autre annee). */
$inscriptions = [];
if ($ouverte) {
	$stmt = $dtb->prepare('SELECT ins.session_id, ins.niveau_std, ins.etude_mention, ins.date_entry,
			s.session_name, s.session_year,
			(SELECT COUNT(*) FROM t_2024_cours_finance cf
			  WHERE cf.student_id = ins.student_id AND cf.session_id = ins.session_id AND cf.remove != 1) AS nb_cours
		FROM t_2024_inscription_session ins
		JOIN t_2023_session s ON s.session_id = ins.session_id
		WHERE ins.student_id = :sid AND COALESCE(ins.remove, 0) = 0
		GROUP BY ins.session_id
		ORDER BY s.session_year DESC, s.session_id DESC');
	$stmt->execute(['sid' => $sid]);
	/* Licence a partir de 2026-2027, Master a partir de 2025-2026
	   (espaceEtudiantFicheVisible) : les fiches plus anciennes restent au guichet. */
	$inscriptions = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC),
		static fn ($i) => espaceEtudiantFicheVisible((string) $i['session_year'], $i['niveau_std'], $studentInfo['annee_etude'] ?? 0)));
}

$niveaux = array_column(baremeNiveaux(), 'libelle', 'niveau');
$parAnnee = [];
foreach ($inscriptions as $i) {
	$i['frais'] = (bool) inscFinanceSession($dtb, $sid, $i['session_id']);
	$parAnnee[$i['session_year']][] = $i;
}
$dateFr = static function ($d) {
	$t = strtotime((string) $d);
	return ($t && $t > 0) ? date('d/m/Y', $t) : '';
};
?>
	<style>
		body { font-family: "Inter", sans-serif; background: rgb(var(--c-canvas)); color: rgb(var(--c-text)); }
		.fi-barre {
			position: sticky; top: 0; z-index: 10; height: 56px; padding: 0 1.25rem;
			display: flex; align-items: center; justify-content: space-between;
			background: rgb(var(--c-canvas) / .92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
			border-bottom: 1px solid rgb(var(--c-line));
		}
		.fi-barre img { width: 32px; height: 32px; object-fit: contain; }
		.fi-barre__titre { font-weight: 600; font-size: .9rem; }
		.fi-icone {
			width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
			color: rgb(var(--c-accent-text)); background: rgb(var(--c-accent) / .1); border: 1px solid rgb(var(--c-accent) / .2);
			text-decoration: none; font-size: 1.1rem;
		}
		.fi-icone:focus-visible, .fi-bouton:focus-visible { outline: 2px solid rgb(var(--c-accent)); outline-offset: 2px; }
		.fi-page { max-width: 980px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
		.fi-titre { font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; margin: 0 0 .25rem; }
		.fi-sous { color: rgb(var(--c-text-2)); font-size: .88rem; line-height: 1.55; }
		.fi-annee { font-size: .78rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: rgb(var(--c-text-3)); margin: 1.75rem 0 .5rem; }
		.fi-carte { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-line)); border-radius: 14px; margin-top: .75rem; }
		/* Grille : les boutons gardent leur colonne a droite, avertissement ou non. */
		.fi-session { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: .75rem 1.25rem; padding: 1rem 1.25rem; }
		.fi-session + .fi-session { border-top: 1px solid rgb(var(--c-line)); }
		.fi-session__nom { font-size: 1rem; font-weight: 600; margin: 0; }
		.fi-session__details { color: rgb(var(--c-text-2)); font-size: .82rem; margin-top: .2rem; }
		.fi-avis { display: flex; gap: .4rem; align-items: flex-start; margin-top: .45rem; font-size: .78rem; line-height: 1.45; color: rgb(var(--c-warning)); }
		.fi-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
		.fi-bouton {
			display: inline-flex; align-items: center; gap: .45rem; min-height: 40px; padding: 0 .95rem; border-radius: 10px;
			font-size: .85rem; font-weight: 600; text-decoration: none; border: 1px solid rgb(var(--c-line));
			color: rgb(var(--c-text)); background: rgb(var(--c-canvas));
		}
		.fi-bouton:hover { border-color: rgb(var(--c-accent) / .5); }
		.fi-bouton--plein { background: rgb(var(--c-accent)); border-color: rgb(var(--c-accent)); color: #fff; }
		.fi-bouton--plein:hover { filter: brightness(1.08); }
		.fi-vide { padding: 2rem 1.25rem; text-align: center; }
		.fi-vide i { font-size: 2rem; color: rgb(var(--c-text-3)); }
		@media (max-width: 600px) {
			.fi-session { grid-template-columns: 1fr; }
			.fi-actions, .fi-bouton { width: 100%; }
			.fi-bouton { justify-content: center; }
		}
	</style>
</head>
<body>
	<header class="fi-barre">
		<div style="display:flex;align-items:center;gap:.6rem">
			<img src="<?= $e($app_base) ?>/file/UAZ Official.png" alt="UAZ">
			<span class="fi-barre__titre">Mes fiches d'inscription</span>
		</div>
		<nav style="display:flex;gap:.5rem" aria-label="Espace étudiant">
			<a class="fi-icone" href="<?= $e($app_base) ?>/student/home" title="Accueil" aria-label="Accueil"><i class="bi bi-house-door-fill"></i></a>
			<a class="fi-icone" href="<?= $e($app_base) ?>/logout" title="Déconnexion" aria-label="Déconnexion"><i class="bi bi-box-arrow-right"></i></a>
		</nav>
	</header>

	<main class="fi-page">
		<h1 class="fi-titre">Mes fiches d'inscription</h1>
		<p class="fi-sous"><?= $e(trim(($studentInfo['student_prenom'] ?? '') . ' ' . ($studentInfo['student_nom'] ?? ''))) ?> · <?= $e($sid) ?></p>

<?php if (!$ouverte): ?>
		<div class="fi-carte fi-vide">
			<i class="bi bi-file-earmark-lock"></i>
			<p class="fi-sous" style="margin-top:.5rem">Les fiches d'inscription ne sont pas consultables pour le moment. Pour obtenir la vôtre, adressez-vous au bureau de la registraire.</p>
		</div>
<?php elseif (!$inscriptions): ?>
		<div class="fi-carte fi-vide">
			<i class="bi bi-file-earmark-text"></i>
			<p class="fi-sous" style="margin-top:.5rem">Aucune fiche d'inscription à afficher pour le moment. Votre fiche apparaîtra ici après votre passage au guichet d'inscription. Les fiches des années précédentes (avant <?= $e(ESPACE_FICHES_DEPUIS['L']) ?> en Licence, avant <?= $e(ESPACE_FICHES_DEPUIS['M']) ?> en Master) s'obtiennent au bureau de la registraire.</p>
		</div>
<?php else: ?>
		<p class="fi-sous" style="margin-top:.75rem">Une fiche par session : vos cours, vos frais et le mode de paiement enregistrés à l'inscription. Téléchargez-la en PDF pour la garder ou l'imprimer.</p>

<?php foreach ($parAnnee as $annee => $sessions): ?>
		<h2 class="fi-annee">Année <?= $e($annee) ?></h2>
		<section class="fi-carte">
<?php foreach ($sessions as $i):
		$lien = $app_base . '/student/fiche-inscription?session=' . (int) $i['session_id'];
		$details = array_filter([
			$niveaux[$i['niveau_std']] ?? null,
			$i['etude_mention'] ?: null,
			$dateFr($i['date_entry']) !== '' ? 'inscrit le ' . $dateFr($i['date_entry']) : null,
			(int) $i['nb_cours'] . ' cours',
		]);
?>
			<div class="fi-session">
				<div>
					<h3 class="fi-session__nom"><?= $e($i['session_name']) ?> <?= $e($i['session_year']) ?></h3>
					<div class="fi-session__details"><?= $e(implode(' · ', $details)) ?></div>
<?php if (!$i['frais']): ?>
					<div class="fi-avis"><i class="bi bi-exclamation-triangle-fill"></i><span>Les frais de cette session ne sont pas encore enregistrés : la fiche ne vaut pas justificatif de paiement.</span></div>
<?php endif; ?>
				</div>
				<div class="fi-actions">
					<a class="fi-bouton" href="<?= $e($lien) ?>"><i class="bi bi-eye"></i>Voir la fiche</a>
					<a class="fi-bouton fi-bouton--plein" href="<?= $e($lien . '&telecharger=1') ?>"><i class="bi bi-download"></i>Télécharger le PDF</a>
				</div>
			</div>
<?php endforeach; ?>
		</section>
<?php endforeach; ?>
<?php endif; ?>
	</main>

<?php include('./student.transition.php'); ?>
</body>
</html>
