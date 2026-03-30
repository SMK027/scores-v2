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

        if (!$this->model->isActiveInSpace((int) $pid, (int) $id)) {
            $this->error('Joueur introuvable.', 404);
        }

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
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

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
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

        $player = $this->model->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
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

        $player = $this->model->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        ActivityLog::logSpace((int) $id, 'player.delete', $this->userId, 'player', (int) $pid);
        $this->model->softDelete((int) $pid);

        $this->json(['success' => true, 'message' => 'Joueur supprimé.']);
    }

    /**
     * POST /api/spaces/{id}/players/{pid}/link
     * Permet à un membre de se raccorder à un joueur non lié.
     */
    public function linkSelf(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        if ($this->model->isUserLinkedInSpace((int) $id, $this->userId)) {
            $this->error('Vous êtes déjà raccordé à un joueur dans cet espace.', 409);
        }

        $player = $this->model->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        if (!empty($player['user_id'])) {
            $this->error('Ce joueur est déjà raccordé à un compte.', 409);
        }

        $this->model->update((int) $pid, ['user_id' => $this->userId]);

        ActivityLog::logSpace((int) $id, 'player.link_self', $this->userId, 'player', (int) $pid, [
            'player_name' => $player['name'],
        ]);

        $this->json(['success' => true, 'player' => $this->model->find((int) $pid)]);
    }
}
