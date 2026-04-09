-- Ajouter le statut 'paused' aux sessions interactives
ALTER TABLE interactive_game_sessions
    MODIFY COLUMN status ENUM('waiting', 'in_progress', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting';
