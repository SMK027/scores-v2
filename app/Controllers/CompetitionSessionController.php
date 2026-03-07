<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CSRF;
use App\Models\CompetitionSession;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameType;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\RoundPause;
use App\Config\Database;

/**
 * Contrôleur pour l'interface arbitre des sessions de compétition.
 * Pas d'authentification utilisateur requise – authentification par session/mot de passe.
 */
class CompetitionSessionController extends Controller
{
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

        // Revérifier que la session est toujours active
        $session = $this->sessionModel->find($data['session_id']);
        if (!$session || !$session['is_active']) {
            Session::remove('competition_session');
            $this->setFlash('warning', 'Cette session a été désactivée.');
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

        $session = $this->sessionModel->authenticate($competitionId, $sessionNumber, $password);
        if (!$session) {
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

        $this->redirect('/competition/dashboard');
    }

    /**
     * Déconnexion de session.
     */
    public function logout(): void
    {
        Session::remove('competition_session');
        $this->setFlash('success', 'Session terminée.');
        $this->redirect('/competition/login');
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
        $gameTypes = $this->gameType->findBy(['space_id' => $data['space_id']], 'name');

        // Joueurs de l'espace
        $players = $this->player->findBy(['space_id' => $data['space_id']], 'name');

        $this->renderMinimal('competitions/session_dashboard', [
            'title'     => 'Session #' . $data['session_number'],
            'session'   => $data,
            'games'     => $games,
            'gameTypes' => $gameTypes,
            'players'   => $players,
        ]);
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
        $playerIds  = $_POST['player_ids'] ?? [];
        $notes      = trim($_POST['notes'] ?? '');

        if ($gameTypeId <= 0 || empty($playerIds)) {
            $this->setFlash('danger', 'Le type de jeu et les joueurs sont requis.');
            $this->redirect('/competition/dashboard');
            return;
        }

        // Vérifier que le type de jeu appartient bien à l'espace
        $gameType = $this->gameType->find($gameTypeId);
        if (!$gameType || (int) $gameType['space_id'] !== $data['space_id']) {
            $this->setFlash('danger', 'Type de jeu invalide.');
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

        $this->gamePlayer->addPlayers($gameId, $playerIds);

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
