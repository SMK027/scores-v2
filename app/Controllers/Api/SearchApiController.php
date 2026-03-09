<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Player;
use App\Models\GameType;
use App\Models\Game;
use App\Models\Comment;

/**
 * API REST pour la recherche dans un espace.
 */
class SearchApiController extends ApiController
{
    /**
     * GET /api/spaces/{id}/search?q=...
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            $this->error('La recherche doit contenir au moins 2 caractères.');
        }

        $searchTerm = '%' . $query . '%';
        $spaceId = (int) $id;

        // Joueurs
        $playerModel = new Player();
        $players = $this->searchPlayers($spaceId, $searchTerm);

        // Types de jeux
        $gameTypes = $this->searchGameTypes($spaceId, $searchTerm);

        // Parties
        $games = $this->searchGames($spaceId, $searchTerm);

        // Commentaires
        $comments = $this->searchComments($spaceId, $searchTerm);

        $this->json([
            'success'    => true,
            'query'      => $query,
            'results'    => [
                'players'    => $players,
                'game_types' => $gameTypes,
                'games'      => $games,
                'comments'   => $comments,
            ],
            'total' => count($players) + count($gameTypes) + count($games) + count($comments),
        ]);
    }

    private function searchPlayers(int $spaceId, string $term): array
    {
        $model = new Player();
        $all = $model->findBySpace($spaceId);
        return array_values(array_filter($all, fn($p) => stripos($p['name'], trim($term, '%')) !== false));
    }

    private function searchGameTypes(int $spaceId, string $term): array
    {
        $model = new GameType();
        $all = $model->findBySpace($spaceId);
        $search = trim($term, '%');
        return array_values(array_filter($all, fn($g) =>
            stripos($g['name'], $search) !== false ||
            stripos($g['description'] ?? '', $search) !== false
        ));
    }

    private function searchGames(int $spaceId, string $term): array
    {
        $model = new Game();
        $result = $model->findBySpace($spaceId, 1, 100, []);
        $search = trim($term, '%');
        return array_values(array_filter($result['data'], fn($g) =>
            stripos($g['game_type_name'] ?? '', $search) !== false ||
            stripos($g['notes'] ?? '', $search) !== false
        ));
    }

    private function searchComments(int $spaceId, string $term): array
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT c.id, c.content, c.created_at, u.username, c.game_id
            FROM comments c
            JOIN users u ON u.id = c.user_id
            JOIN games g ON g.id = c.game_id
            WHERE g.space_id = :space_id AND c.content LIKE :term
            ORDER BY c.created_at DESC
            LIMIT 20
        ");
        $stmt->execute(['space_id' => $spaceId, 'term' => $term]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
