<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Space;
use App\Models\SpaceMember;
use App\Models\SpaceInvite;
use App\Models\SpaceInvitation;
use App\Models\User;
use App\Models\Game;
use App\Models\Player;
use App\Models\GameType;
use App\Models\ActivityLog;

/**
 * API REST pour la gestion des espaces.
 */
class SpaceApiController extends ApiController
{
    private Space $spaceModel;
    private SpaceMember $memberModel;
    private SpaceInvite $inviteModel;
    private SpaceInvitation $invitationModel;

    public function __construct()
    {
        $this->spaceModel = new Space();
        $this->memberModel = new SpaceMember();
        $this->inviteModel = new SpaceInvite();
        $this->invitationModel = new SpaceInvitation();
    }

    /**
     * GET /api/spaces — Liste des espaces de l'utilisateur.
     */
    public function index(): void
    {
        $this->requireAuth();
        $spaces = $this->spaceModel->findByUser($this->userId);
        $pendingInvitations = $this->invitationModel->findPendingForUser($this->userId);

        // Compter les parties en cours pour chaque espace
        $gameModel = new Game();
        $spacesWithCounts = array_map(function ($space) use ($gameModel) {
            $gamesCount = $gameModel->countInProgressBySpace($space['id']);
            $space['games_count'] = $gamesCount;
            return $space;
        }, $spaces);

        $this->json([
            'success' => true,
            'spaces' => $spacesWithCounts,
            'pending_invitations' => $pendingInvitations,
        ]);
    }

    /**
     * GET /api/spaces/{id} — Détail d'un espace (dashboard).
     */
    public function show(string $id): void
    {
        $this->requireAuth();
        $ctx = $this->checkSpaceAccess((int) $id);

        $space = $this->spaceModel->findWithDetails((int) $id);
        $recentGames = (new Game())->getRecentBySpace((int) $id, 5);
        $members = $this->memberModel->findBySpace((int) $id);
        $playerCount = count((new Player())->findBySpace((int) $id));
        $gameTypeCount = count((new GameType())->findBySpace((int) $id));

        $this->json([
            'success' => true,
            'space' => $space,
            'role' => $ctx['member']['role'],
            'recent_games' => $recentGames,
            'members' => $members,
            'stats' => [
                'member_count' => count($members),
                'player_count' => $playerCount,
                'game_type_count' => $gameTypeCount,
            ],
        ]);
    }

    /**
     * POST /api/spaces — Créer un espace.
     * Body: { name, description? }
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('space_create');
        $data = $this->getJsonBody();

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
            $this->error('Le nom de l\'espace est requis.');
        }
        if (strlen($name) > 100) {
            $this->error('Le nom ne peut pas dépasser 100 caractères.');
        }

        $spaceId = $this->spaceModel->create([
            'name'        => $name,
            'description' => $description,
            'created_by'  => $this->userId,
        ]);

        $this->memberModel->addMember($spaceId, $this->userId, 'admin');

        ActivityLog::logSpace($spaceId, 'space.create', $this->userId, 'space', $spaceId, ['name' => $name]);

        $space = $this->spaceModel->find($spaceId);

        $this->json(['success' => true, 'space' => $space], 201);
    }

    /**
     * PUT /api/spaces/{id} — Modifier un espace.
     * Body: { name, description? }
     */
    public function update(string $id): void
    {
        $this->requireAuth();
        $ctx = $this->checkSpaceAccess((int) $id, ['admin']);

        $data = $this->getJsonBody();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            $this->error('Le nom de l\'espace est requis.');
        }

        $this->spaceModel->update((int) $id, [
            'name'        => $name,
            'description' => trim($data['description'] ?? ''),
        ]);

        ActivityLog::logSpace((int) $id, 'space.update', $this->userId, 'space', (int) $id, ['name' => $name]);

