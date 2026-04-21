<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BotAI;
use App\Core\Controller;
use App\Core\Middleware;
use App\Core\Session;
use App\Models\InteractiveGameSession;
use App\Models\Lobby;
use App\Models\Notification;
use App\Models\Space;
use App\Models\SpaceMember;

/**
 * Contrôleur des lobbies (salons de jeu).
 */
class LobbyController extends Controller
{
    private Lobby $lobbyModel;
    private Space $spaceModel;
    private SpaceMember $memberModel;
    private InteractiveGameSession $sessionModel;

    public function __construct()
    {
        $this->lobbyModel   = new Lobby();
        $this->spaceModel   = new Space();
        $this->memberModel  = new SpaceMember();
        $this->sessionModel = new InteractiveGameSession();
    }

    /**
     * Vérifie l'accès à l'espace.
     */
    private function checkAccess(string $spaceId): array
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            exit;
        }
        $member = Middleware::checkSpaceAccess((int) $spaceId, $this->getCurrentUserId());
        if (!$member) {
            $this->setFlash('danger', 'Accès réservé aux membres de l\'espace.');
            $this->redirect('/spaces');
            exit;
        }
        return ['space' => $space, 'role' => $member['role']];
    }

    /**
     * Liste des lobbies d'un espace.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $lobbies = $this->lobbyModel->findBySpace((int) $id);
        $invitations = $this->lobbyModel->getInvitationsForUser((int) $id, $this->getCurrentUserId());

        $this->render('lobbies/index', [
            'title'         => 'Salons de jeu',
            'currentSpace'  => $ctx['space'],
            'spaceRole'     => $ctx['role'],
            'activeMenu'    => 'play',
            'lobbies'       => $lobbies,
            'invitations'   => $invitations,
            'games'         => InteractiveGameSession::GAMES,
            'grids'         => InteractiveGameSession::MORPION_GRIDS,
            'currentUserId' => $this->getCurrentUserId(),
        ]);
    }

    /**
     * Créer un lobby.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $name    = trim($_POST['name'] ?? '');
        $gameKey = trim($_POST['game_key'] ?? '');
        $visibility = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';

        if ($name === '' || strlen($name) > 100) {
            $this->setFlash('danger', 'Nom du salon requis (100 caractères max).');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        if (!isset(InteractiveGameSession::GAMES[$gameKey])) {
            $this->setFlash('danger', 'Jeu inconnu.');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        $game = InteractiveGameSession::GAMES[$gameKey];
        $maxPlayers = (int) ($_POST['max_players'] ?? $game['max_players']);
        $maxPlayers = max($game['min_players'], min($game['max_players'], $maxPlayers));

        $gameConfig = [
            'max_players' => $maxPlayers,
        ];

        if ($gameKey === 'morpion') {
            $gridSize = (int) ($_POST['grid_size'] ?? 3);
            if (!isset(InteractiveGameSession::MORPION_GRIDS[$gridSize])) {
                $gridSize = 3;
            }
            $grid = InteractiveGameSession::MORPION_GRIDS[$gridSize];
            $alignCount = (int) ($_POST['align_count'] ?? $grid['aligns'][0]);
            if (!in_array($alignCount, $grid['aligns'], true)) {
                $alignCount = $grid['aligns'][0];
            }
            $gameConfig['grid_size']   = $gridSize;
            $gameConfig['align_count'] = $alignCount;
        }

        $lobbyId = $this->lobbyModel->createLobby(
            (int) $id,
            $this->getCurrentUserId(),
            $name,
            $gameKey,
            $gameConfig,
            $visibility
        );

        $this->setFlash('success', 'Salon créé !');
        $this->redirect("/spaces/{$id}/lobbies/{$lobbyId}");
    }

    /**
     * Page d'un lobby.
     */
    public function show(string $id, string $lid): void
    {
        $ctx = $this->checkAccess($id);

        $lobby = $this->lobbyModel->findWithMembers((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Salon introuvable.');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        $spaceMembers = $this->memberModel->findBySpace((int) $id);

        $this->render('lobbies/show', [
            'title'         => $lobby['name'],
            'currentSpace'  => $ctx['space'],
            'spaceRole'     => $ctx['role'],
            'activeMenu'    => 'play',
            'lobby'         => $lobby,
            'games'         => InteractiveGameSession::GAMES,
            'grids'         => InteractiveGameSession::MORPION_GRIDS,
            'spaceMembers'  => $spaceMembers,
            'currentUserId' => $this->getCurrentUserId(),
        ]);
    }

    /**
     * Rejoindre un lobby public.
     */
    public function join(string $id, string $lid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $lobby = $this->lobbyModel->find((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id || $lobby['status'] !== 'open') {
            $this->setFlash('danger', 'Salon introuvable ou fermé.');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        if ($lobby['visibility'] !== 'public') {
            $this->setFlash('danger', 'Ce salon est privé, vous devez être invité.');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        $this->lobbyModel->addMember((int) $lid, $this->getCurrentUserId());

        // Notifier l'hôte qu'un joueur a rejoint
        $joinerName = Session::get('username') ?? 'Un joueur';
        if ((int) $lobby['created_by'] !== $this->getCurrentUserId()) {
            (new Notification())->createForUser(
                (int) $lobby['created_by'],
                Notification::TYPE_LOBBY_JOIN,
                '🎮 Nouveau joueur dans le salon',
                "{$joinerName} a rejoint le salon « {$lobby['name']} ».",
                "/spaces/{$id}/lobbies/{$lid}"
            );
        }

        $this->redirect("/spaces/{$id}/lobbies/{$lid}");
    }

    /**
     * Quitter un lobby.
     */
    public function leave(string $id, string $lid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $lobby = $this->lobbyModel->find((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id) {
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        // L'hôte ne peut pas quitter, il doit fermer
        if ((int) $lobby['created_by'] === $this->getCurrentUserId()) {
            $this->setFlash('warning', 'En tant qu\'hôte, vous devez fermer le salon au lieu de le quitter.');
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        $this->lobbyModel->removeMember((int) $lid, $this->getCurrentUserId());
        $this->setFlash('info', 'Vous avez quitté le salon.');
        $this->redirect("/spaces/{$id}/lobbies");
    }

    /**
     * Fermer un lobby (hôte uniquement).
     */
    public function close(string $id, string $lid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $ok = $this->lobbyModel->closeLobby((int) $lid, $this->getCurrentUserId());
        if ($ok) {
            $this->setFlash('info', 'Salon fermé.');
        } else {
            $this->setFlash('danger', 'Impossible de fermer ce salon.');
        }
        $this->redirect("/spaces/{$id}/lobbies");
    }

    /**
     * Inviter un utilisateur.
     */
    public function invite(string $id, string $lid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $lobby = $this->lobbyModel->find((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id || $lobby['status'] !== 'open') {
            $this->setFlash('danger', 'Salon introuvable ou fermé.');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        // Seul l'hôte peut inviter
        if ((int) $lobby['created_by'] !== $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Seul l\'hôte peut inviter des joueurs.');
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        $invitedUserId = (int) ($_POST['user_id'] ?? 0);
        if ($invitedUserId <= 0) {
            $this->setFlash('danger', 'Utilisateur invalide.');
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        // Vérifier que l'invité est membre de l'espace
        if (!$this->memberModel->isMember((int) $id, $invitedUserId)) {
            $this->setFlash('danger', 'Cet utilisateur n\'est pas membre de l\'espace.');
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        $ok = $this->lobbyModel->invite((int) $lid, $invitedUserId, $this->getCurrentUserId());
        if ($ok) {
            // Notifier l'utilisateur invité
            $hostName = Session::get('username') ?? 'L\'hôte';
            (new Notification())->createForUser(
                $invitedUserId,
                Notification::TYPE_LOBBY_INVITE,
                '📩 Invitation à rejoindre un salon',
                "{$hostName} vous invite à rejoindre le salon « {$lobby['name']} ».",
                "/spaces/{$id}/lobbies"
            );
            $this->setFlash('success', 'Invitation envoyée !');
        } else {
            $this->setFlash('warning', 'Cet utilisateur est déjà invité ou membre.');
        }
        $this->redirect("/spaces/{$id}/lobbies/{$lid}");
    }

    /**
     * Accepter une invitation.
     */
    public function acceptInvite(string $id, string $invId): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        // Récupérer les détails avant d'accepter pour la notification
        $invData = $this->lobbyModel->findInvitationById((int) $invId);

        $ok = $this->lobbyModel->acceptInvitation((int) $invId, $this->getCurrentUserId());
        if ($ok) {
            // Notifier l'hôte que l'invitation a été acceptée
            if ($invData && (int) $invData['lobby_host_id'] !== $this->getCurrentUserId()) {
                $accepterName = Session::get('username') ?? 'Un joueur';
                (new Notification())->createForUser(
                    (int) $invData['lobby_host_id'],
                    Notification::TYPE_LOBBY_INVITE_ACCEPTED,
                    '✅ Invitation acceptée',
                    "{$accepterName} a accepté votre invitation pour le salon « {$invData['lobby_name']} ».",
                    "/spaces/{$id}/lobbies/" . $invData['lobby_id']
                );
            }
            $this->setFlash('success', 'Invitation acceptée !');
        } else {
            $this->setFlash('danger', 'Invitation introuvable ou expirée.');
        }
        $this->redirect("/spaces/{$id}/lobbies");
    }

    /**
     * Décliner une invitation.
     */
    public function declineInvite(string $id, string $invId): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $this->lobbyModel->declineInvitation((int) $invId, $this->getCurrentUserId());
        $this->redirect("/spaces/{$id}/lobbies");
    }

    /**
     * Lancer la partie (hôte uniquement).
     */
    public function launch(string $id, string $lid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $lobby = $this->lobbyModel->findWithMembers((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id || $lobby['status'] !== 'open') {
            $this->setFlash('danger', 'Salon introuvable ou déjà en jeu.');
            $this->redirect("/spaces/{$id}/lobbies");
            return;
        }

        if ((int) $lobby['created_by'] !== $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Seul l\'hôte peut lancer la partie.');
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        $gameKey = $lobby['game_key'];
        $config  = $lobby['game_config'];
        $members = $lobby['members'];
        $maxPlayers = $config['max_players'] ?? 2;

        $game = InteractiveGameSession::GAMES[$gameKey] ?? null;
        if (!$game) {
            $this->setFlash('danger', 'Jeu invalide.');
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        // Vérifier le nombre de joueurs
        $memberCount = count($members);
        if ($memberCount < $game['min_players']) {
            $this->setFlash('warning', "Il faut au moins {$game['min_players']} joueurs pour lancer la partie.");
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }
        if ($memberCount > $maxPlayers) {
            $this->setFlash('warning', "Maximum {$maxPlayers} joueurs pour cette configuration.");
            $this->redirect("/spaces/{$id}/lobbies/{$lid}");
            return;
        }

        // Vérifier qu'aucun membre n'a de partie active
        foreach ($members as $m) {
            $active = $this->sessionModel->hasActiveSession((int) $id, (int) $m['user_id']);
            if ($active) {
                $this->setFlash('warning', e($m['username']) . " a déjà une partie en cours. Attendez qu'elle se termine.");
                $this->redirect("/spaces/{$id}/lobbies/{$lid}");
                return;
            }
        }

        $gridSize   = $config['grid_size'] ?? 3;
        $alignCount = $config['align_count'] ?? $gridSize;

        // Créer la session pour l'hôte
        $sessionId = $this->sessionModel->createSession(
            (int) $id,
            $gameKey,
            $this->getCurrentUserId(),
            $memberCount,
            false,
            null,
            $gridSize,
            $alignCount,
            (int) $lid
        );

        // Inscrire les autres membres
        foreach ($members as $m) {
            if ((int) $m['user_id'] === $this->getCurrentUserId()) {
                continue;
            }
            $this->sessionModel->joinSession($sessionId, (int) $m['user_id']);
        }

        // Mettre le lobby en mode « en jeu »
        $this->lobbyModel->setInGame((int) $lid, $sessionId);

        // Notifier tous les membres du lobby (sauf l'hôte qui a lancé)
        $notifModel = new Notification();
        foreach ($members as $m) {
            if ((int) $m['user_id'] === $this->getCurrentUserId()) {
                continue;
            }
            $notifModel->createForUser(
                (int) $m['user_id'],
                Notification::TYPE_LOBBY_LAUNCH,
                '🚀 La partie commence !',
                "L'hôte a lancé la partie dans le salon « {$lobby['name']} ». À toi de jouer !",
                "/spaces/{$id}/play/{$sessionId}"
            );
        }

        $this->redirect("/spaces/{$id}/play/{$sessionId}");
    }

    /**
     * Retour au lobby après une partie.
     */
    public function returnToLobby(string $id, string $sid): void
    {
        $ctx = $this->checkAccess($id);

        $session = $this->sessionModel->find((int) $sid);
        if (!$session || !(int) $session['lobby_id']) {
            $this->setFlash('danger', 'Aucun salon associé.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        $lobbyId = (int) $session['lobby_id'];
        $lobby = $this->lobbyModel->find($lobbyId);

        // Si le lobby est en mode in_game et la session est terminée, repasser en open
        if ($lobby && $lobby['status'] === 'in_game' && in_array($session['status'], ['completed', 'cancelled'])) {
            $this->lobbyModel->setOpen($lobbyId);
        }

        $this->redirect("/spaces/{$id}/lobbies/{$lobbyId}");
    }

    /**
     * Recherche AJAX de membres invitables.
     */
    public function searchMembers(string $id, string $lid): void
    {
        $this->checkAccess($id);

        $lobby = $this->lobbyModel->find((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id) {
            $this->json(['results' => []], 404);
            return;
        }

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json(['results' => []]);
            return;
        }

        $results = $this->lobbyModel->searchInvitableMembers((int) $lid, (int) $id, $q);
        $this->json(['results' => $results]);
    }

    /**
     * Polling : état du lobby (membres, statut...).
     */
    public function state(string $id, string $lid): void
    {
        $this->checkAccess($id);

        $lobby = $this->lobbyModel->findWithMembers((int) $lid);
        if (!$lobby || (int) $lobby['space_id'] !== (int) $id) {
            $this->json(['error' => 'Lobby introuvable'], 404);
            return;
        }

        $this->json([
            'status'             => $lobby['status'],
            'member_count'       => count($lobby['members']),
            'members'            => array_map(fn($m) => [
                'user_id'  => (int) $m['user_id'],
                'username' => $m['username'],
                'avatar'   => $m['avatar'],
            ], $lobby['members']),
            'current_session_id' => $lobby['current_session_id'] ? (int) $lobby['current_session_id'] : null,
            'invitations'        => array_map(fn($i) => [
                'id'       => (int) $i['id'],
                'username' => $i['invited_username'],
            ], $lobby['invitations']),
        ]);
    }
}
