<?php
/**
 * Studio UAZ, etape 5 — les visuels enregistres sur le serveur (demande du
 * 20/10/2026, kit docs/studio-uaz/, § 9) : brouillons, duplication,
 * historique, circuit de validation, journal des exports.
 *
 * Tables : data/migration_studio_visuels.sql. Les images sont des fichiers de
 * data/studio/images/<visuel>/ (data/ est ferme au web), nommes d'apres leur
 * contenu : une version de l'historique garde donc sa propre image.
 *
 * Roles (rattaches a l'authentification existante) :
 *   - editeur     le Media (niveau 5) : cree, modifie ses visuels, les exporte ;
 *   - validateur  un compte de t_studio_role : valide ou refuse ;
 *   - administrateur  le superadmin : valide, voit le journal.
 * Les presets COM et REC ne s'exportent qu'une fois valides ; pour les autres,
 * le circuit est facultatif.
 *
 * Une modification du contenu (champs, images, options) d'un visuel soumis,
 * valide ou refuse le remet en brouillon : on ne peut pas exporter autre chose
 * que ce qui a ete valide.
 */

require_once __DIR__ . '/studio.php';

if (!function_exists('studioVisuelLire')) {

define('STUDIO_DONNEES', dirname(__DIR__) . '/data/studio');
/* Les presets qu'un validateur doit approuver avant l'export (communiques officiels, recrutement) */
define('STUDIO_VALIDATION_OBLIGATOIRE', ['com', 'rec']);
define('STUDIO_VERSIONS_MAX', 30);
/* Les enregistrements automatiques rapproches ne font qu'une version */
define('STUDIO_VERSION_FUSION_SECONDES', 180);
define('STUDIO_STATUTS', ['brouillon', 'a_valider', 'valide', 'refuse']);

/** Ce que peut faire ce compte : ['editeur', 'validateur', 'admin']. */
function studioRoles(PDO $dtb, int $userId, int $niveau): array
{
	$admin = $niveau === (defined('ROLE_SUPERADMIN') ? (int) ROLE_SUPERADMIN : 1);
	$validateur = $admin;
	if (!$validateur && $userId > 0) {
		$s = $dtb->prepare("SELECT COUNT(*) FROM t_studio_role WHERE user_id = :u AND role = 'validateur'");
		$s->execute(['u' => $userId]);
		$validateur = (int) $s->fetchColumn() > 0;
	}
	return ['editeur' => true, 'validateur' => $validateur, 'admin' => $admin];
}

function studioValidationObligatoire(string $presetId): bool
{
	return in_array($presetId, STUDIO_VALIDATION_OBLIGATOIRE, true);
}

/** Le titre d'un visuel, tel que le nomme la liste : celui que designe titreDepuis, sinon le nom du preset. */
function studioVisuelTitre(array $preset, array $donnees): string
{
	$source = (string) ($preset['titreDepuis'] ?? '');
	$texte = strpos($source, '{') !== false
		? (string) preg_replace_callback('/\{([a-zA-Z0-9]+)\}/', static fn ($m) => is_scalar($donnees[$m[1]] ?? null) ? (string) $donnees[$m[1]] : '', $source)
		: (string) ($donnees[$source] ?? '');
	$texte = studioTexte($texte);
	return mb_substr($texte !== '' ? $texte : 'Sans titre', 0, 160);
}

/**
 * Les champs d'un brouillon : ceux du preset, nettoyes comme a l'export mais sans
 * rien exiger (un brouillon peut etre incomplet). Un texte trop long est coupe.
 */
function studioDonneesBrouillon(array $preset, array $donnees): array
{
	[$propres] = studioValiderDonnees($preset, $donnees);
	foreach ($preset['champs'] as $c) {
		$v = $propres[$c['id']] ?? '';
		if (is_string($v) && isset($c['max']) && mb_strlen($v) > (int) $c['max']) { $propres[$c['id']] = mb_substr($v, 0, (int) $c['max']); }
		if (is_array($v) && isset($c['max'])) { $propres[$c['id']] = array_slice($v, 0, (int) $c['max']); }
	}
	return $propres;
}

/** Ce qui fait « le meme visuel » : champs, images (fichier + options), mise en page, preset. Pas le format ni la legende. */
function studioSignature(string $presetId, array $donnees, array $images, array $miseEnPage = [], array $blocs = []): string
{
	ksort($images);
	$copie = [];
	foreach ($images as $id => $m) {
		$copie[$id] = [$m['fichier'] ?? '', $m['alt'] ?? '', !empty($m['fondre']), $m['ajustement'] ?? '', $m['cadrage'] ?? [], $m['reglages'] ?? []];
	}
	/* sans mise en page libre, la signature d'avant reste la meme */
	$charge = [$presetId, $donnees, $copie];
	if ($miseEnPage || $blocs) { $charge[] = $miseEnPage; }
	if ($blocs) { $charge[] = $blocs; }
	return sha1(json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
}

/* ------------------------------------------------------------------ images */

function studioImagesDossier(int $visuelId): string
{
	return STUDIO_DONNEES . '/images/' . $visuelId;
}

/** Un nom de fichier d'image tel que nous les ecrivons : jamais de chemin. */
function studioNomImageValide($nom): bool
{
	return is_string($nom) && (bool) preg_match('/^[a-zA-Z0-9]{1,40}-[0-9a-f]{12}\.(jpg|png)$/', $nom);
}

/** Ecrit une image preparee (studioPreparerImage) ; renvoie son nom de fichier. */
function studioImageEcrire(int $visuelId, string $emplacement, array $image): string
{
	$octets = base64_decode($image['base64'], true);
	if ($octets === false || $octets === '') { throw new StudioErreur("L'image est illisible."); }
	$nom = preg_replace('/[^a-zA-Z0-9]/', '', $emplacement) . '-' . substr(sha1($octets), 0, 12) . ($image['type'] === 'image/png' ? '.png' : '.jpg');
	$dossier = studioImagesDossier($visuelId);
	if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) { throw new StudioErreur("Le dossier des images n'a pas pu être créé."); }
	if (!is_file("$dossier/$nom") && file_put_contents("$dossier/$nom", $octets) === false) { throw new StudioErreur("L'image n'a pas pu être enregistrée."); }
	return $nom;
}

/** Une image enregistree, prete pour le service de rendu : ['type', 'base64']. */
function studioImageLire(int $visuelId, string $nom): ?array
{
	if (!studioNomImageValide($nom)) { return null; }
	$chemin = studioImagesDossier($visuelId) . '/' . $nom;
	if (!is_file($chemin)) { return null; }
	return ['type' => substr($nom, -4) === '.png' ? 'image/png' : 'image/jpeg', 'base64' => base64_encode((string) file_get_contents($chemin))];
}

/** Supprime les fichiers d'un visuel qu'aucune version (ni le visuel) ne cite plus. */
function studioImagesNettoyer(PDO $dtb, int $visuelId): void
{
	$gardes = [];
	$lire = static function (string $json) use (&$gardes): void {
		foreach ((json_decode($json, true) ?: []) as $m) { if (!empty($m['fichier'])) { $gardes[$m['fichier']] = true; } }
	};
	$v = $dtb->prepare('SELECT images FROM t_studio_visuel WHERE id = :id');
	$v->execute(['id' => $visuelId]);
	$lire((string) $v->fetchColumn());
	$s = $dtb->prepare('SELECT images FROM t_studio_version WHERE visuel_id = :id');
	$s->execute(['id' => $visuelId]);
	foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $j) { $lire((string) $j); }
	foreach (glob(studioImagesDossier($visuelId) . '/*') ?: [] as $f) {
		if (is_file($f) && !isset($gardes[basename($f)])) { @unlink($f); }
	}
}

