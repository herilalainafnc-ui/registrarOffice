-- Frais supplementaires du bareme (16/09/2026)
--
-- Le bareme portait une colonne par frais ; ajouter « Alliance francaise »
-- aurait demande une migration de schema et une retouche de tous les points
-- d'ecriture des frais. Ces trois tables les rendent modulables.
--
-- data/finance.frais.php cree ces tables automatiquement a la premiere
-- ouverture de /finance : ce fichier sert a l'appliquer a la main si besoin.

CREATE TABLE IF NOT EXISTS t_frais_perso (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    libelle    VARCHAR(120) NOT NULL,
    actif      TINYINT(1)   NOT NULL DEFAULT 1,
    ordre      INT          NOT NULL DEFAULT 0,
    date_entry DATETIME     DEFAULT NULL,
    user_id    INT          DEFAULT NULL,
    UNIQUE KEY uk_frais_perso_libelle (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meme cle que t_2024_finance_detail_licence : session, filiere, niveau,
-- statut, semestre. session_id = 0 designe le bareme de base.
CREATE TABLE IF NOT EXISTS t_frais_perso_montant (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    frais_id    INT         NOT NULL,
    session_id  INT         NOT NULL DEFAULT 0,
    std_mention VARCHAR(20) NOT NULL,
    level       INT         NOT NULL,
    std_status  VARCHAR(20) NOT NULL,
    semester    INT         NOT NULL,
    montant     DOUBLE      NOT NULL DEFAULT 0,
    UNIQUE KEY uk_frais_perso_cle (frais_id, session_id, std_mention, level, std_status, semester),
    KEY idx_frais_perso_session (session_id, std_mention, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce qui a ete facture a un etudiant : libelle et montant figes, comme les
-- colonnes cout_* de t_2024_etudiant_finace.
CREATE TABLE IF NOT EXISTS t_2024_etudiant_frais_perso (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    student_id          VARCHAR(50)  NOT NULL,
    session_id          INT          NOT NULL,
    frais_id            INT          NOT NULL,
    libelle             VARCHAR(120) NOT NULL,
    montant             DOUBLE       NOT NULL DEFAULT 0,
    `remove`            TINYINT(1)   NOT NULL DEFAULT 0,
    date_entry          DATETIME     DEFAULT NULL,
    last_change_user_id INT          DEFAULT NULL,
    UNIQUE KEY uk_etudiant_frais (student_id, session_id, frais_id),
    KEY idx_etudiant_dossier (student_id, session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
