<?php
/**
 * Comptes Google Workspace de zurcher.edu.mg, par l'API Google Admin
 * (Directory API), sans bibliotheque : un compte de service signe son jeton
 * (JWT RS256, openssl) et agit au nom d'un administrateur du domaine
 * (delegation a l'echelle du domaine).
 *
 * Sert a :	
 *   - creer le compte Google d'un etudiant a « Creer l'etudiant »
 *     (app/.student/addStd.php) : l'adresse marche des que le mail
 *     d'acceptation part, sans import CSV dans Google Admin ;
 *   - ne jamais proposer une adresse deja prise dans Google
 *     (data/adresse.etudiant.php) ;
 *   - comparer les adresses de la base aux comptes Google (src/adresses-google.php) ;
 *   - le jeton du meme compte de service, au nom d'un enseignant, pour envoyer
 *     depuis sa boite Gmail (data/gmail.envoi.php).
 *
 * Mise en place (une fois, par un super-administrateur Google) :
 *   1. Google Cloud : un projet, l'API « Admin SDK » activee, un compte de
 *      service et sa cle JSON.
 *   2. Google Admin > Securite > Controle des API > Delegation a l'echelle du
 *      domaine : l'ID client du compte de service, avec le champ d'application
 *      https://www.googleapis.com/auth/admin.directory.user
 *   3. La cle sur le serveur : data/prive/google-admin.json (0640, groupe
 *      www-data ; data/ n'est jamais servi, data/prive/ n'est pas suivi par git).
 *   4. GOOGLE_ADMIN_SUJET ci-dessous : l'administrateur au nom duquel
 *      l'application agit (ses droits bornent ce qu'elle peut faire).
 */

if (!function_exists('googleAdminConfiguree')) {

if (!defined('GOOGLE_ADMIN_CLE')) { define('GOOGLE_ADMIN_CLE', __DIR__ . '/prive/google-admin.json'); }
/* Super-administrateur du domaine au nom duquel l'application agit (donne par
   l'utilisateur le 08/10/2026, delegation verifiee depuis le serveur). */
if (!defined('GOOGLE_ADMIN_SUJET')) { define('GOOGLE_ADMIN_SUJET', 'it@zurcher.edu.mg'); }
if (!defined('GOOGLE_ADMIN_DOMAINE')) { define('GOOGLE_ADMIN_DOMAINE', 'zurcher.edu.mg'); }
define('GOOGLE_ADMIN_PORTEE', 'https://www.googleapis.com/auth/admin.directory.user');
define('GOOGLE_ADMIN_API', 'https://admin.googleapis.com/admin/directory/v1');

final class GoogleAdminErreur extends RuntimeException
{
	/** Le code d'erreur de Google (unauthorized_client…), quand il en donne un. */
	public string $codeGoogle = '';
}

/** La cle est-elle posee et le compte a emprunter connu ? */
function googleAdminConfiguree(): bool
{
	return GOOGLE_ADMIN_SUJET !== '' && is_readable(GOOGLE_ADMIN_CLE);
}

/** Ce qui manque, en clair, pour la page de verification. */
function googleAdminCeQuiManque(): array
{
	$manque = [];
	if (!is_readable(GOOGLE_ADMIN_CLE)) { $manque[] = 'la clé du compte de service (data/prive/google-admin.json)'; }
	if (GOOGLE_ADMIN_SUJET === '') { $manque[] = "l'administrateur Google au nom duquel l'application agit (GOOGLE_ADMIN_SUJET)"; }
	return $manque;
}

function googleAdminBase64Url(string $s): string
{
	return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function googleAdminDossierCache(): string
{
	$d = defined('GOOGLE_ADMIN_CACHE') ? (string) GOOGLE_ADMIN_CACHE : rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'uaz-google-admin';
	if (!is_dir($d)) { @mkdir($d, 0700, true); }
	return $d;
}

/**
 * Appel HTTP ; renvoie [code, corps decode]. Leve GoogleAdminErreur si Google
 * est injoignable.
 *
 * @return array{0:int, 1:array}
 */
function googleAdminHttp(string $methode, string $url, ?string $corps = null, array $entetes = [], int $delai = 25): array
{
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => $methode,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_TIMEOUT        => $delai,
		CURLOPT_HTTPHEADER     => $entetes,
	]);
	if ($corps !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $corps); }
	$reponse = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$erreur = curl_error($ch);
	curl_close($ch);
	if ($reponse === false) { throw new GoogleAdminErreur('Google est injoignable depuis le serveur (' . $erreur . ').'); }
	$json = json_decode((string) $reponse, true);
	return [$code, is_array($json) ? $json : []];
}

