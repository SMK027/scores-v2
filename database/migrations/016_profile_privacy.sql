-- ============================================================
-- Migration 016 : Consentement affichage taux de victoire public
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `show_win_rate_public` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = afficher les stats de victoire sur le profil public, 0 = masquer'
        AFTER `email_verification_required`;
