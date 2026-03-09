-- ============================================================
-- Migration 013 : Restrictions de fonctionnalités par espace
-- ============================================================

ALTER TABLE `spaces`
    ADD COLUMN `restrictions` JSON DEFAULT NULL
        COMMENT 'Fonctionnalités restreintes (ex: {"games":true,"members":true})'
        AFTER `description`,
    ADD COLUMN `restriction_reason` VARCHAR(500) DEFAULT NULL
        COMMENT 'Motif de la restriction'
        AFTER `restrictions`,
    ADD COLUMN `restricted_by` INT DEFAULT NULL
        COMMENT 'Administrateur ayant posé la restriction'
        AFTER `restriction_reason`,
    ADD COLUMN `restricted_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Date de mise en place de la restriction'
        AFTER `restricted_by`;
