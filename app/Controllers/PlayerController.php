<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Player;
use App\Models\Space;
use App\Models\User;

/**
 * Contrôleur des joueurs.
 */
class PlayerController extends Controller
{
    private Player $playerModel;
    private Space $spaceModel;

    public function __construct()
    {
        $this->playerModel = new Player();
        $this->spaceModel = new Space();
    }

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
     * Liste les joueurs.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $players = $this->playerModel->findBySpace((int) $id);

        // Récupérer les membres de l'espace pour la liaison
        $this->render('players/index', [
            'title'        => 'Joueurs',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'players',
            'players'      => $players,
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function createForm(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);

        $this->render('players/create', [
            'title'        => 'Ajouter un joueur',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'players',
        ]);
    }

    /**
     * Traite la création.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'user_id']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom du joueur est requis.');
            $this->redirect("/spaces/{$id}/players/create");
        }

        $createData = [
            'space_id' => (int) $id,
            'name'     => $data['name'],
        ];

        if (!empty($data['user_id'])) {
            $createData['user_id'] = (int) $data['user_id'];
        }

        $this->playerModel->create($createData);
        $this->setFlash('success', 'Joueur ajouté.');
        $this->redirect("/spaces/{$id}/players");
    }

    /**
     * Formulaire d'édition.
     */
    public function editForm(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $player = $this->playerModel->find((int) $pid);

        if (!$player || $player['space_id'] != $id) {
            $this->setFlash('danger', 'Joueur introuvable.');
            $this->redirect("/spaces/{$id}/players");
        }

        $this->render('players/edit', [
            'title'        => 'Modifier le joueur',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'players',
            'player'       => $player,
        ]);
    }

    /**
     * Traite la modification.
     */
    public function update(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'user_id']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom est requis.');
            $this->redirect("/spaces/{$id}/players/{$pid}/edit");
        }

        $updateData = ['name' => $data['name']];
        if (!empty($data['user_id'])) {
            $updateData['user_id'] = (int) $data['user_id'];
        } else {
            $updateData['user_id'] = null;
        }

        $this->playerModel->update((int) $pid, $updateData);
        $this->setFlash('success', 'Joueur mis à jour.');
        $this->redirect("/spaces/{$id}/players");
    }

    /**
     * Supprime un joueur.
     */
    public function delete(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->validateCSRF();

        $this->playerModel->delete((int) $pid);
        $this->setFlash('success', 'Joueur supprimé.');
        $this->redirect("/spaces/{$id}/players");
    }
}