/** Jeton d'acces (une heure), garde en cache sur le serveur. */
function googleAdminJeton(): string
{
	/* Un refus de Google vaut pour toute la requete : la generation d'une
	   adresse pose plusieurs questions, on ne la ralentit pas a chacune. */
	static $refus = null;
	if ($refus !== null) { throw new GoogleAdminErreur($refus); }
	try {
		return googleAdminJetonNeuf();
	} catch (GoogleAdminErreur $e) {
		$refus = $e->getMessage();
		throw $e;
	}
}

function googleAdminJetonNeuf(): string
{
	if (!googleAdminConfiguree()) { throw new GoogleAdminErreur("L'API Google Admin n'est pas configurée sur ce serveur."); }
	return googleCompteServiceJeton(GOOGLE_ADMIN_SUJET, GOOGLE_ADMIN_PORTEE);
}

/**
 * Jeton d'acces (une heure) du compte de service, au nom d'un compte du domaine
 * et pour un champ d'application : l'administrateur pour la Directory API
 * (ci-dessus), un enseignant pour l'envoi depuis sa boite (data/gmail.envoi.php).
 * Garde en cache sur le serveur. Leve GoogleAdminErreur, avec le code de Google.
 */
function googleCompteServiceJeton(string $sujet, string $portee): string
{
	if (!is_readable(GOOGLE_ADMIN_CLE)) { throw new GoogleAdminErreur("La clé du compte de service n'est pas posée sur ce serveur (data/prive/google-admin.json)."); }
	$cache = googleAdminDossierCache() . DIRECTORY_SEPARATOR . 'jeton-' . substr(sha1(GOOGLE_ADMIN_CLE . "\n" . strtolower($sujet) . "\n" . $portee), 0, 12) . '.json';
	$garde = @json_decode((string) @file_get_contents($cache), true);
	if (is_array($garde) && ($garde['expire'] ?? 0) > time() + 60 && !empty($garde['jeton'])) { return (string) $garde['jeton']; }

	$cle = json_decode((string) file_get_contents(GOOGLE_ADMIN_CLE), true);
	if (!is_array($cle) || empty($cle['client_email']) || empty($cle['private_key'])) {
		throw new GoogleAdminErreur("La clé du compte de service est illisible (fichier JSON attendu, tel que Google le donne).");
	}
	$maintenant = time();
	$entree = googleAdminBase64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
		. '.' . googleAdminBase64Url(json_encode([
			'iss'   => $cle['client_email'],
			'sub'   => $sujet,
			'scope' => $portee,
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $maintenant,
			'exp'   => $maintenant + 3600,
		]));
	$signature = '';
	if (!openssl_sign($entree, $signature, (string) $cle['private_key'], OPENSSL_ALGO_SHA256)) {
		throw new GoogleAdminErreur('La clé privée du compte de service ne permet pas de signer.');
	}
	[$code, $r] = googleAdminHttp('POST', 'https://oauth2.googleapis.com/token', http_build_query([
		'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
		'assertion'  => $entree . '.' . googleAdminBase64Url($signature),
	]), ['Content-Type: application/x-www-form-urlencoded']);
	if ($code !== 200 || empty($r['access_token'])) {
		$quoi = (string) ($r['error'] ?? '');
		$detail = (string) ($r['error_description'] ?? '');
		$refus = new GoogleAdminErreur(match (true) {
			$quoi === 'unauthorized_client' => "Google refuse le compte de service : la délégation à l'échelle du domaine n'est pas accordée (ou sans le champ "
				. $portee . ').',
			$quoi === 'invalid_client' || preg_match('/signature|account not found|not found/i', $detail) === 1
				=> "Google ne reconnaît pas la clé du compte de service (supprimée, ou fichier modifié) : créez-en une nouvelle dans Google Cloud.",
			preg_match('/email or user id/i', $detail) === 1 => "Google ne connaît pas le compte emprunté (" . $sujet . ').',
			preg_match('/iat|exp|timeframe/i', $detail) === 1 => "Google refuse le jeton : l'horloge du serveur est décalée.",
			default => 'Google refuse la connexion : ' . ($detail !== '' ? $detail : ($quoi !== '' ? $quoi : 'code ' . $code)) . '.',
		});
		$refus->codeGoogle = preg_match('/email or user id/i', $detail) === 1 ? 'compte_inconnu' : $quoi;
		throw $refus;
	}
	$jeton = (string) $r['access_token'];
	@file_put_contents($cache, json_encode(['jeton' => $jeton, 'expire' => $maintenant + (int) ($r['expires_in'] ?? 3600)]), LOCK_EX);
	@chmod($cache, 0600);
	return $jeton;
}

