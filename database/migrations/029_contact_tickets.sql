-- -----------------------------------------------------------
-- Migration 029 : Système de tickets de contact
-- Permet aux gestionnaires/admins d'espace de communiquer
-- avec l'équipe de modération du site.
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `contact_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `category` ENUM('assistance', 'competition_request', 'restriction_contest', 'member_report', 'bug_report') NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('open', 'in_progress', 'closed') NOT NULL DEFAULT 'open',
    `closed_by` INT DEFAULT NULL,
    `closed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `body` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `contact_tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX `idx_contact_tickets_space` ON `contact_tickets` (`space_id`);
CREATE INDEX `idx_contact_tickets_status` ON `contact_tickets` (`status`);
CREATE INDEX `idx_contact_messages_ticket` ON `contact_messages` (`ticket_id`);
