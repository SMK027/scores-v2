<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Comment - Commentaires sur les parties.
 */
class Comment extends Model
{
    protected string $table = 'comments';

    /**
     * Récupère les commentaires d'une partie.
     */
    public function findByGame(int $gameId): array
    {
        $stmt = $this->query(
            "SELECT c.*, u.username, u.avatar
             FROM {$this->table} c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.game_id = :game_id
             ORDER BY c.created_at ASC",
            ['game_id' => $gameId]
        );
        return $stmt->fetchAll();
    }
}
