<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle SpaceInvite - Gestion des invitations d'espace.
 */
class SpaceInvite extends Model
{
    protected string $table = 'space_invites';

    /**
     * Crée une invitation.
     */
    public function createInvite(int $spaceId, int $createdBy, int $hoursValid = 72): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($hoursValid * 3600));

        $this->create([
            'space_id'   => $spaceId,
            'token'      => $token,
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Trouve une invitation valide par token.
     */
    public function findValidByToken(string $token): ?array
    {
        $stmt = $this->query(
            "SELECT si.*, s.name as space_name
             FROM {$this->table} si
             INNER JOIN spaces s ON si.space_id = s.id
             WHERE si.token = :token AND si.expires_at > NOW()",
            ['token' => $token]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
