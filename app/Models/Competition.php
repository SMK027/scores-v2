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
        $stmt = $this->query(
            "SELECT c.*, u.username AS creator_name,
                   (SELECT COUNT(*) FROM competition_sessions cs WHERE cs.competition_id = c.id) AS session_count
            FROM {$this->table} c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.space_id = :space_id
            ORDER BY c.starts_at DESC",
            ['space_id' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Prochaine competition planifiee d'un espace.
     */
    public function findNextBySpace(int $spaceId): ?array
    {
        $stmt = $this->query(
            "SELECT c.*, u.username AS creator_name,
                   (SELECT COUNT(*) FROM competition_sessions cs WHERE cs.competition_id = c.id) AS session_count
            FROM {$this->table} c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.space_id = :space_id
              AND c.status = 'planned'
              AND c.starts_at >= NOW()
            ORDER BY c.starts_at ASC
            LIMIT 1",
            ['space_id' => $spaceId]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Détail d'une compétition avec infos complémentaires.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT c.*, u.username AS creator_name, s.name AS space_name,
                   (SELECT COUNT(*) FROM competition_sessions cs WHERE cs.competition_id = c.id) AS session_count,
                   (SELECT COUNT(*) FROM games g WHERE g.competition_id = c.id) AS game_count,
                   (SELECT COUNT(*) FROM competition_game_types cgt WHERE cgt.competition_id = c.id) AS allowed_game_type_count,
                   (SELECT COUNT(*) FROM competition_players cp WHERE cp.competition_id = c.id) AS participant_count
            FROM {$this->table} c
            LEFT JOIN users u ON u.id = c.created_by
            LEFT JOIN spaces s ON s.id = c.space_id
            WHERE c.id = :id",
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Clôture générale : désactive toutes les sessions et passe le statut à closed.
     */
    public function closeCompetition(int $id): void
    {
        $this->query(
            "UPDATE competition_sessions SET is_active = 0 WHERE competition_id = :id",
            ['id' => $id]
        );

        $this->update($id, ['status' => 'closed']);
    }

    /**
     * Active la compétition.
     */
    public function activate(int $id): void
    {
        $this->update($id, ['status' => 'active']);
    }

    /**
     * Met la compétition en pause (les sessions restent actives mais bloquées).
     */
    public function pause(int $id): void
    {
        $this->update($id, ['status' => 'paused']);
    }

    /**
     * Reprend une compétition en pause.
     */
    public function resume(int $id): void
    {
        $this->update($id, ['status' => 'active']);
    }

    /**
     * Types de jeu autorisés pour une compétition.
     */
    public function getAllowedGameTypes(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT gt.*
             FROM competition_game_types cgt
             INNER JOIN game_types gt ON gt.id = cgt.game_type_id
             WHERE cgt.competition_id = :cid
             ORDER BY gt.name ASC"
        );
        $stmt->execute(['cid' => $competitionId]);
        return $stmt->fetchAll();
    }

    /**
     * IDs des types de jeu autorisés pour une compétition.
     */
    public function getAllowedGameTypeIds(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT game_type_id
             FROM competition_game_types
             WHERE competition_id = :cid"
        );
        $stmt->execute(['cid' => $competitionId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'game_type_id'));
    }

    /**
     * Synchronise la liste des types de jeu autorisés.
     */
    public function syncAllowedGameTypes(int $competitionId, int $spaceId, array $gameTypeIds): void
    {
        $gameTypeIds = array_values(array_unique(array_map('intval', $gameTypeIds)));
        if (empty($gameTypeIds)) {
            throw new \InvalidArgumentException('Au moins un type de jeu doit être autorisé pour la compétition.');
        }

        $placeholders = implode(',', array_fill(0, count($gameTypeIds), '?'));
        $params = array_merge([$spaceId], $gameTypeIds);

        $check = $this->db->prepare(
            "SELECT id
             FROM game_types
             WHERE (space_id = ? OR is_global = 1)
               AND id IN ({$placeholders})"
        );
        $check->execute($params);
        $validIds = array_map('intval', array_column($check->fetchAll(), 'id'));

        if (count($validIds) !== count($gameTypeIds)) {
            throw new \InvalidArgumentException('Un ou plusieurs types de jeu sélectionnés ne sont pas valides pour cet espace.');
        }

        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare("DELETE FROM competition_game_types WHERE competition_id = :cid");
            $delete->execute(['cid' => $competitionId]);

            $insert = $this->db->prepare(
                "INSERT INTO competition_game_types (competition_id, game_type_id) VALUES (:cid, :gtid)"
            );
            foreach ($validIds as $gtId) {
                $insert->execute(['cid' => $competitionId, 'gtid' => $gtId]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Liste des joueurs inscrits à une compétition.
     */
    public function getRegisteredPlayers(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.username AS linked_username, cp.added_by, cp.created_at AS registered_at
             FROM competition_players cp
             INNER JOIN players p ON p.id = cp.player_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE cp.competition_id = :cid
                    AND p.deleted_at IS NULL
             ORDER BY p.name ASC"
        );
        $stmt->execute(['cid' => $competitionId]);
        return $stmt->fetchAll();
    }

    /**
     * Vérifie si un joueur est inscrit à une compétition.
     */
    public function isPlayerRegistered(int $competitionId, int $playerId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM competition_players
             WHERE competition_id = :cid AND player_id = :pid
             LIMIT 1"
        );
        $stmt->execute(['cid' => $competitionId, 'pid' => $playerId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Inscrit un joueur à une compétition.
     */
    public function registerPlayer(int $competitionId, int $playerId, ?int $addedBy = null): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO competition_players (competition_id, player_id, added_by)
             VALUES (:cid, :pid, :added_by)"
        );
        $stmt->execute([
            'cid' => $competitionId,
            'pid' => $playerId,
            'added_by' => $addedBy,
        ]);
    }

    /**
     * Désinscrit un joueur d'une compétition.
     */
    public function unregisterPlayer(int $competitionId, int $playerId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM competition_players
             WHERE competition_id = :cid AND player_id = :pid"
        );
        $stmt->execute(['cid' => $competitionId, 'pid' => $playerId]);
    }
}
