<?php
/**
 * Messages adresses au candidat pour l'informer de la decision prise sur sa
 * candidature de pre-admission.
 *
 * L'envoi passe par newsletterSendTransport() (data/newsletter.php), deja
 * utilise ailleurs dans l'application : PHPMailer via SMTP, avec repli sur
 * mail(). On evite ainsi une seconde configuration SMTP a maintenir.
 *
 * L'envoi est toujours « au mieux » : il ne doit jamais empecher
 * l'enregistrement de la decision, qui est l'acte important.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/newsletter.php';

if (!function_exists('admissionCoordonneesContact')) {
    /** Coordonnees du bureau des admissions, reprises dans les messages. */
    function admissionCoordonneesContact()
    {
        return [
            'email'     => 'registrar@zurcher.edu.mg',
            'telephone' => '+261 34 38 180 81',
            'adresse'   => 'Antsampanimahazo, Antsirabe, Madagascar',
        ];
    }
}

if (!function_exists('admissionMessageDecision')) {
    /**
     * Compose le message correspondant a la decision.
     *
     * @param array  $candidat Ligne de la table candidat.
     * @param string $decision 'accepte' ou 'refuse'.
     * @return array{sujet:string, html:string, texte:string}|null
     *         null si la decision ne donne pas lieu a notification.
     */
    function admissionMessageDecision(array $candidat, $decision)
    {
        $nom     = trim((string) ($candidat['nom_complet'] ?? ''));
        $mention = trim((string) ($candidat['mention_libelle'] ?? $candidat['mention'] ?? ''));
        $contact = admissionCoordonneesContact();

        // Echappement systematique : le nom vient d'un formulaire public.
        $nomH     = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
        $mentionH = htmlspecialchars($mention, ENT_QUOTES, 'UTF-8');

        if ($decision === 'accepte') {
            $sujet = 'Votre candidature a été acceptée — Université Adventiste Zurcher';
            $titre = 'Votre candidature a été acceptée';
            $corpsTexte = "Bonjour {$nom},\n\n"
                . "Nous avons le plaisir de vous informer que votre demande de pré-inscription"
                . ($mention !== '' ? " en {$mention}" : '') . " a été acceptée.\n\n"
                . "Pour finaliser votre inscription, veuillez vous présenter au bureau du registrariat "
                . "avec vos pièces justificatives (diplôme, relevés de notes, pièce d'identité, photos d'identité).\n\n"
                . "Bureau du registrariat\n"
                . "{$contact['email']}\n{$contact['telephone']}\n{$contact['adresse']}\n\n"
                . "Nous nous réjouissons de vous accueillir.\n\n"
                . "Université Adventiste Zurcher";
            $corpsHtml = '<p>Bonjour <strong>' . $nomH . '</strong>,</p>'
                . '<p>Nous avons le plaisir de vous informer que votre demande de pré-inscription'
                . ($mentionH !== '' ? ' en <strong>' . $mentionH . '</strong>' : '') . ' a été <strong>acceptée</strong>.</p>'
                . '<p>Pour finaliser votre inscription, veuillez vous présenter au bureau du registrariat '
                . 'avec vos pièces justificatives : diplôme, relevés de notes, pièce d\'identité et photos d\'identité.</p>';
        } elseif ($decision === 'refuse') {
            $sujet = 'Suite donnée à votre candidature — Université Adventiste Zurcher';
            $titre = 'Suite donnée à votre candidature';
            $corpsTexte = "Bonjour {$nom},\n\n"
                . "Nous vous remercions de l'intérêt que vous portez à l'Université Adventiste Zurcher "
                . "et du temps consacré à votre demande de pré-inscription"
                . ($mention !== '' ? " en {$mention}" : '') . ".\n\n"
                . "Après examen de votre dossier, nous ne sommes pas en mesure d'y donner une suite favorable.\n\n"
                . "Cette décision ne remet pas en cause vos qualités ; vous pouvez présenter une nouvelle "
                . "demande lors d'une prochaine session. Le bureau du registrariat reste à votre disposition "
                . "pour tout renseignement.\n\n"
                . "Bureau du registrariat\n"
                . "{$contact['email']}\n{$contact['telephone']}\n\n"
                . "Nous vous souhaitons pleine réussite dans vos projets.\n\n"
                . "Université Adventiste Zurcher";
            $corpsHtml = '<p>Bonjour <strong>' . $nomH . '</strong>,</p>'
                . '<p>Nous vous remercions de l\'intérêt que vous portez à l\'Université Adventiste Zurcher '
                . 'et du temps consacré à votre demande de pré-inscription'
                . ($mentionH !== '' ? ' en <strong>' . $mentionH . '</strong>' : '') . '.</p>'
                . '<p>Après examen de votre dossier, nous ne sommes pas en mesure d\'y donner une suite favorable.</p>'
                . '<p>Cette décision ne remet pas en cause vos qualités : vous pouvez présenter une nouvelle '
                . 'demande lors d\'une prochaine session. Le bureau du registrariat reste à votre disposition '
                . 'pour tout renseignement.</p>';
        } else {
            return null; // les autres statuts ne donnent pas lieu a notification
        }

        return ['sujet' => $sujet, 'html' => admissionEnveloppeHtml($titre, $corpsHtml), 'texte' => $corpsTexte];
    }
}

