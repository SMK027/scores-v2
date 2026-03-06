-- =============================================================
-- Migration : Table de politique de mot de passe
-- Configuration dynamique gérée depuis l'administration
-- =============================================================

CREATE TABLE IF NOT EXISTS `password_policy` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` VARCHAR(255) NOT NULL,
    `label` VARCHAR(100) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valeurs initiales de la politique
INSERT IGNORE INTO `password_policy` (`setting_key`, `setting_value`, `label`) VALUES
    ('min_length',        '12',  'Longueur minimale'),
    ('require_lowercase', '1',   'Exiger une minuscule'),
    ('require_uppercase', '1',   'Exiger une majuscule'),
    ('require_digit',     '1',   'Exiger un chiffre'),
    ('require_special',   '1',   'Exiger un caractère spécial');
