<?php

namespace App\Models;

use App\Core\Model;

class Round extends Model
{
    protected string $table = 'rounds';

    /**
     * Récupère toutes les manches d'une partie, triées par numéro
     */
    public function findByGame(int $gameId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE game_id = :game_id
            ORDER BY round_number ASC
        ");
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetchAll();
    }

    /**
     * Crée une nouvelle manche avec numéro auto-incrémenté
     */
    public function createForGame(int $gameId, ?string $notes = null): int
    {
        // Récupérer le prochain numéro de manche
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(round_number), 0) + 1 AS next_number
            FROM {$this->table}
            WHERE game_id = :game_id
        ");
        $stmt->execute(['game_id' => $gameId]);
        $nextNumber = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (game_id, round_number, status, notes, created_at)
            VALUES (:game_id, :round_number, 'in_progress', :notes, NOW())
        ");
        $stmt->execute([
            'game_id'      => $gameId,
            'round_number' => $nextNumber,
            'notes'        => $notes,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour le statut d'une manche
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET status = :status
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Supprime une manche et ses scores associés
     */
    public function deleteWithScores(int $id): bool
    {
        // Les scores seront supprimés en cascade (FK ON DELETE CASCADE)
        return $this->delete($id);
    }

    /**
     * Compte les manches d'une partie
     */
    public function countByGame(int $gameId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE game_id = :game_id
        ");
        $stmt->execute(['game_id' => $gameId]);
        return (int) $stmt->fetchColumn();
    }
}
