<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle LoginAttempt – enregistre les tentatives de connexion échouées.
 */
class LoginAttempt extends Model
{
    protected string $table = 'login_attempts';

    /**
     * Enregistre une tentative échouée.
     */
    public function record(string $ip, ?string $email = null, ?int $userId = null): int
    {
        return $this->create([
            'ip_address' => $ip,
            'email'      => $email,
            'user_id'    => $userId,
        ]);
    }

    /**
     * Compte les tentatives récentes pour une IP dans la fenêtre donnée.
     */
    public function countRecentByIp(string $ip, int $windowMinutes): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE ip_address = :ip
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':minutes', $windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Compte les tentatives récentes pour un compte dans la fenêtre donnée.
     */
    public function countRecentByUser(int $userId, int $windowMinutes): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE user_id = :uid
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':minutes', $windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Supprime les tentatives plus anciennes que X minutes (nettoyage).
     */
    public function cleanOld(int $olderThanMinutes = 1440): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->bindValue(':minutes', $olderThanMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Efface les tentatives pour une IP (après ban appliqué).
     */
    public function clearByIp(string $ip): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE ip_address = :ip");
        return $stmt->execute(['ip' => $ip]);
    }

    /**
     * Efface les tentatives pour un utilisateur (après ban appliqué).
     */
    public function clearByUser(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = :uid");
        return $stmt->execute(['uid' => $userId]);
    }
}
