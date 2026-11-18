<?php
/**
 * Envoi de SMS — transport generique.
 *
 * Le fournisseur est choisi par configuration (SMS_PROVIDER), pour que le
 * reste de l'application n'ait jamais a le connaitre. Passer de Twilio a un
 * operateur malgache se fera en ajoutant un cas ici, sans toucher au code
 * metier.
 *
 * Fournisseurs disponibles :
 *   'journal' (defaut) — n'envoie rien : ecrit le message dans logs/sms.log.
 *                        Permet d'eprouver toute la chaine sans compte ni
 *                        frais, et de verifier le texte reellement produit.
 *   'twilio'           — API HTTP de Twilio.
 *
 * Configuration (data/config.local.php, secrets dans config.secret.php) :
 *   SMS_ENABLED       true / false
 *   SMS_PROVIDER      'journal' | 'twilio'
 *   TWILIO_SID        identifiant du compte
 *   TWILIO_TOKEN      jeton d'authentification   <- secret
 *   TWILIO_FROM       numero ou nom d'expediteur
 */

require_once __DIR__ . '/config.php';

if (!function_exists('smsNormaliserNumero')) {
    /**
     * Met un numero malgache au format international attendu par les
     * passerelles.
     *
     * Les numeros saisis dans l'application prennent toutes les formes :
     * « 0325855186 », « 032 58 551 86 », « +261325855186 », « 261325855186 ».
     * Une passerelle rejette tout ce qui n'est pas en +261XXXXXXXXX.
     *
     * @return string|null null si le numero n'est pas exploitable.
     */
    function smsNormaliserNumero($numero)
    {
        $brut = preg_replace('/[^0-9+]/', '', (string) $numero);
        if ($brut === '') { return null; }

        // Retire un eventuel + puis les prefixes internationaux.
        $chiffres = ltrim($brut, '+');
        if (strpos($chiffres, '00261') === 0) {
            $chiffres = substr($chiffres, 5);
        } elseif (strpos($chiffres, '261') === 0) {
            $chiffres = substr($chiffres, 3);
        } elseif (strpos($chiffres, '0') === 0) {
            $chiffres = substr($chiffres, 1); // forme locale 03X...
        }

        // Un mobile malgache tient en 9 chiffres et commence par 3.
        if (!preg_match('/^3[0-9]{8}$/', $chiffres)) {
            return null;
        }
        return '+261' . $chiffres;
    }
}

if (!function_exists('smsTexteSimplifie')) {
    /**
     * Remplace les caracteres accentues par leur equivalent simple.
     *
     * Un SMS contenant un caractere hors de l'alphabet GSM bascule en codage
     * UCS-2 : la limite passe de 160 a 70 caracteres, et le message se
     * fractionne — donc coute plusieurs envois. On reste volontairement en
     * caracteres simples.
     */
    function smsTexteSimplifie($texte)
    {
        $table = [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','ñ'=>'n','œ'=>'oe','æ'=>'ae',
            'À'=>'A','Â'=>'A','Ä'=>'A','Á'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Î'=>'I','Ï'=>'I','Ô'=>'O','Ö'=>'O',
            'Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C',
            '’'=>"'", '‘'=>"'", '“'=>'"', '”'=>'"', '—'=>'-', '–'=>'-', '…'=>'...',
        ];
        return strtr((string) $texte, $table);
    }
}

if (!function_exists('envoyerSms')) {
    /**
     * Envoie un SMS au numero indique.
     *
     * @return array{success:bool, message:string}
     */
    function envoyerSms($numero, $texte)
    {
        if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
            return ['success' => false, 'message' => 'Envoi SMS desactive par la configuration.'];
        }

        $destinataire = smsNormaliserNumero($numero);
        if ($destinataire === null) {
            return ['success' => false, 'message' => 'Numero de telephone inexploitable.'];
        }

        $texte = smsTexteSimplifie($texte);
        $fournisseur = defined('SMS_PROVIDER') ? SMS_PROVIDER : 'journal';

        switch ($fournisseur) {
            case 'passerelle':
                return smsEnvoyerViaPasserelle($destinataire, $texte);
            case 'twilio':
                return smsEnvoyerViaTwilio($destinataire, $texte);
            case 'journal':
            default:
                return smsEnvoyerVersJournal($destinataire, $texte);
        }
    }
}

