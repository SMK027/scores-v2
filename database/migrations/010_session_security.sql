-- ============================================================
-- Migration 010 : Sécurité des sessions de compétition
-- ============================================================

-- Agrandir le mot de passe de 6 à 12 caractères
ALTER TABLE `competition_sessions` MODIFY COLUMN `password` VARCHAR(12) NOT NULL;

-- Ajouter l'email de l'arbitre
ALTER TABLE `competition_sessions` ADD COLUMN `referee_email` VARCHAR(255) DEFAULT NULL AFTER `referee_name`;

-- Ajouter le verrouillage de session (tentatives échouées)
ALTER TABLE `competition_sessions` ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

-- Table des tentatives de connexion aux sessions d'arbitrage
CREATE TABLE IF NOT EXISTS `session_login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `competition_sessions`(`id`) ON DELETE CASCADE,
    INDEX `idx_session_attempts` (`session_id`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
