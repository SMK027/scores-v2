<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle SpaceInvitation - Invitations nominatives aux espaces.
 */
class SpaceInvitation extends Model
{
    protected string $table = 'space_invitations';

    /**
     * Crée une invitation en attente.
     */
    public function invite(int $spaceId, int $invitedUserId, int $invitedBy, string $role = 'member'): int
    {
        return $this->create([
            'space_id'        => $spaceId,
            'invited_user_id' => $invitedUserId,
            'invited_by'      => $invitedBy,
            'role'            => $role,
        ]);
    }

    /**
     * Vérifie si une invitation en attente existe déjà.
     */
    public function hasPendingInvite(int $spaceId, int $userId): bool
    {
        $stmt = $this->query(
            "SELECT 1 FROM {$this->table}
             WHERE space_id = :space_id AND invited_user_id = :user_id AND status = 'pending'
             LIMIT 1",
            ['space_id' => $spaceId, 'user_id' => $userId]
        );
        return (bool) $stmt->fetch();
    }

    /**
     * Récupère les invitations en attente pour un utilisateur.
     */
    public function findPendingForUser(int $userId): array
    {
        $stmt = $this->query(
            "SELECT si.*, s.name AS space_name, u.username AS invited_by_name
             FROM {$this->table} si
             INNER JOIN spaces s ON si.space_id = s.id
             INNER JOIN users u ON si.invited_by = u.id
             WHERE si.invited_user_id = :user_id AND si.status = 'pending'
             ORDER BY si.created_at DESC",
            ['user_id' => $userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère les invitations en attente pour un espace.
     */
    public function findPendingForSpace(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT si.*, u.username AS invited_username, inv.username AS invited_by_name
             FROM {$this->table} si
             INNER JOIN users u ON si.invited_user_id = u.id
             INNER JOIN users inv ON si.invited_by = inv.id
             WHERE si.space_id = :space_id AND si.status = 'pending'
             ORDER BY si.created_at DESC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Accepte une invitation.
     */
    public function accept(int $id): bool
    {
        return $this->update($id, [
            'status'       => 'accepted',
            'responded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Refuse une invitation.
     */
    public function decline(int $id): bool
    {
        return $this->update($id, [
            'status'       => 'declined',
            'responded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Annule une invitation en attente (par l'émetteur).
     */
    public function cancel(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Compte les invitations en attente pour un utilisateur.
     */
    public function countPendingForUser(int $userId): int
    {
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM {$this->table}
             WHERE invited_user_id = :user_id AND status = 'pending'",
            ['user_id' => $userId]
        );
        return (int) $stmt->fetch()['cnt'];
    }
}
