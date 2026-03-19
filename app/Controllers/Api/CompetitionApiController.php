<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\Database;
use App\Models\Competition;

/**
 * API REST mobile pour les competitions d'un espace.
 */
class CompetitionApiController extends ApiController
{
    /**
     * GET /api/spaces/{id}/competitions
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $competitionModel = new Competition();
        $rows = $competitionModel->findBySpace((int) $id);

        $competitions = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'space_id' => (int) $row['space_id'],
                'name' => (string) $row['name'],
                'description' => $row['description'] ?? null,
                'status' => (string) $row['status'],
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
                'creator_name' => $row['creator_name'] ?? null,
                'session_count' => isset($row['session_count']) ? (int) $row['session_count'] : 0,
            ];
        }, $rows);

        $this->json([
            'success' => true,
            'competitions' => $competitions,
            'total' => count($competitions),
        ]);
    }

    /**
     * GET /api/spaces/{id}/competitions/{cid}
     */
    public function show(string $id, string $cid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $competitionModel = new Competition();
        $competition = $competitionModel->findWithDetails((int) $cid);

        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->error('Compétition introuvable.', 404);
        }

        $registeredPlayers = $competitionModel->getRegisteredPlayers((int) $cid);
        $pdo = Database::getInstance()->getConnection();

        $roundStmt = $pdo->prepare(
            "SELECT r.id AS round_id, gt.win_condition
             FROM rounds r
             INNER JOIN games g ON g.id = r.game_id
             INNER JOIN game_types gt ON gt.id = g.game_type_id
             WHERE g.competition_id = :cid
               AND r.status = 'completed'"
        );
        $roundStmt->execute(['cid' => (int) $cid]);
        $rounds = $roundStmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoresByRound = [];
        if (!empty($rounds)) {
            $roundIds = array_map(static fn(array $row): int => (int) $row['round_id'], $rounds);
            $placeholders = implode(',', array_fill(0, count($roundIds), '?'));
            $scoreStmt = $pdo->prepare(
                "SELECT round_id, player_id, score
                 FROM round_scores
                 WHERE round_id IN ({$placeholders})"
            );
            $scoreStmt->execute($roundIds);
            $allScores = $scoreStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($allScores as $scoreRow) {
                $scoresByRound[(int) $scoreRow['round_id']][] = $scoreRow;
            }
        }

        $playedByPlayer = [];
        $wonByPlayer = [];
        foreach ($rounds as $round) {
            $roundId = (int) $round['round_id'];
            $winCondition = (string) $round['win_condition'];
            $scores = $scoresByRound[$roundId] ?? [];
            if (empty($scores)) {
                continue;
            }

            $values = array_map(static fn(array $s): float => (float) $s['score'], $scores);
            $best = ($winCondition === 'ranking' || $winCondition === 'lowest_score')
                ? min($values)
                : max($values);

            foreach ($scores as $entry) {
                $playerId = (int) $entry['player_id'];
                $playedByPlayer[$playerId] = ($playedByPlayer[$playerId] ?? 0) + 1;
                if ((float) $entry['score'] === (float) $best) {
                    $wonByPlayer[$playerId] = ($wonByPlayer[$playerId] ?? 0) + 1;
                }
            }
        }

        $participants = array_map(static function (array $player) use ($playedByPlayer, $wonByPlayer): array {
            $playerId = (int) $player['id'];
            $roundsPlayed = (int) ($playedByPlayer[$playerId] ?? 0);
            $roundsWon = (int) ($wonByPlayer[$playerId] ?? 0);

            return [
                'player_id' => $playerId,
                'name' => (string) $player['name'],
                'linked_username' => $player['linked_username'] ?? null,
                'rounds_played' => $roundsPlayed,
                'rounds_won' => $roundsWon,
                'win_rate' => $roundsPlayed > 0 ? round(($roundsWon * 100) / $roundsPlayed, 1) : 0.0,
            ];
        }, $registeredPlayers);

        usort($participants, static function (array $a, array $b): int {
            if ($b['rounds_won'] !== $a['rounds_won']) {
                return $b['rounds_won'] <=> $a['rounds_won'];
            }
            if ($b['win_rate'] !== $a['win_rate']) {
                return $b['win_rate'] <=> $a['win_rate'];
            }
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        $gameStatsStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_games,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_games
             FROM games
             WHERE competition_id = :cid"
        );
        $gameStatsStmt->execute(['cid' => (int) $cid]);
        $gameStats = $gameStatsStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $roundStatsStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_rounds,
                COALESCE(SUM(
                    GREATEST(
                        0,
                        TIMESTAMPDIFF(SECOND, r.started_at, COALESCE(r.ended_at, NOW())) - COALESCE(rp.pause_seconds, 0)
                    )
                ), 0) AS total_play_seconds
             FROM rounds r
             INNER JOIN games g ON g.id = r.game_id
             LEFT JOIN (
                SELECT
                    round_id,
                    COALESCE(SUM(
                        CASE
                            WHEN duration_seconds IS NOT NULL THEN duration_seconds
                            WHEN resumed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, paused_at, resumed_at)
                            ELSE TIMESTAMPDIFF(SECOND, paused_at, NOW())
                        END
                    ), 0) AS pause_seconds
                FROM round_pauses
                GROUP BY round_id
             ) rp ON rp.round_id = r.id
             WHERE g.competition_id = :cid
               AND r.status = 'completed'"
        );
        $roundStatsStmt->execute(['cid' => (int) $cid]);
        $roundStats = $roundStatsStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $participantCount = count($participants);
        $totalRoundsPlayedByParticipants = 0;
        $totalWinRate = 0.0;
        foreach ($participants as $participant) {
            $totalRoundsPlayedByParticipants += (int) $participant['rounds_played'];
            $totalWinRate += (float) $participant['win_rate'];
        }

        $completedGames = (int) ($gameStats['completed_games'] ?? 0);
        $totalPlaySeconds = (int) ($roundStats['total_play_seconds'] ?? 0);

        $stats = [
            'total_games' => (int) ($gameStats['total_games'] ?? 0),
            'completed_games' => $completedGames,
            'total_rounds' => (int) ($roundStats['total_rounds'] ?? 0),
            'total_play_seconds' => $totalPlaySeconds,
            'avg_play_seconds_per_game' => $completedGames > 0 ? (int) round($totalPlaySeconds / $completedGames) : 0,
            'avg_rounds_per_competitor' => $participantCount > 0 ? round($totalRoundsPlayedByParticipants / $participantCount, 2) : 0.0,
            'avg_win_rate' => $participantCount > 0 ? round($totalWinRate / $participantCount, 1) : 0.0,
        ];

        $this->json([
            'success' => true,
            'competition' => [
                'id' => (int) $competition['id'],
                'space_id' => (int) $competition['space_id'],
                'name' => (string) $competition['name'],
                'description' => $competition['description'] ?? null,
                'status' => (string) $competition['status'],
                'starts_at' => $competition['starts_at'] ?? null,
                'ends_at' => $competition['ends_at'] ?? null,
                'created_by' => isset($competition['created_by']) ? (int) $competition['created_by'] : null,
                'creator_name' => $competition['creator_name'] ?? null,
                'session_count' => isset($competition['session_count']) ? (int) $competition['session_count'] : 0,
            ],
            'participants' => $participants,
            'stats' => $stats,
        ]);
    }
}