/**
 * Appel de la Directory API ; renvoie [code, corps]. Les erreurs de droits
 * et d'indisponibilite levent GoogleAdminErreur ; 404 et 409 sont rendus a
 * l'appelant, qui sait ce qu'ils veulent dire pour lui.
 *
 * @return array{0:int, 1:array}
 */
function googleAdminApi(string $methode, string $chemin, array $parametres = [], ?array $corps = null): array
{
	$url = GOOGLE_ADMIN_API . $chemin . ($parametres ? '?' . http_build_query($parametres) : '');
	$entetes = ['Authorization: Bearer ' . googleAdminJeton(), 'Accept: application/json'];
	if ($corps !== null) { $entetes[] = 'Content-Type: application/json; charset=utf-8'; }
	[$code, $r] = googleAdminHttp($methode, $url, $corps !== null ? json_encode($corps, JSON_UNESCAPED_UNICODE) : null, $entetes);
	if ($code === 401 || $code === 403) {
		throw new GoogleAdminErreur("L'administrateur emprunté (" . GOOGLE_ADMIN_SUJET . ") n'a pas le droit de faire cela dans Google Admin"
			. (isset($r['error']['message']) ? ' : ' . $r['error']['message'] : '') . '.');
	}
	if ($code === 429 || $code >= 500) {
		throw new GoogleAdminErreur('Google est momentanément indisponible (code ' . $code . ') : réessayez dans un instant.');
	}
	return [$code, $r];
}

/** Le compte (ou l'alias) existe-t-il dans Google ? null si Google ne repond pas. */
function googleAdminAdresseExiste(string $adresse): ?bool
{
	try {
		[$code] = googleAdminApi('GET', '/users/' . rawurlencode(strtolower(trim($adresse))), ['fields' => 'primaryEmail']);
		return $code === 200 ? true : ($code === 404 ? false : null);
	} catch (Throwable $e) {
		error_log('Google Admin : verification de ' . $adresse . ' impossible : ' . $e->getMessage());
		return null;
	}
}

/**
 * Tous les comptes du domaine, indexes par adresse (adresse principale et
 * alias). Garde cinq minutes en cache : la page de verification se recharge
 * sans refaire les appels.
 *
 * @return array{comptes: array<string, array>, lu_le: int}
 */
function googleAdminTousLesComptes(bool $forcer = false): array
{
	$cache = googleAdminDossierCache() . DIRECTORY_SEPARATOR . 'comptes.json';
	if (!$forcer) {
		$garde = @json_decode((string) @file_get_contents($cache), true);
		if (is_array($garde) && ($garde['lu_le'] ?? 0) > time() - 300) { return $garde; }
	}
	$comptes = [];
	$page = null;
	do {
		$parametres = [
			'domain' => GOOGLE_ADMIN_DOMAINE, 'maxResults' => 500, 'orderBy' => 'email', 'projection' => 'basic',
			'fields' => 'nextPageToken,users(primaryEmail,aliases,suspended,archived,lastLoginTime,creationTime,name/fullName,orgUnitPath)',
		];
		if ($page) { $parametres['pageToken'] = $page; }
		[$code, $r] = googleAdminApi('GET', '/users', $parametres);
		if ($code !== 200) { throw new GoogleAdminErreur('Google ne donne pas la liste des comptes (code ' . $code . ').'); }
		foreach ($r['users'] ?? [] as $u) {
			$principale = strtolower((string) $u['primaryEmail']);
			$derniere = (string) ($u['lastLoginTime'] ?? '');
			$fiche = [
				'adresse'   => $principale,
				'nom'       => (string) ($u['name']['fullName'] ?? ''),
				'suspendu'  => !empty($u['suspended']),
				'archive'   => !empty($u['archived']),
				'connexion' => ($derniere !== '' && strpos($derniere, '1970-') !== 0) ? $derniere : '',
				'cree_le'   => (string) ($u['creationTime'] ?? ''),
				'unite'     => (string) ($u['orgUnitPath'] ?? ''),
			];
			$comptes[$principale] = $fiche;
			foreach ($u['aliases'] ?? [] as $alias) {
				$comptes[strtolower((string) $alias)] = $fiche + ['alias_de' => $principale];
			}
		}
		$page = $r['nextPageToken'] ?? null;
	} while ($page);
	$resultat = ['comptes' => $comptes, 'lu_le' => time()];
	@file_put_contents($cache, json_encode($resultat, JSON_UNESCAPED_UNICODE), LOCK_EX);
	@chmod($cache, 0600);
	return $resultat;
}

