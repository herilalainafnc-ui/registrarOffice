-- =============================================================================
-- Integrite des matricules (tbl_2024_etudiant.student_id)
-- =============================================================================
--
-- Contexte : la verification anti-doublon de app/.student/addStd.php lisait
-- une variable mal orthographiee et ne s'est donc jamais declenchee. Des
-- etudiants DISTINCTS ont recu le meme matricule. La colonne n'a par
-- ailleurs aucun index.
--
-- Ce fichier se lit en DEUX ETAPES. La premiere est sans risque et peut
-- etre appliquee immediatement. La seconde exige une decision humaine.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- ETAPE 1 — Index simple (non unique).  A APPLIQUER MAINTENANT.
-- -----------------------------------------------------------------------------
-- Sans risque : n'interdit rien, ne modifie aucune donnee. Accelere les
-- recherches par matricule, devenues frequentes depuis que la creation
-- d'etudiant verifie la disponibilite du matricule avant d'ecrire.
--
-- MySQL n'a pas de « CREATE INDEX IF NOT EXISTS » : relancer cette commande
-- sur une base ou l'index existe deja renvoie une erreur, sans dommage.

ALTER TABLE `tbl_2024_etudiant`
    ADD INDEX `idx_student_id` (`student_id`);


-- -----------------------------------------------------------------------------
-- ETAPE 2 — Contrainte d'unicite.  NE PAS APPLIQUER EN L'ETAT.
-- -----------------------------------------------------------------------------
-- Cette contrainte est le seul garde-fou vraiment fiable : elle empeche la
-- base d'accepter un doublon, quoi que fasse l'application.
--
-- Elle ECHOUERA tant que des doublons subsistent. Les identifier :
--
--     SELECT student_id, COUNT(*) AS n
--       FROM tbl_2024_etudiant
--      WHERE student_id IS NOT NULL AND student_id <> ''
--      GROUP BY student_id
--     HAVING n > 1;
--
-- Les resoudre demande de decider QUEL etudiant conserve le matricule et
-- lequel en recoit un nouveau. Ce choix a des consequences hors du logiciel
-- (releves, diplomes, badges, cartes deja emis) : il revient au bureau du
-- registrariat, pas a un script.
--
-- Une fois les doublons resolus, decommenter :

-- ALTER TABLE `tbl_2024_etudiant`
--     DROP INDEX `idx_student_id`,
--     ADD UNIQUE INDEX `uniq_student_id` (`student_id`);

-- Note : la colonne est NULLable. En MySQL, plusieurs NULL restent permis
-- sous une contrainte UNIQUE — ce qui convient ici, aucun matricule n'etant
-- actuellement NULL.
