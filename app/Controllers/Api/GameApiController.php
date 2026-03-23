<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameType;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\RoundPause;
use App\Models\Comment;
use App\Models\ActivityLog;
use App\Models\User;
use App\Core\Middleware;

/**
 * API REST pour la gestion des parties et manches.
 */
class GameApiController extends ApiController
{
    private Game $gameModel;
    private GamePlayer $gamePlayerModel;
    private GameType $gameTypeModel;
    private Player $playerModel;

    public function __construct()
    {
        $this->gameModel = new Game();
        $this->gamePlayerModel = new GamePlayer();
        $this->gameTypeModel = new GameType();
        $this->playerModel = new Player();
    }

    private function isCompetitionProtected(array $game): bool
    {
        return !empty($game['competition_id']) &&
            ($this->userPayload['global_role'] ?? 'user') === 'user';
    }

    // ─── Parties ─────────────────────────────────────────────

    /**
     * GET /api/spaces/{id}/games?page=1&status=&game_type_id=
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'status'       => $_GET['status'] ?? '',
            'game_type_id' => $_GET['game_type_id'] ?? '',
        ];

        $result = $this->gameModel->findBySpace((int) $id, $page, 15, $filters);

        $this->json(['success' => true, ...$result]);
    }

    /**
     * GET /api/spaces/{id}/games/{gid} — Détail complet d'une partie.
     */
    public function show(string $id, string $gid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $game = $this->gameModel->findWithDetails((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }

        $gamePlayers = $this->gamePlayerModel->findByGame((int) $gid);

        $roundModel = new Round();
        $rounds = $roundModel->findByGame((int) $gid);

        $roundScoreModel = new RoundScore();
        $roundScores = [];
        foreach ($rounds as $round) {
            $roundScores[$round['id']] = $roundScoreModel->findByRoundIndexed($round['id']);
        }

        // Durées
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

        $userModel = new User();
        $canComment = $this->userId
            ? !$userModel->isRestricted((int) $this->userId, 'comments_manage')
            : false;

        $this->json([
            'success'          => true,
            'game'             => $game,
            'players'          => $gamePlayers,
            'rounds'           => $rounds,
            'round_scores'     => $roundScores,
            'round_durations'  => $roundDurations,
            'total_play_seconds' => $totalPlaySeconds,
            'comments'         => $comments,
            'can_comment'      => $canComment,
        ]);
    }

    /**
     * POST /api/spaces/{id}/games — Créer une partie.
     * Body: { game_type_id, player_ids: [], notes? }
     */
    public function create(string $id): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);
        $this->checkSpaceRestriction((int) $id, 'games');

        $data = $this->getJsonBody();
        $gameTypeId = (int) ($data['game_type_id'] ?? 0);
        $playerIds = $data['player_ids'] ?? [];
        $notes = trim($data['notes'] ?? '');

        if (empty($gameTypeId)) {
            $this->error('Le type de jeu est requis.');
        }

        $gameType = $this->gameTypeModel->find($gameTypeId);
        if (!$gameType || (int) $gameType['space_id'] !== (int) $id) {
            $this->error('Type de jeu invalide.');
        }

        $playerCount = count($playerIds);
        $minPlayers = (int) ($gameType['min_players'] ?? 1);
        $maxPlayers = $gameType['max_players'] ? (int) $gameType['max_players'] : null;

        if ($playerCount < $minPlayers) {
            $this->error("Ce type de jeu nécessite au minimum {$minPlayers} joueur(s).");
        }
        if ($maxPlayers !== null && $playerCount > $maxPlayers) {
            $this->error("Ce type de jeu autorise au maximum {$maxPlayers} joueur(s).");
        }
        if (count($playerIds) !== count(array_unique($playerIds))) {
            $this->error('Un joueur ne peut pas être ajouté deux fois.');
        }

        // Filtrer/valider la restriction de participation des comptes liés.
        $userModel = new User();
        foreach ($playerIds as $playerId) {
            $player = $this->playerModel->find((int) $playerId);
            if (
                !$player
                || (int) $player['space_id'] !== (int) $id
                || !empty($player['deleted_at'])
            ) {
                $this->error('Un ou plusieurs joueurs sont invalides pour cet espace.');
            }

            $linkedUserId = isset($player['user_id']) ? (int) $player['user_id'] : 0;
            if ($linkedUserId > 0 && $userModel->isRestricted($linkedUserId, 'games_participation')) {
                $this->error('Un joueur selectionne ne peut pas participer aux parties.', 403);
            }
        }

        $gameId = $this->gameModel->create([
            'space_id'     => (int) $id,
            'game_type_id' => $gameTypeId,
            'status'       => 'in_progress',
            'started_at'   => date('Y-m-d H:i:s'),
            'notes'        => $notes,
            'created_by'   => $this->userId,
        ]);

