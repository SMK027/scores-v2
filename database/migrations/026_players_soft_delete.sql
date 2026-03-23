-- -----------------------------------------------------------
-- Migration 026 : Soft delete des joueurs
-- -----------------------------------------------------------

ALTER TABLE `players`
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `players`
    ADD INDEX `idx_players_deleted_at` (`deleted_at`),
    ADD INDEX `idx_players_space_deleted` (`space_id`, `deleted_at`);
