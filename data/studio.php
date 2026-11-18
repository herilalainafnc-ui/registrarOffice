<?php
/**
 * Studio UAZ — ce que PHP sait des visuels (demande du 20/10/2026, kit
 * docs/studio-uaz/).
 *
 * L'editeur (src/studio.php) et l'export (app/studio.export.php) passent par
 * ici : la liste des presets, le controle des donnees saisies (types,
 * longueurs, obligatoires), les images envoyees (vrai type, sans metadonnees,
 * 4 000 px au plus), le nom du fichier exporte et l'appel au service de rendu
 * (outils/studio-rendu/, Chromium sans interface sur 127.0.0.1).
 *
 * Le rendu lui-meme et les regles de la charte vivent dans src/studio/
 * (studio-rendu.js, studio-schema.js) : une seule source pour l'apercu et
 * l'export.
 */

if (!function_exists('studioPresets')) {

if (!defined('STUDIO_RENDU_URL')) { define('STUDIO_RENDU_URL', 'http://127.0.0.1:3310'); }
define('STUDIO_DOSSIER', dirname(__DIR__) . '/src/studio');
/* Le format tel qu'il s'ecrit dans le nom du fichier, et tel qu'on le nomme */
define('STUDIO_FORMATS', ['4:5' => '4x5', '1:1' => '1x1', '9:16' => '9x16', 'A4' => 'A4']);
define('STUDIO_NOMS_FORMATS', ['4:5' => 'Post 4:5', '1:1' => 'Carré 1:1', '9:16' => 'Story 9:16', 'A4' => 'Affiche A4']);
define('STUDIO_URL_MAX', 200);
define('STUDIO_IMAGE_MAX_OCTETS', 15 * 1024 * 1024);
define('STUDIO_IMAGE_MAX_COTE', 4000);
define('STUDIO_IMAGE_MAX_PIXELS', 40000000);
define('STUDIO_ALT_MAX', 150);

final class StudioErreur extends RuntimeException {}

/** Les presets actifs, par identifiant (src/studio/presets/<id>.json). */
function studioPresets(): array
{
	static $presets = null;
	if ($presets !== null) { return $presets; }
	$presets = [];
	foreach (glob(STUDIO_DOSSIER . '/presets/*.json') ?: [] as $f) {
		if (basename($f) === 'preset.schema.json') { continue; }
		$p = json_decode((string) file_get_contents($f), true);
		if (!is_array($p) || ($p['id'] ?? '') . '.json' !== basename($f) || ($p['actif'] ?? true) === false) { continue; }
		$presets[$p['id']] = $p;
	}
	ksort($presets);
	return $presets;
}

function studioPreset(string $id): ?array
{
	return studioPresets()[$id] ?? null;
}

/** Un texte long a paragraphes : une ligne par paragraphe, sans ligne vide ni caractere de controle. */
function studioTexteParagraphes($v): string
{
	$v = is_scalar($v) ? str_replace(["\r\n", "\r"], "\n", (string) $v) : '';
	$lignes = array_map('studioTexte', explode("\n", $v));
	return implode("\n", array_values(array_filter($lignes, static fn ($l) => $l !== '')));
}

/** Un texte saisi, nettoye : sans caracteres de controle, espaces reduits. */
function studioTexte($v): string
{
	$v = is_scalar($v) ? (string) $v : '';
	$v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
	return trim(preg_replace('/[ \t\r\n]+/u', ' ', $v) ?? '');
}

/**
 * Les intitules fixes d'un preset qui se reecrivent pour un visuel (« Lieu », « Heure »…) : les blocs
 * texte, label et badge a valeur constante, sauf l'identite de l'universite (nom, adresse, devise, « Document
 * officiel »). Les memes regles que le moteur (studio-rendu.js, etiquetteModifiable).
 *
 * @return list<string>
 */
function studioEtiquettes(array $preset): array
{
	static $cache = [];
	$cle = (string) ($preset['id'] ?? '') . '|' . md5(json_encode($preset['formats'] ?? []));
	if (isset($cache[$cle])) { return $cache[$cle]; }
	$trouves = [];
	$parcourir = static function ($n) use (&$parcourir, &$trouves): void {
		if (!is_array($n)) { return; }
		if (in_array($n['type'] ?? null, ['texte', 'label', 'badge'], true) && is_string($n['valeur'] ?? null)) {
			$v = $n['valeur'];
			if (trim($v) !== '' && strpos($v, '{') === false && !preg_match('/zurcher|spiritus|document officiel/i', $v)) { $trouves[$v] = true; }
		}
		foreach ($n as $x) { if (is_array($x)) { $parcourir($x); } }
	};
	$parcourir($preset['formats'] ?? []);
	return $cache[$cle] = array_keys($trouves);
}

/** Les intitules reecrits, controles : seulement ceux du preset, 60 caracteres, sans accolades ; vide = masque. */
function studioEtiquettesPropres(array $preset, $brut): array
{
	if (!is_array($brut)) { return []; }
	$permis = studioEtiquettes($preset);
	$sortie = [];
	foreach ($brut as $origine => $texte) {
		if (!in_array((string) $origine, $permis, true) || !is_scalar($texte)) { continue; }
		$t = mb_substr(str_replace(['{', '}'], '', studioTexte($texte)), 0, 60);
		if ($t !== (string) $origine) { $sortie[(string) $origine] = $t; }
	}
	return $sortie;
}

/**
 * Les donnees saisies, controlees champ par champ selon le preset.
 * Renvoie [donnees propres, erreurs en clair]. Les champs inconnus sont ignores.
 *
 * @return array{0: array, 1: list<string>}
 */
function studioValiderDonnees(array $preset, array $donnees): array
{
	$propres = [];
	$erreurs = [];
	foreach ($preset['champs'] as $c) {
		$id = $c['id'];
		$libelle = $c['libelle'];
		$brut = $donnees[$id] ?? null;
		switch ($c['type']) {
			case 'liste':
				$elements = [];
				foreach (is_array($brut) ? $brut : [] as $x) {
					$x = studioTexte($x);
					if ($x === '') { continue; }
					if (mb_strlen($x) > (int) $c['maxElement']) { $erreurs[] = "« {$libelle} » : chaque élément fait au plus {$c['maxElement']} caractères."; }
					$elements[] = $x;
				}
				if (count($elements) > (int) $c['max']) { $erreurs[] = "« {$libelle} » : au plus {$c['max']} éléments."; }
				if (count($elements) < (int) ($c['min'] ?? 0)) { $erreurs[] = "« {$libelle} » : au moins {$c['min']} éléments."; }
				$propres[$id] = $elements;
				break;
			case 'date':
				$v = studioTexte($brut);
				if ($v !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
					$erreurs[] = "« {$libelle} » : date invalide.";
					$v = '';
				}
				$propres[$id] = $v;
				break;
			case 'heure':
				$v = studioTexte($brut);
				if ($v !== '') {
					if (preg_match('/^(\d{1,2})\s*(?:[hH:]\s*(\d{2})?)?$/', $v, $m) && (int) $m[1] <= 23 && (!isset($m[2]) || $m[2] === '' || (int) $m[2] <= 59)) {
						$v = (int) $m[1] . (isset($m[2]) && $m[2] !== '' ? 'h' . $m[2] : '');
					} else {
						$erreurs[] = "« {$libelle} » : une heure s'écrit 15 ou 15h30.";
						$v = '';
					}
				}
				$propres[$id] = $v;
				break;
			case 'sousCode':
				$v = studioTexte($brut);
				$permis = array_column($preset['sousCodes'] ?? [], 'code');
				if ($v !== '' && !in_array($v, $permis, true)) { $erreurs[] = "« {$libelle} » : code inconnu."; $v = ''; }
				$propres[$id] = $v;
				break;
			case 'url':
				/* une adresse web (le QR code des inscriptions) : http ou https seulement */
				$v = studioTexte($brut);
				if (mb_strlen($v) > (int) $c['max']) { $erreurs[] = "« {$libelle} » : au plus {$c['max']} caractères."; }
				if ($v !== '' && (!preg_match('~^https?://~i', $v) || filter_var($v, FILTER_VALIDATE_URL) === false)) {
					$erreurs[] = "« {$libelle} » : une adresse complète, qui commence par https://";
					$v = '';
				}
				$propres[$id] = $v;
				break;
			default: // texte, texteLong, titre, motCle
				$v = !empty($c['paragraphes']) ? studioTexteParagraphes($brut) : studioTexte($brut);
				if (mb_strlen($v) > (int) $c['max']) { $erreurs[] = "« {$libelle} » : au plus {$c['max']} caractères."; }
				$propres[$id] = $v;
		}
		$vide = is_array($propres[$id]) ? count($propres[$id]) === 0 : $propres[$id] === '';
		if (!empty($c['requis']) && $vide) { $erreurs[] = "« {$libelle} » est à remplir."; }
	}
	$etiquettes = studioEtiquettesPropres($preset, $donnees['_etiquettes'] ?? null);
	if ($etiquettes) { $propres['_etiquettes'] = $etiquettes; }
	return [$propres, array_values(array_unique($erreurs))];
}

/** Un morceau de nom de fichier : minuscules sans accents, tirets, 60 caracteres au plus. */
function studioSlug(string $texte, int $max = 60): string
{
	$t = strtr(mb_strtolower(studioTexte($texte)), [
		'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
		'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ñ' => 'n', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'œ' => 'oe',
		'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ý' => 'y', '’' => '-', "'" => '-',
	]);
	$t = trim(preg_replace('/[^a-z0-9]+/', '-', $t) ?? '', '-');
	if (strlen($t) > $max) {
		$t = substr($t, 0, $max);
		$coupe = strrpos($t, '-');
		if ($coupe !== false && $coupe >= (int) ($max * 0.6)) { $t = substr($t, 0, $coupe); }
	}
	return trim($t, '-');
}

/** UAZ_{CODE}_{AAAA-MM-JJ}_{titre} : le debut de tous les noms de fichiers d'un visuel. */
function studioBaseNom(array $preset, array $donnees): string
{
	$sc = (string) ($donnees['sousCode'] ?? '');
	$code = in_array($sc, array_column($preset['sousCodes'] ?? [], 'code'), true) ? $sc : $preset['code'];
	$date = (string) ($donnees[$preset['dateDepuis'] ?? ''] ?? '');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }
	/* titreDepuis : un champ (« titre »), ou un gabarit (« {prenom} {nom} ») */
	$source = (string) ($preset['titreDepuis'] ?? '');
	$texte = strpos($source, '{') !== false
		? (string) preg_replace_callback('/\{([a-zA-Z0-9]+)\}/', static fn ($m) => is_scalar($donnees[$m[1]] ?? null) ? (string) $donnees[$m[1]] : '', $source)
		: (string) ($donnees[$source] ?? '');
	$titre = studioSlug($texte);
	return 'UAZ_' . $code . '_' . $date . '_' . ($titre !== '' ? $titre : $preset['id']);
}

