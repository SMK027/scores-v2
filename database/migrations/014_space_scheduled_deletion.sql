-- ============================================================
-- Migration 014 : Auto-destruction programmée d'un espace
-- ============================================================

ALTER TABLE `spaces`
    ADD COLUMN `scheduled_deletion_at` DATETIME DEFAULT NULL
        COMMENT 'Date/heure de suppression automatique (fuseau Europe/Paris)'
        AFTER `restricted_at`,
    ADD COLUMN `deletion_reason` VARCHAR(500) DEFAULT NULL
        COMMENT 'Motif de la suppression programmée'
        AFTER `scheduled_deletion_at`,
    ADD COLUMN `deletion_scheduled_by` INT DEFAULT NULL
        COMMENT 'Administrateur ayant programmé la suppression'
        AFTER `deletion_reason`;
