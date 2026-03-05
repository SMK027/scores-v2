<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle LoginLock – verrous fail2ban qui bloquent UNIQUEMENT la connexion.
 * Contrairement à IpBan / UserBan, ces verrous n'affectent pas la navigation.
 */
class LoginLock extends Model
{
    protected string $table = 'login_locks';

    /**
     * Cherche un verrou actif pour une IP.
     *
     * @return array|null  Ligne du verrou ou null
     */
    public function findActiveByIp(string $ip): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE ip_address = :ip
              AND locked_until > NOW()
            ORDER BY locked_until DESC
            LIMIT 1
        ");
        $stmt->execute(['ip' => $ip]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cherche un verrou actif pour un compte.
     *
     * @return array|null  Ligne du verrou ou null
     */
    public function findActiveByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE user_id = :uid
              AND locked_until > NOW()
            ORDER BY locked_until DESC
            LIMIT 1
        ");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée un verrou pour une IP.
     */
    public function lockIp(string $ip, string $lockedUntil, string $reason = ''): int
    {
        return $this->create([
            'ip_address'   => $ip,
            'locked_until' => $lockedUntil,
            'reason'       => $reason,
        ]);
    }

    /**
     * Crée un verrou pour un compte.
     */
    public function lockUser(int $userId, string $lockedUntil, string $reason = ''): int
    {
        return $this->create([
            'user_id'      => $userId,
            'locked_until' => $lockedUntil,
            'reason'       => $reason,
        ]);
    }

    /**
     * Supprime les verrous expirés (nettoyage).
     */
    public function cleanExpired(): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE locked_until <= NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
