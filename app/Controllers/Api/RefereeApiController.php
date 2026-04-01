<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\JWT;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameType;
use App\Models\MemberCard;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundPause;
use App\Models\RoundScore;
use App\Models\User;
use App\Models\ActivityLog;
use App\Config\Database;

/**
 * API REST pour l'interface arbitre mobile.
 *
 * Deux modes d'authentification :
 *  - Arbitre libre  : POST /api/referee/login (competition_id + session_number + password) → referee JWT
 *  - Compte lié     : POST /api/referee/open/{sid} (JWT utilisateur) → referee JWT
 *
 * Toutes les autres routes exigent un referee JWT (type="referee").
 */
class RefereeApiController extends ApiController
{
    private const PAUSE_MIN = 5;
    private const PAUSE_MAX = 30;

    private CompetitionSession $sessionModel;
    private Competition        $competitionModel;
    private Game               $gameModel;
    private GamePlayer         $gamePlayerModel;
    private GameType           $gameTypeModel;
    private Player             $playerModel;
    private Round              $roundModel;
    private RoundScore         $roundScoreModel;
    private RoundPause         $roundPauseModel;
    private \PDO               $pdo;

    public function __construct()
    {
        $this->sessionModel     = new CompetitionSession();
        $this->competitionModel = new Competition();
        $this->gameModel        = new Game();
        $this->gamePlayerModel  = new GamePlayer();
        $this->gameTypeModel    = new GameType();
        $this->playerModel      = new Player();
        $this->roundModel       = new Round();
        $this->roundScoreModel  = new RoundScore();
        $this->roundPauseModel  = new RoundPause();
        $this->pdo              = Database::getInstance()->getConnection();
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Exige un referee JWT valide et retourne le payload + recharge la session depuis la BDD.
     * En cas de pause ou session inactive, retourne une erreur 403 avec les détails.
     */
    private function requireRefereeAuth(): array
    {
        $raw = $this->getBearerToken();
        if (!$raw) {
            $this->error('Token d\'arbitre requis.', 401);
        }

        $payload = JWT::decode($raw);
        if (!$payload || ($payload['type'] ?? '') !== 'referee' || empty($payload['session_id'])) {
            $this->error('Token arbitre invalide ou expiré.', 401);
        }

        $sessionId = (int) $payload['session_id'];
        $this->sessionModel->reactivateIfPauseExpired($sessionId);
        $session = $this->sessionModel->find($sessionId);

        if (!$session) {
            $this->error('Session introuvable.', 404);
        }

        if (!empty($session['closed_at'])) {
            $this->json(['success' => false, 'message' => 'Session fermée définitivement.', 'closed' => true], 403);
        }

        if (!$session['is_active']) {
            $pauseUntil = $session['pause_until'] ?? null;
            if ($pauseUntil && strtotime((string) $pauseUntil) > time()) {
                $remaining = max(1, strtotime((string) $pauseUntil) - time());
                $this->json([
                    'success'          => false,
                    'message'          => 'Session en pause.',
                    'paused'           => true,
                    'pause_until'      => $pauseUntil,
                    'remaining_seconds' => $remaining,
                ], 403);
            }
            $this->error('Session inactive ou verrouillée.', 403);
        }

        // Vérifier que la compétition n'est pas clôturée
        $competition = $this->competitionModel->find((int) $payload['competition_id']);
        if ($competition && $competition['status'] === 'closed') {
            $this->error('La compétition est clôturée.', 403);
        }
        if ($competition && $competition['status'] === 'paused') {
            $this->json(['success' => false, 'message' => 'La compétition est en pause.', 'competition_paused' => true], 403);
        }

        return $payload;
    }

    /**
     * Crée un referee JWT à partir d'une session db + compétition.
     */
    private function buildRefereeToken(array $session, array $competition): string
    {
        return JWT::encode([
            'type'             => 'referee',
            'session_id'       => (int) $session['id'],
            'competition_id'   => (int) $session['competition_id'],
            'space_id'         => (int) $competition['space_id'],
            'referee_name'     => (string) $session['referee_name'],
            'session_number'   => (int) $session['session_number'],
            'referee_user_id'  => !empty($session['referee_user_id']) ? (int) $session['referee_user_id'] : null,
        ], 86400 * 7); // 7 jours
    }

    /**
     * Formate les données d'une session pour la réponse API.
     */
    private function formatSession(array $session, array $competition): array
    {
        return [
            'session_id'       => (int) $session['id'],
            'session_number'   => (int) $session['session_number'],
            'competition_id'   => (int) $session['competition_id'],
            'competition_name' => (string) $competition['name'],
            'space_id'         => (int) $competition['space_id'],
            'referee_name'     => (string) $session['referee_name'],
            'is_active'        => (bool) $session['is_active'],
            'pause_until'      => $session['pause_until'] ?? null,
            'closed_at'        => $session['closed_at'] ?? null,
        ];
    }

    /**
     * Vérifie que la compétition est active pour l'arbitrage.
     */
    private function assertCompetitionOpen(array $competition): void
    {
        if (!in_array((string) $competition['status'], ['active', 'paused'], true)) {
            $this->error('Cette compétition n\'accepte pas l\'arbitrage actuellement.', 403);
        }
    }

    /**
     * Retourne les IDs de joueurs restreints pour les compétitions dans un espace.
     */
    private function getRestrictedPlayerIds(int $spaceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.user_id FROM players p
            WHERE p.space_id = :sid AND p.user_id IS NOT NULL AND p.deleted_at IS NULL
        ");
        $stmt->execute(['sid' => $spaceId]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return [];
        }

        $userModel = new User();
        $restricted = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0 && (
                $userModel->isRestricted($uid, 'games_participation') ||
                $userModel->isRestricted($uid, 'competitions_participation')
            )) {
                $restricted[] = (int) $row['id'];
            }
        }

