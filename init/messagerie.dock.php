<?php
/* =============================================================================
   Messagerie en fenetres, au-dessus de chaque page (comme Facebook).

   Pose la configuration, la feuille et le script de la messagerie en mode
   « dock » : un bouton rond en bas a droite (la liste des discussions s'ouvre
   au-dessus), des bulles pour les discussions reduites, et de petites
   fenetres de discussion qui restent ouvertes d'une page a l'autre. C'est
   l'entree de la messagerie : elle n'est plus dans le menu ni la barre du haut.

   Inclus par init/topbar.php (pages du personnel) et src/student.transition.php
   (pages de l'espace etudiant). Rien sur la page /messagerie elle-meme
   (MSG_PAGE_MESSAGERIE), qui a sa propre interface, ni sans les tables : tant que
   data/migration_messagerie.sql n'est pas appliquee, les pages restent telles
   quelles.

   Leger au chargement : le curseur et le nombre de non lus sont donnes ici ;
   la liste des discussions n'est demandee qu'a l'ouverture du panneau ou si
   des fenetres etaient ouvertes sur la page precedente.
   ========================================================================== */
if (!defined('MSG_PAGE_MESSAGERIE') && !defined('MSG_DOCK_POSE') && !empty($_SESSION['user_id']) && isset($dtb)) {
	define('MSG_DOCK_POSE', true);
	try {
		require_once dirname(__DIR__) . '/data/messagerie.php';
		$msgDockMoi = msgMoi($dtb);
		if ($msgDockMoi) {
			$msgDockBase = isset($app_base) ? (string) $app_base : (defined('APP_BASE') ? APP_BASE : '');
			msgContexte((int) $msgDockMoi['id'], $msgDockBase);
			$msgDockConfig = [
				'mode'     => 'dock',
				'api'      => $msgDockBase . '/app/messagerie.api.php',
				'fichier'  => $msgDockBase . '/app/messagerie.fichier.php',
				'page'     => $msgDockBase . '/messagerie',
				'base'     => $msgDockBase,
				'moi'      => (int) $msgDockMoi['id'],
				'morceau'  => MSG_MORCEAU,
				'csrf'     => function_exists('csrf_token') ? csrf_token() : '',
				'curseur'  => msgCurseur($dtb),
				'non_lus'  => msgNonLusTotal($dtb, (int) $msgDockMoi['id']),
				'profil'   => msgProfils($dtb, [(int) $msgDockMoi['id']], $msgDockBase)[(int) $msgDockMoi['id']],
				'reglages' => [
					'peut_creer_groupe' => msgPeutCreerGroupe($msgDockMoi),
					'moderateur'        => msgEstModerateur($msgDockMoi),
					'diffusion'         => msgPeutDiffuser($msgDockMoi),
					'reactions'         => MSG_REACTIONS,
					'texte_max'         => MSG_TEXTE_MAX,
					'taille_max'        => MSG_TAILLE_MAX,
					'groupe_max'        => MSG_GROUPE_MAX,
					'extensions'        => array_merge(...array_values(MSG_EXTENSIONS)),
					'push'              => pushClePublique($dtb),   // notifications, data/push.php
				],
			];
			$msgDockRacine = dirname(__DIR__);
			?>
	<script type="application/json" id="msConfig"><?= json_encode($msgDockConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
	<link rel="stylesheet" href="<?=$msgDockBase?>/src/css/messagerie.css?v=<?=@filemtime($msgDockRacine . '/src/css/messagerie.css')?>">
	<script src="<?=$msgDockBase?>/src/js/messagerie.js?v=<?=@filemtime($msgDockRacine . '/src/js/messagerie.js')?>" defer></script>
<?php
		}
	} catch (Throwable $e) {
		/* Tables absentes ou base indisponible : pas de messagerie en fenetres. */
	}
}
