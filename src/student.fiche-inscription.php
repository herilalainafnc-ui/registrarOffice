<?php
/**
 * Fiche d'inscription d'une session — espace etudiant, a consulter et a
 * telecharger en PDF.
 *
 * C'est la fiche du guichet (src/extenssionPrint/fiche_inscription.php), lue
 * pour l'etudiant connecte seulement : son matricule vient de son compte,
 * jamais de l'adresse, et la session doit etre l'une de ses inscriptions.
 * L'adresse reste a un seul niveau sous /student : la fiche charge le logo et
 * la photo par des chemins relatifs (« ../file », « ../app »).
 */
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);
require_once('../data/espace.etudiant.php');

$app_base = defined('APP_BASE') ? APP_BASE : '';
if (!isStudent() && !isAdmin() && !isRegistrar()) {
	header('Location: ' . $app_base . '/login');
	exit;
}
$studentInfo = getStudentInfo();
if (!$studentInfo) {
	header('Location: ' . $app_base . '/student/home');
	exit;
}
$sid = (string) $studentInfo['student_id'];
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$sessionId = (int) ($_GET['session'] ?? 0);
$session = false;
if (espaceEtudiantRubriqueVisible($dtb, 'fiches_inscription') && $sessionId > 0) {
	$st = $dtb->prepare('SELECT s.session_id, s.session_name, s.session_year, ins.niveau_std
		FROM t_2024_inscription_session ins
		JOIN t_2023_session s ON s.session_id = ins.session_id
		WHERE ins.student_id = :sid AND ins.session_id = :ses AND COALESCE(ins.remove, 0) = 0
		LIMIT 1');
	$st->execute(['sid' => $sid, 'ses' => $sessionId]);
	$session = $st->fetch(PDO::FETCH_ASSOC);
	/* Meme regle que la liste : une fiche trop ancienne ne s'ouvre pas non
	   plus par son adresse. */
	if ($session && !espaceEtudiantFicheVisible((string) $session['session_year'], $session['niveau_std'], $studentInfo['annee_etude'] ?? 0)) {
		$session = false;
	}
}
if (!$session) { http_response_code(404); }

/* Nom du PDF sans accents ni espaces. Table explicite : iconv « TRANSLIT »
   rend « è » en « `e » sous Windows. */
$sansAccents = static fn ($t) => strtr((string) $t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a',
	'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'É' => 'E', 'È' => 'E']);
$nomFichier = $sid . '-fiche-inscription'
	. ($session ? '-' . preg_replace('/[^A-Za-z0-9]+/', '-', $sansAccents($session['session_name'] . ' ' . $session['session_year'])) : '');
$nomFichier = trim(preg_replace('/-+/', '-', $nomFichier), '-') . '.pdf';
$telecharger = $session && !empty($_GET['telecharger']);
?>
<!DOCTYPE html>
<html lang="fr">
<?php require('../init/head.noTem.php'); ?>
<title><?= $e($session ? "Fiche d'inscription — " . $session['session_name'] . ' ' . $session['session_year'] : "Fiche d'inscription") ?></title>
<style>
	/* Sur telephone, src/css/responsive.css passe tous les champs a 16 px
	   (contre le zoom d'iOS a la saisie) : les dates de paiement de la fiche
	   grossissaient et coupaient les montants, jusque dans le PDF. Ici ce sont
	   des champs d'affichage : ils gardent la taille de la fiche. */
	#printThisContent input:not([type="checkbox"]):not([type="radio"]) { font-size: .75rem !important; }
	body { margin: 0; background: #e2e8f0; font-family: "Inter", sans-serif; }
	.fi-barre {
		position: sticky; top: 0; z-index: 10; min-height: 56px; padding: .5rem 1rem;
		display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
		background: #1e293b; color: #fff;
	}
	.fi-retour { display: inline-flex; align-items: center; gap: .45rem; min-height: 40px; color: #fff; text-decoration: none; font-size: .9rem; font-weight: 600; }
	.fi-retour:hover { text-decoration: underline; }
	.fi-telecharger {
		display: inline-flex; align-items: center; gap: .45rem; min-height: 40px; padding: 0 1rem; border: 0; border-radius: 10px;
		background: #88724b; color: #fff; font-size: .88rem; font-weight: 600; cursor: pointer;
	}
	.fi-telecharger:hover { background: #7a6540; }
	.fi-telecharger:disabled { opacity: .6; cursor: progress; }
	.fi-retour:focus-visible, .fi-telecharger:focus-visible { outline: 2px solid #ceb480; outline-offset: 2px; }
	.fi-etat { font-size: .8rem; color: #cbd5e1; }
	.fi-defilement { overflow: auto; padding: 20px 12px; }
	.fi-feuille { width: 800px; margin: 0 auto; padding: 35px; background: #fff; box-shadow: 0 0 20px #47546980; }
	/* L'engagement est le seul enfant d'une rangee flex : l'intercalaire que
	   html2pdf insere pour le passer en page 2 s'y placait a cote de lui (bloc
	   decale a droite, titre reste seul en bas de page) au lieu de le pousser. */
	#printThisContent .w-full.gap-3.flex { display: block; }
	.fi-introuvable { max-width: 520px; margin: 12vh auto; padding: 28px; border-radius: 14px; background: #fff; text-align: center; color: #1e293b; }
</style>
<body>
	<header class="fi-barre">
		<a class="fi-retour" href="<?= $e($app_base) ?>/student/inscriptions"><i class="bi bi-arrow-left"></i>Mes fiches d'inscription</a>
<?php if ($session): ?>
		<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
			<span class="fi-etat" id="etatPdf" role="status" aria-live="polite"></span>
			<button type="button" class="fi-telecharger" id="btnPdf"><i class="bi bi-download"></i>Télécharger le PDF</button>
		</div>
<?php endif; ?>
	</header>

<?php if (!$session): ?>
	<div class="fi-introuvable">
		<h1 style="font-size:1.15rem;font-weight:700;margin-bottom:.5rem">Fiche introuvable</h1>
		<p style="font-size:.9rem;color:#475569">Cette session ne figure pas parmi vos inscriptions, ou les fiches ne sont pas consultables pour le moment.</p>
		<a class="fi-telecharger" style="margin-top:1rem;text-decoration:none" href="<?= $e($app_base) ?>/student/inscriptions">Voir mes fiches d'inscription</a>
	</div>
<?php else: ?>
	<div class="fi-defilement">
		<div class="fi-feuille">
			<div id="printThisContent" style="min-height:1020px;width:100%;position:relative">
<?php
	/* La fiche du guichet, pour CET etudiant et CETTE session, verifies plus
	   haut. La constante ouvre l'en-tete d'impression aux etudiants
	   (init/.forPrint/top.forPrint.php) : definie ici seulement, apres ces
	   verifications, jamais par la requete. */
	define('IMPRESSION_ETUDIANT', true);
	$_GET['student_id'] = $sid;
	$_GET['session_id'] = (string) $session['session_id'];
	require('./extenssionPrint/fiche_inscription.php');
?>
			</div>
		</div>
	</div>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script>
	(function () {
		var bouton = document.getElementById('btnPdf');
		var etat = document.getElementById('etatPdf');
		function telecharger() {
			if (!window.html2pdf) { etat.textContent = 'Le module PDF ne s\'est pas chargé : vérifiez la connexion, puis réessayez.'; return; }
			bouton.disabled = true;
			etat.textContent = 'Préparation du PDF…';
			html2pdf().set({
				margin: 0.5,
				filename: <?= json_encode($nomFichier) ?>,
				image: { type: 'jpeg', quality: 0.95 },
				html2canvas: { scale: 2, useCORS: true },
				jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
				/* Pas de ligne coupee en deux au bas d'une page : l'engagement
				   passait a cheval sur les deux pages. */
				pagebreak: { mode: ['css'], avoid: ['p', 'tr', 'img', 'table', '.w-full.pb-3'] }
			}).from(document.getElementById('printThisContent')).save().then(function () {
				etat.textContent = 'PDF téléchargé.';
			}).catch(function () {
				etat.textContent = 'Le PDF n\'a pas pu être créé. Réessayez.';
			}).then(function () { bouton.disabled = false; });
		}
		/* Telephone : l'apercu de la feuille (800 px) est reduit a la largeur
		   de l'ecran. Le PDF part d'une copie de la fiche, a sa taille reelle. */
		var feuille = document.querySelector('.fi-feuille');
		var largeurFeuille = feuille.offsetWidth;
		function ajuster() {
			var dispo = document.querySelector('.fi-defilement').clientWidth - 24;
			var z = Math.min(1, dispo / largeurFeuille);
			feuille.style.zoom = z < 1 ? String(z) : '';
		}
		ajuster();
		window.addEventListener('resize', ajuster);
		bouton.addEventListener('click', telecharger);
		<?php if ($telecharger): ?>
		window.addEventListener('load', telecharger);
		<?php endif; ?>
	})();
	</script>
<?php endif; ?>
</body>
</html>