/* ----------------------------------------------------------------- lecture */

/** Un visuel, ses champs et ses images decodes ; null s'il n'existe pas (ou est supprime). */
function studioVisuelLire(PDO $dtb, int $id): ?array
{
	$s = $dtb->prepare('SELECT * FROM t_studio_visuel WHERE id = :id AND supprime = 0');
	$s->execute(['id' => $id]);
	$v = $s->fetch(PDO::FETCH_ASSOC);
	if (!$v) { return null; }
	$v['donnees'] = json_decode((string) $v['donnees'], true) ?: [];
	$v['images'] = json_decode((string) $v['images'], true) ?: [];
	$v['mise_en_page'] = json_decode((string) ($v['mise_en_page'] ?? ''), true) ?: [];
	$v['blocs'] = json_decode((string) ($v['blocs'] ?? ''), true) ?: [];
	return $v;
}

/** Peut-on modifier ce visuel ? Son auteur, un validateur, l'administrateur. */
function studioPeutModifier(array $visuel, int $userId, array $roles): bool
{
	return (int) $visuel['auteur_id'] === $userId || $roles['validateur'] || $roles['admin'];
}

/** Le contenu est-il complet, prete a etre soumis ou exporte ? Les erreurs, en clair. */
function studioContenuErreurs(array $preset, array $donnees, array $images, array $blocs = [], array $services = []): array
{
	[$propres, $erreurs] = studioValiderDonnees($preset, $donnees);
	if ($services && function_exists('studioServicesErreurs')) { $erreurs = array_merge($erreurs, studioServicesErreurs($preset, $propres, $services)); }
	foreach (studioEmplacements($preset, $blocs) as $e) {
		$m = $images[$e['id']] ?? null;
		if (!$m || empty($m['fichier'])) {
			if (!empty($e['requis'])) { $erreurs[] = "« {$e['libelle']} » : une image est obligatoire."; }
			continue;
		}
		if (trim((string) ($m['alt'] ?? '')) === '') { $erreurs[] = "« {$e['libelle']} » : décrivez l'image (texte alternatif)."; }
	}
	return $erreurs;
}

