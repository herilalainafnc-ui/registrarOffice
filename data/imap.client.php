<?php
/**
 * Client IMAP ecrit en PHP, sans l'extension imap.
 *
 * L'extension PHP imap n'est pas installee sur le serveur (et elle a quitte
 * PHP a partir de 8.4) : la boite Gmail de /settings/bulk-email ne s'est donc
 * jamais affichee. Ce client parle IMAP directement sur une connexion TLS
 * (imap.gmail.com:993) : connexion, dossiers, liste, lecture, drapeaux,
 * deplacement. Voir data/boite.gmail.php pour l'usage qu'en fait la page.
 *
 * Ce qu'il sait lire d'une reponse : atomes, chaines entre guillemets,
 * litteraux {n}, NIL, listes entre parentheses, et les sections du type
 * BODY[HEADER.FIELDS (FROM SUBJECT)]<0> qui contiennent des espaces.
 */

if (!class_exists('ImapClient')) {

final class ImapClient
{
	/** @var resource|null */
	private $flux = null;
	private int $numero = 0;
	private string $hote;
	private int $port;
	private int $delai;

	public function __construct(string $hote, int $port = 993, int $delai = 20)
	{
		$this->hote = $hote;
		$this->port = $port;
		$this->delai = $delai;
	}

	public function __destruct()
	{
		$this->fermer();
	}

	/** Connexion TLS et identification. */
	public function connecter(string $utilisateur, string $motDePasse): void
	{
		$options = ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->hote];
		$autorites = ini_get('openssl.cafile') ?: ini_get('curl.cainfo');
		if ($autorites) { $options['cafile'] = $autorites; }
		$flux = @stream_socket_client('ssl://' . $this->hote . ':' . $this->port, $errno, $errstr, $this->delai,
			STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $options]));
		if (!$flux) {
			throw new RuntimeException('Connexion au serveur de messagerie impossible : ' . ($errstr ?: 'delai depasse'));
		}
		stream_set_timeout($flux, $this->delai);
		$this->flux = $flux;
		if (!str_starts_with($this->lireLigne(), '* OK')) {
			throw new RuntimeException('Le serveur de messagerie a refuse la connexion.');
		}
		$this->commande('LOGIN ' . self::chaine($utilisateur) . ' ' . self::chaine($motDePasse));
	}

	public function fermer(): void
	{
		if ($this->flux) {
			@fwrite($this->flux, 'Z' . (++$this->numero) . " LOGOUT\r\n");
			@fclose($this->flux);
			$this->flux = null;
		}
	}

	/** Chaine IMAP entre guillemets. */
	public static function chaine(string $s): string
	{
		return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
	}

	/**
	 * Envoie une commande et rend ses reponses non etiquetees, chacune deja
	 * decoupee en elements (voir analyser()).
	 *
	 * $litteral : donnees envoyees en litteral synchronise a la fin de la
	 * commande (texte non ASCII d'une recherche, par exemple).
	 *
	 * @return array<int, array> reponses « * ... »
	 */
	public function commande(string $commande, ?string $litteral = null): array
	{
		if (!$this->flux) { throw new RuntimeException('Pas de connexion IMAP.'); }
		$etiquette = 'A' . (++$this->numero);
		if ($litteral === null) {
			$this->ecrire("$etiquette $commande\r\n");
		} else {
			$this->ecrire("$etiquette $commande {" . strlen($litteral) . "}\r\n");
			$ligne = $this->lireLigne();
			if (!str_starts_with($ligne, '+')) { throw new RuntimeException('Le serveur a refuse la commande : ' . trim($ligne)); }
			$this->ecrire($litteral . "\r\n");
		}

		$reponses = [];
		while (true) {
			$brut = $this->lireReponse();
			if (str_starts_with($brut, $etiquette . ' ')) {
				$statut = strtoupper(substr($brut, strlen($etiquette) + 1, 2));
				if ($statut !== 'OK') {
					throw new RuntimeException('Commande IMAP refusee : ' . trim(substr($brut, strlen($etiquette) + 1)));
				}
				return $reponses;
			}
			if (str_starts_with($brut, '*')) { $reponses[] = self::analyser($brut); }
		}
	}

	/**
	 * Plusieurs commandes envoyees d'un coup, sans attendre chaque reponse :
	 * avec 0,2 s d'aller-retour vers Gmail, c'est l'essentiel du temps gagne.
	 *
	 * @param string[] $commandes
	 * @return array<int, array{ok:bool, reponses:array, erreur:string}> dans l'ordre des commandes
	 */
	public function enRafale(array $commandes): array
	{
		if (!$this->flux) { throw new RuntimeException('Pas de connexion IMAP.'); }
		$etiquettes = [];
		$envoi = '';
		foreach (array_values($commandes) as $k => $commande) {
			$etiquettes[$k] = 'A' . (++$this->numero);
			$envoi .= $etiquettes[$k] . ' ' . $commande . "\r\n";
		}
		$this->ecrire($envoi);
		$sortie = [];
		$courant = [];
		$k = 0;
		while ($k < count($etiquettes)) {
			$brut = $this->lireReponse();
			if (str_starts_with($brut, $etiquettes[$k] . ' ')) {
				$statut = strtoupper(substr($brut, strlen($etiquettes[$k]) + 1, 2));
				$sortie[$k] = ['ok' => $statut === 'OK', 'reponses' => $courant, 'erreur' => $statut === 'OK' ? '' : trim(substr($brut, strlen($etiquettes[$k]) + 1))];
				$courant = [];
				$k++;
				continue;
			}
			if (str_starts_with($brut, '*')) { $courant[] = self::analyser($brut); }
		}
		return $sortie;
	}

	/** Les reponses FETCH d'une liste de reponses, en tableaux associatifs (voir lire()). */
	public static function messagesDe(array $reponses): array
	{
		$sortie = [];
		foreach ($reponses as $r) {
			if (($r[2] ?? '') !== 'FETCH' || !is_array($r[3] ?? null)) { continue; }
			$paires = $r[3];
			$message = ['SEQ' => (int) $r[1]];
			for ($k = 0; $k + 1 < count($paires); $k += 2) {
				$cle = preg_replace(['/^BODY\.PEEK/', '/<\d+>$/'], ['BODY', ''], strtoupper((string) $paires[$k]));
				$message[$cle] = $paires[$k + 1];
			}
			$sortie[] = $message;
		}
		return $sortie;
	}

	private function ecrire(string $donnees): void
	{
		$reste = $donnees;
		while ($reste !== '') {
			$n = @fwrite($this->flux, $reste);
			if ($n === false || $n === 0) { throw new RuntimeException('Connexion IMAP interrompue.'); }
			$reste = substr($reste, $n);
		}
	}

	private function lireLigne(): string
	{
		$ligne = fgets($this->flux);
		if ($ligne === false) {
			$meta = stream_get_meta_data($this->flux);
			throw new RuntimeException(!empty($meta['timed_out']) ? 'Le serveur de messagerie ne repond pas.' : 'Connexion IMAP interrompue.');
		}
		return $ligne;
	}

	/** Une reponse complete : ses lignes, litteraux compris. */
	private function lireReponse(): string
	{
		$reponse = '';
		while (true) {
			$ligne = $this->lireLigne();
			$reponse .= $ligne;
			if (!preg_match('/\{(\d+)\}\r\n$/', $ligne, $m)) { return $reponse; }
			$reste = (int) $m[1];
			while ($reste > 0) {
				$morceau = fread($this->flux, min(65536, $reste));
				if ($morceau === false || $morceau === '') {
					throw new RuntimeException('Connexion IMAP interrompue pendant la lecture.');
				}
				$reponse .= $morceau;
				$reste -= strlen($morceau);
			}
		}
	}

	/**
	 * Decoupe une reponse en elements : chaine, null (NIL) ou tableau (liste).
	 * Les litteraux {n} deviennent des chaines.
	 */
	public static function analyser(string $s): array
	{
		$i = 0;
		$n = strlen($s);
		$pile = [[]];
		while ($i < $n) {
			$c = $s[$i];
			if ($c === ' ' || $c === "\r" || $c === "\n") { $i++; continue; }
			if ($c === '(') { $pile[] = []; $i++; continue; }
			if ($c === ')') {
				$liste = array_pop($pile);
				if (!$pile) { $pile = [[]]; }
				$pile[count($pile) - 1][] = $liste;
				$i++;
				continue;
			}
			if ($c === '"') {
				$i++;
				$valeur = '';
				while ($i < $n && $s[$i] !== '"') {
					if ($s[$i] === '\\' && $i + 1 < $n) { $i++; }
					$valeur .= $s[$i];
					$i++;
				}
				$i++;
				$pile[count($pile) - 1][] = $valeur;
				continue;
			}
			if ($c === '{' && preg_match('/\G\{(\d+)\}\r\n/', $s, $m, 0, $i)) {
				$i += strlen($m[0]);
				$pile[count($pile) - 1][] = substr($s, $i, (int) $m[1]);
				$i += (int) $m[1];
				continue;
			}
			// Atome ; une section [...] peut contenir espaces et parentheses.
			$debut = $i;
			$crochets = 0;
			while ($i < $n) {
				$d = $s[$i];
				if ($d === '[') { $crochets++; }
				elseif ($d === ']') { $crochets--; }
				elseif ($crochets <= 0 && ($d === ' ' || $d === '(' || $d === ')' || $d === "\r" || $d === "\n")) { break; }
				$i++;
			}
			$atome = substr($s, $debut, $i - $debut);
			$pile[count($pile) - 1][] = strtoupper($atome) === 'NIL' ? null : $atome;
		}
		return $pile[0];
	}

	/* ------------------------------------------------------------ dossiers */

	/**
	 * @return array<int, array{nom:string, affiche:string, attributs:string[]}>
	 */
	public function dossiers(): array
	{
		$sortie = [];
		foreach ($this->commande('LIST "" "*"') as $r) {
			if (($r[1] ?? '') !== 'LIST' || !is_array($r[2] ?? null)) { continue; }
			$nom = (string) ($r[4] ?? '');
			$sortie[] = ['nom' => $nom, 'affiche' => self::utf7Decoder($nom), 'attributs' => array_map('strval', $r[2])];
		}
		return $sortie;
	}

	/** Ouvre un dossier ; rend le nombre de messages et la validite des UID. */
	public function ouvrir(string $dossier, bool $lectureSeule = true): array
	{
		return self::infosOuverture($this->commande(($lectureSeule ? 'EXAMINE ' : 'SELECT ') . self::chaine($dossier)));
	}

	/** Nombre de messages et validite des UID, dans les reponses d'un EXAMINE / SELECT. */
	public static function infosOuverture(array $reponses): array
	{
		$infos = ['messages' => 0, 'uidvalidity' => 0];
		foreach ($reponses as $r) {
			if (($r[2] ?? '') === 'EXISTS') { $infos['messages'] = (int) $r[1]; }
			if (($r[1] ?? '') === 'OK' && is_string($r[2] ?? null) && preg_match('/^\[UIDVALIDITY (\d+)\]/i', $r[2], $m)) {
				$infos['uidvalidity'] = (int) $m[1];
			}
		}
		return $infos;
	}

	/** STATUS d'un dossier sans l'ouvrir. */
	public function statut(string $dossier, array $elements = ['MESSAGES', 'UNSEEN']): array
	{
		return self::statutDe($this->commande('STATUS ' . self::chaine($dossier) . ' (' . implode(' ', $elements) . ')'));
	}

	/** Valeurs d'une reponse STATUS : ['MESSAGES' => n, 'UNSEEN' => n...]. */
	public static function statutDe(array $reponses): array
	{
		$sortie = [];
		foreach ($reponses as $r) {
			if (($r[1] ?? '') === 'STATUS' && is_array(end($r))) {
				$liste = end($r);
				for ($k = 0; $k + 1 < count($liste); $k += 2) { $sortie[strtoupper((string) $liste[$k])] = (int) $liste[$k + 1]; }
			}
		}
		return $sortie;
	}

	/** UID SEARCH ; rend les UID tries. $litteral pour un texte non ASCII. */
	public function chercher(string $criteres, ?string $litteral = null): array
	{
		$uids = [];
		foreach ($this->commande('UID SEARCH ' . $criteres, $litteral) as $r) {
			if (($r[1] ?? '') === 'SEARCH') {
				foreach (array_slice($r, 2) as $u) { if (is_string($u) && ctype_digit($u)) { $uids[] = (int) $u; } }
			}
		}
		sort($uids);
		return $uids;
	}

	/**
	 * FETCH ou UID FETCH : rend, par message, ses elements en tableau associatif
	 * (cles en majuscules : UID, FLAGS, BODYSTRUCTURE, BODY[...]...).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function lire(string $ensemble, string $elements, bool $parUid = true): array
	{
		return self::messagesDe($this->commande(($parUid ? 'UID FETCH ' : 'FETCH ') . $ensemble . ' (' . $elements . ')'));
	}

	public function drapeaux(int $uid, string $operation, string $drapeaux): void
	{
		$this->commande("UID STORE $uid {$operation}FLAGS.SILENT ($drapeaux)");
	}

	/** Deplace un message (Gmail connait MOVE). */
	public function deplacer(int $uid, string $dossier): void
	{
		$this->commande("UID MOVE $uid " . self::chaine($dossier));
	}

	/* ------------------------------------------------- decodage des messages */

	/** Nom de dossier IMAP (UTF-7 modifie) en UTF-8 : « Messages envoy&AOk-s ». */
	public static function utf7Decoder(string $nom): string
	{
		return preg_replace_callback('/&([^-]*)-/', static function ($m) {
			if ($m[1] === '') { return '&'; }
			$octets = base64_decode(str_replace(',', '/', $m[1]));
			return $octets === false ? $m[0] : (string) @mb_convert_encoding($octets, 'UTF-8', 'UTF-16BE');
		}, $nom);
	}

	/** Texte vers UTF-8 depuis un jeu de caracteres annonce (souvent faux ou vide). */
	public static function versUtf8(string $texte, string $charset = ''): string
	{
		$charset = strtoupper(trim($charset, " \"'"));
		if ($charset === '' || $charset === 'UTF-8' || $charset === 'UTF8' || $charset === 'US-ASCII') {
			if (mb_check_encoding($texte, 'UTF-8')) { return $texte; }
			/* Un extrait (lecture partielle) coupe souvent un caractere en deux :
			   sans cette retouche, tout le texte passait pour du Windows-1252
			   (« peut-Ãªtre »). */
			$fin = strlen($texte);
			for ($k = 1; $k <= min(3, $fin); $k++) {
				$octet = ord($texte[$fin - $k]);
				if ($octet < 0x80) { break; }                 // ASCII : rien de coupe
				if ($octet < 0xC0) { continue; }               // octet de suite : on remonte
				$attendu = $octet >= 0xF0 ? 4 : ($octet >= 0xE0 ? 3 : 2);
				if ($k < $attendu) {                           // tete d'un caractere incomplet
					$court = substr($texte, 0, $fin - $k);
					if (mb_check_encoding($court, 'UTF-8')) { return $court; }
				}
				break;
			}
			if ($charset !== '') { return (string) mb_convert_encoding($texte, 'UTF-8', 'UTF-8'); }
			$charset = 'WINDOWS-1252';
		}
		if ($charset === 'ISO-8859-1' || $charset === 'LATIN1') { $charset = 'WINDOWS-1252'; }
		try {
			$r = mb_convert_encoding($texte, 'UTF-8', $charset);
			if (is_string($r)) { return $r; }
		} catch (Throwable $e) {
			// jeu inconnu de mbstring : on tente iconv
		}
		$r = @iconv($charset, 'UTF-8//IGNORE', $texte);
		return is_string($r) && $r !== '' ? $r : mb_convert_encoding($texte, 'UTF-8', 'UTF-8');
	}

	/** En-tete encode (=?utf-8?B?...?=) en UTF-8. */
	public static function enTete(string $valeur): string
	{
		$valeur = trim(preg_replace('/\r?\n[ \t]+/', ' ', $valeur));
		if ($valeur === '') { return ''; }
		if (str_contains($valeur, '=?')) {
			$d = @iconv_mime_decode($valeur, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
			if (is_string($d) && $d !== '') { return $d; }
			$d = @mb_decode_mimeheader($valeur);
			if (is_string($d) && $d !== '') { return $d; }
		}
		return self::versUtf8($valeur);
	}

	/** Bloc d'en-tetes brut en tableau nom (minuscules) => valeur decodee. */
	public static function enTetes(string $bloc): array
	{
		$sortie = [];
		$bloc = preg_replace('/\r?\n[ \t]+/', ' ', $bloc);
		foreach (preg_split('/\r?\n/', $bloc) as $ligne) {
			if (!str_contains($ligne, ':')) { continue; }
			[$nom, $valeur] = explode(':', $ligne, 2);
			$nom = strtolower(trim($nom));
			if ($nom !== '' && !isset($sortie[$nom])) { $sortie[$nom] = self::enTete($valeur); }
		}
		return $sortie;
	}

	/**
	 * Adresses d'un en-tete (From, To...).
	 * @return array<int, array{nom:string, email:string}>
	 */
	public static function adresses(string $valeur): array
	{
		$sortie = [];
		foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $valeur) as $morceau) {
			$morceau = trim($morceau);
			if ($morceau === '') { continue; }
			if (preg_match('/^(.*)<([^>]+)>\s*$/', $morceau, $m)) {
				$sortie[] = ['nom' => trim($m[1], " \"'"), 'email' => trim($m[2])];
			} else {
				$sortie[] = ['nom' => '', 'email' => trim($morceau, ' <>')];
			}
		}
		return $sortie;
	}

	/** Corps decode selon son encodage de transfert. */
	public static function decoderCorps(string $corps, string $encodage): string
	{
		$encodage = strtoupper(trim($encodage));
		if ($encodage === 'BASE64') {
			$propre = preg_replace('/[^A-Za-z0-9+\/=]/', '', $corps);
			$propre = substr($propre, 0, intdiv(strlen($propre), 4) * 4); // un extrait peut couper un bloc
			return (string) base64_decode($propre);
		}
		if ($encodage === 'QUOTED-PRINTABLE') { return quoted_printable_decode($corps); }
		return $corps;
	}

	/**
	 * Les parties d'un BODYSTRUCTURE : textes (plain, html), pieces jointes et
	 * images integrees, avec leur numero de section.
	 *
	 * @return array<int, array{section:string, type:string, sous:string, encodage:string, charset:string, nom:string, taille:int, cid:string, piece:bool}>
	 */
	public static function parties($structure, string $prefixe = ''): array
	{
		if (!is_array($structure) || !$structure) { return []; }
		$sortie = [];
		if (is_array($structure[0])) {
			// multipart : sous-parties puis sous-type
			$k = 1;
			foreach ($structure as $element) {
				if (!is_array($element)) { break; }
				$section = $prefixe === '' ? (string) $k : "$prefixe.$k";
				$sortie = array_merge($sortie, self::parties($element, $section));
				$k++;
			}
			return $sortie;
		}
		$type = strtolower((string) ($structure[0] ?? ''));
		$sous = strtolower((string) ($structure[1] ?? ''));
		$params = self::paires($structure[2] ?? null);
		$cid = trim((string) ($structure[3] ?? ''), '<> ');
		$encodage = (string) ($structure[5] ?? '7BIT');
		$taille = (int) ($structure[6] ?? 0);
		$posDisposition = $type === 'text' ? 9 : ($type === 'message' && $sous === 'rfc822' ? 11 : 8);
		$disposition = $structure[$posDisposition] ?? null;
		$dispoType = is_array($disposition) ? strtolower((string) ($disposition[0] ?? '')) : '';
		$dispoParams = is_array($disposition) ? self::paires($disposition[1] ?? null) : [];
		$nom = $dispoParams['filename'] ?? $dispoParams['filename*'] ?? $params['name'] ?? $params['name*'] ?? '';
		$nom = self::nomFichier((string) $nom);
		$section = $prefixe === '' ? '1' : $prefixe;
		$piece = $dispoType === 'attachment' || ($nom !== '' && !($type === 'text' && $dispoType !== 'attachment'));
		$sortie[] = [
			'section' => $section, 'type' => $type, 'sous' => $sous, 'encodage' => $encodage,
			'charset' => (string) ($params['charset'] ?? ''), 'nom' => $nom, 'taille' => $taille,
			'cid' => $cid, 'piece' => $piece,
		];
		return $sortie;
	}

	private static function paires($liste): array
	{
		$sortie = [];
		if (!is_array($liste)) { return $sortie; }
		for ($k = 0; $k + 1 < count($liste); $k += 2) { $sortie[strtolower((string) $liste[$k])] = (string) $liste[$k + 1]; }
		return $sortie;
	}

	/** Nom de fichier encode (RFC 2047 ou RFC 2231 « utf-8''nom%20x.pdf »). */
	public static function nomFichier(string $nom): string
	{
		if (preg_match("/^([\\w-]+)'[^']*'(.*)$/", $nom, $m)) { return self::versUtf8(rawurldecode($m[2]), $m[1]); }
		return self::enTete($nom);
	}
}

}
