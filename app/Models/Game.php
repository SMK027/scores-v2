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
        if (!empty($filters['period'])) {
            $dateFrom = match($filters['period']) {
                'week'  => date('Y-m-d', strtotime('-7 days')),
                'month' => date('Y-m-d', strtotime('-30 days')),
                'year'  => date('Y-m-d', strtotime('-365 days')),
                default => null,
            };
            if ($dateFrom !== null) {
                $where .= " AND g.created_at >= :date_from";
                $params['date_from'] = $dateFrom;
            }
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

        $winCondition = $game['win_condition'];

        if ($winCondition === 'win_loss') {
            // Pour win_loss : compter le nombre de victoires (score = 1)
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
        } elseif ($winCondition === 'ranking') {
            // Pour ranking : sommer les positions (plus bas = meilleur)
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
        } else {
            // Pour highest_score et lowest_score : sommer les scores
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
        }

        // Réinitialiser les gagnants
        $this->query("UPDATE game_players SET is_winner = 0, `rank` = NULL WHERE game_id = :game_id", ['game_id' => $gameId]);

        // Déterminer le classement selon la condition de victoire
        if ($winCondition === 'ranking' || $winCondition === 'lowest_score') {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }

        $playersStmt = $this->query(
            "SELECT id, player_id, total_score FROM game_players WHERE game_id = :game_id ORDER BY total_score {$order}",
            ['game_id' => $gameId]
        );
        $players = $playersStmt->fetchAll();

        // Classement avec gestion des égalités (dense rank)
        $currentRank = 0;
        $lastScore = null;

        foreach ($players as $index => $player) {
            $score = (float) $player['total_score'];

            // Nouveau rang seulement si le score diffère du précédent
            if ($lastScore === null || $score !== $lastScore) {
                $currentRank = $index + 1;
            }
            $lastScore = $score;

            $isWinner = ($currentRank === 1) ? 1 : 0;
            $this->query(
                "UPDATE game_players SET `rank` = :rank, is_winner = :is_winner WHERE id = :id",
                ['rank' => $currentRank, 'is_winner' => $isWinner, 'id' => $player['id']]
            );
        }
    }

    /**
     * Calcule la durée totale de jeu d'une partie en secondes.
     * Durée = somme des durées brutes de chaque manche - somme des temps de pause.
     */
    public function calculateTotalPlayDuration(int $gameId): int
    {
        $roundModel = new Round();
        $roundPauseModel = new RoundPause();

        $rounds = $roundModel->findByGame($gameId);
        if (empty($rounds)) {
            return 0;
        }

        $roundIds = array_column($rounds, 'id');
        $pausesByRound = $roundPauseModel->getTotalPauseSecondsByRounds($roundIds);

        $totalPlay = 0;
        foreach ($rounds as $round) {
            $pauseSeconds = $pausesByRound[(int) $round['id']] ?? 0;
            $totalPlay += $roundModel->getPlayDurationSeconds($round, $pauseSeconds);
        }

        return $totalPlay;
    }

    /**
     * Compte le nombre de parties en cours (non complétées) pour un espace.
     */
    public function countInProgressBySpace(int $spaceId): int
    {
        $stmt = $this->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE space_id = :space_id AND status IN ('pending', 'in_progress', 'paused')",
            ['space_id' => $spaceId]
        );
        return (int) $stmt->fetchColumn();
    }
}
