<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle RememberToken - Jetons persistants "Se souvenir de moi".
 */
class RememberToken extends Model
{
    protected string $table = 'remember_tokens';

    /**
     * Crée un token persistant.
     */
    public function createToken(
        int $userId,
        string $selector,
        string $tokenHash,
        string $expiresAt,
        ?string $createdIp,
        ?string $userAgent
    ): int {
        return $this->create([
            'user_id'     => $userId,
            'selector'    => $selector,
            'token_hash'  => $tokenHash,
            'expires_at'  => $expiresAt,
            'created_ip'  => $createdIp,
            'user_agent'  => $userAgent,
            'last_used_at' => null,
        ]);
    }

    /**
     * Trouve un token par selector.
     */
    public function findBySelector(string $selector): ?array
    {
        return $this->findOneBy(['selector' => $selector]);
    }

    /**
     * Supprime un token par selector.
     */
    public function deleteBySelector(string $selector): bool
    {
        $stmt = $this->query(
            "DELETE FROM {$this->table} WHERE selector = :selector",
            ['selector' => $selector]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime tous les tokens d'un utilisateur.
     */
    public function deleteByUser(int $userId): int
    {
        $stmt = $this->query(
            "DELETE FROM {$this->table} WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        return $stmt->rowCount();
    }

    /**
     * Purge les tokens expirés.
     */
    public function purgeExpired(): int
    {
        $stmt = $this->query(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
        return $stmt->rowCount();
    }

    /**
     * Met à jour la date de dernière utilisation.
     */
    public function touch(int $id): bool
    {
        return $this->update($id, ['last_used_at' => date('Y-m-d H:i:s')]);
    }
}