/**
 * UAZ_{CODE}_{AAAA-MM-JJ}_{titre}_{format}.{ext} : la date du visuel (sinon le jour de l'export).
 * $variante : « imprimeur » pour le PDF A4 avec fond perdu et traits de coupe.
 */
function studioNomFichier(array $preset, array $donnees, string $format, string $ext, string $variante = ''): string
{
	return studioBaseNom($preset, $donnees) . '_' . (STUDIO_FORMATS[$format] ?? 'visuel') . ($variante !== '' ? '-' . $variante : '') . '.' . $ext;
}

/**
 * « Tout exporter » : pour chaque format du preset, le PNG et le PDF ; en A4, le PDF
 * et celui de l'imprimeur (fond perdu, traits de coupe).
 *
 * @return list<array{format: string, sortie: string, impression: bool}>
 */
function studioElementsLot(array $preset): array
{
	$elements = [];
	foreach (array_keys(STUDIO_FORMATS) as $format) {
		if (!isset($preset['formats'][$format])) { continue; }
		if ($format === 'A4') {
			$elements[] = ['format' => $format, 'sortie' => 'pdf', 'impression' => false];
			$elements[] = ['format' => $format, 'sortie' => 'pdf', 'impression' => true];
		} else {
			$elements[] = ['format' => $format, 'sortie' => 'png', 'impression' => false];
			$elements[] = ['format' => $format, 'sortie' => 'pdf', 'impression' => false];
		}
	}
	return $elements;
}

