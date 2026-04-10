<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BotAI;
use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameType;
use App\Models\InteractiveGameSession;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\Space;

/**
 * Contrôleur des jeux interactifs jouables en ligne.
 */
class InteractiveGameController extends Controller
{
    private InteractiveGameSession $sessionModel;
    private Space $spaceModel;
    private Game $gameModel;
    private GamePlayer $gamePlayerModel;
    private Player $playerModel;
    private Round $roundModel;
    private RoundScore $roundScoreModel;
    private GameType $gameTypeModel;

    public function __construct()
    {
        $this->sessionModel     = new InteractiveGameSession();
        $this->spaceModel       = new Space();
        $this->gameModel        = new Game();
        $this->gamePlayerModel  = new GamePlayer();
        $this->playerModel      = new Player();
        $this->roundModel       = new Round();
        $this->roundScoreModel  = new RoundScore();
        $this->gameTypeModel    = new GameType();
    }

    /**
     * Vérifie l'accès à l'espace (tout membre).
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
     * Retrouve les infos d'un joueur dans une session.
     */
    private function getPlayerInfo(array $session, int $userId): ?array
    {
        foreach ($session['players'] as $p) {
            if ((int) $p['user_id'] === $userId) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Lobby : liste des jeux disponibles et sessions actives.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $sessions = $this->sessionModel->findBySpace((int) $id);

        $this->render('interactive_games/index', [
            'title'        => 'Jeux en ligne',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['role'],
            'activeMenu'   => 'play',
            'games'        => InteractiveGameSession::GAMES,
            'sessions'     => $sessions,
            'currentUserId' => $this->getCurrentUserId(),
        ]);
    }

    /**
     * Créer une nouvelle session de jeu.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        // Vérifier si le joueur a déjà une partie active
        $active = $this->sessionModel->hasActiveSession((int) $id, $this->getCurrentUserId());
        if ($active) {
            $gameName = InteractiveGameSession::GAMES[$active['game_key']]['name'] ?? $active['game_key'];
            $this->setFlash('warning', "Vous avez déjà une partie de {$gameName} en cours. Terminez-la ou annulez-la avant d'en créer une nouvelle.");
            $this->redirect("/spaces/{$id}/play/{$active['id']}");
            return;
        }

        $gameKey = trim($_POST['game_key'] ?? '');
        if (!isset(InteractiveGameSession::GAMES[$gameKey])) {
            $this->setFlash('danger', 'Jeu inconnu.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        $game = InteractiveGameSession::GAMES[$gameKey];
        $maxPlayers = (int) ($_POST['max_players'] ?? $game['max_players']);
        $maxPlayers = max($game['min_players'], min($game['max_players'], $maxPlayers));
        $vsBot = !empty($_POST['vs_bot']);

        $botDifficulty = null;
        if ($vsBot) {
            $botDifficulty = $_POST['bot_difficulty'] ?? BotAI::DIFFICULTY_MEDIUM;
            if (!isset(BotAI::DIFFICULTIES[$botDifficulty])) {
                $botDifficulty = BotAI::DIFFICULTY_MEDIUM;
            }
        }

        $gridSize = 3;
        $alignCount = 3;
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
        }

        $sessionId = $this->sessionModel->createSession(
            (int) $id,
            $gameKey,
            $this->getCurrentUserId(),
            $maxPlayers,
            $vsBot,
            $botDifficulty,
            $gridSize,
            $alignCount
        );

        $this->redirect("/spaces/{$id}/play/{$sessionId}");
    }

    /**
     * Rejoindre une session existante.
     */
    public function join(string $id, string $sid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        // Vérifier si le joueur a déjà une partie active
        $active = $this->sessionModel->hasActiveSession((int) $id, $this->getCurrentUserId());
        if ($active && (int) $active['id'] !== (int) $sid) {
            $gameName = InteractiveGameSession::GAMES[$active['game_key']]['name'] ?? $active['game_key'];
            $this->setFlash('warning', "Vous avez déjà une partie de {$gameName} en cours. Terminez-la ou annulez-la avant d'en rejoindre une autre.");
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        $session = $this->sessionModel->findWithPlayers((int) $sid);
        if (!$session || (int) $session['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Session introuvable.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        if ($session['status'] !== 'waiting') {
            $this->setFlash('warning', 'Cette partie a déjà commencé ou est terminée.');
            $this->redirect("/spaces/{$id}/play/{$sid}");
            return;
        }

        $joined = $this->sessionModel->joinSession((int) $sid, $this->getCurrentUserId());
        if (!$joined) {
            $this->setFlash('danger', 'Impossible de rejoindre cette partie.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        $this->redirect("/spaces/{$id}/play/{$sid}");
    }

    /**
     * Affiche une session de jeu.
     */
    public function show(string $id, string $sid): void
    {
        $ctx = $this->checkAccess($id);

        $session = $this->sessionModel->findWithPlayers((int) $sid);
        if (!$session || (int) $session['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Session introuvable.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        $this->render('interactive_games/' . $session['game_key'], [
            'title'         => InteractiveGameSession::GAMES[$session['game_key']]['name'] ?? 'Jeu',
            'currentSpace'  => $ctx['space'],
            'spaceRole'     => $ctx['role'],
            'activeMenu'    => 'play',
            'session'       => $session,
            'currentUserId' => $this->getCurrentUserId(),
            'isGlobalStaff' => Middleware::isGlobalStaff(),
        ]);
    }

    /**
     * Rejouer avec les mêmes réglages.
     */
    public function replay(string $id, string $sid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $session = $this->sessionModel->findWithPlayers((int) $sid);
        if (!$session || (int) $session['space_id'] !== (int) $id || $session['status'] !== 'completed') {
            $this->setFlash('danger', 'Impossible de rejouer cette partie.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        // Vérifier qu'on était dans la partie
        $wasPlayer = false;
        foreach ($session['players'] as $p) {
            if ((int) $p['user_id'] === $this->getCurrentUserId()) {
                $wasPlayer = true;
                break;
            }
        }
        if (!$wasPlayer) {
            $this->setFlash('danger', 'Vous n\'étiez pas dans cette partie.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        // Vérifier pas de partie active
        $active = $this->sessionModel->hasActiveSession((int) $id, $this->getCurrentUserId());
        if ($active) {
            $this->setFlash('warning', 'Vous avez déjà une partie en cours.');
            $this->redirect("/spaces/{$id}/play/{$active['id']}");
            return;
        }

        // Récupérer les réglages de la partie précédente
        $state = $session['game_state'];
        $gameKey = $session['game_key'];
        $maxPlayers = (int) $session['max_players'];
        $vsBot = !empty($state['vs_bot']);
        $botDifficulty = $state['bot_difficulty'] ?? null;
        $gridSize = $state['grid_size'] ?? 3;
        $alignCount = $state['align_count'] ?? $gridSize;

        $sessionId = $this->sessionModel->createSession(
            (int) $id,
            $gameKey,
            $this->getCurrentUserId(),
            $maxPlayers,
            $vsBot,
            $botDifficulty,
            $gridSize,
            $alignCount
        );

        $this->redirect("/spaces/{$id}/play/{$sessionId}");
    }

    /**
     * Annuler / supprimer une session.
     */
    public function cancel(string $id, string $sid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $this->sessionModel->cancelSession((int) $sid, $this->getCurrentUserId());
        $this->setFlash('info', 'Partie annulée.');
        $this->redirect("/spaces/{$id}/play");
    }

    /**
     * Mettre en pause une session.
     */
    public function pause(string $id, string $sid): void
    {
        $this->checkAccess($id);
        $this->validateCSRF();

        $ok = $this->sessionModel->pauseSession((int) $sid, $this->getCurrentUserId());
        if ($ok) {
            $this->setFlash('info', 'Partie mise en pause.');
        } else {
            $this->setFlash('danger', 'Impossible de mettre cette partie en pause.');
        }
        $this->redirect("/spaces/{$id}/play/{$sid}");
    }

    /**
     * Reprendre une session en pause.
     */
    public function resume(string $id, string $sid): void
    {
        $this->checkAccess($id);
        $this->validateCSRF();

        $ok = $this->sessionModel->resumeSession((int) $sid, $this->getCurrentUserId());
        if ($ok) {
            $this->setFlash('info', 'Partie reprise !');
        } else {
            $this->setFlash('danger', 'Impossible de reprendre cette partie.');
        }
        $this->redirect("/spaces/{$id}/play/{$sid}");
    }

    // ─── ENDPOINTS AJAX ────────────────────────────────────────────

    /**
     * Récupère l'état courant d'une session (polling).
     */
    public function state(string $id, string $sid): void
    {
        $this->checkAccess($id);

        $session = $this->sessionModel->findWithPlayers((int) $sid);
        if (!$session || (int) $session['space_id'] !== (int) $id) {
            $this->json(['error' => 'Session introuvable.'], 404);
            return;
        }

        // Auto-jouer le robot si c'est son tour
        if ($session['status'] === 'in_progress') {
            $this->playBotTurns((int) $sid);
            $session = $this->sessionModel->findWithPlayers((int) $sid);
        }

        $this->json([
            'id'            => (int) $session['id'],
            'status'        => $session['status'],
            'game_key'      => $session['game_key'],
            'max_players'   => (int) $session['max_players'],
            'game_state'    => $session['game_state'],
            'current_turn'  => $session['current_turn'] ? (int) $session['current_turn'] : null,
            'players'       => array_map(fn($p) => [
                'player_number' => (int) $p['player_number'],
                'user_id'       => (int) $p['user_id'],
                'username'      => $p['username'],
            ], $session['players']),
            'winner_id'     => $session['winner_id'] ? (int) $session['winner_id'] : null,
            'winner_name'   => $session['winner_name'] ?? null,
            'dev_mode'      => Middleware::isGlobalStaff(),
            'bot_difficulty' => $session['game_state']['bot_difficulty'] ?? null,
        ]);
    }

    /**
     * Jouer un coup (AJAX POST).
     */
    public function play(string $id, string $sid): void
    {
        $this->checkAccess($id);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->json(['error' => 'Données invalides.'], 400);
            return;
        }

        $session = $this->sessionModel->findWithPlayers((int) $sid);
        if (!$session || (int) $session['space_id'] !== (int) $id) {
            $this->json(['error' => 'Session introuvable.'], 404);
            return;
        }

        if ($session['status'] !== 'in_progress') {
            $this->json(['error' => 'La partie n\'est pas en cours.'], 400);
            return;
        }

        $userId = $this->getCurrentUserId();

        $myPlayer = $this->getPlayerInfo($session, $userId);
        if (!$myPlayer) {
            $this->json(['error' => 'Vous ne participez pas à cette partie.'], 403);
            return;
        }

        $isDevAction = in_array($input['action'] ?? '', ['set_dice', 'dev_set_rolls']);
        if ((int) $session['current_turn'] !== $userId && !($isDevAction && Middleware::isGlobalStaff())) {
            $this->json(['error' => 'Ce n\'est pas votre tour.'], 403);
            return;
        }

        $state = $session['game_state'];

        $result = match ($session['game_key']) {
            'morpion' => $this->playMorpion($session, $state, $input, $userId, $myPlayer),
            'yams'    => $this->playYams($session, $state, $input, $userId, $myPlayer),
            default   => ['error' => 'Jeu non supporté.'],
        };

        if (isset($result['error'])) {
            $this->json($result, 400);
            return;
        }

        $this->json($result);
    }

    // ─── LOGIQUE MORPION ───────────────────────────────────────────

    private function playMorpion(array $session, array $state, array $input, int $userId, array $myPlayer): array
    {
        $gridSize = $state['grid_size'] ?? 3;
        $alignCount = $state['align_count'] ?? $gridSize;
        $totalCells = $gridSize * $gridSize;

        $cell = $input['cell'] ?? null;
        if ($cell === null || $cell < 0 || $cell >= $totalCells) {
            return ['error' => 'Case invalide.'];
        }

        if ($state['board'][$cell] !== null) {
            return ['error' => 'Case déjà occupée.'];
        }

        $symbol = $myPlayer['player_number'] === 1 ? 'X' : 'O';
        $state['board'][$cell] = $symbol;
        $state['moves']++;

        $winner = $this->checkMorpionWinner($state['board'], $gridSize, $alignCount);
        $isDraw = !$winner && $state['moves'] >= $totalCells;

        if ($winner || $isDraw) {
            $winnerId = null;
            if ($winner) {
                $winnerNumber = $winner === 'X' ? 1 : 2;
                foreach ($session['players'] as $p) {
                    if ((int) $p['player_number'] === $winnerNumber) {
                        $winnerId = (int) $p['user_id'];
                        break;
                    }
                }
            }
            $this->sessionModel->updateState((int) $session['id'], $state, null);
            $this->sessionModel->endSession((int) $session['id'], $winnerId);

            // Enregistrer dans le leaderboard (morpion humain)
            $this->recordResult($session, 'morpion', $winnerId);
        } else {
            $nextTurn = null;
            foreach ($session['players'] as $p) {
                if ((int) $p['user_id'] !== $userId) {
                    $nextTurn = (int) $p['user_id'];
                    break;
                }
            }
            $this->sessionModel->updateState((int) $session['id'], $state, $nextTurn);
        }

        // Auto-jouer le robot si c'est son tour
        $this->playBotTurns((int) $session['id']);

        $updated = $this->sessionModel->findWithPlayers((int) $session['id']);
        return [
            'status'       => $updated['status'],
            'game_state'   => $updated['game_state'],
            'current_turn' => $updated['current_turn'] ? (int) $updated['current_turn'] : null,
            'winner_id'    => $updated['winner_id'] ? (int) $updated['winner_id'] : null,
            'winner_name'  => $updated['winner_name'] ?? null,
        ];
    }

    /**
     * Génère les lignes gagnantes pour une grille NxN avec un alignement K.
     */
    private function generateWinLines(int $gridSize, int $alignCount): array
    {
        $lines = [];
        $n = $gridSize;
        $k = $alignCount;

        // Lignes horizontales
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c <= $n - $k; $c++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) {
                    $line[] = $r * $n + ($c + $i);
                }
                $lines[] = $line;
            }
        }

        // Colonnes verticales
        for ($c = 0; $c < $n; $c++) {
            for ($r = 0; $r <= $n - $k; $r++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) {
                    $line[] = ($r + $i) * $n + $c;
                }
                $lines[] = $line;
            }
        }

        // Diagonales ↘
        for ($r = 0; $r <= $n - $k; $r++) {
            for ($c = 0; $c <= $n - $k; $c++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) {
                    $line[] = ($r + $i) * $n + ($c + $i);
                }
                $lines[] = $line;
            }
        }

        // Diagonales ↙
        for ($r = 0; $r <= $n - $k; $r++) {
            for ($c = $k - 1; $c < $n; $c++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) {
                    $line[] = ($r + $i) * $n + ($c - $i);
                }
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function checkMorpionWinner(array $board, int $gridSize = 3, int $alignCount = 3): ?string
    {
        $lines = $this->generateWinLines($gridSize, $alignCount);
        foreach ($lines as $line) {
            $first = $board[$line[0]];
            if ($first === null) continue;
            $win = true;
            for ($i = 1; $i < count($line); $i++) {
                if ($board[$line[$i]] !== $first) {
                    $win = false;
                    break;
                }
            }
            if ($win) return $first;
        }
        return null;
    }

    // ─── LOGIQUE YAMS ─────────────────────────────────────────────

    private function playYams(array $session, array $state, array $input, int $userId, array $myPlayer): array
    {
        $action = $input['action'] ?? '';
        $playerKey = 'player' . $myPlayer['player_number'];
        $players = $session['players'];

        if ($action === 'roll') {
            $isDevMode = Middleware::isGlobalStaff() && !empty($input['dev_mode']);
            if (!$isDevMode && ($state['rolls_left'] ?? 0) <= 0) {
                return ['error' => 'Plus de lancers disponibles.'];
            }

            $kept = $input['kept'] ?? [false, false, false, false, false];
            $dice = $state['current_dice'] ?? [1, 1, 1, 1, 1];

            for ($i = 0; $i < 5; $i++) {
                if (empty($kept[$i])) {
                    $dice[$i] = random_int(1, 6);
                }
            }

            $state['current_dice'] = $dice;
            $state['kept'] = $kept;
            if (!$isDevMode) {
                $state['rolls_left']--;
            }

            $this->sessionModel->updateState((int) $session['id'], $state, $userId);

            $updated = $this->sessionModel->findWithPlayers((int) $session['id']);
            return [
                'status'       => $updated['status'],
                'game_state'   => $updated['game_state'],
                'current_turn' => $updated['current_turn'] ? (int) $updated['current_turn'] : null,
                'winner_id'    => null,
            ];
        }

        if ($action === 'set_dice') {
            if (!Middleware::isGlobalStaff()) {
                return ['error' => 'Accès réservé aux administrateurs.'];
            }

            $newDice = $input['dice'] ?? null;
            if (!is_array($newDice) || count($newDice) !== 5) {
                return ['error' => 'Valeurs de dés invalides.'];
            }
            foreach ($newDice as $v) {
                if (!is_int($v) || $v < 1 || $v > 6) {
                    return ['error' => 'Chaque dé doit être entre 1 et 6.'];
                }
            }

            $state['current_dice'] = $newDice;
            $this->sessionModel->updateState((int) $session['id'], $state, (int) $session['current_turn']);

            $updated = $this->sessionModel->findWithPlayers((int) $session['id']);
            return [
                'status'       => $updated['status'],
                'game_state'   => $updated['game_state'],
                'current_turn' => $updated['current_turn'] ? (int) $updated['current_turn'] : null,
                'winner_id'    => null,
            ];
        }

        if ($action === 'dev_set_rolls') {
            if (!Middleware::isGlobalStaff()) {
                return ['error' => 'Accès réservé aux administrateurs.'];
            }

            $rolls = $input['rolls_left'] ?? null;
            if (!is_int($rolls) || $rolls < 0 || $rolls > 3) {
                return ['error' => 'Nombre de lancers invalide (0-3).'];
            }

            $state['rolls_left'] = $rolls;
            $this->sessionModel->updateState((int) $session['id'], $state, (int) $session['current_turn']);

            $updated = $this->sessionModel->findWithPlayers((int) $session['id']);
            return [
                'status'       => $updated['status'],
                'game_state'   => $updated['game_state'],
                'current_turn' => $updated['current_turn'] ? (int) $updated['current_turn'] : null,
                'winner_id'    => null,
            ];
        }

        if ($action === 'score') {
            $category = $input['category'] ?? '';
            $validCategories = [
                'ones', 'twos', 'threes', 'fours', 'fives', 'sixes',
                'three_of_kind', 'four_of_kind', 'full_house',
                'small_straight', 'large_straight', 'yams', 'chance',
            ];

            if (!in_array($category, $validCategories, true)) {
                return ['error' => 'Catégorie invalide.'];
            }

            if (isset($state['scores'][$playerKey][$category])) {
                return ['error' => 'Catégorie déjà remplie.'];
            }

            $dice = $state['current_dice'];
            $score = $this->calculateYamsScore($category, $dice);
            $state['scores'][$playerKey][$category] = $score;

            // Vérifier si la partie est terminée (tous les joueurs ont 13 catégories)
            $allDone = true;
            foreach ($players as $p) {
                $pk = 'player' . $p['player_number'];
                if (count($state['scores'][$pk] ?? []) < 13) {
                    $allDone = false;
                    break;
                }
            }

            if ($allDone) {
                $finalScores = [];
                $maxTotal = -1;
                $winnerId = null;
                $tie = false;

                foreach ($players as $p) {
                    $pk = 'player' . $p['player_number'];
                    $total = array_sum($state['scores'][$pk]);
                    $upper = $this->yamsUpperTotal($state['scores'][$pk]);
                    $bonus = $upper >= 63 ? 35 : 0;
                    $total += $bonus;

                    $finalScores[$pk] = $total;
                    $finalScores['bonus' . $p['player_number']] = $bonus;

                    if ($total > $maxTotal) {
                        $maxTotal = $total;
                        $winnerId = (int) $p['user_id'];
                        $tie = false;
                    } elseif ($total === $maxTotal) {
                        $tie = true;
                    }
                }

                if ($tie) {
                    $winnerId = null;
                }

                $state['final_scores'] = $finalScores;
                $state['current_dice'] = [1, 1, 1, 1, 1];
                $state['kept'] = [false, false, false, false, false];
                $state['rolls_left'] = 0;

                $this->sessionModel->updateState((int) $session['id'], $state, null);
                $this->sessionModel->endSession((int) $session['id'], $winnerId);

                // Enregistrer dans le leaderboard (yams humain)
                $this->recordResult($session, 'yams', $winnerId, $finalScores);
            } else {
                // Tour suivant : prochain joueur dans l'ordre
                $currentIndex = null;
                foreach ($players as $i => $p) {
                    if ((int) $p['user_id'] === $userId) {
                        $currentIndex = $i;
                        break;
                    }
                }
                $nextIndex = ($currentIndex + 1) % count($players);
                $nextTurn = (int) $players[$nextIndex]['user_id'];

                $state['current_dice'] = [1, 1, 1, 1, 1];
                $state['kept'] = [false, false, false, false, false];
                $state['rolls_left'] = 3;

                $minFilled = PHP_INT_MAX;
                foreach ($players as $p) {
                    $pk = 'player' . $p['player_number'];
                    $minFilled = min($minFilled, count($state['scores'][$pk] ?? []));
                }
                $state['round'] = $minFilled + 1;

                $this->sessionModel->updateState((int) $session['id'], $state, $nextTurn);
            }

            // Auto-jouer le robot si c'est son tour
            $this->playBotTurns((int) $session['id']);

            $updated = $this->sessionModel->findWithPlayers((int) $session['id']);
            return [
                'status'       => $updated['status'],
                'game_state'   => $updated['game_state'],
                'current_turn' => $updated['current_turn'] ? (int) $updated['current_turn'] : null,
                'winner_id'    => $updated['winner_id'] ? (int) $updated['winner_id'] : null,
                'winner_name'  => $updated['winner_name'] ?? null,
            ];
        }

        return ['error' => 'Action inconnue.'];
    }

    private function calculateYamsScore(string $category, array $dice): int
    {
        $counts = array_count_values($dice);
        $sum = array_sum($dice);
        sort($dice);

        return match ($category) {
            'ones'   => ($counts[1] ?? 0) * 1,
            'twos'   => ($counts[2] ?? 0) * 2,
            'threes' => ($counts[3] ?? 0) * 3,
            'fours'  => ($counts[4] ?? 0) * 4,
            'fives'  => ($counts[5] ?? 0) * 5,
            'sixes'  => ($counts[6] ?? 0) * 6,
            'three_of_kind' => max($counts) >= 3 ? $sum : 0,
            'four_of_kind'  => max($counts) >= 4 ? $sum : 0,
            'full_house'    => (in_array(3, $counts, true) && in_array(2, $counts, true)) ? 25 : 0,
            'small_straight' => $this->hasStraight($dice, 4) ? 30 : 0,
            'large_straight' => $this->hasStraight($dice, 5) ? 40 : 0,
            'yams'    => max($counts) >= 5 ? 50 : 0,
            'chance'  => $sum,
            default   => 0,
        };
    }

    private function hasStraight(array $sorted, int $length): bool
    {
        $unique = array_values(array_unique($sorted));
        $consecutive = 1;
        for ($i = 1, $n = count($unique); $i < $n; $i++) {
            if ($unique[$i] === $unique[$i - 1] + 1) {
                $consecutive++;
                if ($consecutive >= $length) return true;
            } else {
                $consecutive = 1;
            }
        }
        return false;
    }

    private function yamsUpperTotal(array $scores): int
    {
        $upper = ['ones', 'twos', 'threes', 'fours', 'fives', 'sixes'];
        $total = 0;
        foreach ($upper as $cat) {
            $total += $scores[$cat] ?? 0;
        }
        return $total;
    }

    // ─── LOGIQUE ROBOTS ──────────────────────────────────────────────────────

    /**
     * Joue automatiquement tous les tours consécutifs de robots.
     */
    private function playBotTurns(int $sessionId): void
    {
        for ($i = 0; $i < 10; $i++) {
            $session = $this->sessionModel->findWithPlayers($sessionId);
            if (!$session || $session['status'] !== 'in_progress' || !$session['current_turn']) {
                return;
            }

            $botPlayer = null;
            foreach ($session['players'] as $p) {
                if ((int) $p['user_id'] === (int) $session['current_turn'] && !empty($p['is_bot'])) {
                    $botPlayer = $p;
                    break;
                }
            }
            if (!$botPlayer) {
                return;
            }

            if ($session['game_key'] === 'morpion') {
                $this->executeBotMorpion($session, $botPlayer);
            } elseif ($session['game_key'] === 'yams') {
                $this->executeBotYams($session, $botPlayer);
            } else {
                return;
            }
        }
    }

    /**
     * Exécute un coup de robot au morpion (minimax).
     */
    private function executeBotMorpion(array $session, array $botPlayer): void
    {
        $state = $session['game_state'];
        $botSymbol = (int) $botPlayer['player_number'] === 1 ? 'X' : 'O';
        $difficulty = $state['bot_difficulty'] ?? BotAI::DIFFICULTY_HARD;
        $gridSize = $state['grid_size'] ?? 3;
        $alignCount = $state['align_count'] ?? $gridSize;
        $totalCells = $gridSize * $gridSize;

        $cell = BotAI::morpionMove($state['board'], $botSymbol, $difficulty, $gridSize, $alignCount);
        if ($cell < 0) {
            return;
        }

        $state['board'][$cell] = $botSymbol;
        $state['moves']++;

        $winner = $this->checkMorpionWinner($state['board'], $gridSize, $alignCount);
        $isDraw = !$winner && $state['moves'] >= $totalCells;

        if ($winner || $isDraw) {
            $winnerId = null;
            if ($winner) {
                $winnerNumber = $winner === 'X' ? 1 : 2;
                foreach ($session['players'] as $p) {
                    if ((int) $p['player_number'] === $winnerNumber) {
                        $winnerId = (int) $p['user_id'];
                        break;
                    }
                }
            }
            $this->sessionModel->updateState((int) $session['id'], $state, null);
            $this->sessionModel->endSession((int) $session['id'], $winnerId);
        } else {
            $nextTurn = null;
            foreach ($session['players'] as $p) {
                if ((int) $p['user_id'] !== (int) $botPlayer['user_id']) {
                    $nextTurn = (int) $p['user_id'];
                    break;
                }
            }
            $this->sessionModel->updateState((int) $session['id'], $state, $nextTurn);
        }
    }

    /**
     * Exécute un tour complet de YAMS pour le robot.
     */
    private function executeBotYams(array $session, array $botPlayer): void
    {
        $state = $session['game_state'];
        $playerKey = 'player' . $botPlayer['player_number'];
        $players = $session['players'];
        $difficulty = $state['bot_difficulty'] ?? BotAI::DIFFICULTY_HARD;

        $result = BotAI::yamsTurn($state, $playerKey, $difficulty);
        $state['current_dice'] = $result['dice'];
        $state['rolls_left'] = 0;

        $category = $result['category'];
        $score = $this->calculateYamsScore($category, $result['dice']);
        $state['scores'][$playerKey][$category] = $score;

        // Vérifier fin de partie
        $allDone = true;
        foreach ($players as $p) {
            $pk = 'player' . $p['player_number'];
            if (count($state['scores'][$pk] ?? []) < 13) {
                $allDone = false;
                break;
            }
        }

        if ($allDone) {
            $finalScores = [];
            $maxTotal = -1;
            $winnerId = null;
            $tie = false;

            foreach ($players as $p) {
                $pk = 'player' . $p['player_number'];
                $total = array_sum($state['scores'][$pk]);
                $upper = $this->yamsUpperTotal($state['scores'][$pk]);
                $bonus = $upper >= 63 ? 35 : 0;
                $total += $bonus;
                $finalScores[$pk] = $total;
                $finalScores['bonus' . $p['player_number']] = $bonus;

                if ($total > $maxTotal) {
                    $maxTotal = $total;
                    $winnerId = (int) $p['user_id'];
                    $tie = false;
                } elseif ($total === $maxTotal) {
                    $tie = true;
                }
            }

            if ($tie) {
                $winnerId = null;
            }

            $state['final_scores'] = $finalScores;
            $state['current_dice'] = [1, 1, 1, 1, 1];
            $state['kept'] = [false, false, false, false, false];
            $state['rolls_left'] = 0;

            $this->sessionModel->updateState((int) $session['id'], $state, null);
            $this->sessionModel->endSession((int) $session['id'], $winnerId);

            // Enregistrer dans le leaderboard (scores humains uniquement)
            $this->recordResult($session, 'yams', $winnerId, $finalScores);
        } else {
            $currentIndex = null;
            foreach ($players as $i => $p) {
                if ((int) $p['user_id'] === (int) $botPlayer['user_id']) {
                    $currentIndex = $i;
                    break;
                }
            }
            $nextIndex = ($currentIndex + 1) % count($players);
            $nextTurn = (int) $players[$nextIndex]['user_id'];

            $state['current_dice'] = [1, 1, 1, 1, 1];
            $state['kept'] = [false, false, false, false, false];
            $state['rolls_left'] = 3;

            $minFilled = PHP_INT_MAX;
            foreach ($players as $p) {
                $pk = 'player' . $p['player_number'];
                $minFilled = min($minFilled, count($state['scores'][$pk] ?? []));
            }
            $state['round'] = $minFilled + 1;

            $this->sessionModel->updateState((int) $session['id'], $state, $nextTurn);
        }
    }

    // ─── ENREGISTREMENT LEADERBOARD ──────────────────────────────

    /**
     * Enregistre le résultat d'une partie interactive dans le système de jeux classiques
     * (game_types / games / game_players / rounds / round_scores)
     * pour que le leaderboard puisse les comptabiliser.
     *
     * Ignoré si la session contient un bot.
     */
    private function recordResult(array $session, string $gameKey, ?int $winnerId, ?array $finalScores = null): void
    {
        $players = $session['players'];

        // Morpion (win_loss) : exige 2 joueurs humains
        if ($gameKey === 'morpion') {
            if (count($players) < 2) {
                return;
            }
            foreach ($players as $p) {
                if (!empty($p['is_bot'])) {
                    return;
                }
            }
        }

        // YAMS (highest_score) : ne garder que les joueurs humains
        $humanPlayers = array_values(array_filter($players, fn($p) => empty($p['is_bot'])));
        if (empty($humanPlayers)) {
            return;
        }

        // Pour le morpion, utiliser tous les joueurs (déjà vérifié humains)
        // Pour le YAMS, n'enregistrer que les humains
        $recordedPlayers = ($gameKey === 'morpion') ? $players : $humanPlayers;

        $spaceId = (int) $session['space_id'];

        // Trouver le game_type global correspondant
        $gameName = $gameKey === 'morpion' ? 'Morpion' : 'YAMS';
        $gameType = $this->gameTypeModel->findOneBy(['name' => $gameName, 'is_global' => 1]);
        if (!$gameType) {
            return;
        }

        // Ne garder que les joueurs déjà enregistrés dans l'espace
        $playerIds = [];
        foreach ($recordedPlayers as $p) {
            $existingPlayer = $this->playerModel->findByUserInSpace($spaceId, (int) $p['user_id']);
            if ($existingPlayer) {
                $playerIds[(int) $p['user_id']] = (int) $existingPlayer['id'];
            }
        }

        // Il faut au moins 2 joueurs raccordés à l'espace pour enregistrer
        if (count($playerIds) < 2) {
            return;
        }

        // Filtrer les joueurs enregistrés
        $recordedPlayers = array_values(array_filter($recordedPlayers, fn($p) => isset($playerIds[(int) $p['user_id']])));

        // Créer la partie classique
        $gameId = $this->gameModel->create([
            'space_id'     => $spaceId,
            'game_type_id' => (int) $gameType['id'],
            'status'       => 'completed',
            'started_at'   => $session['created_at'],
            'ended_at'     => date('Y-m-d H:i:s'),
            'notes'        => "Partie interactive #{$session['id']}",
            'created_by'   => (int) $session['created_by'],
        ]);

        // Déterminer le gagnant parmi les joueurs enregistrés
        $hasBot = count($recordedPlayers) < count($players);
        $recordedWinnerId = $hasBot ? null : $winnerId;

        // Ajouter les joueurs à la partie
        foreach ($recordedPlayers as $p) {
            $uid = (int) $p['user_id'];
            $pid = $playerIds[$uid];
            $isWinner = ($recordedWinnerId !== null && $uid === $recordedWinnerId) ? 1 : 0;

            if ($gameKey === 'morpion') {
                $score = $isWinner ? 1 : 0;
            } else {
                $pk = 'player' . $p['player_number'];
                $score = $finalScores[$pk] ?? 0;
            }

            $this->gamePlayerModel->create([
                'game_id'    => $gameId,
                'player_id'  => $pid,
                'total_score' => $score,
                'is_winner'  => $isWinner,
            ]);
        }

        // Créer une manche unique
        $roundId = $this->roundModel->create([
            'game_id'      => $gameId,
            'round_number' => 1,
            'status'       => 'completed',
            'started_at'   => $session['created_at'],
            'ended_at'     => date('Y-m-d H:i:s'),
        ]);

        // Enregistrer les scores dans round_scores
        $scores = [];
        foreach ($recordedPlayers as $p) {
            $uid = (int) $p['user_id'];
            $pid = $playerIds[$uid];

            if ($gameKey === 'morpion') {
                $scores[$pid] = ($recordedWinnerId !== null && $uid === $recordedWinnerId) ? 1 : 0;
            } else {
                $pk = 'player' . $p['player_number'];
                $scores[$pid] = $finalScores[$pk] ?? 0;
            }
        }
        $this->roundScoreModel->saveScores($roundId, $scores, $gameType['win_condition'], $gameId);

        // Recalculer les totaux et rangs
        $this->gameModel->recalculateTotals($gameId);
    }
}
