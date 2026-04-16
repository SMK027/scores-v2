-- Migration 036: Lobbies pour jeux interactifs
-- Salons persistants permettant de jouer ensemble plusieurs parties

CREATE TABLE IF NOT EXISTS lobbies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    space_id INT NOT NULL,
    created_by INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    game_key VARCHAR(50) NOT NULL COMMENT 'morpion, yams',
    game_config JSON NOT NULL COMMENT 'Configuration du jeu (grid_size, align_count, max_players, bot…)',
    visibility ENUM('public', 'private') NOT NULL DEFAULT 'public',
    status ENUM('open', 'in_game', 'closed') NOT NULL DEFAULT 'open',
    current_session_id INT DEFAULT NULL COMMENT 'Session interactive en cours',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (current_session_id) REFERENCES interactive_game_sessions(id) ON DELETE SET NULL,
    INDEX idx_lobby_space (space_id),
    INDEX idx_lobby_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lobby_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lobby_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lobby_id) REFERENCES lobbies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_lobby_member (lobby_id, user_id),
    INDEX idx_lm_lobby (lobby_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lobby_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lobby_id INT NOT NULL,
    invited_user_id INT NOT NULL,
    invited_by INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lobby_id) REFERENCES lobbies(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_lobby_invite (lobby_id, invited_user_id),
    INDEX idx_li_lobby (lobby_id),
    INDEX idx_li_invited (invited_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter le lien lobby sur les sessions de jeux interactifs
ALTER TABLE interactive_game_sessions
    ADD COLUMN lobby_id INT DEFAULT NULL AFTER space_id,
    ADD FOREIGN KEY fk_igs_lobby (lobby_id) REFERENCES lobbies(id) ON DELETE SET NULL,
    ADD INDEX idx_igs_lobby (lobby_id);
