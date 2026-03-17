-- ============================================================
-- Migration 023 : Session arbitre liée à un compte utilisateur
-- ============================================================

ALTER TABLE competition_sessions
ADD COLUMN referee_user_id INT DEFAULT NULL AFTER referee_email;

ALTER TABLE competition_sessions
ADD CONSTRAINT fk_competition_sessions_referee_user
FOREIGN KEY (referee_user_id) REFERENCES users(id)
ON DELETE SET NULL;

CREATE INDEX idx_comp_sessions_referee_user ON competition_sessions(referee_user_id);