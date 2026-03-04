<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Game - Parties de jeu.
 */
class Game extends Model
{
    protected string $table = 'games';

    /**
     * Récupère les parties récentes d'un espace.
     */
    public function getRecentBySpace(int $spaceId, int $limit = 5): array
    {
        $stmt = $this->query(
            "SELECT g.*, gt.name as game_type_name, gt.win_condition,
                    u.username as creator_name,
                    (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) as player_count
             FROM {$this->table} g
             INNER JOIN game_types gt ON g.game_type_id = gt.id
             LEFT JOIN users u ON g.created_by = u.id
             WHERE g.space_id = :space_id
             ORDER BY g.created_at DESC
             LIMIT {$limit}",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Liste les parties d'un espace avec pagination.
     */
    public function findBySpace(int $spaceId, int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = ['space_id' => $spaceId];
        $where = "g.space_id = :space_id";

        if (!empty($filters['status'])) {
            $where .= " AND g.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['game_type_id'])) {
            $where .= " AND g.game_type_id = :game_type_id";
            $params['game_type_id'] = $filters['game_type_id'];
        }

        // Count
        $countStmt = $this->query("SELECT COUNT(*) FROM {$this->table} g WHERE {$where}", $params);
        $total = (int) $countStmt->fetchColumn();

        // Data
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        $stmt = $this->query(
            "SELECT g.*, gt.name as game_type_name, gt.win_condition,
                    u.username as creator_name,
                    (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) as player_count
             FROM {$this->table} g
             INNER JOIN game_types gt ON g.game_type_id = gt.id
             LEFT JOIN users u ON g.created_by = u.id
             WHERE {$where}
             ORDER BY g.created_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Récupère une partie avec tous les détails.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT g.*, gt.name as game_type_name, gt.win_condition, gt.description as game_type_description,
                    u.username as creator_name
             FROM {$this->table} g
             INNER JOIN game_types gt ON g.game_type_id = gt.id
             LEFT JOIN users u ON g.created_by = u.id
             WHERE g.id = :id",
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Recalcule les totaux et détermine le gagnant.
     */
    public function recalculateTotals(int $gameId): void
    {
        $game = $this->findWithDetails($gameId);
        if (!$game) return;

        // Recalculer les totaux des joueurs
        $this->query(
            "UPDATE game_players gp
             SET total_score = (
                 SELECT COALESCE(SUM(rs.score), 0)
                 FROM round_scores rs
                 INNER JOIN rounds r ON rs.round_id = r.id
                 WHERE r.game_id = :game_id AND rs.player_id = gp.player_id
             )
             WHERE gp.game_id = :game_id2",
            ['game_id' => $gameId, 'game_id2' => $gameId]
        );

        // Réinitialiser les gagnants
        $this->query("UPDATE game_players SET is_winner = 0, `rank` = NULL WHERE game_id = :game_id", ['game_id' => $gameId]);

        // Déterminer le classement selon la condition de victoire
        $winCondition = $game['win_condition'];
        $order = ($winCondition === 'lowest_score') ? 'ASC' : 'DESC';

        $playersStmt = $this->query(
            "SELECT id, player_id, total_score FROM game_players WHERE game_id = :game_id ORDER BY total_score {$order}",
            ['game_id' => $gameId]
        );
        $players = $playersStmt->fetchAll();

        foreach ($players as $rank => $player) {
            $isWinner = ($rank === 0) ? 1 : 0;
            $this->query(
                "UPDATE game_players SET `rank` = :rank, is_winner = :is_winner WHERE id = :id",
                ['rank' => $rank + 1, 'is_winner' => $isWinner, 'id' => $player['id']]
            );
        }
    }
}
