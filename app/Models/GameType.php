<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle GameType - Types de jeux disponibles dans un espace.
 */
class GameType extends Model
{
    protected string $table = 'game_types';

    /**
     * Liste les types de jeux d'un espace.
     */
    public function findBySpace(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT gt.*,
                    (SELECT COUNT(*) FROM games WHERE game_type_id = gt.id) as game_count
             FROM {$this->table} gt
             WHERE gt.space_id = :space_id
             ORDER BY gt.name ASC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }
}
