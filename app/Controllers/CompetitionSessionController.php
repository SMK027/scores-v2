<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CSRF;
use App\Models\CompetitionSession;
use App\Models\Competition;
use App\Models\User;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameType;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\RoundPause;
use App\Config\Database;
use App\Models\ActivityLog;

/**
 * Contrôleur pour l'interface arbitre des sessions de compétition.
 * Pas d'authentification utilisateur requise – authentification par session/mot de passe.
 */
class CompetitionSessionController extends Controller
{
    private const REFEREE_PAUSE_MIN_MINUTES = 5;
    private const REFEREE_PAUSE_MAX_MINUTES = 30;
    private const REFEREE_PAUSE_DEFAULT_MINUTES = 15;

    private CompetitionSession $sessionModel;
    private Competition $competition;
    private Game $game;
    private GamePlayer $gamePlayer;
    private GameType $gameType;
    private Player $player;
    private Round $round;
    private RoundScore $roundScore;
    private RoundPause $roundPause;
    private \PDO $pdo;

    /**
     * Retourne les IDs de joueurs (dans un espace) liés à des comptes
     * restreints pour la participation aux parties/compétitions.
     */
    private function getRestrictedCompetitionPlayerIds(int $spaceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.user_id
            FROM players p
            WHERE p.space_id = :space_id
              AND p.user_id IS NOT NULL
        ");
        $stmt->execute(['space_id' => $spaceId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return [];
        }

        $userModel = new User();
        $restricted = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0 && (
                $userModel->isRestricted($uid, 'games_participation')
                || $userModel->isRestricted($uid, 'competitions_participation')
            )) {
                $restricted[] = (int) $row['id'];
            }
        }

