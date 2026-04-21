<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Notification — notifications in-app pour les utilisateurs.
 */
class Notification extends Model
{
    protected string $table = 'notifications';

    // Types de notifications supportés
    public const TYPE_LOBBY_JOIN            = 'lobby.join';
    public const TYPE_LOBBY_INVITE          = 'lobby.invite';
    public const TYPE_LOBBY_INVITE_ACCEPTED = 'lobby.invite_accepted';
    public const TYPE_LOBBY_LAUNCH          = 'lobby.launch';
    public const TYPE_SPACE_INVITE          = 'space.invite';
    public const TYPE_SPACE_INVITE_ACCEPTED = 'space.invite_accepted';
    public const TYPE_SPACE_INVITE_DECLINED = 'space.invite_declined';

    /**
     * Crée une notification pour un utilisateur.
     */
    public function createForUser(
        int     $userId,
        string  $type,
        string  $title,
        string  $message,
        ?string $url = null
    ): int {
        return $this->create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'url'     => $url,
        ]);
    }

    /**
     * Retourne les N dernières notifications d'un utilisateur.
     */
    public function getRecentForUser(int $userId, int $limit = 15): array
    {
        return $this->query(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT " . (int) $limit,
            ['user_id' => $userId]
        )->fetchAll();
    }

    /**
     * Compte les notifications non lues d'un utilisateur.
     */
    public function countUnread(int $userId): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        )->fetchColumn();
    }

    /**
     * Marque une notification comme lue (appartenant à l'utilisateur).
     */
    public function markAsRead(int $id, int $userId): void
    {
        $this->query(
            "UPDATE {$this->table} SET is_read = 1
             WHERE id = :id AND user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     */
    public function markAllAsRead(int $userId): void
    {
        $this->query(
            "UPDATE {$this->table} SET is_read = 1
             WHERE user_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        );
    }

    /**
     * Retourne les notifications créées après un ID donné (pour détecter les nouveautés).
     */
    public function getNewSince(int $userId, int $sinceId): array
    {
        return $this->query(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id AND id > :since_id
             ORDER BY created_at DESC",
            ['user_id' => $userId, 'since_id' => $sinceId]
        )->fetchAll();
    }
}
