-- -----------------------------------------------------------
-- Migration 028 : Types de jeux globaux
-- Permet de créer des types de jeux disponibles dans tous les espaces,
-- gérés uniquement par les modérateurs et administrateurs globaux.
-- -----------------------------------------------------------

-- Rendre space_id nullable pour les types globaux
ALTER TABLE `game_types`
    ADD COLUMN `is_global` TINYINT(1) NOT NULL DEFAULT 0 AFTER `space_id`,
    MODIFY COLUMN `space_id` INT DEFAULT NULL;

-- Index pour retrouver rapidement les types globaux
CREATE INDEX `idx_game_types_global` ON `game_types` (`is_global`);
