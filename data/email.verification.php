<?php
/**
 * Verification d'une adresse email saisie par un candidat (formulaire de
 * pre-inscription), pendant la frappe et a l'envoi.
 *
 * Ce qu'on peut savoir de facon sure : l'adresse est bien formee, et son
 * DOMAINE recoit des emails (serveur MX publie dans le DNS). On repere aussi
 * les fautes de frappe dans les domaines courants (« gmial.com », « yaho.fr »).
 *
 * Ce qu'on ne peut PAS savoir : que la boite elle-meme existe. Gmail ou Yahoo
 * ne le disent pas, et interroger leurs serveurs ferait mettre le notre sur
 * liste noire. La partie avant le @ reste donc a la charge du candidat.
 *
 * Pourquoi : le mail d'acceptation (data/admissions.mail.php) porte l'adresse
 * universitaire et le mot de passe du candidat de Master. Envoye a un domaine
 * mal ecrit, il se perd ; envoye a un domaine de faute de frappe qui accepte
 * le courrier (gmai.com en est un), il tombe chez un inconnu.
 */

if (!function_exists('emailVerifier')) {

/** Domaines connus et legitimes : jamais signales comme fautes de frappe. */
function emailDomainesConnus(): array
{
	return [
		'gmail.com', 'googlemail.com', 'yahoo.fr', 'yahoo.com', 'ymail.com', 'hotmail.com', 'hotmail.fr',
		'outlook.com', 'outlook.fr', 'live.com', 'live.fr', 'msn.com', 'icloud.com', 'me.com', 'aol.com',
		'mail.com', 'gmx.com', 'gmx.fr', 'orange.fr', 'free.fr', 'laposte.net', 'sfr.fr', 'protonmail.com',
		'proton.me', 'yandex.com', 'zurcher.edu.mg',
	];
}

/** Domaines proposes en correction d'une faute de frappe. */
function emailDomainesSuggeres(): array
{
	return [
		'gmail.com', 'yahoo.fr', 'yahoo.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr',
		'live.fr', 'icloud.com', 'zurcher.edu.mg',
	];
}

/**
 * Le DNS du serveur repond-il ? Sinon, tout domaine paraitrait inexistant et
 * l'on refuserait tous les candidats : on ne verifie alors que la forme.
 */
function emailDnsDisponible(): bool
{
	static $ok = null;
	return $ok ??= checkdnsrr('gmail.com.', 'MX');
}

/** Domaine connu le plus proche (faute de frappe probable), ou null. */
function emailSuggestionDomaine(string $domaine): ?string
{
	if (in_array($domaine, emailDomainesConnus(), true) || strlen($domaine) < 5) { return null; }
	$meilleur = null;
	/* Ecart admis : deux lettres, trois sur un domaine long (« zurcher.edu »
	   pour « zurcher.edu.mg »). */
	$distance = strlen($domaine) >= 10 ? 4 : 3;
	foreach (emailDomainesSuggeres() as $connu) {
		$d = levenshtein($domaine, $connu);
		if ($d < $distance) { $distance = $d; $meilleur = $connu; }
	}
	return $meilleur;
}

/**
 * Verifie une adresse.
 *
 * @return array{etat:string, bloquant:bool, message:string, suggestion:?string}
 *   etat : vide | invalide | inexistant | sans_mx | suggestion | uaz | ok | non_verifie
 *   bloquant : l'adresse ne peut pas recevoir d'email, l'envoi est refuse.
 *   suggestion : l'adresse corrigee, quand une faute de frappe est probable.
 */
function emailVerifier(string $adresse): array
{
	$adresse = trim($adresse);
	$r = static fn (string $etat, bool $bloquant, string $message, ?string $suggestion = null)
		=> ['etat' => $etat, 'bloquant' => $bloquant, 'message' => $message, 'suggestion' => $suggestion];

	if ($adresse === '') { return $r('vide', false, ''); }
	if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
		return $r('invalide', true, 'Adresse incomplète ou mal écrite (exemple : nom.prenom@gmail.com).');
	}

	[$local, $domaine] = explode('@', $adresse, 2);
	$domaine = strtolower($domaine);
	$suggestion = emailSuggestionDomaine($domaine);
	$corrigee = $suggestion !== null ? $local . '@' . $suggestion : null;

	if (!emailDnsDisponible()) {
		return $r('non_verifie', false, '');
	}
	if (!checkdnsrr($domaine . '.', 'MX')) {
		$existe = checkdnsrr($domaine . '.', 'A') || checkdnsrr($domaine . '.', 'AAAA');
		$message = $existe
			? "Le domaine « {$domaine} » ne reçoit pas d'emails : cette adresse ne peut pas fonctionner."
			: "Le domaine « {$domaine} » n'existe pas : cette adresse ne peut pas fonctionner.";
		if ($corrigee !== null) { $message .= " Vouliez-vous dire {$corrigee} ?"; }
		return $r($existe ? 'sans_mx' : 'inexistant', true, $message, $corrigee);
	}
	if ($corrigee !== null) {
		return $r('suggestion', false, "Vouliez-vous dire {$corrigee} ? « {$domaine} » ressemble à une faute de frappe.", $corrigee);
	}
	if ($domaine === 'zurcher.edu.mg') {
		return $r('uaz', false, "Adresse UAZ : assurez-vous qu'elle est déjà active. Sinon, indiquez plutôt une adresse personnelle (Gmail, Yahoo…).");
	}
	return $r('ok', false, "Le domaine « {$domaine} » reçoit bien les emails. Vérifiez l'orthographe de la partie avant le @.");
}

}
