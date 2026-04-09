<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle InteractiveGameSession — sessions de jeux en ligne (morpion, yams…).
 */
class InteractiveGameSession extends Model
{
    protected string $table = 'interactive_game_sessions';

    /** Jeux disponibles avec leurs métadonnées. */
    public const GAMES = [
        'morpion' => [
            'name'        => 'Morpion',
            'icon'        => '❌⭕',
            'description' => 'Le classique Tic-Tac-Toe ! Alignez 3 symboles pour gagner.',
            'min_players' => 2,
            'max_players' => 2,
        ],
        'yams' => [
            'name'        => 'YAMS',
            'icon'        => '🎲',
            'description' => 'Lancez 5 dés et réalisez les meilleures combinaisons !',
            'min_players' => 1,
            'max_players' => 4,
        ],
    ];

    /**
     * Retourne l'état initial du jeu selon la clé.
     */
    public static function initialState(string $gameKey, int $maxPlayers = 2): array
    {
        return match ($gameKey) {
            'morpion' => [
                'board'  => array_fill(0, 9, null),
                'moves'  => 0,
            ],
            'yams' => self::initialYamsState($maxPlayers),
            default => [],
        };
    }

    private static function initialYamsState(int $maxPlayers): array
    {
        $scores = [];
        for ($i = 1; $i <= $maxPlayers; $i++) {
            $scores["player{$i}"] = [];
        }
        return [
            'scores'       => $scores,
            'current_dice' => [1, 1, 1, 1, 1],
            'kept'         => [false, false, false, false, false],
            'rolls_left'   => 3,
            'round'        => 1,
            'max_rounds'   => 13,
        ];
    }

    /**
     * Crée une nouvelle session.
     */
    /**
     * Retourne l'ID de l'utilisateur robot.
     */
    public function getBotUserId(): ?int
    {
        $stmt = $this->query("SELECT id FROM users WHERE is_bot = 1 LIMIT 1");
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Crée une nouvelle session.
     */
    public function createSession(int $spaceId, string $gameKey, int $userId, int $maxPlayers = 2, bool $vsBot = false): int
    {
        if ($vsBot) {
            $maxPlayers = 2;
        }
        $state = self::initialState($gameKey, $maxPlayers);
        $isSolo = (!$vsBot && $maxPlayers <= 1);
        $status = ($isSolo || $vsBot) ? 'in_progress' : 'waiting';

        $this->query(
            "INSERT INTO {$this->table} (space_id, game_key, max_players, status, created_by, player1_id, current_turn, game_state)
             VALUES (:space_id, :game_key, :max_players, :status, :created_by, :player1_id, :current_turn, :game_state)",
            [
                'space_id'     => $spaceId,
                'game_key'     => $gameKey,
                'max_players'  => $maxPlayers,
                'status'       => $status,
                'created_by'   => $userId,
                'player1_id'   => $userId,
                'current_turn' => $userId,
                'game_state'   => json_encode($state),
            ]
        );

        $sessionId = (int) $this->db->lastInsertId();

        // Inscrire le créateur comme joueur 1
        $this->query(
            "INSERT INTO interactive_game_players (session_id, user_id, player_number) VALUES (:sid, :uid, 1)",
            ['sid' => $sessionId, 'uid' => $userId]
        );

        // Ajouter le robot comme joueur 2
        if ($vsBot) {
            $botUserId = $this->getBotUserId();
            if ($botUserId) {
                $this->query(
                    "INSERT INTO interactive_game_players (session_id, user_id, player_number) VALUES (:sid, :uid, 2)",
                    ['sid' => $sessionId, 'uid' => $botUserId]
                );
            }
        }

        return $sessionId;
    }

    /**
     * Rejoindre une session en attente.
     */
    public function joinSession(int $sessionId, int $userId): bool
    {
        $session = $this->find($sessionId);
        if (!$session || $session['status'] !== 'waiting') {
            return false;
        }

        // Vérifier si déjà inscrit
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM interactive_game_players WHERE session_id = :sid AND user_id = :uid",
            ['sid' => $sessionId, 'uid' => $userId]
        );
        if ((int) $stmt->fetch()['cnt'] > 0) {
            return false;
        }

        // Compter les joueurs actuels
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM interactive_game_players WHERE session_id = :sid",
            ['sid' => $sessionId]
        );
        $currentCount = (int) $stmt->fetch()['cnt'];
        $maxPlayers = (int) $session['max_players'];

        if ($currentCount >= $maxPlayers) {
            return false;
        }

        $playerNumber = $currentCount + 1;

        $this->query(
            "INSERT INTO interactive_game_players (session_id, user_id, player_number) VALUES (:sid, :uid, :pn)",
            ['sid' => $sessionId, 'uid' => $userId, 'pn' => $playerNumber]
        );

        // Mettre à jour game_state si nécessaire (YAMS : ajouter les scores)
        $state = json_decode($session['game_state'], true);
        $playerKey = 'player' . $playerNumber;
        if ($session['game_key'] === 'yams' && !isset($state['scores'][$playerKey])) {
            $state['scores'][$playerKey] = [];
        }

