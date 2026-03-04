<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle SpaceMember - Gestion des membres d'un espace.
 */
class SpaceMember extends Model
{
    protected string $table = 'space_members';

    /**
     * Trouve un membre dans un espace.
     */
    public function findMember(int $spaceId, int $userId): ?array
    {
        return $this->findOneBy(['space_id' => $spaceId, 'user_id' => $userId]);
    }

    /**
     * Liste les membres d'un espace avec infos utilisateur.
     */
    public function findBySpace(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT sm.*, u.username, u.email, u.avatar
             FROM {$this->table} sm
             INNER JOIN users u ON sm.user_id = u.id
             WHERE sm.space_id = :space_id
             ORDER BY sm.role ASC, u.username ASC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Ajoute un membre à un espace.
     */
    public function addMember(int $spaceId, int $userId, string $role = 'member'): int
    {
        return $this->create([
            'space_id' => $spaceId,
            'user_id'  => $userId,
            'role'     => $role,
        ]);
    }

    /**
     * Met à jour le rôle d'un membre.
     */
    public function updateRole(int $id, string $role): bool
    {
        $validRoles = ['admin', 'manager', 'member', 'guest'];
        if (!in_array($role, $validRoles, true)) {
            return false;
        }
        return $this->update($id, ['role' => $role]);
    }

    /**
     * Vérifie si un utilisateur est membre d'un espace.
     */
    public function isMember(int $spaceId, int $userId): bool
    {
        return $this->findMember($spaceId, $userId) !== null;
    }
}
