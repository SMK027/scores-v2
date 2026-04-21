-- Migration 037 : Table des notifications in-app
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED    NOT NULL,
    type       VARCHAR(64)     NOT NULL,
    title      VARCHAR(255)    NOT NULL,
    message    TEXT            NOT NULL,
    url        VARCHAR(512)    DEFAULT NULL,
    is_read    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user_unread  (user_id, is_read),
    INDEX idx_notif_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
