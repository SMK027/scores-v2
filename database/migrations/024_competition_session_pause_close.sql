-- ============================================================
-- Migration 024 : Pause temporaire et fermeture definitive de session arbitre
-- ============================================================

ALTER TABLE competition_sessions
ADD COLUMN pause_until DATETIME DEFAULT NULL AFTER is_locked,
ADD COLUMN closed_at DATETIME DEFAULT NULL AFTER pause_until;

CREATE INDEX idx_comp_sessions_pause_until ON competition_sessions(pause_until);
CREATE INDEX idx_comp_sessions_closed_at ON competition_sessions(closed_at);
