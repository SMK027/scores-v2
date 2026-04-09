-- Migration 032: Support multi-joueurs (1-4) pour les jeux interactifs
-- Ajoute une table de joueurs flexible et un champ max_players

-- Table des joueurs par session (remplace player1_id/player2_id)
CREATE TABLE IF NOT EXISTS interactive_game_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    player_number TINYINT NOT NULL COMMENT 'Numéro du joueur (1-4)',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES interactive_game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_igp_session_user (session_id, user_id),
    UNIQUE KEY uq_igp_session_number (session_id, player_number),
    INDEX idx_igp_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout du nombre max de joueurs à la table sessions
ALTER TABLE interactive_game_sessions
    ADD COLUMN max_players TINYINT NOT NULL DEFAULT 2 AFTER game_key;

-- Migration des données existantes vers la nouvelle table
INSERT IGNORE INTO interactive_game_players (session_id, user_id, player_number)
SELECT id, player1_id, 1 FROM interactive_game_sessions WHERE player1_id IS NOT NULL;

INSERT IGNORE INTO interactive_game_players (session_id, user_id, player_number)
SELECT id, player2_id, 2 FROM interactive_game_sessions WHERE player2_id IS NOT NULL;
