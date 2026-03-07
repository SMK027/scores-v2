-- -----------------------------------------------------------
-- Ajout du statut 'paused' aux compétitions
-- -----------------------------------------------------------
ALTER TABLE `competitions`
    MODIFY COLUMN `status` ENUM('planned', 'active', 'paused', 'closed') DEFAULT 'planned';
