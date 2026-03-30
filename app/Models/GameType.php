<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle GameType - Types de jeux disponibles dans un espace ou globalement.
 */
class GameType extends Model
{
    protected string $table = 'game_types';

    /**
     * Sous-requête SQL pour le temps moyen effectif de manche (en secondes).
     * Durée effective = (ended_at - started_at) - somme des pauses.
     * Ne prend en compte que les manches terminées.
     */
    private function avgRoundDurationSubquery(): string
    {
        return "(SELECT ROUND(AVG(
                    TIMESTAMPDIFF(SECOND, r.started_at, r.ended_at)
                    - COALESCE((SELECT SUM(rp.duration_seconds) FROM round_pauses rp WHERE rp.round_id = r.id), 0)
                ))
                FROM rounds r
                INNER JOIN games g ON g.id = r.game_id
                WHERE g.game_type_id = gt.id
                  AND r.status = 'completed'
                  AND r.started_at IS NOT NULL
                  AND r.ended_at IS NOT NULL
               ) as avg_round_duration";
    }

    /**
     * Liste les types de jeux d'un espace (propres + globaux).
     */
    public function findBySpace(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT gt.*,
                    (SELECT COUNT(*) FROM games WHERE game_type_id = gt.id) as game_count,
                    {$this->avgRoundDurationSubquery()}
             FROM {$this->table} gt
             WHERE gt.space_id = :space_id OR gt.is_global = 1
             ORDER BY gt.is_global ASC, gt.name ASC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Liste uniquement les types de jeux propres à un espace (sans les globaux).
     */
    public function findBySpaceOnly(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT gt.*,
                    (SELECT COUNT(*) FROM games WHERE game_type_id = gt.id) as game_count,
                    {$this->avgRoundDurationSubquery()}
             FROM {$this->table} gt
             WHERE gt.space_id = :space_id AND gt.is_global = 0
             ORDER BY gt.name ASC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Liste tous les types de jeux globaux.
     */
    public function findGlobal(): array
    {
        $stmt = $this->query(
            "SELECT gt.*,
                    (SELECT COUNT(*) FROM games WHERE game_type_id = gt.id) as game_count,
                    {$this->avgRoundDurationSubquery()}
             FROM {$this->table} gt
             WHERE gt.is_global = 1
             ORDER BY gt.name ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Vérifie qu'un type de jeu est accessible depuis un espace donné.
     * Un type est accessible s'il appartient à l'espace ou s'il est global.
     */
    public function isAccessibleInSpace(int $gameTypeId, int $spaceId): bool
    {
        $gt = $this->find($gameTypeId);
        if (!$gt) {
            return false;
        }
        return (int) $gt['is_global'] === 1 || (int) $gt['space_id'] === $spaceId;
    }
}
