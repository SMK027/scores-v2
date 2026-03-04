<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle GamePlayer - Joueurs participant à une partie.
 */
class GamePlayer extends Model
{
    protected string $table = 'game_players';

    /**
     * Récupère les joueurs d'une partie avec leurs scores.
     */
    public function findByGame(int $gameId): array
    {
        $stmt = $this->query(
            "SELECT gp.*, p.name as player_name
             FROM {$this->table} gp
             INNER JOIN players p ON gp.player_id = p.id
             ORDER BY gp.`rank` ASC, gp.total_score DESC",
            []
        );
        // Utilisation avec WHERE
        $stmt = $this->query(
            "SELECT gp.*, p.name as player_name
             FROM {$this->table} gp
             INNER JOIN players p ON gp.player_id = p.id
             WHERE gp.game_id = :game_id
             ORDER BY CASE WHEN gp.`rank` IS NULL THEN 1 ELSE 0 END, gp.`rank` ASC, gp.total_score DESC",
            ['game_id' => $gameId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Ajoute des joueurs à une partie.
     */
    public function addPlayers(int $gameId, array $playerIds): void
    {
        foreach ($playerIds as $playerId) {
            $this->create([
                'game_id'   => $gameId,
                'player_id' => (int) $playerId,
            ]);
        }
    }

    /**
     * Supprime tous les joueurs d'une partie.
     */
    public function removeAllByGame(int $gameId): void
    {
        $this->query("DELETE FROM {$this->table} WHERE game_id = :game_id", ['game_id' => $gameId]);
    }
}