        try {
            $this->gamePlayerModel->addPlayers($gameId, $playerIds);
        } catch (\DomainException $e) {
            $this->gameModel->delete((int) $gameId);
            $this->error($e->getMessage(), 422);
        }

        ActivityLog::logSpace((int) $id, 'game.create', $this->userId, 'game', $gameId, ['game_type' => $gameType['name'], 'players' => $playerCount]);

        $this->json([
            'success' => true,
            'game'    => $this->gameModel->findWithDetails($gameId),
        ], 201);
    }

    /**
     * PUT /api/spaces/{id}/games/{gid} — Modifier les notes d'une partie.
     * Body: { notes }
     */
    public function update(string $id, string $gid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);
        $this->checkSpaceRestriction((int) $id, 'games');

        $game = $this->gameModel->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }

        $data = $this->getJsonBody();
        $this->gameModel->update((int) $gid, ['notes' => trim($data['notes'] ?? '')]);

        ActivityLog::logSpace((int) $id, 'game.update', $this->userId, 'game', (int) $gid);

        $this->json(['success' => true, 'game' => $this->gameModel->findWithDetails((int) $gid)]);
    }

    /**
     * DELETE /api/spaces/{id}/games/{gid}
     */
    public function delete(string $id, string $gid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'games');

        $game = $this->gameModel->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }

        ActivityLog::logSpace((int) $id, 'game.delete', $this->userId, 'game', (int) $gid);
        $this->gameModel->delete((int) $gid);

        $this->json(['success' => true, 'message' => 'Partie supprimée.']);
    }

    /**
     * PUT /api/spaces/{id}/games/{gid}/status
     * Body: { status: pending|in_progress|paused|completed }
     */
    public function updateStatus(string $id, string $gid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);

        $game = $this->gameModel->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }

        $data = $this->getJsonBody();
        $status = $data['status'] ?? '';
        $validStatuses = ['pending', 'in_progress', 'paused', 'completed'];

        if (!in_array($status, $validStatuses, true)) {
            $this->error('Statut invalide.');
        }

        $updateData = ['status' => $status];
        if ($status === 'completed') {
            $updateData['ended_at'] = date('Y-m-d H:i:s');
            $this->gameModel->recalculateTotals((int) $gid);
        } elseif ($status === 'in_progress') {
            $updateData['started_at'] = $game['started_at'] ?? date('Y-m-d H:i:s');
        }

        $this->gameModel->update((int) $gid, $updateData);

        ActivityLog::logSpace((int) $id, 'game.status_change', $this->userId, 'game', (int) $gid, ['status' => $status]);

        $this->json(['success' => true, 'game' => $this->gameModel->findWithDetails((int) $gid)]);
    }

    // ─── Commentaires ────────────────────────────────────────

    /**
     * POST /api/spaces/{id}/games/{gid}/comments
     * Body: { content }
     */
    public function addComment(string $id, string $gid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('comments_manage');
        $this->checkSpaceAccess((int) $id);

        $data = $this->getJsonBody();
        $content = trim($data['content'] ?? '');

        if (empty($content)) {
            $this->error('Le commentaire ne peut pas être vide.');
        }

        $commentModel = new Comment();
        $commentId = $commentModel->create([
            'game_id' => (int) $gid,
            'user_id' => $this->userId,
            'content' => $content,
        ]);

        ActivityLog::logSpace((int) $id, 'game.comment_add', $this->userId, 'game', (int) $gid);

        $this->json(['success' => true, 'comment_id' => $commentId], 201);
    }

    /**
     * DELETE /api/spaces/{id}/games/{gid}/comments/{cid}
     */
    public function deleteComment(string $id, string $gid, string $cid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('comments_manage');
        $this->checkSpaceAccess((int) $id);

        $commentModel = new Comment();
        $comment = $commentModel->find((int) $cid);

        $globalRole = $this->userPayload['global_role'] ?? 'user';
        $isStaff = in_array($globalRole, ['admin', 'superadmin', 'moderator'], true);

        if (!$comment || ($comment['user_id'] != $this->userId && !$isStaff)) {
            $this->error('Non autorisé.', 403);
        }

        ActivityLog::logSpace((int) $id, 'game.comment_delete', $this->userId, 'comment', (int) $cid);
        $commentModel->delete((int) $cid);

        $this->json(['success' => true, 'message' => 'Commentaire supprimé.']);
    }

    // ─── Manches ─────────────────────────────────────────────

    /**
     * POST /api/spaces/{id}/games/{gid}/rounds
     * Body: { notes? }
     */
    public function createRound(string $id, string $gid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);

        $game = $this->gameModel->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }
        if (!in_array($game['status'], ['in_progress', 'paused'])) {
            $this->error('Impossible d\'ajouter une manche à cette partie.');
        }

        $data = $this->getJsonBody();
        $roundModel = new Round();
        $roundId = $roundModel->createForGame((int) $gid, trim($data['notes'] ?? '') ?: null);

        ActivityLog::logSpace((int) $id, 'round.create', $this->userId, 'game', (int) $gid);

        $this->json(['success' => true, 'round' => $roundModel->find($roundId)], 201);
    }

    /**
     * PUT /api/spaces/{id}/games/{gid}/rounds/{rid}/scores
     * Body: { scores: { player_id: score_value, ... } }
     */
    public function updateScores(string $id, string $gid, string $rid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceRestriction((int) $id, 'games');
        $ctx = $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);

        $game = $this->gameModel->findWithDetails((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }

        $roundModel = new Round();
        $round = $roundModel->find((int) $rid);
        if (!$round || $round['game_id'] != $gid) {
            $this->error('Manche introuvable.', 404);
        }

        $isCompleted = $round['status'] === 'completed';
        $spaceRole = $ctx['member']['role'];

        if ($isCompleted && !in_array($spaceRole, ['admin', 'manager'])) {
            $this->error('Cette manche est déjà terminée.');
        }

        $data = $this->getJsonBody();
        $scores = $data['scores'] ?? [];

        if (!empty($scores) || $game['win_condition'] === 'win_loss') {
            $roundScoreModel = new RoundScore();
            $roundPauseModel = new RoundPause();

            $roundScoreModel->saveScores((int) $rid, $scores, $game['win_condition'], (int) $gid);

            if (!$isCompleted) {
                $roundPauseModel->endAllOpenPauses((int) $rid);
                $roundModel->updateStatus((int) $rid, 'completed');
            }

            $this->gameModel->recalculateTotals((int) $gid);
        }

        $msg = $isCompleted ? 'Scores corrigés.' : 'Scores enregistrés, manche terminée.';
        $action = $isCompleted ? 'round.scores_corrected' : 'round.scores_saved';
        ActivityLog::logSpace((int) $id, $action, $this->userId, 'round', (int) $rid, ['game_id' => (int) $gid]);

        $this->json(['success' => true, 'message' => $msg]);
    }

    /**
     * PUT /api/spaces/{id}/games/{gid}/rounds/{rid}/status
     * Body: { status: in_progress|paused|completed }
     */
    public function updateRoundStatus(string $id, string $gid, string $rid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager', 'member']);

        $game = $this->gameModel->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }

        $roundModel = new Round();
        $round = $roundModel->find((int) $rid);
        if (!$round || $round['game_id'] != $gid) {
            $this->error('Manche introuvable.', 404);
        }

        $data = $this->getJsonBody();
        $status = $data['status'] ?? '';
        $allowed = ['in_progress', 'paused', 'completed'];

        if (!in_array($status, $allowed, true)) {
            $this->error('Statut invalide.');
        }

        $roundPauseModel = new RoundPause();

        if ($status === 'paused' && $round['status'] === 'in_progress') {
            $roundPauseModel->startPause((int) $rid);
        } elseif ($status === 'in_progress' && $round['status'] === 'paused') {
            $roundPauseModel->endPause((int) $rid);
        } elseif ($status === 'completed') {
            $roundPauseModel->endAllOpenPauses((int) $rid);
        }

        $roundModel->updateStatus((int) $rid, $status);

        if ($status === 'completed') {
            $this->gameModel->recalculateTotals((int) $gid);
        }

        ActivityLog::logSpace((int) $id, 'round.status_change', $this->userId, 'round', (int) $rid, ['status' => $status, 'game_id' => (int) $gid]);

        $this->json(['success' => true, 'round' => $roundModel->find((int) $rid)]);
    }

    /**
     * DELETE /api/spaces/{id}/games/{gid}/rounds/{rid}
     */
    public function deleteRound(string $id, string $gid, string $rid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('games_manage');
        $this->checkSpaceRestriction((int) $id, 'games');
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

        $game = $this->gameModel->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->error('Partie introuvable.', 404);
        }
        if ($this->isCompetitionProtected($game)) {
            $this->error('Partie de compétition protégée.', 403);
        }

        $roundModel = new Round();
        $round = $roundModel->find((int) $rid);
        if (!$round || $round['game_id'] != $gid) {
            $this->error('Manche introuvable.', 404);
        }

        $roundModel->deleteWithScores((int) $rid);
        $roundModel->renumberRounds((int) $gid);
        $this->gameModel->recalculateTotals((int) $gid);

        ActivityLog::logSpace((int) $id, 'round.delete', $this->userId, 'round', (int) $rid, ['game_id' => (int) $gid]);

        $this->json(['success' => true, 'message' => 'Manche supprimée.']);
    }
}