/** Le cache de la liste ne vaut plus (un compte vient d'etre cree). */
function googleAdminOublierComptes(): void
{
	@unlink(googleAdminDossierCache() . DIRECTORY_SEPARATOR . 'comptes.json');
}

/**
 * Cree le compte Google d'un etudiant, comme le faisait l'import CSV
 * (src/genPDF/gen.mail_csv.php) : unite « / », le mot de passe de la fiche,
 * pas de changement impose a la premiere connexion, matricule, mention,
 * parcours et telephone. Ne leve jamais : la creation de l'etudiant ne doit
 * pas echouer pour Google.
 *
 * @param array{adresse:string, nom:string, prenom:string, mot_de_passe:string,
 *              matricule?:string, mention?:string, parcours?:string,
 *              telephone?:string, domicile?:string} $e
 * @return array{cree:bool, existait:bool, raison:string}
 */
function googleAdminCreerEtudiant(array $e): array
{
	$adresse = strtolower(trim((string) $e['adresse']));
	if (!googleAdminConfiguree()) {
		return ['cree' => false, 'existait' => false, 'raison' => "Compte Google non créé : l'API Google Admin n'est pas configurée."];
	}
	if (!preg_match("/^[a-z0-9._'-]+@" . preg_quote(GOOGLE_ADMIN_DOMAINE, '/') . '$/', $adresse)) {
		return ['cree' => false, 'existait' => false, 'raison' => "Compte Google non créé : « {$adresse} » n'est pas une adresse que Google accepte (accent, espace ou autre domaine)."];
	}
	$motDePasse = (string) $e['mot_de_passe'];
	if (strlen($motDePasse) < 8) {
		return ['cree' => false, 'existait' => false, 'raison' => 'Compte Google non créé : le mot de passe de la fiche fait moins de 8 caractères.'];
	}
	$propre = static fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v));
	$nom = mb_substr($propre($e['nom'] ?? ''), 0, 60);
	$prenom = mb_substr($propre($e['prenom'] ?? ''), 0, 60);
	$corps = [
		'primaryEmail' => $adresse,
		'name' => ['givenName' => $prenom !== '' ? $prenom : $nom, 'familyName' => $nom !== '' ? $nom : $prenom],
		'password' => $motDePasse,
		'changePasswordAtNextLogin' => false,
		'orgUnitPath' => '/',
		'organizations' => [[
			'name' => 'Université Adventiste Zurcher', 'title' => 'Student', 'description' => 'Student', 'primary' => true,
			'department' => $propre($e['mention'] ?? ''), 'costCenter' => $propre($e['parcours'] ?? ''),
		]],
	];
	if (($e['matricule'] ?? '') !== '') { $corps['externalIds'] = [['value' => (string) $e['matricule'], 'type' => 'organization']]; }
	$chiffres = preg_replace('/\D/', '', (string) ($e['telephone'] ?? ''));
	if (preg_match('/^(?:0|261)(\d{9})$/', $chiffres, $m)) { $corps['phones'] = [['value' => '+261' . $m[1], 'type' => 'mobile']]; }
	if ($propre($e['domicile'] ?? '') !== '') { $corps['addresses'] = [['type' => 'home', 'formatted' => $propre($e['domicile'])]]; }

	try {
		[$code, $r] = googleAdminApi('POST', '/users', [], $corps);
	} catch (Throwable $x) {
		error_log('Google Admin : creation de ' . $adresse . ' impossible : ' . $x->getMessage());
		return ['cree' => false, 'existait' => false, 'raison' => 'Compte Google non créé : ' . $x->getMessage()];
	}
	if ($code === 200 || $code === 201) {
		googleAdminOublierComptes();
		return ['cree' => true, 'existait' => false, 'raison' => "Compte Google créé : {$adresse} fonctionne dès maintenant, avec le mot de passe de la fiche."];
	}
	if ($code === 409) {
		return ['cree' => false, 'existait' => true, 'raison' => "{$adresse} existe déjà dans Google : son mot de passe peut différer de celui de la fiche."];
	}
	$detail = (string) ($r['error']['message'] ?? ('code ' . $code));
	error_log('Google Admin : creation de ' . $adresse . ' refusee : ' . $detail);
	return ['cree' => false, 'existait' => false, 'raison' => 'Compte Google non créé, Google répond : ' . $detail];
}

}