if (!function_exists('admissionEnveloppeHtml')) {
    /** Habillage commun des messages au candidat : bandeau, corps, coordonnees du registrariat. */
    function admissionEnveloppeHtml($titre, $corpsHtml)
    {
        $contact = admissionCoordonneesContact();
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f4f5f7;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;'
            . 'font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
            . '<tr><td style="background:#1a365d;padding:22px 28px;color:#ffffff;">'
            . '<div style="font-size:13px;letter-spacing:.06em;text-transform:uppercase;opacity:.85;">'
            . 'Université Adventiste Zurcher</div>'
            . '<div style="font-size:19px;font-weight:bold;margin-top:6px;">' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:26px 28px;font-size:15px;line-height:1.65;">' . $corpsHtml . '</td></tr>'
            . '<tr><td style="padding:0 28px 24px;font-size:13px;line-height:1.6;color:#4b5563;">'
            . '<div style="border-top:1px solid #e5e7eb;padding-top:16px;">'
            . '<strong>Bureau du registrariat</strong><br>'
            . htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') . '<br>'
            . htmlspecialchars($contact['telephone'], ENT_QUOTES, 'UTF-8') . '<br>'
            . htmlspecialchars($contact['adresse'], ENT_QUOTES, 'UTF-8')
            . '</div></td></tr>'
            . '<tr><td style="background:#f9fafb;padding:14px 28px;font-size:11px;color:#9ca3af;">'
            . 'Message automatique — merci de ne pas y répondre directement.'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}

/* -----------------------------------------------------------------------------
   MASTER : l'acceptation part avec l'adresse universitaire et son mot de passe

   L'adresse (@zurcher.edu.mg) et le mot de passe naissent a la creation de
   l'etudiant (app/.student/addStd.php), pas a l'acceptation : pour un
   candidat de Master, « Accepter » n'envoie donc rien, et c'est « Creer
   l'etudiant » qui envoie ce message (decision de l'utilisateur, 08/10/2026).
   La Licence garde le message d'acceptation d'avant.
   -------------------------------------------------------------------------- */

if (!defined('ADMISSION_ADRESSES_ACTIVES_LE')) {
    /* Ouverture des adresses des nouveaux etudiants (comptes Google Workspace) :
       avant cette date, le message l'annonce ; ensuite, il la dit active. */
    define('ADMISSION_ADRESSES_ACTIVES_LE', '2026-10-11');
    define('ADMISSION_ADRESSES_ACTIVES_LIBELLE', 'dimanche 11 octobre 2026');
}

if (!function_exists('admissionEstMaster')) {
    /** La candidature vise-t-elle le Master (candidat.diplome_vise) ? */
    function admissionEstMaster(array $candidat)
    {
        return strtolower(trim((string) ($candidat['diplome_vise'] ?? ''))) === 'master';
    }
}

if (!function_exists('admissionMessageIdentifiants')) {
    /**
     * Acceptation d'un candidat de Master, avec son adresse universitaire et
     * son mot de passe.
     *
     * @param bool|null $compteCree le compte Google vient d'etre cree (true),
     *        n'a pas pu l'etre (false), ou l'API n'est pas branchee (null :
     *        l'ouverture suit ADMISSION_ADRESSES_ACTIVES_LE, comme avec l'import CSV).
     * @return array{sujet:string, html:string, texte:string}
     */
    function admissionMessageIdentifiants(array $candidat, $adresse, $motDePasse, $compteCree = null)
    {
        $nom     = trim((string) ($candidat['nom_complet'] ?? ''));
        $mention = trim((string) ($candidat['mention_libelle'] ?? $candidat['mention'] ?? ''));
        $contact = admissionCoordonneesContact();
        $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        if ($compteCree === true) {
            $quandTexte = 'Cette adresse est active dès maintenant : connectez-vous sur https://mail.google.com avec cette adresse et ce mot de passe.';
            $quandHtml = 'Cette adresse est <strong>active dès maintenant</strong> : connectez-vous sur '
                . '<a href="https://mail.google.com" style="color:#4658c2;">mail.google.com</a> avec cette adresse et ce mot de passe.';
        } elseif ($compteCree === false) {
            $quandTexte = 'Cette adresse sera activée sous peu.';
            $quandHtml = 'Cette adresse sera <strong>activée sous peu</strong>.';
        } else {
            $active = date('Y-m-d') >= ADMISSION_ADRESSES_ACTIVES_LE;
            $quandTexte = $active
                ? 'Cette adresse est active.'
                : 'Cette adresse sera active à partir du ' . ADMISSION_ADRESSES_ACTIVES_LIBELLE . '.';
            $quandHtml = $active
                ? 'Cette adresse est <strong>active</strong>.'
                : 'Cette adresse sera active à partir du <strong>' . $h(ADMISSION_ADRESSES_ACTIVES_LIBELLE) . '</strong>.';
        }

        $sujet = 'Votre candidature en Master a été acceptée — Université Adventiste Zurcher';
        $titre = 'Votre candidature a été acceptée';

        $corpsTexte = "Bonjour {$nom},\n\n"
            . "Nous avons le plaisir de vous informer que votre candidature en Master"
            . ($mention !== '' ? ", mention {$mention}," : '') . " a été acceptée. "
            . "Bienvenue à l'Université Adventiste Zurcher.\n\n"
            . "Votre adresse électronique universitaire a été créée :\n"
            . "  Adresse : {$adresse}\n"
            . "  Mot de passe : {$motDePasse}\n\n"
            . $quandTexte . " C'est par elle que l'université communiquera avec vous.\n\n"
            . "Conservez ce message et ne communiquez votre mot de passe à personne.\n\n"
            . "Le bureau du registrariat reste à votre disposition pour toute question et pour la suite de votre inscription.\n\n"
            . "Bureau du registrariat\n"
            . "{$contact['email']}\n{$contact['telephone']}\n{$contact['adresse']}\n\n"
            . "Université Adventiste Zurcher";

        $corpsHtml = '<p>Bonjour <strong>' . $h($nom) . '</strong>,</p>'
            . '<p>Nous avons le plaisir de vous informer que votre candidature en <strong>Master</strong>'
            . ($mention !== '' ? ', mention <strong>' . $h($mention) . '</strong>,' : '') . ' a été <strong>acceptée</strong>. '
            . 'Bienvenue à l\'Université Adventiste Zurcher.</p>'
            . '<p>Votre adresse électronique universitaire a été créée :</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:4px 0 16px;'
            . 'background:#f3f6fb;border:1px solid #dbe3ef;border-radius:10px;">'
            . '<tr><td style="padding:14px 18px;font-size:15px;line-height:1.9;">'
            . 'Adresse : <strong style="font-family:Consolas,Menlo,monospace;">' . $h($adresse) . '</strong><br>'
            . 'Mot de passe : <strong style="font-family:Consolas,Menlo,monospace;">' . $h($motDePasse) . '</strong>'
            . '</td></tr></table>'
            . '<p>' . $quandHtml . ' C\'est par elle que l\'université communiquera avec vous.</p>'
            . '<p>Conservez ce message et ne communiquez votre mot de passe à personne.</p>'
            . '<p>Le bureau du registrariat reste à votre disposition pour toute question et pour la suite de votre inscription.</p>';

        return ['sujet' => $sujet, 'html' => admissionEnveloppeHtml($titre, $corpsHtml), 'texte' => $corpsTexte];
    }
}

if (!function_exists('admissionEnvoyer')) {
    /**
     * Envoie un message compose a l'adresse personnelle du candidat.
     *
     * @return array{envoye:bool, raison:string}
     */
    function admissionEnvoyer(array $candidat, array $message)
    {
        $email = trim((string) ($candidat['email'] ?? ''));
        if ($email === '') {
            // Frequent : l'adresse est facultative dans le formulaire public.
            return ['envoye' => false, 'raison' => "Le candidat n'a pas laissé d'adresse email."];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['envoye' => false, 'raison' => 'Adresse email du candidat invalide.'];
        }

        if (!function_exists('newsletterSendTransport')) {
            return ['envoye' => false, 'raison' => "Transport d'envoi indisponible."];
        }

        $resultat = newsletterSendTransport(
            $email,
            $message['sujet'],
            $message['html'],
            $message['texte'],
            defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@zurcher.edu.mg',
            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Université Adventiste Zurcher'
        );

        if (!empty($resultat['success'])) {
            return ['envoye' => true, 'raison' => 'Le candidat a été notifié par email.'];
        }

        $detail = isset($resultat['message']) ? (string) $resultat['message'] : 'cause inconnue';
        error_log('Admissions: notification candidat non envoyee (' . $email . ') : ' . $detail);
        return ['envoye' => false, 'raison' => "L'email n'a pas pu être envoyé."];
    }
}

if (!function_exists('admissionNotifierIdentifiants')) {
    /**
     * Master : previent le candidat de son acceptation et lui donne son adresse
     * universitaire et son mot de passe, a la creation de l'etudiant.
     *
     * @param bool|null $compteCree voir admissionMessageIdentifiants()
     * @return array{envoye:bool, raison:string}
     */
    function admissionNotifierIdentifiants(array $candidat, $adresse, $motDePasse, $compteCree = null)
    {
        $adresse = trim((string) $adresse);
        if ($adresse === '' || trim((string) $motDePasse) === '') {
            return ['envoye' => false, 'raison' => "Adresse universitaire ou mot de passe manquant : le mail d'acceptation n'est pas parti."];
        }
        $envoi = admissionEnvoyer($candidat, admissionMessageIdentifiants($candidat, $adresse, $motDePasse, $compteCree));
        if ($envoi['envoye']) {
            $envoi['raison'] = "Le mail d'acceptation, avec son adresse et son mot de passe, a été envoyé au candidat.";
        }
        return $envoi;
    }
}

if (!function_exists('admissionNotifierCandidat')) {
    /**
     * Previent le candidat de la decision prise sur son dossier.
     *
     * @return array{envoye:bool, raison:string} raison decrit ce qui s'est
     *         passe, pour l'afficher a l'agent et le journaliser.
     */
    function admissionNotifierCandidat(array $candidat, $decision)
    {
        /* Master : l'acceptation part a la creation de l'etudiant, avec ses
           identifiants (admissionNotifierIdentifiants). */
        if ($decision === 'accepte' && admissionEstMaster($candidat)) {
            return ['envoye' => false, 'raison' => "Master : le mail d'acceptation partira à la création de l'étudiant, avec son adresse et son mot de passe."];
        }

        $message = admissionMessageDecision($candidat, $decision);
        if ($message === null) {
            return ['envoye' => false, 'raison' => 'Statut sans notification.'];
        }
        return admissionEnvoyer($candidat, $message);
    }
}
