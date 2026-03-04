<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle IpBan - Bannissement d'adresses IP.
 */
class IpBan extends Model
{
    protected string $table = 'ip_bans';

    /**
     * Recherche un bannissement actif pour une adresse IP.
     */
    public function findActiveBan(string $ip): ?array
    {
        $stmt = $this->query(
            "SELECT ib.*, u_mod.username AS banned_by_username
             FROM {$this->table} ib
             LEFT JOIN users u_mod ON ib.banned_by = u_mod.id
             WHERE ib.ip_address = :ip
               AND ib.is_active = 1
               AND (ib.expires_at IS NULL OR ib.expires_at > NOW())
             ORDER BY ib.created_at DESC
             LIMIT 1",
            ['ip' => $ip]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Crée un bannissement d'IP.
     */
    public function ban(string $ip, ?int $bannedBy, string $reason, ?string $expiresAt): int
    {
        return $this->create([
            'ip_address' => $ip,
            'banned_by'  => $bannedBy,
            'reason'     => $reason,
            'expires_at' => $expiresAt,
            'is_active'  => 1,
        ]);
    }

    /**
     * Annule un bannissement d'IP.
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
     * Liste tous les bannissements d'IP avec pagination.
     */
    public function listAll(int $page = 1, int $perPage = 20, string $filter = 'all'): array
    {
        $where = '';
        if ($filter === 'active') {
            $where = 'WHERE ib.is_active = 1 AND (ib.expires_at IS NULL OR ib.expires_at > NOW())';
        } elseif ($filter === 'expired') {
            $where = 'WHERE ib.is_active = 1 AND ib.expires_at IS NOT NULL AND ib.expires_at <= NOW()';
        } elseif ($filter === 'revoked') {
            $where = 'WHERE ib.is_active = 0';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} ib {$where}");
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT ib.*,
                   u_mod.username AS banned_by_username,
                   u_rev.username AS revoked_by_username
            FROM {$this->table} ib
            LEFT JOIN users u_mod ON ib.banned_by = u_mod.id
            LEFT JOIN users u_rev ON ib.revoked_by = u_rev.id
            {$where}
            ORDER BY ib.created_at DESC
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
     * Désactive automatiquement les bans d'IP expirés.
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
