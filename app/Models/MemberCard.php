<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle MemberCard — Cartes de membre numériques.
 *
 * Une carte est unique par (player_id, space_id).
 * Sa référence est un identifiant lisible (SC-YYYYMMDD-XXXXXXXX).
 * Sa signature est un HMAC-SHA256 signé avec APP_KEY, calculé sur
 * "{reference}|{player_id}|{space_id}|{created_at}".
 */
class MemberCard extends Model
{
    protected string $table = 'member_cards';

    // ----------------------------------------------------------------
    // Lecture
    // ----------------------------------------------------------------

    /**
     * Trouve la carte d'un joueur dans un espace (active ou non).
     */
    public function findByPlayerAndSpace(int $playerId, int $spaceId): ?array
    {
        $stmt = $this->query(
            "SELECT mc.*,
                    p.name          AS player_name,
                    p.user_id       AS player_user_id,
                    p.created_at    AS player_joined_at,
                    s.name          AS space_name,
                    COALESCE(sm.role, 'joueur') AS space_role
             FROM {$this->table} mc
             JOIN players  p  ON p.id = mc.player_id
             JOIN spaces   s  ON s.id = mc.space_id
             LEFT JOIN space_members sm
               ON sm.space_id = p.space_id AND sm.user_id = p.user_id
             WHERE mc.player_id = :player_id
               AND mc.space_id  = :space_id
             LIMIT 1",
            ['player_id' => $playerId, 'space_id' => $spaceId]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Trouve une carte par sa référence (pour la vérification publique).
     * Inclut les données associées au joueur et à l'espace.
     */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->query(
            "SELECT mc.*,
                    p.name          AS player_name,
                    p.user_id       AS player_user_id,
                    p.created_at    AS player_joined_at,
                    p.deleted_at    AS player_deleted_at,
                    s.name          AS space_name,
                    COALESCE(sm.role, 'joueur') AS space_role
             FROM {$this->table} mc
             JOIN players  p  ON p.id = mc.player_id
             JOIN spaces   s  ON s.id = mc.space_id
             LEFT JOIN space_members sm
               ON sm.space_id = p.space_id AND sm.user_id = p.user_id
             WHERE mc.reference = :ref
             LIMIT 1",
            ['ref' => $reference]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ----------------------------------------------------------------
    // Écriture
    // ----------------------------------------------------------------

    /**
     * Génère une nouvelle carte pour un joueur dans un espace.
     * Retourne les données complètes de la carte créée.
     *
     * @throws \RuntimeException si une carte existe déjà (appeler regenerate()).
     */
    public function generate(int $playerId, int $spaceId): array
    {
        $reference = self::buildReference();
        $createdAt = date('Y-m-d H:i:s');
        $signature = self::sign($reference, $playerId, $spaceId, $createdAt);

        $id = $this->create([
            'player_id' => $playerId,
            'space_id'  => $spaceId,
            'reference' => $reference,
            'signature' => $signature,
            'is_active' => 1,
        ]);

        return $this->findByPlayerAndSpace($playerId, $spaceId)
            ?? ['id' => $id, 'reference' => $reference, 'signature' => $signature, 'is_active' => 1];
    }

    /**
     * Régénère la carte (supprime l'ancienne et en crée une nouvelle).
     */
    public function regenerate(int $playerId, int $spaceId): array
    {
        $existing = $this->findByPlayerAndSpace($playerId, $spaceId);
        if ($existing) {
            $this->delete((int) $existing['id']);
        }
        return $this->generate($playerId, $spaceId);
    }

    /**
     * Active ou désactive une carte.
     */
    public function setActive(int $id, bool $active): bool
    {
        return $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    // ----------------------------------------------------------------
    // Signature numérique
    // ----------------------------------------------------------------

    /**
     * Génère la signature HMAC-SHA256 d'une carte.
     * Le payload signé est : "{reference}|{playerId}|{spaceId}|{createdAt}"
     */
    public static function sign(
        string $reference,
        int    $playerId,
        int    $spaceId,
        string $createdAt
    ): string {
        $secret  = getenv('APP_KEY') ?: 'scores-default-insecure-key';
        $payload = "{$reference}|{$playerId}|{$spaceId}|{$createdAt}";
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Vérifie la validité de la signature d'une carte.
     */
    public static function verify(array $card): bool
    {
        $expected = self::sign(
            (string) $card['reference'],
            (int)    $card['player_id'],
            (int)    $card['space_id'],
            (string) $card['created_at']
        );
        return hash_equals($expected, (string) $card['signature']);
    }

    // ----------------------------------------------------------------
    // Utilitaires
    // ----------------------------------------------------------------

    /**
     * Construit une référence lisible et unique :
     * SC-YYYYMMDD-XXXXXXXX (8 hex majuscules aléatoires).
     */
    public static function buildReference(): string
    {
        return 'SC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Libellé lisible pour le rôle dans l'espace.
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'admin'   => 'Administrateur',
            'manager' => 'Manager',
            'member'  => 'Membre',
            'guest'   => 'Invité',
            default   => 'Joueur',
        };
    }
}
