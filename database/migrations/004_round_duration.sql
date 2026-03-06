-- =============================================================
-- Migration 004 : Durée des manches et suivi des pauses
-- Ajout de started_at / ended_at sur les manches
-- Création de la table round_pauses
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Ajouter les colonnes de timing sur les manches (si elles n'existent pas)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rounds' AND COLUMN_NAME = 'started_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `rounds` ADD COLUMN `started_at` DATETIME DEFAULT NULL AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rounds' AND COLUMN_NAME = 'ended_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `rounds` ADD COLUMN `ended_at` DATETIME DEFAULT NULL AFTER `started_at`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Initialiser started_at à partir de created_at pour les manches existantes
UPDATE `rounds` SET `started_at` = `created_at` WHERE `started_at` IS NULL;

-- Initialiser ended_at pour les manches déjà terminées
UPDATE `rounds` SET `ended_at` = `updated_at` WHERE `status` = 'completed' AND `ended_at` IS NULL;

-- -----------------------------------------------------------
-- Table : round_pauses
-- Historique des pauses de chaque manche
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `round_pauses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `round_id` INT NOT NULL,
    `paused_at` DATETIME NOT NULL,
    `resumed_at` DATETIME DEFAULT NULL,
    `duration_seconds` INT UNSIGNED DEFAULT NULL COMMENT 'Durée de la pause en secondes (calculé au resume)',
    FOREIGN KEY (`round_id`) REFERENCES `rounds`(`id`) ON DELETE CASCADE,
    INDEX `idx_round_pauses_round` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
