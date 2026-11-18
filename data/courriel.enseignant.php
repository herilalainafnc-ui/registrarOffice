<?php
/**
 * Les e-mails des enseignants (src/bulk-email.php). Demande du 19/10/2026 :
 * « ajouter la fonctionnalité d'envoi de mail pour les profs, mais ils vont
 * utiliser l'adresse email avec laquelle ils se sont authentifiés, pas
 * no-reply ».
 *
 * Pour un enseignant, ou un chef de mention (qui enseigne), la page garde la
 * fenetre d'envoi du registrariat, avec :
 *   - l'expediteur : son adresse Google de connexion, et son nom ;
 *   - des personnes : ses etudiants de l'annee, les adresses @zurcher.edu.mg
 *     des enseignants et du personnel, ou toute adresse tapee. Le message part
 *     comme dans Gmail : chacun voit les autres destinataires ;
 *   - ou ses etudiants : ceux d'un de ses cours, ou de tous, en copie cachee
 *     par lots de 100 (aucun ne voit l'adresse des autres) ;
 *   - l'envoi par l'API Gmail (data/gmail.envoi.php) : le message est dans ses
 *     « Messages envoyes », les reponses arrivent dans sa boite.
 *
 * Comme ailleurs dans l'espace enseignant, rien hors de ses propres cours : la
 * recherche ne parcourt pas la liste de tous les etudiants
 * (BaseController::refuserEnseignant).
 */

require_once __DIR__ . '/gmail.envoi.php';
require_once __DIR__ . '/remise.notes.php';
require_once __DIR__ . '/cours.sigle.php';

