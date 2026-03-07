<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Competition – Compétitions organisées sur un espace.
 */
class Competition extends Model
{
    protected string $table = 'competitions';

    /**
     * Compétitions d'un espace, triées par date de début desc.
     */
    public function findBySpace(int $spaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.username AS creator_name,
                   (SELECT COUNT(*) FROM competition_sessions cs WHERE cs.competition_id = c.id) AS session_count
            FROM {$this->table} c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.space_id = :space_id
            ORDER BY c.starts_at DESC
        ");
        $stmt->execute(['space_id' => $spaceId]);
        return $stmt->fetchAll();
    }

    /**
     * Détail d'une compétition avec infos complémentaires.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.username AS creator_name, s.name AS space_name,
                   (SELECT COUNT(*) FROM competition_sessions cs WHERE cs.competition_id = c.id) AS session_count,
                   (SELECT COUNT(*) FROM games g WHERE g.competition_id = c.id) AS game_count
            FROM {$this->table} c
            LEFT JOIN users u ON u.id = c.created_by
            LEFT JOIN spaces s ON s.id = c.space_id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Clôture générale : désactive toutes les sessions et passe le statut à closed.
     */
    public function closeCompetition(int $id): void
    {
        $this->db->prepare("
            UPDATE competition_sessions SET is_active = 0 WHERE competition_id = :id
        ")->execute(['id' => $id]);

        $this->update($id, ['status' => 'closed']);
    }

    /**
     * Active la compétition.
     */
    public function activate(int $id): void
    {
        $this->update($id, ['status' => 'active']);
    }
}