if (!function_exists('smsEnvoyerVersJournal')) {
    /**
     * N'envoie rien : consigne le message dans logs/sms.log.
     *
     * Sert a eprouver la chaine complete — composition, normalisation du
     * numero, tracabilite — sans compte chez un fournisseur ni frais. C'est
     * le mode par defaut, pour qu'une mauvaise configuration n'entraine
     * jamais d'envoi involontaire.
     */
    function smsEnvoyerVersJournal($numero, $texte)
    {
        $dossier = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/logs';
        if (!is_dir($dossier)) { @mkdir($dossier, 0775, true); }

        $ligne = sprintf(
            "[%s] %s (%d caracteres, %d SMS) : %s%s",
            date('Y-m-d H:i:s'),
            $numero,
            strlen($texte),
            (int) ceil(max(1, strlen($texte)) / 160),
            $texte,
            PHP_EOL
        );
        $ok = @file_put_contents($dossier . '/sms.log', $ligne, FILE_APPEND) !== false;

        return $ok
            ? ['success' => true, 'message' => 'Consigne dans logs/sms.log (aucun envoi reel).']
            : ['success' => false, 'message' => "Impossible d'ecrire dans logs/sms.log."];
    }
}

if (!function_exists('smsEnvoyerViaPasserelle')) {
    /**
     * Envoie via une passerelle HTTP d'operateur (Yas Madagascar, Orange,
     * Airtel, ou tout agregateur local).
     *
     * Ces passerelles suivent presque toutes le meme principe : une requete
     * HTTP portant le numero, le texte, un identifiant d'expediteur et des
     * identifiants d'acces. Seuls changent l'adresse, la methode et le NOM
     * des parametres. Tout cela est donc pris dans la configuration, pour que
     * le raccordement a l'operateur soit un reglage et non une modification
     * de code.
     *
     * Configuration attendue (voir data/config.example.php) :
     *   SMS_PASSERELLE_URL        adresse de l'API
     *   SMS_PASSERELLE_METHODE    'POST' (defaut) ou 'GET'
     *   SMS_PASSERELLE_CHAMPS     correspondance des noms de parametres :
     *                             ['numero' => 'to', 'texte' => 'message', ...]
     *   SMS_PASSERELLE_FIXES      parametres constants (expediteur, compte...)
     *   SMS_PASSERELLE_ENTETES    en-tetes HTTP (jeton d'authentification...)
     *   SMS_PASSERELLE_AUTH       'utilisateur:motdepasse' si authentification
     *                             HTTP simple                    <- secret
     *   SMS_PASSERELLE_SUCCES     fragment attendu dans une reponse reussie
     *                             (facultatif : sinon seul le code HTTP compte)
     */
    function smsEnvoyerViaPasserelle($numero, $texte)
    {
        if (!defined('SMS_PASSERELLE_URL') || SMS_PASSERELLE_URL === '') {
            return ['success' => false, 'message' => 'Adresse de la passerelle non configuree (SMS_PASSERELLE_URL).'];
        }
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'Extension cURL indisponible.'];
        }

        // Noms de parametres attendus par l'operateur ; valeurs par defaut
        // frequentes, a ajuster selon sa documentation.
        $champs = defined('SMS_PASSERELLE_CHAMPS') && is_array(SMS_PASSERELLE_CHAMPS)
            ? SMS_PASSERELLE_CHAMPS
            : ['numero' => 'to', 'texte' => 'message'];

        $parametres = defined('SMS_PASSERELLE_FIXES') && is_array(SMS_PASSERELLE_FIXES)
            ? SMS_PASSERELLE_FIXES
            : [];
        $parametres[$champs['numero'] ?? 'to']    = $numero;
        $parametres[$champs['texte']  ?? 'message'] = $texte;

        $methode = defined('SMS_PASSERELLE_METHODE') ? strtoupper(SMS_PASSERELLE_METHODE) : 'POST';
        $url = SMS_PASSERELLE_URL;

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ];
        if ($methode === 'GET') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($parametres);
        } else {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($parametres);
        }
        if (defined('SMS_PASSERELLE_ENTETES') && is_array(SMS_PASSERELLE_ENTETES) && SMS_PASSERELLE_ENTETES) {
            $options[CURLOPT_HTTPHEADER] = SMS_PASSERELLE_ENTETES;
        }
        if (defined('SMS_PASSERELLE_AUTH') && SMS_PASSERELLE_AUTH !== '') {
            $options[CURLOPT_USERPWD] = SMS_PASSERELLE_AUTH;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $reponse  = curl_exec($ch);
        $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur   = curl_error($ch);
        curl_close($ch);

        if ($reponse === false) {
            return ['success' => false, 'message' => 'Passerelle injoignable : ' . $erreur];
        }
        if ($codeHttp < 200 || $codeHttp >= 300) {
            return ['success' => false, 'message' => 'Passerelle : HTTP ' . $codeHttp . ' — ' . substr((string) $reponse, 0, 200)];
        }
        // Certaines passerelles repondent 200 meme en cas d'echec : on peut
        // exiger un fragment precis dans le corps de la reponse.
        if (defined('SMS_PASSERELLE_SUCCES') && SMS_PASSERELLE_SUCCES !== ''
            && strpos((string) $reponse, SMS_PASSERELLE_SUCCES) === false) {
            return ['success' => false, 'message' => 'Passerelle : reponse inattendue — ' . substr((string) $reponse, 0, 200)];
        }
        return ['success' => true, 'message' => 'Envoye via la passerelle operateur.'];
    }
}

