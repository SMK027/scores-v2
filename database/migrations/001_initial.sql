-- =============================================================
-- Migration initiale : Création de toutes les tables
-- Application Scores - Gestion de parties de jeux
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Table : users
-- Utilisateurs de l'application
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `global_role` ENUM('superadmin', 'admin', 'moderator', 'user') DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_global_role` (`global_role`),
    INDEX `idx_users_username` (`username`),
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : spaces
-- Espaces de jeu (chaque espace a ses propres joueurs, parties, etc.)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `spaces` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_spaces_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : space_members
-- Membres d'un espace avec leurs rôles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `space_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('admin', 'manager', 'member', 'guest') DEFAULT 'member',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_space_user` (`space_id`, `user_id`),
    INDEX `idx_space_members_user` (`user_id`),
    INDEX `idx_space_members_space` (`space_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : space_invites
-- Invitations à rejoindre un espace (via lien)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `space_invites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `created_by` INT NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_space_invites_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : game_types
-- Types de jeux disponibles dans un espace
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `win_condition` ENUM('highest_score', 'lowest_score', 'win_loss', 'ranking') DEFAULT 'highest_score',
    `min_players` INT DEFAULT 2,
    `max_players` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    INDEX `idx_game_types_space` (`space_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : players
-- Joueurs d'un espace (peuvent être liés à un compte utilisateur)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `players` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_players_space` (`space_id`),
    INDEX `idx_players_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : games
-- Parties de jeu
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `games` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `game_type_id` INT NOT NULL,
    `status` ENUM('pending', 'in_progress', 'paused', 'completed') DEFAULT 'pending',
    `started_at` TIMESTAMP NULL,
    `ended_at` TIMESTAMP NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_type_id`) REFERENCES `game_types`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_games_space` (`space_id`),
    INDEX `idx_games_type` (`game_type_id`),
    INDEX `idx_games_status` (`status`),
    INDEX `idx_games_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : game_players
-- Joueurs participant à une partie
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_players` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `player_id` INT NOT NULL,
    `total_score` DECIMAL(10,2) DEFAULT 0,
    `rank` INT DEFAULT NULL,
    `is_winner` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_game_player` (`game_id`, `player_id`),
    INDEX `idx_game_players_game` (`game_id`),
    INDEX `idx_game_players_player` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : rounds
-- Manches d'une partie
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rounds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `round_number` INT NOT NULL,
    `status` ENUM('in_progress', 'paused', 'completed') DEFAULT 'in_progress',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
    INDEX `idx_rounds_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : round_scores
-- Scores de chaque joueur pour une manche
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `round_scores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `round_id` INT NOT NULL,
    `player_id` INT NOT NULL,
    `score` DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (`round_id`) REFERENCES `rounds`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_round_player` (`round_id`, `player_id`),
    INDEX `idx_round_scores_round` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table : comments
-- Commentaires sur les parties
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_comments_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------
-- Insertion du compte super administrateur par défaut
-- Mot de passe : Admin123! (hashé avec password_hash)
-- -----------------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `global_role`)
VALUES ('admin', 'admin@scores.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin');