        return $restricted;
    }

    public function __construct()
    {
        $this->sessionModel = new CompetitionSession();
        $this->competition  = new Competition();
        $this->game         = new Game();
        $this->gamePlayer   = new GamePlayer();
        $this->gameType     = new GameType();
        $this->player       = new Player();
        $this->round        = new Round();
        $this->roundScore   = new RoundScore();
        $this->roundPause   = new RoundPause();
        $this->pdo          = Database::getInstance()->getConnection();
    }

    /**
     * Retourne les données de session de compétition en cours, ou null.
     */
    private function getSessionData(): ?array
    {
        return Session::get('competition_session');
    }

    /**
     * Vérifie l'authentification de la session arbitre.
     */
    private function requireSession(): array
    {
        $data = $this->getSessionData();
        if (!$data) {
            $this->redirect('/competition/login');
            exit;
        }

        $sessionId = (int) $data['session_id'];
        $this->sessionModel->reactivateIfPauseExpired($sessionId);

        // Revérifier que la session est toujours active
        $session = $this->sessionModel->find($sessionId);
        if (!$session || !$session['is_active']) {
            if ($session && !empty($session['pause_until']) && strtotime((string) $session['pause_until']) > time()) {
                $remainingSeconds = max(1, strtotime((string) $session['pause_until']) - time());
                $this->renderMinimal('competitions/session_on_break', [
                    'title' => 'Session en pause',
                    'session' => $data,
                    'pauseUntil' => $session['pause_until'],
                    'remainingSeconds' => $remainingSeconds,
                ]);
                exit;
            }

            Session::remove('competition_session');
            $message = ($session && !empty($session['closed_at']))
                ? 'Cette session a été fermée définitivement.'
                : 'Cette session a été désactivée.';
            $this->setFlash('warning', $message);
            $this->redirect('/competition/login');
            exit;
        }

        // Vérifier que la compétition n'est pas en pause ou clôturée
        $competition = (new Competition())->find($data['competition_id']);
        if ($competition && $competition['status'] === 'paused') {
            $this->renderMinimal('competitions/session_paused', [
                'title'   => 'Compétition en pause',
                'session' => $data,
            ]);
            exit;
        }
        if ($competition && $competition['status'] === 'closed') {
            Session::remove('competition_session');
            $this->setFlash('warning', 'Cette compétition est clôturée.');
            $this->redirect('/competition/login');
            exit;
        }

        return $data;
    }

    // ================================================================
    // Connexion / Déconnexion
    // ================================================================

    /**
     * Formulaire de connexion arbitre.
     */
    public function loginForm(): void
    {
        $this->renderMinimal('competitions/session_login', [
            'title' => 'Connexion session',
        ]);
    }

    /**
     * Traite la connexion arbitre.
     */
    public function login(): void
    {
        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect('/competition/login');
            return;
        }

        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $sessionNumber = (int) ($_POST['session_number'] ?? 0);
        $password      = trim($_POST['password'] ?? '');

        if ($competitionId <= 0 || $sessionNumber <= 0 || $password === '') {
            $this->setFlash('danger', 'Tous les champs sont requis.');
            $this->redirect('/competition/login');
            return;
        }

        $connectedUserId = Session::get('user_id');
        if (!empty($connectedUserId)) {
            $userModel = new User();
            if ($userModel->isRestricted((int) $connectedUserId, 'competitions_participation')) {
                $user = $userModel->find((int) $connectedUserId);
                $reason = trim((string) ($user['restriction_reason'] ?? ''));
                $message = 'Votre compte ne peut pas participer aux compétitions pour le moment.';
                if ($reason !== '') {
                    $message .= ' Motif: ' . $reason;
                }
                $this->setFlash('danger', $message);
                $this->redirect('/competition/login');
                return;
            }
        }

        // Vérifier si la session existe et si elle est verrouillée
        $targetSession = $this->sessionModel->findByCompetitionAndNumber($competitionId, $sessionNumber);
        if ($targetSession) {
            $this->sessionModel->reactivateIfPauseExpired((int) $targetSession['id']);
            $targetSession = $this->sessionModel->findByCompetitionAndNumber($competitionId, $sessionNumber);
        }
        if ($targetSession && $targetSession['is_locked']) {
            $this->setFlash('danger', 'Cette session est verrouillée suite à trop de tentatives échouées. Contactez un membre de l\'équipe.');
            $this->redirect('/competition/login');
            return;
        }

        if ($targetSession && !empty($targetSession['closed_at'])) {
            $this->setFlash('danger', 'Cette session est fermée définitivement.');
            $this->redirect('/competition/login');
            return;
        }

        $session = $this->sessionModel->authenticate($competitionId, $sessionNumber, $password);
        if (!$session) {
            // Enregistrer la tentative échouée si la session existe
            if ($targetSession) {
                $ip = get_client_ip();
                $this->sessionModel->recordFailedAttempt((int) $targetSession['id'], $ip);
                // Verrouiller après 3 tentatives en 15 minutes
                $recentAttempts = $this->sessionModel->countRecentFailedAttempts((int) $targetSession['id'], 15);
                if ($recentAttempts >= 3) {
                    $this->sessionModel->lock((int) $targetSession['id']);
                    $this->setFlash('danger', 'Session verrouillée : trop de tentatives échouées. Contactez un membre de l\'équipe pour la réinitialiser.');
                    $this->redirect('/competition/login');
                    return;
                }
            }
            $this->setFlash('danger', 'Identifiants invalides ou session désactivée.');
            $this->redirect('/competition/login');
            return;
        }

        // Vérifier que la compétition est active
        if ($session['competition_status'] !== 'active') {
            $this->setFlash('warning', 'Cette compétition n\'est pas active.');
            $this->redirect('/competition/login');
            return;
        }

        Session::set('competition_session', [
            'session_id'     => (int) $session['id'],
            'competition_id' => (int) $session['competition_id'],
            'session_number' => (int) $session['session_number'],
            'referee_name'   => $session['referee_name'],
            'space_id'       => (int) $session['space_id'],
            'competition_name' => $session['competition_name'],
        ]);

        ActivityLog::logCompetition((int) $session['competition_id'], 'session.login', null, 'competition_session', (int) $session['id'], (int) $session['id'], ['referee' => $session['referee_name'], 'session_number' => (int) $session['session_number']]);

        $this->redirect('/competition/dashboard');
    }

    /**
     * Déconnexion de session.
     */
    public function logout(): void
    {
        $data = $this->getSessionData();
        if ($data) {
            ActivityLog::logCompetition($data['competition_id'], 'session.logout', null, 'competition_session', $data['session_id'], $data['session_id'], ['referee' => $data['referee_name']]);
        }
        Session::remove('competition_session');
        $this->setFlash('success', 'Session terminée.');
        $this->redirect('/competition/login');
    }

    /**
     * Ouvre une session d'arbitrage pour un compte arbitre assigné.
     */
    public function openAssignedSession(string $sid): void
    {
        $this->requireAuth();

        $sessionId = (int) $sid;
        if ($sessionId <= 0) {
            $this->setFlash('danger', 'Session invalide.');
            $this->redirect('/spaces');
            return;
        }

        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            $this->setFlash('danger', 'Session introuvable.');
            $this->redirect('/spaces');
            return;
        }

        if ((int) ($session['referee_user_id'] ?? 0) !== (int) $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous n\'êtes pas assigné à cette session.');
            $this->redirect('/spaces');
            return;
        }

        $this->sessionModel->reactivateIfPauseExpired((int) $session['id']);
        $session = $this->sessionModel->find((int) $session['id']) ?: $session;

        if (!empty($session['closed_at'])) {
            $this->setFlash('danger', 'Cette session est fermée définitivement.');
            $this->redirect('/spaces');
            return;
        }

        if ((int) ($session['is_active'] ?? 0) !== 1 || (int) ($session['is_locked'] ?? 0) === 1) {
            $this->setFlash('danger', 'Cette session est inactive ou verrouillée.');
            $this->redirect('/spaces');
            return;
        }

        $competition = $this->competition->find((int) $session['competition_id']);
        if (!$competition || !in_array((string) ($competition['status'] ?? ''), ['active', 'paused'], true)) {
            $this->setFlash('danger', 'Cette compétition n\'autorise pas l\'arbitrage actuellement.');
            $this->redirect('/spaces');
            return;
        }

        Session::set('competition_session', [
            'session_id'     => (int) $session['id'],
            'competition_id' => (int) $session['competition_id'],
            'session_number' => (int) $session['session_number'],
            'referee_name'   => (string) $session['referee_name'],
            'space_id'       => (int) $competition['space_id'],
            'competition_name' => (string) $competition['name'],
        ]);

        ActivityLog::logCompetition((int) $session['competition_id'], 'session.login.user', (int) $this->getCurrentUserId(), 'competition_session', (int) $session['id'], (int) $session['id']);

        $this->redirect('/competition/dashboard');
    }

    // ================================================================
    // Dashboard de la session
    // ================================================================

    /**
     * Tableau de bord de la session arbitre.
     */
    public function dashboard(): void
    {
        $data = $this->requireSession();

        // Parties de cette session
        $stmt = $this->pdo->prepare("
            SELECT g.*, gt.name AS game_type_name,
                   (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) AS player_count,
                   (SELECT COUNT(*) FROM rounds WHERE game_id = g.id) AS round_count
            FROM games g
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.session_id = :sid
            ORDER BY g.created_at DESC
        ");
        $stmt->execute(['sid' => $data['session_id']]);
        $games = $stmt->fetchAll();

        // Types de jeu de l'espace
        $gameTypes = $this->competition->getAllowedGameTypes((int) $data['competition_id']);

        // Joueurs inscrits à la compétition
        $players = $this->competition->getRegisteredPlayers((int) $data['competition_id']);
        $restrictedPlayerIds = $this->getRestrictedCompetitionPlayerIds((int) $data['space_id']);

        $this->renderMinimal('competitions/session_dashboard', [
            'title'     => 'Session #' . $data['session_number'],
            'session'   => $data,
            'games'     => $games,
            'gameTypes' => $gameTypes,
            'players'   => $players,
            'pauseDurationMinutes' => self::REFEREE_PAUSE_DEFAULT_MINUTES,
            'pauseMinMinutes' => self::REFEREE_PAUSE_MIN_MINUTES,
            'pauseMaxMinutes' => self::REFEREE_PAUSE_MAX_MINUTES,
            'restrictedCompetitionPlayerIds' => $restrictedPlayerIds,
        ]);
    }

    /**
     * Met la session arbitre en pause pendant une durée configurable.
     */
    public function pauseSession(): void
    {
        $data = $this->requireSession();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $rawMinutes = $_POST['pause_minutes'] ?? null;
        if (!is_string($rawMinutes) && !is_int($rawMinutes)) {
            $this->setFlash('danger', 'Durée de pause invalide.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $rawMinutes = trim((string) $rawMinutes);
        if ($rawMinutes === '' || !ctype_digit($rawMinutes)) {
            $this->setFlash('danger', 'Veuillez saisir une durée de pause en minutes.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $minutes = (int) $rawMinutes;
        if ($minutes > self::REFEREE_PAUSE_MAX_MINUTES) {
            $this->setFlash('warning', 'Pour une pause supérieure à 30 minutes, merci de contacter un membre de l\'équipe pour suspendre la session.');
            $this->redirect('/competition/dashboard');
            return;
        }
        if ($minutes < self::REFEREE_PAUSE_MIN_MINUTES) {
            $this->setFlash('danger', 'La durée de pause doit être comprise entre 5 et 30 minutes.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $this->sessionModel->pauseTemporarily((int) $data['session_id'], $minutes);

        ActivityLog::logCompetition(
            (int) $data['competition_id'],
            'session.pause.self',
            null,
            'competition_session',
            (int) $data['session_id'],
            (int) $data['session_id'],
            ['duration_minutes' => $minutes, 'referee' => $data['referee_name']]
        );

        $this->setFlash('info', 'Session mise en pause pour ' . $minutes . ' minute(s).');
        $this->redirect('/competition/dashboard');
    }

    /**
     * Ferme définitivement la session arbitre.
     */
    public function closeSession(): void
    {
        $data = $this->requireSession();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $this->sessionModel->closePermanently((int) $data['session_id']);

        ActivityLog::logCompetition(
            (int) $data['competition_id'],
            'session.close.self',
            null,
            'competition_session',
            (int) $data['session_id'],
            (int) $data['session_id'],
            ['referee' => $data['referee_name']]
        );

        Session::remove('competition_session');
        $this->setFlash('warning', 'Session fermée définitivement.');
        $this->redirect('/competition/login');
    }

    // ================================================================
    // Gestion des parties (via session arbitre)
    // ================================================================

    /**
     * Créer une partie depuis la session.
     */
    public function createGame(): void
    {
        $data = $this->requireSession();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $gameTypeId = (int) ($_POST['game_type_id'] ?? 0);
        $rawPlayerIds = $_POST['player_ids'] ?? [];
        $playerIds = array_values(array_unique(array_map('intval', (array) $rawPlayerIds)));
        $notes      = trim($_POST['notes'] ?? '');

        if ($gameTypeId <= 0 || empty($playerIds)) {
            $this->setFlash('danger', 'Le type de jeu et les joueurs sont requis.');
            $this->redirect('/competition/dashboard');
            return;
        }

        // Vérifier que le type de jeu est autorisé pour cette compétition
        $allowedTypeIds = $this->competition->getAllowedGameTypeIds((int) $data['competition_id']);
        if (!in_array($gameTypeId, $allowedTypeIds, true)) {
            $this->setFlash('danger', 'Type de jeu non autorisé pour cette compétition.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $gameType = $this->gameType->find($gameTypeId);
        if (!$gameType || (int) $gameType['space_id'] !== (int) $data['space_id']) {
            $this->setFlash('danger', 'Type de jeu invalide pour cet espace.');
            $this->redirect('/competition/dashboard');
            return;
        }

        // Valider le nombre de joueurs
        $playerCount = count($playerIds);
        $minPlayers = (int) ($gameType['min_players'] ?? 1);
        $maxPlayers = $gameType['max_players'] ? (int) $gameType['max_players'] : null;

        if ($playerCount < $minPlayers) {
            $this->setFlash('danger', "Ce jeu nécessite au minimum {$minPlayers} joueur(s).");
            $this->redirect('/competition/dashboard');
            return;
        }
        if ($maxPlayers !== null && $playerCount > $maxPlayers) {
            $this->setFlash('danger', "Ce jeu autorise au maximum {$maxPlayers} joueur(s).");
            $this->redirect('/competition/dashboard');
            return;
        }

        // Vérifier que tous les joueurs sélectionnés existent dans l'espace courant.
        $playerPlaceholders = implode(',', array_fill(0, count($playerIds), '?'));
        $playerCheckSql = "
            SELECT p.id, p.name, p.user_id
            FROM players p
            WHERE p.space_id = ?
              AND p.id IN ({$playerPlaceholders})
        ";
        $playerCheckStmt = $this->pdo->prepare($playerCheckSql);
        $playerCheckStmt->execute(array_merge([(int) $data['space_id']], $playerIds));
        $selectedPlayers = $playerCheckStmt->fetchAll();
        if (count($selectedPlayers) !== count($playerIds)) {
            $this->setFlash('danger', 'Sélection de joueurs invalide pour cette compétition.');
            $this->redirect('/competition/dashboard');
            return;
        }

        // Vérifier que tous les joueurs sont inscrits à la compétition.
        $registeredPlayers = $this->competition->getRegisteredPlayers((int) $data['competition_id']);
        $registeredIds = array_map(static fn(array $p): int => (int) $p['id'], $registeredPlayers);
        $registeredSet = array_flip($registeredIds);
        foreach ($playerIds as $pid) {
            if (!isset($registeredSet[(int) $pid])) {
                $this->setFlash('danger', 'Tous les joueurs doivent être inscrits à la compétition.');
                $this->redirect('/competition/dashboard');
                return;
            }
        }

        // Bloquer les joueurs liés à des comptes restreints pour les compétitions.
        $restrictedIds = $this->getRestrictedCompetitionPlayerIds((int) $data['space_id']);
        if (!empty($restrictedIds)) {
            $blocked = array_flip($restrictedIds);
            $restrictedSelected = array_values(array_filter($selectedPlayers, static fn(array $p): bool => !empty($blocked[(int) $p['id']])));
            if (!empty($restrictedSelected)) {
                $names = array_map(static fn(array $p): string => (string) $p['name'], $restrictedSelected);
                $this->setFlash('danger', 'Impossible de rattacher à une partie: ' . implode(', ', $names) . '.');
                $this->redirect('/competition/dashboard');
                return;
            }
        }

        $gameId = $this->game->create([
            'space_id'       => $data['space_id'],
            'competition_id' => $data['competition_id'],
            'session_id'     => $data['session_id'],
            'game_type_id'   => $gameTypeId,
            'status'         => 'in_progress',
            'started_at'     => date('Y-m-d H:i:s'),
            'notes'          => $notes ?: null,
            'created_by'     => $this->getCurrentUserId() ?? 1, // fallback si pas connecté en user
        ]);

        try {
            $this->gamePlayer->addPlayers($gameId, $playerIds);
        } catch (\DomainException $e) {
            // Nettoyer la partie créée si les joueurs sont refusés.
            $this->game->delete((int) $gameId);
            $this->setFlash('danger', $e->getMessage());
            $this->redirect('/competition/dashboard');
            return;
        }

        ActivityLog::logCompetition($data['competition_id'], 'session.game_create', null, 'game', $gameId, $data['session_id'], ['referee' => $data['referee_name']]);

        $this->setFlash('success', 'Partie créée.');
        $this->redirect("/competition/games/{$gameId}");
    }

    /**
     * Affiche une partie de la session.
     */
    public function showGame(string $gid): void
    {
        $data = $this->requireSession();

        $game = $this->game->findWithDetails((int) $gid);
        if (!$game || (int) $game['session_id'] !== $data['session_id']) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $gamePlayers = $this->gamePlayer->findByGame((int) $gid);
        $rounds      = $this->round->findByGame((int) $gid);

        $roundScores = [];
        foreach ($rounds as $round) {
            $roundScores[$round['id']] = $this->roundScore->findByRoundIndexed($round['id']);
        }

        $roundDurations = [];
        foreach ($rounds as $round) {
            $pauseModel = new RoundPause();
            $pauseSec = $pauseModel->getTotalPauseSeconds($round['id']);
            $roundDurations[$round['id']] = [
                'play'  => $this->round->getPlayDurationSeconds($round, $pauseSec),
                'pause' => $pauseSec,
            ];
        }

        $this->renderMinimal('competitions/session_game', [
            'title'          => 'Partie #' . $gid,
            'session'        => $data,
            'game'           => $game,
            'gamePlayers'    => $gamePlayers,
            'rounds'         => $rounds,
            'roundScores'    => $roundScores,
            'roundDurations' => $roundDurations,
        ]);
    }

    /**
     * Ajouter une manche.
     */
    public function createRound(string $gid): void
    {
        $data = $this->requireSession();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect("/competition/games/{$gid}");
            return;
        }

        $game = $this->game->find((int) $gid);
        if (!$game || (int) $game['session_id'] !== $data['session_id']) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $this->round->createForGame((int) $gid);

        ActivityLog::logCompetition($data['competition_id'], 'session.round_create', null, 'game', (int) $gid, $data['session_id']);

        $this->setFlash('success', 'Manche ajoutée.');
        $this->redirect("/competition/games/{$gid}");
    }

    /**
     * Enregistrer les scores d'une manche.
     */
    public function updateScores(string $gid, string $rid): void
    {
        $data = $this->requireSession();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect("/competition/games/{$gid}");
            return;
        }

        $game = $this->game->findWithDetails((int) $gid);
        if (!$game || (int) $game['session_id'] !== $data['session_id']) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $round = $this->round->find((int) $rid);
        if (!$round || (int) $round['game_id'] !== (int) $gid) {
            $this->setFlash('danger', 'Manche introuvable.');
            $this->redirect("/competition/games/{$gid}");
            return;
        }

        $scores = $_POST['scores'] ?? [];
        if (!empty($scores) || $game['win_condition'] === 'win_loss') {
            $this->roundScore->saveScores((int) $rid, $scores, $game['win_condition'], (int) $gid);

            if ($round['status'] !== 'completed') {
                $this->roundPause->endAllOpenPauses((int) $rid);
                $this->round->updateStatus((int) $rid, 'completed');
            }

            $this->game->recalculateTotals((int) $gid);
        }

        ActivityLog::logCompetition($data['competition_id'], 'session.scores_saved', null, 'round', (int) $rid, $data['session_id'], ['game_id' => (int) $gid]);

        $this->setFlash('success', 'Scores enregistrés, manche terminée.');
        $this->redirect("/competition/games/{$gid}");
    }

    /**
     * Terminer une partie.
     */
    public function completeGame(string $gid): void
    {
        $data = $this->requireSession();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->setFlash('danger', 'Token de sécurité invalide.');
            $this->redirect("/competition/games/{$gid}");
            return;
        }

        $game = $this->game->find((int) $gid);
        if (!$game || (int) $game['session_id'] !== $data['session_id']) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect('/competition/dashboard');
            return;
        }

        $this->game->update((int) $gid, [
            'status'   => 'completed',
            'ended_at' => date('Y-m-d H:i:s'),
        ]);
        $this->game->recalculateTotals((int) $gid);

        ActivityLog::logCompetition($data['competition_id'], 'session.game_complete', null, 'game', (int) $gid, $data['session_id']);

        $this->setFlash('success', 'Partie terminée.');
        $this->redirect('/competition/dashboard');
    }

    // ================================================================
    // Rendu minimal (sans layout principal)
    // ================================================================

    /**
     * Rend une vue avec un layout minimal (sans sidebar, sans navbar complète).
     */
    protected function renderMinimal(string $view, array $data = []): void
    {
        extract($data);

        ob_start();
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            ob_end_clean();
            throw new \RuntimeException("Vue introuvable : {$view}");
        }
        require $viewPath;
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layouts/competition.php';
    }
}
