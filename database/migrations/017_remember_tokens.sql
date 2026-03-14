-- -----------------------------------------------------------
-- Table : remember_tokens
-- Tokens persistants "Se souvenir de moi" (7 jours)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `selector` VARCHAR(24) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME DEFAULT NULL,
    `created_ip` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uniq_remember_selector` (`selector`),
    INDEX `idx_remember_user` (`user_id`),
    INDEX `idx_remember_expires` (`expires_at`),
    CONSTRAINT `fk_remember_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
