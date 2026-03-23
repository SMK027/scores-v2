-- -----------------------------------------------------------
-- Migration 027 : Cartes de membre numériques
-- Une carte par joueur par espace, signée numériquement (HMAC-SHA256).
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `member_cards` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `player_id`   INT NOT NULL,
    `space_id`    INT NOT NULL,
    `reference`   VARCHAR(32) NOT NULL,
    `signature`   VARCHAR(64) NOT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_member_cards_player_space` (`player_id`, `space_id`),
    UNIQUE KEY `uq_member_cards_reference`    (`reference`),
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`space_id`)  REFERENCES `spaces`(`id`)  ON DELETE CASCADE,
    INDEX `idx_member_cards_reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