if (!function_exists('smsEnvoyerViaTwilio')) {
    /**
     * Envoie via l'API HTTP de Twilio.
     *
     * En compte d'essai, Twilio n'accepte que des numeros prealablement
     * verifies et prefixe le message d'une mention d'essai ; l'envoi vers
     * Madagascar doit en outre etre autorise dans les permissions
     * geographiques du compte.
     */
    function smsEnvoyerViaTwilio($numero, $texte)
    {
        foreach (['TWILIO_SID', 'TWILIO_TOKEN', 'TWILIO_FROM'] as $requis) {
            if (!defined($requis) || constant($requis) === '') {
                return ['success' => false, 'message' => 'Configuration Twilio incomplete (' . $requis . ').'];
            }
        }
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'Extension cURL indisponible.'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_SID) . '/Messages.json';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => TWILIO_SID . ':' . TWILIO_TOKEN,
            CURLOPT_POSTFIELDS     => http_build_query([
                'From' => TWILIO_FROM,
                'To'   => $numero,
                'Body' => $texte,
            ]),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $reponse = curl_exec($ch);
        $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);
        curl_close($ch);

        if ($reponse === false) {
            return ['success' => false, 'message' => 'Appel Twilio impossible : ' . $erreur];
        }
        if ($codeHttp >= 200 && $codeHttp < 300) {
            return ['success' => true, 'message' => 'Envoye via Twilio.'];
        }

        // Twilio renvoie un motif exploitable : on le remonte tel quel.
        $detail = json_decode((string) $reponse, true);
        $motif = is_array($detail) && isset($detail['message']) ? $detail['message'] : 'HTTP ' . $codeHttp;
        return ['success' => false, 'message' => 'Twilio a refuse l\'envoi : ' . $motif];
    }
}
