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
            "SELECT p.*, u.username as linked_username,
                    (SELECT COUNT(DISTINCT gp.game_id) FROM game_players gp WHERE gp.player_id = p.id) as game_count,
                    (SELECT COUNT(*) FROM game_players gp WHERE gp.player_id = p.id AND gp.is_winner = 1) as win_count
             FROM {$this->table} p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.space_id = :space_id
             ORDER BY p.name ASC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
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
}
