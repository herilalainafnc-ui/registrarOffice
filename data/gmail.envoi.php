<?php
/**
 * Envoi depuis la boite Gmail d'un compte de zurcher.edu.mg, par l'API Gmail
 * (users.messages.send), sans bibliotheque : le compte de service de l'API
 * Google Admin (data/google.admin.php) agit au nom de l'expediteur.
 *
 * Sert aux enseignants (src/bulk-email.php, demande du 19/10/2026) : le message
 * part de l'adresse Google avec laquelle ils se sont connectes, et non de
 * no-reply. Gmail le range dans leurs « Messages envoyes » et les reponses
 * arrivent dans leur boite.
 *
 * Mise en place (une fois, par un super-administrateur Google) :
 *   1. Google Cloud, dans le projet du compte de service : activer l'API Gmail.
 *   2. Google Admin > Securite > Controle des API > Delegation a l'echelle du
 *      domaine : sur la ligne du compte de service, ajouter le champ
 *      https://www.googleapis.com/auth/gmail.send (en gardant
 *      https://www.googleapis.com/auth/admin.directory.user).
 *
 * Le compte de service peut alors envoyer au nom de tout compte du domaine :
 * l'application ne l'emprunte que pour l'adresse que Google a verifiee a la
 * connexion de l'utilisateur (gmailAdresseConnexion).
 */

require_once __DIR__ . '/google.admin.php';

if (!function_exists('gmailEnvoyer')) {

define('GMAIL_ENVOI_PORTEE', 'https://www.googleapis.com/auth/gmail.send');
define('GMAIL_ENVOI_API', 'https://gmail.googleapis.com/upload/gmail/v1/users/me/messages/send?uploadType=media');

/** Gmail refuse ce message-ci (adresse, taille…) : les suivants peuvent partir. */
final class GmailMessageRefuse extends RuntimeException {}

/** L'adresse Google de la connexion en cours, si elle est du domaine ; '' sinon. */
function gmailAdresseConnexion(): string
{
	if (empty($_SESSION['google_login'])) { return ''; }
	$adresse = strtolower(trim((string) ($_SESSION['google_email'] ?? '')));
	return preg_match("/^[a-z0-9._%+'-]+@" . preg_quote(GOOGLE_ADMIN_DOMAINE, '/') . '$/', $adresse) === 1 ? $adresse : '';
}

/** Jeton d'envoi au nom de $adresse ; les refus de Google, dits pour l'enseignant. */
function gmailEnvoiJeton(string $adresse): string
{
	try {
		return googleCompteServiceJeton($adresse, GMAIL_ENVOI_PORTEE);
	} catch (GoogleAdminErreur $e) {
		error_log('Gmail (envoi au nom de ' . $adresse . ') : ' . $e->getMessage());
		$clair = new GoogleAdminErreur(match ($e->codeGoogle) {
			'unauthorized_client' => "L'administrateur Google de l'université n'a pas encore autorisé l'application à envoyer depuis les adresses @"
				. GOOGLE_ADMIN_DOMAINE . ' (délégation du compte de service, champ gmail.send). Prévenez le registrariat.',
			'compte_inconnu' => "Google ne trouve pas le compte {$adresse}.",
			default => "L'envoi depuis votre adresse est impossible pour le moment : " . $e->getMessage(),
		});
		$clair->codeGoogle = $e->codeGoogle;
		throw $clair;
	}
}

/**
 * L'envoi au nom de $adresse est-il possible ? Demande le jeton (garde une
 * heure). Un refus est garde une minute : la page ne redemande pas a Google a
 * chaque ouverture, et un reglage corrige dans Google Admin se voit vite.
 *
 * @return array{pret:bool, raison:string}
 */
function gmailEnvoiEtat(string $adresse): array
{
	$refus = googleAdminDossierCache() . DIRECTORY_SEPARATOR . 'gmail-refus-' . substr(sha1(strtolower($adresse)), 0, 12) . '.json';
	$garde = @json_decode((string) @file_get_contents($refus), true);
	if (is_array($garde) && ($garde['jusqua'] ?? 0) > time()) { return ['pret' => false, 'raison' => (string) ($garde['raison'] ?? '')]; }
	try {
		gmailEnvoiJeton($adresse);
		@unlink($refus);
		return ['pret' => true, 'raison' => ''];
	} catch (Throwable $e) {
		@file_put_contents($refus, json_encode(['raison' => $e->getMessage(), 'jusqua' => time() + 60], JSON_UNESCAPED_UNICODE), LOCK_EX);
		@chmod($refus, 0600);
		return ['pret' => false, 'raison' => $e->getMessage()];
	}
}

/**
 * Envoie un message MIME complet (From, To, Bcc, Subject…) depuis la boite de
 * $adresse. Gmail le range dans ses messages envoyes et retire l'en-tete Bcc
 * des copies livrees. Renvoie l'identifiant Gmail du message.
 *
 * Leve GmailMessageRefuse si Gmail refuse ce message-ci, GoogleAdminErreur si
 * plus rien ne peut partir (droits, quota, Google injoignable).
 */
function gmailEnvoyer(string $adresse, string $mime): string
{
	$jeton = gmailEnvoiJeton($adresse);
	[$code, $r] = googleAdminHttp('POST', GMAIL_ENVOI_API, $mime, [
		'Authorization: Bearer ' . $jeton,
		'Content-Type: message/rfc822',
		'Accept: application/json',
	], 120);
	if ($code === 200 && !empty($r['id'])) { return (string) $r['id']; }

	$message = (string) ($r['error']['message'] ?? '');
	$raison = (string) ($r['error']['errors'][0]['reason'] ?? ($r['error']['status'] ?? ''));
	error_log('Gmail (envoi depuis ' . $adresse . ') : code ' . $code . ' ' . $raison . ' ' . $message);
	if (preg_match('/mail service not enabled/i', $message) === 1) {
		throw new GoogleAdminErreur("Votre compte Google n'a pas de messagerie Gmail active : l'administrateur Google de l'université doit l'activer.");
	}
	if ($code === 429 || preg_match('/rateLimit|dailyLimit|quota/i', $raison) === 1) {
		throw new GoogleAdminErreur('Google limite les envois de votre adresse (quota du jour atteint, ou envois trop rapprochés) : réessayez plus tard.');
	}
	if ($code === 400 || $code === 413) {
		throw new GmailMessageRefuse('Gmail refuse le message' . ($message !== '' ? ' : ' . $message : '') . '.');
	}
	if ($code === 403 && preg_match('/has not been used|is disabled|accessNotConfigured|SERVICE_DISABLED/i', $message . ' ' . $raison) === 1) {
		throw new GoogleAdminErreur("L'API Gmail n'est pas activée dans le projet Google Cloud du compte de service. Prévenez le registrariat.");
	}
	if ($code === 401 || $code === 403) {
		throw new GoogleAdminErreur("Google refuse l'envoi depuis votre adresse" . ($message !== '' ? ' : ' . $message : '') . '.');
	}
	throw new GoogleAdminErreur('Gmail est momentanément indisponible (code ' . $code . ') : réessayez dans un instant.');
}

}