if (!function_exists('courrielEnseignantExpediteur')) {

/** Copie cachee : au plus 100 adresses par message. */
define('COURRIEL_ENSEIGNANT_LOT', 100);
/** Des personnes : au plus 100 sur un message, toutes visibles. */
define('COURRIEL_ENSEIGNANT_MAX_PERSONNES', 100);
/** Ses etudiants : au plus 500 par envoi (Gmail : 2 000 messages par jour et par compte). */
define('COURRIEL_ENSEIGNANT_MAX_ETUDIANTS', 500);

/**
 * Qui envoie : l'adresse Google de la connexion ('' sans connexion Google) et
 * le nom de la fiche enseignant, a defaut celui du compte.
 *
 * @return array{adresse:string, nom:string}
 */
function courrielEnseignantExpediteur(PDO $dtb, ?array $utilisateur): array
{
	$adresse = gmailAdresseConnexion();
	$nom = '';
	$uid = courrielEnseignantUid();
	if ($uid > 0) {
		try {
			$stmt = $dtb->prepare("SELECT TRIM(CONCAT_WS(' ', NULLIF(TRIM(name), ''), NULLIF(TRIM(lastName), ''))) FROM teacher WHERE uid = :uid LIMIT 1");
			$stmt->execute(['uid' => $uid]);
			$nom = trim((string) $stmt->fetchColumn());
		} catch (Throwable $e) {
			$nom = '';
		}
	}
	if ($nom === '') { $nom = trim((string) ($utilisateur['prenom'] ?? '') . ' ' . (string) ($utilisateur['nom'] ?? '')); }
	if ($nom === '') { $nom = $adresse; }
	return ['adresse' => $adresse, 'nom' => preg_replace('/\s+/u', ' ', $nom)];
}

function courrielEnseignantUid(): int
{
	return function_exists('getTeacherUid') ? (int) getTeacherUid() : 0;
}

/** L'annee des cours proposes : celle des feuilles de notes et de presence. */
function courrielEnseignantAnnee(): string
{
	return remiseAnneeCourante();
}

/**
 * Les cours de l'enseignant qui ont des inscrits cette annee, avec leur
 * nombre d'etudiants joignables. Memes inscrits que la feuille de notes et le
 * registre de presence : ni suspendus, ni retires de l'universite.
 *
 * @return list<array{id:int, sigle:string, titre:string, semestre:int, etudiants:int, sans_adresse:int}>
 */
function courrielEnseignantCours(PDO $dtb): array
{
	$uid = courrielEnseignantUid();
	if ($uid <= 0) { return []; }
	$stmt = $dtb->prepare("SELECT c.*, k.inscrits, k.avec_adresse
		FROM t_2023_cours c
		JOIN (
			SELECT n.id_cours, COUNT(DISTINCT n.student_id) AS inscrits,
			       COUNT(DISTINCT CASE WHEN TRIM(COALESCE(e.student_email, '')) <> '' THEN n.student_id END) AS avec_adresse
			FROM t_2023_notes n
			LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
			WHERE n.annee_scolaire = :annee AND n.remove = 0
			  AND n.id_cours IN (SELECT id FROM t_2023_cours WHERE id_teacher = :uid AND remove != 1)
			  AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
			GROUP BY n.id_cours
		) k ON k.id_cours = c.id
		WHERE c.id_teacher = :uid2 AND c.remove != 1
		ORDER BY c.semester DESC, c.title ASC");
	$stmt->execute(['annee' => courrielEnseignantAnnee(), 'uid' => $uid, 'uid2' => $uid]);
	$cours = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
		$cours[] = [
			'id' => (int) $c['id'],
			'sigle' => coursSigleAffiche($c),
			'titre' => trim((string) ($c['title'] ?? '')),
			'semestre' => (int) ($c['semester'] ?? 0),
			'etudiants' => (int) $c['avec_adresse'],
			'sans_adresse' => (int) $c['inscrits'] - (int) $c['avec_adresse'],
		];
	}
	return $cours;
}

/**
 * Les etudiants de l'enseignant cette annee, d'un cours ($coursId) ou de tous
 * (0), avec une adresse. Un cours qui n'est pas le sien ne donne personne.
 *
 * @return list<array{email:string, nom:string, matricule:string}>
 */
function courrielEnseignantEtudiants(PDO $dtb, int $coursId = 0): array
{
	$uid = courrielEnseignantUid();
	if ($uid <= 0) { return []; }
	$params = ['annee' => courrielEnseignantAnnee(), 'uid' => $uid];
	$clauseCours = '';
	if ($coursId > 0) {
		$clauseCours = ' AND n.id_cours = :cours';
		$params['cours'] = $coursId;
	}
	$stmt = $dtb->prepare("SELECT n.student_id, MAX(e.student_nom) AS nom, MAX(e.student_prenom) AS prenom, MAX(TRIM(e.student_email)) AS email
		FROM t_2023_notes n
		JOIN t_2023_cours c ON c.id = n.id_cours AND c.id_teacher = :uid AND c.remove != 1
		LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
		WHERE n.annee_scolaire = :annee AND n.remove = 0" . $clauseCours . "
		  AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
		GROUP BY n.student_id
		ORDER BY nom ASC, prenom ASC, n.student_id ASC");
	$stmt->execute($params);
	$etudiants = [];
	$vus = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
		$email = strtolower(trim((string) ($l['email'] ?? '')));
		if ($email === '' || isset($vus[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; }
		$vus[$email] = true;
		$etudiants[] = [
			'email' => $email,
			'nom' => trim(preg_replace('/\s+/u', ' ', (string) ($l['nom'] ?? '') . ' ' . (string) ($l['prenom'] ?? ''))),
			'matricule' => trim((string) ($l['student_id'] ?? '')),
		];
	}
	return $etudiants;
}

/**
 * La recherche du champ « À » : ses etudiants de l'annee (nom, matricule ou
 * adresse), puis les enseignants et le personnel, par leur adresse
 * @zurcher.edu.mg seulement (l'annuaire de l'universite, pas les adresses
 * personnelles des fiches).
 *
 * @return list<array{name:string, email:string, source:string}>
 */
function courrielEnseignantRecherche(PDO $dtb, string $q): array
{
	$q = trim($q);
	if (mb_strlen($q) < 2) { return []; }
	$cherche = mb_strtolower($q);
	$items = [];
	$vus = [];
	$ajouter = static function (string $nom, string $email, string $source) use (&$items, &$vus) {
		$email = strtolower(trim($email));
		if ($email === '' || isset($vus[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return; }
		$vus[$email] = true;
		$items[] = ['name' => $nom, 'email' => $email, 'source' => $source];
	};
	foreach (courrielEnseignantEtudiants($dtb) as $e) {
		if (mb_strpos(mb_strtolower($e['nom'] . ' ' . $e['email'] . ' ' . $e['matricule']), $cherche) !== false) {
			$ajouter($e['nom'], $e['email'], 'Étudiant');
		}
	}

	$like = '%' . $q . '%';
	$domaine = '%@' . GOOGLE_ADMIN_DOMAINE;
	try {
		$stmt = $dtb->prepare("SELECT CONCAT(TRIM(lastName), ' ', TRIM(name)) AS nom, TRIM(email) AS email
			FROM teacher
			WHERE remove != 1 AND LOWER(TRIM(email)) LIKE :domaine
			  AND (lastName LIKE :q1 OR name LIKE :q2 OR email LIKE :q3)
			ORDER BY lastName, name LIMIT 15");
		$stmt->execute(['domaine' => $domaine, 'q1' => $like, 'q2' => $like, 'q3' => $like]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) { $ajouter(trim((string) $l['nom']), (string) $l['email'], 'Professeur'); }
	} catch (Throwable $e) { /* la recherche des etudiants reste */ }
	try {
		/* Le personnel : pas les comptes etudiants, qui ne s'ouvrent qu'aux siens. */
		$stmt = $dtb->prepare("SELECT CONCAT(TRIM(nom), ' ', TRIM(prenom)) AS nom, TRIM(mail) AS email
			FROM compt_utilisateur
			WHERE etat = 1 AND LOWER(TRIM(mail)) LIKE :domaine
			  AND COALESCE(user_type, '') <> 'student' AND COALESCE(level, 0) <> :niveauEtudiant
			  AND (nom LIKE :q1 OR prenom LIKE :q2 OR mail LIKE :q3)
			ORDER BY nom, prenom LIMIT 15");
		$stmt->execute(['domaine' => $domaine, 'niveauEtudiant' => defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8,
			'q1' => $like, 'q2' => $like, 'q3' => $like]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) { $ajouter(trim((string) $l['nom']), (string) $l['email'], 'Personnel'); }
	} catch (Throwable $e) { /* idem */ }
	return array_slice($items, 0, 25);
}

/**
 * Le message MIME complet, prepare par PHPMailer sans rien envoyer : Gmail
 * l'envoie ensuite tel quel (gmailEnvoyer).
 *
 * @param array{adresse:string, nom:string} $de
 * @param list<string> $a    destinataires visibles (« À »)
 * @param list<string> $cci  destinataires en copie cachee
 * @param list<array{data:string, cid:string, nom:string, type:string}> $images  images du corps (cid:)
 * @param list<array{data:string, name:string, mime:string}> $pieces            pieces jointes
 */
function courrielEnseignantMime(array $de, array $a, array $cci, string $objet, string $html, string $texte, array $images, array $pieces): string
{
	if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
		$src = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/vendor/phpmailer/phpmailer/src';
		require_once $src . '/Exception.php';
		require_once $src . '/PHPMailer.php';
		require_once $src . '/SMTP.php';
	}
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	/* Comme pour SMTP : en-tetes To et Subject, fins de ligne CRLF. Rien ne part
	   par SMTP : preSend() ne fait que composer le message. */
	$mail->isSMTP();
	$mail->CharSet = 'UTF-8';
	$mail->setFrom($de['adresse'], $de['nom']);
	foreach ($a as $x) { $mail->addAddress($x); }
	foreach ($cci as $x) { $mail->addBCC($x); }
	$mail->Subject = $objet;
	$mail->isHTML(true);
	$mail->Body = $html;
	$mail->AltBody = $texte;
	foreach ($images as $im) { $mail->addStringEmbeddedImage($im['data'], $im['cid'], $im['nom'], 'base64', $im['type']); }
	foreach ($pieces as $p) { $mail->addStringAttachment($p['data'], $p['name'], 'base64', $p['mime']); }
	$mail->preSend();
	$mime = $mail->getSentMIMEMessage();
	if ($cci) {
		/* PHPMailer n'ecrit l'en-tete Bcc que pour sendmail. Gmail le lit pour
		   livrer, puis le retire des copies livrees. */
		$mime = 'Bcc: ' . implode(",\r\n ", $cci) . "\r\n" . $mime;
	}
	return $mime;
}

/**
 * L'envoi : des personnes, en un message (chacun voit les autres) ; ses
 * etudiants, en copie cachee par lots de 100. Un message refuse n'arrete pas
 * les suivants ; un refus de Google (droits, quota) arrete tout.
 *
 * @param array{de:array{adresse:string, nom:string}, mode:string, destinataires:list<string>, objet:string,
 *              html:string, texte:string, images:list<array>, pieces:list<array>, progression:?callable} $p
 * @return array{envoyes:int, echecs:int, en_echec:list<string>, messages:int, erreur:string, refus:string}
 *         erreur : ce qui a tout arrete ; refus : la raison du dernier message refuse
 */
function courrielEnseignantEnvoyer(array $p): array
{
	$dest = array_values($p['destinataires']);
	$personnes = $p['mode'] === 'personnes';
	$lots = $personnes ? [$dest] : array_chunk($dest, COURRIEL_ENSEIGNANT_LOT);
	$bilan = ['envoyes' => 0, 'echecs' => 0, 'en_echec' => [], 'messages' => 0, 'erreur' => '', 'refus' => ''];
	$total = count($dest);
	$enEchec = static function (array $adresses) use (&$bilan) {
		$bilan['echecs'] += count($adresses);
		foreach ($adresses as $x) { if (count($bilan['en_echec']) < 30) { $bilan['en_echec'][] = $x; } }
	};
	foreach ($lots as $k => $lot) {
		$ok = false;
		$fatale = false;
		try {
			$mime = courrielEnseignantMime($p['de'], $personnes ? $lot : [], $personnes ? [] : $lot,
				$p['objet'], $p['html'], $p['texte'], $p['images'], $p['pieces']);
			gmailEnvoyer($p['de']['adresse'], $mime);
			$ok = true;
		} catch (GoogleAdminErreur $e) {
			$bilan['erreur'] = $e->getMessage();
			$fatale = true;
		} catch (Throwable $e) {
			/* GmailMessageRefuse, ou PHPMailer qui refuse une adresse */
			error_log('Courriel enseignant (' . $p['de']['adresse'] . ') : ' . $e->getMessage());
			$bilan['refus'] = $e->getMessage();
		}
		if ($ok) {
			$bilan['envoyes'] += count($lot);
			$bilan['messages']++;
		} else {
			$enEchec($lot);
		}
		if (is_callable($p['progression'] ?? null)) {
			$fait = $bilan['envoyes'] + $bilan['echecs'];
			$libelle = $personnes
				? implode(', ', array_slice($lot, 0, 3)) . (count($lot) > 3 ? ' +' . (count($lot) - 3) : '')
				: (count($lots) > 1 ? 'Lot ' . ($k + 1) . '/' . count($lots) . ' · ' : '') . count($lot) . ' étudiant' . (count($lot) > 1 ? 's' : '') . ' (copie cachée)';
			try {
				($p['progression'])([
					'type' => 'progress', 'recipient' => $libelle, 'status' => $ok ? 'envoye' : 'echec',
					'index' => $k + 1, 'total' => $total, 'sent' => $bilan['envoyes'], 'failed' => $bilan['echecs'], 'processed' => $fait,
				]);
			} catch (Throwable $ignore) {
			}
		}
		if ($fatale) {
			/* Plus rien ne partira : le reste compte en echec. */
			$enEchec(array_slice($dest, ($k + 1) * COURRIEL_ENSEIGNANT_LOT));
			break;
		}
		if ($k < count($lots) - 1) { usleep(400000); } // Gmail : pas plus de deux envois par seconde
	}
	return $bilan;
}

}
