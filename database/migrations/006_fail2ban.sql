-- ============================================================
-- Migration 006 : Fail2ban – tentatives de connexion et configuration
-- ============================================================

-- Table des tentatives de connexion échouées
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45)  NOT NULL,
    email       VARCHAR(255) DEFAULT NULL,
    user_id     INT          DEFAULT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_user_time (user_id, attempted_at),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration fail2ban (clé / valeur, même pattern que password_policy)
CREATE TABLE IF NOT EXISTS fail2ban_config (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(50)  NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    label         VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valeurs par défaut
INSERT INTO fail2ban_config (setting_key, setting_value, label) VALUES
    ('enabled',        '1',  'Activer le fail2ban'),
    ('max_attempts',   '3',  'Nombre maximal de tentatives'),
    ('window_minutes', '15', 'Fenêtre de temps (minutes)'),
    ('ban_duration',   '30', 'Durée du bannissement (minutes)'),
    ('ban_ip',         '1',  'Bannir l''adresse IP'),
    ('ban_account',    '1',  'Bannir le compte utilisateur'),
    ('exempt_staff',   '1',  'Exempter les comptes staff (modérateur, admin, superadmin)');
