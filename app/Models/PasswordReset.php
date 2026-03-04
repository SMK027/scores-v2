<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle PasswordReset - Tokens de réinitialisation de mot de passe.
 */
class PasswordReset extends Model
{
    protected string $table = 'password_resets';

    /**
     * Crée un token de réinitialisation pour un utilisateur.
     * Invalide les tokens précédents non utilisés.
     *
     * @return string Le token généré
     */
    public function createToken(int $userId): string
    {
        // Invalider les anciens tokens
        $stmt = $this->db->prepare("UPDATE {$this->table} SET used = 1 WHERE user_id = :uid AND used = 0");
        $stmt->execute(['uid' => $userId]);

        // Générer un nouveau token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 30 * 60); // 30 minutes

        $this->create([
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'used'       => 0,
        ]);

        return $token;
    }

    /**
     * Trouve un token valide (non expiré et non utilisé).
     */
    public function findValidToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, u.email, u.username
            FROM {$this->table} pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token = :token
              AND pr.used = 0
              AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Marque un token comme utilisé.
     */
    public function markUsed(string $token): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET used = 1 WHERE token = :token");
        return $stmt->execute(['token' => $token]);
    }

    /**
     * Supprime les tokens expirés (nettoyage).
     */
    public function purgeExpired(): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE expires_at < NOW() OR used = 1");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
