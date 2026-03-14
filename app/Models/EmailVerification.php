<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle EmailVerification - Tokens de vérification d'adresse email.
 * Génère et valide des codes à 6 chiffres à usage unique (validité : 15 min).
 */
class EmailVerification extends Model
{
    protected string $table = 'email_verification_tokens';

    /**
     * Génère un nouveau code de vérification pour un utilisateur.
     * Invalide tous les codes précédents non utilisés.
     *
     * @return string Le code à 6 chiffres généré
     */
    public function generateCode(int $userId, string $email): string
    {
        // Invalider les anciens codes non utilisés
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET used = 1 WHERE user_id = :uid AND used = 0"
        );
        $stmt->execute(['uid' => $userId]);

        // Code à 6 chiffres aléatoire (cryptographiquement sûr)
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + 15 * 60); // 15 minutes

        $this->create([
            'user_id'    => $userId,
            'email'      => $email,
            'code'       => $code,
            'expires_at' => $expiresAt,
            'used'       => 0,
        ]);

        return $code;
    }

    /**
     * Recherche un code valide (non expiré, non utilisé) pour un utilisateur.
     */
    public function findValidCode(int $userId, string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE user_id = :uid
              AND code = :code
              AND used = 0
              AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId, 'code' => $code]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Marque un token comme utilisé.
     */
    public function markUsed(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET used = 1 WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Compte le nombre de codes générés pour un utilisateur au cours des dernières N heures.
     * Inclut tous les codes (utilisés ou non) afin de mesurer le volume d'envois réels.
     */
    public function countRecentCodes(int $userId, int $hours = 24): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = :uid
               AND created_at >= NOW() - INTERVAL :hours HOUR"
        );
        $stmt->execute(['uid' => $userId, 'hours' => $hours]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Supprime les tokens expirés ou déjà utilisés (nettoyage).
     */
    public function purgeExpired(): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE expires_at < NOW() OR used = 1"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