        // Si la session est pleine, la démarrer
        if ($currentCount + 1 >= $maxPlayers) {
            $this->query(
                "UPDATE {$this->table} SET player2_id = :p2, status = 'in_progress', game_state = :state, updated_at = NOW() WHERE id = :id",
                ['p2' => $userId, 'state' => json_encode($state), 'id' => $sessionId]
            );
        } else {
            $this->query(
                "UPDATE {$this->table} SET game_state = :state, updated_at = NOW() WHERE id = :id",
                ['state' => json_encode($state), 'id' => $sessionId]
            );
        }

        return true;
    }

    /**
     * Récupère une session avec les joueurs.
     */
    public function findWithPlayers(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT s.*, uw.username AS winner_name
             FROM {$this->table} s
             LEFT JOIN users uw ON uw.id = s.winner_id
             WHERE s.id = :id",
            ['id' => $id]
        );
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['game_state'] = json_decode($row['game_state'], true);

        // Charger les joueurs
        $stmt = $this->query(
            "SELECT igp.player_number, igp.user_id, u.username, u.is_bot
             FROM interactive_game_players igp
             JOIN users u ON u.id = igp.user_id
             WHERE igp.session_id = :sid
             ORDER BY igp.player_number",
            ['sid' => $id]
        );
        $row['players'] = $stmt->fetchAll();

        return $row;
    }

    /**
     * Liste les sessions d'un espace (actives d'abord).
     */
    public function findBySpace(int $spaceId, ?string $gameKey = null): array
    {
        $params = ['space_id' => $spaceId];
        $where = "s.space_id = :space_id";

        if ($gameKey !== null) {
            $where .= " AND s.game_key = :game_key";
            $params['game_key'] = $gameKey;
        }

        $stmt = $this->query(
            "SELECT s.*,
                    uc.username AS creator_name,
                    uw.username AS winner_name,
                    GROUP_CONCAT(u.username ORDER BY igp.player_number SEPARATOR ', ') AS player_names,
                    GROUP_CONCAT(igp.user_id ORDER BY igp.player_number) AS player_user_ids,
                    COUNT(igp.id) AS player_count
             FROM {$this->table} s
             JOIN users uc ON uc.id = s.created_by
             LEFT JOIN users uw ON uw.id = s.winner_id
             LEFT JOIN interactive_game_players igp ON igp.session_id = s.id
             LEFT JOIN users u ON u.id = igp.user_id
             WHERE {$where}
             GROUP BY s.id
             ORDER BY FIELD(s.status, 'waiting', 'in_progress', 'paused', 'completed', 'cancelled'),
                      s.updated_at DESC
             LIMIT 50",
            $params
        );
        return $stmt->fetchAll();
    }

    /**
     * Met à jour l'état du jeu.
     */
    public function updateState(int $id, array $state, ?int $currentTurn = null): void
    {
        $this->query(
            "UPDATE {$this->table}
             SET game_state = :state, current_turn = :turn, updated_at = NOW()
             WHERE id = :id",
            [
                'state' => json_encode($state),
                'turn'  => $currentTurn,
                'id'    => $id,
            ]
        );
    }

    /**
     * Termine une session.
     */
    public function endSession(int $id, ?int $winnerId, string $status = 'completed'): void
    {
        $this->query(
            "UPDATE {$this->table}
             SET status = :status, winner_id = :winner_id, ended_at = NOW(), updated_at = NOW()
             WHERE id = :id",
            [
                'status'    => $status,
                'winner_id' => $winnerId,
                'id'        => $id,
            ]
        );
    }

    /**
     * Annule une session (par le créateur).
     */
    public function cancelSession(int $id, int $userId): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET status = 'cancelled', ended_at = NOW(), updated_at = NOW()
             WHERE id = :id AND created_by = :uid AND status IN ('waiting', 'in_progress', 'paused')",
            ['id' => $id, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un utilisateur a déjà une session non terminée dans un espace.
     */
    public function hasActiveSession(int $spaceId, int $userId): ?array
    {
        $stmt = $this->query(
            "SELECT s.id, s.game_key, s.status
             FROM {$this->table} s
             INNER JOIN interactive_game_players igp ON igp.session_id = s.id
             WHERE s.space_id = :space_id
               AND igp.user_id = :user_id
               AND s.status IN ('waiting', 'in_progress', 'paused')
             LIMIT 1",
            ['space_id' => $spaceId, 'user_id' => $userId]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Met en pause une session (par le créateur).
     */
    public function pauseSession(int $id, int $userId): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET status = 'paused', updated_at = NOW()
             WHERE id = :id AND created_by = :uid AND status = 'in_progress'",
            ['id' => $id, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Reprend une session en pause (par le créateur).
     */
    public function resumeSession(int $id, int $userId): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET status = 'in_progress', updated_at = NOW()
             WHERE id = :id AND created_by = :uid AND status = 'paused'",
            ['id' => $id, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }
}
