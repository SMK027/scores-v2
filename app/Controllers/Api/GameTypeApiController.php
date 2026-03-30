<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\GameType;
use App\Models\ActivityLog;

/**
 * API REST pour les types de jeux.
 */
class GameTypeApiController extends ApiController
{
    private GameType $model;

    public function __construct()
    {
        $this->model = new GameType();
    }

    /**
     * GET /api/spaces/{id}/game-types
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $this->json([
            'success'    => true,
            'game_types' => $this->model->findBySpace((int) $id),
        ]);
    }

    /**
     * GET /api/spaces/{id}/game-types/{gtid}
     */
    public function show(string $id, string $gtid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $gt = $this->model->find((int) $gtid);
        if (!$gt || !$this->model->isAccessibleInSpace((int) $gtid, (int) $id)) {
            $this->error('Type de jeu introuvable.', 404);
        }

        $this->json(['success' => true, 'game_type' => $gt]);
    }

    /**
     * POST /api/spaces/{id}/game-types
     * Body: { name, description?, win_condition, min_players?, max_players? }
     */
    public function create(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);
        $this->checkSpaceRestriction((int) $id, 'game_types');

        $data = $this->getJsonBody();
        $name = trim($data['name'] ?? '');
        $winCondition = $data['win_condition'] ?? 'highest_score';

        if (empty($name)) {
            $this->error('Le nom est requis.');
        }

        $validConditions = ['highest_score', 'lowest_score', 'ranking', 'win_loss'];
        if (!in_array($winCondition, $validConditions, true)) {
            $this->error('Condition de victoire invalide.');
        }

        $gtId = $this->model->create([
            'space_id'      => (int) $id,
            'name'          => $name,
            'description'   => trim($data['description'] ?? ''),
            'win_condition' => $winCondition,
            'min_players'   => (int) ($data['min_players'] ?? 1),
            'max_players'   => !empty($data['max_players']) ? (int) $data['max_players'] : null,
        ]);

        ActivityLog::logSpace((int) $id, 'game_type.create', $this->userId, 'game_type', $gtId, ['name' => $name]);

        $this->json(['success' => true, 'game_type' => $this->model->find($gtId)], 201);
    }

    /**
     * PUT /api/spaces/{id}/game-types/{gtid}
     */
    public function update(string $id, string $gtid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);
        $this->checkSpaceRestriction((int) $id, 'game_types');

        $gt = $this->model->find((int) $gtid);
        if (!$gt || (int) ($gt['space_id'] ?? 0) !== (int) $id) {
            $this->error('Type de jeu introuvable.', 404);
        }

        if (!empty($gt['is_global'])) {
            $this->error('Les types de jeux globaux ne peuvent être modifiés que depuis l\'administration.', 403);
        }

        $data = $this->getJsonBody();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            $this->error('Le nom est requis.');
        }

        $winCondition = $data['win_condition'] ?? $gt['win_condition'];
        $validConditions = ['highest_score', 'lowest_score', 'ranking', 'win_loss'];
        if (!in_array($winCondition, $validConditions, true)) {
            $this->error('Condition de victoire invalide.');
        }

        $this->model->update((int) $gtid, [
            'name'          => $name,
            'description'   => trim($data['description'] ?? ''),
            'win_condition' => $winCondition,
            'min_players'   => (int) ($data['min_players'] ?? 1),
            'max_players'   => !empty($data['max_players']) ? (int) $data['max_players'] : null,
        ]);

        ActivityLog::logSpace((int) $id, 'game_type.update', $this->userId, 'game_type', (int) $gtid, ['name' => $name]);

        $this->json(['success' => true, 'game_type' => $this->model->find((int) $gtid)]);
    }

    /**
     * DELETE /api/spaces/{id}/game-types/{gtid}
     */
    public function delete(string $id, string $gtid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'game_types');

        $gt = $this->model->find((int) $gtid);
        if (!$gt || (int) ($gt['space_id'] ?? 0) !== (int) $id) {
            $this->error('Type de jeu introuvable.', 404);
        }

        if (!empty($gt['is_global'])) {
            $this->error('Les types de jeux globaux ne peuvent être supprimés que depuis l\'administration.', 403);
        }

        ActivityLog::logSpace((int) $id, 'game_type.delete', $this->userId, 'game_type', (int) $gtid);
        $this->model->delete((int) $gtid);

        $this->json(['success' => true, 'message' => 'Type de jeu supprimé.']);
    }

    /**
     * POST /api/spaces/{id}/game-types/{gtid}/replace
     * Body: { global_game_type_id }
     */
    public function replace(string $id, string $gtid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

        $localType = $this->model->find((int) $gtid);
        if (!$localType || (int) ($localType['space_id'] ?? 0) !== (int) $id || !empty($localType['is_global'])) {
            $this->error('Type de jeu introuvable ou non remplaçable.', 404);
        }

        $data = $this->getJsonBody();
        $globalId = (int) ($data['global_game_type_id'] ?? 0);

        $globalType = $this->model->find($globalId);
        if (!$globalType || empty($globalType['is_global'])) {
            $this->error('Le type de jeu global sélectionné est invalide.', 400);
        }

        $count = $this->model->replaceWithGlobal((int) $gtid, $globalId);

        ActivityLog::logSpace((int) $id, 'game_type.replace', $this->userId, 'game_type', (int) $gtid, [
            'local_name'  => $localType['name'],
            'global_name' => $globalType['name'],
            'games_moved' => $count,
        ]);

        $this->json([
            'success'       => true,
            'message'       => "Type remplacé. {$count} partie(s) mise(s) à jour.",
            'games_updated' => $count,
        ]);
    }
}
