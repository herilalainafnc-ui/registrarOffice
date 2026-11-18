<?php
/**
 * Adresse universitaire d'un nouvel etudiant (@zurcher.edu.mg), unique.
 *
 * Elle se derive du nom : « nom.pre » (trois premieres lettres du prenom).
 * Jusqu'ici rien ne verifiait qu'elle etait libre : en octobre 2026, 76
 * adresses etaient portees par des etudiants differents (un seul peut avoir
 * le compte Google), et 59 par la meme personne passee de Licence en Master
 * avec un nouveau matricule. Regle de l'utilisateur (08/10/2026) : une
 * adresse deja prise, par qui que ce soit, n'est jamais reprise ; la nouvelle
 * fiche recoit une adresse neuve.
 *
 * Ordre des essais : nom.pre, nom.pren, nom.preno... jusqu'au prenom entier,
 * puis nom.prenom2, nom.prenom3... Sans prenom : nom.uaz, nom.uaz2...
 * Lettres et chiffres seulement : Google refuse les accents, et les espaces,
 * apostrophes et tirets des noms malgaches composes n'y ont pas leur place.
 *
 * Une adresse est prise si une fiche etudiant (meme supprimee : son compte
 * Google peut exister), un compte de l'application ou une fiche enseignant
 * la porte deja, ou si elle existe dans Google (compte ou alias), quand l'API
 * Google Admin est configuree (data/google.admin.php). Google injoignable :
 * on s'en tient a la base, la creation de l'etudiant ne s'arrete pas.
 */

require_once __DIR__ . '/google.admin.php';

if (!function_exists('adresseEtudiantLibre')) {

define('ADRESSE_ETUDIANT_DOMAINE', 'zurcher.edu.mg');

/**
 * Le texte sans accents ni caracteres hors ASCII, casse et ponctuation
 * gardees (« Université » -> « Universite »). Sert aussi au fichier d'import
 * Google Admin (src/genPDF/gen.mail_csv.php) : Excel l'ouvre sans « Ã© ».
 */
function adresseEtudiantSansAccents(string $texte): string
{
	$texte = strtr($texte, [
		'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c',
		'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
		'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u',
		'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
		'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ç' => 'C',
		'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
		'Ñ' => 'N', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ù' => 'U',
		'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ÿ' => 'Y', 'Œ' => 'OE', 'Æ' => 'AE',
		'’' => "'", '‘' => "'", '«' => '"', '»' => '"', '–' => '-', '—' => '-', "\u{00A0}" => ' ',
	]);
	return preg_replace('/[^\x20-\x7E]/', '', $texte);
}

/** Minuscules sans accents, lettres et chiffres seulement. */
function adresseEtudiantNormaliser(string $texte): string
{
	return preg_replace('/[^a-z0-9]/', '', strtolower(adresseEtudiantSansAccents(trim($texte))));
}

/**
 * Une adresse telle que Google l'accepte : minuscules, sans accents ni
 * espaces, lettres, chiffres, points, tirets, soulignes et apostrophes.
 */
function adresseEtudiantPourGoogle(string $adresse): string
{
	return preg_replace("/[^a-z0-9._'@-]/", '', strtolower(adresseEtudiantSansAccents(trim($adresse))));
}

/**
 * Adresses deja prises qui commencent par « debut » (minuscules), toutes
 * sources confondues.
 *
 * @return array<string, true>
 */
function adresseEtudiantPrisesCommencantPar(PDO $dtb, string $debut): array
{
	$motif = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $debut) . '%';
	$prises = [];
	foreach ([
		'SELECT LOWER(TRIM(student_email)) FROM tbl_2024_etudiant WHERE LOWER(TRIM(student_email)) LIKE ?',
		'SELECT LOWER(TRIM(mail)) FROM compt_utilisateur WHERE LOWER(TRIM(mail)) LIKE ?',
		'SELECT LOWER(TRIM(email)) FROM teacher WHERE LOWER(TRIM(email)) LIKE ?',
	] as $sql) {
		$st = $dtb->prepare($sql);
		$st->execute([$motif]);
		foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $a) { $prises[(string) $a] = true; }
	}
	return $prises;
}

