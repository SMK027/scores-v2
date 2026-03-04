<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Space;
use App\Config\Database;

/**
 * Contrôleur de recherche dans un espace.
 */
class SearchController extends Controller
{
    private Space $spaceModel;
    private \PDO $pdo;

    public function __construct()
    {
        $this->spaceModel = new Space();
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Page de recherche avec résultats.
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            return;
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
            return;
        }

        $query = trim($_GET['q'] ?? '');
        $results = [];

        if (strlen($query) >= 2) {
            $results = $this->search((int) $id, $query);
        }

        $this->render('search/index', [
            'title'        => 'Recherche',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'],
            'activeMenu'   => 'search',
            'query'        => $query,
            'results'      => $results,
        ]);
    }

    /**
     * Effectue la recherche dans les différentes tables de l'espace.
     */
    private function search(int $spaceId, string $query): array
    {
        $results = [];
        $like = '%' . $query . '%';

        // Recherche dans les joueurs
        $stmt = $this->pdo->prepare("
            SELECT id, name, 'player' AS type, NULL AS extra
            FROM players
            WHERE space_id = :sid AND name LIKE :q
            ORDER BY name ASC
            LIMIT 20
        ");
        $stmt->execute(['sid' => $spaceId, 'q' => $like]);
        $results = array_merge($results, $stmt->fetchAll());

        // Recherche dans les types de jeu
        $stmt = $this->pdo->prepare("
            SELECT id, name, 'game_type' AS type, description AS extra
            FROM game_types
            WHERE space_id = :sid AND (name LIKE :q OR description LIKE :q2)
            ORDER BY name ASC
            LIMIT 20
        ");
        $stmt->execute(['sid' => $spaceId, 'q' => $like, 'q2' => $like]);
        $results = array_merge($results, $stmt->fetchAll());

        // Recherche dans les parties (notes + type de jeu)
        $stmt = $this->pdo->prepare("
            SELECT g.id, gt.name AS name, 'game' AS type,
                   g.status, DATE_FORMAT(g.created_at, '%d/%m/%Y') AS game_date
            FROM games g
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.space_id = :sid AND (gt.name LIKE :q OR g.notes LIKE :q2)
            ORDER BY g.created_at DESC
            LIMIT 20
        ");
        $stmt->execute(['sid' => $spaceId, 'q' => $like, 'q2' => $like]);
        $games = $stmt->fetchAll();
        foreach ($games as &$game) {
            $game['extra'] = game_status_label($game['status']) . ' - ' . $game['game_date'];
        }
        $results = array_merge($results, $games);

        // Recherche dans les commentaires
        $stmt = $this->pdo->prepare("
            SELECT c.game_id AS id, SUBSTRING(c.content, 1, 80) AS name, 'comment' AS type,
                   u.username AS extra
            FROM comments c
            JOIN users u ON u.id = c.user_id
            JOIN games g ON g.id = c.game_id
            WHERE g.space_id = :sid AND c.content LIKE :q
            ORDER BY c.created_at DESC
            LIMIT 20
        ");
        $stmt->execute(['sid' => $spaceId, 'q' => $like]);
        $results = array_merge($results, $stmt->fetchAll());

        return $results;
    }
}
