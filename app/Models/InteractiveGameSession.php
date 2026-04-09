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
            'min_players' => 2,
            'max_players' => 2,
        ],
    ];

    /**
     * Retourne l'état initial du jeu selon la clé.
     */
    public static function initialState(string $gameKey): array
    {
        return match ($gameKey) {
            'morpion' => [
                'board'  => array_fill(0, 9, null),
                'moves'  => 0,
            ],
            'yams' => [
                'scores'       => ['player1' => [], 'player2' => []],
                'current_dice' => [1, 1, 1, 1, 1],
                'kept'         => [false, false, false, false, false],
                'rolls_left'   => 3,
                'round'        => 1,
                'max_rounds'   => 13,
            ],
            default => [],
        };
    }

    /**
     * Crée une nouvelle session.
     */
    public function createSession(int $spaceId, string $gameKey, int $userId): int
    {
        $state = self::initialState($gameKey);

        $this->query(
            "INSERT INTO {$this->table} (space_id, game_key, status, created_by, player1_id, current_turn, game_state)
             VALUES (:space_id, :game_key, 'waiting', :created_by, :player1_id, :current_turn, :game_state)",
            [
                'space_id'     => $spaceId,
                'game_key'     => $gameKey,
                'created_by'   => $userId,
                'player1_id'   => $userId,
                'current_turn' => $userId,
                'game_state'   => json_encode($state),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Rejoindre une session en attente.
     */
    public function joinSession(int $sessionId, int $userId): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET player2_id = :player2_id, status = 'in_progress', updated_at = NOW()
             WHERE id = :id AND status = 'waiting' AND player1_id != :uid",
            [
                'player2_id' => $userId,
                'id'         => $sessionId,
                'uid'        => $userId,
            ]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Récupère une session avec les noms des joueurs.
     */
    public function findWithPlayers(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT s.*,
                    u1.username AS player1_name,
                    u2.username AS player2_name,
                    uw.username AS winner_name
             FROM {$this->table} s
             JOIN users u1 ON u1.id = s.player1_id
             LEFT JOIN users u2 ON u2.id = s.player2_id
             LEFT JOIN users uw ON uw.id = s.winner_id
             WHERE s.id = :id",
            ['id' => $id]
        );
        $row = $stmt->fetch();
        if ($row) {
            $row['game_state'] = json_decode($row['game_state'], true);
        }
        return $row ?: null;
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
                    u1.username AS player1_name,
                    u2.username AS player2_name
             FROM {$this->table} s
             JOIN users u1 ON u1.id = s.player1_id
             LEFT JOIN users u2 ON u2.id = s.player2_id
             WHERE {$where}
             ORDER BY FIELD(s.status, 'waiting', 'in_progress', 'completed', 'cancelled'),
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
             WHERE id = :id AND created_by = :uid AND status IN ('waiting', 'in_progress')",
            ['id' => $id, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }
}
