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
        $stmt = $this->db->prepare("
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
        if ($this->hasActiveRound($gameId)) {
            throw new \DomainException('Terminez la manche en cours avant d\'en créer une nouvelle.');
        }

        // Récupérer le prochain numéro de manche
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(round_number), 0) + 1 AS next_number
            FROM {$this->table}
            WHERE game_id = :game_id
        ");
        $stmt->execute(['game_id' => $gameId]);
        $nextNumber = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (game_id, round_number, status, started_at, created_at)
            VALUES (:game_id, :round_number, 'in_progress', NOW(), NOW())
        ");
        $stmt->execute([
            'game_id'      => $gameId,
            'round_number' => $nextNumber,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Crée une nouvelle manche déjà terminée avec une durée fixée (en secondes).
     * started_at = NOW() - duration, ended_at = NOW(), status = 'completed'.
     */
    public function createForGameWithDuration(int $gameId, int $durationSeconds, ?string $notes = null): int
    {
        if ($this->hasActiveRound($gameId)) {
            throw new \DomainException('Terminez la manche en cours avant d\'en créer une nouvelle.');
        }

        $durationSeconds = max(0, $durationSeconds);

        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(round_number), 0) + 1 AS next_number
            FROM {$this->table}
            WHERE game_id = :game_id
        ");
        $stmt->execute(['game_id' => $gameId]);
        $nextNumber = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (game_id, round_number, status, started_at, ended_at, created_at)
            VALUES (:game_id, :round_number, 'completed', DATE_SUB(NOW(), INTERVAL :duration SECOND), NOW(), NOW())
        ");
        $stmt->execute([
            'game_id'      => $gameId,
            'round_number' => $nextNumber,
            'duration'     => $durationSeconds,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Vérifie si la partie a une manche non terminée (in_progress ou paused).
     */
    public function hasActiveRound(int $gameId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE game_id = :game_id AND status != 'completed'
        ");
        $stmt->execute(['game_id' => $gameId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Met à jour le statut d'une manche
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sets = ['status = :status'];
        $params = ['id' => $id, 'status' => $status];

        if ($status === 'completed') {
            $sets[] = 'ended_at = NOW()';
        }

        $setStr = implode(', ', $sets);
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET {$setStr}
            WHERE id = :id
        ");
        return $stmt->execute($params);
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
     * Renuméro les manches d'une partie (1, 2, 3, …) après suppression.
     */
    public function renumberRounds(int $gameId): void
    {
        $stmt = $this->db->prepare("
            SELECT id FROM {$this->table}
            WHERE game_id = :game_id
            ORDER BY round_number ASC, id ASC
        ");
        $stmt->execute(['game_id' => $gameId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $update = $this->db->prepare("
            UPDATE {$this->table} SET round_number = :num WHERE id = :id
        ");
        foreach ($rows as $i => $roundId) {
            $update->execute(['num' => $i + 1, 'id' => $roundId]);
        }
    }

    /**
     * Compte les manches d'une partie
     */
    public function countByGame(int $gameId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE game_id = :game_id
        ");
        $stmt->execute(['game_id' => $gameId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Calcule la durée brute d'une manche en secondes (ended_at - started_at).
     * Si la manche n'est pas terminée, calcule jusqu'à maintenant.
     */
    public function getRawDurationSeconds(array $round): int
    {
        if (empty($round['started_at'])) {
            return 0;
        }

        $start = new \DateTime($round['started_at']);
        $end = !empty($round['ended_at'])
            ? new \DateTime($round['ended_at'])
            : new \DateTime();

        return max(0, $end->getTimestamp() - $start->getTimestamp());
    }

    /**
     * Calcule la durée effective de jeu d'une manche (brute - pauses).
     */
    public function getPlayDurationSeconds(array $round, int $pauseSeconds): int
    {
        $raw = $this->getRawDurationSeconds($round);
        return max(0, $raw - $pauseSeconds);
    }
}
