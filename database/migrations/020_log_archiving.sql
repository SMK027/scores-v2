-- ============================================================
-- Migration 020 : Archivage chiffré des logs d'activité
-- ============================================================
-- Les logs d'activité de plus de 3 mois sont archivés et chiffrés
-- via AES_ENCRYPT dans la table activity_logs_archive.
-- Les archives de plus de 6 mois sont purgées automatiquement.
-- La procédure archive_activity_logs() est appelée quotidiennement
-- par le cron bin/archive-logs.php.
-- ============================================================

-- Table de configuration de l'archivage (clé de chiffrement)
CREATE TABLE IF NOT EXISTS `archive_config` (
    `key_name`   VARCHAR(50) PRIMARY KEY,
    `key_value`  TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Générer une clé de chiffrement aléatoire unique (SHA2-256, 64 chars hex)
-- INSERT IGNORE garantit qu'une éventuelle re-exécution ne l'écrase pas.
INSERT IGNORE INTO `archive_config` (`key_name`, `key_value`)
VALUES ('log_encryption_key', SHA2(CONCAT(UUID(), '-', RAND(), '-', NOW(6)), 256));

-- Table des archives chiffrées
CREATE TABLE IF NOT EXISTS `activity_logs_archive` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `original_id`         INT            NOT NULL COMMENT 'ID original dans activity_logs',
    `original_created_at` TIMESTAMP      NOT NULL COMMENT 'Date de création du log original',
    `archived_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'archivage',
    `payload`             BLOB           NOT NULL COMMENT 'JSON chiffré via AES_ENCRYPT (AES-128-ECB)',
    INDEX `idx_archived_at`      (`archived_at`),
    INDEX `idx_original_id`      (`original_id`),
    INDEX `idx_original_created` (`original_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Procédure stockée : archive_activity_logs()
-- ============================================================

DELIMITER //

CREATE OR REPLACE PROCEDURE `archive_activity_logs`()
BEGIN
    DECLARE enc_key      TEXT    DEFAULT NULL;
    DECLARE rows_archived INT    DEFAULT 0;
    DECLARE rows_purged   INT    DEFAULT 0;

    -- Récupérer la clé de chiffrement depuis la table de configuration
    SELECT key_value INTO enc_key
    FROM archive_config
    WHERE key_name = 'log_encryption_key'
    LIMIT 1;

    IF enc_key IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Clé de chiffrement introuvable dans archive_config';
    END IF;

    -- Archiver les logs de plus de 3 mois non encore archivés
    -- LEFT JOIN pour éviter les doublons sans sous-requête coûteuse
    INSERT INTO activity_logs_archive (original_id, original_created_at, payload)
    SELECT
        al.id,
        al.created_at,
        AES_ENCRYPT(
            JSON_OBJECT(
                'id',          al.id,
                'scope',       al.scope,
                'scope_id',    al.scope_id,
                'action',      al.action,
                'entity_type', al.entity_type,
                'entity_id',   al.entity_id,
                'user_id',     al.user_id,
                'session_id',  al.session_id,
                'ip_address',  al.ip_address,
                'details',     al.details,
                'created_at',  DATE_FORMAT(al.created_at, '%Y-%m-%d %H:%i:%s')
            ),
            enc_key
        )
    FROM activity_logs al
    LEFT JOIN activity_logs_archive ala ON al.id = ala.original_id
    WHERE al.created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
      AND ala.original_id IS NULL;

    SET rows_archived = ROW_COUNT();

    -- Supprimer de activity_logs les entrées désormais archivées
    DELETE al
    FROM activity_logs al
    INNER JOIN activity_logs_archive ala ON al.id = ala.original_id
    WHERE al.created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);

    -- Purger les archives de plus de 6 mois d'archivage
    DELETE FROM activity_logs_archive
    WHERE archived_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

    SET rows_purged = ROW_COUNT();

    -- Journaliser l'opération (uniquement si quelque chose a changé)
    IF rows_archived > 0 OR rows_purged > 0 THEN
        INSERT INTO activity_logs (scope, action, details)
        VALUES (
            'admin',
            'logs.archive',
            JSON_OBJECT(
                'archived', rows_archived,
                'purged',   rows_purged
            )
        );
    END IF;
END //

DELIMITER ;
