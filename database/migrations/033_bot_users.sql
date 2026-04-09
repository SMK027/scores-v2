-- Migration 033: Ajout du support des robots (bots) pour les jeux interactifs

-- Indicateur bot sur la table users
ALTER TABLE users ADD COLUMN is_bot TINYINT(1) NOT NULL DEFAULT 0 AFTER global_role;

-- Créer l'utilisateur robot (mot de passe invalide → connexion impossible)
INSERT IGNORE INTO users (username, email, password_hash, global_role, is_bot)
VALUES ('🤖 Robot', 'bot@system.internal', 'BOT_NO_LOGIN', 'user', 1);
