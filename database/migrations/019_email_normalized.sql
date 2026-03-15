-- =============================================================
-- Migration 019 : Canonicalisation email pour éviter les doublons alias
-- =============================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `email_normalized` VARCHAR(255) NULL AFTER `email`;

-- Valeur par défaut pour tous les comptes
UPDATE `users`
SET `email_normalized` = LOWER(`email`)
WHERE `email_normalized` IS NULL;

-- Canonicalisation Gmail / Googlemail :
-- 1) suppression du +alias
-- 2) suppression des points dans la partie locale
-- 3) unification du domaine vers gmail.com
UPDATE `users`
SET `email_normalized` = CONCAT(
    REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER(`email`), '@', 1), '+', 1), '.', ''),
    '@gmail.com'
)
WHERE LOWER(SUBSTRING_INDEX(`email`, '@', -1)) IN ('gmail.com', 'googlemail.com');

-- Suppression automatique des doublons canonisés:
-- on conserve l'utilisateur le plus ancien (id minimal) pour chaque email_normalized.
DELETE u1
FROM `users` u1
INNER JOIN `users` u2
    ON u1.email_normalized = u2.email_normalized
   AND u1.id > u2.id
WHERE u1.email_normalized IS NOT NULL;

ALTER TABLE `users`
    ADD INDEX `idx_users_email_normalized` (`email_normalized`);

ALTER TABLE `users`
    ADD UNIQUE INDEX `uniq_users_email_normalized` (`email_normalized`);
