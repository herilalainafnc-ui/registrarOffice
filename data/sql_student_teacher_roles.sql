-- =============================================================================
-- SCRIPT SQL POUR LA MIGRATION DES RÔLES UTILISATEURS
-- =============================================================================
-- Ce script met à jour le système de rôles de 6 niveaux vers 8 niveaux.
-- Les colonnes student_id, teacher_uid existent déjà.
-- 
-- Date: 2026-02-09
-- =============================================================================

-- =========================================
-- ÉTAPE 1: Modifier le ENUM de user_type
-- =========================================
ALTER TABLE `compt_utilisateur` 
MODIFY COLUMN `user_type` ENUM('superadmin', 'admin', 'registrar', 'comptabilite', 'media', 'chef_mention', 'teacher', 'student') DEFAULT 'admin' COMMENT 'Type d utilisateur';

-- =========================================
-- ÉTAPE 2: Migration des anciens niveaux
-- =========================================
-- IMPORTANT: Exécuter dans cet ordre (du plus élevé au plus bas) 
-- pour éviter les conflits de remap !

-- Ancien 6 (Student)   → Nouveau 8
UPDATE `compt_utilisateur` SET `level` = 8, `privilege` = 'student', `user_type` = 'student' WHERE `level` = 6 AND `privilege` = 'student';

-- Ancien 5 (Teacher)   → Nouveau 7
UPDATE `compt_utilisateur` SET `level` = 7, `privilege` = 'teacher', `user_type` = 'teacher' WHERE `level` = 5 AND `privilege` = 'teacher';

-- Ancien 4 (Visitor)   → Nouveau 5 (Média, rôle le plus proche)
UPDATE `compt_utilisateur` SET `level` = 5, `privilege` = 'media', `user_type` = 'media' WHERE `level` = 4 AND `privilege` = 'visitor';

-- Ancien 3 (User)      → Nouveau 4 (Comptabilité, rôle le plus proche)
UPDATE `compt_utilisateur` SET `level` = 4, `privilege` = 'comptabilite', `user_type` = 'comptabilite' WHERE `level` = 3 AND `privilege` = 'user';

-- Ancien 2 (Registrar) → Nouveau 3
UPDATE `compt_utilisateur` SET `level` = 3, `privilege` = 'registrar', `user_type` = 'registrar' WHERE `level` = 2 AND `privilege` = 'registrar';

-- Ancien 1 (Admin)     → Nouveau 2
UPDATE `compt_utilisateur` SET `level` = 2, `privilege` = 'administrator', `user_type` = 'admin' WHERE `level` = 1 AND `privilege` = 'administrator';

-- =========================================
-- Niveaux de rôles (nouveau système):
-- =========================================
-- Niveau 1 = Superadmin
-- Niveau 2 = Administrator (Admin)
-- Niveau 3 = Registrar (Registraire)
-- Niveau 4 = Comptabilité
-- Niveau 5 = Média
-- Niveau 6 = Chef de mention
-- Niveau 7 = Teacher (Professeur)
-- Niveau 8 = Student (Étudiant)

-- =========================================
-- EXEMPLES DE CRÉATION DE COMPTES
-- =========================================

-- Superadmin:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 1, 'superadmin', 'superadmin', 1);

-- Administrateur:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 2, 'administrator', 'admin', 1);

-- Registraire:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 3, 'registrar', 'registrar', 1);

-- Comptabilité:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 4, 'comptabilite', 'comptabilite', 1);

-- Média:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 5, 'media', 'media', 1);

-- Chef de mention:
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 6, 'chef_mention', 'chef_mention', 1);

-- Professeur (lié):
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, teacher_uid, etat)
-- VALUES ('Nom', 'Prenom', 'login', 'hash', 7, 'teacher', 'teacher', 123, 1);

-- Étudiant (lié):
-- INSERT INTO compt_utilisateur (nom, prenom, pseudo, password, level, privilege, user_type, student_id, etat)
-- VALUES ('Nom', 'Prenom', 'matricule', 'hash', 8, 'student', 'student', 'STD-2024-001', 1);
