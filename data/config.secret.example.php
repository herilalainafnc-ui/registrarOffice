<?php
/**
 * MODÈLE de secrets (celui-ci EST commité, sans valeurs réelles).
 *
 * Sur chaque environnement :
 *   1. Copiez ce fichier en "config.secret.php" (ignoré par git).
 *   2. Renseignez les vrais mots de passe.
 * Ne JAMAIS commiter config.secret.php.
 *
 * Alternative : définir les variables d'environnement IMAP_PASSWORD et SMTP_PASS
 * (config.php utilise getenv() en repli si config.secret.php est absent).
 *
 * ATTENTION — « Constant SMTP_PASS already defined in config.local.php »
 * config.php charge config.secret.php AVANT config.local.php. Si votre
 * config.local.php (ignoré par git, donc jamais mis à jour automatiquement)
 * contient encore un define('SMTP_PASS', ...) ou define('MAILBOX_IMAP_PASSWORD', ...),
 * retirez-les : les secrets ne vivent plus que dans ce fichier-ci.
 */

if (!defined('MAILBOX_IMAP_PASSWORD')) define('MAILBOX_IMAP_PASSWORD', 'VOTRE_MOT_DE_PASSE_IMAP');
if (!defined('SMTP_PASS'))            define('SMTP_PASS', 'VOTRE_MOT_DE_PASSE_SMTP');
