-- Migration 031: Sessions de jeux interactifs en ligne
-- Stocke les parties jouables en ligne (morpion, yams, etc.)

CREATE TABLE IF NOT EXISTS interactive_game_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    space_id INT NOT NULL,
    game_key VARCHAR(50) NOT NULL COMMENT 'Identifiant du jeu : morpion, yams',
    status ENUM('waiting', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
    created_by INT NOT NULL,
    player1_id INT NOT NULL,
    player2_id INT DEFAULT NULL,
    winner_id INT DEFAULT NULL,
    game_state JSON NOT NULL COMMENT 'État complet du jeu (plateau, scores, tour actuel…)',
    current_turn INT DEFAULT NULL COMMENT 'user_id du joueur dont c''est le tour',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_igs_space (space_id),
    INDEX idx_igs_status (status),
    INDEX idx_igs_game_key (game_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
