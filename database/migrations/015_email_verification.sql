-- ============================================================
-- Migration 015 : Vérification d'adresse email
-- ============================================================

-- Colonnes de vérification ajoutées à la table users
ALTER TABLE `users`
    ADD COLUMN `email_verified_at`             DATETIME NULL DEFAULT NULL                 AFTER `bio`,
    ADD COLUMN `email_verification_required`   TINYINT(1) NOT NULL DEFAULT 0              AFTER `email_verified_at`;

-- Table des tokens de vérification d'email (code à 6 chiffres, usage unique)
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `code`       VARCHAR(6) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_evtoken_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_evtoken_user` (`user_id`),
    INDEX `idx_evtoken_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