/**
 * La liste des visuels. $vue : « mes » (les miens), « tous » (tout le Media), « a_valider ».
 * Sans les champs ni les images (lourds) : juste de quoi les nommer.
 */
function studioVisuelsListe(PDO $dtb, string $vue, int $userId, int $limite = 200): array
{
	$sql = 'SELECT id, preset, titre, format, statut, motif_refus, auteur_id, auteur_nom, valide_nom, valide_le, exporte_le, modifie_le FROM t_studio_visuel WHERE supprime = 0';
	$p = [];
	if ($vue === 'mes') { $sql .= ' AND auteur_id = :u'; $p['u'] = $userId; }
	elseif ($vue === 'a_valider') { $sql .= " AND statut = 'a_valider'"; }
	$sql .= ' ORDER BY modifie_le DESC, id DESC LIMIT ' . max(1, min(500, $limite));
	$s = $dtb->prepare($sql);
	$s->execute($p);
	return $s->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------ enregistrement */

function studioNomUtilisateur(PDO $dtb, int $userId): string
{
	$s = $dtb->prepare('SELECT * FROM compt_utilisateur WHERE id = :id');
	$s->execute(['id' => $userId]);
	$u = $s->fetch(PDO::FETCH_ASSOC) ?: [];
	$nom = trim((string) ($u['prenom'] ?? '') . ' ' . (string) ($u['nom'] ?? ''));
	return mb_substr($nom !== '' ? $nom : (string) ($u['pseudo'] ?? ('compte ' . $userId)), 0, 120);
}

/**
 * Une version de l'historique. Les enregistrements automatiques rapproches (moins de
 * 3 minutes, meme auteur) ne font qu'une version ; une version « manuelle » ou une
 * restauration en fait toujours une nouvelle. Les 30 dernieres sont gardees.
 */
function studioVersionAjouter(PDO $dtb, int $visuelId, array $v, string $motif, int $userId, string $nom): void
{
	$maintenant = date('Y-m-d H:i:s');
	$d = $dtb->prepare('SELECT id, numero, auteur_id, cree_le, motif, donnees, images, format, legende, mise_en_page, blocs FROM t_studio_version WHERE visuel_id = :id ORDER BY numero DESC LIMIT 1');
	$d->execute(['id' => $visuelId]);
	$derniere = $d->fetch(PDO::FETCH_ASSOC);
	$donnees = json_encode($v['donnees'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	$images = json_encode($v['images'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	$mep = json_encode($v['mise_en_page'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	$blocs = json_encode($v['blocs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	if ($derniere && $derniere['donnees'] === $donnees && $derniere['images'] === $images && (string) $derniere['mise_en_page'] === $mep && (string) $derniere['blocs'] === $blocs && $derniere['format'] === $v['format'] && (string) $derniere['legende'] === (string) $v['legende'] && $motif === 'auto') {
		return;   // rien n'a change
	}
	if ($derniere && $motif === 'auto' && $derniere['motif'] === 'auto' && (int) $derniere['auteur_id'] === $userId
		&& strtotime($maintenant) - strtotime((string) $derniere['cree_le']) < STUDIO_VERSION_FUSION_SECONDES) {
		$u = $dtb->prepare('UPDATE t_studio_version SET format = :f, donnees = :d, images = :i, mise_en_page = :mep, blocs = :bl, legende = :l, cree_le = :c WHERE id = :id');
		$u->execute(['f' => $v['format'], 'd' => $donnees, 'i' => $images, 'mep' => $mep, 'bl' => $blocs, 'l' => $v['legende'], 'c' => $maintenant, 'id' => $derniere['id']]);
		return;
	}
	$i = $dtb->prepare('INSERT INTO t_studio_version (visuel_id, numero, format, donnees, images, mise_en_page, blocs, legende, motif, auteur_id, auteur_nom, cree_le)
		VALUES (:v, :n, :f, :d, :i, :mep, :bl, :l, :m, :u, :nom, :c)');
	$i->execute(['v' => $visuelId, 'n' => ($derniere ? (int) $derniere['numero'] : 0) + 1, 'f' => $v['format'], 'd' => $donnees, 'i' => $images, 'mep' => $mep, 'bl' => $blocs, 'l' => $v['legende'],
		'm' => $motif, 'u' => $userId, 'nom' => $nom, 'c' => $maintenant]);
	$dtb->prepare('DELETE FROM t_studio_version WHERE visuel_id = :v AND numero <= :n')->execute(['v' => $visuelId, 'n' => ($derniere ? (int) $derniere['numero'] : 0) + 1 - STUDIO_VERSIONS_MAX]);
}

/**
 * Cree ou met a jour un visuel. $images : par emplacement, les options et le nom du fichier
 * (« fichier ») ou un fichier prepare a ecrire (« nouveau » : studioPreparerImage).
 * Renvoie le visuel tel qu'enregistre. Le contenu change : un visuel soumis, valide ou
 * refuse repasse en brouillon.
 *
 * @throws StudioErreur
 */
function studioVisuelEnregistrer(PDO $dtb, ?array $existant, array $preset, string $format, array $donnees, array $images, ?string $legende, string $motif, int $userId, array $miseEnPage = [], array $blocs = []): array
{
	$miseEnPage = studioMiseEnPage($preset, $miseEnPage);
	$blocs = studioBlocs($preset, $blocs);
	if (!isset($preset['formats'][$format])) { $format = (string) array_key_first($preset['formats']); }
	$propres = studioDonneesBrouillon($preset, $donnees);
	$nomUtilisateur = studioNomUtilisateur($dtb, $userId);
	$maintenant = date('Y-m-d H:i:s');
	$legende = $legende === null ? null : mb_substr(str_replace(["\r\n", "\r"], "\n", $legende), 0, 2200);

	$dtb->beginTransaction();
	try {
		if ($existant) {
			$id = (int) $existant['id'];
		} else {
			$dtb->prepare("INSERT INTO t_studio_visuel (preset, titre, format, donnees, images, statut, auteur_id, auteur_nom, cree_le, modifie_le) VALUES (:p, '', :f, '{}', '{}', 'brouillon', :u, :n, :c, :c2)")
				->execute(['p' => $preset['id'], 'f' => $format, 'u' => $userId, 'n' => $nomUtilisateur, 'c' => $maintenant, 'c2' => $maintenant]);
			$id = (int) $dtb->lastInsertId();
		}
		/* les images : le fichier garde (le sien), ou le nouveau, ecrit sous un nom tire de son contenu */
		$meta = [];
		foreach (studioEmplacements($preset, $blocs) as $e) {
			$m = $images[$e['id']] ?? null;
			if (!is_array($m)) { continue; }
			$fichier = null;
			if (!empty($m['nouveau'])) {
				$fichier = studioImageEcrire($id, $e['id'], $m['nouveau']);
			} elseif (!empty($m['fichier']) && $existant && studioNomImageValide($m['fichier']) && is_file(studioImagesDossier($id) . '/' . $m['fichier'])) {
				$fichier = $m['fichier'];
			}
			if ($fichier === null) { continue; }
			$meta[$e['id']] = ['fichier' => $fichier, 'alt' => mb_substr(studioTexte($m['alt'] ?? ''), 0, STUDIO_ALT_MAX)] + studioOptionsImage($m, $e);
		}
		$signature = studioSignature($preset['id'], $propres, $meta, $miseEnPage, $blocs);
		$statut = $existant ? (string) $existant['statut'] : 'brouillon';
		$change = !$existant || $existant['signature'] !== $signature;
		$retour = false;
		/* ce qui a ete soumis ou valide est fige : y toucher le remet en brouillon */
		if ($existant && $statut !== 'brouillon' && studioSignature((string) $existant['preset'], $existant['donnees'], $existant['images'], $existant['mise_en_page'], $existant['blocs']) !== $signature) {
			$statut = 'brouillon';
			$retour = true;
		}
		$titre = studioVisuelTitre($preset, $propres);
		$dtb->prepare('UPDATE t_studio_visuel SET titre = :t, format = :f, donnees = :d, images = :i, mise_en_page = :mep, blocs = :bl, legende = :l, statut = :s,
				motif_refus = IF(:r = 1, NULL, motif_refus), signature = IF(:r2 = 1, NULL, signature), valide_par = IF(:r3 = 1, NULL, valide_par),
				valide_nom = IF(:r4 = 1, NULL, valide_nom), valide_le = IF(:r5 = 1, NULL, valide_le), modifie_le = :m WHERE id = :id')
			->execute(['t' => $titre, 'f' => $format, 'd' => json_encode($propres, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'i' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'mep' => json_encode($miseEnPage, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'bl' => json_encode($blocs, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
				'l' => $legende, 's' => $statut, 'r' => (int) $retour, 'r2' => (int) $retour, 'r3' => (int) $retour, 'r4' => (int) $retour, 'r5' => (int) $retour, 'm' => $maintenant, 'id' => $id]);
		$visuel = studioVisuelLire($dtb, $id);
		$visuel['retourBrouillon'] = $retour;
		studioVersionAjouter($dtb, $id, $visuel, $motif, $userId, $nomUtilisateur);
		$dtb->commit();
	} catch (Throwable $e) {
		if ($dtb->inTransaction()) { $dtb->rollBack(); }
		throw $e instanceof StudioErreur ? $e : new StudioErreur("L'enregistrement a échoué : " . $e->getMessage());
	}
	studioImagesNettoyer($dtb, $id);
	return $visuel;
}

/** Copie un visuel pour une serie : un brouillon neuf, au nom du compte, sans validation. */
function studioVisuelDupliquer(PDO $dtb, array $source, int $userId): array
{
	$preset = studioPreset((string) $source['preset']);
	if (!$preset) { throw new StudioErreur('Le preset de ce visuel n\'existe plus.'); }
	$nom = studioNomUtilisateur($dtb, $userId);
	$maintenant = date('Y-m-d H:i:s');
	$dtb->beginTransaction();
	try {
		$dtb->prepare("INSERT INTO t_studio_visuel (preset, titre, format, donnees, images, mise_en_page, blocs, legende, statut, auteur_id, auteur_nom, cree_le, modifie_le) VALUES (:p, :t, :f, :d, :i, :mep, :bl, :l, 'brouillon', :u, :n, :c, :c2)")
			->execute(['p' => $source['preset'], 't' => $source['titre'], 'f' => $source['format'], 'd' => json_encode($source['donnees'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
				'i' => json_encode($source['images'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'mep' => json_encode($source['mise_en_page'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'bl' => json_encode($source['blocs'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'l' => $source['legende'], 'u' => $userId, 'n' => $nom, 'c' => $maintenant, 'c2' => $maintenant]);
		$id = (int) $dtb->lastInsertId();
		foreach ($source['images'] as $m) {
			$de = studioImagesDossier((int) $source['id']) . '/' . ($m['fichier'] ?? '');
			if (studioNomImageValide($m['fichier'] ?? null) && is_file($de)) {
				@mkdir(studioImagesDossier($id), 0775, true);
				copy($de, studioImagesDossier($id) . '/' . $m['fichier']);
			}
		}
		$visuel = studioVisuelLire($dtb, $id);
		studioVersionAjouter($dtb, $id, $visuel, 'duplication', $userId, $nom);
		$dtb->commit();
	} catch (Throwable $e) {
		if ($dtb->inTransaction()) { $dtb->rollBack(); }
		throw new StudioErreur('La copie a échoué : ' . $e->getMessage());
	}
	return $visuel;
}

/** Supprime (sans effacer : le journal et l'historique le citent encore). */
function studioVisuelSupprimer(PDO $dtb, int $id): void
{
	$dtb->prepare('UPDATE t_studio_visuel SET supprime = 1, modifie_le = :m WHERE id = :id')->execute(['m' => date('Y-m-d H:i:s'), 'id' => $id]);
}

/** L'historique d'un visuel, de la plus recente a la plus ancienne (sans le contenu). */
function studioHistorique(PDO $dtb, int $visuelId): array
{
	$s = $dtb->prepare('SELECT numero, format, motif, auteur_nom, cree_le FROM t_studio_version WHERE visuel_id = :id ORDER BY numero DESC');
	$s->execute(['id' => $visuelId]);
	return $s->fetchAll(PDO::FETCH_ASSOC);
}

/** Une ancienne version devient la version courante (une nouvelle ligne d'historique : rien n'est perdu). */
function studioRestaurer(PDO $dtb, array $visuel, int $numero, int $userId): array
{
	$s = $dtb->prepare('SELECT * FROM t_studio_version WHERE visuel_id = :id AND numero = :n');
	$s->execute(['id' => $visuel['id'], 'n' => $numero]);
	$v = $s->fetch(PDO::FETCH_ASSOC);
	if (!$v) { throw new StudioErreur('Cette version n\'existe plus.'); }
	$preset = studioPreset((string) $visuel['preset']);
	if (!$preset) { throw new StudioErreur('Le preset de ce visuel n\'existe plus.'); }
	$images = [];
	foreach (json_decode((string) $v['images'], true) ?: [] as $id => $m) { $images[$id] = $m; }
	return studioVisuelEnregistrer($dtb, $visuel, $preset, (string) $v['format'], json_decode((string) $v['donnees'], true) ?: [], $images, $v['legende'], 'restauration', $userId, json_decode((string) ($v['mise_en_page'] ?? ''), true) ?: [], json_decode((string) ($v['blocs'] ?? ''), true) ?: []);
}

/* ---------------------------------------------------------------- validation */

/** Soumet pour validation : le contenu doit etre complet. */
function studioSoumettre(PDO $dtb, array $visuel, array $preset): void
{
	if ($visuel['statut'] === 'a_valider') { throw new StudioErreur('Ce visuel attend déjà sa validation.'); }
	if ($visuel['statut'] === 'valide') { throw new StudioErreur('Ce visuel est déjà validé.'); }
	$services = function_exists('studioReglages') ? studioReglages($dtb)['listes']['services'] : [];
	$erreurs = studioContenuErreurs($preset, $visuel['donnees'], $visuel['images'], $visuel['blocs'], $services);
	if ($erreurs) { throw new StudioErreur('Le visuel n\'est pas prêt : ' . implode(' ', array_slice($erreurs, 0, 4))); }
	$dtb->prepare("UPDATE t_studio_visuel SET statut = 'a_valider', motif_refus = NULL, signature = :s, modifie_le = :m WHERE id = :id")
		->execute(['s' => studioSignature((string) $visuel['preset'], $visuel['donnees'], $visuel['images'], $visuel['mise_en_page'], $visuel['blocs']), 'm' => date('Y-m-d H:i:s'), 'id' => $visuel['id']]);
}

/** Un validateur approuve ou refuse ce qui a ete soumis (et que personne n'a retouche depuis). */
function studioDecider(PDO $dtb, array $visuel, bool $approuve, ?string $motif, int $userId, array $roles): void
{
	if (!$roles['validateur']) { throw new StudioErreur('Seul un validateur peut décider.'); }
	if ($visuel['statut'] !== 'a_valider') { throw new StudioErreur('Ce visuel n\'est pas en attente de validation.'); }
	if ((int) $visuel['auteur_id'] === $userId && !$roles['admin']) { throw new StudioErreur('Un visuel ne se valide pas soi-même : un autre validateur doit le faire.'); }
	if (studioSignature((string) $visuel['preset'], $visuel['donnees'], $visuel['images'], $visuel['mise_en_page'], $visuel['blocs']) !== $visuel['signature']) {
		throw new StudioErreur('Le visuel a changé depuis sa soumission : il repasse en brouillon.');
	}
	$motif = $motif === null ? '' : mb_substr(studioTexte($motif), 0, 300);
	if (!$approuve && $motif === '') { throw new StudioErreur('Dites pourquoi le visuel est refusé : l\'auteur en a besoin pour le corriger.'); }
	$dtb->prepare('UPDATE t_studio_visuel SET statut = :s, motif_refus = :mo, valide_par = :u, valide_nom = :n, valide_le = :d, modifie_le = :d2 WHERE id = :id')
		->execute(['s' => $approuve ? 'valide' : 'refuse', 'mo' => $approuve ? null : $motif, 'u' => $userId, 'n' => studioNomUtilisateur($dtb, $userId), 'd' => date('Y-m-d H:i:s'), 'd2' => date('Y-m-d H:i:s'), 'id' => $visuel['id']]);
}

/**
 * Peut-on exporter ce visuel ? Un preset a validation obligatoire exige un visuel enregistre
 * et valide, dont le contenu est celui qui a ete approuve. Renvoie le motif du refus, ou null.
 */
function studioExportRefuse(?array $visuel, array $preset): ?string
{
	if (!studioValidationObligatoire((string) $preset['id'])) { return null; }
	if (!$visuel) { return 'Un ' . mb_strtolower($preset['nom']) . ' s\'exporte après validation : enregistrez-le puis soumettez-le.'; }
	if ($visuel['statut'] !== 'valide') {
		return ['brouillon' => 'Ce visuel n\'a pas été soumis pour validation.', 'a_valider' => 'Ce visuel attend encore sa validation.', 'refuse' => 'Ce visuel a été refusé : corrigez-le et soumettez-le de nouveau.'][$visuel['statut']] ?? 'Ce visuel n\'est pas validé.';
	}
	if (studioSignature((string) $visuel['preset'], $visuel['donnees'], $visuel['images'], $visuel['mise_en_page'], $visuel['blocs']) !== $visuel['signature']) { return 'Ce visuel a changé depuis sa validation.'; }
	return null;
}

/* ------------------------------------------------------------------- journal */

function studioJournalAjouter(PDO $dtb, ?array $visuel, array $preset, string $titre, string $format, string $sortie, int $userId): void
{
	$dtb->prepare('INSERT INTO t_studio_journal (visuel_id, preset, titre, format, sortie, statut_visuel, auteur_id, auteur_nom, cree_le) VALUES (:v, :p, :t, :f, :s, :st, :u, :n, :c)')
		->execute(['v' => $visuel['id'] ?? null, 'p' => $preset['id'], 't' => mb_substr($titre, 0, 160), 'f' => $format, 's' => $sortie, 'st' => $visuel['statut'] ?? '', 'u' => $userId, 'n' => studioNomUtilisateur($dtb, $userId), 'c' => date('Y-m-d H:i:s')]);
	if ($visuel) { $dtb->prepare('UPDATE t_studio_visuel SET exporte_le = :d WHERE id = :id')->execute(['d' => date('Y-m-d H:i:s'), 'id' => $visuel['id']]); }
}

function studioJournalLire(PDO $dtb, int $limite = 200): array
{
	return $dtb->query('SELECT id, visuel_id, preset, titre, format, sortie, statut_visuel, auteur_nom, cree_le FROM t_studio_journal ORDER BY id DESC LIMIT ' . max(1, min(1000, $limite)))->fetchAll(PDO::FETCH_ASSOC);
}

}
