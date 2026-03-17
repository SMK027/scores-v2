<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Player;
use App\Models\ActivityLog;

/**
 * API REST pour les joueurs d'un espace.
 */
class PlayerApiController extends ApiController
{
    private Player $model;

    public function __construct()
    {
        $this->model = new Player();
    }

    /**
     * GET /api/spaces/{id}/players
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $players = $this->model->findBySpace((int) $id);

        $this->json(['success' => true, 'players' => $players]);
    }

    /**
     * GET /api/spaces/{id}/players/{pid}
     */
    public function show(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $player = $this->model->findWithStats((int) $pid);
        if (!$player || (int) $player['space_id'] !== (int) $id) {
            $this->error('Joueur introuvable.', 404);
        }

        $this->json(['success' => true, 'player' => $player]);
    }

    /**
     * POST /api/spaces/{id}/players
     * Body: { name, user_id? }
     */
    public function create(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);

        $data = $this->getJsonBody();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            $this->error('Le nom du joueur est requis.');
        }

        $userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;

        if ($userId !== null && $this->model->isUserLinkedInSpace((int) $id, $userId)) {
            $this->error('Ce compte est déjà rattaché à un autre joueur de cet espace.');
        }

        $playerId = $this->model->create([
            'space_id' => (int) $id,
            'name'     => $name,
            'user_id'  => $userId,
        ]);

        ActivityLog::logSpace((int) $id, 'player.create', $this->userId, 'player', $playerId, ['name' => $name]);

        $this->json(['success' => true, 'player' => $this->model->find($playerId)], 201);
    }

    /**
     * PUT /api/spaces/{id}/players/{pid}
     * Body: { name, user_id? }
     */
    public function update(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);

        $player = $this->model->find((int) $pid);
        if (!$player || (int) $player['space_id'] !== (int) $id) {
            $this->error('Joueur introuvable.', 404);
        }

        $data = $this->getJsonBody();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            $this->error('Le nom du joueur est requis.');
        }

        $userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;

        if ($userId !== null && $this->model->isUserLinkedInSpace((int) $id, $userId, (int) $pid)) {
            $this->error('Ce compte est déjà rattaché à un autre joueur de cet espace.');
        }

        $this->model->update((int) $pid, [
            'name'    => $name,
            'user_id' => $userId,
        ]);

        ActivityLog::logSpace((int) $id, 'player.update', $this->userId, 'player', (int) $pid, ['name' => $name]);

        $this->json(['success' => true, 'player' => $this->model->find((int) $pid)]);
    }

    /**
     * DELETE /api/spaces/{id}/players/{pid}
     */
    public function delete(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

        $player = $this->model->find((int) $pid);
        if (!$player || (int) $player['space_id'] !== (int) $id) {
            $this->error('Joueur introuvable.', 404);
        }

        ActivityLog::logSpace((int) $id, 'player.delete', $this->userId, 'player', (int) $pid);
        $this->model->delete((int) $pid);

        $this->json(['success' => true, 'message' => 'Joueur supprimé.']);
    }
}
