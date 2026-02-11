-- ============================================================
-- Table des annonces / actualités universitaires
-- Utilisée par la page Actus de l'espace étudiant
-- ============================================================

CREATE TABLE IF NOT EXISTS `t_annonces` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL COMMENT 'Titre de l''annonce',
    `content` TEXT NOT NULL COMMENT 'Contenu complet de l''annonce',
    `excerpt` VARCHAR(500) DEFAULT NULL COMMENT 'Résumé court pour aperçu',
    `image` VARCHAR(255) DEFAULT NULL COMMENT 'Nom du fichier image (dans app/uploads/annonces/)',
    `category` ENUM('info','event','academic','urgent','sport','culture') NOT NULL DEFAULT 'info' COMMENT 'Catégorie de l''annonce',
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Annonce épinglée en priorité',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Annonce visible ou masquée',
    `author` VARCHAR(100) DEFAULT NULL COMMENT 'Auteur de l''annonce',
    `publish_date` DATE DEFAULT NULL COMMENT 'Date de publication',
    `expire_date` DATE DEFAULT NULL COMMENT 'Date d''expiration (NULL = pas d''expiration)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Dernière modification',
    INDEX `idx_category` (`category`),
    INDEX `idx_publish_date` (`publish_date`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_is_pinned` (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Données de démonstration
-- ============================================================

INSERT INTO `t_annonces` (`title`, `content`, `excerpt`, `image`, `category`, `is_pinned`, `author`, `publish_date`) VALUES

('Rentrée Académique 2025-2026', 
'La rentrée académique pour l''année universitaire 2025-2026 est fixée au lundi 15 septembre 2025. Tous les étudiants sont priés de se présenter au campus dès 7h30 pour la cérémonie d''ouverture qui aura lieu dans l''amphithéâtre principal.\n\nLe programme de la journée :\n- 7h30 - 8h00 : Accueil et enregistrement\n- 8h00 - 9h30 : Cérémonie d''ouverture\n- 9h30 - 10h00 : Pause café\n- 10h00 - 12h00 : Présentation des filières\n- 14h00 - 16h00 : Visite du campus pour les nouveaux étudiants\n\nLes documents suivants sont requis : carte d''étudiant, reçu de paiement, et attestation d''inscription.',
'La rentrée académique 2025-2026 est fixée au lundi 15 septembre. Cérémonie d''ouverture à 8h00 dans l''amphithéâtre principal.', 
NULL, 'academic', 1, 'Direction Académique', '2025-08-20'),

('Semaine Spirituelle — Thème : « Espérance »',
'La semaine spirituelle annuelle se tiendra du 20 au 25 octobre 2025. Le thème choisi cette année est « Espérance : Ancre de l''Âme ».\n\nProgramme quotidien :\n- 6h00 - 6h45 : Méditation matinale\n- 11h30 - 12h30 : Message principal (Amphithéâtre)\n- 18h00 - 19h00 : Groupes de discussion\n- 19h30 - 20h30 : Veillée musicale\n\nOrateur invité : Pasteur Jean-Marc RANDRIANASOLO, directeur de l''Union des Fédérations.\n\nTous les cours sont suspendus durant cette semaine. La présence est obligatoire pour tous les étudiants résidents.',
'Semaine spirituelle du 20 au 25 octobre — Thème « Espérance ». Tous les cours suspendus. Présence obligatoire.',
NULL, 'event', 1, 'Aumônerie', '2025-10-01'),

('Résultats des examens du 1er semestre disponibles',
'Les résultats des examens du premier semestre 2024-2025 sont désormais disponibles sur la plateforme en ligne. Les étudiants peuvent consulter leurs notes via l''espace « Mes Notes » de leur portail étudiant.\n\nPour toute réclamation, veuillez vous adresser au secrétariat académique avant le 15 février 2025. Les demandes tardives ne seront pas prises en compte.\n\nRappel : La moyenne minimale de passage est de 10/20 pour chaque UE.',
'Les résultats du 1er semestre sont disponibles. Consultez vos notes dans l''espace « Mes Notes ». Réclamations avant le 15/02.',
NULL, 'academic', 0, 'Secrétariat Académique', '2025-01-28'),

('Tournoi Inter-Facultés de Football',
'Le tournoi inter-facultés de football aura lieu les samedis 8 et 15 mars 2025 sur le terrain principal du campus.\n\nÉquipes participantes :\n- Faculté de Théologie\n- Faculté des Sciences\n- Faculté de Gestion\n- Faculté des Lettres\n\nInscription des joueurs : avant le 1er mars auprès du responsable sportif de chaque faculté.\n\nPrix pour l''équipe gagnante : Trophée du Recteur + équipements sportifs.\n\nVenez nombreux supporter vos équipes !',
'Tournoi inter-facultés de football les 8 et 15 mars. Inscriptions ouvertes. Trophée du Recteur à gagner !',
NULL, 'sport', 0, 'Service des Sports', '2025-02-15'),

('Nouvelle bibliothèque numérique',
'L''université est heureuse d''annoncer le lancement de sa bibliothèque numérique. Plus de 10 000 ouvrages, articles scientifiques et ressources pédagogiques sont désormais accessibles en ligne 24h/24.\n\nAccès : bibliothèque.uaz.mg (identifiants = même login que le portail étudiant)\n\nRessources disponibles :\n- Manuels universitaires (PDF)\n- Articles de revues académiques\n- Thèses et mémoires des anciens étudiants\n- Vidéos de cours enregistrés\n- Bases de données scientifiques\n\nFormation à l''utilisation : tous les mercredis de 14h à 15h en salle informatique.',
'Lancement de la bibliothèque numérique : 10 000+ ressources accessibles 24h/24. Formation chaque mercredi.',
NULL, 'info', 0, 'Direction de la Bibliothèque', '2025-01-10'),

('Inscription aux clubs et associations',
'Les inscriptions aux clubs et associations étudiantes pour le second semestre sont ouvertes !\n\nClubs disponibles :\n- Club de débat et éloquence\n- Club scientifique\n- Club de musique et chorale\n- Club d''informatique et robotique\n- Club d''entrepreneuriat\n- Club de langues (Anglais, Français, Malgache)\n- Club environnement et développement durable\n\nPour s''inscrire : se présenter au bureau de la vie étudiante (Bâtiment B, salle 102) avec sa carte d''étudiant.\n\nDate limite d''inscription : 20 février 2025.',
'Inscriptions ouvertes pour les clubs étudiants ! Débat, musique, informatique, entrepreneuriat… Avant le 20/02.',
NULL, 'culture', 0, 'Vie Étudiante', '2025-02-01'),

('⚠️ Rappel : Paiement des frais de scolarité',
'Nous rappelons à tous les étudiants que la date limite de paiement du second versement des frais de scolarité est fixée au 28 février 2025.\n\nMODALITÉS DE PAIEMENT :\n- Virement bancaire (BFV-SG, compte universitaire)\n- Mobile Money (MVola, Orange Money)\n- Paiement au guichet de la comptabilité\n\nATTENTION : Tout étudiant n''ayant pas régularisé sa situation financière au-delà de cette date se verra refuser l''accès aux examens du second semestre.\n\nPour les demandes de bourse ou d''échelonnement, contactez le service financier avant le 15 février.',
'⚠️ Date limite paiement 2e versement : 28 février. Accès aux examens refusé en cas de non-paiement.',
NULL, 'urgent', 1, 'Service Financier', '2025-02-05'),

('Conférence : Intelligence Artificielle et Éducation',
'Le département d''informatique organise une conférence ouverte sur le thème « L''Intelligence Artificielle au service de l''Éducation en Afrique ».\n\nDate : Jeudi 6 mars 2025, de 14h à 17h\nLieu : Amphithéâtre A\nEntrée libre\n\nIntervenants :\n- Dr. RAKOTO Andry, Chercheur en IA — Université d''Antananarivo\n- Prof. RASOAMANANA Hery, Expert en EdTech\n- M. RANDRIA Tojo, Fondateur de TechMada\n\nTopics :\n- IA générative dans l''enseignement\n- Plateformes adaptatives d''apprentissage\n- Défis et opportunités pour Madagascar\n\nUn certificat de participation sera délivré.',
'Conférence IA & Éducation le 6 mars à 14h. Amphithéâtre A, entrée libre. Certificat de participation.',
NULL, 'event', 0, 'Département Informatique', '2025-02-20'),

('Horaires de la cantine — Mise à jour',
'Les horaires de la cantine universitaire sont modifiés à partir du 1er février 2025 :\n\nPetit-déjeuner : 6h00 - 7h30\nDéjeuner : 11h30 - 13h30\nDîner : 17h30 - 19h00\n\nMenu du jour affiché chaque matin à l''entrée de la cantine et sur le panneau d''affichage du bâtiment principal.\n\nRappel : l''abonnement mensuel à la cantine est de 120 000 Ar. Les repas à l''unité sont à 4 000 Ar.\n\nPour toute allergie alimentaire, merci de prévenir le responsable de la cantine.',
'Nouveaux horaires cantine dès le 1er février. Petit-déj 6h-7h30, Déjeuner 11h30-13h30, Dîner 17h30-19h.',
NULL, 'info', 0, 'Service Restauration', '2025-01-25'),

('Journée Portes Ouvertes — Ambassadeurs recherchés',
'L''université organise sa Journée Portes Ouvertes le samedi 22 mars 2025. Nous recherchons des étudiants ambassadeurs pour accueillir et guider les visiteurs.\n\nMission des ambassadeurs :\n- Accueillir les lycéens et leurs parents\n- Faire visiter le campus\n- Témoigner de leur expérience universitaire\n- Répondre aux questions des futurs étudiants\n\nAvantages :\n- Attestation de bénévolat\n- Repas offert\n- T-shirt officiel UAZ\n- Points bonus en engagement communautaire\n\nInscription : envoyer un mail à ambassadeurs@uaz.mg avant le 5 mars.',
'Journée Portes Ouvertes le 22 mars — Devenez ambassadeur ! Attestation + repas offert. Inscription avant le 05/03.',
NULL, 'event', 0, 'Service Communication', '2025-02-10');
