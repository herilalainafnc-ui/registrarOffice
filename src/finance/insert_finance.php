<?php
/* =============================================================================
   RETIRE DU SERVICE — ce point d'entree ne doit plus rien ecrire.

   Neutralise sur place et non supprime, pour la meme raison que
   save_finance.php : le deploiement par extraction d'archive remplace les
   fichiers mais n'efface pas ceux retires de la source. L'archive ecrase donc
   le code dangereux.

   CE QU'IL FAISAIT
   ----------------
   Comme save_finance.php, aucune authentification et aucun jeton CSRF : un
   POST anonyme inserait dix lignes de bareme a zero, en doublon des lignes
   existantes — la table n'a pas d'index unique sur
   (session_id, std_mention, level, std_status, semester). Verifie en local :
   un seul appel a produit dix doublons.

   La page /finance n'y renvoyait plus depuis longtemps : la creation des
   lignes de bareme est desormais faite par app/finance-bareme.save.php, qui
   cree une surcharge de session en partant du bareme de base — jamais a zero.
   ========================================================================== */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

http_response_code(410); // Gone

if (function_exists('estRequeteAjax') && estRequeteAjax()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => "Ce point d'entrée a été retiré. Les lignes de barème sont "
            . 'créées depuis /finance, via app/finance-bareme.save.php.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Point d'entrée retiré</title></head>
<body style="font-family:system-ui,sans-serif;max-width:38rem;margin:4rem auto;line-height:1.6">
	<h1 style="font-size:1.25rem">Point d'entrée retiré</h1>
	<p>La création des lignes de barème ne passe plus par cette adresse.</p>
	<p>Depuis la page Finance, le bouton <em>Reprendre la base</em> crée le tarif
		propre d'une session en partant du barème de base.</p>
	<p><a href="<?= htmlspecialchars(
		(function () {
			$dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
			$ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
			$base = ($dr !== '' && strpos($ar, $dr) === 0) ? (substr($ar, strlen($dr)) ?: '') : '';

			return $base . '/finance';
		})(),
		ENT_QUOTES,
		'UTF-8'
	) ?>">Aller à la gestion du barème</a></p>
</body>
</html>
