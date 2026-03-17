-- ============================================================
-- Migration 022 : Types de jeu autorisés et compétiteurs par compétition
-- ============================================================

CREATE TABLE IF NOT EXISTS `competition_game_types` (
    `competition_id` INT NOT NULL,
    `game_type_id`   INT NOT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`competition_id`, `game_type_id`),
    INDEX `idx_cgt_game_type` (`game_type_id`),
    FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_type_id`) REFERENCES `game_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competition_players` (
    `competition_id` INT NOT NULL,
    `player_id`      INT NOT NULL,
    `added_by`       INT DEFAULT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`competition_id`, `player_id`),
    INDEX `idx_cp_player` (`player_id`),
    INDEX `idx_cp_added_by` (`added_by`),
    FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;