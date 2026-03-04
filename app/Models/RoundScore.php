<?php

namespace App\Models;

use App\Core\Model;

class RoundScore extends Model
{
    protected string $table = 'round_scores';

    /**
     * Récupère les scores d'une manche avec les noms des joueurs
     */
    public function findByRound(int $roundId): array
    {
        $stmt = $this->db->prepare("
            SELECT rs.*, p.name AS player_name
            FROM {$this->table} rs
            JOIN players p ON p.id = rs.player_id
            WHERE rs.round_id = :round_id
            ORDER BY p.name ASC
        ");
        $stmt->execute(['round_id' => $roundId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les scores d'une manche indexés par player_id
     */
    public function findByRoundIndexed(int $roundId): array
    {
        $scores = $this->findByRound($roundId);
        $indexed = [];
        foreach ($scores as $score) {
            $indexed[$score['player_id']] = $score;
        }
        return $indexed;
    }

    /**
     * Enregistre ou met à jour les scores d'une manche
     */
    public function saveScores(int $roundId, array $scores): void
    {
        // Supprimer les scores existants pour cette manche
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE round_id = :round_id");
        $stmt->execute(['round_id' => $roundId]);

        // Insérer les nouveaux scores
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (round_id, player_id, score)
            VALUES (:round_id, :player_id, :score)
        ");

        foreach ($scores as $playerId => $score) {
            if ($score !== '' && $score !== null) {
                $stmt->execute([
                    'round_id'  => $roundId,
                    'player_id' => (int) $playerId,
                    'score'     => (float) $score,
                ]);
            }
        }
    }

    /**
     * Récupère tous les scores de toutes les manches d'une partie, groupés par round_id
     */
    public function findByGame(int $gameId): array
    {
        $stmt = $this->db->prepare("
            SELECT rs.*, p.name AS player_name
            FROM {$this->table} rs
            JOIN rounds r ON r.id = rs.round_id
            JOIN players p ON p.id = rs.player_id
            WHERE r.game_id = :game_id
            ORDER BY r.round_number ASC, p.name ASC
        ");
        $stmt->execute(['game_id' => $gameId]);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['round_id']][$row['player_id']] = $row;
        }
        return $grouped;
    }
}
