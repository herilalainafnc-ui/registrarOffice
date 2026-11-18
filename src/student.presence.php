<?php
/**
 * Mon assiduite — espace etudiant.
 *
 * L'etudiant est soumis aux regles d'assiduite du Bulletin academique
 * (§472 a §476) : il voit, cours par cours, ses presences, retards et
 * absences tels que ses enseignants les ont pointes, ce qu'ils comptent, et
 * ou il en est du seuil des 15 %. Les regles sont celles de data/presence.php,
 * les memes que celles de l'enseignant et du registrariat.
 */
?>
<!DOCTYPE html>
<html>
<?php require('../init/head.php');?>
	<title>Mon assiduité - Espace Étudiant</title>
<?php
require_once('../data/presence.php');

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

/* Les registres ou l'etudiant figure, du plus recent au plus ancien. */
$stmt = $dtb->prepare('SELECT DISTINCT f.* FROM t_presence_feuille f
	JOIN t_presence_seance s ON s.feuille_id = f.id
	JOIN t_presence_ligne l ON l.seance_id = s.id
	WHERE l.student_id = :sid
	ORDER BY f.annee_scolaire DESC, f.semestre ASC, f.sigle ASC');
$stmt->execute(['sid' => $sid]);
$feuilles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cours = [];
foreach ($feuilles as $f) {
	$pointages = presencePointages($dtb, (int)$f['id'], $sid);
	$cours[] = ['feuille' => $f, 'pointages' => $pointages, 'bilan' => presenceBilan($pointages, $f['seances_prevues'])];
}
$alertes = array_filter($cours, static fn ($c) => in_array($c['bilan']['etat'], ['depasse', 'limite'], true));
?>
	<style>
		body { font-family: "Inter", sans-serif; background: rgb(var(--c-canvas)); color: rgb(var(--c-text)); }
		.ma-barre {
			position: sticky; top: 0; z-index: 10; height: 56px; padding: 0 1.25rem;
			display: flex; align-items: center; justify-content: space-between;
			background: rgb(var(--c-canvas) / .92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
			border-bottom: 1px solid rgb(var(--c-line));
		}
		.ma-barre img { width: 32px; height: 32px; object-fit: contain; }
		.ma-barre__titre { font-weight: 600; font-size: .9rem; }
		.ma-icone {
			width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
			color: rgb(var(--c-accent-text)); background: rgb(var(--c-accent) / .1); border: 1px solid rgb(var(--c-accent) / .2);
			text-decoration: none; font-size: 1.1rem;
		}
		.ma-icone:focus-visible { outline: 2px solid rgb(var(--c-accent)); outline-offset: 2px; }
		.ma-page { max-width: 980px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
		.ma-titre { font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; margin: 0 0 .25rem; }
		.ma-sous { color: rgb(var(--c-text-2)); font-size: .88rem; }
		.ma-carte { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-line)); border-radius: 14px; margin-top: 1rem; overflow: hidden; }
		.ma-regles { padding: 1rem 1.25rem; font-size: .88rem; line-height: 1.6; color: rgb(var(--c-text-2)); }
		.ma-regles ul { margin: .5rem 0 0; padding-left: 1.1rem; }
		.ma-regles summary { cursor: pointer; font-weight: 600; color: rgb(var(--c-text)); }
		.ma-alerte { display: flex; gap: .75rem; padding: .9rem 1.1rem; border-radius: 12px; margin-top: 1rem; font-size: .9rem; line-height: 1.5; }
		.ma-cours__tete { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.25rem; border-bottom: 1px solid rgb(var(--c-line)); }
		.ma-sigle { font-family: 'JetBrains Mono', monospace; font-weight: 600; font-size: .85rem; color: rgb(var(--c-gold-text)); }
		.ma-etiquette { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; border: 1px solid transparent; }
		.ma-chiffres { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; background: rgb(var(--c-line)); }
		.ma-chiffre { background: rgb(var(--c-surface)); padding: .8rem 1rem; }
		.ma-chiffre b { display: block; font-size: 1.35rem; font-variant-numeric: tabular-nums; }
		.ma-chiffre span { font-size: .75rem; color: rgb(var(--c-text-3)); }
		.ma-jauge { padding: .9rem 1.25rem; border-top: 1px solid rgb(var(--c-line)); }
		.ma-jauge__piste { position: relative; height: 8px; border-radius: 999px; background: rgb(var(--c-sunken)); overflow: hidden; margin-top: .45rem; }
		.ma-jauge__plein { position: absolute; inset: 0 auto 0 0; border-radius: 999px; }
		.ma-seances { border-top: 1px solid rgb(var(--c-line)); }
		.ma-seances summary { padding: .75rem 1.25rem; cursor: pointer; font-size: .85rem; color: rgb(var(--c-text-2)); }
		.ma-seance { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 1.25rem; border-top: 1px solid rgb(var(--c-line) / .6); font-size: .85rem; }
		.ma-seance__statut { font-weight: 600; white-space: nowrap; }
		@media (max-width: 600px) {
			.ma-chiffres { grid-template-columns: repeat(2, minmax(0, 1fr)); }
			.ma-seance { flex-direction: column; gap: .15rem; }
		}
	</style>
</head>
<body>
	<header class="ma-barre">
		<div style="display:flex;align-items:center;gap:.6rem">
			<img src="<?= $e($app_base) ?>/file/UAZ Official.png" alt="UAZ">
			<span class="ma-barre__titre">Mon assiduité</span>
		</div>
		<nav style="display:flex;gap:.5rem" aria-label="Espace étudiant">
			<a class="ma-icone" href="<?= $e($app_base) ?>/student/home" title="Accueil" aria-label="Accueil"><i class="bi bi-house-door-fill"></i></a>
			<a class="ma-icone" href="<?= $e($app_base) ?>/logout" title="Déconnexion" aria-label="Déconnexion"><i class="bi bi-box-arrow-right"></i></a>
		</nav>
	</header>

	<main class="ma-page">
		<h1 class="ma-titre">Mon assiduité</h1>
		<p class="ma-sous"><?= $e(trim(($studentInfo['student_prenom'] ?? '') . ' ' . ($studentInfo['student_nom'] ?? ''))) ?> · <?= $e($sid) ?></p>

<?php foreach ($alertes as $a):
		$depasse = $a['bilan']['etat'] === 'depasse';
?>
		<div class="ma-alerte" style="background:<?= $depasse ? 'rgba(239,68,68,.12)' : 'rgba(245,158,11,.12)' ?>;border:1px solid <?= $depasse ? 'rgba(239,68,68,.4)' : 'rgba(245,158,11,.4)' ?>" role="alert">
			<i class="<?= $depasse ? 'bi bi-x-octagon-fill' : 'bi bi-exclamation-triangle-fill' ?>" style="color:<?= $depasse ? '#f87171' : '#fbbf24' ?>;font-size:1.1rem"></i>
			<div>
				<b><?= $e($a['feuille']['sigle']) ?></b> :
				<?php if ($depasse): ?>
					vos absences dépassent 15 % des séances prévues. Selon le Bulletin académique, vous devez quitter ce cours ;
					le registrariat en a été informé. Présentez-vous au bureau de la scolarité.
				<?php else: ?>
					vous êtes à la limite des 15 % : une absence de plus<?= $a['bilan']['retard_en_attente'] ? ', ou un retard de plus,' : '' ?> et vous devrez quitter ce cours.
				<?php endif; ?>
			</div>
		</div>
<?php endforeach; ?>

		<details class="ma-carte ma-regles" <?= $cours ? '' : 'open' ?>>
			<summary>Les règles d'assiduité (Bulletin académique, §472 à §476)</summary>
			<ul>
				<li>La présence régulière à tous les cours est exigée (§472).</li>
				<li>Un retard de moins de 5 minutes n'est pas compté. De 5 à 9 minutes, c'est un retard, et <b>deux retards comptent comme une absence</b>. À partir de 10 minutes, c'est une absence (§476).</li>
				<li>Au-delà de <b>15 % d'absences</b> dans un cours, vous devez quitter ce cours, quelle qu'en soit la raison : une inscription tardive ou une maladie n'excusent pas l'absence. Avec un certificat médical ou un billet d'hôpital, vous pouvez refaire les tests et examens manqués ; présentez votre justificatif à l'enseignant à votre retour (§476).</li>
				<li>Si l'enseignant n'est pas là 10 minutes après l'heure et n'a envoyé aucun message, vous pouvez partir sans pénalité : cette séance ne compte pas contre vous (§474).</li>
			</ul>
		</details>

<?php if (!$cours): ?>
		<div class="ma-carte" style="padding:2rem;text-align:center">
			<i class="bi bi-calendar-check" style="font-size:2rem;color:rgb(var(--c-text-3))"></i>
			<p class="ma-sous" style="margin-top:.5rem">Aucune présence n'a encore été relevée dans vos cours.</p>
		</div>
<?php endif; ?>

<?php foreach ($cours as $c):
		$f = $c['feuille']; $b = $c['bilan'];
		$info = presenceEtatInfo($b['etat']);
		$part = ($b['permises'] !== null) ? min(100, (int)round($b['comptees'] * 100 / max(1, $b['permises'] + 1))) : 0;
?>
		<section class="ma-carte" aria-labelledby="cours-<?= (int)$f['id'] ?>">
			<div class="ma-cours__tete">
				<div>
					<div class="ma-sigle"><?= $e($f['sigle']) ?></div>
					<h2 id="cours-<?= (int)$f['id'] ?>" style="font-size:1rem;font-weight:600;margin:.1rem 0"><?= $e($f['titre_cours']) ?></h2>
					<div class="ma-sous" style="font-size:.8rem"><?= $e($f['annee_scolaire']) ?> · <?= $e(remiseLibelleSemestre($f['semestre'])) ?></div>
				</div>
				<span class="ma-etiquette" style="background:<?= $info['couleur'] ?>1f;color:<?= $info['couleur'] ?>;border-color:<?= $info['couleur'] ?>55"><i class="<?= $info['icone'] ?>"></i><?= $e($info['libelle']) ?></span>
			</div>
			<div class="ma-chiffres">
				<div class="ma-chiffre"><b><?= (int)$b['presences'] ?></b><span>présence(s)</span></div>
				<div class="ma-chiffre"><b><?= (int)$b['retards'] ?></b><span>retard(s) de 5 à 9 min</span></div>
				<div class="ma-chiffre"><b><?= (int)$b['absences'] ?></b><span>absence(s)</span></div>
				<div class="ma-chiffre"><b><?= (int)$b['comptees'] ?><?= $b['permises'] !== null ? '<small style="font-size:.8rem;color:rgb(var(--c-text-3))"> / ' . (int)$b['permises'] . '</small>' : '' ?></b><span>absences comptées / permises</span></div>
			</div>
			<div class="ma-jauge">
				<?php if ($b['permises'] === null): ?>
					<span class="ma-sous" style="font-size:.8rem">Votre enseignant n'a pas encore indiqué le nombre de séances prévues : le seuil des 15 % n'est pas encore calculé.</span>
				<?php else: ?>
					<span class="ma-sous" style="font-size:.8rem">
						<?= (int)$b['prevues'] ?> séances prévues : <?= (int)$b['permises'] ?> absence(s) permise(s).
						<?= $b['retard_en_attente'] ? 'Un retard est en attente : le prochain comptera comme une absence.' : '' ?>
						<?= $b['sans_penalite'] ? (int)$b['sans_penalite'] . ' absence(s) non comptée(s) (enseignant en retard, §474).' : '' ?>
					</span>
					<div class="ma-jauge__piste" role="img" aria-label="<?= (int)$b['comptees'] ?> absence(s) comptée(s) sur <?= (int)$b['permises'] ?> permise(s)">
						<div class="ma-jauge__plein" style="width:<?= $b['etat'] === 'depasse' ? 100 : $part ?>%;background:<?= $info['couleur'] ?>"></div>
					</div>
				<?php endif; ?>
			</div>
			<details class="ma-seances">
				<summary><?= count($c['pointages']) ?> séance(s) relevée(s) — voir le détail</summary>
				<?php foreach (array_reverse($c['pointages']) as $p):
					$categorie = presenceCategorie($p['statut'], $p['minutes_retard']);
					$couleur = $categorie === 'absence' ? '#f87171' : ($categorie === 'retard' ? '#fbbf24' : ($categorie === 'present' ? '#4ade80' : 'rgb(var(--c-text-3))'));
				?>
				<div class="ma-seance">
					<span><?= $e(presenceDateLongue($p['date_seance'])) ?><?= $p['heure_prevue'] ? ' · ' . substr($p['heure_prevue'], 0, 5) : '' ?></span>
					<span class="ma-seance__statut" style="color:<?= $couleur ?>">
						<?= $e(presenceLibellePointage($p['statut'], $p['minutes_retard'])) ?>
						<?php if ($p['sans_penalite'] && $categorie !== 'present' && $categorie !== null): ?><span class="ma-sous" style="font-weight:400"> · non compté (§474)</span><?php endif; ?>
						<?php if ($p['justificatif'] === 'medical'): ?><span class="ma-sous" style="font-weight:400"> · certificat médical</span><?php elseif ($p['justificatif'] === 'autre'): ?><span class="ma-sous" style="font-weight:400"> · justificatif</span><?php endif; ?>
					</span>
				</div>
				<?php endforeach; ?>
			</details>
		</section>
<?php endforeach; ?>
	</main>
<?php include('./student.transition.php'); ?>
</body>
</html>
