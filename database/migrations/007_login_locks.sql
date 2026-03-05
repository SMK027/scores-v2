-- ============================================================
-- Migration 007 : Login Locks – verrous fail2ban (login seul)
-- ============================================================

-- Remplace l'utilisation des bans globaux (ip_bans / user_bans)
-- pour le fail2ban : ces verrous bloquent UNIQUEMENT la connexion,
-- pas l'accès au reste du site.

CREATE TABLE IF NOT EXISTS login_locks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    user_id     INT          DEFAULT NULL,
    locked_until DATETIME    NOT NULL,
    reason      VARCHAR(255) NOT NULL DEFAULT '',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_locked   (ip_address, locked_until),
    INDEX idx_user_locked (user_id, locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
