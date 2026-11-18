-- =============================================================================
-- Work Education : le compte du responsable et la tache de chaque etudiant.
--
-- 1. Un type de compte de plus, « work_education » (niveau 9, voir
--    ROLE_WORK_EDUCATION dans data/middleware.php) : la personne qui attribue
--    le Work Education. Elle ne voit que /work-education.
-- 2. La tache attribuee, une par etudiant et par session. Elle s'imprime dans
--    le champ « Work education » de la fiche d'inscription
--    (src/extenssionPrint/fiche_inscription.php). Page : src/work-education.php,
--    donnees : data/work.education.php.
--
-- Les anciennes tables work_education (catalogue de 94 taches, repris comme
-- suggestions) et grade_worked_chapelle (rien depuis janvier 2023, anciens
-- numeros de session) ne sont pas modifiees.
--
-- A appliquer une fois, a la main (local puis production).
-- Retour arriere :
--   DROP TABLE t_work_education;
--   ALTER TABLE compt_utilisateur MODIFY user_type ENUM('superadmin','admin','registrar','comptabilite','media','chef_mention','teacher','student')
--     CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'admin' COMMENT 'Type d utilisateur';
--   (apres avoir change le type des comptes work_education, sinon ils passent a vide)
-- =============================================================================

ALTER TABLE compt_utilisateur
  MODIFY user_type ENUM('superadmin','admin','registrar','comptabilite','media','chef_mention','teacher','student','work_education')
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'admin' COMMENT 'Type d utilisateur';

CREATE TABLE IF NOT EXISTS t_work_education (
  id           INT          NOT NULL AUTO_INCREMENT,
  session_id   INT          NOT NULL,
  student_id   VARCHAR(20)  NOT NULL COMMENT 'matricule, comme t_2024_inscription_session',
  tache        VARCHAR(200) NOT NULL COMMENT 'ce que fait l etudiant, tel que saisi',
  attribue_par INT          NULL,
  attribue_le  DATETIME     NULL,
  modifie_par  INT          NULL,
  modifie_le   DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_work_education (session_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
