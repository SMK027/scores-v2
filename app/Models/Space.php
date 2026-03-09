<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Space - Gestion des espaces de jeu.
 */
class Space extends Model
{
    protected string $table = 'spaces';

    /**
     * Retourne les espaces dont l'utilisateur est membre.
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->query(
            "SELECT s.*, sm.role as user_role, u.username as creator_name,
                    (SELECT COUNT(*) FROM space_members WHERE space_id = s.id) as member_count
             FROM {$this->table} s
             INNER JOIN space_members sm ON s.id = sm.space_id AND sm.user_id = :user_id
             LEFT JOIN users u ON s.created_by = u.id
             ORDER BY s.updated_at DESC",
            ['user_id' => $userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Retourne un espace avec les infos supplémentaires.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT s.*, u.username as creator_name,
                    (SELECT COUNT(*) FROM space_members WHERE space_id = s.id) as member_count,
                    (SELECT COUNT(*) FROM games WHERE space_id = s.id) as game_count,
                    (SELECT COUNT(*) FROM players WHERE space_id = s.id) as player_count,
                    (SELECT COUNT(*) FROM game_types WHERE space_id = s.id) as game_type_count
             FROM {$this->table} s
             LEFT JOIN users u ON s.created_by = u.id
             WHERE s.id = :id",
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Clés de restriction possibles.
     */
    public const RESTRICTION_KEYS = [
        'games'        => 'Création/modification/suppression de parties',
        'members'      => 'Ajout/modification des membres',
        'invites'      => 'Création de liens d\'invitation',
        'competitions' => 'Création de compétitions',
        'game_types'   => 'Création/modification/suppression de types de jeux',
    ];

    /**
     * Retourne les restrictions actives d'un espace (tableau associatif).
     */
    public function getRestrictions(int $id): array
    {
        $space = $this->find($id);
        if (!$space || empty($space['restrictions'])) {
            return [];
        }
        $data = json_decode($space['restrictions'], true);
        return is_array($data) ? $data : [];
    }

    /**
     * Vérifie si une fonctionnalité est restreinte pour un espace.
     */
    public function isRestricted(int $spaceId, string $key): bool
    {
        $restrictions = $this->getRestrictions($spaceId);
        return !empty($restrictions[$key]);
    }

    /**
     * Met à jour les restrictions d'un espace.
     */
    public function setRestrictions(int $id, array $restrictions, ?string $reason, int $adminId): bool
    {
        $active = array_filter($restrictions);
        $json = empty($active) ? null : json_encode($active, JSON_UNESCAPED_UNICODE);

        $stmt = $this->query(
            "UPDATE {$this->table}
             SET restrictions = :restrictions,
                 restriction_reason = :reason,
                 restricted_by = :admin_id,
                 restricted_at = :at
             WHERE id = :id",
            [
                'restrictions' => $json,
                'reason'       => empty($active) ? null : $reason,
                'admin_id'     => empty($active) ? null : $adminId,
                'at'           => empty($active) ? null : date('Y-m-d H:i:s'),
                'id'           => $id,
            ]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un espace a au moins une restriction active.
     */
    public function hasAnyRestriction(int $id): bool
    {
        return !empty($this->getRestrictions($id));
    }

    // ─── Auto-destruction programmée ──────────────────────────

    /**
     * Programme la suppression automatique d'un espace (datetime en Europe/Paris).
     */
    public function scheduleDeletion(int $id, string $datetimeParis, string $reason, int $adminId): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET scheduled_deletion_at = :dt,
                 deletion_reason = :reason,
                 deletion_scheduled_by = :admin_id
             WHERE id = :id",
            [
                'dt'       => $datetimeParis,
                'reason'   => $reason,
                'admin_id' => $adminId,
                'id'       => $id,
            ]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Annule la suppression programmée d'un espace.
     */
    public function cancelDeletion(int $id): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table}
             SET scheduled_deletion_at = NULL,
                 deletion_reason = NULL,
                 deletion_scheduled_by = NULL
             WHERE id = :id",
            ['id' => $id]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un espace est programmé pour suppression.
     */
    public function isScheduledForDeletion(int $id): bool
    {
        $space = $this->find($id);
        return $space && !empty($space['scheduled_deletion_at']);
    }

    /**
     * Retourne tous les espaces dont la date de suppression est dépassée.
     */
    public function findDueForDeletion(): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s');
        $stmt = $this->query(
            "SELECT * FROM {$this->table}
             WHERE scheduled_deletion_at IS NOT NULL
               AND scheduled_deletion_at <= :now",
            ['now' => $now]
        );
        return $stmt->fetchAll();
    }
}
