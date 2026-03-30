<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\GameType;
use App\Models\Space;
use App\Models\ActivityLog;

/**
 * Contrôleur des types de jeux.
 */
class GameTypeController extends Controller
{
    private GameType $gameTypeModel;
    private Space $spaceModel;

    public function __construct()
    {
        $this->gameTypeModel = new GameType();
        $this->spaceModel = new Space();
    }

    /**
     * Vérifie l'accès à l'espace et retourne les infos.
     */
    private function checkAccess(string $spaceId, array $roles = ['admin', 'manager', 'member', 'guest']): array
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }
        $member = Middleware::checkSpaceAccess((int) $spaceId, $this->getCurrentUserId(), $roles);
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
        }
        return ['space' => $space, 'member' => $member];
    }

    /**
     * Liste les types de jeux d'un espace.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $gameTypes = $this->gameTypeModel->findBySpace((int) $id);

        $this->render('game_types/index', [
            'title'        => 'Types de jeux',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'game-types',
            'gameTypes'    => $gameTypes,
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function createForm(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkSpaceRestriction((int) $id, 'game_types');
        $this->render('game_types/create', [
            'title'        => 'Nouveau type de jeu',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'game-types',
        ]);
    }

    /**
     * Traite la création.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkSpaceRestriction((int) $id, 'game_types');
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'description', 'win_condition', 'min_players', 'max_players']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom du type de jeu est requis.');
            $this->redirect("/spaces/{$id}/game-types/create");
        }

        $this->gameTypeModel->create([
            'space_id'      => (int) $id,
            'name'          => $data['name'],
            'description'   => $data['description'],
            'win_condition' => $data['win_condition'] ?: 'highest_score',
            'min_players'   => (int) ($data['min_players'] ?: 2),
            'max_players'   => !empty($data['max_players']) ? (int) $data['max_players'] : null,
        ]);

        ActivityLog::logSpace((int) $id, 'game_type.create', $this->getCurrentUserId(), 'game_type', null, ['name' => $data['name']]);

        $this->setFlash('success', 'Type de jeu créé.');
        $this->redirect("/spaces/{$id}/game-types");
    }

    /**
     * Formulaire d'édition.
     */
    public function editForm(string $id, string $gtid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'game_types');
        $gameType = $this->gameTypeModel->find((int) $gtid);

        if (!$gameType || $gameType['space_id'] != $id) {
            $this->setFlash('danger', 'Type de jeu introuvable.');
            $this->redirect("/spaces/{$id}/game-types");
        }

        if (!empty($gameType['is_global'])) {
            $this->setFlash('danger', 'Les types de jeux globaux ne peuvent être modifiés que depuis l\'administration.');
            $this->redirect("/spaces/{$id}/game-types");
        }

        $this->render('game_types/edit', [
            'title'        => 'Modifier le type de jeu',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'game-types',
            'gameType'     => $gameType,
        ]);
    }

    /**
     * Traite la modification.
     */
    public function update(string $id, string $gtid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'game_types');
        $this->validateCSRF();

        $existing = $this->gameTypeModel->find((int) $gtid);
        if ($existing && !empty($existing['is_global'])) {
            $this->setFlash('danger', 'Les types de jeux globaux ne peuvent être modifiés que depuis l\'administration.');
            $this->redirect("/spaces/{$id}/game-types");
            return;
        }

        $data = $this->getPostData(['name', 'description', 'win_condition', 'min_players', 'max_players']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom est requis.');
            $this->redirect("/spaces/{$id}/game-types/{$gtid}/edit");
        }

        $this->gameTypeModel->update((int) $gtid, [
            'name'          => $data['name'],
            'description'   => $data['description'],
            'win_condition' => $data['win_condition'] ?: 'highest_score',
            'min_players'   => (int) ($data['min_players'] ?: 2),
            'max_players'   => !empty($data['max_players']) ? (int) $data['max_players'] : null,
        ]);

        ActivityLog::logSpace((int) $id, 'game_type.update', $this->getCurrentUserId(), 'game_type', (int) $gtid, ['name' => $data['name']]);

        $this->setFlash('success', 'Type de jeu mis à jour.');
        $this->redirect("/spaces/{$id}/game-types");
    }

    /**
     * Supprime un type de jeu.
     */
    public function delete(string $id, string $gtid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'game_types');
        $this->validateCSRF();

        $existing = $this->gameTypeModel->find((int) $gtid);
        if ($existing && !empty($existing['is_global'])) {
            $this->setFlash('danger', 'Les types de jeux globaux ne peuvent être supprimés que depuis l\'administration.');
            $this->redirect("/spaces/{$id}/game-types");
            return;
        }

        ActivityLog::logSpace((int) $id, 'game_type.delete', $this->getCurrentUserId(), 'game_type', (int) $gtid);

        $this->gameTypeModel->delete((int) $gtid);
        $this->setFlash('success', 'Type de jeu supprimé.');
        $this->redirect("/spaces/{$id}/game-types");
    }

    /**
     * Formulaire de remplacement d'un type local par un type global.
     */
    public function replaceForm(string $id, string $gtid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $localType = $this->gameTypeModel->find((int) $gtid);

        if (!$localType || (int) ($localType['space_id'] ?? 0) !== (int) $id || !empty($localType['is_global'])) {
            $this->setFlash('danger', 'Type de jeu introuvable ou non remplaçable.');
            $this->redirect("/spaces/{$id}/game-types");
        }

        $globalTypes = $this->gameTypeModel->findGlobal();
        if (empty($globalTypes)) {
            $this->setFlash('warning', 'Aucun type de jeu global disponible pour le remplacement.');
            $this->redirect("/spaces/{$id}/game-types");
        }

        $this->render('game_types/replace', [
            'title'        => 'Remplacer un type de jeu',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'game-types',
            'localType'    => $localType,
            'globalTypes'  => $globalTypes,
        ]);
    }

    /**
     * Traite le remplacement d'un type local par un type global.
     */
    public function replace(string $id, string $gtid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->validateCSRF();

        $localType = $this->gameTypeModel->find((int) $gtid);
        if (!$localType || (int) ($localType['space_id'] ?? 0) !== (int) $id || !empty($localType['is_global'])) {
            $this->setFlash('danger', 'Type de jeu introuvable ou non remplaçable.');
            $this->redirect("/spaces/{$id}/game-types");
            return;
        }

        $data = $this->getPostData(['global_game_type_id']);
        $globalId = (int) ($data['global_game_type_id'] ?? 0);

        $globalType = $this->gameTypeModel->find($globalId);
        if (!$globalType || empty($globalType['is_global'])) {
            $this->setFlash('danger', 'Le type de jeu global sélectionné est invalide.');
            $this->redirect("/spaces/{$id}/game-types/{$gtid}/replace");
            return;
        }

        $count = $this->gameTypeModel->replaceWithGlobal((int) $gtid, $globalId);

        ActivityLog::logSpace((int) $id, 'game_type.replace', $this->getCurrentUserId(), 'game_type', (int) $gtid, [
            'local_name'  => $localType['name'],
            'global_name' => $globalType['name'],
            'games_moved' => $count,
        ]);

        $this->setFlash('success', "Type « {$localType['name']} » remplacé par « {$globalType['name']} ». {$count} partie(s) mise(s) à jour.");
        $this->redirect("/spaces/{$id}/game-types");
    }
}