/** L'adresse est-elle deja portee par une fiche, un compte, un enseignant ou un compte Google ? */
function adresseEtudiantPrise(PDO $dtb, string $adresse): bool
{
	$adresse = strtolower(trim($adresse));
	if ($adresse === '') { return false; }
	if (isset(adresseEtudiantPrisesCommencantPar($dtb, $adresse)[$adresse])) { return true; }
	return googleAdminConfiguree() && googleAdminAdresseExiste($adresse) === true;
}

/**
 * La premiere adresse libre pour ce nom et ce prenom.
 *
 * @return array{adresse:string, base:string}
 *   adresse : l'adresse attribuee ('' sans nom) ;
 *   base : l'adresse habituelle (nom.pre), pour dire a l'agent qu'elle etait prise.
 */
function adresseEtudiantLibre(PDO $dtb, string $nom, string $prenom): array
{
	$n = adresseEtudiantNormaliser($nom);
	$p = adresseEtudiantNormaliser($prenom);
	if ($n === '') { return ['adresse' => '', 'base' => '']; }

	$suffixe = '@' . ADRESSE_ETUDIANT_DOMAINE;
	$essais = [];
	if ($p === '') {
		$essais[] = "$n.uaz";
		$racine = "$n.uaz";
	} else {
		for ($k = min(3, strlen($p)); $k <= strlen($p); $k++) { $essais[] = $n . '.' . substr($p, 0, $k); }
		$racine = "$n.$p";
	}
	for ($i = 2; $i <= 99; $i++) { $essais[] = $racine . $i; }

	$prises = adresseEtudiantPrisesCommencantPar($dtb, "$n.");
	$google = googleAdminConfiguree();
	$questionsGoogle = 0;
	foreach ($essais as $e) {
		if (isset($prises[$e . $suffixe])) { continue; }
		/* Libre dans la base : Google a-t-il deja ce compte (personnel, ancien
		   etudiant, import CSV) ? Au plus cinq questions, une demi-seconde chacune. */
		if ($google && $questionsGoogle < 5) {
			$questionsGoogle++;
			if (googleAdminAdresseExiste($e . $suffixe) === true) { continue; }
		}
		return ['adresse' => $e . $suffixe, 'base' => $essais[0] . $suffixe];
	}
	return ['adresse' => '', 'base' => $essais[0] . $suffixe];
}

/**
 * Qui porte deja cette adresse (pour le dire a l'agent) : « NOM Prenom
 * (matricule, Licence 3) », ou « un compte » / « un enseignant ». Vide si libre.
 */
function adresseEtudiantPorteur(PDO $dtb, string $adresse): string
{
	$adresse = strtolower(trim($adresse));
	$st = $dtb->prepare('SELECT student_nom, student_prenom, student_id, annee_etude FROM tbl_2024_etudiant
		WHERE LOWER(TRIM(student_email)) = ? ORDER BY `remove` ASC, id DESC LIMIT 1');
	$st->execute([$adresse]);
	if ($e = $st->fetch(PDO::FETCH_ASSOC)) {
		$niveau = (int) $e['annee_etude'];
		$niveau = $niveau === 0 ? 'remise à niveau' : ($niveau <= 3 ? 'Licence ' . $niveau : 'Master ' . ($niveau - 3));
		return trim($e['student_nom'] . ' ' . $e['student_prenom']) . ' (' . $e['student_id'] . ', ' . $niveau . ')';
	}
	$st = $dtb->prepare('SELECT 1 FROM compt_utilisateur WHERE LOWER(TRIM(mail)) = ? LIMIT 1');
	$st->execute([$adresse]);
	if ($st->fetchColumn()) { return 'un compte de l’application'; }
	$st = $dtb->prepare('SELECT 1 FROM teacher WHERE LOWER(TRIM(email)) = ? LIMIT 1');
	$st->execute([$adresse]);
	if ($st->fetchColumn()) { return 'un enseignant'; }
	return (googleAdminConfiguree() && googleAdminAdresseExiste($adresse) === true) ? 'un compte Google existant' : '';
}

}
