-- =============================================================
-- Migration 005 : Système de bannissement (comptes & IPs)
-- Bannissements temporaires ou permanents
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Table : user_bans
-- Bannissements de comptes utilisateurs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_bans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `banned_by` INT DEFAULT NULL COMMENT 'NULL si bannissement automatique',
    `reason` TEXT NOT NULL,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'NULL = bannissement permanent',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `revoked_by` INT DEFAULT NULL COMMENT 'Admin qui a annulé le ban',
    `revoked_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`banned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`revoked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_bans_user` (`user_id`),
    INDEX `idx_user_bans_active` (`is_active`, `user_id`),
    INDEX `idx_user_bans_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : ip_bans
-- Bannissements d'adresses IP
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ip_bans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 ou IPv6',
    `banned_by` INT DEFAULT NULL COMMENT 'NULL si bannissement automatique',
    `reason` TEXT NOT NULL,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'NULL = bannissement permanent',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `revoked_by` INT DEFAULT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`banned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`revoked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_ip_bans_ip` (`ip_address`),
    INDEX `idx_ip_bans_active` (`is_active`, `ip_address`),
    INDEX `idx_ip_bans_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
