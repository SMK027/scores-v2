<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Space;
use App\Models\SpaceMember;
use App\Models\SpaceInvite;
use App\Models\User;

/**
 * Contrôleur des espaces de jeu.
 */
class SpaceController extends Controller
{
    private Space $spaceModel;
    private SpaceMember $memberModel;
    private SpaceInvite $inviteModel;

    public function __construct()
    {
        $this->spaceModel = new Space();
        $this->memberModel = new SpaceMember();
        $this->inviteModel = new SpaceInvite();
    }

    /**
     * Liste des espaces de l'utilisateur.
     */
    public function index(): void
    {
        $this->requireAuth();
        $spaces = $this->spaceModel->findByUser($this->getCurrentUserId());

        $this->render('spaces/index', [
            'title'  => 'Mes espaces',
            'spaces' => $spaces,
        ]);
    }

    /**
     * Formulaire de création d'espace.
     */
    public function createForm(): void
    {
        $this->requireAuth();
        $this->render('spaces/create', ['title' => 'Créer un espace']);
    }

    /**
     * Traite la création d'un espace.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'description']);
        $userId = $this->getCurrentUserId();

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom de l\'espace est requis.');
            $this->redirect('/spaces/create');
        }

        if (strlen($data['name']) > 100) {
            $this->setFlash('danger', 'Le nom de l\'espace ne peut pas dépasser 100 caractères.');
            $this->redirect('/spaces/create');
        }

        // Créer l'espace
        $spaceId = $this->spaceModel->create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'created_by'  => $userId,
        ]);

        // Ajouter le créateur comme admin
        $this->memberModel->addMember($spaceId, $userId, 'admin');

        $this->setFlash('success', 'Espace "' . $data['name'] . '" créé avec succès.');
        $this->redirect('/spaces/' . $spaceId);
    }

    /**
     * Affiche le tableau de bord d'un espace.
     */
    public function show(string $id): void
    {
        $this->requireAuth();

        $space = $this->spaceModel->findWithDetails((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());
        if (!$member) {
            $this->setFlash('danger', 'Vous n\'avez pas accès à cet espace.');
            $this->redirect('/spaces');
        }

        // Récupérer les dernières parties
        $stmt = (new \App\Models\Game())->getRecentBySpace((int) $id, 5);

        $this->render('spaces/show', [
            'title'        => $space['name'],
            'currentSpace' => $space,
            'spaceRole'    => $member['role'],
            'activeMenu'   => 'dashboard',
            'recentGames'  => $stmt,
        ]);
    }

    /**
     * Formulaire d'édition d'un espace.
     */
    public function editForm(string $id): void
    {
        $this->requireAuth();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id);
        }
        
        // Les admins globaux (non superadmin) ne peuvent pas modifier les paramètres
        if (!empty($member['is_global_staff']) && !Middleware::isSuperAdmin()) {
            $this->setFlash('danger', 'Seul le super administrateur peut modifier les paramètres de l\'espace.');
            $this->redirect('/spaces/' . $id);
        }

