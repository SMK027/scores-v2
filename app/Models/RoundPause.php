<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle pour les pauses de manches.
 * Chaque enregistrement représente une période de pause.
 */
class RoundPause extends Model
{
    protected string $table = 'round_pauses';

    /**
     * Crée un enregistrement de pause (début de pause).
     */
    public function startPause(int $roundId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (round_id, paused_at)
            VALUES (:round_id, NOW())
        ");
        $stmt->execute(['round_id' => $roundId]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Termine la pause en cours pour une manche (reprise du jeu).
     */
    public function endPause(int $roundId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET resumed_at = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, paused_at, NOW())
            WHERE round_id = :round_id
              AND resumed_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        return $stmt->execute(['round_id' => $roundId]);
    }

    /**
     * Termine toutes les pauses ouvertes d'une manche (en cas de terminaison directe).
     */
    public function endAllOpenPauses(int $roundId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET resumed_at = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, paused_at, NOW())
            WHERE round_id = :round_id
              AND resumed_at IS NULL
        ");
        return $stmt->execute(['round_id' => $roundId]);
    }

    /**
     * Vérifie s'il y a une pause en cours (non terminée) pour une manche.
     */
    public function hasOpenPause(int $roundId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE round_id = :round_id
              AND resumed_at IS NULL
        ");
        $stmt->execute(['round_id' => $roundId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère toutes les pauses d'une manche.
     */
    public function findByRound(int $roundId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE round_id = :round_id
            ORDER BY paused_at ASC
        ");
        $stmt->execute(['round_id' => $roundId]);
        return $stmt->fetchAll();
    }

    /**
     * Calcule le temps total de pause en secondes pour une manche.
     */
    public function getTotalPauseSeconds(int $roundId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN duration_seconds IS NOT NULL THEN duration_seconds
                    WHEN resumed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, paused_at, resumed_at)
                    ELSE TIMESTAMPDIFF(SECOND, paused_at, NOW())
                END
            ), 0) AS total_pause
            FROM {$this->table}
            WHERE round_id = :round_id
        ");
        $stmt->execute(['round_id' => $roundId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Calcule le temps total de pause en secondes pour plusieurs manches.
     * Retourne un tableau indexé par round_id.
     */
    public function getTotalPauseSecondsByRounds(array $roundIds): array
    {
        if (empty($roundIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roundIds), '?'));
        $stmt = $this->db->prepare("
            SELECT round_id,
                   COALESCE(SUM(
                       CASE
                           WHEN duration_seconds IS NOT NULL THEN duration_seconds
                           WHEN resumed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, paused_at, resumed_at)
                           ELSE TIMESTAMPDIFF(SECOND, paused_at, NOW())
                       END
                   ), 0) AS total_pause
            FROM {$this->table}
            WHERE round_id IN ({$placeholders})
            GROUP BY round_id
        ");
        $stmt->execute($roundIds);

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[(int) $row['round_id']] = (int) $row['total_pause'];
        }
        return $result;
    }
}
