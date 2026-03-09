-- ============================================================
-- Migration 012 : Invitations nominatives aux espaces (consentement)
-- ============================================================

CREATE TABLE IF NOT EXISTS `space_invitations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `invited_user_id` INT NOT NULL,
    `invited_by` INT NOT NULL,
    `role` ENUM('admin', 'manager', 'member', 'guest') NOT NULL DEFAULT 'member',
    `status` ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_invited_user_pending` (`invited_user_id`, `status`),
    INDEX `idx_space_status` (`space_id`, `status`),
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
