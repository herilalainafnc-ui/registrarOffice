<?php
/**
 * Studio UAZ, etape 6 — l'administration (kit docs/studio-uaz/, § 9.6 et 9.8) : types de visuels
 * actifs, valeurs par defaut, listes gerees (services emetteurs, annees, filieres), validateurs,
 * mediatheque. Reservee au superadmin ; le Media ne fait que LIRE ces reglages et piocher dans la
 * mediatheque.
 *
 * Tables : data/migration_studio_admin.sql. Les images de la mediatheque sont des fichiers de
 * data/studio/mediatheque/ (data/ est ferme au web), nommes d'apres leur contenu.
 */

require_once __DIR__ . '/studio.visuels.php';

if (!function_exists('studioReglages')) {

/* Les champs « service emetteur » : leur valeur doit venir de la liste des services, quand elle existe */
define('STUDIO_CHAMPS_SERVICE', ['service', 'organisateur']);
define('STUDIO_LISTE_MAX', 100);
define('STUDIO_MEDIA_DOSSIER', STUDIO_DONNEES . '/mediatheque');

/** Les reglages, completes par leurs valeurs d'origine ; sans la table (pas encore migree), les valeurs d'origine. */
function studioReglages(PDO $dtb): array
{
	$reg = ['inactifs' => [], 'defauts' => [], 'listes' => ['services' => [], 'annees' => [], 'filieres' => []]];
	try {
		foreach ($dtb->query('SELECT cle, valeur FROM t_studio_reglage')->fetchAll(PDO::FETCH_KEY_PAIR) as $cle => $valeur) {
			$v = json_decode((string) $valeur, true);
			if ($cle === 'inactifs' && is_array($v)) { $reg['inactifs'] = array_values(array_filter($v, 'is_string')); }
			if ($cle === 'defauts' && is_array($v)) { $reg['defauts'] = $v; }
			if ($cle === 'listes' && is_array($v)) { foreach (['services', 'annees', 'filieres'] as $l) { if (is_array($v[$l] ?? null)) { $reg['listes'][$l] = array_values(array_filter($v[$l], 'is_string')); } } }
		}
	} catch (PDOException $e) { /* pas encore migre */ }
	return $reg;
}

/**
 * Des reglages envoyes, controles : types de visuels connus (un au moins reste actif), valeurs par defaut
 * des champs du preset seulement (jamais pour une liste), trois listes de 100 elements au plus, de 80
 * caracteres, sans doublon ni ligne vide.
 */
function studioReglagesNettoyer($brut): array
{
	$brut = is_array($brut) ? $brut : [];
	$presets = studioPresets();
	$reg = ['inactifs' => [], 'defauts' => [], 'listes' => ['services' => [], 'annees' => [], 'filieres' => []]];
	foreach (is_array($brut['inactifs'] ?? null) ? $brut['inactifs'] : [] as $id) {
		if (is_string($id) && isset($presets[$id]) && !in_array($id, $reg['inactifs'], true)) { $reg['inactifs'][] = $id; }
	}
	if (count($reg['inactifs']) >= count($presets)) { array_pop($reg['inactifs']); }   // au moins un type reste ouvert
	foreach (is_array($brut['defauts'] ?? null) ? $brut['defauts'] : [] as $pid => $champs) {
		if (!isset($presets[$pid]) || !is_array($champs)) { continue; }
		foreach ($presets[$pid]['champs'] as $c) {
			if ($c['type'] === 'liste' || !isset($champs[$c['id']])) { continue; }
			$v = $c['type'] === 'texteLong' && !empty($c['paragraphes']) ? studioTexteParagraphes($champs[$c['id']]) : studioTexte($champs[$c['id']]);
			if ($v === '') { continue; }
			$reg['defauts'][$pid][$c['id']] = mb_substr($v, 0, (int) ($c['max'] ?? 80));
		}
	}
	foreach (['services', 'annees', 'filieres'] as $l) {
		$vus = [];
		foreach (is_array($brut['listes'][$l] ?? null) ? $brut['listes'][$l] : [] as $x) {
			$x = mb_substr(studioTexte($x), 0, 80);
			if ($x === '' || isset($vus[mb_strtolower($x)])) { continue; }
			$vus[mb_strtolower($x)] = true;
			$reg['listes'][$l][] = $x;
			if (count($reg['listes'][$l]) >= STUDIO_LISTE_MAX) { break; }
		}
	}
	return $reg;
}

function studioReglagesSauver(PDO $dtb, array $reg, int $userId): void
{
	$s = $dtb->prepare('INSERT INTO t_studio_reglage (cle, valeur, modifie_par, modifie_le) VALUES (:c, :v, :u, :d) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), modifie_par = VALUES(modifie_par), modifie_le = VALUES(modifie_le)');
	foreach (['inactifs', 'defauts', 'listes'] as $cle) {
		$s->execute(['c' => $cle, 'v' => json_encode($reg[$cle], JSON_UNESCAPED_UNICODE), 'u' => $userId, 'd' => date('Y-m-d H:i:s')]);
	}
}

/** Un type de visuel peut-il etre cree ? (un visuel deja fait reste ouvrable et exportable) */
function studioPresetActif(array $reg, string $id): bool
{
	return !in_array($id, $reg['inactifs'], true);
}

/** Les erreurs de « service emetteur » : la valeur doit etre dans la liste des services (saisie libre sinon). */
function studioServicesErreurs(array $preset, array $propres, array $services): array
{
	if (!$services) { return []; }
	$erreurs = [];
	foreach ($preset['champs'] as $c) {
		if (!in_array($c['id'], STUDIO_CHAMPS_SERVICE, true)) { continue; }
		$v = (string) ($propres[$c['id']] ?? '');
		if ($v !== '' && !in_array($v, $services, true)) { $erreurs[] = "« {$c['libelle']} » : choisissez un service de la liste."; }
	}
	return $erreurs;
}

/* ------------------------------------------------------------------ validateurs */

/** Les validateurs designes (le superadmin valide toujours : il n'est pas dans la table). */
function studioValidateurs(PDO $dtb): array
{
	return $dtb->query("SELECT r.user_id, r.cree_le, u.pseudo, u.nom, u.prenom, u.level, u.etat FROM t_studio_role r LEFT JOIN compt_utilisateur u ON u.id = r.user_id WHERE r.role = 'validateur' ORDER BY u.nom, u.prenom")->fetchAll(PDO::FETCH_ASSOC);
}

/** Des comptes du personnel (pas les etudiants) a designer : par nom, prenom ou pseudo. */
function studioComptesRecherche(PDO $dtb, string $q): array
{
	$q = mb_substr(studioTexte($q), 0, 40);
	if (mb_strlen($q) < 2) { return []; }
	$like = '%' . addcslashes($q, '%_\\') . '%';
	$s = $dtb->prepare("SELECT id, pseudo, nom, prenom, level FROM compt_utilisateur WHERE etat = 1 AND level < :etu AND level != 1
		AND (nom LIKE :q OR prenom LIKE :q2 OR pseudo LIKE :q3) AND id NOT IN (SELECT user_id FROM t_studio_role WHERE role = 'validateur') ORDER BY nom, prenom LIMIT 15");
	$s->execute(['etu' => defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8, 'q' => $like, 'q2' => $like, 'q3' => $like]);
	return $s->fetchAll(PDO::FETCH_ASSOC);
}

function studioValidateurAjouter(PDO $dtb, int $userId, int $par): void
{
	$s = $dtb->prepare('SELECT level FROM compt_utilisateur WHERE id = :id AND etat = 1');
	$s->execute(['id' => $userId]);
	$niveau = $s->fetchColumn();
	if ($niveau === false) { throw new StudioErreur('Ce compte n\'existe pas ou est désactivé.'); }
	if ((int) $niveau >= (defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8)) { throw new StudioErreur('Un compte étudiant ne peut pas valider.'); }
	$dtb->prepare("INSERT IGNORE INTO t_studio_role (user_id, role, cree_par, cree_le) VALUES (:u, 'validateur', :p, :d)")->execute(['u' => $userId, 'p' => $par, 'd' => date('Y-m-d H:i:s')]);
}

function studioValidateurRetirer(PDO $dtb, int $userId): void
{
	$dtb->prepare("DELETE FROM t_studio_role WHERE user_id = :u AND role = 'validateur'")->execute(['u' => $userId]);
}

/* ---------------------------------------------------------------- mediatheque */

function studioMediaListe(PDO $dtb): array
{
	return $dtb->query('SELECT id, titre, alt, largeur, hauteur, ajoute_nom, ajoute_le FROM t_studio_media WHERE supprime = 0 ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
}

/** Ajoute une image preparee (studioPreparerImage) a la mediatheque. */
function studioMediaAjouter(PDO $dtb, array $image, string $titre, string $alt, int $userId): array
{
	$titre = mb_substr(studioTexte($titre), 0, 120);
	$alt = mb_substr(studioTexte($alt), 0, STUDIO_ALT_MAX);
	if ($titre === '') { throw new StudioErreur('Donnez un titre à l\'image.'); }
	if ($alt === '') { throw new StudioErreur('Décrivez l\'image (texte alternatif) : il est repris dans chaque visuel qui l\'utilise.'); }
	$octets = base64_decode($image['base64'], true);
	if ($octets === false || $octets === '') { throw new StudioErreur('L\'image est illisible.'); }
	if (!is_dir(STUDIO_MEDIA_DOSSIER) && !@mkdir(STUDIO_MEDIA_DOSSIER, 0775, true) && !is_dir(STUDIO_MEDIA_DOSSIER)) { throw new StudioErreur('Le dossier de la médiathèque n\'a pas pu être créé.'); }
	$nom = 'media-' . substr(sha1($octets), 0, 16) . ($image['type'] === 'image/png' ? '.png' : '.jpg');
	if (!is_file(STUDIO_MEDIA_DOSSIER . '/' . $nom) && file_put_contents(STUDIO_MEDIA_DOSSIER . '/' . $nom, $octets) === false) { throw new StudioErreur('L\'image n\'a pas pu être enregistrée.'); }
	$dtb->prepare('INSERT INTO t_studio_media (fichier, type, titre, alt, largeur, hauteur, ajoute_par, ajoute_nom, ajoute_le) VALUES (:f, :t, :ti, :a, :l, :h, :u, :n, :d)')
		->execute(['f' => $nom, 't' => $image['type'], 'ti' => $titre, 'a' => $alt, 'l' => $image['largeur'], 'h' => $image['hauteur'], 'u' => $userId, 'n' => studioNomUtilisateur($dtb, $userId), 'd' => date('Y-m-d H:i:s')]);
	return ['id' => (int) $dtb->lastInsertId(), 'titre' => $titre, 'alt' => $alt];
}

/** Une image de la mediatheque : ['type', 'chemin', 'titre', 'alt'] ; null si elle n'existe pas. */
function studioMediaLire(PDO $dtb, int $id): ?array
{
	$s = $dtb->prepare('SELECT fichier, type, titre, alt FROM t_studio_media WHERE id = :id AND supprime = 0');
	$s->execute(['id' => $id]);
	$m = $s->fetch(PDO::FETCH_ASSOC);
	if (!$m || !preg_match('/^media-[0-9a-f]{16}\.(jpg|png)$/', $m['fichier'])) { return null; }
	$chemin = STUDIO_MEDIA_DOSSIER . '/' . $m['fichier'];
	return is_file($chemin) ? ['type' => $m['type'], 'chemin' => $chemin, 'titre' => $m['titre'], 'alt' => $m['alt']] : null;
}

/** Retire une image de la mediatheque ; son fichier part s'il n'est plus cite par une autre ligne. */
function studioMediaSupprimer(PDO $dtb, int $id): void
{
	$s = $dtb->prepare('SELECT fichier FROM t_studio_media WHERE id = :id AND supprime = 0');
	$s->execute(['id' => $id]);
	$fichier = $s->fetchColumn();
	if ($fichier === false) { return; }
	$dtb->prepare('UPDATE t_studio_media SET supprime = 1 WHERE id = :id')->execute(['id' => $id]);
	$reste = $dtb->prepare('SELECT COUNT(*) FROM t_studio_media WHERE fichier = :f AND supprime = 0');
	$reste->execute(['f' => $fichier]);
	if ((int) $reste->fetchColumn() === 0 && preg_match('/^media-[0-9a-f]{16}\.(jpg|png)$/', (string) $fichier)) { @unlink(STUDIO_MEDIA_DOSSIER . '/' . $fichier); }
}

/**
 * Les images que ce compte a deja utilisees dans ses visuels (les plus recentes d'abord, sans
 * doublon de fichier) : [visuel_id, fichier, alt, titre du visuel].
 */
function studioImagesUtilisees(PDO $dtb, int $userId, int $limite = 60): array
{
	$s = $dtb->prepare('SELECT id, titre, images FROM t_studio_visuel WHERE auteur_id = :u AND supprime = 0 ORDER BY modifie_le DESC LIMIT 80');
	$s->execute(['u' => $userId]);
	$sortie = [];
	$vus = [];
	foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $v) {
		foreach (json_decode((string) $v['images'], true) ?: [] as $m) {
			$f = (string) ($m['fichier'] ?? '');
			if (!studioNomImageValide($f) || isset($vus[$f])) { continue; }
			$vus[$f] = true;
			$sortie[] = ['visuel' => (int) $v['id'], 'fichier' => $f, 'alt' => (string) ($m['alt'] ?? ''), 'titre' => (string) $v['titre']];
			if (count($sortie) >= $limite) { break 2; }
		}
	}
	return $sortie;
}

}