/**
 * L'archive ZIP de « Tout exporter » : [nom => octets] -> octets du ZIP. Les PNG et
 * les PDF sont deja compresses : ils y sont ranges tels quels.
 */
function studioZip(array $fichiers): string
{
	$tmp = tempnam(sys_get_temp_dir(), 'studio');
	$zip = new ZipArchive();
	if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) { throw new StudioErreur("L'archive n'a pas pu être créée."); }
	foreach ($fichiers as $nom => $octets) {
		$zip->addFromString($nom, $octets);
		$zip->setCompressionName($nom, ZipArchive::CM_STORE);
	}
	$zip->close();
	$sortie = (string) file_get_contents($tmp);
	@unlink($tmp);
	return $sortie;
}

/**
 * Les options d'une image, telles que l'editeur les envoie, bornees : fondre le blanc,
 * cadrage du cadre (« contenir » l'image entiere, « remplir » le cadre), recadrage
 * (point focal x, y de 0 a 1, zoom de 1 a 4) et reglages rapides (lumiere, contraste,
 * saturation de -100 a 100, le moteur les borne plus serre ; mode couleur, nb, duotone).
 *
 * @return array{fondre: bool, ajustement: string, cadrage: array{x: float, y: float, zoom: float}, reglages: array{lumiere: int, contraste: int, saturation: int, mode: string}}
 */
