-- -----------------------------------------------------------
-- Table : competitions
-- Compétitions organisées sur un espace
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('planned', 'active', 'closed') DEFAULT 'planned',
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_competitions_space` (`space_id`),
    INDEX `idx_competitions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : competition_sessions
-- Sessions de saisie pour une compétition (une par table/arbitre)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competition_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `competition_id` INT NOT NULL,
    `session_number` INT NOT NULL,
    `referee_name` VARCHAR(255) NOT NULL,
    `password` VARCHAR(6) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE,
    INDEX `idx_comp_sessions_competition` (`competition_id`),
    UNIQUE KEY `unique_comp_session_number` (`competition_id`, `session_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Colonne competition_id sur la table games
-- Permet de lier une partie à une compétition (nullable)
-- -----------------------------------------------------------
ALTER TABLE `games` ADD COLUMN `competition_id` INT DEFAULT NULL AFTER `space_id`;
ALTER TABLE `games` ADD COLUMN `session_id` INT DEFAULT NULL AFTER `competition_id`;
ALTER TABLE `games` ADD INDEX `idx_games_competition` (`competition_id`);
ALTER TABLE `games` ADD INDEX `idx_games_session` (`session_id`);
