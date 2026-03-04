<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Space - Gestion des espaces de jeu.
 */
class Space extends Model
{
    protected string $table = 'spaces';

    /**
     * Retourne les espaces dont l'utilisateur est membre.
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->query(
            "SELECT s.*, sm.role as user_role, u.username as creator_name,
                    (SELECT COUNT(*) FROM space_members WHERE space_id = s.id) as member_count
             FROM {$this->table} s
             INNER JOIN space_members sm ON s.id = sm.space_id AND sm.user_id = :user_id
             LEFT JOIN users u ON s.created_by = u.id
             ORDER BY s.updated_at DESC",
            ['user_id' => $userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Retourne un espace avec les infos supplémentaires.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT s.*, u.username as creator_name,
                    (SELECT COUNT(*) FROM space_members WHERE space_id = s.id) as member_count,
                    (SELECT COUNT(*) FROM games WHERE space_id = s.id) as game_count,
                    (SELECT COUNT(*) FROM players WHERE space_id = s.id) as player_count,
                    (SELECT COUNT(*) FROM game_types WHERE space_id = s.id) as game_type_count
             FROM {$this->table} s
             LEFT JOIN users u ON s.created_by = u.id
             WHERE s.id = :id",
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