function studioOptionsImage(array $o, array $emplacement): array
{
	$nombre = static fn ($v, float $min, float $max, float $defaut): float => is_numeric($v) ? max($min, min($max, (float) $v)) : $defaut;
	$c = is_array($o['cadrage'] ?? null) ? $o['cadrage'] : [];
	$r = is_array($o['reglages'] ?? null) ? $o['reglages'] : [];
	return [
		'fondre' => !empty($o['fondre']),
		'ajustement' => in_array($o['ajustement'] ?? '', ['contenir', 'remplir'], true) ? $o['ajustement'] : ($emplacement['ajustement'] ?? 'contenir'),
		'cadrage' => ['x' => $nombre($c['x'] ?? null, 0, 1, 0.5), 'y' => $nombre($c['y'] ?? null, 0, 1, 0.5), 'zoom' => $nombre($c['zoom'] ?? null, 1, 4, 1)],
		'reglages' => [
			'lumiere' => (int) round($nombre($r['lumiere'] ?? null, -100, 100, 0)),
			'contraste' => (int) round($nombre($r['contraste'] ?? null, -100, 100, 0)),
			'saturation' => (int) round($nombre($r['saturation'] ?? null, -100, 100, 0)),
			'mode' => in_array($r['mode'] ?? '', ['couleur', 'nb', 'duotone'], true) ? $r['mode'] : 'couleur',
		],
	];
}

/** Les bornes de la mise en page libre (les memes que le moteur : studio-rendu.js, BORNES_MEP). */
define('STUDIO_MEP_BORNES', ['dx' => [-3000, 3000], 'dy' => [-3000, 3000], 'l' => [20, 3000], 'h' => [20, 3000], 'k' => [0.3, 4]]);

/**
 * La mise en page libre d'un visuel : { format: { id: { dx, dy, l, h, k } } }, ne gardant que les
 * formats du preset, les elements du preset (champs et images) et des nombres, bornes et arrondis
 * au dixieme.
 *
 * @return array<string, array<string, array<string, float>>>
 */
function studioMiseEnPage(array $preset, $brut): array
{
	if (!is_array($brut)) { return []; }
	$ids = array_merge(array_column($preset['champs'] ?? [], 'id'), array_column($preset['images'] ?? [], 'id'));
	$sortie = [];
	foreach ($brut as $format => $elements) {
		if (!isset($preset['formats'][$format]) || !is_array($elements)) { continue; }
		foreach ($elements as $id => $a) {
			if (!in_array($id, $ids, true) || !is_array($a)) { continue; }
			$propre = [];
			foreach (STUDIO_MEP_BORNES as $cle => [$min, $max]) {
				if (isset($a[$cle]) && is_numeric($a[$cle])) { $propre[$cle] = round(max($min, min($max, (float) $a[$cle])), 1); }
			}
			if ($propre) { $sortie[$format][$id] = $propre; }
		}
	}
	return $sortie;
}

