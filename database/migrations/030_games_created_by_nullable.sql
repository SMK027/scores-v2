-- ============================================================
-- Migration 030 : Rendre created_by nullable dans games
-- Permet les parties créées via l'interface arbitre (referee JWT)
-- sans compte utilisateur associé.
-- ============================================================

ALTER TABLE `games`
    MODIFY COLUMN `created_by` INT DEFAULT NULL;
