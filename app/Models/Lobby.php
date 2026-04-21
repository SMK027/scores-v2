<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Lobby — salons persistants pour jeux interactifs.
 */
class Lobby extends Model
{
    protected string $table = 'lobbies';

    /**
     * Crée un lobby.
     */
    public function createLobby(int $spaceId, int $userId, string $name, string $gameKey, array $gameConfig, string $visibility = 'public'): int
    {
        $this->query(
            "INSERT INTO {$this->table} (space_id, created_by, name, game_key, game_config, visibility)
             VALUES (:space_id, :created_by, :name, :game_key, :game_config, :visibility)",
            [
                'space_id'    => $spaceId,
                'created_by'  => $userId,
                'name'        => $name,
                'game_key'    => $gameKey,
                'game_config' => json_encode($gameConfig),
                'visibility'  => $visibility,
            ]
        );
        $lobbyId = (int) $this->db->lastInsertId();

        // L'hôte est automatiquement membre
        $this->addMember($lobbyId, $userId);

        return $lobbyId;
    }

    /**
     * Récupère un lobby avec ses membres.
     */
    public function findWithMembers(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT l.*, u.username AS creator_name
             FROM {$this->table} l
             JOIN users u ON u.id = l.created_by
             WHERE l.id = :id",
            ['id' => $id]
        );
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['game_config'] = json_decode($row['game_config'], true);
        $row['members'] = $this->getMembers($id);
        $row['invitations'] = $this->getPendingInvitations($id);
        return $row;
    }

    /**
     * Liste les lobbies d'un espace (ouverts d'abord).
     */
    public function findBySpace(int $spaceId): array
    {
        $stmt = $this->query(
            "SELECT l.*, u.username AS creator_name,
                    COUNT(lm.id) AS member_count
             FROM {$this->table} l
             JOIN users u ON u.id = l.created_by
             LEFT JOIN lobby_members lm ON lm.lobby_id = l.id
             WHERE l.space_id = :space_id AND l.status != 'closed'
             GROUP BY l.id
             ORDER BY FIELD(l.status, 'open', 'in_game', 'closed'), l.updated_at DESC
             LIMIT 50",
            ['space_id' => $spaceId]
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['game_config'] = json_decode($row['game_config'], true);
        }
        return $rows;
    }

    /**
     * Récupère les membres d'un lobby.
     */
    public function getMembers(int $lobbyId): array
    {
        $stmt = $this->query(
            "SELECT lm.*, u.username, u.avatar
             FROM lobby_members lm
             JOIN users u ON u.id = lm.user_id
             WHERE lm.lobby_id = :lobby_id
             ORDER BY lm.joined_at ASC",
            ['lobby_id' => $lobbyId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Ajoute un membre au lobby.
     */
    public function addMember(int $lobbyId, int $userId): bool
    {
        // Vérifier doublon
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM lobby_members WHERE lobby_id = :lid AND user_id = :uid",
            ['lid' => $lobbyId, 'uid' => $userId]
        );
        if ((int) $stmt->fetch()['cnt'] > 0) {
            return false;
        }
        $this->query(
            "INSERT INTO lobby_members (lobby_id, user_id) VALUES (:lid, :uid)",
            ['lid' => $lobbyId, 'uid' => $userId]
        );
        return true;
    }

    /**
     * Retire un membre du lobby.
     */
    public function removeMember(int $lobbyId, int $userId): bool
    {
        $stmt = $this->query(
            "DELETE FROM lobby_members WHERE lobby_id = :lid AND user_id = :uid",
            ['lid' => $lobbyId, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un utilisateur est membre d'un lobby.
     */
    public function isMember(int $lobbyId, int $userId): bool
    {
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM lobby_members WHERE lobby_id = :lid AND user_id = :uid",
            ['lid' => $lobbyId, 'uid' => $userId]
        );
        return (int) $stmt->fetch()['cnt'] > 0;
    }

    /**
     * Invite un utilisateur au lobby.
     */
    public function invite(int $lobbyId, int $invitedUserId, int $invitedBy): bool
    {
        // Vérifier doublon
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM lobby_invitations WHERE lobby_id = :lid AND invited_user_id = :uid",
            ['lid' => $lobbyId, 'uid' => $invitedUserId]
        );
        if ((int) $stmt->fetch()['cnt'] > 0) {
            return false;
        }
        $this->query(
            "INSERT INTO lobby_invitations (lobby_id, invited_user_id, invited_by)
             VALUES (:lid, :uid, :by)",
            ['lid' => $lobbyId, 'uid' => $invitedUserId, 'by' => $invitedBy]
        );
        return true;
    }

    /**
     * Récupère les invitations en attente d'un lobby.
     */
    public function getPendingInvitations(int $lobbyId): array
    {
        $stmt = $this->query(
            "SELECT li.*, u.username AS invited_username
             FROM lobby_invitations li
             JOIN users u ON u.id = li.invited_user_id
             WHERE li.lobby_id = :lid AND li.status = 'pending'
             ORDER BY li.created_at DESC",
            ['lid' => $lobbyId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère les invitations reçues par un utilisateur dans un espace.
     */
    public function getInvitationsForUser(int $spaceId, int $userId): array
    {
        $stmt = $this->query(
            "SELECT li.*, l.name AS lobby_name, l.game_key, u.username AS invited_by_name
             FROM lobby_invitations li
             JOIN lobbies l ON l.id = li.lobby_id
             JOIN users u ON u.id = li.invited_by
             WHERE li.invited_user_id = :uid AND l.space_id = :sid AND li.status = 'pending' AND l.status = 'open'
             ORDER BY li.created_at DESC",
            ['uid' => $userId, 'sid' => $spaceId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Accepte une invitation (ajoute au lobby).
     */
    public function acceptInvitation(int $invitationId, int $userId): bool
    {
        $stmt = $this->query(
            "SELECT * FROM lobby_invitations WHERE id = :id AND invited_user_id = :uid AND status = 'pending'",
            ['id' => $invitationId, 'uid' => $userId]
        );
        $inv = $stmt->fetch();
        if (!$inv) {
            return false;
        }
        $this->query(
            "UPDATE lobby_invitations SET status = 'accepted' WHERE id = :id",
            ['id' => $invitationId]
        );
        $this->addMember((int) $inv['lobby_id'], $userId);
        return true;
    }

    /**
     * Décline une invitation.
     */
    public function declineInvitation(int $invitationId, int $userId): bool
    {
        $stmt = $this->query(
            "UPDATE lobby_invitations SET status = 'declined'
             WHERE id = :id AND invited_user_id = :uid AND status = 'pending'",
            ['id' => $invitationId, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Passe le lobby en mode « en jeu ».
     */
    public function setInGame(int $lobbyId, int $sessionId): void
    {
        $this->query(
            "UPDATE {$this->table} SET status = 'in_game', current_session_id = :sid, updated_at = NOW() WHERE id = :id",
            ['sid' => $sessionId, 'id' => $lobbyId]
        );
    }

    /**
     * Repasse le lobby en mode « ouvert » après fin de partie.
     */
    public function setOpen(int $lobbyId): void
    {
        $this->query(
            "UPDATE {$this->table} SET status = 'open', current_session_id = NULL, updated_at = NOW() WHERE id = :id",
            ['id' => $lobbyId]
        );
    }

    /**
     * Ferme un lobby.
     */
    public function closeLobby(int $lobbyId, int $userId): bool
    {
        $stmt = $this->query(
            "UPDATE {$this->table} SET status = 'closed', updated_at = NOW()
             WHERE id = :id AND created_by = :uid AND status IN ('open', 'in_game')",
            ['id' => $lobbyId, 'uid' => $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Recherche les membres de l'espace pouvant être invités (pas déjà membres/invités).
     */
    public function searchInvitableMembers(int $lobbyId, int $spaceId, string $search): array
    {
        $stmt = $this->query(
            "SELECT u.id, u.username, u.avatar
             FROM space_members sm
             JOIN users u ON u.id = sm.user_id
             WHERE sm.space_id = :sid
               AND u.username LIKE :search
               AND u.id NOT IN (SELECT user_id FROM lobby_members WHERE lobby_id = :lid)
               AND u.id NOT IN (SELECT invited_user_id FROM lobby_invitations WHERE lobby_id = :lid2 AND status = 'pending')
               AND u.is_bot = 0
             ORDER BY u.username ASC
             LIMIT 10",
            ['sid' => $spaceId, 'search' => '%' . $search . '%', 'lid' => $lobbyId, 'lid2' => $lobbyId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Nombre de membres dans un lobby.
     */
    public function getMemberCount(int $lobbyId): int
    {
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM lobby_members WHERE lobby_id = :lid",
            ['lid' => $lobbyId]
        );
        return (int) $stmt->fetch()['cnt'];
    }

    /**
     * Récupère les détails d'une invitation lobby (pour notifications).
     * Retourne : id, lobby_id, invited_user_id, invited_by, status + lobby.name, lobby.space_id, lobby.created_by
     */
    public function findInvitationById(int $invId): ?array
    {
        $stmt = $this->query(
            "SELECT li.*, l.name AS lobby_name, l.space_id, l.created_by AS lobby_host_id
             FROM lobby_invitations li
             JOIN lobbies l ON l.id = li.lobby_id
             WHERE li.id = :id",
            ['id' => $invId]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