define('STUDIO_BLOCS_MAX', 30);
define('STUDIO_BLOCS_COULEURS', ['navy', 'gold', 'goldDeep', 'goldLight', 'bronze', 'cream', 'paper', 'sand', 'ink', 'rule', 'navySoft', 'white']);

/**
 * Les blocs ajoutes a un visuel (texte libre, image, forme), controles : 30 au plus, identifiant bNN,
 * couleurs de la charte seulement, graisses de la police, nombres bornes, un texte jamais interprete.
 * L'ordre du tableau est celui des calques (le dernier est au-dessus).
 *
 * @return list<array<string, mixed>>
 */
function studioBlocs(array $preset, $brut): array
{
	if (!is_array($brut)) { return []; }
	$sortie = [];
	$vus = [];
	$nombre = static fn ($v, float $min, float $max, float $defaut): float => is_numeric($v) ? max($min, min($max, (float) $v)) : $defaut;
	foreach (array_values($brut) as $b) {
		if (count($sortie) >= STUDIO_BLOCS_MAX) { break; }
		$id = is_array($b) ? (string) ($b['id'] ?? '') : '';
		$type = is_array($b) ? ($b['type'] ?? '') : '';
		if (!preg_match('/^b[0-9]{1,4}$/', $id) || isset($vus[$id]) || !in_array($type, ['texte', 'forme', 'image'], true)) { continue; }
		$vus[$id] = true;
		$geo = [];
		foreach ((is_array($b['geo'] ?? null) ? $b['geo'] : []) as $format => $g) {
			if (!isset($preset['formats'][$format]) || !is_array($g)) { continue; }
			$geo[$format] = [
				'x' => round($nombre($g['x'] ?? null, -2000, 6000, 0), 1), 'y' => round($nombre($g['y'] ?? null, -2000, 6000, 0), 1),
				'l' => round($nombre($g['l'] ?? null, 4, 6000, 200), 1), 'h' => round($nombre($g['h'] ?? null, 2, 6000, 100), 1),
			];
		}
		$n = ['id' => $id, 'type' => $type, 'geo' => $geo];
		$couleur = static fn ($v, string $defaut): string => in_array($v, STUDIO_BLOCS_COULEURS, true) ? $v : $defaut;
		if ($type === 'texte') {
			$n += [
				'texte' => mb_substr(studioTexteParagraphes($b['texte'] ?? ''), 0, 600),
				'taille' => (int) round($nombre($b['taille'] ?? null, 8, 600, 48)),
				'graisse' => in_array((int) ($b['graisse'] ?? 0), [400, 500, 600, 700, 800], true) ? (int) $b['graisse'] : 600,
				'couleur' => $couleur($b['couleur'] ?? null, 'navy'),
				'align' => in_array($b['align'] ?? '', ['left', 'center', 'right'], true) ? $b['align'] : 'left',
				'maj' => !empty($b['maj']),
			];
		} elseif ($type === 'forme') {
			$n += [
				'forme' => ($b['forme'] ?? '') === 'cercle' ? 'cercle' : 'rect',
				'couleur' => $couleur($b['couleur'] ?? null, 'gold'),
				'opacite' => round($nombre($b['opacite'] ?? null, 0.05, 1, 1), 2),
				'rayon' => (int) round($nombre($b['rayon'] ?? null, 0, 600, 0)),
			];
		}
		$sortie[] = $n;
	}
	return $sortie;
}

/** Les emplacements d'image d'un visuel : ceux du preset, et un par bloc image (« libre<id> »). */
function studioEmplacements(array $preset, array $blocs): array
{
	$libres = [];
	foreach ($blocs as $b) {
		if (($b['type'] ?? '') === 'image') { $libres[] = ['id' => 'libre' . $b['id'], 'libelle' => 'Image ajoutée', 'ajustement' => 'contenir', 'requis' => false, 'fondre' => false]; }
	}
	return array_merge($preset['images'] ?? [], $libres);
}

