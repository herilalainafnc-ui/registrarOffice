-- =============================================================================
-- SCRIPT SQL POUR LES RÔLES ÉTUDIANT ET PROFESSEUR
-- =============================================================================
-- Ce script ajoute les colonnes nécessaires pour lier les comptes utilisateurs
-- aux profils étudiants et professeurs.
-- 
-- Date: 2026-01-27
-- =============================================================================

-- Ajouter les colonnes de liaison dans compt_utilisateur
ALTER TABLE `compt_utilisateur` 
ADD COLUMN `student_id` VARCHAR(50) DEFAULT NULL COMMENT 'Lien vers student_id si utilisateur est étudiant',
ADD COLUMN `teacher_uid` INT DEFAULT NULL COMMENT 'Lien vers teacher.uid si utilisateur est professeur',
ADD COLUMN `user_type` ENUM('admin', 'staff', 'teacher', 'student') DEFAULT 'staff' COMMENT 'Type d utilisateur';

-- Index pour améliorer les performances
ALTER TABLE `compt_utilisateur`
ADD INDEX `idx_student_id` (`student_id`),
ADD INDEX `idx_teacher_uid` (`teacher_uid`),
ADD INDEX `idx_user_type` (`user_type`);

-- Ajouter les niveaux pour étudiant (5) et professeur (6)
-- Niveau 1 = Administrator
-- Niveau 2 = Registrar
-- Niveau 3 = User
-- Niveau 4 = Visitor
-- Niveau 5 = Teacher (Professeur)
-- Niveau 6 = Student (Étudiant)

-- Exemple pour créer un compte professeur lié:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, teacher_uid, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 5, 'teacher', 'teacher', 123, 1);

-- Exemple pour créer un compte étudiant lié:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, student_id, etat)
-- VALUES ('Nom', 'Prenom', 'matricule', 'hash', 6, 'student', 'student', 'STD-2024-001', 1);
