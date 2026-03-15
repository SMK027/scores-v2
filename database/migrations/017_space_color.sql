-- ============================================================
-- Migration 017 : Couleur personnalisée des espaces
-- ============================================================

ALTER TABLE `spaces`
    ADD COLUMN `color` VARCHAR(7) NULL DEFAULT NULL
        COMMENT 'Couleur d\'accentuation de la carte espace (ex: #4361ee)'
        AFTER `description`;
