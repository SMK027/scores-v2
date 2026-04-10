<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Player - Joueurs d'un espace.
 */
class Player extends Model
{
    protected string $table = 'players';

    /**
     * Liste les joueurs d'un espace.
     */
    public function findBySpace(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT p.*, u.username as linked_username
             FROM {$this->table} p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.space_id = :space_id
               AND p.deleted_at IS NULL
             ORDER BY p.name ASC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Vérifie si un user_id est déjà lié à un joueur dans cet espace.
     * Exclut éventuellement un joueur (utile pour l'édition).
     */
    public function isUserLinkedInSpace(int $spaceId, int $userId, ?int $excludePlayerId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE space_id = :space_id AND user_id = :user_id AND deleted_at IS NULL";
        $params = ['space_id' => $spaceId, 'user_id' => $userId];

        if ($excludePlayerId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludePlayerId;
        }

        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Retourne les user_id déjà liés à des joueurs dans un espace.
     * Exclut éventuellement un joueur.
     */
    public function getLinkedUserIds(int $spaceId, ?int $excludePlayerId = null): array
    {
        $sql = "SELECT user_id FROM {$this->table} WHERE space_id = :space_id AND user_id IS NOT NULL AND deleted_at IS NULL";
        $params = ['space_id' => $spaceId];

        if ($excludePlayerId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludePlayerId;
        }

        $stmt = $this->query($sql, $params);
        return array_column($stmt->fetchAll(), 'user_id');
    }

    /**
     * Retourne un joueur avec ses statistiques.
     */
    public function findWithStats(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT p.*, u.username as linked_username,
                    (SELECT COUNT(DISTINCT gp.game_id) FROM game_players gp WHERE gp.player_id = p.id) as game_count,
                    (SELECT COUNT(*) FROM game_players gp WHERE gp.player_id = p.id AND gp.is_winner = 1) as win_count,
                    (SELECT AVG(gp.total_score) FROM game_players gp WHERE gp.player_id = p.id) as avg_score
             FROM {$this->table} p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = :id",
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Vérifie qu'un joueur actif appartient à un espace.
     */
    public function isActiveInSpace(int $playerId, int $spaceId): bool
    {
        $stmt = $this->query(
            "SELECT 1
             FROM {$this->table}
             WHERE id = :id
               AND space_id = :space_id
               AND deleted_at IS NULL
             LIMIT 1",
            [
                'id' => $playerId,
                'space_id' => $spaceId,
            ]
        );

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Vérifie qu'un joueur actif existe dans un espace.
     */
    public function findActiveByIdInSpace(int $playerId, int $spaceId): ?array
    {
        $stmt = $this->query(
            "SELECT *
             FROM {$this->table}
             WHERE id = :id
               AND space_id = :space_id
               AND deleted_at IS NULL
             LIMIT 1",
            [
                'id' => $playerId,
                'space_id' => $spaceId,
            ]
        );

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retourne le joueur actif lié à un utilisateur dans un espace.
     */
    public function findByUserInSpace(int $spaceId, int $userId): ?array
    {
        $stmt = $this->query(
            "SELECT *
             FROM {$this->table}
             WHERE space_id = :space_id
               AND user_id = :user_id
               AND deleted_at IS NULL
             LIMIT 1",
            ['space_id' => $spaceId, 'user_id' => $userId]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Soft delete d'un joueur.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET deleted_at = NOW(), user_id = NULL
             WHERE id = :id
               AND deleted_at IS NULL",
            ['id' => $id]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Récupère un joueur supprimé (soft delete).
     */
    public function findDeletedById(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT *
             FROM {$this->table}
             WHERE id = :id
               AND deleted_at IS NOT NULL
             LIMIT 1",
            ['id' => $id]
        );

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Restaure un joueur supprimé.
     */
    public function restore(int $id): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET deleted_at = NULL
             WHERE id = :id
               AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        return $stmt->rowCount() > 0;
    }
}
