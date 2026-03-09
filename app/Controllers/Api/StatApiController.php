<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Game;
use App\Models\Player;
use App\Models\GameType;
use App\Models\Round;
use App\Models\GamePlayer;

/**
 * API REST pour les statistiques d'un espace.
 */
class StatApiController extends ApiController
{
    /**
     * GET /api/spaces/{id}/stats
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $gameModel = new Game();
        $playerModel = new Player();
        $gameTypeModel = new GameType();
        $roundModel = new Round();
        $gamePlayerModel = new GamePlayer();

        // Vue d'ensemble
        $games = $gameModel->findBySpace((int) $id, 1, 1000, []);
        $totalGames = $games['total'];
        $players = $playerModel->findBySpace((int) $id);
        $gameTypes = $gameTypeModel->findBySpace((int) $id);

        // Compter les manches
        $totalRounds = 0;
        $completedGames = 0;
        foreach ($games['data'] as $g) {
            if ($g['status'] === 'completed') {
                $completedGames++;
            }
        }

        // Top joueurs (via findWithStats pour chaque joueur)
        $topPlayers = [];
        foreach ($players as $player) {
            $stats = $playerModel->findWithStats((int) $player['id']);
            if ($stats) {
                $topPlayers[] = $stats;
            }
        }

        // Trier par win_count DESC
        usort($topPlayers, fn($a, $b) => ($b['win_count'] ?? 0) <=> ($a['win_count'] ?? 0));
        $topPlayers = array_slice($topPlayers, 0, 10);

        // Statistiques par type de jeu
        $statsByGameType = [];
        foreach ($gameTypes as $gt) {
            $filtered = $gameModel->findBySpace((int) $id, 1, 1000, ['game_type_id' => $gt['id']]);
            $statsByGameType[] = [
                'game_type' => $gt,
                'total_games' => $filtered['total'],
            ];
        }

        // Parties récentes terminées
        $recentCompleted = [];
        foreach ($games['data'] as $g) {
            if ($g['status'] === 'completed') {
                $recentCompleted[] = $g;
            }
            if (count($recentCompleted) >= 5) break;
        }

        $this->json([
            'success' => true,
            'overview' => [
                'total_games'     => $totalGames,
                'completed_games' => $completedGames,
                'total_players'   => count($players),
                'total_game_types' => count($gameTypes),
            ],
            'top_players'       => $topPlayers,
            'stats_by_game_type' => $statsByGameType,
            'recent_completed'  => $recentCompleted,
        ]);
    }
}
