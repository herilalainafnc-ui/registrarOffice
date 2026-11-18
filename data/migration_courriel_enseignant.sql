-- =============================================================================
-- E-mails des enseignants (demande du 19/10/2026) : l'historique des envois
-- (t_bulk_email_mailbox) garde l'adresse d'ou chaque message est parti.
--
-- sender_email   NULL : envoi du registrariat, depuis no-reply (comme avant) ;
--                une adresse @zurcher.edu.mg : envoi d'un enseignant, depuis sa
--                boite Gmail (data/courriel.enseignant.php).
--
-- La page /settings/bulk-email en tire deux historiques separes : le
-- registrariat voit ses envois groupes, chaque enseignant les siens.
--
-- La table existait deja en production (creee par la page au premier envoi) ;
-- le CREATE ne sert qu'a une base neuve.
--
-- A appliquer une fois, a la main (local puis production).
-- Retour arriere :
--   ALTER TABLE t_bulk_email_mailbox DROP INDEX idx_mailbox_sender, DROP COLUMN sender_email;
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_bulk_email_mailbox (
    mailbox_id INT AUTO_INCREMENT PRIMARY KEY,
    mailbox_folder VARCHAR(16) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    preview_text TEXT NULL,
    preview_image LONGTEXT NULL,
    message_html MEDIUMTEXT NULL,
    recipient_total INT NOT NULL DEFAULT 0,
    sent_total INT NOT NULL DEFAULT 0,
    failed_total INT NOT NULL DEFAULT 0,
    created_by VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mailbox_folder_date (mailbox_folder, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE t_bulk_email_mailbox
    ADD COLUMN sender_email VARCHAR(190) NULL DEFAULT NULL AFTER created_by,
    ADD INDEX idx_mailbox_sender (sender_email, mailbox_folder, mailbox_id);
