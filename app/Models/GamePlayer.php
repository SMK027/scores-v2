<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle GamePlayer - Joueurs participant à une partie.
 */
class GamePlayer extends Model
{
    protected string $table = 'game_players';

    /**
     * Vérifie qu'aucun joueur lié à un compte restreint compétition
     * n'est ajouté à une partie de compétition.
     *
     * @throws \DomainException
     */
    private function assertCompetitionEligiblePlayers(int $gameId, array $playerIds): void
    {
        if (empty($playerIds)) {
            return;
        }

        $gameStmt = $this->query(
            "SELECT competition_id FROM games WHERE id = :game_id LIMIT 1",
            ['game_id' => $gameId]
        );
        $game = $gameStmt->fetch();
        if (!$game || empty($game['competition_id'])) {
            return;
        }

        $normalizedIds = array_values(array_unique(array_map('intval', $playerIds)));
        if (empty($normalizedIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name, p.user_id
             FROM players p
             WHERE p.id IN ({$placeholders})"
        );
        $stmt->execute($normalizedIds);
        $players = $stmt->fetchAll();

        $userModel = new User();
        $blockedNames = [];
        foreach ($players as $player) {
            $userId = (int) ($player['user_id'] ?? 0);
            if ($userId > 0 && $userModel->isRestricted($userId, 'competitions_participation')) {
                $blockedNames[] = (string) $player['name'];
            }
        }

        if (!empty($blockedNames)) {
            throw new \DomainException(
                'Impossible de rattacher à une partie de compétition : ' . implode(', ', $blockedNames) . '.'
            );
        }
    }

    /**
     * Récupère les joueurs d'une partie avec leurs scores.
     */
    public function findByGame(int $gameId): array
    {
        $stmt = $this->query(
            "SELECT gp.*, p.name as player_name
             FROM {$this->table} gp
             INNER JOIN players p ON gp.player_id = p.id
             ORDER BY gp.`rank` ASC, gp.total_score DESC",
            []
        );
        // Utilisation avec WHERE
        $stmt = $this->query(
            "SELECT gp.*, p.name as player_name
             FROM {$this->table} gp
             INNER JOIN players p ON gp.player_id = p.id
             WHERE gp.game_id = :game_id
             ORDER BY CASE WHEN gp.`rank` IS NULL THEN 1 ELSE 0 END, gp.`rank` ASC, gp.total_score DESC",
            ['game_id' => $gameId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Ajoute des joueurs à une partie.
     */
    public function addPlayers(int $gameId, array $playerIds): void
    {
        $this->assertCompetitionEligiblePlayers($gameId, $playerIds);

        foreach ($playerIds as $playerId) {
            $this->create([
                'game_id'   => $gameId,
                'player_id' => (int) $playerId,
            ]);
        }
    }

    /**
     * Supprime tous les joueurs d'une partie.
     */
    public function removeAllByGame(int $gameId): void
    {
        $this->query("DELETE FROM {$this->table} WHERE game_id = :game_id", ['game_id' => $gameId]);
    }
}