        return $restricted;
    }

    // ================================================================
    // Authentification
    // ================================================================

    /**
     * POST /api/referee/login
     * Body : { competition_id, session_number, password }
     */
    public function login(): void
    {
        $body          = $this->getJsonBody();
        $competitionId = (int) ($body['competition_id'] ?? 0);
        $sessionNumber = (int) ($body['session_number'] ?? 0);
        $password      = trim((string) ($body['password'] ?? ''));

        if ($competitionId <= 0 || $sessionNumber <= 0 || $password === '') {
            $this->error('competition_id, session_number et password sont requis.', 422);
        }

        // Vérifier restrictions du compte utilisateur connecté, si fourni
        $userToken = $this->getBearerToken();
        if ($userToken) {
            $userPayload = JWT::decode($userToken);
            if ($userPayload && empty($userPayload['type']) && !empty($userPayload['user_id'])) {
                $userModel = new User();
                $userId = (int) $userPayload['user_id'];
                if ($userModel->isRestricted($userId, 'arbitration_access')) {
                    $this->error('Votre compte n\'est pas autorisé à accéder à l\'arbitrage.', 403);
                }
            }
        }

        // Pré-vérification de la session (locks, closed_at)
        $targetSession = $this->sessionModel->findByCompetitionAndNumber($competitionId, $sessionNumber);
        if ($targetSession) {
            $this->sessionModel->reactivateIfPauseExpired((int) $targetSession['id']);
            $targetSession = $this->sessionModel->findByCompetitionAndNumber($competitionId, $sessionNumber);
        }

        if ($targetSession && !empty($targetSession['closed_at'])) {
            $this->error('Cette session est fermée définitivement.', 403);
        }

        if ($targetSession && $targetSession['is_locked']) {
            $this->error('Session verrouillée après trop de tentatives. Contactez l\'équipe organisatrice.', 403);
        }

        $session = $this->sessionModel->authenticate($competitionId, $sessionNumber, $password);

        if (!$session) {
            if ($targetSession) {
                $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
                $this->sessionModel->recordFailedAttempt((int) $targetSession['id'], $ip);
                $recent = $this->sessionModel->countRecentFailedAttempts((int) $targetSession['id'], 15);
                if ($recent >= 3) {
                    $this->sessionModel->lock((int) $targetSession['id']);
                    $this->error('Session verrouillée : trop de tentatives. Contactez l\'équipe organisatrice.', 403);
                }
            }
            $this->error('Identifiants invalides ou session désactivée.', 401);
        }

        if ((string) ($session['competition_status'] ?? '') !== 'active') {
            $this->error('Cette compétition n\'est pas actuellement active.', 403);
        }

        $competition = $this->competitionModel->find($competitionId);
        if (!$competition) {
            $this->error('Compétition introuvable.', 404);
        }

        $token = $this->buildRefereeToken($session, $competition);

        ActivityLog::logCompetition(
            $competitionId,
            'session.login.api',
            null,
            'competition_session',
            (int) $session['id'],
            (int) $session['id'],
            ['referee' => $session['referee_name'], 'via' => 'mobile']
        );

        $this->json([
            'success' => true,
            'token'   => $token,
            'session' => $this->formatSession($session, $competition),
        ]);
    }

    /**
     * POST /api/referee/open/{sid}
     * Ouvre une session assignée à un compte utilisateur (JWT utilisateur requis).
     */
    public function openAssigned(string $sid): void
    {
        $this->requireAuth();

        $userModel = new User();
        if ($userModel->isRestricted((int) $this->userId, 'arbitration_access')) {
            $this->error('Votre compte n\'est pas autorisé à accéder à l\'arbitrage.', 403);
        }

        $sessionId = (int) $sid;
        if ($sessionId <= 0) {
            $this->error('ID de session invalide.', 422);
        }

        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            $this->error('Session introuvable.', 404);
        }

        if ((int) ($session['referee_user_id'] ?? 0) !== (int) $this->userId) {
            $this->error('Vous n\'êtes pas assigné à cette session.', 403);
        }

        $this->sessionModel->reactivateIfPauseExpired($sessionId);
        $session = $this->sessionModel->find($sessionId) ?: $session;

        if (!empty($session['closed_at'])) {
            $this->error('Session fermée définitivement.', 403);
        }

        if (!(bool) $session['is_active'] || (bool) $session['is_locked']) {
            $this->error('Session inactive ou verrouillée.', 403);
        }

        $competition = $this->competitionModel->find((int) $session['competition_id']);
        if (!$competition) {
            $this->error('Compétition introuvable.', 404);
        }

        $this->assertCompetitionOpen($competition);

        $token = $this->buildRefereeToken($session, $competition);

        ActivityLog::logCompetition(
            (int) $session['competition_id'],
            'session.login.user.api',
            (int) $this->userId,
            'competition_session',
            $sessionId,
            $sessionId,
            ['via' => 'mobile']
        );

        $this->json([
            'success' => true,
            'token'   => $token,
            'session' => $this->formatSession($session, $competition),
        ]);
    }

    /**
     * GET /api/referee/assigned
     * Retourne les sessions assignées au compte utilisateur connecté (JWT utilisateur).
     */
    public function assigned(): void
    {
        $this->requireAuth();

        $stmt = $this->pdo->prepare("
            SELECT cs.id, cs.competition_id, cs.session_number, cs.referee_name,
                   cs.is_active, cs.is_locked, cs.pause_until, cs.closed_at,
                   c.name  AS competition_name,
                   c.status AS competition_status,
                   c.space_id,
                   s.name  AS space_name,
                   (SELECT COUNT(*) FROM games g WHERE g.session_id = cs.id) AS game_count
            FROM competition_sessions cs
            JOIN competitions c ON c.id = cs.competition_id
            JOIN spaces s       ON s.id = c.space_id
            WHERE cs.referee_user_id = :uid
              AND cs.closed_at IS NULL
            ORDER BY c.starts_at DESC, cs.session_number ASC
        ");
        $stmt->execute(['uid' => $this->userId]);
        $rows = $stmt->fetchAll();

        $sessions = array_map(static function (array $row): array {
            return [
                'session_id'         => (int) $row['id'],
                'session_number'     => (int) $row['session_number'],
                'competition_id'     => (int) $row['competition_id'],
                'competition_name'   => $row['competition_name'],
                'competition_status' => $row['competition_status'],
                'space_id'           => (int) $row['space_id'],
                'space_name'         => $row['space_name'],
                'referee_name'       => $row['referee_name'],
                'is_active'          => (bool) $row['is_active'],
                'is_locked'          => (bool) $row['is_locked'],
                'pause_until'        => $row['pause_until'] ?? null,
                'game_count'         => (int) $row['game_count'],
            ];
        }, $rows);

        $this->json(['success' => true, 'sessions' => $sessions]);
    }

    // ================================================================
    // Dashboard
    // ================================================================

    /**
     * GET /api/referee/dashboard
     */
    public function dashboard(): void
    {
        $payload = $this->requireRefereeAuth();

        $sessionId     = (int) $payload['session_id'];
        $competitionId = (int) $payload['competition_id'];
        $spaceId       = (int) $payload['space_id'];

        // Parties de la session
        $stmt = $this->pdo->prepare("
            SELECT g.id, g.game_type_id, g.status, g.notes, g.created_at,
                   gt.name AS game_type_name, gt.win_condition,
                   (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) AS player_count,
                   (SELECT COUNT(*) FROM rounds         WHERE game_id = g.id) AS round_count
            FROM games g
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.session_id = :sid
            ORDER BY g.created_at DESC
        ");
        $stmt->execute(['sid' => $sessionId]);
        $games = $stmt->fetchAll();

        // Types de jeu autorisés
        $gameTypes = $this->competitionModel->getAllowedGameTypes($competitionId);

        // Joueurs inscrits
        $players = $this->competitionModel->getRegisteredPlayers($competitionId);
        $restrictedIds = $this->getRestrictedPlayerIds($spaceId);

        $playersFormatted = array_map(static function (array $p) use ($restrictedIds): array {
            return [
                'id'               => (int) $p['id'],
                'name'             => $p['name'],
                'linked_username'  => $p['linked_username'] ?? null,
                'is_restricted'    => in_array((int) $p['id'], $restrictedIds, true),
            ];
        }, $players);

        // Session actuelle
        $session = $this->sessionModel->find($sessionId);
        $competition = $this->competitionModel->find($competitionId);

        $this->json([
            'success'    => true,
            'session'    => $this->formatSession($session, $competition),
            'games'      => array_values(array_map(static function (array $g): array {
                return [
                    'id'             => (int) $g['id'],
                    'game_type_id'   => (int) $g['game_type_id'],
                    'game_type_name' => $g['game_type_name'],
                    'win_condition'  => $g['win_condition'],
                    'status'         => $g['status'],
                    'notes'          => $g['notes'],
                    'created_at'     => $g['created_at'],
                    'player_count'   => (int) $g['player_count'],
                    'round_count'    => (int) $g['round_count'],
                ];
            }, $games)),
            'game_types' => array_values(array_map(static function (array $gt): array {
                return [
                    'id'            => (int) $gt['id'],
                    'name'          => $gt['name'],
                    'win_condition' => $gt['win_condition'],
                    'min_players'   => (int) ($gt['min_players'] ?? 1),
                    'max_players'   => $gt['max_players'] !== null ? (int) $gt['max_players'] : null,
                ];
            }, $gameTypes)),
            'players'    => $playersFormatted,
        ]);
    }

    // ================================================================
    // Gestion de la session
    // ================================================================

    /**
     * POST /api/referee/session/pause
     * Body : { minutes: 5-30 }
     */
    public function pauseSession(): void
    {
        $payload = $this->requireRefereeAuth();
        $body    = $this->getJsonBody();
        $minutes = (int) ($body['minutes'] ?? 0);

        if ($minutes < self::PAUSE_MIN || $minutes > self::PAUSE_MAX) {
            $this->error('La durée de pause doit être entre ' . self::PAUSE_MIN . ' et ' . self::PAUSE_MAX . ' minutes.', 422);
        }

        $sessionId = (int) $payload['session_id'];
        $this->sessionModel->pauseTemporarily($sessionId, $minutes);

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.pause.self',
            null,
            'competition_session',
            $sessionId,
            $sessionId,
            ['duration_minutes' => $minutes, 'referee' => $payload['referee_name'], 'via' => 'mobile']
        );

        $session = $this->sessionModel->find($sessionId);
        $this->json([
            'success'    => true,
            'message'    => "Session mise en pause pour {$minutes} minute(s).",
            'pause_until' => $session['pause_until'] ?? null,
        ]);
    }

    /**
     * POST /api/referee/session/close
     * Ferme définitivement la session.
     */
    public function closeSession(): void
    {
        $payload   = $this->requireRefereeAuth();
        $sessionId = (int) $payload['session_id'];

        $this->sessionModel->closePermanently($sessionId);

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.close.self',
            null,
            'competition_session',
            $sessionId,
            $sessionId,
            ['referee' => $payload['referee_name'], 'via' => 'mobile']
        );

        $this->json(['success' => true, 'message' => 'Session fermée définitivement.']);
    }

    // ================================================================
    // Vérification de carte de membre
    // ================================================================

    /**
     * POST /api/referee/participants/verify-card
     * Body : { player_id, reference }
     */
    public function verifyCard(): void
    {
        $payload = $this->requireRefereeAuth();
        $body    = $this->getJsonBody();

        $playerId  = (int) ($body['player_id'] ?? 0);
        $reference = strtoupper(trim((string) ($body['reference'] ?? '')));

        if ($playerId <= 0 || $reference === '') {
            $this->error('player_id et reference sont requis.', 422);
        }

        if (!$this->competitionModel->isPlayerRegistered((int) $payload['competition_id'], $playerId)) {
            $this->error('Ce joueur n\'est pas inscrit à cette compétition.', 404);
        }

        $cardModel = new MemberCard();
        $card = $cardModel->findByReference($reference);

        if (!$card || (int) ($card['space_id'] ?? 0) !== (int) $payload['space_id']) {
            $this->json(['success' => false, 'valid' => false, 'reason' => 'Aucune carte trouvée avec cette référence dans cet espace.']);
            return;
        }

        if ((int) ($card['player_id'] ?? 0) !== $playerId) {
            $this->json(['success' => false, 'valid' => false, 'reason' => 'Cette carte n\'appartient pas au compétiteur sélectionné.']);
            return;
        }

        $signatureValid = MemberCard::verify($card);
        if (!$signatureValid) {
            $this->json(['success' => false, 'valid' => false, 'reason' => 'Signature numérique invalide.']);
            return;
        }

        if ((int) $card['is_active'] !== 1) {
            $this->json(['success' => false, 'valid' => false, 'reason' => 'Cette carte est désactivée.']);
            return;
        }

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'competition.member_card.verify',
            null,
            'player',
            $playerId,
            (int) $payload['session_id'],
            ['reference' => $reference, 'referee' => $payload['referee_name'], 'via' => 'mobile']
        );

        $this->json([
            'success'  => true,
            'valid'    => true,
            'message'  => 'Carte valide — identité confirmée.',
            'card'     => [
                'player_name' => $card['player_name'] ?? null,
                'reference'   => $card['reference'],
                'is_active'   => (bool) $card['is_active'],
            ],
        ]);
    }

    // ================================================================
    // Parties
    // ================================================================

    /**
     * POST /api/referee/games
     * Body : { game_type_id, player_ids: number[], notes?: string }
     */
    public function createGame(): void
    {
        $payload = $this->requireRefereeAuth();
        $body    = $this->getJsonBody();

        $gameTypeId = (int) ($body['game_type_id'] ?? 0);
        $playerIds  = array_values(array_unique(array_map('intval', (array) ($body['player_ids'] ?? []))));
        $notes      = trim((string) ($body['notes'] ?? ''));

        if ($gameTypeId <= 0 || empty($playerIds)) {
            $this->error('game_type_id et player_ids sont requis.', 422);
        }

        $competitionId = (int) $payload['competition_id'];
        $spaceId       = (int) $payload['space_id'];
        $sessionId     = (int) $payload['session_id'];

        // Type de jeu autorisé ?
        $allowedTypeIds = $this->competitionModel->getAllowedGameTypeIds($competitionId);
        if (!in_array($gameTypeId, $allowedTypeIds, true)) {
            $this->error('Type de jeu non autorisé pour cette compétition.', 403);
        }

        $gameType = $this->gameTypeModel->find($gameTypeId);
        if (!$gameType || !$this->gameTypeModel->isAccessibleInSpace($gameTypeId, $spaceId)) {
            $this->error('Type de jeu invalide pour cet espace.', 404);
        }

        // Validation count joueurs
        $count      = count($playerIds);
        $minPlayers = (int) ($gameType['min_players'] ?? 1);
        $maxPlayers = $gameType['max_players'] !== null ? (int) $gameType['max_players'] : null;

        if ($count < $minPlayers) {
            $this->error("Ce jeu nécessite au minimum {$minPlayers} joueur(s).", 422);
        }
        if ($maxPlayers !== null && $count > $maxPlayers) {
            $this->error("Ce jeu autorise au maximum {$maxPlayers} joueur(s).", 422);
        }

        // Joueurs existent dans l'espace ?
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $checkStmt = $this->pdo->prepare("
            SELECT id, name, user_id FROM players
            WHERE space_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})
        ");
        $checkStmt->execute(array_merge([$spaceId], $playerIds));
        $selected = $checkStmt->fetchAll();
        if (count($selected) !== count($playerIds)) {
            $this->error('Sélection de joueurs invalide.', 422);
        }

        // Tous inscrits à la compétition ?
        $registered    = $this->competitionModel->getRegisteredPlayers($competitionId);
        $registeredSet = array_flip(array_map(static fn(array $p): int => (int) $p['id'], $registered));
        foreach ($playerIds as $pid) {
            if (!isset($registeredSet[$pid])) {
                $this->error('Tous les joueurs doivent être inscrits à la compétition.', 422);
            }
        }

        // Joueurs restreints ?
        $restrictedIds = $this->getRestrictedPlayerIds($spaceId);
        if (!empty($restrictedIds)) {
            $blocked = array_flip($restrictedIds);
            $restrictedSelected = array_filter($selected, static fn(array $p): bool => isset($blocked[(int) $p['id']]));
            if (!empty($restrictedSelected)) {
                $names = array_map(static fn(array $p): string => (string) $p['name'], $restrictedSelected);
                $this->error('Joueurs bloqués : ' . implode(', ', $names) . '.', 403);
            }
        }

        $gameId = $this->gameModel->create([
            'space_id'       => $spaceId,
            'competition_id' => $competitionId,
            'session_id'     => $sessionId,
            'game_type_id'   => $gameTypeId,
            'status'         => 'in_progress',
            'started_at'     => date('Y-m-d H:i:s'),
            'notes'          => $notes ?: null,
            'created_by'     => !empty($payload['referee_user_id']) ? (int) $payload['referee_user_id'] : null,
        ]);

        try {
            $this->gamePlayerModel->addPlayers($gameId, $playerIds);
        } catch (\DomainException $e) {
            $this->gameModel->delete($gameId);
            $this->error($e->getMessage(), 422);
        }

        ActivityLog::logCompetition($competitionId, 'session.game_create', null, 'game', $gameId, $sessionId, [
            'referee' => $payload['referee_name'],
            'via'     => 'mobile',
        ]);

        $game = $this->gameModel->findWithDetails($gameId);
        $this->json(['success' => true, 'game' => $this->formatGame($game, [])], 201);
    }

    /**
     * GET /api/referee/games/{gid}
     */
    public function showGame(string $gid): void
    {
        $payload = $this->requireRefereeAuth();
        $gameId  = (int) $gid;

        $game = $this->gameModel->findWithDetails($gameId);
        if (!$game || (int) $game['session_id'] !== (int) $payload['session_id']) {
            $this->error('Partie introuvable.', 404);
        }

        $players = $this->gamePlayerModel->findByGame($gameId);
        $rounds  = $this->roundModel->findByGame($gameId);

        $roundsWithScores = [];
        foreach ($rounds as $round) {
            $scores = $this->roundScoreModel->findByRoundIndexed((int) $round['id']);
            $pauseSec = $this->roundPauseModel->getTotalPauseSeconds((int) $round['id']);
            $roundsWithScores[] = [
                'id'           => (int) $round['id'],
                'round_number' => (int) $round['round_number'],
                'status'       => $round['status'],
                'started_at'   => $round['started_at'] ?? null,
                'ended_at'     => $round['ended_at'] ?? null,
                'play_seconds' => $this->roundModel->getPlayDurationSeconds($round, $pauseSec),
                'scores'       => array_values(array_map(static function (array $s): array {
                    return [
                        'player_id' => (int) $s['player_id'],
                        'score'     => $s['score'] !== null ? (float) $s['score'] : null,
                        'won'       => isset($s['won']) ? (bool) $s['won'] : null,
                        'rank'      => isset($s['rank']) ? (int) $s['rank'] : null,
                    ];
                }, $scores)),
            ];
        }

        $this->json([
            'success' => true,
            'game'    => $this->formatGame($game, $players, $roundsWithScores),
        ]);
    }

    /**
     * Formate un objet partie pour la réponse API.
     */
    private function formatGame(array $game, array $players, array $rounds = []): array
    {
        $base = [
            'id'             => (int) $game['id'],
            'game_type_id'   => (int) $game['game_type_id'],
            'game_type_name' => $game['game_type_name'] ?? null,
            'win_condition'  => $game['win_condition'] ?? null,
            'status'         => $game['status'],
            'notes'          => $game['notes'] ?? null,
            'created_at'     => $game['created_at'],
            'ended_at'       => $game['ended_at'] ?? null,
            'player_count'   => count($players),
            'round_count'    => count($rounds),
        ];

        if (!empty($players)) {
            $base['players'] = array_values(array_map(static function (array $p): array {
                return [
                    'player_id'   => (int) $p['player_id'],
                    'name'        => $p['name'],
                    'total_score' => $p['total_score'] !== null ? (float) $p['total_score'] : null,
                    'wins'        => isset($p['wins']) ? (int) $p['wins'] : null,
                    'rank'        => isset($p['rank']) ? (int) $p['rank'] : null,
                ];
            }, $players));
        }

        if (!empty($rounds)) {
            $base['rounds'] = $rounds;
        }

        return $base;
    }

    // ================================================================
    // Manches
    // ================================================================

    /**
     * POST /api/referee/games/{gid}/rounds
     * Ajoute une nouvelle manche.
     */
    public function createRound(string $gid): void
    {
        $payload = $this->requireRefereeAuth();
        $gameId  = (int) $gid;

        $game = $this->gameModel->find($gameId);
        if (!$game || (int) $game['session_id'] !== (int) $payload['session_id']) {
            $this->error('Partie introuvable.', 404);
        }

        if ((string) $game['status'] === 'completed') {
            $this->error('Cette partie est déjà terminée.', 422);
        }

        try {
            $roundId = $this->roundModel->createForGame($gameId);
        } catch (\DomainException $e) {
            $this->error($e->getMessage(), 422);
        }

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.round_create',
            null,
            'game',
            $gameId,
            (int) $payload['session_id'],
            ['via' => 'mobile']
        );

        $round = $this->roundModel->find($roundId);
        $this->json(['success' => true, 'round' => [
            'id'           => (int) $round['id'],
            'round_number' => (int) $round['round_number'],
            'status'       => $round['status'],
            'started_at'   => $round['started_at'] ?? null,
            'ended_at'     => $round['ended_at'] ?? null,
            'scores'       => [],
        ]], 201);
    }

    /**
     * POST /api/referee/games/{gid}/rounds/{rid}/status
     * Body : { status: "in_progress" | "paused" | "completed" }
     */
    public function updateRoundStatus(string $gid, string $rid): void
    {
        $payload = $this->requireRefereeAuth();
        $gameId  = (int) $gid;
        $roundId = (int) $rid;

        $game = $this->gameModel->find($gameId);
        if (!$game || (int) $game['session_id'] !== (int) $payload['session_id']) {
            $this->error('Partie introuvable.', 404);
        }

        $round = $this->roundModel->find($roundId);
        if (!$round || (int) $round['game_id'] !== $gameId) {
            $this->error('Manche introuvable.', 404);
        }

        $body   = $this->getJsonBody();
        $status = trim((string) ($body['status'] ?? ''));
        if (!in_array($status, ['in_progress', 'paused', 'completed'], true)) {
            $this->error('Statut invalide. Valeurs acceptées : in_progress, paused, completed.', 422);
        }

        if ($status === 'paused' && $round['status'] !== 'in_progress') {
            $this->error('Seule une manche en cours peut être mise en pause.', 422);
        }
        if ($status === 'in_progress' && $round['status'] !== 'paused') {
            $this->error('Seule une manche en pause peut être reprise.', 422);
        }

        if ($status === 'paused') {
            $this->roundPauseModel->startPause($roundId);
        } elseif ($status === 'in_progress') {
            $this->roundPauseModel->endPause($roundId);
        } elseif ($status === 'completed') {
            $this->roundPauseModel->endAllOpenPauses($roundId);
        }

        $this->roundModel->updateStatus($roundId, $status);
        if ($status === 'completed') {
            $this->gameModel->recalculateTotals($gameId);
        }

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.round_status_change',
            null,
            'round',
            $roundId,
            (int) $payload['session_id'],
            ['game_id' => $gameId, 'status' => $status, 'via' => 'mobile']
        );

        $round = $this->roundModel->find($roundId);
        $this->json(['success' => true, 'round' => [
            'id'           => (int) $round['id'],
            'round_number' => (int) $round['round_number'],
            'status'       => $round['status'],
            'started_at'   => $round['started_at'] ?? null,
            'ended_at'     => $round['ended_at'] ?? null,
        ]]);
    }

    /**
     * POST /api/referee/games/{gid}/rounds/{rid}/scores
     * Body : { scores: { [player_id]: score } }
     */
    public function updateScores(string $gid, string $rid): void
    {
        $payload = $this->requireRefereeAuth();
        $gameId  = (int) $gid;
        $roundId = (int) $rid;

        $game = $this->gameModel->findWithDetails($gameId);
        if (!$game || (int) $game['session_id'] !== (int) $payload['session_id']) {
            $this->error('Partie introuvable.', 404);
        }

        $round = $this->roundModel->find($roundId);
        if (!$round || (int) $round['game_id'] !== $gameId) {
            $this->error('Manche introuvable.', 404);
        }

        $body   = $this->getJsonBody();
        $scores = $body['scores'] ?? [];

        $this->roundScoreModel->saveScores($roundId, $scores, (string) $game['win_condition'], $gameId);

        if ($round['status'] !== 'completed') {
            $this->roundPauseModel->endAllOpenPauses($roundId);
            $this->roundModel->updateStatus($roundId, 'completed');
        }

        $this->gameModel->recalculateTotals($gameId);

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.scores_saved',
            null,
            'round',
            $roundId,
            (int) $payload['session_id'],
            ['game_id' => $gameId, 'via' => 'mobile']
        );

        $savedScores = $this->roundScoreModel->findByRoundIndexed($roundId);
        $this->json(['success' => true, 'scores' => array_values(array_map(static function (array $s): array {
            return [
                'player_id' => (int) $s['player_id'],
                'score'     => $s['score'] !== null ? (float) $s['score'] : null,
                'won'       => isset($s['won']) ? (bool) $s['won'] : null,
                'rank'      => isset($s['rank']) ? (int) $s['rank'] : null,
            ];
        }, $savedScores))]);
    }

    /**
     * DELETE /api/referee/games/{gid}/rounds/{rid}
     * Body : { reason: string }
     */
    public function deleteRound(string $gid, string $rid): void
    {
        $payload = $this->requireRefereeAuth();
        $gameId  = (int) $gid;
        $roundId = (int) $rid;

        $game = $this->gameModel->find($gameId);
        if (!$game || (int) $game['session_id'] !== (int) $payload['session_id']) {
            $this->error('Partie introuvable.', 404);
        }

        $round = $this->roundModel->find($roundId);
        if (!$round || (int) $round['game_id'] !== $gameId) {
            $this->error('Manche introuvable.', 404);
        }

        $body   = $this->getJsonBody();
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            $this->error('Un motif est obligatoire pour supprimer une manche.', 422);
        }

        $roundNumber = (int) ($round['round_number'] ?? 0);
        $this->roundPauseModel->endAllOpenPauses($roundId);
        $this->roundModel->deleteWithScores($roundId);
        $this->roundModel->renumberRounds($gameId);
        $this->gameModel->recalculateTotals($gameId);

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.round_delete',
            null,
            'round',
            $roundId,
            (int) $payload['session_id'],
            ['game_id' => $gameId, 'round_number' => $roundNumber, 'reason' => $reason, 'via' => 'mobile']
        );

        $this->json(['success' => true, 'message' => 'Manche supprimée.']);
    }

    // ================================================================
    // Finalisation de partie
    // ================================================================

    /**
     * POST /api/referee/games/{gid}/complete
     */
    public function completeGame(string $gid): void
    {
        $payload = $this->requireRefereeAuth();
        $gameId  = (int) $gid;

        $game = $this->gameModel->find($gameId);
        if (!$game || (int) $game['session_id'] !== (int) $payload['session_id']) {
            $this->error('Partie introuvable.', 404);
        }

        if ($game['status'] === 'completed') {
            $this->error('Cette partie est déjà terminée.', 422);
        }

        $this->gameModel->update($gameId, [
            'status'   => 'completed',
            'ended_at' => date('Y-m-d H:i:s'),
        ]);
        $this->gameModel->recalculateTotals($gameId);

        ActivityLog::logCompetition(
            (int) $payload['competition_id'],
            'session.game_complete',
            null,
            'game',
            $gameId,
            (int) $payload['session_id'],
            ['via' => 'mobile']
        );

        $this->json(['success' => true, 'message' => 'Partie terminée.']);
    }
}
