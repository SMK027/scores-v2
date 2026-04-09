<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\InteractiveGameSession;
use App\Models\Space;

/**
 * Contrôleur des jeux interactifs jouables en ligne.
 */
class InteractiveGameController extends Controller
{
    private InteractiveGameSession $sessionModel;
    private Space $spaceModel;

    public function __construct()
    {
        $this->sessionModel = new InteractiveGameSession();
        $this->spaceModel   = new Space();
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

        $gameKey = trim($_POST['game_key'] ?? '');
        if (!isset(InteractiveGameSession::GAMES[$gameKey])) {
            $this->setFlash('danger', 'Jeu inconnu.');
            $this->redirect("/spaces/{$id}/play");
            return;
        }

        $sessionId = $this->sessionModel->createSession(
            (int) $id,
            $gameKey,
            $this->getCurrentUserId()
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
        ]);
    }

    /**
     * Annuler une session.
     */
    public function cancel(string $id, string $sid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $this->sessionModel->cancelSession((int) $sid, $this->getCurrentUserId());
        $this->setFlash('info', 'Partie annulée.');
        $this->redirect("/spaces/{$id}/play");
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

        $this->json([
            'id'            => (int) $session['id'],
            'status'        => $session['status'],
            'game_key'      => $session['game_key'],
            'game_state'    => $session['game_state'],
            'current_turn'  => $session['current_turn'] ? (int) $session['current_turn'] : null,
            'player1_id'    => (int) $session['player1_id'],
            'player2_id'    => $session['player2_id'] ? (int) $session['player2_id'] : null,
            'player1_name'  => $session['player1_name'],
            'player2_name'  => $session['player2_name'] ?? null,
            'winner_id'     => $session['winner_id'] ? (int) $session['winner_id'] : null,
            'winner_name'   => $session['winner_name'] ?? null,
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
        if ((int) $session['current_turn'] !== $userId) {
            $this->json(['error' => 'Ce n\'est pas votre tour.'], 403);
            return;
        }

        $state = $session['game_state'];

        $result = match ($session['game_key']) {
            'morpion' => $this->playMorpion($session, $state, $input, $userId),
            'yams'    => $this->playYams($session, $state, $input, $userId),
            default   => ['error' => 'Jeu non supporté.'],
        };

        if (isset($result['error'])) {
            $this->json($result, 400);
            return;
        }

        $this->json($result);
    }

    // ─── LOGIQUE MORPION ───────────────────────────────────────────

    private function playMorpion(array $session, array $state, array $input, int $userId): array
    {
        $cell = $input['cell'] ?? null;
        if ($cell === null || $cell < 0 || $cell > 8) {
            return ['error' => 'Case invalide.'];
        }

        if ($state['board'][$cell] !== null) {
            return ['error' => 'Case déjà occupée.'];
        }

        // X = player1, O = player2
        $symbol = ($userId === (int) $session['player1_id']) ? 'X' : 'O';
        $state['board'][$cell] = $symbol;
        $state['moves']++;

        $winner = $this->checkMorpionWinner($state['board']);
        $isDraw = !$winner && $state['moves'] >= 9;

        if ($winner || $isDraw) {
            $winnerId = null;
            if ($winner === 'X') {
                $winnerId = (int) $session['player1_id'];
            } elseif ($winner === 'O') {
                $winnerId = (int) $session['player2_id'];
            }
            $this->sessionModel->updateState((int) $session['id'], $state, null);
            $this->sessionModel->endSession((int) $session['id'], $winnerId);
        } else {
            $nextTurn = ($userId === (int) $session['player1_id'])
                ? (int) $session['player2_id']
                : (int) $session['player1_id'];
            $this->sessionModel->updateState((int) $session['id'], $state, $nextTurn);
        }

        // Re-fetch pour renvoyer l'état à jour
        $updated = $this->sessionModel->findWithPlayers((int) $session['id']);
        return [
            'status'       => $updated['status'],
            'game_state'   => $updated['game_state'],
            'current_turn' => $updated['current_turn'] ? (int) $updated['current_turn'] : null,
            'winner_id'    => $updated['winner_id'] ? (int) $updated['winner_id'] : null,
            'winner_name'  => $updated['winner_name'] ?? null,
        ];
    }

    private function checkMorpionWinner(array $board): ?string
    {
        $lines = [
            [0, 1, 2], [3, 4, 5], [6, 7, 8], // lignes
            [0, 3, 6], [1, 4, 7], [2, 5, 8], // colonnes
            [0, 4, 8], [2, 4, 6],             // diagonales
        ];
        foreach ($lines as $line) {
            $a = $board[$line[0]];
            $b = $board[$line[1]];
            $c = $board[$line[2]];
            if ($a !== null && $a === $b && $b === $c) {
                return $a;
            }
        }
        return null;
    }

    // ─── LOGIQUE YAMS ─────────────────────────────────────────────

    private function playYams(array $session, array $state, array $input, int $userId): array
    {
        $action = $input['action'] ?? '';

        $playerKey = ($userId === (int) $session['player1_id']) ? 'player1' : 'player2';

        if ($action === 'roll') {
            if (($state['rolls_left'] ?? 0) <= 0) {
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
            $state['rolls_left']--;

            $this->sessionModel->updateState((int) $session['id'], $state, $userId);

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

            // Passer au joueur suivant ou à la manche suivante
            $otherKey = ($playerKey === 'player1') ? 'player2' : 'player1';
            $otherUserId = ($userId === (int) $session['player1_id'])
                ? (int) $session['player2_id']
                : (int) $session['player1_id'];

            $playerFilledCount = count($state['scores'][$playerKey]);
            $otherFilledCount  = count($state['scores'][$otherKey]);

            // Vérifier si la partie est terminée (13 catégories chacun)
            if ($playerFilledCount >= 13 && $otherFilledCount >= 13) {
                $total1 = array_sum($state['scores']['player1']);
                $total2 = array_sum($state['scores']['player2']);

                // Bonus partie haute (>= 63)
                $upper1 = $this->yamsUpperTotal($state['scores']['player1']);
                $upper2 = $this->yamsUpperTotal($state['scores']['player2']);
                if ($upper1 >= 63) $total1 += 35;
                if ($upper2 >= 63) $total2 += 35;

                $state['final_scores'] = [
                    'player1' => $total1,
                    'player2' => $total2,
                    'bonus1'  => $upper1 >= 63 ? 35 : 0,
                    'bonus2'  => $upper2 >= 63 ? 35 : 0,
                ];

                $winnerId = null;
                if ($total1 > $total2) $winnerId = (int) $session['player1_id'];
                elseif ($total2 > $total1) $winnerId = (int) $session['player2_id'];

                $state['current_dice'] = [1, 1, 1, 1, 1];
                $state['kept'] = [false, false, false, false, false];
                $state['rolls_left'] = 0;

                $this->sessionModel->updateState((int) $session['id'], $state, null);
                $this->sessionModel->endSession((int) $session['id'], $winnerId);
            } else {
                // Tour suivant : alterner entre les joueurs
                // Si les deux ont le même nombre de catégories, c'est au joueur1
                if ($playerFilledCount > $otherFilledCount) {
                    $nextTurn = $otherUserId;
                } else {
                    $nextTurn = (int) $session['player1_id'];
                }

                $state['current_dice'] = [1, 1, 1, 1, 1];
                $state['kept'] = [false, false, false, false, false];
                $state['rolls_left'] = 3;
                $state['round'] = min($playerFilledCount, $otherFilledCount) + 1;

                $this->sessionModel->updateState((int) $session['id'], $state, $nextTurn);
            }

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
}
