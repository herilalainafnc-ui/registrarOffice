<?php
/**
 * La boite Gmail de no-reply@zurcher.edu.mg dans /settings/bulk-email
 * (decision de l'utilisateur, 08/10/2026) : dossiers, liste, lecture, pieces
 * jointes et actions, par le client IMAP de data/imap.client.php.
 *
 * Identifiants : MAILBOX_IMAP_* de data/config.php (mot de passe
 * d'application Google). Registrariat seul (la page le verifie).
 *
 * Chaque requete ouvre sa connexion : la page ne garde rien en memoire sur le
 * serveur, et un mot de passe change prend effet tout de suite.
 */

require_once __DIR__ . '/imap.client.php';

if (!function_exists('boiteGmailConfiguree')) {

define('BOITE_GMAIL_PAR_PAGE', 50);

/** Les dossiers que la page connait, dans l'ordre de Gmail, et l'attribut IMAP qui les designe. */
function boiteGmailRoles(): array
{
	return [
		'inbox'     => ['libelle' => 'Boîte de réception', 'attribut' => null],
		'starred'   => ['libelle' => 'Messages suivis',    'attribut' => '\\Flagged'],
		'important' => ['libelle' => 'Important',          'attribut' => '\\Important'],
		'sent'      => ['libelle' => 'Messages envoyés',   'attribut' => '\\Sent'],
		'drafts'    => ['libelle' => 'Brouillons',         'attribut' => '\\Drafts'],
		'all'       => ['libelle' => 'Tous les messages',  'attribut' => '\\All'],
		'spam'      => ['libelle' => 'Spam',               'attribut' => '\\Junk'],
		'trash'     => ['libelle' => 'Corbeille',          'attribut' => '\\Trash'],
	];
}

function boiteGmailConfiguree(): bool
{
	return defined('MAILBOX_IMAP_ENABLED') && MAILBOX_IMAP_ENABLED === true
		&& trim((string) (defined('MAILBOX_IMAP_HOST') ? MAILBOX_IMAP_HOST : '')) !== ''
		&& trim((string) (defined('MAILBOX_IMAP_USERNAME') ? MAILBOX_IMAP_USERNAME : '')) !== ''
		&& (string) (defined('MAILBOX_IMAP_PASSWORD') ? MAILBOX_IMAP_PASSWORD : '') !== '';
}

function boiteGmailAdresse(): string
{
	return defined('MAILBOX_IMAP_USERNAME') ? (string) MAILBOX_IMAP_USERNAME : '';
}

/** Connexion identifiee, une par requete. */
function boiteGmailClient(): ImapClient
{
	static $client = null;
	if ($client === null) {
		$client = new ImapClient((string) MAILBOX_IMAP_HOST, (int) (defined('MAILBOX_IMAP_PORT') ? MAILBOX_IMAP_PORT : 993));
		$client->connecter((string) MAILBOX_IMAP_USERNAME, (string) MAILBOX_IMAP_PASSWORD);
	}
	return $client;
}

/**
 * Nom IMAP de chaque dossier, retrouve par ses attributs (les noms changent
 * avec la langue du compte : « [Gmail]/Messages envoyés »). Garde une heure
 * dans un fichier (pas en session : la page ferme sa session avant de parler
 * a Gmail, pour que ses requetes ne s'attendent pas les unes les autres).
 *
 * @return array<string, string> role => nom IMAP
 */
function boiteGmailDossiers(ImapClient $c): array
{
	$fichier = boiteGmailCacheFichier('dossiers', 0);
	$cache = boiteGmailCacheLire($fichier);
	if (($cache['t'] ?? 0) > time() - 3600 && !empty($cache['d'])) { return $cache['d']; }
	$noms = ['inbox' => 'INBOX'];
	foreach ($c->dossiers() as $d) {
		foreach (boiteGmailRoles() as $role => $r) {
			if ($r['attribut'] !== null && in_array($r['attribut'], $d['attributs'], true)) { $noms[$role] = $d['nom']; }
		}
	}
	boiteGmailCacheEcrire($fichier, ['t' => time(), 'd' => $noms]);
	return $noms;
}

/** Les commandes STATUS des compteurs du menu : non lus, brouillons, spam non lus. */
function boiteGmailCommandesCompteurs(array $dossiers): array
{
	$sortie = [];
	foreach (['inbox' => 'UNSEEN', 'drafts' => 'MESSAGES', 'spam' => 'UNSEEN'] as $role => $element) {
		if (isset($dossiers[$role])) { $sortie[$role] = ['STATUS ' . ImapClient::chaine($dossiers[$role]) . " ($element)", $element]; }
	}
	return $sortie;
}

/* -----------------------------------------------------------------------------
   Cache de ce qui ne change pas pour un message donne (expediteur, objet,
   date, extrait, pieces jointes) : un UID ne designe jamais qu'un seul
   message tant que la validite du dossier ne change pas. Seuls les drapeaux
   (lu, suivi) sont relus a chaque affichage. Fichiers lisibles du seul
   serveur web (0600), hors du site.
   -------------------------------------------------------------------------- */

function boiteGmailCacheFichier(string $dossier, int $uidvalidity): string
{
	$rep = sys_get_temp_dir() . '/uaz-boite-gmail';
	if (!is_dir($rep)) { @mkdir($rep, 0700, true); }
	return $rep . '/' . sha1(boiteGmailAdresse() . '|' . $dossier . '|' . $uidvalidity) . '.json';
}

function boiteGmailCacheLire(string $fichier): array
{
	$brut = @file_get_contents($fichier);
	$cache = $brut ? json_decode($brut, true) : null;
	return is_array($cache) ? $cache : [];
}

function boiteGmailCacheEcrire(string $fichier, array $cache): void
{
	if (count($cache) > 3000) { krsort($cache); $cache = array_slice($cache, 0, 3000, true); }
	$provisoire = $fichier . '.' . getmypid();
	if (@file_put_contents($provisoire, json_encode($cache, JSON_UNESCAPED_UNICODE)) !== false) {
		@chmod($provisoire, 0600);
		@rename($provisoire, $fichier);
	}
}

/** La meilleure partie texte d'un message, pour l'extrait : le texte brut, sinon le HTML. */
function boiteGmailPartieExtrait(array $parties): ?array
{
	foreach (['plain', 'html'] as $sous) {
		foreach ($parties as $p) {
			if ($p['type'] === 'text' && $p['sous'] === $sous && !$p['piece']) { return $p; }
		}
	}
	return null;
}

/** Texte lisible d'un morceau de corps : HTML retire, espaces resserres. */
function boiteGmailTexteSimple(string $texte, bool $html): string
{
	if ($html) {
		$texte = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', ' ', $texte);
		$texte = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</div>#i', ' ', $texte)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	return trim(preg_replace('/\s+/u', ' ', $texte));
}

function boiteGmailDate(string $interne): string
{
	try { return (new DateTimeImmutable($interne))->format(DATE_ATOM); } catch (Throwable $e) { return ''; }
}

/** En-tetes et structure des messages absents du cache : ce que Gmail rend vite. */
function boiteGmailCompleterCache(ImapClient $c, array &$cache, array $uids): void
{
	if (!$uids) { return; }
	foreach ($c->lire(implode(',', $uids), 'UID INTERNALDATE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)]') as $m) {
		$uid = (int) ($m['UID'] ?? 0);
		$h = ImapClient::enTetes((string) ($m['BODY[HEADER.FIELDS (FROM TO SUBJECT DATE)]'] ?? ''));
		$parties = ImapClient::parties($m['BODYSTRUCTURE'] ?? null);
		$p = boiteGmailPartieExtrait($parties);
		$cache[$uid] = [
			'de' => ImapClient::adresses($h['from'] ?? '')[0] ?? ['nom' => '', 'email' => ''],
			'a' => array_slice(ImapClient::adresses($h['to'] ?? ''), 0, 5),
			'objet' => $h['subject'] ?? '',
			'date' => boiteGmailDate((string) ($m['INTERNALDATE'] ?? '')),
			'pieces' => array_values(array_map(static fn ($q) => $q['nom'], array_filter($parties, static fn ($q) => $q['piece'] && $q['cid'] === ''))),
			/* null : extrait pas encore lu (voir boiteGmailComplements) */
			'extrait' => $p ? null : '',
			'partie' => $p ? ['section' => $p['section'], 'sous' => $p['sous'], 'encodage' => $p['encodage'], 'charset' => $p['charset']] : null,
		];
	}
}

/**
 * Une page de messages d'un dossier, ou d'une recherche (dans tous les
 * messages, comme Gmail). Rien n'est marque lu.
 *
 * Les extraits ne sont pas lus ici : la premiere lecture d'un corps coute
 * a Gmail environ 0,2 s par message (9 s pour une page). La page s'affiche
 * avec expediteur, objet et date ; boiteGmailComplements() apporte ensuite
 * les extraits manquants et les compteurs du menu.
 */
function boiteGmailListe(string $role, int $page = 0, string $recherche = ''): array
{
	$c = boiteGmailClient();
	$dossiers = boiteGmailDossiers($c);
	$recherche = trim($recherche);
	if ($recherche !== '') { $role = 'all'; }
	if (!isset($dossiers[$role])) { $role = 'inbox'; }
	$parPage = BOITE_GMAIL_PAR_PAGE;
	$page = max(0, $page);

	$infos = $c->ouvrir($dossiers[$role], true);

	/* Les UID de la page, avec leurs drapeaux (seule chose qui change). */
	if ($recherche === '') {
		$total = $infos['messages'];
		$haut = $total - $page * $parPage;
		$drapeaux = $haut >= 1 ? $c->lire(max(1, $haut - $parPage + 1) . ':' . $haut, 'UID FLAGS', false) : [];
	} else {
		/* La recherche de Gmail elle-meme (X-GM-RAW) : memes mots-cles que la
		   barre de Gmail (from:, has:attachment...). Texte non ASCII : en
		   litteral, avec CHARSET UTF-8. */
		$uids = preg_match('/^[\x20-\x7E]*$/', $recherche)
			? $c->chercher('X-GM-RAW ' . ImapClient::chaine($recherche))
			: $c->chercher('CHARSET UTF-8 X-GM-RAW', $recherche);
		$total = count($uids);
		$tranche = array_slice(array_reverse($uids), $page * $parPage, $parPage);
		$drapeaux = $tranche ? $c->lire(implode(',', $tranche), 'UID FLAGS') : [];
	}

	$fichier = boiteGmailCacheFichier($dossiers[$role], $infos['uidvalidity']);
	$cache = boiteGmailCacheLire($fichier);
	$manquants = [];
	foreach ($drapeaux as $d) {
		$uid = (int) ($d['UID'] ?? 0);
		if ($uid > 0 && !isset($cache[$uid])) { $manquants[] = $uid; }
	}
	if ($manquants) {
		boiteGmailCompleterCache($c, $cache, $manquants);
		boiteGmailCacheEcrire($fichier, $cache);
	}

	$items = [];
	foreach ($drapeaux as $d) {
		$uid = (int) ($d['UID'] ?? 0);
		if (!isset($cache[$uid])) { continue; }
		$f = array_map('strval', (array) ($d['FLAGS'] ?? []));
		$entree = $cache[$uid];
		unset($entree['partie']);
		$items[] = $entree + [
			'uid' => $uid,
			'lu' => in_array('\\Seen', $f, true),
			'suivi' => in_array('\\Flagged', $f, true),
		];
	}
	usort($items, static fn ($x, $y) => strcmp($y['date'], $x['date']) ?: $y['uid'] <=> $x['uid']);

	return [
		'dossier' => $role,
		'libelle' => boiteGmailRoles()[$role]['libelle'],
		'recherche' => $recherche,
		'total' => $total,
		'page' => $page,
		'parPage' => $parPage,
		'items' => $items,
		'dossiersPresents' => array_keys($dossiers),
		'adresse' => boiteGmailAdresse(),
	];
}

/**
 * Ce que la liste n'a pas attendu : les extraits des messages demandes
 * (lus une fois, gardes en cache) et les compteurs du menu.
 *
 * @param int[] $uids
 * @return array{extraits: array<int,string>, compteurs: array<string,int>}
 */
function boiteGmailComplements(string $role, array $uids): array
{
	$c = boiteGmailClient();
	$dossiers = boiteGmailDossiers($c);
	if (!isset($dossiers[$role])) { $role = 'inbox'; }
	$uids = array_slice(array_values(array_unique(array_filter(array_map('intval', $uids)))), 0, 100);

	$compteursCmd = boiteGmailCommandesCompteurs($dossiers);
	$rafale = $c->enRafale(array_merge(['EXAMINE ' . ImapClient::chaine($dossiers[$role])], array_column($compteursCmd, 0)));
	$compteurs = ['inbox' => 0, 'drafts' => 0, 'spam' => 0];
	$k = 1;
	foreach ($compteursCmd as $r => [, $element]) {
		$compteurs[$r] = (int) (ImapClient::statutDe($rafale[$k]['reponses'] ?? [])[$element] ?? 0);
		$k++;
	}
	if (!$rafale[0]['ok'] || !$uids) { return ['extraits' => [], 'compteurs' => $compteurs]; }

	$fichier = boiteGmailCacheFichier($dossiers[$role], ImapClient::infosOuverture($rafale[0]['reponses'])['uidvalidity']);
	$cache = boiteGmailCacheLire($fichier);
	boiteGmailCompleterCache($c, $cache, array_values(array_filter($uids, static fn ($u) => !isset($cache[$u]))));

	/* Une lecture partielle par section commune ; texte brut : 800 octets
	   suffisent, HTML : davantage, l'en-tete <style> passe avant le texte. */
	$groupes = [];
	foreach ($uids as $uid) {
		$e = $cache[$uid] ?? null;
		if (!$e || $e['extrait'] !== null || empty($e['partie'])) { continue; }
		$p = $e['partie'];
		$groupes[$p['section'] . '<0.' . ($p['sous'] === 'plain' ? 800 : 4000) . '>'][] = $uid;
	}
	$cles = array_keys($groupes);
	$commandes = array_map(static fn ($cle) => 'UID FETCH ' . implode(',', $groupes[$cle]) . ' (BODY.PEEK[' . strstr($cle, '<', true) . ']' . strstr($cle, '<') . ')', $cles);
	foreach (($commandes ? $c->enRafale($commandes) : []) as $k => $resultat) {
		$section = strstr($cles[$k], '<', true);
		foreach (ImapClient::messagesDe($resultat['reponses']) as $r) {
			$uid = (int) ($r['UID'] ?? 0);
			if (!isset($cache[$uid]['partie'])) { continue; }
			$p = $cache[$uid]['partie'];
			$texte = ImapClient::versUtf8(ImapClient::decoderCorps((string) ($r['BODY[' . $section . ']'] ?? ''), $p['encodage']), $p['charset']);
			$cache[$uid]['extrait'] = mb_substr(boiteGmailTexteSimple($texte, $p['sous'] === 'html'), 0, 200);
		}
	}
	if ($commandes) { boiteGmailCacheEcrire($fichier, $cache); }

	$extraits = [];
	foreach ($uids as $uid) { $extraits[$uid] = (string) ($cache[$uid]['extrait'] ?? ''); }
	return ['extraits' => $extraits, 'compteurs' => $compteurs];
}

/** Document HTML du message pour une iframe isolee (sans scripts). */
function boiteGmailDocument(string $corps): string
{
	$corps = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $corps);
	$corps = preg_replace('#<meta\b[^>]*http-equiv[^>]*>#i', '', $corps);
	return '<!doctype html><html><head><meta charset="utf-8">'
		. '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'; object-src \'none\'; frame-src \'none\'">'
		. '<base target="_blank">'
		. '<style>html,body{margin:0;padding:0}body{font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#222;word-wrap:break-word;overflow-wrap:anywhere}'
		. 'img{max-width:100%;height:auto}pre{white-space:pre-wrap;font-family:inherit;margin:0}blockquote{margin:0 0 0 .8ex;border-left:1px solid #ccc;padding-left:1ex}a{color:#1a73e8}</style>'
		. '</head><body>' . $corps . '</body></html>';
}

/** Texte brut en HTML : echappe, liens actifs, retours a la ligne gardes. */
function boiteGmailTexteEnHtml(string $texte): string
{
	$html = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
	$html = preg_replace('#\bhttps?://[^\s<]+#i', '<a href="$0">$0</a>', $html);
	return '<pre>' . $html . '</pre>';
}

/**
 * Un message complet, marque lu (comme a l'ouverture dans Gmail).
 */
function boiteGmailMessage(string $role, int $uid): array
{
	$c = boiteGmailClient();
	$dossiers = boiteGmailDossiers($c);
	if (!isset($dossiers[$role])) { throw new RuntimeException('Dossier inconnu.'); }
	$rafale = $c->enRafale([
		'SELECT ' . ImapClient::chaine($dossiers[$role]),
		"UID FETCH $uid (UID FLAGS INTERNALDATE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO CC REPLY-TO SUBJECT DATE MESSAGE-ID)])",
	]);
	if (!$rafale[0]['ok']) { throw new RuntimeException('Dossier inaccessible : ' . $rafale[0]['erreur']); }
	$m = ImapClient::messagesDe($rafale[1]['reponses'] ?? [])[0] ?? null;
	if (!$m) { throw new RuntimeException('Message introuvable.'); }
	$h = ImapClient::enTetes((string) ($m['BODY[HEADER.FIELDS (FROM TO CC REPLY-TO SUBJECT DATE MESSAGE-ID)]'] ?? ''));
	$parties = ImapClient::parties($m['BODYSTRUCTURE'] ?? null);

	$html = null; $texte = null;
	foreach ($parties as $p) {
		if ($p['type'] !== 'text' || $p['piece']) { continue; }
		if ($p['sous'] === 'html' && $html === null) { $html = $p; }
		if ($p['sous'] === 'plain' && $texte === null) { $texte = $p; }
	}
	$aLire = array_filter([$html, $texte]);
	$corps = [];
	if ($aLire) {
		$r = $c->lire((string) $uid, implode(' ', array_map(static fn ($p) => 'BODY.PEEK[' . $p['section'] . ']', $aLire)))[0] ?? [];
		foreach ($aLire as $p) {
			$corps[$p['section']] = ImapClient::versUtf8(ImapClient::decoderCorps((string) ($r['BODY[' . $p['section'] . ']'] ?? ''), $p['encodage']), $p['charset']);
		}
	}

	/* Images integrees (cid:) : en data: dans le document, jusqu'a 4 Mo. */
	$contenu = $html ? ($corps[$html['section']] ?? '') : ($texte ? boiteGmailTexteEnHtml($corps[$texte['section']] ?? '') : '');
	$integrees = [];
	if ($html) {
		$budget = 4 * 1024 * 1024;
		foreach ($parties as $p) {
			if ($p['cid'] === '' || $p['type'] !== 'image' || stripos($contenu, 'cid:' . $p['cid']) === false || $p['taille'] > $budget) { continue; }
			$r = $c->lire((string) $uid, 'BODY.PEEK[' . $p['section'] . ']')[0] ?? [];
			$donnees = ImapClient::decoderCorps((string) ($r['BODY[' . $p['section'] . ']'] ?? ''), $p['encodage']);
			$budget -= strlen($donnees);
			$contenu = str_ireplace('cid:' . $p['cid'], 'data:image/' . $p['sous'] . ';base64,' . base64_encode($donnees), $contenu);
			$integrees[] = $p['section'];
		}
	}

	$pieces = [];
	foreach ($parties as $p) {
		if (!$p['piece'] || in_array($p['section'], $integrees, true)) { continue; }
		$pieces[] = ['section' => $p['section'], 'nom' => $p['nom'] !== '' ? $p['nom'] : 'piece-' . $p['section'], 'type' => $p['type'] . '/' . $p['sous'], 'taille' => $p['taille']];
	}

	$drapeaux = array_map('strval', (array) ($m['FLAGS'] ?? []));
	if (!in_array('\\Seen', $drapeaux, true)) {
		try { $c->drapeaux($uid, '+', '\\Seen'); } catch (Throwable $e) { /* lecture seule : tant pis */ }
	}

	$de = ImapClient::adresses($h['from'] ?? '')[0] ?? ['nom' => '', 'email' => ''];
	$repondreA = ImapClient::adresses($h['reply-to'] ?? '')[0]['email'] ?? $de['email'];
	return [
		'uid' => $uid,
		'dossier' => $role,
		'objet' => $h['subject'] ?? '',
		'de' => $de,
		'a' => ImapClient::adresses($h['to'] ?? ''),
		'cc' => ImapClient::adresses($h['cc'] ?? ''),
		'repondreA' => $repondreA,
		'date' => boiteGmailDate((string) ($m['INTERNALDATE'] ?? '')),
		'suivi' => in_array('\\Flagged', $drapeaux, true),
		'document' => boiteGmailDocument($contenu !== '' ? $contenu : '<p style="color:#5f6368"><em>Ce message n’a pas de contenu lisible.</em></p>'),
		'texte' => $texte ? mb_substr($corps[$texte['section']] ?? '', 0, 20000) : boiteGmailTexteSimple($contenu, true),
		'pieces' => $pieces,
	];
}

/** Une piece jointe : nom, type et contenu. */
function boiteGmailPiece(string $role, int $uid, string $section): array
{
	if (!preg_match('/^\d+(\.\d+)*$/', $section)) { throw new RuntimeException('Section invalide.'); }
	$c = boiteGmailClient();
	$dossiers = boiteGmailDossiers($c);
	if (!isset($dossiers[$role])) { throw new RuntimeException('Dossier inconnu.'); }
	$c->ouvrir($dossiers[$role], true);
	$m = $c->lire((string) $uid, 'BODYSTRUCTURE')[0] ?? null;
	foreach (ImapClient::parties($m['BODYSTRUCTURE'] ?? null) as $p) {
		if ($p['section'] !== $section) { continue; }
		$r = $c->lire((string) $uid, 'BODY.PEEK[' . $section . ']')[0] ?? [];
		return [
			'nom' => $p['nom'] !== '' ? $p['nom'] : 'piece-' . $section,
			'type' => $p['type'] . '/' . $p['sous'],
			'donnees' => ImapClient::decoderCorps((string) ($r['BODY[' . $section . ']'] ?? ''), $p['encodage']),
		];
	}
	throw new RuntimeException('Piece jointe introuvable.');
}

/**
 * Action sur un message : suivre, ne plus suivre, lu, non lu, archiver
 * (retire de la boite de reception), corbeille, restaurer (de la corbeille
 * vers la boite de reception).
 */
function boiteGmailAction(string $role, int $uid, string $action): void
{
	$c = boiteGmailClient();
	$dossiers = boiteGmailDossiers($c);
	if (!isset($dossiers[$role])) { throw new RuntimeException('Dossier inconnu.'); }
	$c->ouvrir($dossiers[$role], false);
	switch ($action) {
		case 'suivre':    $c->drapeaux($uid, '+', '\\Flagged'); break;
		case 'nesuivre':  $c->drapeaux($uid, '-', '\\Flagged'); break;
		case 'lu':        $c->drapeaux($uid, '+', '\\Seen'); break;
		case 'nonlu':     $c->drapeaux($uid, '-', '\\Seen'); break;
		case 'archiver':
			if ($role !== 'inbox' || !isset($dossiers['all'])) { throw new RuntimeException('Seuls les messages de la boîte de réception s’archivent.'); }
			$c->deplacer($uid, $dossiers['all']);
			break;
		case 'corbeille':
			if (!isset($dossiers['trash']) || $role === 'trash') { throw new RuntimeException('Déjà dans la corbeille.'); }
			$c->deplacer($uid, $dossiers['trash']);
			break;
		case 'restaurer':
			if ($role !== 'trash') { throw new RuntimeException('Seuls les messages de la corbeille se restaurent.'); }
			$c->deplacer($uid, $dossiers['inbox']);
			break;
		default:
			throw new RuntimeException('Action inconnue.');
	}
}

}
