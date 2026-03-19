<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\Database;
use App\Models\LeaderboardConfig;

/**
 * API REST mobile du leaderboard global.
 */
class LeaderboardApiController extends ApiController
{
    /**
     * GET /api/leaderboard?period=all|7d|30d|3m|6m|1y|custom&from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function index(): void
    {
        $this->requireAuth();

        $pdo = Database::getInstance()->getConnection();
        $cfgModel = new LeaderboardConfig();
        $config = $cfgModel->getConfig();

        $minRounds = max(1, (int) ($config['min_rounds_played'] ?? 5));
        $minSpaces = max(1, (int) ($config['min_spaces_played'] ?? 2));

        $validPeriods = ['7d', '30d', '3m', '6m', '1y', 'custom', 'all'];
        $period = $_GET['period'] ?? 'all';
        if (!in_array($period, $validPeriods, true)) {
            $period = 'all';
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $dateFrom = null;
        $dateTo = null;
        $customFrom = '';
        $customTo = '';

        if ($period === 'custom') {
            $rawFrom = $_GET['from'] ?? '';
            $rawTo = $_GET['to'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom)) {
                $dateFrom = $rawFrom . ' 00:00:00';
                $customFrom = $rawFrom;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)) {
                $dateTo = $rawTo . ' 23:59:59';
                $customTo = $rawTo;
            }
        } elseif ($period !== 'all') {
            $dateFrom = match ($period) {
                '7d' => $now->modify('-7 days')->format('Y-m-d H:i:s'),
                '30d' => $now->modify('-30 days')->format('Y-m-d H:i:s'),
                '3m' => $now->modify('-3 months')->format('Y-m-d H:i:s'),
                '6m' => $now->modify('-6 months')->format('Y-m-d H:i:s'),
                '1y' => $now->modify('-1 year')->format('Y-m-d H:i:s'),
                default => null,
            };
            $dateTo = $now->format('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare(
            "SELECT DISTINCT u.id, u.username, u.avatar
             FROM users u
             WHERE u.account_status = 'active'
               AND u.is_anonymized = 0
               AND (
                 EXISTS (SELECT 1 FROM space_members sm WHERE sm.user_id = u.id)
                 OR EXISTS (SELECT 1 FROM spaces s WHERE s.created_by = u.id)
               )"
        );
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($users as $user) {
            $stats = $this->computeGlobalWinRateForUser(
                (int) $user['id'],
                $pdo,
                $minRounds,
                $minSpaces,
                $dateFrom,
                $dateTo
            );

            if ($stats === null) {
                continue;
            }

            $rows[] = [
                'user_id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'avatar' => $user['avatar'] ?? null,
                'rounds_played' => $stats['rounds_played'],
                'rounds_won' => $stats['rounds_won'],
                'win_rate' => $stats['win_rate'],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($b['win_rate'] !== $a['win_rate']) {
                return $b['win_rate'] <=> $a['win_rate'];
            }
            if ($b['rounds_won'] !== $a['rounds_won']) {
                return $b['rounds_won'] <=> $a['rounds_won'];
            }
            if ($b['rounds_played'] !== $a['rounds_played']) {
                return $b['rounds_played'] <=> $a['rounds_played'];
            }
            return strcmp((string) $a['username'], (string) $b['username']);
        });

        $rank = 0;
        $lastKey = null;
        foreach ($rows as $index => &$row) {
            $key = $row['win_rate'] . ':' . $row['rounds_won'] . ':' . $row['rounds_played'];
            if ($key !== $lastKey) {
                $rank = $index + 1;
                $lastKey = $key;
            }
            $row['rank'] = $rank;
        }
        unset($row);

        $this->json([
            'success' => true,
            'period' => $period,
            'custom_from' => $customFrom,
            'custom_to' => $customTo,
            'criteria' => [
                'min_rounds_played' => $minRounds,
                'min_spaces_played' => $minSpaces,
            ],
            'leaderboard' => $rows,
            'total' => count($rows),
        ]);
    }

    private function computeGlobalWinRateForUser(
        int $userId,
        \PDO $pdo,
        int $minRounds,
        int $minSpaces,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): ?array {
        $stmt = $pdo->prepare(
            "SELECT p.id AS player_id, p.space_id
             FROM players p
             JOIN spaces s ON s.id = p.space_id
             WHERE p.user_id = :player_user_id
               AND (
                 EXISTS (
                    SELECT 1
                    FROM space_members sm
                    WHERE sm.space_id = p.space_id
                      AND sm.user_id = :member_user_id
                 )
                 OR s.created_by = :owner_user_id
               )"
        );
        $stmt->execute([
            'player_user_id' => $userId,
            'member_user_id' => $userId,
            'owner_user_id' => $userId,
        ]);
        $playerRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($playerRows)) {
            return null;
        }

        $playerIdToSpace = [];
        foreach ($playerRows as $row) {
            $playerIdToSpace[(int) $row['player_id']] = (int) $row['space_id'];
        }
        $playerIds = array_keys($playerIdToSpace);

        $ph = implode(',', array_fill(0, count($playerIds), '?'));
        $dateConditions = '';
        $dateParams = [];
        if ($dateFrom !== null) {
            $dateConditions .= ' AND r.ended_at >= ?';
            $dateParams[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $dateConditions .= ' AND r.ended_at <= ?';
            $dateParams[] = $dateTo;
        }

        $stmt = $pdo->prepare(
            "SELECT DISTINCT r.id AS round_id, gt.win_condition
             FROM round_scores rs
             JOIN rounds r ON r.id = rs.round_id AND r.status = 'completed'
             JOIN games g ON g.id = r.game_id
             JOIN game_types gt ON gt.id = g.game_type_id
             WHERE rs.player_id IN ($ph)
             $dateConditions"
        );
        $stmt->execute([...$playerIds, ...$dateParams]);
        $rounds = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rounds)) {
            return null;
        }

        $roundIds = array_column($rounds, 'round_id');
        $rph = implode(',', array_fill(0, count($roundIds), '?'));

        $stmt = $pdo->prepare(
            "SELECT round_id, player_id, score
             FROM round_scores
             WHERE round_id IN ($rph)"
        );
        $stmt->execute($roundIds);
        $allScores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoresByRound = [];
        foreach ($allScores as $scoreRow) {
            $scoresByRound[(int) $scoreRow['round_id']][] = $scoreRow;
        }

        $totalPlayed = 0;
        $totalWon = 0;
        $playedSpaces = [];

        foreach ($rounds as $round) {
            $roundId = (int) $round['round_id'];
            $winCondition = (string) $round['win_condition'];
            $scores = $scoresByRound[$roundId] ?? [];
            if (empty($scores)) {
                continue;
            }

            $values = array_map(static fn(array $s): float => (float) $s['score'], $scores);
            $best = ($winCondition === 'lowest_score' || $winCondition === 'ranking') ? min($values) : max($values);

            foreach ($scores as $entry) {
                $playerId = (int) $entry['player_id'];
                if (!isset($playerIdToSpace[$playerId])) {
                    continue;
                }

                $spaceId = $playerIdToSpace[$playerId];
                $totalPlayed++;
                $playedSpaces[$spaceId] = true;

                if ((float) $entry['score'] === $best) {
                    $totalWon++;
                }
            }
        }

        if ($totalPlayed === 0) {
            return null;
        }

        if ($totalPlayed < $minRounds || count($playedSpaces) < $minSpaces) {
            return null;
        }

        return [
            'rounds_played' => $totalPlayed,
            'rounds_won' => $totalWon,
            'win_rate' => round($totalWon * 100.0 / $totalPlayed, 2),
        ];
    }
}
