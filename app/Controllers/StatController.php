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
        $monthlyActivity = $this->getMonthlyActivity($spaceId, 6);

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
                (SELECT COUNT(*) FROM game_types WHERE space_id = :s5) AS total_game_types,
                (SELECT COUNT(*) FROM rounds r JOIN games g ON g.id = r.game_id WHERE g.space_id = :s6) AS total_rounds
        ");
        $stmt->execute([
            's1' => $spaceId, 's2' => $spaceId, 's3' => $spaceId,
            's4' => $spaceId, 's5' => $spaceId, 's6' => $spaceId,
        ]);
        return $stmt->fetch();
    }

    /**
     * Top joueurs par nombre de victoires (rang 1).
     */
    private function getTopPlayers(int $spaceId, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.name,
                COUNT(DISTINCT gp.game_id) AS games_played,
                SUM(CASE WHEN gp.rank = 1 THEN 1 ELSE 0 END) AS wins,
                ROUND(AVG(gp.total_score), 2) AS avg_score,
                ROUND(
                    SUM(CASE WHEN gp.rank = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(DISTINCT gp.game_id), 0),
                    1
                ) AS win_rate
            FROM players p
            JOIN game_players gp ON gp.player_id = p.id
            JOIN games g ON g.id = gp.game_id AND g.space_id = :space_id AND g.status = 'completed'
            WHERE p.space_id = :space_id2
            GROUP BY p.id, p.name
            ORDER BY wins DESC, win_rate DESC, avg_score DESC
            LIMIT :lim
        ");
        $stmt->bindValue('space_id', $spaceId, \PDO::PARAM_INT);
        $stmt->bindValue('space_id2', $spaceId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Dernières parties terminées.
     */
    private function getRecentCompleted(int $spaceId, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT g.id, g.status, g.created_at,
                   gt.name AS game_type_name,
                   (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) AS player_count,
                   (SELECT p.name FROM game_players gp2 JOIN players p ON p.id = gp2.player_id
                    WHERE gp2.game_id = g.id AND gp2.rank = 1 LIMIT 1) AS winner_name
            FROM games g
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.space_id = :space_id AND g.status = 'completed'
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
                DATE_FORMAT(created_at, '%Y-%m') AS month,
                COUNT(*) AS game_count
            FROM games
            WHERE space_id = :space_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->bindValue('space_id', $spaceId, \PDO::PARAM_INT);
        $stmt->bindValue('months', $months, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