/**
 * Une image envoyee : vrai type verifie, sens de la photo applique, reencodee
 * (sans metadonnees : ni lieu ni appareil), 4 000 px au plus de cote.
 *
 * @return array{type: string, base64: string, largeur: int, hauteur: int}
 */
function studioPreparerImage(string $chemin): array
{
	$taille = (int) @filesize($chemin);
	if ($taille <= 0) { throw new StudioErreur("L'image est vide."); }
	if ($taille > STUDIO_IMAGE_MAX_OCTETS) { throw new StudioErreur("L'image dépasse 15 Mo."); }
	$type = (string) (new finfo(FILEINFO_MIME_TYPE))->file($chemin);
	if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) { throw new StudioErreur('Seules les images JPEG, PNG et WebP sont acceptées.'); }
	$info = @getimagesize($chemin);
	if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > STUDIO_IMAGE_MAX_PIXELS) { throw new StudioErreur("L'image est illisible ou trop grande (40 millions de pixels au plus)."); }
	$src = @imagecreatefromstring((string) file_get_contents($chemin));
	if (!$src) { throw new StudioErreur("L'image est illisible."); }
	if ($type === 'image/jpeg' && function_exists('exif_read_data')) {
		$o = (int) ((@exif_read_data($chemin) ?: [])['Orientation'] ?? 1);
		$angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
		if ($angle) { $tourne = imagerotate($src, $angle, 0); if ($tourne) { imagedestroy($src); $src = $tourne; } }
	}
	$l = imagesx($src);
	$h = imagesy($src);
	$k = min(1, STUDIO_IMAGE_MAX_COTE / max($l, $h));
	if ($k < 1) {
		$petite = imagescale($src, max(1, (int) round($l * $k)), max(1, (int) round($h * $k)), IMG_BICUBIC);
		if ($petite) { imagedestroy($src); $src = $petite; }
	}
	/* La transparence se garde en PNG ; le reste part en JPEG. */
	$alpha = $type !== 'image/jpeg' && studioImageTransparente($src);
	ob_start();
	if ($alpha) { imagesavealpha($src, true); imagepng($src, null, 6); } else { imagejpeg($src, null, 92); }
	$octets = (string) ob_get_clean();
	$sortie = ['type' => $alpha ? 'image/png' : 'image/jpeg', 'base64' => base64_encode($octets), 'largeur' => imagesx($src), 'hauteur' => imagesy($src)];
	imagedestroy($src);
	return $sortie;
}

function studioImageTransparente($im): bool
{
	if (!imageistruecolor($im)) { return imagecolortransparent($im) >= 0; }
	$l = imagesx($im);
	$h = imagesy($im);
	$pas = max(1, (int) floor(max($l, $h) / 200));
	for ($y = 0; $y < $h; $y += $pas) {
		for ($x = 0; $x < $l; $x += $pas) {
			if ((imagecolorat($im, $x, $y) >> 24) & 0x7F) { return true; }
		}
	}
	return false;
}

/**
 * Le service de rendu. Renvoie [code http, corps, type] ; code 0 si le
 * service ne repond pas.
 *
 * @return array{0: int, 1: string, 2: string}
 */
function studioAppelerService(string $methode, string $chemin, ?array $corps = null, int $delai = 45): array
{
	$ch = curl_init(rtrim(STUDIO_RENDU_URL, '/') . $chemin);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => $methode,
		CURLOPT_CONNECTTIMEOUT => 3,
		CURLOPT_TIMEOUT => $delai,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: */*'],
	]);
	if ($corps !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corps, JSON_UNESCAPED_UNICODE)); }
	$reponse = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	curl_close($ch);
	return [$reponse === false ? 0 : $code, $reponse === false ? '' : (string) $reponse, $type];
}

/** Le service repond-il ? null sinon. */
function studioSante(): ?array
{
	[$code, $corps] = studioAppelerService('GET', '/sante', null, 4);
	$j = $code === 200 ? json_decode($corps, true) : null;
	return is_array($j) ? $j : null;
}

}