        $this->json(['success' => true, 'space' => $this->spaceModel->find((int) $id)]);
    }

    /**
     * DELETE /api/spaces/{id} — Supprimer un espace.
     */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin']);

        ActivityLog::logSpace((int) $id, 'space.delete', $this->userId, 'space', (int) $id);
        $this->spaceModel->delete((int) $id);

        $this->json(['success' => true, 'message' => 'Espace supprimé.']);
    }

    /**
     * POST /api/spaces/{id}/leave — Quitter un espace.
     * POST /api/spaces/{id}/invite-link — Génère un lien d'invitation par token.
     * Accessible aux admins et managers.
     */
    public function createInviteLink(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'members');

        $token = $this->inviteModel->createInvite((int) $id, $this->userId, 72);

        ActivityLog::logSpace((int) $id, 'invite.create_link', $this->userId, 'space', (int) $id);

        $this->json([
            'success'    => true,
            'token'      => $token,
            'expires_in' => '72h',
        ], 201);
    }

    /**
     * POST /api/spaces/{id}/leave — Quitter un espace.
     */
    public function leave(string $id): void
    {
        $this->requireAuth();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->error('Espace introuvable.', 404);
        }

        if ($space['created_by'] == $this->userId) {
            $this->error('Le créateur ne peut pas quitter son propre espace.');
        }

        $member = $this->memberModel->findMember((int) $id, $this->userId);
        if (!$member) {
            $this->error('Vous n\'êtes pas membre de cet espace.');
        }

        ActivityLog::logSpace((int) $id, 'member.leave', $this->userId, 'user', $this->userId);
        $this->memberModel->delete($member['id']);

        $this->json(['success' => true, 'message' => 'Vous avez quitté l\'espace.']);
    }

    // ─── Membres ─────────────────────────────────────────────

    /**
     * GET /api/spaces/{id}/members — Liste des membres.
     */
    public function members(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $members = $this->memberModel->findBySpace((int) $id);
        $members = array_map(static function (array $member): array {
            $restrictions = json_decode((string) ($member['user_restrictions'] ?? ''), true);
            $member['games_participation_restricted'] = is_array($restrictions) && !empty($restrictions['games_participation']);
            unset($member['user_restrictions']);
            return $member;
        }, $members);
        $pendingInvitations = $this->invitationModel->findPendingForSpace((int) $id);

        $this->json([
            'success' => true,
            'members' => $members,
            'pending_invitations' => $pendingInvitations,
        ]);
    }

    /**
     * POST /api/spaces/{id}/members — Inviter un membre (par username).
     * Body: { username, role? }
     */
    public function addMember(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'members');

        $data = $this->getJsonBody();
        $username = trim($data['username'] ?? '');
        $role = $data['role'] ?? 'member';

        if (empty($username)) {
            $this->error('Le nom d\'utilisateur est requis.');
        }

        $userModel = new User();
        $user = $userModel->findByUsername($username);
        if (!$user) {
            $this->error('Utilisateur introuvable.');
        }

        if ($userModel->isRestricted((int) $user['id'], 'space_join')) {
            $this->error('Cet utilisateur ne peut pas être ajouté à un espace pour le moment.', 403);
        }

        if ($this->memberModel->isMember((int) $id, $user['id'])) {
            $this->error('Cet utilisateur est déjà membre.');
        }

        if ($this->invitationModel->hasPendingInvite((int) $id, $user['id'])) {
            $this->error('Une invitation est déjà en attente pour cet utilisateur.');
        }

        $this->invitationModel->invite((int) $id, $user['id'], $this->userId, $role);

        ActivityLog::logSpace((int) $id, 'member.invite', $this->userId, 'user', $user['id'], ['username' => $username, 'role' => $role]);

        $this->json(['success' => true, 'message' => 'Invitation envoyée à ' . $username . '.'], 201);
    }

    /**
     * PUT /api/spaces/{id}/members/{mid}/role — Modifier le rôle d'un membre.
     * Body: { role }
     */
    public function updateMemberRole(string $id, string $mid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin']);

        $data = $this->getJsonBody();
        $role = $data['role'] ?? 'member';

        $this->memberModel->updateRole((int) $mid, $role);

        ActivityLog::logSpace((int) $id, 'member.role_update', $this->userId, 'space_member', (int) $mid, ['role' => $role]);

        $this->json(['success' => true, 'message' => 'Rôle mis à jour.']);
    }

    /**
     * DELETE /api/spaces/{id}/members/{mid} — Retirer un membre.
     */
    public function removeMember(string $id, string $mid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin']);

        $space = $this->spaceModel->find((int) $id);
        $memberToRemove = $this->memberModel->find((int) $mid);

        if ($memberToRemove && $memberToRemove['user_id'] == $space['created_by']) {
            $this->error('Impossible de retirer le créateur de l\'espace.');
        }

        ActivityLog::logSpace((int) $id, 'member.remove', $this->userId, 'user', (int) ($memberToRemove['user_id'] ?? $mid));
        $this->memberModel->delete((int) $mid);

        $this->json(['success' => true, 'message' => 'Membre retiré.']);
    }

    // ─── Invitations ─────────────────────────────────────────

    /**
     * POST /api/invitations/{invId}/accept — Accepter une invitation.
     */
    public function acceptInvitation(string $invId): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('space_join');

        $invitation = $this->invitationModel->find((int) $invId);
        if (!$invitation || $invitation['invited_user_id'] != $this->userId || $invitation['status'] !== 'pending') {
            $this->error('Invitation introuvable ou déjà traitée.', 404);
        }

        $this->invitationModel->accept((int) $invId);
        $this->memberModel->addMember($invitation['space_id'], $this->userId, $invitation['role']);

        ActivityLog::logSpace($invitation['space_id'], 'member.accept_invite', $this->userId, 'space_invitation', (int) $invId);

        $this->json(['success' => true, 'message' => 'Vous avez rejoint l\'espace !', 'space_id' => $invitation['space_id']]);
    }

    /**
     * POST /api/invitations/{invId}/decline — Refuser une invitation.
     */
    public function declineInvitation(string $invId): void
    {
        $this->requireAuth();

        $invitation = $this->invitationModel->find((int) $invId);
        if (!$invitation || $invitation['invited_user_id'] != $this->userId || $invitation['status'] !== 'pending') {
            $this->error('Invitation introuvable ou déjà traitée.', 404);
        }

        $this->invitationModel->decline((int) $invId);

        ActivityLog::logSpace($invitation['space_id'], 'member.decline_invite', $this->userId, 'space_invitation', (int) $invId);

        $this->json(['success' => true, 'message' => 'Invitation refusée.']);
    }

    /**
     * POST /api/spaces/join/{token} — Rejoindre via lien d'invitation.
     */
    public function join(string $token): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('space_join');

        $invite = $this->inviteModel->findValidByToken($token);
        if (!$invite) {
            $this->error('Lien d\'invitation invalide ou expiré.', 404);
        }

        $spaceId = $invite['space_id'];

        if ($this->memberModel->isMember($spaceId, $this->userId)) {
            $this->json(['success' => true, 'message' => 'Déjà membre.', 'space_id' => $spaceId]);
            return;
        }

        $this->memberModel->addMember($spaceId, $this->userId, 'member');
        ActivityLog::logSpace($spaceId, 'member.join', $this->userId, 'user', $this->userId);

        $this->json(['success' => true, 'message' => 'Espace rejoint !', 'space_id' => $spaceId]);
    }
}
