<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle UserBan - Bannissement de comptes utilisateurs.
 */
class UserBan extends Model
{
    protected string $table = 'user_bans';

    /**
     * Recherche un bannissement actif pour un utilisateur.
     * Exclut les bans expirés.
     */
    public function findActiveBan(int $userId): ?array
    {
        $stmt = $this->query(
            "SELECT ub.*, u_mod.username AS banned_by_username
             FROM {$this->table} ub
             LEFT JOIN users u_mod ON ub.banned_by = u_mod.id
             WHERE ub.user_id = :user_id
               AND ub.is_active = 1
               AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
             ORDER BY ub.created_at DESC
             LIMIT 1",
            ['user_id' => $userId]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Crée un bannissement de compte.
     */
    public function ban(int $userId, ?int $bannedBy, string $reason, ?string $expiresAt): int
    {
        return $this->create([
            'user_id'    => $userId,
            'banned_by'  => $bannedBy,
            'reason'     => $reason,
            'expires_at' => $expiresAt,
            'is_active'  => 1,
        ]);
    }

    /**
     * Annule un bannissement (revoke).
     */
    public function revoke(int $banId, int $revokedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 0, revoked_by = :revoked_by, revoked_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $banId, 'revoked_by' => $revokedBy]);
    }

    /**
     * Liste tous les bannissements (actifs et inactifs), avec pagination.
     */
    public function listAll(int $page = 1, int $perPage = 20, string $filter = 'all'): array
    {
        $where = '';
        if ($filter === 'active') {
            $where = 'WHERE ub.is_active = 1 AND (ub.expires_at IS NULL OR ub.expires_at > NOW())';
        } elseif ($filter === 'expired') {
            $where = 'WHERE ub.is_active = 1 AND ub.expires_at IS NOT NULL AND ub.expires_at <= NOW()';
        } elseif ($filter === 'revoked') {
            $where = 'WHERE ub.is_active = 0';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} ub {$where}");
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT ub.*, 
                   u.username AS user_username, u.email AS user_email,
                   u_mod.username AS banned_by_username,
                   u_rev.username AS revoked_by_username
            FROM {$this->table} ub
            JOIN users u ON ub.user_id = u.id
            LEFT JOIN users u_mod ON ub.banned_by = u_mod.id
            LEFT JOIN users u_rev ON ub.revoked_by = u_rev.id
            {$where}
            ORDER BY ub.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute();

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Désactive automatiquement les bans expirés.
     */
    public function cleanExpired(): int
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 0
            WHERE is_active = 1
              AND expires_at IS NOT NULL
              AND expires_at <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
