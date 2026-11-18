<?php
/* =============================================================================
   RETIRE DU SERVICE — ce point d'entree ne doit plus rien ecrire.

   POURQUOI IL EST NEUTRALISE PLUTOT QUE SUPPRIME
   ----------------------------------------------
   Le deploiement se fait par extraction d'archive (tar) : elle remplace les
   fichiers mais n'efface pas ceux qui ont disparu de la source. Supprimer ce
   fichier en local laisserait donc l'ancienne version, vulnerable, en place
   sur le serveur. Il est remplace par ce refus explicite : l'archive ecrase le
   code dangereux, et toute requete recoit une reponse claire.

   CE QU'IL FAISAIT
   ----------------
   1. AUCUNE AUTHENTIFICATION. Un require de la connexion, puis directement
      l'UPDATE. Un POST anonyme sur
          /src/finance/save_finance.php?filiere=THEO&semester=1
      remettait a zero le bareme de toute une mention. Le fichier vivait hors
      du routeur : la garde de FinanceController ne le couvrait pas.
   2. AUCUN JETON CSRF.
   3. ECRASEMENT PAR ZERO. Les onze montants etaient initialises a 0, puis
      seuls ceux presents dans le POST etaient repris. Comme la page ne rend
      pas d'input pour frais_graduation au semestre 1, ni pour frais_costume
      hors Theologie, chaque enregistrement remettait ces montants a zero.
   4. Une reponse par redirection HTTP, inexploitable en AJAX.
   5. Aucune notion de session : un ajustement de tarif s'appliquait
      retroactivement a toutes les annees.

   REMPLACE PAR
   ------------
   app/finance-bareme.save.php — authentification, niveau <= 3, jeton CSRF,
   ecriture par session, et n'ecrit QUE les montants recus.
   ========================================================================== */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

http_response_code(410); // Gone

if (function_exists('estRequeteAjax') && estRequeteAjax()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => "Ce point d'entrée a été retiré. Le barème s'enregistre "
            . 'depuis /finance, qui utilise app/finance-bareme.save.php.',
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
	<p>L'enregistrement du barème financier ne passe plus par cette adresse.</p>
	<p>Le barème est désormais géré <strong>par session</strong> depuis la page
		Finance : chaque semestre d'une année scolaire a son propre tarif, et une
		session sans tarif propre hérite du barème de base.</p>
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
