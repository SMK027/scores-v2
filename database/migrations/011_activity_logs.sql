-- ============================================================
-- Migration 011 : Table de journalisation des activités
-- ============================================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `scope` ENUM('space', 'competition', 'admin', 'auth') NOT NULL,
    `scope_id` INT DEFAULT NULL COMMENT 'space_id ou competition_id selon le scope',
    `action` VARCHAR(100) NOT NULL COMMENT 'Action effectuée (ex: game.create, round.delete)',
    `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'Type entité concernée (game, round, player...)',
    `entity_id` INT DEFAULT NULL COMMENT 'ID de l entité concernée',
    `user_id` INT DEFAULT NULL COMMENT 'Utilisateur ayant effectué l action',
    `session_id` INT DEFAULT NULL COMMENT 'Session arbitrage si action via compétition',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `details` JSON DEFAULT NULL COMMENT 'Détails supplémentaires en JSON',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_scope` (`scope`, `scope_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
