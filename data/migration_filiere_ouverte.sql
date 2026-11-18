-- =============================================================================
-- Ouverture / fermeture d'une mention
--
-- POURQUOI
-- Une mention peut cesser d'etre proposee sans disparaitre : le Cours
-- Preparatoire n'accueille personne cette annee, l'Education n'a plus
-- d'inscription depuis 2024 mais compte encore 43 etudiants et 4 parcours.
-- Jusqu'ici, rien ne permettait de la retirer des listes de choix : elle
-- restait proposee a la creation d'un etudiant comme si elle recrutait.
--
-- CE QUE LA COLONNE CHANGE, ET CE QU'ELLE NE CHANGE PAS
-- Une mention fermee disparait des listes ou l'on CHOISIT une mention pour
-- une donnee nouvelle. Elle reste visible partout ou des donnees existantes
-- s'y referent : fiches etudiantes, releves, statistiques, filtres
-- d'impression, bareme financier. Un releve de notes de Theologie doit
-- continuer d'afficher Theologie, meme si la mention ferme.
--
-- Valeur par defaut 1 : toutes les mentions existantes restent ouvertes, donc
-- l'application se comporte exactement comme avant cette migration.
--
-- Rejouable : la colonne n'est ajoutee que si elle manque.
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'filiere'
       AND COLUMN_NAME = 'ouverte'
);

SET @sql := IF(
    @existe = 0,
    'ALTER TABLE filiere
        ADD COLUMN ouverte TINYINT(1) NOT NULL DEFAULT 1
            COMMENT "1 = proposee aux nouvelles inscriptions, 0 = fermee"',
    'DO 0'
);

PREPARE requete FROM @sql;
EXECUTE requete;
DEALLOCATE PREPARE requete;