        $this->render('spaces/edit', [
            'title'        => 'Modifier l\'espace',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'],
            'activeMenu'   => 'settings',
        ]);
    }

    /**
     * Traite la modification d'un espace.
     */
    public function update(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id);
        }
        
        // Les admins globaux (non superadmin) ne peuvent pas modifier les paramètres
        if (!empty($member['is_global_staff']) && !Middleware::isSuperAdmin()) {
            $this->setFlash('danger', 'Seul le super administrateur peut modifier les paramètres de l\'espace.');
            $this->redirect('/spaces/' . $id);
        }

        $data = $this->getPostData(['name', 'description']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom de l\'espace est requis.');
            $this->redirect('/spaces/' . $id . '/edit');
        }

        $this->spaceModel->update((int) $id, [
            'name'        => $data['name'],
            'description' => $data['description'],
        ]);

        $this->setFlash('success', 'Espace mis à jour.');
        $this->redirect('/spaces/' . $id);
    }

    /**
     * Supprime un espace.
     */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id);
        }
        
        // Les admins globaux (non superadmin) ne peuvent pas supprimer les espaces
        if (!empty($member['is_global_staff']) && !Middleware::isSuperAdmin()) {
            $this->setFlash('danger', 'Seul le super administrateur peut supprimer un espace.');
            $this->redirect('/spaces/' . $id);
        }

        $this->spaceModel->delete((int) $id);
        $this->setFlash('success', 'Espace supprimé.');
        $this->redirect('/spaces');
    }

    /**
     * Gestion des membres de l'espace.
     */
    public function members(string $id): void
    {
        $this->requireAuth();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin', 'manager']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id);
        }

        $members = $this->memberModel->findBySpace((int) $id);
        $activeInvites = $this->inviteModel->findActiveBySpace((int) $id);

        $this->render('spaces/members', [
            'title'         => 'Membres',
            'currentSpace'  => $space,
            'spaceRole'     => $member['role'],
            'activeMenu'    => 'members',
            'members'       => $members,
            'activeInvites' => $activeInvites,
        ]);
    }

    /**
     * Ajoute un membre par nom d'utilisateur.
     */
    public function addMember(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin', 'manager']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'member';

        if (empty($username)) {
            $this->setFlash('danger', 'Le nom d\'utilisateur est requis.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $userModel = new User();
        $user = $userModel->findByUsername($username);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        if ($this->memberModel->isMember((int) $id, $user['id'])) {
            $this->setFlash('warning', 'Cet utilisateur est déjà membre de l\'espace.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $this->memberModel->addMember((int) $id, $user['id'], $role);
        $this->setFlash('success', $username . ' a été ajouté à l\'espace.');
        $this->redirect('/spaces/' . $id . '/members');
    }

    /**
     * Met à jour le rôle d'un membre.
     */
    public function updateMemberRole(string $id, string $mid): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $role = $_POST['role'] ?? 'member';
        $this->memberModel->updateRole((int) $mid, $role);

        $this->setFlash('success', 'Rôle mis à jour.');
        $this->redirect('/spaces/' . $id . '/members');
    }

    /**
     * Retire un membre de l'espace.
     */
    public function removeMember(string $id, string $mid): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        // Vérifier si le membre à retirer est le créateur de l'espace
        $space = $this->spaceModel->find((int) $id);
        $memberToRemove = $this->memberModel->find((int) $mid);
        
        if ($memberToRemove && $memberToRemove['user_id'] == $space['created_by']) {
            $this->setFlash('danger', 'Impossible de retirer le créateur de l\'espace.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $this->memberModel->delete((int) $mid);
        $this->setFlash('success', 'Membre retiré de l\'espace.');
        $this->redirect('/spaces/' . $id . '/members');
    }

    /**
     * Génère un lien d'invitation.
     */
    public function invite(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin', 'manager']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $token = $this->inviteModel->createInvite((int) $id, $this->getCurrentUserId());
        $link = url('spaces/join/' . $token);

        \App\Core\Session::set('invite_link', $link);
        $this->setFlash('success', 'Lien d\'invitation créé (valable 72h)');
        $this->redirect('/spaces/' . $id . '/members');
    }

    /**
     * Rejoint un espace via un lien d'invitation.
     */
    public function join(string $token): void
    {
        $this->requireAuth();

        $invite = $this->inviteModel->findValidByToken($token);
        if (!$invite) {
            $this->setFlash('danger', 'Lien d\'invitation invalide ou expiré.');
            $this->redirect('/spaces');
        }

        $userId = $this->getCurrentUserId();
        $spaceId = $invite['space_id'];

        if ($this->memberModel->isMember($spaceId, $userId)) {
            $this->setFlash('info', 'Vous êtes déjà membre de cet espace.');
            $this->redirect('/spaces/' . $spaceId);
        }

        $this->memberModel->addMember($spaceId, $userId, 'member');
        $this->setFlash('success', 'Vous avez rejoint l\'espace "' . $invite['space_name'] . '" !');
        $this->redirect('/spaces/' . $spaceId);
    }

    /**
     * Permet à un membre de quitter l'espace (sauf le créateur).
     */
    public function leave(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            return;
        }

        $userId = $this->getCurrentUserId();

        // Le créateur ne peut pas quitter son propre espace
        if ($space['created_by'] == $userId) {
            $this->setFlash('danger', 'Le créateur ne peut pas quitter son propre espace.');
            $this->redirect('/spaces/' . $id);
            return;
        }

        $member = $this->memberModel->findMember((int) $id, $userId);
        if (!$member) {
            $this->setFlash('warning', 'Vous n\'êtes pas membre de cet espace.');
            $this->redirect('/spaces');
            return;
        }

        $this->memberModel->delete($member['id']);
        $this->setFlash('success', 'Vous avez quitté l\'espace « ' . e($space['name']) . ' ».');
        $this->redirect('/spaces');
    }

    /**
     * Désactive une invitation.
     */
    public function revokeInvite(string $id, string $iid): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), ['admin', 'manager']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id . '/members');
        }

        $this->inviteModel->delete((int) $iid);
        $this->setFlash('success', 'Invitation désactivée.');
        $this->redirect('/spaces/' . $id . '/members');
    }
}
