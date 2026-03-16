-- ============================================================
-- Migration 021 : Droit à l'oubli (demande de suppression)
-- ============================================================

ALTER TABLE users ADD COLUMN account_status ENUM('active', 'pending_deletion', 'suspended') NOT NULL DEFAULT 'active' AFTER global_role;
ALTER TABLE users ADD COLUMN deletion_requested_at DATETIME NULL AFTER account_status;
ALTER TABLE users ADD COLUMN deletion_effective_at DATETIME NULL AFTER deletion_requested_at;
ALTER TABLE users ADD COLUMN deletion_contact_email VARCHAR(255) NULL AFTER deletion_effective_at;
ALTER TABLE users ADD COLUMN is_anonymized TINYINT(1) NOT NULL DEFAULT 0 AFTER deletion_contact_email;
ALTER TABLE users ADD COLUMN anonymized_at DATETIME NULL AFTER is_anonymized;

CREATE INDEX idx_users_account_status ON users(account_status);
CREATE INDEX idx_users_deletion_effective_at ON users(deletion_effective_at);
CREATE INDEX idx_users_is_anonymized ON users(is_anonymized);