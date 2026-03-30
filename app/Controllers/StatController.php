<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Space;
use App\Config\Database;

/**
 * Contrôleur des statistiques d'un espace.
 */
class StatController extends Controller
{
    private Space $spaceModel;
    private \PDO $pdo;

    public function __construct()
    {
        $this->spaceModel = new Space();
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Vérifie l'accès à l'espace.
     */
    private function checkAccess(string $id): array
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            exit;
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
            exit;
        }
        return ['space' => $space, 'member' => $member];
    }

    /**
     * Affiche le tableau de bord statistiques de l'espace.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $spaceId = (int) $id;

        // Stats globales de l'espace
        $overview = $this->getOverview($spaceId);

        // Top joueurs (par nombre de victoires)
        $topPlayers = $this->getTopPlayers($spaceId, 10);

        // Dernières parties terminées
        $recentGames = $this->getRecentCompleted($spaceId, 5);

        // Classement par type de jeu
        $statsByGameType = $this->getStatsByGameType($spaceId);

        // Activité récente (parties par mois)
        $monthlyActivity = $this->getMonthlyActivity($spaceId, 12);

        // Joueur le plus actif (nombre de manches jouées)
        $mostActivePlayer = $this->getMostActivePlayer($spaceId);

        $this->render('stats/index', [
            'title'           => 'Statistiques',
            'currentSpace'    => $ctx['space'],
            'spaceRole'       => $ctx['member']['role'],
            'activeMenu'      => 'stats',
            'overview'        => $overview,
            'topPlayers'      => $topPlayers,
            'recentGames'     => $recentGames,
            'statsByGameType' => $statsByGameType,
            'monthlyActivity' => $monthlyActivity,
            'mostActivePlayer'=> $mostActivePlayer,
        ]);
    }

    /**
     * Vue d'ensemble chiffrée de l'espace.
     */
    private function getOverview(int $spaceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM games WHERE space_id = :s1) AS total_games,
                (SELECT COUNT(*) FROM games WHERE space_id = :s2 AND status = 'completed') AS completed_games,
                (SELECT COUNT(*) FROM games WHERE space_id = :s3 AND status = 'in_progress') AS active_games,
                (SELECT COUNT(*) FROM players WHERE space_id = :s4) AS total_players,
                (SELECT COUNT(*) FROM game_types WHERE space_id = :s5 OR is_global = 1) AS total_game_types,
                (SELECT COUNT(*) FROM rounds r JOIN games g ON g.id = r.game_id WHERE g.space_id = :s6) AS total_rounds
        ");
        $stmt->execute([
            's1' => $spaceId, 's2' => $spaceId, 's3' => $spaceId,
            's4' => $spaceId, 's5' => $spaceId, 's6' => $spaceId,
        ]);
        return $stmt->fetch();
    }

    /**
     * Top joueurs par taux de manches gagnées.
     * Une manche est considérée gagnée si le joueur a le meilleur score
     * (selon la win_condition du jeu). En cas d'égalité au 1er rang,
     * tous les joueurs ex-aequo sont comptés comme gagnants.
     * Inclut toutes les manches terminées, y compris celles des parties en cours.
     */
    private function getTopPlayers(int $spaceId, int $limit): array
    {
        $result = $this->buildRoundPerformance($spaceId);

        // Trier par manches gagnées DESC, puis taux DESC
        usort($result, function ($a, $b) {
            if ($b['rounds_won'] !== $a['rounds_won']) {
                return $b['rounds_won'] - $a['rounds_won'];
            }
            return $b['win_rate'] <=> $a['win_rate'];
        });

        return array_slice($result, 0, $limit);
    }

    /**
     * Dernières parties (terminées ou en cours).
     */
    private function getRecentCompleted(int $spaceId, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT g.id, g.status, g.created_at,
                   gt.name AS game_type_name,
                   (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) AS player_count,
                   (SELECT GROUP_CONCAT(p.name SEPARATOR ', ')
                    FROM game_players gp2 JOIN players p ON p.id = gp2.player_id
                    WHERE gp2.game_id = g.id AND gp2.rank = 1) AS winner_name
            FROM games g
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.space_id = :space_id
            ORDER BY g.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue('space_id', $spaceId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Statistiques par type de jeu.
     */
    private function getStatsByGameType(int $spaceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                gt.id,
                gt.name,
                gt.win_condition,
                COUNT(g.id) AS total_games,
                SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) AS completed_games,
                ROUND(AVG(
                    (SELECT COUNT(*) FROM rounds r WHERE r.game_id = g.id)
                ), 1) AS avg_rounds
            FROM game_types gt
            LEFT JOIN games g ON g.game_type_id = gt.id
            WHERE gt.space_id = :space_id
            GROUP BY gt.id, gt.name, gt.win_condition
            ORDER BY total_games DESC
        ");
        $stmt->execute(['space_id' => $spaceId]);
        return $stmt->fetchAll();
    }

    /**
     * Activité mensuelle (nombre de parties créées par mois).
     */
    private function getMonthlyActivity(int $spaceId, int $months): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE_FORMAT(COALESCE(started_at, created_at), '%Y-%m') AS month,
                COUNT(*) AS game_count
            FROM games
            WHERE space_id = :space_id
              AND COALESCE(started_at, created_at) >= DATE_SUB(NOW(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(COALESCE(started_at, created_at), '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->bindValue('space_id', $spaceId, \PDO::PARAM_INT);
        $stmt->bindValue('months', $months, \PDO::PARAM_INT);
        $stmt->execute();
        $raw = $stmt->fetchAll();

        $countsByMonth = [];
        foreach ($raw as $row) {
            $countsByMonth[$row['month']] = (int) $row['game_count'];
        }

        // Compléter les mois sans activité pour un graphe continu.
        $monthLabels = [
            '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Avr',
            '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aou',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
        ];

        $series = [];
        $start = new \DateTimeImmutable('first day of -' . max($months - 1, 0) . ' month');
        for ($i = 0; $i < $months; $i++) {
            $dt = $start->modify('+' . $i . ' month');
            $key = $dt->format('Y-m');
            $series[] = [
                'month'      => $key,
                'label'      => ($monthLabels[$dt->format('m')] ?? $dt->format('m')) . ' ' . $dt->format('y'),
                'game_count' => $countsByMonth[$key] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Joueur le plus actif de l'espace (nombre de manches jouées).
     */
    private function getMostActivePlayer(int $spaceId): ?array
    {
        $players = $this->buildRoundPerformance($spaceId);
        if (empty($players)) {
            return null;
        }

        usort($players, function ($a, $b) {
            if ($b['rounds_played'] !== $a['rounds_played']) {
                return $b['rounds_played'] <=> $a['rounds_played'];
            }
            if ($b['win_rate'] !== $a['win_rate']) {
                return $b['win_rate'] <=> $a['win_rate'];
            }
            if ($b['rounds_won'] !== $a['rounds_won']) {
                return $b['rounds_won'] <=> $a['rounds_won'];
            }
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $players[0] ?? null;
    }

    /**
     * Construit les performances de joueurs basées sur les manches:
     * - manches jouées
     * - manches gagnées
     * - taux = gagnées / jouées
     */
    private function buildRoundPerformance(int $spaceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id AS round_id, gt.win_condition
            FROM rounds r
            JOIN games g ON g.id = r.game_id
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.space_id = :space_id
              AND r.status = 'completed'
        ");
        $stmt->execute(['space_id' => $spaceId]);
        $rounds = $stmt->fetchAll();

        $roundsPlayed = [];
        $roundsWon = [];

        foreach ($rounds as $round) {
            $scoreStmt = $this->pdo->prepare("
                SELECT rs.player_id, rs.score
                FROM round_scores rs
                WHERE rs.round_id = :round_id
            ");
            $scoreStmt->execute(['round_id' => $round['round_id']]);
            $scores = $scoreStmt->fetchAll();

            if (empty($scores)) {
                continue;
            }

            foreach ($scores as $scoreRow) {
                $pid = (int) $scoreRow['player_id'];
                $roundsPlayed[$pid] = ($roundsPlayed[$pid] ?? 0) + 1;
            }

            $scoreValues = array_column($scores, 'score');
            $winCondition = (string) $round['win_condition'];
            $bestScore = ($winCondition === 'ranking' || $winCondition === 'lowest_score')
                ? min($scoreValues)
                : max($scoreValues);

            foreach ($scores as $scoreRow) {
                if ((float) $scoreRow['score'] === (float) $bestScore) {
                    $pid = (int) $scoreRow['player_id'];
                    $roundsWon[$pid] = ($roundsWon[$pid] ?? 0) + 1;
                }
            }
        }

        $playerStmt = $this->pdo->prepare("
            SELECT p.id, p.name
            FROM players p
            WHERE p.space_id = :space_id
        ");
        $playerStmt->execute(['space_id' => $spaceId]);
        $players = $playerStmt->fetchAll();

        $result = [];
        foreach ($players as $player) {
            $pid = (int) $player['id'];
            $played = $roundsPlayed[$pid] ?? 0;
            if ($played === 0) {
                continue;
            }

            $won = $roundsWon[$pid] ?? 0;
            $result[] = [
                'id' => $pid,
                'name' => $player['name'],
                'rounds_played' => $played,
                'rounds_won' => $won,
                'win_rate' => round(($won * 100.0) / $played, 1),
            ];
        }

        return $result;
    }
}
