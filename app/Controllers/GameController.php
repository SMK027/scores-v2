<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameType;
use App\Models\Player;
use App\Models\Space;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\RoundPause;
use App\Models\Comment;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * Contrôleur des parties.
 */
class GameController extends Controller
{
    private Game $gameModel;
    private GamePlayer $gamePlayerModel;
    private GameType $gameTypeModel;
    private Player $playerModel;
    private Space $spaceModel;

    public function __construct()
    {
        $this->gameModel = new Game();
        $this->gamePlayerModel = new GamePlayer();
        $this->gameTypeModel = new GameType();
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
     * Vérifie si une partie de compétition est protégée pour l'utilisateur courant.
     * Retourne true si la partie est liée à une compétition ET l'utilisateur n'est pas staff global.
     */
    private function isCompetitionProtected(array $game): bool
    {
        return !empty($game['competition_id']) && !Middleware::isGlobalStaff();
    }

    /**
     * Retourne les IDs de joueurs liés à des comptes restreints pour la participation aux parties.
     */
    private function getRestrictedGamePlayerIds(array $players): array
    {
        if (empty($players)) {
            return [];
        }

        $userModel = new User();
        $restricted = [];
        foreach ($players as $player) {
            $userId = (int) ($player['user_id'] ?? 0);
            if ($userId > 0 && $userModel->isRestricted($userId, 'games_participation')) {
                $restricted[] = (int) $player['id'];
            }
        }

        return $restricted;
    }

    /**
     * Liste les parties d'un espace.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);

        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'status'       => $_GET['status'] ?? '',
            'game_type_id' => $_GET['game_type_id'] ?? '',
        ];

        $result = $this->gameModel->findBySpace((int) $id, $page, 15, $filters);
        $gameTypes = $this->gameTypeModel->findBySpace((int) $id);

        $this->render('games/index', [
            'title'        => 'Parties',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'games',
            'games'        => $result['data'],
            'pagination'   => $result,
            'gameTypes'    => $gameTypes,
            'filters'      => $filters,
        ]);
    }

    /**
     * Formulaire de création de partie.
     */
    public function createForm(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkUserRestriction('games_manage', null, '/spaces/' . $id . '/games');
        $this->checkSpaceRestriction((int) $id, 'games');

        $gameTypes = $this->gameTypeModel->findBySpace((int) $id);
        $players = $this->playerModel->findBySpace((int) $id);
        $restrictedGamePlayerIds = $this->getRestrictedGamePlayerIds($players);

        if (empty($gameTypes)) {
            $this->setFlash('warning', 'Vous devez d\'abord créer un type de jeu.');
            $this->redirect("/spaces/{$id}/game-types/create");
        }

        if (empty($players)) {
            $this->setFlash('warning', 'Vous devez d\'abord ajouter des joueurs.');
            $this->redirect("/spaces/{$id}/players/create");
        }

        $this->render('games/create', [
            'title'        => 'Nouvelle partie',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'games',
            'gameTypes'    => $gameTypes,
            'players'      => $players,
            'restrictedGamePlayerIds' => $restrictedGamePlayerIds,
        ]);
    }

    /**
     * Traite la création de partie.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkUserRestriction('games_manage', null, '/spaces/' . $id . '/games');
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->validateCSRF();

        $data = $this->getPostData(['game_type_id', 'notes']);
        $playerIds = $_POST['player_ids'] ?? [];

        if (empty($data['game_type_id'])) {
            $this->setFlash('danger', 'Le type de jeu est requis.');
            $this->redirect("/spaces/{$id}/games/create");
        }

        // Récupérer le type de jeu pour valider le nombre de joueurs
        $gameType = $this->gameTypeModel->find((int) $data['game_type_id']);
        if (!$gameType || !$this->gameTypeModel->isAccessibleInSpace((int) $data['game_type_id'], (int) $id)) {
            $this->setFlash('danger', 'Type de jeu invalide.');
            $this->redirect("/spaces/{$id}/games/create");
        }

        $playerCount = count($playerIds);
        $minPlayers = (int) ($gameType['min_players'] ?? 1);
        $maxPlayers = $gameType['max_players'] ? (int) $gameType['max_players'] : null;

        if ($playerCount < $minPlayers) {
            $plural = $minPlayers > 1 ? 's' : '';
            $this->setFlash('danger', "Ce type de jeu nécessite au minimum {$minPlayers} joueur{$plural} ({$playerCount} sélectionné(s)).");
            $this->redirect("/spaces/{$id}/games/create");
        }

        if ($maxPlayers !== null && $playerCount > $maxPlayers) {
            $plural = $maxPlayers > 1 ? 's' : '';
            $this->setFlash('danger', "Ce type de jeu autorise au maximum {$maxPlayers} joueur{$plural} ({$playerCount} sélectionné(s)).");
            $this->redirect("/spaces/{$id}/games/create");
        }

        // Vérifier les doublons
        if (count($playerIds) !== count(array_unique($playerIds))) {
            $this->setFlash('danger', 'Un joueur ne peut pas être ajouté deux fois.');
            $this->redirect("/spaces/{$id}/games/create");
        }

        $gameId = $this->gameModel->create([
            'space_id'     => (int) $id,
            'game_type_id' => (int) $data['game_type_id'],
            'status'       => 'in_progress',
            'started_at'   => date('Y-m-d H:i:s'),
            'notes'        => $data['notes'],
            'created_by'   => $this->getCurrentUserId(),
        ]);

        try {
            $this->gamePlayerModel->addPlayers($gameId, $playerIds);
        } catch (\DomainException $e) {
            $this->gameModel->delete((int) $gameId);
            $this->setFlash('danger', $e->getMessage());
            $this->redirect("/spaces/{$id}/games/create");
            return;
        }

        ActivityLog::logSpace((int) $id, 'game.create', $this->getCurrentUserId(), 'game', $gameId, ['game_type' => $gameType['name'], 'players' => count($playerIds)]);

        $this->setFlash('success', 'Partie créée et lancée !');
        $this->redirect("/spaces/{$id}/games/{$gameId}");
    }

    /**
     * Affiche le détail d'une partie.
     */
    public function show(string $id, string $gid): void
    {
        $ctx = $this->checkAccess($id);

        $game = $this->gameModel->findWithDetails((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
        }

        $gamePlayers = $this->gamePlayerModel->findByGame((int) $gid);

        // Manches
        $roundModel = new Round();
        $rounds = $roundModel->findByGame((int) $gid);

        // Scores par manche
        $roundScoreModel = new RoundScore();
        $roundScores = [];
        foreach ($rounds as $round) {
            $roundScores[$round['id']] = $roundScoreModel->findByRoundIndexed($round['id']);
        }

        // Durées des manches (temps de jeu effectif)
        $roundPauseModel = new RoundPause();
        $roundIds = array_column($rounds, 'id');
        $pausesByRound = !empty($roundIds)
            ? $roundPauseModel->getTotalPauseSecondsByRounds($roundIds)
            : [];

        $roundDurations = [];
        $totalPlaySeconds = 0;
        foreach ($rounds as $round) {
            $pauseSec = $pausesByRound[(int) $round['id']] ?? 0;
            $playDuration = $roundModel->getPlayDurationSeconds($round, $pauseSec);
            $roundDurations[$round['id']] = [
                'raw'   => $roundModel->getRawDurationSeconds($round),
                'pause' => $pauseSec,
                'play'  => $playDuration,
            ];
            $totalPlaySeconds += $playDuration;
        }

        // Commentaires
        $commentModel = new Comment();
        $comments = $commentModel->findByGame((int) $gid);

        $isCompetitionGame = !empty($game['competition_id']);
        $isGlobalStaff = Middleware::isGlobalStaff();

        $this->render('games/show', [
            'title'            => $game['game_type_name'],
            'currentSpace'     => $ctx['space'],
            'spaceRole'        => $ctx['member']['role'],
            'activeMenu'       => 'games',
            'game'             => $game,
            'gamePlayers'      => $gamePlayers,
            'rounds'           => $rounds,
            'roundScores'      => $roundScores,
            'roundDurations'   => $roundDurations,
            'totalPlaySeconds' => $totalPlaySeconds,
            'comments'         => $comments,
            'isCompetitionGame' => $isCompetitionGame,
            'isGlobalStaff'     => $isGlobalStaff,
        ]);
    }

    /**
     * Formulaire d'édition de partie.
     */
    public function editForm(string $id, string $gid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkUserRestriction('games_manage', null, '/spaces/' . $id . '/games/' . $gid);
        $this->checkSpaceRestriction((int) $id, 'games');

        $game = $this->gameModel->findWithDetails((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
        }

        if ($this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $gameTypes = $this->gameTypeModel->findBySpace((int) $id);
        $players = $this->playerModel->findBySpace((int) $id);
        $gamePlayers = $this->gamePlayerModel->findByGame((int) $gid);

        $this->render('games/edit', [
            'title'        => 'Modifier la partie',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'games',
            'game'         => $game,
            'gameTypes'    => $gameTypes,
            'players'      => $players,
            'gamePlayers'  => $gamePlayers,
        ]);
    }

    /**
     * Traite la modification de la partie.
     */
    public function update(string $id, string $gid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkUserRestriction('games_manage', null, '/spaces/' . $id . '/games/' . $gid);
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->validateCSRF();

        $game = $this->gameModel->find((int) $gid);
        if ($game && $this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $data = $this->getPostData(['notes']);

        $data = $this->getPostData(['notes']);

        $this->gameModel->update((int) $gid, [
            'notes' => $data['notes'],
        ]);

        ActivityLog::logSpace((int) $id, 'game.update', $this->getCurrentUserId(), 'game', (int) $gid);

        $this->setFlash('success', 'Partie mise à jour.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Supprime une partie.
     */
    public function delete(string $id, string $gid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->checkUserRestriction('games_manage', null, '/spaces/' . $id . '/games/' . $gid);
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->validateCSRF();

        $game = $this->gameModel->find((int) $gid);
        if ($game && $this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être supprimées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        ActivityLog::logSpace((int) $id, 'game.delete', $this->getCurrentUserId(), 'game', (int) $gid);

        $this->gameModel->delete((int) $gid);
        $this->setFlash('success', 'Partie supprimée.');
        $this->redirect("/spaces/{$id}/games");
    }

    /**
     * Met à jour le statut d'une partie.
     */
    public function updateStatus(string $id, string $gid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->checkUserRestriction('games_manage', null, '/spaces/' . $id . '/games/' . $gid);
        $this->validateCSRF();

        $game = $this->gameModel->find((int) $gid);
        if ($game && $this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $status = $_POST['status'] ?? '';
        $validStatuses = ['pending', 'in_progress', 'paused', 'completed'];

        if (!in_array($status, $validStatuses, true)) {
            $this->setFlash('danger', 'Statut invalide.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
        }

        $updateData = ['status' => $status];

        if ($status === 'completed') {
            $updateData['ended_at'] = date('Y-m-d H:i:s');
            // Recalculer les totaux
            $this->gameModel->recalculateTotals((int) $gid);
        } elseif ($status === 'in_progress') {
            $updateData['started_at'] = $updateData['started_at'] ?? date('Y-m-d H:i:s');
        }

        $this->gameModel->update((int) $gid, $updateData);

        ActivityLog::logSpace((int) $id, 'game.status_change', $this->getCurrentUserId(), 'game', (int) $gid, ['status' => $status]);

        $this->setFlash('success', 'Statut mis à jour : ' . game_status_label($status));
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Ajoute un commentaire à une partie.
     */
    public function addComment(string $id, string $gid): void
    {
        $ctx = $this->checkAccess($id);
        $this->checkUserRestriction('comments_manage', null, '/spaces/' . $id . '/games/' . $gid);
        $this->validateCSRF();

        $content = trim($_POST['content'] ?? '');
        if (empty($content)) {
            $this->setFlash('danger', 'Le commentaire ne peut pas être vide.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
        }

        $commentModel = new Comment();
        $commentModel->create([
            'game_id' => (int) $gid,
            'user_id' => $this->getCurrentUserId(),
            'content' => $content,
        ]);

        ActivityLog::logSpace((int) $id, 'game.comment_add', $this->getCurrentUserId(), 'game', (int) $gid);

        $this->setFlash('success', 'Commentaire ajouté.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Supprime un commentaire.
     */
    public function deleteComment(string $id, string $gid, string $cid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('comments_manage', null, '/spaces/' . $id . '/games/' . $gid);
        $this->validateCSRF();

        $commentModel = new Comment();
        $comment = $commentModel->find((int) $cid);

        // Seul l'auteur ou un admin peut supprimer
        if ($comment && ($comment['user_id'] == $this->getCurrentUserId() || Middleware::isGlobalStaff())) {
            ActivityLog::logSpace((int) $id, 'game.comment_delete', $this->getCurrentUserId(), 'comment', (int) $cid);
            $commentModel->delete((int) $cid);
            $this->setFlash('success', 'Commentaire supprimé.');
        }

        $this->redirect("/spaces/{$id}/games/{$gid}");
    }
}
