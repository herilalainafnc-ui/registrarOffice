<?php
/**
 * SMS adresses au candidat pour l'informer de la decision prise sur sa
 * candidature de pre-admission.
 *
 * Le telephone est obligatoire dans le formulaire public, l'email
 * facultatif : le SMS atteint donc davantage de candidats que le courriel.
 *
 * Les textes tiennent volontairement dans un seul SMS (160 caracteres en
 * alphabet GSM). L'acheminement est delegue a data/sms.php, qui masque le
 * fournisseur.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sms.php';

if (!function_exists('admissionTexteSmsDecision')) {
    /**
     * Compose le SMS correspondant a la decision.
     *
     * @return string|null null si la decision ne donne pas lieu a notification.
     */
    function admissionTexteSmsDecision(array $candidat, $decision)
    {
        $mention = trim((string) ($candidat['mention_libelle'] ?? $candidat['mention'] ?? ''));
        $enMention = $mention !== '' ? ' en ' . $mention : '';
        $tel = 'Tel +261 34 38 180 81';

        if ($decision === 'accepte') {
            $texte = 'UAZ : votre candidature' . $enMention . ' est acceptee. '
                   . 'Presentez-vous au registrariat avec vos pieces justificatives. ' . $tel;
        } elseif ($decision === 'refuse') {
            $texte = 'UAZ : apres examen, votre candidature' . $enMention . ' n\'a pas ete retenue. '
                   . 'Vous pouvez postuler a une prochaine session. ' . $tel;
        } else {
            return null;
        }

        $texte = smsTexteSimplifie($texte);

        // Un nom de mention inhabituellement long ne doit pas faire basculer
        // le message sur deux SMS : on retire la mention plutot que de couper
        // le texte au milieu d'une phrase.
        if (strlen($texte) > 160 && $mention !== '') {
            return admissionTexteSmsDecision(
                ['mention_libelle' => '', 'mention' => ''],
                $decision
            );
        }
        return $texte;
    }
}

if (!function_exists('admissionNotifierCandidatSms')) {
    /**
     * Previent le candidat par SMS de la decision prise sur son dossier.
     *
     * @return array{envoye:bool, raison:string}
     */
    function admissionNotifierCandidatSms(array $candidat, $decision)
    {
        $texte = admissionTexteSmsDecision($candidat, $decision);
        if ($texte === null) {
            return ['envoye' => false, 'raison' => 'Statut sans notification.'];
        }

        $numero = trim((string) ($candidat['telephone'] ?? ''));
        if ($numero === '') {
            return ['envoye' => false, 'raison' => "Le candidat n'a pas laissé de numéro."];
        }
        if (smsNormaliserNumero($numero) === null) {
            return ['envoye' => false, 'raison' => 'Numéro de téléphone inexploitable.'];
        }

        $resultat = envoyerSms($numero, $texte);
        if (!empty($resultat['success'])) {
            return ['envoye' => true, 'raison' => 'Le candidat a été notifié par SMS.'];
        }

        $detail = isset($resultat['message']) ? (string) $resultat['message'] : 'cause inconnue';
        error_log('Admissions: SMS non envoye (' . $numero . ') : ' . $detail);
        return ['envoye' => false, 'raison' => "Le SMS n'a pas pu être envoyé."];
    }
}
